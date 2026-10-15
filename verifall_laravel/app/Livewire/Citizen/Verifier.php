<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\Economy\Exceptions\SoldeInsuffisant;
use App\Core\FactChecking\Services\ModuleIndisponible;
use App\Core\FactChecking\Services\RegistreDesMoteurs;
use App\Core\FactChecking\Services\SoumissionVerificationService;
use App\Enums\StatutVerification;
use App\Enums\TypeContenu;
use App\Jobs\AnalyserVerification;
use App\Models\User;
use App\Models\Verification;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * « Vérifier une information » — page verifier.html du prototype.
 *
 * Le coût est ANNONCÉ avant, prélevé après : une personne doit savoir ce
 * qu'elle dépense avant de le dépenser, et le voir se refléter tout de suite.
 *
 * Le client n'envoie qu'un TYPE de contenu ; le tarif est lu côté serveur.
 */
#[Layout('components.layouts.citizen', ['title' => "Vérifier une information · Vérif'All"])]
final class Verifier extends Component
{
    use InteractsWithToasts;
    use WithFileUploads;

    /*
     | `#[Locked]` : le type ne se change que par `choisirType()`, qui refuse un
     | module fermé. Sans cet attribut, une requête Livewire forgée posait
     | `type = video` directement et contournait la garde — unités débitées,
     | analyse lancée sur un fichier que nul moteur ne peut transcrire.
     |
     | Ce n'est PAS la seule protection : le service refuse aussi, parce que
     | l'API mobile ne passe jamais par cet écran.
     */
    #[Locked]
    public string $type = 'text';

    public string $texte = '';

    public string $lien = '';

    public string $contexte = '';

    public $fichier = null;

    /** Vérification en cours de traitement, suivie par l'écran d'attente. */
    public ?string $enCours = null;

    public function render(SoumissionVerificationService $soumissions, RegistreDesMoteurs $registre): View
    {
        $utilisateur = $this->utilisateur();

        return view('livewire.citizen.verifier', [
            // Les types dont le module est fermé : le bouton reste VISIBLE mais
            // inactif. Le retirer laisserait croire que la plateforme n'analyse
            // pas la vidéo ; il s'agit d'une indisponibilité, pas d'un périmètre.
            'typesFermes' => array_values(array_map(
                static fn (TypeContenu $t): string => $t->value,
                array_filter(
                    TypeContenu::cases(),
                    fn (TypeContenu $t): bool => ! $this->moduleDisponible($t, $registre),
                ),
            )),

            'utilisateur' => $utilisateur,
            'types' => TypeContenu::cases(),
            'typeChoisi' => $this->typeChoisi(),
            'cout' => $this->typeChoisi()->cout(),
            'soldeApres' => max(0, $utilisateur->units - $this->typeChoisi()->cout()),
            'soldeSuffisant' => $soumissions->peutSoumettre($utilisateur, $this->typeChoisi()),
            'verification' => $this->verification(),
        ]);
    }

    public function choisirType(string $type, RegistreDesMoteurs $registre): void
    {
        $choisi = TypeContenu::tryFrom($type);

        // Un type inconnu vient d'un client modifié : on n'invente pas de tarif.
        if ($choisi === null) {
            return;
        }

        /*
         | MODULE FERMÉ : on le DIT, et on ne change pas de type.
         |
         | Vidéo et audio dépendent d'un moteur de transcription, et aucun
         | fournisseur n'est souscrit. Laisser choisir le type ferait débiter
         | une unité pour un rapport vide : Gemini et la recherche web figurent
         | bien au catalogue de ces types, mais ils travaillent sur du TEXTE, et
         | sans transcription il n'y a pas de texte.
         |
         | La condition est déduite, jamais codée en dur : le jour où un
         | fournisseur est souscrit et le moteur rallumé dans les réglages, les
         | deux boutons se rouvrent d'eux-mêmes.
         */
        if (! $this->moduleDisponible($choisi, $registre)) {
            $this->toastWarning(sprintf(
                'Module %s indisponible pour le moment.',
                mb_strtolower($choisi->libelle()),
            ));

            return;
        }

        $this->type = $type;
        $this->resetErrorBag();
    }

    /**
     * Le type est-il réellement analysable aujourd'hui ?
     *
     * Un type sans moteur indispensable l'est toujours ; sinon, il faut que ce
     * moteur soit allumé dans les réglages ET porté.
     */
    /**
     * Délégué au registre : une seule définition pour l'écran, le service et l'API.
     *
     * La règle vivait ICI, et n'était donc opposable qu'à cet écran. Voir
     * {@see RegistreDesMoteurs::moduleOuvert()}.
     */
    public function moduleDisponible(TypeContenu $type, RegistreDesMoteurs $registre): bool
    {
        return $registre->moduleOuvert($type);
    }

    public function lancer(SoumissionVerificationService $soumissions): void
    {
        $type = $this->typeChoisi();
        $donnees = $this->validate($this->regles($type), $this->messages());

        $utilisateur = $this->utilisateur();

        /*
         | Limitation de débit, en plus du coût en unités. Les deux ne protègent
         | pas de la même chose : les unités bornent la dépense d'une personne,
         | le débit borne la charge sur des services tiers facturés à l'appel.
         | Un compte rechargé pourrait sinon lancer mille analyses en une
         | minute et épuiser les quotas de toute la plateforme.
         */
        $cle = 'verification:'.$utilisateur->id;
        $plafond = (int) config('factcheck.runtime.analyses_par_heure', 20);

        if (RateLimiter::tooManyAttempts($cle, $plafond)) {
            $minutes = (int) ceil(RateLimiter::availableIn($cle) / 60);

            $this->toastWarning(
                'Vous avez lancé beaucoup d\'analyses. Réessayez dans '
                .max(1, $minutes).' minute'.($minutes > 1 ? 's' : '').'.'
            );

            return;
        }

        try {
            $verification = $soumissions->soumettre(
                utilisateur: $utilisateur,
                type: $type,
                affirmation: $this->affirmation($type, $donnees),
                contexte: $this->contexte,
                fichier: $type->estFichier() ? $this->fichier : null,
            );
        } catch (ModuleIndisponible $ferme) {
            /*
             | Second filet. `choisirType()` refuse déjà le clic et `#[Locked]`
             | empêche l'écriture directe de la propriété — mais la règle est
             | dans le service, et c'est lui qui a le dernier mot. Ce rattrapage
             | existe pour que son refus s'affiche comme un message, jamais
             | comme une erreur 500.
             */
            $this->toastWarning($ferme->getMessage());

            return;
        } catch (SoldeInsuffisant $manque) {
            // Le message dit ce qu'il manque et quoi faire, pas « erreur ».
            $this->toastError($manque->messageUtilisateur());

            return;
        }

        // Compte APRES le succes : une soumission refusee faute de solde ne
        // doit pas consommer le quota horaire.
        RateLimiter::hit($cle, 3600);

        AnalyserVerification::dispatch($verification->id);

        /*
         | Le témoin d'analyse de l'en-tête (B-03) s'allume ici, et pas à la
         | lecture : sans ce drapeau, il devrait interroger la base à chaque
         | affichage des vingt-cinq écrans citoyens pour n'afficher presque
         | jamais rien. C'est `AnalyseEnCours` qui l'éteint, quand la base
         | confirme qu'il ne reste plus rien en cours.
         */
        session()->put(AnalyseEnCours::CLE_SESSION, true);

        $this->enCours = $verification->uuid;
        $this->reset(['texte', 'lien', 'contexte', 'fichier']);

        // Le solde est affiche aussi dans l'en-tete, hors du composant : sans
        // cette annonce, l'ecran montrerait deux soldes differents.
        $this->dispatch('solde-change', solde: $utilisateur->fresh()->units);

        $this->toastSuccess('Analyse lancée.');

        // Le parcours du prototype : verifier -> analyse en cours -> rapport.
        // L'encart de suivi sur cette page ne remplacait pas l'ecran dedie, qui
        // montre les etapes franchies.
        $this->redirectRoute('citizen.verification', ['verification' => $verification->uuid], navigate: true);
    }

    /**
     * Interroge l'avancement — l'écran d'attente appelle ceci toutes les
     * 3 secondes.
     */
    public function rafraichir(): void
    {
        $verification = $this->verification();

        if ($verification === null || ! $verification->estTerminee()) {
            return;
        }

        $this->enCours = null;

        if ($verification->status === StatutVerification::Echouee) {
            $this->toastError('L\'analyse n\'a pas abouti. Vos unités vous sont rendues.');

            return;
        }

        $this->toastSuccess('Votre rapport est prêt.');
    }

    public function fermerSuivi(): void
    {
        $this->enCours = null;
    }

    /**
     * @return array<string, array<int, mixed>|string>
     */
    private function regles(TypeContenu $type): array
    {
        $communes = ['contexte' => ['nullable', 'string', 'max:140']];

        return match ($type) {
            // 1000 caractères, comme le compteur du prototype.
            TypeContenu::Texte => $communes + ['texte' => ['required', 'string', 'min:15', 'max:1000']],

            TypeContenu::Lien => $communes + ['lien' => ['required', 'url:http,https', 'max:500']],

            TypeContenu::Image => $communes + [
                'fichier' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
            ],

            TypeContenu::Video => $communes + [
                'fichier' => ['required', 'file', 'mimes:mp4,webm,mov', 'max:102400'],
            ],

            TypeContenu::Audio => $communes + [
                'fichier' => ['required', 'file', 'mimes:mp3,wav,ogg,m4a', 'max:25600'],
            ],
        };
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'texte.required' => 'Collez le message ou l\'affirmation à vérifier.',
            'texte.min' => 'Donnez-nous un peu plus de matière : au moins 15 caractères.',
            'texte.max' => 'Le texte dépasse 1 000 caractères.',
            'lien.required' => 'Collez le lien à vérifier.',
            'lien.url' => 'Ce lien ne semble pas valide.',
            'fichier.required' => 'Choisissez un fichier à analyser.',
            'fichier.mimes' => 'Ce format de fichier n\'est pas accepté.',
            'fichier.max' => 'Ce fichier est trop lourd.',
            'contexte.max' => 'Le contexte dépasse 140 caractères.',
        ];
    }

    /**
     * @param  array<string, mixed>  $donnees
     */
    private function affirmation(TypeContenu $type, array $donnees): string
    {
        return match ($type) {
            TypeContenu::Texte => (string) $donnees['texte'],
            TypeContenu::Lien => (string) $donnees['lien'],
            // Pour un média, l'affirmation est le contexte donné — ou, à
            // défaut, une mention explicite. Laisser le champ vide priverait le
            // rapport de tout intitulé.
            default => $this->contexte !== ''
                ? $this->contexte
                : 'Média '.$type->libelle().' soumis sans contexte',
        };
    }

    private function typeChoisi(): TypeContenu
    {
        return TypeContenu::tryFrom($this->type) ?? TypeContenu::Texte;
    }

    private function verification(): ?Verification
    {
        if ($this->enCours === null) {
            return null;
        }

        // Bornée au propriétaire : un uuid deviné ne renseigne sur rien.
        return Verification::where('uuid', $this->enCours)
            ->where('user_id', $this->utilisateur()->id)
            ->first();
    }

    private function utilisateur(): User
    {
        $utilisateur = Auth::guard('web')->user();

        abort_if($utilisateur === null, 403);

        return $utilisateur;
    }
}
