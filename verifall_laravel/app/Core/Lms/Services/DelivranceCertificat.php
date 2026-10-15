<?php

declare(strict_types=1);

namespace App\Core\Lms\Services;

use App\Models\Certificate;
use App\Models\Course;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Délivre un certificat quand un cours est achevé.
 *
 * Le code public est ce qui circule sur un CV, et il sera saisi par des
 * inconnus sur la page de vérification. Trois exigences en découlent :
 *
 *   · Il doit être IMPOSSIBLE À DEVINER. Un numéro séquentiel permettrait
 *     d'énumérer les diplômés, et d'en fabriquer un plausible.
 *   · Il doit être LISIBLE À VOIX HAUTE et recopiable sans faute. D'où
 *     l'alphabet réduit : ni O ni 0, ni I ni 1, ni S ni 5.
 *   · Il ne doit être délivré QU'UNE FOIS. Deux codes pour un même diplôme, et
 *     personne ne sait plus lequel fait foi.
 */
final class DelivranceCertificat
{
    /**
     * Alphabet sans caractères confondables.
     *
     * 28 symboles sur 8 positions font environ 3,8 × 10^11 combinaisons : une
     * énumération est hors de portée, et le code reste dictable au téléphone.
     */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRTUVWXYZ2346789';

    /**
     * Délivre, ou rend celui qui existe déjà.
     *
     * Idempotent par nature : la complétion d'un cours est recalculée à chaque
     * événement, et sans cette garde un quiz repassé rééditerait un diplôme.
     */
    public function delivrer(User $utilisateur, Course $cours): ?Certificate
    {
        if (! $cours->delivers_certificate) {
            return null;
        }

        $existant = Certificate::where('user_id', $utilisateur->id)
            ->where('course_id', $cours->id)
            ->first();

        if ($existant !== null) {
            return $existant;
        }

        return Certificate::create([
            'uuid' => (string) Str::uuid(),
            'code' => $this->codeUnique(),
            'user_id' => $utilisateur->id,
            'course_id' => $cours->id,
            // Recopiés, jamais lus par jointure : un certificat atteste de ce
            // qui a été suivi CE jour-là. Si le cours est renommé ou le compte
            // anonymisé, le document déjà délivré ne doit ni mentir, ni
            // redevenir nominatif.
            'holder_name' => $utilisateur->name,
            'course_title' => $cours->title,
            'issued_at' => now(),
        ]);
    }

    /**
     * Un code libre, vérifié en base.
     *
     * La boucle n'est pas de la superstition : sur un alphabet fini, une
     * collision reste possible, et une contrainte d'unicité violée renverrait
     * une exception au visage de quelqu'un qui vient de finir un cours.
     */
    private function codeUnique(): string
    {
        do {
            $code = 'VERIF-'.$this->bloc(4).'-'.$this->bloc(4);
        } while (Certificate::where('code', $code)->exists());

        return $code;
    }

    private function bloc(int $longueur): string
    {
        $sortie = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < $longueur; $i++) {
            // random_int et non rand() : un générateur prévisible rendrait les
            // codes devinables, ce qui est exactement ce qu'on veut éviter.
            $sortie .= self::ALPHABET[random_int(0, $max)];
        }

        return $sortie;
    }
}
