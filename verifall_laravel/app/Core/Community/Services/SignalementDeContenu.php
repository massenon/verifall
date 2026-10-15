<?php

declare(strict_types=1);

namespace App\Core\Community\Services;

use App\Enums\StatutSignalementContenu;
use App\Models\ContentReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/**
 * Signaler un contenu à la modération.
 *
 * Le signalement est un ACTE, pas un vote : cinq personnes qui signalent le
 * même message ne le rendent pas cinq fois plus répréhensible, et masquer
 * automatiquement au-delà d'un seuil offrirait à n'importe quel groupe organisé
 * un bouton de censure. Un humain tranche, toujours.
 *
 * Un signalement se TRAITE, il ne se supprime pas : effacer la trace, c'est
 * perdre le fait qu'une décision a été prise, et rendre impossible de
 * reconnaître un signaleur abusif.
 */
final class SignalementDeContenu
{
    /**
     * Motifs proposés.
     *
     * Une liste fermée plutôt qu'un champ libre : un motif normalisé se trie,
     * se compte et se traite en lot. Le détail libre reste possible à côté.
     *
     * @return array<string, string>
     */
    public static function motifs(): array
    {
        return [
            'desinformation' => 'Fausse information',
            'haine' => 'Propos haineux ou insultants',
            'arnaque' => 'Arnaque ou tentative d\'escroquerie',
            'spam' => 'Spam ou hors-sujet',
            'donnees' => 'Données personnelles exposées',
            /*
             | Détection automatique par la liste de mots surveillés. Un motif
             | à part, et non « autre » : le modérateur doit savoir d'emblée
             | que personne n'a cliqué sur « signaler ». La conduite à tenir
             | n'est pas la même — un signalement citoyen dit qu'un lecteur a
             | été heurté, une détection dit qu'un mot figurait dans le texte.
             */
            'mot_surveille' => 'Mot surveillé (détection automatique)',
            'autre' => 'Autre motif',
        ];
    }

    /**
     * Enregistre un signalement.
     *
     * Rend le signalement EXISTANT si la personne a déjà signalé ce contenu :
     * du point de vue de qui clique, le résultat voulu est atteint, et
     * empiler dix lignes du même signaleur fausserait la file de modération.
     *
     * @param  Model  $cible  le sujet ou la réponse visés
     */
    public function signaler(?User $signaleur, Model $cible, string $motif, ?string $detail = null): ContentReport
    {
        $motif = array_key_exists($motif, self::motifs()) ? $motif : 'autre';

        $existant = ContentReport::where('user_id', $signaleur?->id)
            ->where('reportable_type', $cible->getMorphClass())
            ->where('reportable_id', $cible->getKey())
            ->first();

        if ($existant !== null) {
            return $existant;
        }

        return ContentReport::create([
            'uuid' => (string) Str::uuid(),
            /*
             | `null` pour une DÉTECTION AUTOMATIQUE.
             |
             | Y mettre l'auteur du message afficherait son nom comme signaleur
             | dans l'écran de modération : il se dénoncerait lui-même, ce qui
             | est faux et se lit comme un défaut. L'écran affiche « Compte
             | supprimé » à défaut de nom ; c'est le seul point à corriger si
             | l'on veut y distinguer les deux cas.
             */
            'user_id' => $signaleur?->id,
            'reportable_type' => $cible->getMorphClass(),
            'reportable_id' => $cible->getKey(),
            'reason' => $motif,
            'detail' => $detail !== null && trim($detail) !== '' ? trim($detail) : null,
            'status' => StatutSignalementContenu::EnAttente->value,
        ]);
    }

    /** Cette personne a-t-elle déjà signalé ce contenu ? */
    public function dejaSignale(User $signaleur, Model $cible): bool
    {
        return ContentReport::where('user_id', $signaleur->id)
            ->where('reportable_type', $cible->getMorphClass())
            ->where('reportable_id', $cible->getKey())
            ->exists();
    }
}
