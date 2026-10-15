<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Core\Community\Services\SignalementDeContenu;
use App\Core\Economy\Exceptions\SoldeInsuffisant;
use App\Core\FactChecking\Services\ModuleIndisponible;
use App\Core\FactChecking\Services\RegistreDesMoteurs;
use App\Core\FactChecking\Services\SoumissionVerificationService;
use App\Enums\TypeContenu;
use App\Enums\VerdictFactCheck;
use App\Http\Api\V1\Resources\VerificationResource;
use App\Http\Api\V1\Support\CodeErreur;
use App\Http\Api\V1\Support\Enveloppe;
use App\Http\Api\V1\Support\PageCurseur;
use App\Http\Api\V1\Support\TeleversementTemporaire;
use App\Jobs\AnalyserVerification;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * Soumettre une information, suivre son analyse, lire son rapport.
 *
 * Le cœur du produit, et donc l'endroit où l'on duplique le moins :
 * `SoumissionVerificationService` porte le tarif, le débit transactionnel,
 * l'empreinte de contenu et le rangement du fichier sur le disque privé. Le
 * contrôleur n'y ajoute que la limitation de débit — la même que l'écran web,
 * avec la même clé, pour que les deux parcours partagent réellement le quota.
 */
final class VerificationController
{
    /**
     * `POST /verifications`
     *
     * Le COÛT n'est jamais envoyé par le client : il est lu du type. Une
     * plateforme qui accepterait un prix venu du téléphone se ferait vider ses
     * quotas de moteurs en une nuit.
     */
    public function soumettre(
        Request $requete,
        SoumissionVerificationService $soumissions,
        RegistreDesMoteurs $registre,
    ): JsonResponse {
        $donnees = $requete->validate([
            'type' => ['required', 'string', 'in:'.implode(',', array_column(TypeContenu::cases(), 'value'))],
            'affirmation' => ['required_without:televersement', 'nullable', 'string', 'min:10', 'max:2000'],
            'contexte' => ['nullable', 'string', 'max:140'],
            // Identifiant rendu par `POST /uploads`. Le fichier monte AVANT la
            // soumission : sur un réseau instable, une soumission rejouée ne
            // doit pas renvoyer dix mégaoctets une seconde fois.
            'televersement' => ['nullable', 'string', 'uuid'],
        ]);

        $utilisateur = $this->utilisateur($requete);
        $type = TypeContenu::from($donnees['type']);

        /*
         | LE MODULE D'ABORD, LE FICHIER ENSUITE.
         |
         | L'ordre inverse faisait répondre « téléversez un fichier » pour un
         | type dont le module est fermé : le client mobile montait vingt-cinq
         | mégaoctets d'audio, puis se faisait refuser. Le service refuse de
         | toute façon, mais trop tard pour épargner l'envoi.
         */
        if (! $registre->moduleOuvert($type)) {
            return Enveloppe::erreur(
                CodeErreur::ActionImpossible,
                ModuleIndisponible::pour($type)->getMessage(),
            );
        }

        if ($type->estFichier() && ($donnees['televersement'] ?? null) === null) {
            return Enveloppe::erreur(CodeErreur::Validation, null, [
                'televersement' => ['Ce type de contenu exige un fichier téléversé au préalable.'],
            ]);
        }

        /*
         | Limitation de débit, en plus du coût en unités. Les deux ne
         | protègent pas de la même chose : les unités bornent la dépense d'une
         | personne, le débit borne la charge sur des services tiers facturés à
         | l'appel. La clé est IDENTIQUE à celle de l'écran web — sans quoi
         | quelqu'un doublerait son quota en alternant les deux clients.
         */
        $cle = 'verification:'.$utilisateur->id;
        $plafond = (int) config('factcheck.runtime.analyses_par_heure', 20);

        if (RateLimiter::tooManyAttempts($cle, $plafond)) {
            $secondes = RateLimiter::availableIn($cle);

            return Enveloppe::erreur(
                CodeErreur::DebitDepasse,
                'Vous avez lancé beaucoup d\'analyses. Réessayez dans '
                    .max(1, (int) ceil($secondes / 60)).' minute(s).',
                meta: ['retry_after' => $secondes],
            )->header('Retry-After', (string) $secondes);
        }

        $fichier = ($donnees['televersement'] ?? null) !== null
            ? TeleversementTemporaire::reprendre($utilisateur, (string) $donnees['televersement'])
            : null;

        if (($donnees['televersement'] ?? null) !== null && $fichier === null) {
            return Enveloppe::erreur(CodeErreur::Validation, null, [
                'televersement' => ['Ce téléversement a expiré. Renvoyez le fichier.'],
            ]);
        }

        try {
            $verification = $soumissions->soumettre(
                utilisateur: $utilisateur,
                type: $type,
                affirmation: $donnees['affirmation'] ?? ($fichier?->getClientOriginalName() ?? ''),
                contexte: $donnees['contexte'] ?? null,
                fichier: $fichier,
            );
        } catch (ModuleIndisponible $ferme) {
            /*
             | L'API acceptait TOUT type de `TypeContenu`, y compris vidéo et
             | audio, sans vérifier qu'un moteur sache les traiter. L'écran web
             | grisait les deux onglets ; l'application mobile, elle, débitait les
             | unités et lançait une analyse sur un fichier que nul moteur ne
             | pouvait transcrire.
             |
             | `ActionImpossible` et non `Validation` : la valeur envoyée est
             | parfaitement valide, c'est le module qui est fermé. Le client doit
             | pouvoir distinguer « votre requête est mal formée » de « cette
             | fonction n'est pas encore ouverte ».
             */
            return Enveloppe::erreur(CodeErreur::ActionImpossible, $ferme->getMessage());
        } catch (SoldeInsuffisant $manque) {
            // 402 plutôt qu'un 400 générique : le client sait alors proposer
            // une recharge au lieu d'afficher « une erreur est survenue ».
            return Enveloppe::erreur(CodeErreur::SoldeInsuffisant, $manque->messageUtilisateur());
        }

        // Compté APRÈS le succès : une soumission refusée faute de solde ne
        // doit pas consommer le quota horaire.
        RateLimiter::hit($cle, 3600);

        AnalyserVerification::dispatch($verification->id);

        return Enveloppe::donnee(
            VerificationResource::make($verification->loadMissing('report')),
            meta: ['solde' => $utilisateur->fresh()?->units],
            statut: 201,
        );
    }

    /**
     * `GET /verifications/{uuid}`
     *
     * Sert les DEUX écrans : le suivi d'analyse et le rapport final. Un seul
     * point d'entrée parce qu'il s'agit du même objet à deux moments de sa
     * vie, et que le client n'a pas à savoir lequel appeler avant d'avoir lu
     * l'état.
     *
     * Borné au PROPRIÉTAIRE, comme l'écran web : un uuid deviné ne doit
     * renseigner sur rien, pas même sur l'existence de la vérification —
     * d'où un 404 et non un 403.
     */
    public function afficher(Request $requete, Verification $verification): JsonResponse
    {
        if ($verification->user_id !== $this->utilisateur($requete)->id) {
            return Enveloppe::erreur(CodeErreur::Introuvable);
        }

        return Enveloppe::donnee(VerificationResource::make($verification->loadMissing('report')));
    }

    /**
     * `POST /verifications/{uuid}/contest` — demander une contre-expertise.
     *
     * ARBITRAGE (voir ARBITRAGES n° 71). La contestation N'EST PAS un
     * changement d'état de la vérification : laisser un citoyen basculer son
     * propre rapport en « arbitrage » offrirait à quiconque un bouton pour
     * effacer un verdict qui le dérange. Elle est enregistrée comme un
     * SIGNALEMENT polymorphe (`ContentReport`), c'est-à-dire dans la file de
     * modération que l'équipe traite déjà — un humain tranche, toujours.
     *
     * Idempotent par construction : `SignalementDeContenu` rend le
     * signalement existant plutôt que d'en empiler dix.
     */
    public function contester(
        Request $requete,
        Verification $verification,
        SignalementDeContenu $signalements,
    ): JsonResponse {
        if ($verification->user_id !== $this->utilisateur($requete)->id) {
            return Enveloppe::erreur(CodeErreur::Introuvable);
        }

        $donnees = $requete->validate([
            'motif' => ['nullable', 'string', 'in:'.implode(',', array_keys(SignalementDeContenu::motifs()))],
            'detail' => ['required', 'string', 'min:10', 'max:1000'],
        ]);

        $signalement = $signalements->signaler(
            $this->utilisateur($requete),
            $verification,
            $donnees['motif'] ?? 'autre',
            $donnees['detail'],
        );

        return Enveloppe::donnee([
            'uuid' => $signalement->uuid,
            'message' => 'Votre demande de contre-expertise a été transmise à la rédaction.',
        ], statut: 201);
    }

    /**
     * `GET /me/verifications`
     *
     * Filtres du prototype : verdict et recherche plein texte. La pagination
     * est par CURSEUR : sur une liste où la dernière analyse arrive en tête,
     * un décalage ferait sauter ou répéter des éléments à chaque chargement.
     */
    public function mesVerifications(Request $requete): JsonResponse
    {
        $donnees = $requete->validate([
            'verdict' => ['nullable', 'string', 'in:'.implode(',', array_column(VerdictFactCheck::cases(), 'value'))],
            'q' => ['nullable', 'string', 'max:120'],
            'limit' => ['nullable', 'integer'],
            'cursor' => ['nullable', 'string'],
        ]);

        $requeteSql = Verification::with('report')
            ->where('user_id', $this->utilisateur($requete)->id);

        if (($donnees['verdict'] ?? null) !== null) {
            $requeteSql->whereHas(
                'report',
                fn ($r) => $r->where('verdict', $donnees['verdict']),
            );
        }

        if (($donnees['q'] ?? null) !== null && trim((string) $donnees['q']) !== '') {
            $recherche = trim((string) $donnees['q']);
            $requeteSql->where(
                fn ($r) => $r->where('claim', 'like', '%'.$recherche.'%')
                    ->orWhere('context', 'like', '%'.$recherche.'%'),
            );
        }

        /*
         | Tri sur `id` et non sur `created_at` : deux vérifications soumises
         | dans la même seconde donneraient un curseur ambigu, et l'une des
         | deux disparaîtrait de la pagination. L'ordre reste celui du
         | prototype — la plus récente en tête.
         */
        $page = $requeteSql->orderByDesc('id')
            ->cursorPaginate(PageCurseur::taille($requete))
            ->withQueryString();

        return Enveloppe::page($page, VerificationResource::collection($page->items()));
    }

    private function utilisateur(Request $requete): User
    {
        /** @var User $utilisateur */
        $utilisateur = $requete->user();

        return $utilisateur;
    }
}
