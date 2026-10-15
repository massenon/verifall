<?php

declare(strict_types=1);

namespace App\Core\Lms\Services;

use App\Core\Economy\Exceptions\SoldeInsuffisant;
use App\Core\Economy\Services\EconomyService;
use App\Core\Lms\Exceptions\CoursIndisponible;
use App\Enums\MotifMouvementUnite;
use App\Enums\StatutInscription;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * S'inscrire à un cours — gratuitement, ou en dépensant des unités.
 *
 * Un seul endroit débite pour l'Académie, et il est verrouillé. Trois pièges
 * ont été traités explicitement, parce qu'ils coûtent tous de l'argent à
 * quelqu'un :
 *
 *   · LE DOUBLE-CLIC. « S'inscrire » cliqué deux fois débiterait deux fois. Le
 *     verrou de ligne et l'index unique le rendent impossible, et la seconde
 *     tentative rend simplement l'inscription existante — pas une erreur, car
 *     du point de vue de la personne le résultat voulu est atteint.
 *   · LE PRIX QUI BOUGE. Ce qui est débité est figé dans `paid_units`. Sans
 *     cela, un remboursement se calculerait au tarif du jour.
 *   · LE COURS OFFERT. Le tarif est relu au moment du débit, jamais celui que
 *     la page affichait il y a dix minutes.
 */
final class InscriptionAuCours
{
    public function __construct(private readonly EconomyService $economie) {}

    /**
     * @throws CoursIndisponible si le cours n'est pas publié
     * @throws SoldeInsuffisant si les unités manquent
     */
    public function inscrire(User $utilisateur, Course $cours): Enrollment
    {
        if (! $cours->is_published) {
            throw new CoursIndisponible('Ce cours n\'est pas encore ouvert aux inscriptions.');
        }

        return DB::transaction(function () use ($utilisateur, $cours): Enrollment {
            /*
             | Verrou sur la ligne d'inscription s'il y en a une. Deux requêtes
             | concurrentes — le double-clic — se sérialisent ici : la seconde
             | voit l'inscription de la première et repart sans débiter.
             */
            $existante = Enrollment::where('user_id', $utilisateur->id)
                ->where('course_id', $cours->id)
                ->lockForUpdate()
                ->first();

            if ($existante !== null) {
                return $existante;
            }

            // Relu MAINTENANT : le cours a pu devenir offert, ou cesser de
            // l'être, depuis l'affichage de la page.
            $cout = $cours->coutActuel();

            if ($cout > 0) {
                $this->economie->debiter(
                    $utilisateur,
                    $cout,
                    MotifMouvementUnite::CoursPremium,
                    'Inscription — '.$cours->title,
                    $cours,
                );
            }

            $inscription = Enrollment::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $utilisateur->id,
                'course_id' => $cours->id,
                'status' => StatutInscription::Inscrit->value,
                'progress' => 0,
                'paid_units' => $cout,
                'started_at' => now(),
                'last_activity_at' => now(),
            ]);

            // Compteur DÉNORMALISÉ tenu à jour ici, sous la même transaction :
            // recalculé à l'affichage, il ferait un agrégat par carte du
            // catalogue, sur la page la plus visitée de l'Académie.
            $cours->increment('students_count');

            return $inscription;
        });
    }

    public function inscriptionDe(User $utilisateur, Course $cours): ?Enrollment
    {
        return Enrollment::where('user_id', $utilisateur->id)
            ->where('course_id', $cours->id)
            ->first();
    }

    public function estInscrit(User $utilisateur, Course $cours): bool
    {
        return $this->inscriptionDe($utilisateur, $cours) !== null;
    }
}
