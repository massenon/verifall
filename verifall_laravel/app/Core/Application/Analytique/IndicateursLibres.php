<?php

declare(strict_types=1);

namespace App\Core\Application\Analytique;

use App\Enums\StatutPaiement;
use App\Models\Certificate;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\UnitLog;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Business Intelligence : des indicateurs choisis dans une LISTE FERMÉE.
 *
 * Pas de requête libre, pas de SQL saisi : l'écran n'envoie qu'une clé
 * d'indicateur et une durée, le service les valide contre {@see self::CATALOGUE}.
 * Un indicateur inconnu lève une exception plutôt que de retomber sur un autre.
 *
 * Chaque série a un point par jour, y compris les jours à zéro : un trou ne
 * doit pas se lire comme une absence de mesure.
 */
final class IndicateursLibres
{
    public const DUREES = [7, 30, 90];

    private const TTL_MINUTES = 10;

    /**
     * clé => [libellé, unité, modèle, colonne de date, colonne sommée (null = comptage)]
     *
     * @var array<string, array{libelle: string, unite: string, modele: class-string, date: string, somme: ?string}>
     */
    public const CATALOGUE = [
        'comptes' => ['libelle' => 'Comptes créés', 'unite' => '', 'modele' => User::class, 'date' => 'created_at', 'somme' => null],
        'verifications' => ['libelle' => 'Vérifications lancées', 'unite' => '', 'modele' => Verification::class, 'date' => 'created_at', 'somme' => null],
        'inscriptions' => ['libelle' => 'Inscriptions aux cours', 'unite' => '', 'modele' => Enrollment::class, 'date' => 'created_at', 'somme' => null],
        'certificats' => ['libelle' => 'Certificats délivrés', 'unite' => '', 'modele' => Certificate::class, 'date' => 'created_at', 'somme' => null],
        'paiements' => ['libelle' => 'Paiements réussis', 'unite' => '', 'modele' => Payment::class, 'date' => 'settled_at', 'somme' => null],
        'encaissements' => ['libelle' => 'Encaissé', 'unite' => 'FCFA', 'modele' => Payment::class, 'date' => 'settled_at', 'somme' => 'amount_fcfa'],
        'unites' => ['libelle' => 'Unités consommées', 'unite' => '', 'modele' => UnitLog::class, 'date' => 'created_at', 'somme' => 'delta'],
    ];

    /**
     * @return array{
     *   libelle: string, unite: string, total: int, precedent: int, variation: ?int,
     *   jours: list<array{jour: string, valeur: int}>
     * }
     */
    public function serie(string $cle, int $jours): array
    {
        $def = self::CATALOGUE[$cle] ?? throw new \InvalidArgumentException("Indicateur inconnu : {$cle}");
        $jours = in_array($jours, self::DUREES, true) ? $jours : 30;

        return Cache::remember("analytique.bi.{$cle}.{$jours}", now()->addMinutes(self::TTL_MINUTES), function () use ($def, $jours): array {
            $fin = Carbon::today();
            $debut = $fin->copy()->subDays($jours - 1);
            $debutPrecedent = $debut->copy()->subDays($jours);

            // Deux fenêtres d'un coup : la période demandée et celle d'avant, pour la variation.
            $parJour = $this->parJour($def, $debutPrecedent, $fin);

            $points = [];
            $total = 0;
            $precedent = 0;

            for ($i = 0; $i < $jours * 2; $i++) {
                $jour = $debutPrecedent->copy()->addDays($i);
                $valeur = $parJour[$jour->toDateString()] ?? 0;

                if ($jour->lt($debut)) {
                    $precedent += $valeur;

                    continue;
                }

                $total += $valeur;
                $points[] = ['jour' => $jour->toDateString(), 'valeur' => $valeur];
            }

            return [
                'libelle' => $def['libelle'],
                'unite' => $def['unite'],
                'total' => $total,
                'precedent' => $precedent,
                'variation' => $precedent > 0 ? (int) round(($total - $precedent) / $precedent * 100) : null,
                'jours' => $points,
            ];
        });
    }

    /**
     * @param  array{libelle: string, unite: string, modele: class-string, date: string, somme: ?string}  $def
     * @return array<string, int>
     */
    private function parJour(array $def, Carbon $debut, Carbon $fin): array
    {
        /** @var Builder<Model> $requete */
        $requete = $def['modele']::query()
            ->where($def['date'], '>=', $debut->copy()->startOfDay())
            ->where($def['date'], '<=', $fin->copy()->endOfDay());

        if ($def['modele'] === Payment::class) {
            $requete->where('status', StatutPaiement::Reussi);
        }

        if ($def['modele'] === UnitLog::class) {
            $requete->where('delta', '<', 0);
        }

        $colonne = $def['date'];
        $somme = $def['somme'];
        $valeurs = [];

        // Agrégat par jour côté PHP : la base ne reçoit que des clauses paramétrées.
        $requete->get(array_filter([$colonne, $somme]))->each(function ($ligne) use ($colonne, $somme, &$valeurs): void {
            $jour = Carbon::parse($ligne->{$colonne})->toDateString();
            $valeurs[$jour] = ($valeurs[$jour] ?? 0) + ($somme === null ? 1 : (int) abs((int) $ligne->{$somme}));
        });

        return $valeurs;
    }
}
