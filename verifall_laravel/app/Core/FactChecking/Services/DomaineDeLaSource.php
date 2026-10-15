<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

/**
 * Le vrai domaine derrière une adresse de source.
 *
 * ## Le défaut que cette classe referme
 *
 * Quand Gemini s'appuie sur la recherche Google, il ne rend PAS les adresses
 * des pages qu'il a lues. Il rend des liens de redirection :
 *
 *     https://vertexaisearch.cloud.google.com/grounding-api-redirect/AbCd…
 *
 * `parse_url()` en tire `vertexaisearch.cloud.google.com`, et c'est ce qui
 * s'affichait sous CHAQUE source de la colonne « Sources et preuves » : vingt
 * cartes identiques, toutes attribuées à un redirecteur Google. Le lecteur ne
 * pouvait savoir si le démenti s'appuyait sur le Journal officiel ou sur un
 * forum.
 *
 * Deux conséquences, et la seconde est pire que la première :
 *
 * 1. **À l'écran et sur papier**, la provenance disparaît. La directive
 *    d'impression exige les adresses « en toutes lettres » parce qu'une source
 *    qu'on ne peut pas retrouver ne prouve rien ; vingt fois la même adresse de
 *    redirecteur ne prouve rien non plus.
 * 2. **Au score**, `fiabiliteDeLaSource()` cherchait `trusted_sources` sur
 *    `vertexaisearch.cloud.google.com` — absent du référentiel, donc défaut
 *    pour tout le monde. Les 408 fiches de crédibilité tenues par la rédaction
 *    n'avaient AUCUN effet sur les sources trouvées par Gemini, c'est-à-dire
 *    sur la quasi-totalité d'entre elles.
 *
 * ## Où est le vrai domaine
 *
 * Dans `groundingChunks[].web.title`, que l'API renseigne avec le domaine de
 * la page (« lemonde.fr », « gouv.bj ») et non avec son titre. On s'en sert
 * quand — et seulement quand — l'adresse est celle d'un redirecteur connu : un
 * titre de page ordinaire ne doit jamais être pris pour un domaine.
 */
final class DomaineDeLaSource
{
    /**
     * Les hôtes qui ne sont pas des sources, seulement des passages.
     *
     * Énumérés et non devinés : un domaine absent de cette liste est traité
     * comme une vraie source, ce qui est le comportement sûr. Se tromper dans
     * l'autre sens — prendre un média pour un redirecteur — ferait afficher le
     * titre de la page à la place de son domaine.
     *
     * @var list<string>
     */
    private const REDIRECTEURS = [
        'vertexaisearch.cloud.google.com',
        'grounding-api-redirect.googleapis.com',
        'www.google.com',
        'news.google.com',
    ];

    /**
     * Le domaine à AFFICHER et à chercher au référentiel.
     *
     * Rend l'hôte de l'adresse quand elle est directe. Quand c'est un
     * redirecteur, rend le domaine lu dans le nom de la source — s'il ressemble
     * vraiment à un domaine. Sinon, l'hôte du redirecteur : dire « Google »
     * reste plus honnête que d'inventer une provenance.
     */
    public function pour(string $url, ?string $nomSource = null): string
    {
        $hote = $this->hote($url);

        if ($hote === null) {
            return $url;
        }

        if (! $this->estUnRedirecteur($hote)) {
            return $hote;
        }

        $devine = $this->domaineDansLeNom($nomSource);

        return $devine ?? $hote;
    }

    /**
     * Ce domaine porte-t-il un suffixe RÉSERVÉ aux institutions ?
     *
     * Partagé entre `AnalyseTechniqueAdapter` — qui s'en sert pour ne pas
     * accuser une institution d'usurper son propre nom — et
     * `GoogleCustomSearchAdapter`, qui s'en sert comme défaut de crédibilité.
     * Une seule définition : deux motifs divergents donneraient deux réponses
     * à la même question.
     *
     * ## Un motif, pas une liste de pays
     *
     * L'une des deux listes d'origine comptait sept entrées, toutes
     * francophones ou anglophones. Un portail d'État nigérian ou mexicain ne
     * recevait donc aucun crédit institutionnel là où son équivalent français
     * en recevait un. Allonger la liste reproduirait le défaut un pays plus
     * loin : on reconnaît la FORME du segment réservé, qui est la même partout.
     *
     * Les segments sont les abréviations administratives employées par les
     * registres nationaux, plus `int` (organisations créées par traité) et
     * `mil`. `edu` et `ac` en sont volontairement ABSENTS : une université est
     * une source légitime, mais pas une autorité institutionnelle au sens de ce
     * signal.
     *
     * Le segment doit être ENTIER et terminer le domaine : `mongov.example.com`,
     * `actu-gouv-benin.info` et `sante.gouv.fr.evil.com` n'obtiennent rien.
     */
    public function estInstitutionnel(string $hoteOuUrl): bool
    {
        $hote = str_contains($hoteOuUrl, '/') ? $this->hote($hoteOuUrl) : $hoteOuUrl;

        if ($hote === null) {
            return false;
        }

        return preg_match(
            '/(?:^|\.)(?:gov|gouv|gob|go|gc|govt|gv|int|mil)(?:\.[a-z]{2,})?$/i',
            $hote,
        ) === 1;
    }

    /**
     * L'hôte publie-t-il du contenu DÉPOSÉ PAR SES UTILISATEURS ?
     *
     * Un signal de forme, indépendant de toute géographie : sur une plateforme
     * ouverte, l'hôte ne se porte pas garant de ce qui s'y publie. Un article
     * de blog hébergé n'engage pas la plateforme qui l'héberge, et un billet
     * sur un réseau social n'engage que son auteur.
     *
     * Cela ne dit rien de la VÉRACITÉ du contenu — une vidéo authentique circule
     * souvent d'abord sur un réseau social. Cela dit seulement que l'hôte
     * n'apporte aucune garantie éditoriale, donc qu'il ne peut pas servir de
     * caution.
     *
     * @var list<string>
     */
    private const PLATEFORMES_OUVERTES = [
        'facebook.', 'instagram.', 'tiktok.', 'x.com', 'twitter.',
        'youtube.', 'youtu.be', 'reddit.', 'telegram.', 'whatsapp.',
        'blogspot.', 'wordpress.com', 'medium.com', 'substack.com',
        'wixsite.com', 'over-blog.', 'tumblr.com', 'quora.com',
    ];

    public function estUnePlateformeOuverte(string $hoteOuUrl): bool
    {
        $hote = mb_strtolower((string) (str_contains($hoteOuUrl, '/') ? $this->hote($hoteOuUrl) : $hoteOuUrl));

        foreach (self::PLATEFORMES_OUVERTES as $plateforme) {
            if (str_contains($hote, $plateforme)) {
                return true;
            }
        }

        return false;
    }

    /** L'adresse passe-t-elle par un redirecteur plutôt que de mener à la source ? */
    public function estUnRedirecteur(string $hoteOuUrl): bool
    {
        $hote = str_contains($hoteOuUrl, '/') ? $this->hote($hoteOuUrl) : $hoteOuUrl;

        return $hote !== null && in_array(mb_strtolower($hote), self::REDIRECTEURS, true);
    }

    /**
     * Le nom de la source EST-il un domaine ?
     *
     * Le contrôle est volontairement strict : un ou plusieurs libellés séparés
     * par des points, suivis d'une extension de deux lettres au moins, et rien
     * d'autre. « gouv.bj » passe, « lemonde.fr » passe, « Le Monde » non, et
     * « Communiqué du 3 mars. » non plus — c'est exactement le genre de titre
     * qu'on ne veut pas voir s'afficher en guise de provenance.
     */
    private function domaineDansLeNom(?string $nom): ?string
    {
        $candidat = mb_strtolower(trim((string) $nom));

        if ($candidat === '' || str_contains($candidat, ' ')) {
            return null;
        }

        return preg_match('/^(?:[a-z0-9](?:[a-z0-9-]*[a-z0-9])?\.)+[a-z]{2,}$/', $candidat) === 1
            ? $candidat
            : null;
    }

    private function hote(string $url): ?string
    {
        $hote = parse_url($url, PHP_URL_HOST);

        return is_string($hote) && $hote !== '' ? $hote : null;
    }

    /**
     * Les suffixes à DEUX niveaux les plus courants — ce que ce dépôt ne
     * peut pas déduire d'une règle de forme, faute de la liste IANA des
     * suffixes publics (aucune dépendance de ce genre n'est installée ici).
     *
     * ## Ce que c'est, et ce que ce n'est PAS
     *
     * Une approximation PRAGMATIQUE, pas la liste complète : elle couvre les
     * suffixes qu'une plateforme de fact-checking rencontre le plus souvent
     * (institutions et presse), pas les quelque mille entrées de la vraie
     * liste. `exemple.perso.free.fr` s'y tromperait. Le signal reste correct
     * pour l'écrasante majorité des sources réellement citées — et se
     * dégrader vers « deux derniers libellés » plutôt que de lever reste le
     * choix le plus sûr pour un domaine absent d'ici.
     *
     * @var list<string>
     */
    private const SUFFIXES_A_DEUX_NIVEAUX = [
        'co.uk', 'org.uk', 'gov.uk', 'ac.uk', 'co.jp', 'co.kr', 'co.za',
        'com.br', 'com.au', 'com.ng', 'com.gh', 'co.ke', 'co.tz',
        'gouv.bj', 'gouv.fr', 'gouv.ci', 'gouv.sn', 'gov.ng', 'gov.gh',
        'gob.mx', 'gob.ar', 'gob.pe', 'gob.cl',
    ];

    /**
     * Le domaine ENREGISTRABLE (eTLD+1) — ce qui identifie l'ÉDITEUR, pas la
     * page précise. Phase 5 : c'est lui qui sert à dédupliquer deux adresses
     * du même média, et non l'hôte brut, qui varie avec chaque sous-domaine.
     *
     * `www.lemonde.fr`, `m.lemonde.fr` et `amp.lemonde.fr` sont le MÊME
     * éditeur ; `sante.gouv.bj` et `travail.gouv.bj` ne le sont PAS — ce sont
     * deux administrations distinctes sous le même suffixe institutionnel.
     * On ne retire donc que les préfixes de PRÉSENTATION connus (`www`, `m`,
     * `amp`, `mobile`), jamais un sous-domaine qui pourrait nommer une entité
     * différente.
     */
    public function domaineEnregistrable(string $hoteOuUrl): ?string
    {
        $hote = str_contains($hoteOuUrl, '/') ? $this->hote($hoteOuUrl) : $hoteOuUrl;

        if ($hote === null || $hote === '') {
            return null;
        }

        $hote = mb_strtolower($hote);
        $hote = (string) preg_replace('/^(www|m|amp|mobile)\./', '', $hote);

        $labels = explode('.', $hote);

        if (count($labels) < 2) {
            return $hote;
        }

        foreach (self::SUFFIXES_A_DEUX_NIVEAUX as $suffixe) {
            if (str_ends_with($hote, '.'.$suffixe) || $hote === $suffixe) {
                $segments = explode('.', $suffixe);
                $avant = count($labels) - count($segments);

                return $avant > 0 ? implode('.', array_slice($labels, $avant - 1)) : $hote;
            }
        }

        // Défaut : les deux derniers libellés — correct pour `.com`, `.fr`,
        // `.bj`, `.info` et la grande majorité des suffixes à un niveau.
        return implode('.', array_slice($labels, -2));
    }
}
