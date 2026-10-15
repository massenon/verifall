<?php

declare(strict_types=1);

namespace App\Livewire\Public;

use App\Core\FactChecking\Services\OrchestrateurVerification;
use App\Core\FactChecking\Services\QrCodeRapport;
use App\Core\FactChecking\Services\RenduDeSynthese;
use App\Core\Marketing\Seo\Referencement;
use App\Models\Verification;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Un rapport de vérification, consultable sans compte.
 *
 * ## Pourquoi cette page existe
 *
 * Le rapport imprimé porte un QR de contrôle. Celui qui le scanne — un
 * confrère journaliste, un employeur, le parent à qui l'on montre que la
 * rumeur est fausse — n'a pas de session sur Vérif'All. Sans cette page, le
 * code mènerait à un écran de connexion, et le contrôle promis par le document
 * papier serait une promesse creuse.
 *
 * C'est aussi l'adresse que porte le partage WhatsApp : un démenti que seuls
 * les inscrits peuvent lire ne dément rien là où l'infox circule.
 *
 * ## Les quatre gardes, et ce que chacune protège
 *
 * 1. **Rapport PUBLIÉ uniquement.** `published_at` n'est renseigné que
 *    lorsqu'une analyse aboutit à un verdict tenable ({@see OrchestrateurVerification}) :
 *    une analyse en cours, partielle, ou soumise à arbitrage humain n'a rien à
 *    faire en libre accès. Absence de rapport publié → 404, et non 403 : un
 *    403 confirmerait qu'un rapport existe.
 * 2. **Aucune donnée personnelle.** Ni nom, ni adresse, ni avatar, ni
 *    identifiant du demandeur — ce composant ne charge même pas la relation
 *    `user`, pour qu'aucun ajout ultérieur dans le gabarit ne puisse en faire
 *    ressortir quoi que ce soit.
 * 3. **Le contexte n'est pas publié.** « Où avez-vous vu cette information ? »
 *    est un champ libre où les gens écrivent le nom d'un proche ou un numéro.
 *    Le gabarit partagé l'omet dès que `public` vaut vrai.
 * 4. **Pas d'indexation.** `indexable: false` pose `noindex` dans la page, et
 *    la route porte l'en-tête `X-Robots-Tag`. Sans cela, les soumissions des
 *    citoyens deviendraient un corpus de pages indexées par Google — chacune
 *    reprenant en titre l'affirmation fausse qu'elle dément.
 */
#[Layout('components.layouts.public')]
final class RapportPublic extends Component
{
    public string $uuid = '';

    public function mount(string $verification): void
    {
        $this->uuid = $verification;

        // Éprouvé au montage : une adresse inventée doit rendre 404 tout de
        // suite, pas à la première interaction.
        $this->verification();
    }

    public function render(RenduDeSynthese $rendu, QrCodeRapport $qr): View
    {
        $verification = $this->verification();
        $rapport = $verification->report;
        $moteurs = $verification->engineRuns()->orderBy('id')->get();

        return view('livewire.public.rapport-public', [
            'verification' => $verification,
            'rapport' => $rapport,
            'moteurs' => $moteurs,
            'sources' => $verification->evidences()->orderByDesc('reliability')->get(),
            'haine' => $rapport->indiceHaine(),
            // Le verdict accompagne le texte : un raisonnement qui le
            // contredit n'est pas affichable. Voir RenduDeSynthese::pour().
            //
            // `verdict` est NULLABLE dès qu'un rapport est MIXTE (contrat
            // V-8) : voir le même garde posé dans `Citizen\Rapport.php`.
            'synthese' => $rapport->verdict !== null ? ($rendu->pour($moteurs, $rapport->verdict) ?: null) : null,
            'dissidents' => $rapport->verdict !== null
                ? $moteurs->filter(fn ($m): bool => ! $m->failed && $m->verdict !== null && $m->verdict !== $rapport->verdict)->count()
                : 0,
            'qrCode' => $qr->svg($verification),
            'lienPublic' => $qr->adresse($verification),
        ])->layoutData(['seo' => app(Referencement::class)->pourRapportPublic($verification)]);
    }

    /**
     * Le rapport demandé, s'il est publié.
     *
     * `firstOrFail()` sur la jointure plutôt qu'un `abort_if` après coup : la
     * condition de publication est dans la REQUÊTE, donc il n'existe aucun
     * chemin où un rapport non publié serait chargé puis écarté.
     */
    private function verification(): Verification
    {
        return Verification::query()
            ->with('report')
            ->whereHas('report', fn ($q) => $q->whereNotNull('published_at'))
            ->where('uuid', $this->uuid)
            ->firstOrFail();
    }
}
