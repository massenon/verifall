<?php

declare(strict_types=1);

namespace App\Core\Lms\Services;

use App\Models\Lesson;
use App\Models\LessonAttachment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Les documents joints à une leçon : dépôt, retrait, et droit d'accès.
 *
 * ## Le disque est PRIVÉ, et ce n'est pas une précaution de trop
 *
 * Un cours payant dont les PDF sont servis par une URL devinable est un cours
 * vendu une fois et diffusé cent. Les fichiers vivent hors du site, sous un nom
 * aléatoire, et ne transitent que par un contrôleur qui vérifie l'inscription.
 *
 * C'est le même dispositif que les pièces KYC, pour une raison différente : là
 * c'était la vie privée, ici c'est la valeur du cours.
 *
 * ## Les types acceptés forment une liste FERMÉE
 *
 * Un formateur dépose des supports, pas des exécutables. La liste couvre ce que
 * demande le porteur du projet — PDF, tableur, document, image — et rien de
 * plus. Accepter « tout sauf ce qui est dangereux » est une course perdue :
 * l'inverse se maintient.
 */
final class PiecesJointesDeLecon
{
    /** Le disque privé. Jamais `public` : ces fichiers ne doivent avoir aucune URL. */
    private const DISQUE = 'local';

    private const DOSSIER = 'supports-de-cours';

    /** 20 Mo : un support de cours illustré passe, une vidéo non — elle a son champ. */
    public const TAILLE_MAXIMUM_KO = 20480;

    /**
     * Les extensions acceptées, pour la règle de validation `mimes:`.
     *
     * @var list<string>
     */
    public const EXTENSIONS = [
        'pdf',
        'doc', 'docx', 'odt',
        'xls', 'xlsx', 'ods', 'csv',
        'ppt', 'pptx', 'odp',
        'jpg', 'jpeg', 'png', 'webp',
        'zip',
    ];

    /**
     * Attache un document à une leçon.
     *
     * Le nom d'origine est conservé pour l'AFFICHAGE seulement : le fichier sur
     * le disque porte un uuid. Sans lui, la liste montrerait des identifiants et
     * personne ne saurait lequel ouvrir ; avec lui comme nom de fichier, un
     * « cours-premium-module-3.pdf » sur un disque partagé se devinerait.
     */
    public function attacher(Lesson $lecon, UploadedFile $fichier): LessonAttachment
    {
        $extension = mb_strtolower($fichier->extension());

        if (! in_array($extension, self::EXTENSIONS, true)) {
            throw new RuntimeException(
                'Ce type de fichier n’est pas accepté. Formats admis : '
                .implode(', ', self::EXTENSIONS).'.'
            );
        }

        $chemin = $fichier->storeAs(
            self::DOSSIER,
            Str::uuid().'.'.$extension,
            ['disk' => self::DISQUE],
        );

        return LessonAttachment::create([
            'uuid' => (string) Str::uuid(),
            'lesson_id' => $lecon->getKey(),
            'path' => $chemin,
            // `getClientOriginalName()` vient du navigateur : il est nettoyé
            // avant d'être stocké, et ne sert JAMAIS à composer un chemin.
            'original_name' => Str::limit(
                preg_replace('/[^\p{L}\p{N}\s\.\-_]+/u', '', $fichier->getClientOriginalName()) ?: 'document',
                120,
                '',
            ),
            'mime' => (string) $fichier->getMimeType(),
            'size_bytes' => $fichier->getSize(),
            'position' => (int) LessonAttachment::query()
                ->where('lesson_id', $lecon->getKey())->max('position') + 10,
        ]);
    }

    /** Retire un document, du disque ET de la base. */
    public function detacher(LessonAttachment $piece): void
    {
        Storage::disk(self::DISQUE)->delete($piece->path);

        $piece->delete();
    }

    /**
     * Cette personne a-t-elle le droit d'ouvrir ce document ?
     *
     * ## Trois portes, et une seule suffit
     *
     * 1. **L'inscription au cours.** C'est le cas normal : on a payé, ou le
     *    cours est gratuit, et on suit.
     * 2. **Le formateur du cours.** Il doit pouvoir relire ce qu'il a déposé
     *    sans s'inscrire à son propre cours.
     * 3. **Une habilitation `lesson.view`.** L'équipe pédagogique relit les
     *    supports avant publication.
     *
     * Un cours GRATUIT ne dispense pas de l'inscription : c'est elle qui
     * mesure la progression, et laisser télécharger sans elle rendrait les
     * statistiques du cours fausses.
     */
    public function peutOuvrir(LessonAttachment $piece, ?User $personne): bool
    {
        if ($personne === null) {
            return false;
        }

        $cours = $piece->lecon?->course;

        if ($cours === null) {
            return false;
        }

        if ($personne->can('lesson.view')) {
            return true;
        }

        if ($cours->instructor_id === $personne->getKey()) {
            return true;
        }

        return $cours->enrollments()
            ->where('user_id', $personne->getKey())
            ->exists();
    }

    /** Le contenu du fichier, pour le contrôleur qui le sert. */
    public function chemin(LessonAttachment $piece): string
    {
        return $piece->path;
    }

    public function disque(): string
    {
        return self::DISQUE;
    }
}
