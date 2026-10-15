<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Adapters;

use App\Core\FactChecking\Services\AutoritesCompetentes;
use App\Core\FactChecking\ValueObjects\ContexteDuClaim;
use App\Core\FactChecking\ValueObjects\PaquetDePreuves;
use App\Core\FactChecking\ValueObjects\SacDePreuves;
use App\Enums\TypeContenu;
use Illuminate\Support\Facades\File;
use RuntimeException;

/**
 * Charge le gabarit de prompt et y injecte l'affirmation.
 *
 * Le gabarit vit dans `resources/prompts/factcheck/`, hors du code : c'est le
 * texte qui décide de la qualité des verdicts, il doit pouvoir être relu,
 * comparé et corrigé sans toucher à PHP — et son historique doit être lisible
 * dans le dépôt. Un prompt en dur dans une classe ne se relit pas.
 *
 * Le contenu du citoyen est inséré entre guillemets français et jamais
 * interprété comme une consigne : le gabarit énonce ses règles AVANT de le
 * présenter, ce qui limite la portée d'une tentative d'injection.
 */
final class ConstructeurDePrompt
{
    private const GABARIT = 'prompts/factcheck/analyse.md';

    /** Gabarit de la phase 2 — décomposition en claims, avant tout verdict. */
    private const GABARIT_EXTRACTION = 'prompts/factcheck/extraction.md';

    public function __construct(
        private readonly AutoritesCompetentes $autorites = new AutoritesCompetentes,
    ) {}

    /**
     * Les consignes propres au TYPE de contenu.
     *
     * Un fichier par type, nommé sur la VALEUR de l'enum — `text.md`, `url.md`,
     * `image.md`, `video.md`, `audio.md`.
     *
     * ## Pourquoi un fragment par type, et pas un seul prompt
     *
     * Les preuves qui font un rapport n'ont rien de commun d'un type à l'autre.
     * Pour un texte, ce sont le Journal officiel et la procédure applicable ;
     * pour un lien, l'ancienneté du domaine et ce que réclame le formulaire ;
     * pour une image, la première occurrence connue et les indices visuels —
     * plaques, uniformes, enseignes. Les énumérer tous dans un prompt unique
     * ferait lire au modèle quatre sections sans objet, et l'expérience montre
     * qu'il y pioche alors des critères inadaptés : des « indices visuels »
     * cherchés dans un message texte, par exemple.
     *
     * Chaque fragment se relit donc seul, se corrige seul, et son historique
     * est lisible dans le dépôt — la même raison qui veut que le gabarit
     * principal ne vive pas dans une classe PHP.
     */
    private const CONSIGNES = 'prompts/factcheck/types/';

    /**
     * Le bloc d'isolation du contenu non fiable.
     *
     * Placé EN TÊTE du prompt, avant toute consigne d'analyse, et non en
     * annexe : une règle de sécurité lue après le contenu qu'elle est censée
     * encadrer arrive trop tard.
     *
     * Il vit dans son propre fichier parce que c'est la seule partie du prompt
     * qui relève de la sécurité et non de l'éditorial. On doit pouvoir la
     * relire, la durcir et en suivre l'historique sans rouvrir les consignes
     * de rédaction — exigence de `SECURITE_RGPD.md` et de l'audit de la version
     * précédente, qui classait l'injection de prompt sur les adaptateurs IA en
     * prérequis de mise en production.
     */
    private const SECURITE = 'prompts/factcheck/security/adversarial-content.md';

    /**
     * Les balises qui encadrent la donnée dans le prompt.
     *
     * @var list<string>
     */
    private const DELIMITEURS = [
        '<CONTENU_NON_FIABLE>', '</CONTENU_NON_FIABLE>',
        '<CONTEXTE_NON_FIABLE>', '</CONTEXTE_NON_FIABLE>',
    ];

    public function pour(SacDePreuves $entree): string
    {
        $chemin = resource_path(self::GABARIT);

        if (! File::exists($chemin)) {
            throw new RuntimeException('Gabarit de prompt introuvable : '.self::GABARIT);
        }

        /*
         | L'ORDRE DE SUBSTITUTION COMPTE.
         |
         | Les consignes sont injectées d'abord, le contenu du citoyen ENSUITE.
         | Sans cela, un contenu portant littéralement « {{CONSIGNES_DU_TYPE}} »
         | ou « {{CONSIGNES_DE_SECURITE}} » verrait son marqueur remplacé par du
         | vrai prompt au passage suivant : le contenu soumis se retrouverait
         | capable d'injecter des consignes dans le prompt. `str_replace` avec
         | des tableaux parallèles fait exactement cela — il repasse sur le
         | résultat des substitutions précédentes.
         |
         | Deux passes, donc, et le contenu arrive en dernier.
         */
        $prompt = str_replace(
            [
                '{{CONSIGNES_DE_SECURITE}}', '{{CONSIGNES_DU_TYPE}}', '{{DATE}}', '{{PORTEE_TEMPORELLE}}',
                '{{AUTORITES_COMPETENTES}}', '{{PAQUET_DE_PREUVES}}', '{{CONSIGNES_DE_CITATION}}',
            ],
            [
                $this->consignesDeSecurite(),
                $this->consignesDuType($entree->type),
                now()->locale('fr')->isoFormat('D MMMM YYYY'),
                $this->consignesTemporelles($entree->contexteTemporel),
                // Phase 4 : une FONCTION générique, jamais un pays. Lève
                // C-06.1 — `analyse.md` disait « gouv.bj » en dur.
                $this->autorites->pour($entree->affirmation),
                $this->blocDuPaquet($entree->paquetDePreuves),
                $this->consignesDeCitation($entree->paquetDePreuves),
            ],
            File::get($chemin),
        );

        return str_replace(
            ['{{CLAIM}}', '{{CONTEXTE}}'],
            [
                // Borné : au-delà, on paie des jetons pour du bruit, et les
                // modèles perdent le fil.
                $this->neutraliserLesDelimiteurs(mb_substr(trim($entree->affirmation), 0, 4000)),
                $entree->contexte !== null && trim($entree->contexte) !== ''
                    ? $this->neutraliserLesDelimiteurs(mb_substr(trim($entree->contexte), 0, 300))
                    : '(aucun contexte fourni)',
            ],
            $prompt,
        );
    }

    /**
     * Le prompt d'extraction — MÊME quarantaine, contenu brut et non un claim.
     *
     * Appelé AVANT qu'aucun claim n'existe : le contenu soumis est encore une
     * donnée brute, jamais un objet vérifiable. C'est la première fois qu'un
     * contenu non fiable atteint un modèle, donc la quarantaine s'applique
     * ici exactement comme sur le gabarit d'analyse — un contenu qui injecte
     * des instructions ne doit pas pouvoir décider lui-même en combien de
     * claims il se décompose.
     */
    public function pourExtraction(string $contenuBrut, ?string $contexte): string
    {
        $chemin = resource_path(self::GABARIT_EXTRACTION);

        if (! File::exists($chemin)) {
            throw new RuntimeException('Gabarit de prompt introuvable : '.self::GABARIT_EXTRACTION);
        }

        $prompt = str_replace(
            ['{{CONSIGNES_DE_SECURITE}}', '{{DATE}}'],
            [$this->consignesDeSecurite(), now()->locale('fr')->isoFormat('D MMMM YYYY')],
            File::get($chemin),
        );

        return str_replace(
            ['{{CLAIM}}', '{{CONTEXTE}}'],
            [
                $this->neutraliserLesDelimiteurs(mb_substr(trim($contenuBrut), 0, 4000)),
                $contexte !== null && trim($contexte) !== ''
                    ? $this->neutraliserLesDelimiteurs(mb_substr(trim($contexte), 0, 300))
                    : '(aucun contexte fourni)',
            ],
            $prompt,
        );
    }

    /**
     * Empêche le contenu de refermer le bloc qui l'isole.
     *
     * C'est l'évasion la plus simple qui soit : il suffit d'écrire
     * `</CONTENU_NON_FIABLE>` dans le champ pour que la suite du message se
     * retrouve, aux yeux du modèle, HORS de la zone de quarantaine — et donc
     * au même rang que les consignes du système. Le prompt dit déjà qu'une
     * balise interne est une donnée ; on ne s'en remet pas à la bonne volonté
     * du modèle pour une garde aussi élémentaire.
     *
     * Les chevrons sont remplacés par leurs équivalents typographiques, non
     * échappés en entités : le modèle lit un texte, pas du HTML, et
     * `&lt;/CONTENU_NON_FIABLE&gt;` resterait reconnaissable comme une balise.
     * Le contenu reste lisible — ce qui compte, puisqu'une tentative d'évasion
     * est elle-même un signal éditorial que l'analyse peut relever.
     */
    public function neutraliserLesDelimiteurs(string $contenu): string
    {
        return str_replace(
            self::DELIMITEURS,
            array_map(
                static fn (string $balise): string => '‹'.trim($balise, '<>').'›',
                self::DELIMITEURS,
            ),
            $contenu,
        );
    }

    /**
     * Le bloc de sécurité, ou un refus franc.
     *
     * Contrairement aux consignes de type, un fragment manquant ici LÈVE. La
     * tolérance appliquée au fragment de type — faire tourner l'analyse plutôt
     * que de faire échouer une vérification payée — ne vaut pas pour la
     * sécurité : une analyse sans isolation du contenu n'est pas une analyse
     * dégradée, c'est une analyse dont le verdict peut être dicté par le
     * contenu soumis. Mieux vaut l'échec, visible, et les unités rendues.
     */
    private function consignesDeSecurite(): string
    {
        $chemin = resource_path(self::SECURITE);

        if (! File::exists($chemin)) {
            throw new RuntimeException('Bloc de sécurité introuvable : '.self::SECURITE);
        }

        return trim(File::get($chemin));
    }

    /**
     * La consigne temporelle calculée par le SYSTÈME pour ce claim — phase 3.
     *
     * « La temporalité devient une donnée du domaine, pas une consigne de
     * prompt » : ce bloc dit au modèle, sans qu'il ait à le déduire lui-même,
     * si sa mémoire est admise et ce qu'il doit chercher sinon. Il
     * COMPLÈTE la vigilance générale du gabarit (§ JUSTESSE TEMPORELLE), il
     * ne la remplace pas — celle-ci reste le filet pour tout claim que la
     * phase 3 n'aurait pas classé.
     *
     * `null` quand aucune classification n'existe : les appels d'avant cette
     * phase, et les moteurs interrogés hors du pipeline de claims. Le
     * gabarit garde alors sa vigilance générale, sans affirmation fausse sur
     * une classification qui n'a pas eu lieu.
     */
    private function consignesTemporelles(?ContexteDuClaim $contexte): string
    {
        return $contexte?->consigne()
            ?? "Aucune classification temporelle automatique n'est disponible pour cette affirmation : applique la vigilance générale ci-dessus.";
    }

    /**
     * Le paquet de preuves — contrat V-10, phase 7.
     *
     * `null` tant que le cutover n'est pas actif : le prompt ne mentionne
     * alors aucun paquet, à l'identique d'avant cette phase. Un paquet NON
     * NUL change le contrat de citation ({@see consignesDeCitation()}) — les
     * deux voyagent toujours ensemble, jamais l'un sans l'autre.
     */
    private function blocDuPaquet(?PaquetDePreuves $paquet): string
    {
        if ($paquet === null) {
            return '';
        }

        return "PREUVES DISPONIBLES — CITE UNIQUEMENT CES IDENTIFIANTS, JAMAIS UNE URL :\n"
            .'Chaque identifiant ci-dessous correspond à une page que la plateforme a RÉELLEMENT récupérée et vérifiée. '
            ."Tu ne peux citer, dans \"source_ids\", qu'un identifiant qui figure dans cette liste. "
            ."Toute adresse que tu nommerais toi-même, ou tout identifiant absent de cette liste, sera IGNORÉ — jamais traité comme une preuve.\n\n"
            .$paquet->pourPrompt();
    }

    /**
     * Le format de citation attendu dans la réponse JSON — contrat V-10.
     *
     * Les deux formes ne coexistent JAMAIS dans la même réponse : un paquet
     * absent garde le format `sources:[{url}]` d'avant cette phase, à
     * l'identique ; un paquet présent impose `source_ids`, et SEULEMENT lui.
     */
    private function consignesDeCitation(?PaquetDePreuves $paquet): string
    {
        if ($paquet === null) {
            return <<<'TXT'
              "sources": [
                {
                  "name": "Nom de la source",
                  "url": "https://...",
                  "stance": "SUPPORTS | REFUTES | NEUTRAL"
                }
              ],
            TXT;
        }

        return <<<'TXT'
              "source_ids": [
                {
                  "id": "SRC_001",
                  "stance": "SUPPORTS | REFUTES | NEUTRAL"
                }
              ],
            TXT;
    }

    /**
     * Le fragment du type, ou rien.
     *
     * Un fragment manquant ne lève PAS : l'analyse tourne avec le gabarit
     * principal, qui tient déjà debout tout seul. Faire échouer une
     * vérification payée en unités parce qu'un fichier de prompt a été renommé
     * serait hors de proportion — le test `ConsignesDeTypeTest` garantit par
     * ailleurs que les cinq existent.
     */
    private function consignesDuType(TypeContenu $type): string
    {
        $chemin = resource_path(self::CONSIGNES.$type->value.'.md');

        return File::exists($chemin) ? trim(File::get($chemin)) : '';
    }
}
