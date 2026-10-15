<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use App\Core\Application\Reglages\Reglages;
use App\Core\Identity\Services\SuppressionCompteService;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * Les conditions générales d'utilisation.
 *
 * ## Pourquoi un contrôleur, alors que les autres pages légales sont des vues
 *
 * `mentions-legales`, `confidentialite` et `cookies` sont servies par
 * `Route::view` : elles ne calculent rien, et l'identité de l'éditeur leur
 * arrive par un composeur de vue.
 *
 * Celle-ci calcule. Trois valeurs du texte viennent d'ailleurs et doivent
 * rester d'accord avec le produit :
 *
 *  · **l'âge minimum**, qui est un réglage — une décision de politique, pas de
 *    code, et modifiable sans déploiement ;
 *  · **le délai de rétractation** de la suppression de compte, qui est la
 *    constante du service qui l'applique. Écrire « 30 jours » à la main dans
 *    le texte le ferait mentir le jour où la constante change — et c'est
 *    précisément la clause qu'on invoquerait pour contester une suppression ;
 *  · **la date d'entrée en vigueur**, ci-dessous.
 *
 * ## La date est une CONSTANTE, pas `now()`
 *
 * `mentions-legales` affiche `now()` en « dernière mise à jour » : la page
 * prétend donc avoir été révisée chaque jour. Sur un document juridique c'est
 * une fausse information, et c'est justement la date qu'on invoque pour savoir
 * quelle version on a acceptée.
 *
 * **À MODIFIER À LA MAIN à chaque révision substantielle du texte**, en même
 * temps que `lang/{fr,en}/cgu.php`.
 */
final class ConditionsGeneralesController
{
    /** Date d'entrée en vigueur de la version en cours du texte. */
    private const ENTREE_EN_VIGUEUR = '2026-08-31';

    public function __invoke(Reglages $reglages): View
    {
        $age = $reglages->nombre('legal_minimum_age', 15);

        /** @var list<array{titre: string, paragraphes: list<string>}> $sections */
        $sections = (array) __('cgu.sections');

        return view('public.conditions-generales', [
            'title' => __('cgu.conditions_generales')." · Vérif'All",
            'entreeEnVigueur' => Carbon::parse(self::ENTREE_EN_VIGUEUR),
            'sections' => $this->substituer($sections, [
                ':age' => (string) $age,
                ':retractation' => (string) SuppressionCompteService::JOURS_RETRACTATION,
            ]),
        ]);
    }

    /**
     * Remplace les jetons du texte par les valeurs réelles.
     *
     * L'éditeur ne fait PAS partie des jetons, et c'est le fruit d'une erreur
     * corrigée : le texte portait un `:entite` que ce contrôleur laissait
     * volontairement passer — au motif que `<x-mention-legale>` sait dire « Non
     * communiqué » au visiteur et signaler le réglage manquant au personnel.
     * Le raisonnement était juste, la conséquence non : la page publiait
     * « éditée par :entite » en clair. L'éditeur est désormais nommé une seule
     * fois, en tête de page, par ce composant ; le texte y renvoie.
     *
     * @param  list<array{titre: string, paragraphes: list<string>}>  $sections
     * @param  array<string, string>  $valeurs
     * @return list<array{titre: string, paragraphes: list<string>}>
     */
    private function substituer(array $sections, array $valeurs): array
    {
        foreach ($sections as $rang => $section) {
            $sections[$rang]['paragraphes'] = array_map(
                static fn (string $p): string => strtr($p, $valeurs),
                $section['paragraphes'],
            );
        }

        return $sections;
    }
}
