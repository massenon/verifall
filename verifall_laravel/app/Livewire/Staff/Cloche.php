<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Component;

/**
 * La cloche du back-office — adossée à des données, enfin.
 *
 * ## Ce qu'elle remplace
 *
 * Quatre notifications écrites en dur dans `layouts/staff.blade.php`, et une
 * pastille rouge portant le chiffre **3**, littéralement tapé dans le gabarit.
 * Chaque membre de l'équipe, sur chaque page du back-office, voyait « 12
 * nouveaux signalements », « Dossier KYC-2214 soumis », « il y a 8 min ».
 *
 * Le même défaut avait déjà été corrigé côté citoyen — la cloche y lisait un
 * magasin `localStorage` — mais l'administration était restée sur son décor de
 * prototype.
 *
 * ## Pourquoi c'est plus grave ici que côté citoyen
 *
 * Une pastille allumée en permanence n'est pas seulement inutile : elle
 * **désapprend à la regarder**. Le jour où douze signalements arrivent
 * réellement, plus personne ne distingue ce chiffre de celui d'hier. Sur un
 * back-office de modération, la cloche est un instrument de travail.
 *
 * ## Ce que la cloche affiche, et ce qu'elle n'invente pas
 *
 * La table `notifications` est celle de Laravel, partagée avec l'espace
 * citoyen ; les notifications d'un membre du personnel y sont rattachées à son
 * compte, comme les autres. **Tant que rien ne lui en adresse, la cloche est
 * vide** — et elle le dit, plutôt que de meubler.
 *
 * C'est un état correct, pas un manque : les producteurs de notifications
 * destinées au personnel (signalements, KYC, sauvegardes) arriveront avec les
 * écrans qui les concernent. La cloche sera prête ; elle ne promet rien
 * d'avance.
 */
final class Cloche extends Component
{
    /**
     * Combien on en montre dans le menu déroulant.
     *
     * Le même plafond que côté citoyen, et pour la même raison : la cloche est
     * sur toutes les pages, et charger l'historique entier pour en afficher
     * dix se paie à chaque affichage.
     */
    private const PLAFOND = 15;

    public function toutMarquerLu(): void
    {
        Auth::guard('staff')->user()?->unreadNotifications()->update(['read_at' => now()]);
    }

    public function render(): View
    {
        return view('livewire.staff.cloche', [
            'notifications' => $this->notifications(),
            'nonLues' => $this->nonLues(),
        ]);
    }

    /**
     * @return Collection<int, object>
     */
    private function notifications(): Collection
    {
        $membre = Auth::guard('staff')->user();

        if ($membre === null) {
            return collect();
        }

        return $membre->notifications()->latest()->limit(self::PLAFOND)->get();
    }

    private function nonLues(): int
    {
        return (int) (Auth::guard('staff')->user()?->unreadNotifications()->count() ?? 0);
    }
}
