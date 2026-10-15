<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Core\FactChecking\Adapters\AdaptateurHttp;
use App\Enums\EtatMoteur;
use App\Enums\MoteurAnalyse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * L'état réel des douze moteurs, tel qu'un écran a le droit de l'afficher.
 *
 * ## Ce que ce service corrige
 *
 * `RegistreDesMoteurs::etat()` répondait « disponible » à une seule question :
 * *une classe d'adaptateur existe-t-elle ?* Le 1er septembre 2026, cela
 * annonçait **Perplexity opérationnel avec une clé vide**, et **Google Fact
 * Check opérationnel avec une clé que Google refuse** — vérifié par appel.
 *
 * Trois questions distinctes, posées dans l'ordre, chacune avec une réponse
 * vérifiable : l'adaptateur est-il écrit, la clé est-elle là, le fournisseur
 * l'accepte-t-il. Voir {@see EtatMoteur}.
 *
 * ## Pourquoi la sonde est mise en cache aussi longtemps
 *
 * Le quota gratuit de Google Custom Search est de **cent requêtes par jour**.
 * Une sonde relancée à chaque affichage du tableau de bord épuiserait le quota
 * du moteur qu'elle surveille avant midi — un dispositif de surveillance qui
 * provoque la panne qu'il cherche n'a aucun sens.
 *
 * D'où : résultat gardé {@see self::FRAICHEUR_MINUTES} minutes, rafraîchi par
 * le planificateur ou par un geste explicite dans l'écran, jamais par le simple
 * fait de regarder. L'écran affiche **l'âge de la mesure** avec la mesure : une
 * pastille verte sans date ne veut rien dire.
 */
final class SanteDesMoteurs
{
    /**
     * Une heure. Une clé d'API ne meurt pas en dix minutes, et les quotas des
     * fournisseurs se comptent parfois en centaines d'appels par jour.
     */
    public const FRAICHEUR_MINUTES = 60;

    private const CLE_CACHE = 'sante_des_moteurs';

    public function __construct(private readonly RegistreDesMoteurs $registre) {}

    /**
     * L'état des douze moteurs, depuis la dernière sonde.
     *
     * @return array{
     *     mesure_le: Carbon|null,
     *     simule: bool,
     *     moteurs: list<array{moteur: string, libelle: string, etat: EtatMoteur, precision: string|null}>,
     * }
     */
    public function etat(): array
    {
        /** @var array{mesure_le: string, simule: bool, moteurs: list<array<string, mixed>>}|null $garde */
        $garde = Cache::get(self::CLE_CACHE);

        if ($garde === null) {
            return $this->jamaisMesure();
        }

        return [
            'mesure_le' => Carbon::parse($garde['mesure_le']),
            'simule' => $garde['simule'],
            'moteurs' => array_map(
                fn (array $m): array => [
                    'moteur' => (string) $m['moteur'],
                    'libelle' => (string) $m['libelle'],
                    'etat' => EtatMoteur::from((string) $m['etat']),
                    'precision' => $m['precision'] === null ? null : (string) $m['precision'],
                ],
                $garde['moteurs'],
            ),
        ];
    }

    /**
     * Relance les sondes et réécrit le cache. Appel réseau : jamais depuis un
     * simple affichage.
     *
     * @return array{
     *     mesure_le: Carbon|null,
     *     simule: bool,
     *     moteurs: list<array{moteur: string, libelle: string, etat: EtatMoteur, precision: string|null}>,
     * }
     */
    public function mesurer(): array
    {
        $simule = (bool) config('factcheck.fake_adapters', true);
        $moteurs = [];

        foreach (MoteurAnalyse::cases() as $moteur) {
            $adaptateur = $this->registre->adaptateurReel($moteur);

            [$etat, $precision] = match (true) {
                $simule => [EtatMoteur::Simule, null],
                // AVANT tout le reste : un moteur éteint ne se sonde pas. Le
                // sonder appellerait le fournisseur qu'on a décidé de ne pas
                // appeler — et sur un service payant, la sonde EST la facture.
                ! $this->registre->estAllume($moteur) => [EtatMoteur::Desactive, null],
                $adaptateur === null => [EtatMoteur::NonPorte, null],
                default => $this->interroger($adaptateur),
            };

            $moteurs[] = [
                'moteur' => $moteur->value,
                'libelle' => $moteur->libelle(),
                'etat' => $etat,
                'precision' => $precision,
            ];
        }

        Cache::put(
            self::CLE_CACHE,
            [
                'mesure_le' => now()->toIso8601String(),
                'simule' => $simule,
                'moteurs' => array_map(
                    fn (array $m): array => [...$m, 'etat' => $m['etat']->value],
                    $moteurs,
                ),
            ],
            now()->addMinutes(self::FRAICHEUR_MINUTES * 24),
        );

        return ['mesure_le' => now(), 'simule' => $simule, 'moteurs' => $moteurs];
    }

    /** La mesure est-elle trop vieille pour être montrée sans réserve ? */
    public function estPerimee(): bool
    {
        $etat = $this->etat();

        return $etat['mesure_le'] === null
            || $etat['mesure_le']->lt(now()->subMinutes(self::FRAICHEUR_MINUTES));
    }

    /** Combien de moteurs peuvent réellement peser dans un verdict. */
    public function nombreQuiComptent(): int
    {
        return count(array_filter(
            $this->etat()['moteurs'],
            fn (array $m): bool => $m['etat']->compteDansLeScore(),
        ));
    }

    /**
     * @return array{0: EtatMoteur, 1: string|null}
     */
    private function interroger(AdaptateurHttp $adaptateur): array
    {
        if (! $adaptateur->estConfigure()) {
            return [EtatMoteur::NonConfigure, null];
        }

        $joignable = $adaptateur->joignable();

        return match ($joignable) {
            // Pas de point d'appel de contrôle gratuit : on ne SAIT pas, et on
            // le dit, plutôt que de peindre du vert sur une supposition.
            null => [EtatMoteur::Configure, $adaptateur->precision()],
            true => [EtatMoteur::Operationnel, $adaptateur->precision()],
            false => [EtatMoteur::Injoignable, $adaptateur->precision()],
        };
    }

    /**
     * Avant la toute première sonde.
     *
     * On ne devine pas : chaque moteur est rendu dans son état LOCAL, celui qui
     * se connaît sans réseau, et `mesure_le` reste nul pour que l'écran dise
     * « jamais vérifié » au lieu d'inventer une date.
     *
     * @return array{
     *     mesure_le: null,
     *     simule: bool,
     *     moteurs: list<array{moteur: string, libelle: string, etat: EtatMoteur, precision: null}>,
     * }
     */
    private function jamaisMesure(): array
    {
        $simule = (bool) config('factcheck.fake_adapters', true);
        $moteurs = [];

        foreach (MoteurAnalyse::cases() as $moteur) {
            $adaptateur = $this->registre->adaptateurReel($moteur);

            $moteurs[] = [
                'moteur' => $moteur->value,
                'libelle' => $moteur->libelle(),
                'etat' => match (true) {
                    $simule => EtatMoteur::Simule,
                    ! $this->registre->estAllume($moteur) => EtatMoteur::Desactive,
                    $adaptateur === null => EtatMoteur::NonPorte,
                    ! $adaptateur->estConfigure() => EtatMoteur::NonConfigure,
                    default => EtatMoteur::Configure,
                },
                'precision' => null,
            ];
        }

        return ['mesure_le' => null, 'simule' => $simule, 'moteurs' => $moteurs];
    }
}
