<?php

declare(strict_types=1);

namespace App\Http\Api\V1\Controllers;

use App\Core\Lms\Services\RappelDeSeance;
use App\Http\Api\V1\Resources\OutilResource;
use App\Http\Api\V1\Resources\SeanceResource;
use App\Http\Api\V1\Support\CodeErreur;
use App\Http\Api\V1\Support\Enveloppe;
use App\Http\Api\V1\Support\Filtre;
use App\Models\LiveClass;
use App\Models\Tool;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Deux écrans qui listent, et rien d'autre : les séances en direct et
 * l'annuaire d'outils.
 *
 * Ni l'un ni l'autre n'est paginé, et c'est un choix : ce sont des listes
 * FERMÉES, tenues à la main par l'équipe. Une vingtaine d'outils, quelques
 * séances par mois. Y ajouter un curseur compliquerait le client pour une
 * pagination qui ne se déclencherait jamais.
 */
final class SeanceEtOutilsController
{
    /**
     * `GET /live-classes`
     *
     * L'état — à venir, en direct, terminée — se DÉDUIT de l'heure. Le filtre
     * `state` s'applique donc après lecture, sur une liste courte : le mettre
     * en SQL supposerait une colonne d'état, c'est-à-dire une colonne qui
     * mentirait dès que personne ne repasserait derrière.
     */
    public function seances(Request $requete, RappelDeSeance $rappels): JsonResponse
    {
        $requete->validate([
            'state' => ['nullable', 'string', 'in:upcoming,live,past'],
        ]);

        $utilisateur = $this->utilisateur($requete);

        $seances = LiveClass::with(['course', 'host'])
            ->where('is_published', true)
            ->orderBy('starts_at')
            ->get()
            ->each(function (LiveClass $seance) use ($rappels, $utilisateur): void {
                $seance->setAttribute('rappel_demande', $rappels->dejaDemande($utilisateur, $seance));
            });

        $etat = $requete->string('state')->toString();

        if ($etat !== '') {
            $seances = $seances->filter(
                fn (LiveClass $seance): bool => $seance->etat() === $etat,
            )->values();
        }

        return Enveloppe::donnee(SeanceResource::collection($seances));
    }

    /**
     * `POST /live-classes/{uuid}/remind`
     *
     * Enregistre l'intention d'assister. RÉSERVE HONNÊTE, écrite au contrat :
     * l'envoi du rappel J-1 attend l'infrastructure de notifications, qui
     * n'existe pas encore. L'inscription, elle, est durable — le jour où le
     * rappel partira, il aura sa liste.
     */
    public function rappeler(Request $requete, LiveClass $liveClass, RappelDeSeance $rappels): JsonResponse
    {
        $inscription = $rappels->demander($this->utilisateur($requete), $liveClass);

        if ($inscription === null) {
            return Enveloppe::erreur(
                CodeErreur::ActionImpossible,
                'Cette séance est passée : il n\'y a plus de rappel à programmer.',
            );
        }

        return Enveloppe::donnee([
            'rappel_demande' => true,
            'seance' => $liveClass->uuid,
        ], statut: 201);
    }

    /**
     * `GET /tools`
     *
     * Les outils sont des liens EXTERNES. Le client les ouvre en onglet
     * système, jamais en WebView maison : moins de surface d'attaque, session
     * isolée, et le citoyen voit le vrai domaine dans la barre d'adresse.
     */
    public function outils(Request $requete): JsonResponse
    {
        $requete->validate([
            'category' => ['nullable', 'string', 'max:80'],
        ]);

        $requeteSql = Tool::where('is_published', true);

        $categorie = Filtre::valeur($requete, 'category');

        if ($categorie !== null) {
            $requeteSql->where('category', $categorie);
        }

        $outils = $requeteSql->orderBy('sort_order')->orderBy('title')->get();

        return Enveloppe::donnee(OutilResource::collection($outils), meta: [
            // Les catégories viennent de la BASE, jamais d'une liste écrite à
            // la main dans le client : deux listes finissent par diverger, et
            // c'est le filtre qui cesse de trouver quoi que ce soit.
            'categories' => Tool::where('is_published', true)
                ->whereNotNull('category')
                ->distinct()
                ->orderBy('category')
                ->pluck('category')
                ->values(),
        ]);
    }

    private function utilisateur(Request $requete): User
    {
        /** @var User $utilisateur */
        $utilisateur = $requete->user();

        return $utilisateur;
    }
}
