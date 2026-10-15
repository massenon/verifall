<?php

declare(strict_types=1);

namespace App\Core\Community\Services;

use App\Models\ContentReport;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Ce qui se répand vite mérite d'être vérifié en premier.
 *
 * ## Le défaut que cela corrige
 *
 * La file de modération se vidait dans l'ordre d'arrivée. C'est équitable et
 * c'est mauvais : une rumeur signalée par quinze personnes en deux heures
 * attend derrière un hors-sujet signalé la veille par une seule. Or entre les
 * deux, c'est la première qui circule pendant qu'on traite la seconde.
 *
 * ## Le SEUL signal honnête dont la base dispose
 *
 * Le nombre d'alertes sur un même contenu, et leur étalement dans le temps.
 *
 * Les compteurs de vues, de réponses et de mentions « j'aime » sont des
 * INSTANTANÉS : ils disent combien, jamais à quelle allure. En tirer une
 * vitesse supposerait un historique que personne n'enregistre, et le chiffre
 * produit serait inventé — ce que la règle du chantier interdit, et pour une
 * bonne raison : un tri fondé sur un chiffre faux est pire qu'un tri par date,
 * parce qu'il a l'air informé.
 *
 * ## Une seule alerte n'a PAS de vitesse
 *
 * Et on ne lui en attribue pas. Un signalement isolé retourne `null`, pas zéro.
 * Zéro dirait « ce contenu ne circule pas », ce que personne ne sait ; `null`
 * dit « on ne peut rien en conclure », ce qui est exact. Ces signalements
 * restent triés par ancienneté, comme avant.
 */
final class VitesseDePropagation
{
    /**
     * Le nombre d'alertes à partir duquel on parle de propagation.
     *
     * Deux, et non trois : sur un forum de quelques centaines de membres
     * actifs, exiger trois alertes ferait que presque rien ne remonterait
     * jamais — et le mécanisme n'existerait que sur le papier.
     */
    public const ALERTES_MINIMUM = 2;

    /**
     * La fenêtre au-delà de laquelle une accumulation n'est plus une flambée.
     *
     * Quarante-huit heures. Quinze signalements étalés sur trois mois décrivent
     * un contenu durablement gênant, pas une rumeur qui monte : les deux
     * méritent d'être traités, mais pas dans le même ordre d'urgence.
     */
    public const FENETRE_HEURES = 48;

    /**
     * Le plancher de durée, en minutes.
     *
     * Sans lui, deux alertes à quelques secondes d'intervalle donneraient une
     * vitesse quasi infinie, et un seul signaleur qui clique deux fois
     * dominerait la file pour toujours.
     */
    private const PLANCHER_MINUTES = 10;

    /**
     * Le nombre d'alertes par heure, ou `null` si la question n'a pas de sens.
     *
     * @param  int  $alertes  nombre de signalements sur ce contenu
     */
    public function parHeure(int $alertes, ?CarbonInterface $premiere): ?float
    {
        if ($alertes < self::ALERTES_MINIMUM || $premiere === null) {
            return null;
        }

        $minutes = max(self::PLANCHER_MINUTES, $premiere->diffInMinutes(now()));

        return round($alertes / ($minutes / 60), 2);
    }

    /**
     * La phrase montrée au modérateur.
     *
     * Elle dit ce qui est MESURÉ — un nombre d'alertes et une durée — et non
     * une conclusion sur la dangerosité du contenu. C'est au modérateur de
     * conclure ; l'écran lui donne de quoi le faire.
     */
    public function resume(int $alertes, ?CarbonInterface $premiere): string
    {
        if ($alertes < self::ALERTES_MINIMUM || $premiere === null) {
            return 'Une seule alerte';
        }

        $cadence = $this->parHeure($alertes, $premiere);

        return $alertes.' alertes · '.$cadence.'/h depuis '.$premiere->diffForHumans(short: true, syntax: CarbonInterface::DIFF_ABSOLUTE);
    }

    /**
     * Les contenus en propagation, avec leur compte et leur première alerte.
     *
     * Une seule requête agrégée. La calculer ligne par ligne coûterait une
     * requête par signalement affiché, et `preventLazyLoading` le signalerait
     * en développement — mais seulement là.
     *
     * @return array<string, array{alertes: int, premiere: CarbonInterface}>
     *                                                                       indexé par « type|identifiant »
     */
    public function pourLesSignalements(): array
    {
        $lignes = ContentReport::query()
            ->selectRaw('reportable_type, reportable_id, COUNT(*) as alertes, MIN(created_at) as premiere')
            ->groupBy('reportable_type', 'reportable_id')
            ->havingRaw('COUNT(*) >= ?', [self::ALERTES_MINIMUM])
            ->get();

        $resultat = [];

        foreach ($lignes as $ligne) {
            $premiere = Carbon::parse((string) $ligne->premiere);

            // Hors fenêtre : l'accumulation existe, la flambée non.
            if ($premiere->lt(now()->subHours(self::FENETRE_HEURES))) {
                continue;
            }

            $resultat[$ligne->reportable_type.'|'.$ligne->reportable_id] = [
                'alertes' => (int) $ligne->alertes,
                'premiere' => $premiere,
            ];
        }

        return $resultat;
    }
}
