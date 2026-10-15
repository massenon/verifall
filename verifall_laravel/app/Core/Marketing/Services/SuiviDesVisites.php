<?php

declare(strict_types=1);

namespace App\Core\Marketing\Services;

use App\Models\ContentVisit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Compte les visites d'un contenu public — une fois par personne et par jour.
 *
 * ## Les quatre choses qu'on refuse de compter
 *
 * 1. **Les robots.** Un article bien référencé reçoit plus de passages
 *    d'indexation que de lecteurs. Ils sont enregistrés à part, jamais mêlés.
 * 2. **Les aperçus de partage.** Quand on colle un lien dans WhatsApp, le
 *    service va chercher la page pour en fabriquer la vignette. Cent partages
 *    produisaient cent « vues » sans qu'aucun humain n'ait rien lu.
 * 3. **Les rafraîchissements.** Une même personne qui recharge trois fois
 *    n'est pas trois lecteurs.
 * 4. **Les requêtes de préchargement.** Les navigateurs récents chargent
 *    d'avance la page qu'on survole. `Sec-Purpose: prefetch` le dit ; on
 *    l'écoute.
 *
 * ## Ce qui reste
 *
 * Un chiffre plus bas que l'ancien, et vrai. C'est le sens de l'échange.
 */
final class SuiviDesVisites
{
    /**
     * Enregistre une visite, si elle en est une.
     *
     * Renvoie `true` quand la visite a été comptée comme humaine et nouvelle —
     * ce qui permet aux appelants de n'incrémenter le compteur dénormalisé
     * qu'à ce moment-là.
     */
    public function enregistrer(Model $contenu, Request $requete): bool
    {
        if ($this->estUnPrechargement($requete)) {
            return false;
        }

        $robot = $this->estUnRobot($requete);
        $empreinte = $this->empreinte($requete);

        /*
         | Le dédoublonnage porte sur la journée. Une personne qui revient le
         | lendemain est une visite de plus : c'est bien un retour, et c'est
         | l'information qu'on veut.
         */
        $dejaVu = ContentVisit::query()
            ->where('visitable_type', $contenu->getMorphClass())
            ->where('visitable_id', $contenu->getKey())
            ->where('visitor_hash', $empreinte)
            ->where('visited_at', '>=', now()->startOfDay())
            ->exists();

        if ($dejaVu) {
            return false;
        }

        ContentVisit::query()->create([
            'visitable_type' => $contenu->getMorphClass(),
            'visitable_id' => $contenu->getKey(),
            'visitor_hash' => $empreinte,
            'referrer_host' => $this->hoteDuReferent($requete),
            'is_bot' => $robot,
            'visited_at' => now(),
        ]);

        if ($robot) {
            return false;
        }

        /*
         | Le compteur dénormalisé sert l'affichage — « 3 240 vues » sous un
         | article. On l'incrémente sans passer par le modèle : `increment()`
         | émet un UPDATE atomique, là où lire-modifier-écrire perdrait des
         | visites simultanées.
         */
        if (in_array('views', $contenu->getFillable(), true)
            || $contenu->getConnection()->getSchemaBuilder()->hasColumn($contenu->getTable(), 'views')) {
            DB::table($contenu->getTable())
                ->where($contenu->getKeyName(), $contenu->getKey())
                ->increment('views');
        }

        return true;
    }

    /**
     * L'empreinte d'un visiteur : reconnaissable aujourd'hui, pas demain.
     *
     * Le sel comprend la date du jour. Le lendemain, la même personne produit
     * une empreinte différente — le suivi ne peut donc PAS servir à reconstituer
     * un parcours dans le temps, même pour qui aurait la base et la clé.
     *
     * Ce n'est pas une promesse de conduite : c'est une impossibilité.
     */
    private function empreinte(Request $requete): string
    {
        return hash('sha256', implode('|', [
            $requete->ip() ?? '',
            (string) $requete->userAgent(),
            now()->toDateString(),
            (string) config('app.key'),
        ]));
    }

    /**
     * Les robots qui se déclarent.
     *
     * On ne cherche pas à démasquer ceux qui se cachent : ce serait une course
     * perdue, et le but n'est pas la sécurité mais la justesse d'un chiffre.
     * Écarter ceux qui s'annoncent suffit à retirer l'essentiel du bruit —
     * les moteurs et les robots d'aperçu des réseaux sociaux annoncent tous
     * leur nom.
     */
    private function estUnRobot(Request $requete): bool
    {
        $agent = mb_strtolower((string) $requete->userAgent());

        if ($agent === '') {
            // Un navigateur envoie toujours un agent. Son absence signale un
            // outil, pas quelqu'un qui lit.
            return true;
        }

        foreach ([
            'bot', 'crawler', 'spider', 'slurp',
            // Les fabricants d'aperçu de lien : WhatsApp, Facebook, Telegram,
            // Twitter, LinkedIn, Discord, Slack.
            'whatsapp', 'facebookexternalhit', 'facebookcatalog', 'telegrambot',
            'twitterbot', 'linkedinbot', 'discordbot', 'slackbot', 'skypeuripreview',
            // Les outils en ligne de commande et les moniteurs.
            'curl', 'wget', 'python-requests', 'headlesschrome', 'lighthouse',
            'pingdom', 'uptimerobot', 'gtmetrix',
        ] as $signature) {
            if (str_contains($agent, $signature)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Un préchargement n'est pas une lecture.
     *
     * Le navigateur charge la page qu'on survole, au cas où. Il le dit dans
     * ses en-têtes ; la compter serait compter une intention, pas une visite.
     */
    private function estUnPrechargement(Request $requete): bool
    {
        $but = mb_strtolower((string) $requete->header('Sec-Purpose', ''));
        $ancien = mb_strtolower((string) $requete->header('Purpose', ''));
        $moz = mb_strtolower((string) $requete->header('X-Moz', ''));

        return str_contains($but, 'prefetch')
            || str_contains($ancien, 'prefetch')
            || $moz === 'prefetch';
    }

    /**
     * Le nom d'hôte du référent, et rien de plus.
     *
     * L'URL complète d'un fil de discussion peut désigner une personne. Le nom
     * de domaine suffit à répondre à la seule question utile : d'où viennent
     * nos lecteurs ?
     */
    private function hoteDuReferent(Request $requete): ?string
    {
        $referent = $requete->headers->get('referer');

        if ($referent === null || $referent === '') {
            return null;
        }

        $hote = parse_url($referent, PHP_URL_HOST);

        if (! is_string($hote) || $hote === '') {
            return null;
        }

        // Une navigation interne n'est pas une provenance.
        if ($hote === $requete->getHost()) {
            return null;
        }

        return mb_substr($hote, 0, 255);
    }
}
