<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Core\FactChecking\Services\SanteDesMoteurs;
use App\Enums\EtatMoteur;
use Illuminate\Console\Command;

/**
 * Interroge chaque moteur d'analyse sur son propre état, et garde la réponse.
 *
 * ## Pourquoi une commande, et pas un calcul à l'affichage
 *
 * Sonder coûte des appels réseau, et certains fournisseurs comptent petit : le
 * quota gratuit de Google Custom Search est de **cent requêtes par jour**. Une
 * sonde relancée à chaque ouverture du tableau de bord épuiserait le quota du
 * moteur qu'elle surveille avant midi. Un dispositif de surveillance qui
 * provoque la panne qu'il cherche ne surveille rien.
 *
 * Le tableau de bord lit donc la DERNIÈRE mesure, et affiche son âge à côté.
 *
 * ## Sortie en échec
 *
 * La commande sort en échec si le nombre de moteurs capables de peser dans un
 * verdict passe sous le plancher de `factcheck.decision.moteurs_minimum`. Sous
 * ce seuil, l'application rend encore des rapports, mais partiels — c'est le
 * genre de dégradation qui ne se remarque pas avant qu'un lecteur s'en plaigne.
 */
final class SonderLesMoteurs extends Command
{
    protected $signature = 'verifall:sonder-les-moteurs';

    protected $description = "Vérifie que chaque moteur d'analyse répond, et garde le résultat pour le tableau de bord.";

    public function handle(SanteDesMoteurs $sante): int
    {
        $etat = $sante->mesurer();

        if ($etat['simule']) {
            $this->components->warn(
                'FAKE_ADAPTERS=true : aucun moteur réel n’a été interrogé. Les verdicts sont inventés.'
            );

            return self::SUCCESS;
        }

        $this->table(
            ['Moteur', 'État', 'Précision'],
            array_map(
                fn (array $m): array => [$m['libelle'], $m['etat']->libelle(), $m['precision'] ?? ''],
                $etat['moteurs'],
            ),
        );

        $comptent = $sante->nombreQuiComptent();
        $plancher = (int) config('factcheck.decision.moteurs_minimum', 2);

        $injoignables = array_filter(
            $etat['moteurs'],
            fn (array $m): bool => $m['etat'] === EtatMoteur::Injoignable,
        );

        /*
         | Le message disait « le fournisseur refuse la clé OU ne répond pas ».
         | Les deux cas n'appellent pas du tout le même geste : le premier se
         | règle en régénérant une clé, le second en ouvrant une sortie réseau.
         | La sonde sait désormais lequel des deux c'est ; on le dit.
         */
        foreach ($injoignables as $moteur) {
            $this->components->error(sprintf(
                '%s : %s.',
                $moteur['libelle'],
                $moteur['precision'] ?? 'le fournisseur refuse la clé ou ne répond pas',
            ));
        }

        if ($comptent < $plancher) {
            $this->components->error(sprintf(
                '%d moteur(s) utilisable(s) pour un plancher de %d : les rapports sortiront partiels.',
                $comptent,
                $plancher,
            ));

            return self::FAILURE;
        }

        $this->components->info(sprintf(
            '%d moteur(s) utilisable(s), plancher de %d tenu.',
            $comptent,
            $plancher,
        ));

        return $injoignables === [] ? self::SUCCESS : self::FAILURE;
    }
}
