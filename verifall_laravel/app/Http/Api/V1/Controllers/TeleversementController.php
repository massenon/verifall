<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Enums\TypeContenu;
use App\Http\Api\V1\Support\CodeErreur;
use App\Http\Api\V1\Support\Enveloppe;
use App\Http\Api\V1\Support\TeleversementTemporaire;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * `POST /uploads` — déposer une image, une vidéo ou un son avant de le
 * soumettre à l'analyse.
 *
 * LES RÈGLES DE VALIDATION SONT CELLES DE L'ÉCRAN WEB, à la lettre : mêmes
 * types MIME, mêmes plafonds. Les assouplir ici ouvrirait par le mobile ce que
 * le navigateur refuse — et c'est toujours la porte la moins surveillée qu'on
 * emprunte.
 *
 * Le fichier va sur le disque PRIVÉ, sous un nom aléatoire, et n'est jamais
 * servi directement. Rien dans la réponse ne permet d'en déduire un chemin.
 */
final class TeleversementController
{
    public function __invoke(Request $requete): JsonResponse
    {
        $donnees = $requete->validate([
            'type' => ['required', 'string', 'in:image,video,audio'],
        ]);

        $type = TypeContenu::from($donnees['type']);

        $requete->validate(['fichier' => $this->regles($type)], $this->messages());

        $fichier = $requete->file('fichier');

        if (! is_object($fichier) || is_array($fichier)) {
            return Enveloppe::erreur(CodeErreur::Validation, null, [
                'fichier' => ['Envoyez un seul fichier.'],
            ]);
        }

        $identifiant = TeleversementTemporaire::deposer($this->utilisateur($requete), $fichier);

        return Enveloppe::donnee([
            'televersement' => $identifiant,
            'nom' => $fichier->getClientOriginalName(),
            'octets' => $fichier->getSize(),
        ], statut: 201);
    }

    /**
     * @return array<int, string>
     */
    private function regles(TypeContenu $type): array
    {
        return match ($type) {
            // 10 Mo pour une image, 100 Mo pour une vidéo, 25 Mo pour un son :
            // exactement les plafonds de l'écran « Vérifier ».
            TypeContenu::Image => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
            TypeContenu::Video => ['required', 'file', 'mimes:mp4,webm,mov', 'max:102400'],
            TypeContenu::Audio => ['required', 'file', 'mimes:mp3,wav,ogg,m4a', 'max:25600'],
            default => ['required', 'file', 'max:10240'],
        };
    }

    /**
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'fichier.required' => 'Choisissez un fichier.',
            'fichier.mimes' => "Ce format de fichier n'est pas accepté.",
            'fichier.max' => 'Ce fichier est trop volumineux.',
        ];
    }

    private function utilisateur(Request $requete): User
    {
        /** @var User $utilisateur */
        $utilisateur = $requete->user();

        return $utilisateur;
    }
}
