<?php

declare(strict_types=1);

namespace App\Core\Lms\Services;

use App\Enums\StatutDevoir;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Mews\Purifier\Facades\Purifier;
use RuntimeException;

/**
 * Rendre un exercice, et le rendre une seule fois.
 *
 * Un exercice corrigé par un mentor n'est pas un quiz : il n'y a pas de reprise
 * automatique. Renvoyer par-dessus une copie déjà corrigée effacerait le travail
 * du correcteur — donc on refuse, et on le dit.
 */
final class RemiseDExercice
{
    /** En deçà, ce n'est pas une analyse, c'est une case cochée. */
    public const CARACTERES_MINIMUM = 30;

    /** Ce que la modale annonce : PDF, ZIP ou image, 10 Mo. */
    public const OCTETS_MAXIMUM = 10 * 1024 * 1024;

    public function estOuvert(User $utilisateur, Assignment $exercice): bool
    {
        return Enrollment::where('user_id', $utilisateur->id)
            ->where('course_id', $exercice->course_id)
            ->exists();
    }

    public function remiseDe(User $utilisateur, Assignment $exercice): ?AssignmentSubmission
    {
        return AssignmentSubmission::where('user_id', $utilisateur->id)
            ->where('assignment_id', $exercice->id)
            ->first();
    }

    /**
     * @throws RuntimeException si le cours n'est pas suivi ou la copie déjà rendue
     */
    public function remettre(
        User $utilisateur,
        Assignment $exercice,
        string $reponse,
        ?UploadedFile $fichier = null,
    ): AssignmentSubmission {
        if (! $this->estOuvert($utilisateur, $exercice)) {
            throw new RuntimeException('Inscrivez-vous au cours pour rendre cet exercice.');
        }

        if ($this->remiseDe($utilisateur, $exercice) !== null) {
            throw new RuntimeException('Cet exercice a déjà été soumis.');
        }

        if (mb_strlen(trim(strip_tags($reponse))) < self::CARACTERES_MINIMUM) {
            throw new RuntimeException(
                'Votre réponse est trop courte ('.self::CARACTERES_MINIMUM.' caractères minimum).'
            );
        }

        return DB::transaction(function () use ($utilisateur, $exercice, $reponse, $fichier): AssignmentSubmission {
            $chemin = null;

            if ($fichier !== null) {
                /*
                 | Disque privé et nom aléatoire : le nom d'origine peut porter
                 | l'identité de la personne, et un fichier deviné par son URL
                 | est un fichier public.
                 */
                $chemin = $fichier->storeAs(
                    'devoirs/'.$exercice->uuid,
                    Str::uuid()->toString().'.'.$fichier->getClientOriginalExtension(),
                    'private',
                );
            }

            return AssignmentSubmission::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $utilisateur->id,
                'assignment_id' => $exercice->id,
                'status' => StatutDevoir::Remis->value,
                // Le contenu est purifié AVANT d'être rangé : ce texte sera
                // relu par un mentor, dans le back-office.
                'note' => trim((string) Purifier::clean($reponse, 'default')),
                'file_path' => $chemin,
                'file_name' => $fichier?->getClientOriginalName(),
            ]);
        });
    }
}
