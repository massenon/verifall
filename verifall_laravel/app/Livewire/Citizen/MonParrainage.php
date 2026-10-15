<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\Marketing\Services\Parrainage;
use App\Enums\MotifMouvementUnite;
use App\Models\Referral;
use App\Models\UnitLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * « Mon Parrainage » — transposition de compte-user/parrainage.html.
 *
 * Le prototype affiche quatre indicateurs dont « Invitations : 12 ». Un envoi
 * WhatsApp ne se constate pas depuis un serveur : l'indicateur compte donc les
 * OUVERTURES du lien, seule mesure honnête de « combien de personnes ont vu mon
 * invitation ». Le libellé le dit.
 */
#[Layout('components.layouts.citizen', ['title' => "Mon Parrainage · Vérif'All"])]
final class MonParrainage extends Component
{
    public function render(Parrainage $parrainage): View
    {
        $utilisateur = $this->utilisateur();

        return view('livewire.citizen.mon-parrainage', [
            'code' => $parrainage->codeDe($utilisateur),
            'lien' => $parrainage->lienDe($utilisateur),
            'indicateurs' => $parrainage->indicateurs($utilisateur),
            'filleuls' => $this->filleuls(),
            /*
             | Le barème vient des RÉGLAGES, plus des constantes de classe.
             | L'écran qui annonce doit lire ce que le service verse : annoncer
             | 100 pendant qu'on en crédite 10 n'est pas un bug d'affichage,
             | c'est une promesse non tenue sur un programme de recommandation.
             */
            'pointsParrain' => $parrainage->unitesParrain(),
            'pointsFilleul' => $parrainage->unitesFilleul(),
            'historique' => $this->historique($utilisateur),
            'prochainPalier' => $this->prochainPalier($utilisateur),
            'messageDInvitation' => $this->messageDInvitation($parrainage->lienDe($utilisateur)),
            /*
             | Le versant FILLEUL, absent jusqu'ici. Il vaut null pour qui n'a
             | pas été parrainé, et le bloc ne s'affiche alors pas du tout —
             | plutôt qu'un encart vide qui laisserait croire à une panne.
             */
            'monParrainage' => $parrainage->vuDuFilleul($utilisateur),
            /*
             | Ce qui ATTEND un filleul non encore récompensé — donc le barème
             | courant, une promesse sur l'avenir. À ne pas confondre avec
             | `referred_points`, que la vue lit une fois la récompense versée :
             | celui-là est historisé et ne bouge plus, quoi qu'on règle ensuite.
             */
            'pointsFilleulRecus' => $parrainage->unitesFilleul(),
        ]);
    }

    /**
     * Les récompenses de parrainage réellement versées.
     *
     * Lues dans le journal des unités, seule source qui fasse foi. Le prototype
     * en affiche trois en dur ; montrer une récompense qui n'a pas été versée
     * serait une promesse, sur l'écran même où l'on explique les conditions.
     *
     * @return Collection<int, UnitLog>
     */
    private function historique(User $utilisateur): Collection
    {
        return UnitLog::where('user_id', $utilisateur->id)
            ->where('reason', MotifMouvementUnite::Parrainage->value)
            ->where('delta', '>', 0)
            ->latest()
            ->limit(5)
            ->get();
    }

    /**
     * La progression vers le prochain palier de parrainage.
     *
     * @return array{acquis: int, objectif: int, restant: int, pourcentage: int}|null
     */
    private function prochainPalier(User $utilisateur): ?array
    {
        $acquis = Referral::where('referrer_id', $utilisateur->id)
            ->whereNotNull('rewarded_at')
            ->count();

        $objectif = (int) (floor($acquis / Parrainage::PALIER) + 1) * Parrainage::PALIER;

        return [
            'acquis' => $acquis,
            'objectif' => $objectif,
            'restant' => $objectif - $acquis,
            'pourcentage' => (int) round($acquis % Parrainage::PALIER * 100 / Parrainage::PALIER),
        ];
    }

    /** Le message prérempli des boutons de partage. */
    private function messageDInvitation(string $lien): string
    {
        return "Découvrez Vérif'All, une plateforme qui vous aide à vérifier les "
            .'informations avant de les partager. Inscrivez-vous avec mon '
            .'invitation et profitez de votre avantage de bienvenue : '.$lien;
    }

    /**
     * @return Collection<int, Referral>
     */
    private function filleuls(): Collection
    {
        return Referral::with('referred:id,name')
            ->where('referrer_id', $this->utilisateur()->id)
            ->latest('id')
            ->get();
    }

    private function utilisateur(): User
    {
        $utilisateur = Auth::guard('web')->user();

        abort_if($utilisateur === null, 403);

        return $utilisateur;
    }
}
