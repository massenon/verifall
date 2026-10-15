<?php

declare(strict_types=1);

namespace App\Core\Marketing\Cartes;

use App\Enums\VerdictFactCheck;
use App\Jobs\EngendrerLaCarteDeDementi;
use App\Models\HubArticle;
use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use GdImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * La carte de démenti — un verdict qui se transfère comme une rumeur.
 *
 * ## Le problème
 *
 * Au Bénin, une rumeur voyage EN IMAGE sur WhatsApp. Un démenti qui ne se
 * partage que sous forme de lien ne circule pas à parité avec ce qu'il
 * dément : le lien demande d'ouvrir un navigateur, d'attendre un site, de
 * lire. L'image, elle, se transfère et se lit dans le fil. Tant que la riposte
 * n'a pas le format de l'attaque, elle arrive après.
 *
 * ## Ce que la carte porte, dans l'ordre où l'œil les prend
 *
 * 1. le VERDICT, en très gros, dans la couleur du verdict ;
 * 2. l'affirmation examinée, telle qu'elle circule ;
 * 3. une phrase de raison — le chapô, à défaut l'explication de l'enum ;
 * 4. l'adresse de la page, en toutes lettres ET en QR ;
 * 5. la marque et la DATE.
 *
 * La date n'est pas une décoration. Une carte sans date se recycle contre
 * nous : six mois plus tard, elle repart dans un fil comme si elle venait
 * d'être publiée — c'est exactement le mode de désinformation que la
 * plateforme combat, et le lui offrir serait le comble.
 *
 * ## Les contraintes qui décident du dessin
 *
 * - **Quelques dizaines de kilooctets.** Elle doit voyager sur 3G. Une carte
 *   de 500 Ko ne se partage pas : elle se met en file d'attente derrière une
 *   barre de progression, et le fil a déjà défilé.
 * - **GD, pas Imagick** — `extension_loaded('imagick')` est faux ici. D'où un
 *   dessin fait d'aplats et de texte, sans ombre portée ni dégradé : ce sont
 *   aussi les deux choses qui font grossir un PNG.
 * - **PNG indexé.** Le dessin ne compte qu'une poignée de teintes ; converti en
 *   palette, il pèse le tiers du même fichier en couleurs vraies, sans
 *   différence visible.
 */
final class CarteDeDementi
{
    /**
     * Le disque, et pourquoi celui-là.
     *
     * `public` et non `local` : ce fichier est fait pour être vu par le monde
     * entier, y compris par le robot de WhatsApp qui n'a aucune session. Il
     * n'est pas servi par le lien symbolique — voir `CarteDeDementiController`,
     * qui existe parce que `public/storage` n'est pas garanti en mutualisé.
     */
    public const DISQUE = 'public';

    public const DOSSIER = 'cartes-de-dementi';

    /**
     * 1200 × 630, et non 1080 × 1080.
     *
     * Le carré est meilleur DANS le fil WhatsApp : il prend plus de hauteur, il
     * se lit de plus loin. Mais la même image sert d'`og:image`, et tout ce qui
     * consomme un `og:image` recadre : Twitter/X en 2:1, les vignettes de
     * prévisualisation en 1.91:1. Un carré recadré perd ses bandes haute et
     * basse — c'est-à-dire, ici, le verdict et la date.
     *
     * Une carte qui perd son verdict au recadrage est pire qu'une carte un peu
     * moins haute. 1200 × 630 est le seul rapport que personne ne recadre.
     */
    public const LARGEUR = 1200;

    public const HAUTEUR = 630;

    /** Hauteur de la bande du verdict. */
    private const BANDE = 200;

    /** Ordonnée du filet qui ouvre le pied de carte. */
    private const PIED = 500;

    private const MARGE = 60;

    /**
     * La version du GABARIT entre dans l'empreinte.
     *
     * Sans elle, un changement de dessin ne regénérerait aucune carte
     * existante : le parc resterait figé sur l'ancien modèle, et personne ne
     * s'en apercevrait avant de comparer deux partages côte à côte.
     */
    private const GABARIT = 'v1';

    public static function policeGrasse(): string
    {
        return resource_path('fonts/Inter-Bold.ttf');
    }

    public static function policeDemiGrasse(): string
    {
        return resource_path('fonts/Inter-SemiBold.ttf');
    }

    public static function policeNormale(): string
    {
        return resource_path('fonts/Inter-Regular.ttf');
    }

    /**
     * Une carte n'est engendrée que pour une analyse PUBLIÉE et TRANCHÉE.
     *
     * Trois conditions, et chacune évite une carte fausse : un brouillon
     * partageable ferait circuler un verdict non relu ; un verdict absent
     * donnerait une bande vide ; une date de publication manquante donnerait
     * une carte sans date, c'est-à-dire réutilisable indéfiniment.
     */
    public function estEligible(HubArticle $article): bool
    {
        return $article->is_published === true
            && $article->verdict instanceof VerdictFactCheck
            && $article->published_at !== null;
    }

    /**
     * L'empreinte de ce que la carte MONTRE.
     *
     * Elle ne couvre que les données dessinées : changer la catégorie d'une
     * brève ne doit pas refabriquer son image. Elle sert à deux choses — savoir
     * s'il faut regénérer, et donner au fichier un nom qui change quand le
     * contenu change.
     *
     * Ce second point est le plus important en pratique : WhatsApp et Facebook
     * gardent une `og:image` en cache plusieurs jours PAR URL. Un verdict
     * corrigé sous une URL identique continuerait de s'afficher faux.
     */
    public function empreinte(HubArticle $article): string
    {
        return substr(hash('sha256', implode('|', [
            self::GABARIT,
            $article->verdict?->value ?? '',
            (string) $article->title,
            (string) ($article->excerpt ?? ''),
            $article->published_at?->toIso8601String() ?? '',
            (string) $article->slug,
        ])), 0, 12);
    }

    public function chemin(HubArticle $article): string
    {
        return self::DOSSIER.'/'.$article->uuid.'-'.$this->empreinte($article).'.png';
    }

    public function estAJour(HubArticle $article): bool
    {
        $chemin = $article->share_card_path;

        return is_string($chemin)
            && $chemin === $this->chemin($article)
            && Storage::disk(self::DISQUE)->exists($chemin);
    }

    /**
     * Décide s'il faut fabriquer, et met en file — sans jamais fabriquer ici.
     *
     * C'est le point d'accroche unique du lot : un observateur de modèle ou le
     * service qui publie appelle CETTE méthode, et rien d'autre. Elle est sans
     * effet quand rien n'a changé, donc appelable à chaque enregistrement.
     *
     * L'engendrement part en file d'attente parce qu'il dure ~150 ms et écrit
     * un fichier : le faire pendant la requête ferait attendre celui qui publie
     * pour un travail dont il n'a pas besoin du résultat.
     *
     * @return bool vrai si un travail a été mis en file
     */
    public function planifierSiNecessaire(HubArticle $article): bool
    {
        if (! $this->estEligible($article) || $this->estAJour($article)) {
            return false;
        }

        EngendrerLaCarteDeDementi::dispatch($article->id);

        return true;
    }

    /** Le travail de la file : recharge, fabrique, range. */
    public function engendrerPourIdentifiant(int $identifiant): ?string
    {
        $article = HubArticle::query()->find($identifiant);

        return $article === null ? null : $this->engendrer($article);
    }

    /**
     * Fabrique la carte, l'écrit, et retient son chemin.
     *
     * L'ancienne carte est effacée : sans cela, chaque correction de verdict
     * laisserait un fichier orphelin sur le disque, et le parc grossirait d'un
     * fichier par correction sans que personne ne le voie.
     */
    public function engendrer(HubArticle $article): ?string
    {
        if (! $this->estEligible($article)) {
            return null;
        }

        $chemin = $this->chemin($article);
        $ancien = $article->share_card_path;

        Storage::disk(self::DISQUE)->put($chemin, $this->octetsPng($article));

        if (is_string($ancien) && $ancien !== '' && $ancien !== $chemin) {
            Storage::disk(self::DISQUE)->delete($ancien);
        }

        /*
         | `forceFill` : la colonne n'est pas dans `$fillable`, et elle n'a rien
         | à y faire — aucun formulaire ne la remplit, seule la file l'écrit.
         |
         | `saveQuietly` : le point d'accroche naturel de ce lot est l'événement
         | `saved` du modèle. Un `save()` ordinaire ici le referait partir, pour
         | un enregistrement qui n'a RIEN changé de ce que la carte montre. La
         | boucle s'arrêterait d'elle-même — `estAJour()` dirait vrai — mais
         | elle coûterait un accès disque à chaque fabrication, et surtout elle
         | rendrait le journal illisible.
         */
        $article->forceFill(['share_card_path' => $chemin])->saveQuietly();

        return $chemin;
    }

    /** Retire la carte et oublie son chemin. */
    public function supprimer(HubArticle $article): void
    {
        $chemin = $article->share_card_path;

        if (is_string($chemin) && $chemin !== '') {
            Storage::disk(self::DISQUE)->delete($chemin);
        }

        $article->forceFill(['share_card_path' => null])->saveQuietly();
    }

    /** Les octets du fichier rangé, s'il existe encore. */
    public function octets(HubArticle $article): ?string
    {
        $chemin = $article->share_card_path;

        if (! is_string($chemin) || $chemin === '' || ! Storage::disk(self::DISQUE)->exists($chemin)) {
            return null;
        }

        $contenu = Storage::disk(self::DISQUE)->get($chemin);

        return is_string($contenu) ? $contenu : null;
    }

    /**
     * L'URL publique de la carte, celle qui devient l'`og:image`.
     *
     * Le `?v=` porte l'empreinte : c'est lui qui fait relire l'image aux
     * plateformes quand un verdict est corrigé. La route, elle, reste stable —
     * un lien déjà partagé continue de fonctionner.
     */
    public function urlPublique(HubArticle $article): ?string
    {
        if (! is_string($article->share_card_path) || $article->share_card_path === '') {
            return null;
        }

        return route('public.fact.carte', $article).'?v='.$this->empreinte($article);
    }

    // ---------------------------------------------------------------- dessin

    /** Le PNG, en mémoire — aucun fichier temporaire. */
    public function octetsPng(HubArticle $article): string
    {
        $this->exigerLesPolices();

        $verdict = $article->verdict;

        if (! $verdict instanceof VerdictFactCheck) {
            throw new RuntimeException('Une carte de démenti sans verdict n’a rien à annoncer.');
        }

        $image = imagecreatetruecolor(self::LARGEUR, self::HAUTEUR);

        if ($image === false) {
            throw new RuntimeException('GD n’a pas pu ouvrir une image de 1200 × 630.');
        }

        imagealphablending($image, true);

        $this->fond($image);
        $this->bandeDuVerdict($image, $verdict);
        $this->corps($image, $article, $verdict);
        $this->pied($image, $article);

        return $this->encoder($image);
    }

    private function fond(GdImage $image): void
    {
        imagefilledrectangle(
            $image, 0, 0, self::LARGEUR - 1, self::HAUTEUR - 1,
            PaletteDuVerdict::allouer($image, PaletteDuVerdict::BLANC),
        );
    }

    /**
     * La bande, et le mot qu'on lit en un quart de seconde.
     *
     * Le libellé sort de l'enum, en capitales : FAUX, VRAI, TROMPEUR, NON
     * VÉRIFIABLE. Rien n'est inventé, et surtout rien n'est adouci.
     */
    private function bandeDuVerdict(GdImage $image, VerdictFactCheck $verdict): void
    {
        $couleur = PaletteDuVerdict::allouer($image, PaletteDuVerdict::bande($verdict));
        imagefilledrectangle($image, 0, 0, self::LARGEUR - 1, self::BANDE - 1, $couleur);

        $blanc = PaletteDuVerdict::allouer($image, PaletteDuVerdict::BLANC);
        $attenue = PaletteDuVerdict::allouer($image, PaletteDuVerdict::BLANC, 45);

        // Le mot « VERDICT » est celui de la page vers laquelle la carte
        // renvoie (« VERDICT : DÉMENTI ») : la carte et la page se répondent.
        imagettftext($image, 22, 0, self::MARGE, 56, $attenue, self::policeDemiGrasse(), 'VERDICT');

        $mot = mb_strtoupper($verdict->libelle(), 'UTF-8');
        $taille = 104.0;
        $largeurUtile = self::LARGEUR - 2 * self::MARGE;

        // « NON VÉRIFIABLE » est trois fois plus long que « VRAI » : la taille
        // s'ajuste plutôt que le texte ne se coupe. Un verdict tronqué serait
        // un autre verdict.
        while ($taille > 40.0 && $this->largeur($mot, self::policeGrasse(), $taille) > $largeurUtile) {
            $taille -= 2.0;
        }

        imagettftext($image, $taille, 0, self::MARGE, 176, $blanc, self::policeGrasse(), $mot);
    }

    private function corps(GdImage $image, HubArticle $article, VerdictFactCheck $verdict): void
    {
        $encre = PaletteDuVerdict::allouer($image, PaletteDuVerdict::ENCRE);
        $gris = PaletteDuVerdict::allouer($image, PaletteDuVerdict::CORPS);
        $largeurUtile = self::LARGEUR - 2 * self::MARGE;

        imagettftext($image, 19, 0, self::MARGE, 250, $gris, self::policeDemiGrasse(), 'AFFIRMATION EXAMINÉE');

        $y = 306;

        foreach ($this->decouperEnLignes((string) $article->title, self::policeGrasse(), 42.0, $largeurUtile, 3) as $ligne) {
            imagettftext($image, 42, 0, self::MARGE, $y, $encre, self::policeGrasse(), $ligne);
            $y += 52;
        }

        /*
         | La phrase de raison : le chapô rédigé s'il existe, sinon
         | l'explication de l'enum — qui est écrite pour le citoyen, pas pour le
         | technicien. Jamais une phrase fabriquée pour l'occasion.
         */
        $raison = trim((string) ($article->excerpt ?? ''));
        $raison = $raison !== '' ? $raison : $verdict->explication();

        $y += 14;

        /*
         | Le pied de carte n'est jamais entamé : un titre de trois lignes
         | laisse moins de place à la raison, et c'est la raison qui cède. Le
         | nombre de lignes restantes se CALCULE avant la découpe, jamais après
         | — sinon la dernière ligne dessinée serait coupée en pleine phrase,
         | sans les points de suspension qui préviennent le lecteur.
         */
        $restantes = max(1, min(2, intdiv(self::PIED - 16 - $y, 32) + 1));

        foreach ($this->decouperEnLignes($raison, self::policeNormale(), 24.0, $largeurUtile, $restantes) as $ligne) {
            imagettftext($image, 24, 0, self::MARGE, $y, $gris, self::policeNormale(), $ligne);
            $y += 32;
        }
    }

    private function pied(GdImage $image, HubArticle $article): void
    {
        $encre = PaletteDuVerdict::allouer($image, PaletteDuVerdict::ENCRE);
        $gris = PaletteDuVerdict::allouer($image, PaletteDuVerdict::CORPS);

        imagefilledrectangle(
            $image, 0, self::PIED, self::LARGEUR - 1, self::HAUTEUR - 1,
            PaletteDuVerdict::allouer($image, PaletteDuVerdict::PIED),
        );
        imagefilledrectangle(
            $image, 0, self::PIED, self::LARGEUR - 1, self::PIED,
            PaletteDuVerdict::allouer($image, PaletteDuVerdict::LIGNE),
        );

        $qr = $this->qrCode($image, route('public.fact', $article));

        $this->marque($image);

        // L'adresse en toutes lettres : le QR sert à celui qui a un second
        // appareil sous la main, le texte à tous les autres.
        imagettftext($image, 21, 0, self::MARGE, 594, $gris, self::policeNormale(), $this->adresseCourte($article));

        $date = 'Publié le '.$article->published_at?->locale('fr')->translatedFormat('j F Y');
        $largeurDate = $this->largeur($date, self::policeDemiGrasse(), 21.0);

        imagettftext(
            $image, 21, 0, $qr - 28 - $largeurDate, 572, $encre,
            self::policeDemiGrasse(), $date,
        );
    }

    /**
     * Le logo, posé depuis le fichier du prototype.
     *
     * Redessiner la marque au trait produirait une seconde marque. On copie
     * celle qui existe, et si le fichier manque on écrit le nom : une carte
     * sans logo reste lisible, une carte qui ne s'engendre pas ne dément rien.
     */
    private function marque(GdImage $image): void
    {
        $fichier = public_path('assets/images/logo.png');
        $hauteur = 42;

        if (is_file($fichier)) {
            $logo = @imagecreatefrompng($fichier);

            if ($logo !== false) {
                $largeur = (int) round(imagesx($logo) * $hauteur / imagesy($logo));
                imagecopyresampled($image, $logo, self::MARGE, 530, 0, 0, $largeur, $hauteur, imagesx($logo), imagesy($logo));
                imagedestroy($logo);

                return;
            }
        }

        imagettftext(
            $image, 30, 0, self::MARGE, 562,
            PaletteDuVerdict::allouer($image, PaletteDuVerdict::ENCRE),
            self::policeGrasse(), "Vérif'All",
        );
    }

    /**
     * Le QR du lien, dessiné module par module.
     *
     * `Parrainage::qrCode()` emploie le rendu SVG de BaconQrCode — parfait dans
     * une page, inutilisable ici : GD ne rastérise pas le SVG, et Imagick n'est
     * pas installé. On descend donc d'un cran, jusqu'à la matrice, et on la
     * dessine en rectangles. Aucune dépendance nouvelle.
     *
     * @return int l'abscisse du bord gauche du bloc, dont la date se tient à distance
     */
    private function qrCode(GdImage $image, string $url): int
    {
        $matrice = Encoder::encode($url, ErrorCorrectionLevel::M())->getMatrix();
        $modules = $matrice->getWidth();

        /*
         | La zone de silence fait partie du code : sans elle, un lecteur ne
         | retrouve pas les repères d'angle. La norme en demande quatre modules ;
         | on n'en DESSINE que deux parce que le pied de carte est déjà
         | quasi-blanc (`#F8F9FA`) — le silence réel dépasse donc largement les
         | quatre modules, tandis que les deux modules rendus laissent au code
         | de quoi grossir d'un pixel par module. Sur une URL longue, c'est la
         | différence entre un QR qui se scanne et un QR décoratif.
         */
        $silence = 2;
        $cible = 126;
        $pas = max(2, intdiv($cible, $modules + 2 * $silence));
        $cote = ($modules + 2 * $silence) * $pas;

        $gauche = self::LARGEUR - self::MARGE - $cote;
        $haut = self::PIED + (int) round((self::HAUTEUR - self::PIED - $cote) / 2);

        imagefilledrectangle(
            $image, $gauche, $haut, $gauche + $cote - 1, $haut + $cote - 1,
            PaletteDuVerdict::allouer($image, PaletteDuVerdict::BLANC),
        );

        $noir = PaletteDuVerdict::allouer($image, PaletteDuVerdict::ENCRE);

        for ($y = 0; $y < $modules; $y++) {
            for ($x = 0; $x < $modules; $x++) {
                if ($matrice->get($x, $y) !== 1) {
                    continue;
                }

                $x0 = $gauche + ($silence + $x) * $pas;
                $y0 = $haut + ($silence + $y) * $pas;
                imagefilledrectangle($image, $x0, $y0, $x0 + $pas - 1, $y0 + $pas - 1, $noir);
            }
        }

        return $gauche;
    }

    /**
     * L'adresse, sans son protocole.
     *
     * `https://` occupe huit caractères et n'apprend rien à personne. Ce qui
     * compte, sur une carte, c'est de reconnaître le domaine.
     */
    private function adresseCourte(HubArticle $article): string
    {
        $adresse = preg_replace('#^https?://#i', '', route('public.fact', $article)) ?? '';

        return $this->decouperEnLignes($adresse, self::policeNormale(), 21.0, 620, 1)[0] ?? '';
    }

    // ------------------------------------------------------------ typographie

    /**
     * Découpe un texte en lignes qui TIENNENT, et coupe proprement au-delà.
     *
     * Trois cas, et le troisième est celui qui casse les cartes en production :
     * un mot plus long que la ligne — une URL collée dans un titre, un
     * mot-dièse à rallonge. Sans découpe au caractère, il dépasse du cadre et
     * personne ne le voit avant le premier partage.
     *
     * Le dernier caractère conservé n'est jamais une virgule ni un tiret
     * pendus devant les points de suspension.
     *
     * @return list<string>
     */
    public function decouperEnLignes(string $texte, string $police, float $taille, int $largeurMax, int $maxLignes): array
    {
        $mots = preg_split('/\s+/u', trim($texte)) ?: [];
        $lignes = [];
        $courante = '';

        foreach ($mots as $mot) {
            if ($mot === '') {
                continue;
            }

            foreach ($this->fragments($mot, $police, $taille, $largeurMax) as $fragment) {
                $essai = $courante === '' ? $fragment : $courante.' '.$fragment;

                if ($courante === '' || $this->largeur($essai, $police, $taille) <= $largeurMax) {
                    $courante = $essai;

                    continue;
                }

                $lignes[] = $courante;
                $courante = $fragment;

                // Inutile de mesurer ce qui ne sera jamais dessiné : un titre de
                // trois mille caractères ne doit pas coûter trois mille mesures
                // FreeType. On s'arrête dès qu'il y a de quoi tronquer.
                if (count($lignes) > $maxLignes) {
                    break 2;
                }
            }
        }

        if ($courante !== '') {
            $lignes[] = $courante;
        }

        if (count($lignes) <= $maxLignes) {
            return array_values($lignes);
        }

        $lignes = array_slice($lignes, 0, $maxLignes);
        $derniere = rtrim($lignes[$maxLignes - 1], " \t,.;:—–-");

        while ($derniere !== '' && $this->largeur($derniere.'…', $police, $taille) > $largeurMax) {
            $derniere = rtrim(mb_substr($derniere, 0, max(0, mb_strlen($derniere) - 1)), " \t,.;:—–-");
        }

        $lignes[$maxLignes - 1] = $derniere.'…';

        return array_values($lignes);
    }

    /**
     * Un mot, découpé en morceaux qui tiennent chacun sur une ligne.
     *
     * @return list<string>
     */
    private function fragments(string $mot, string $police, float $taille, int $largeurMax): array
    {
        if ($this->largeur($mot, $police, $taille) <= $largeurMax) {
            return [$mot];
        }

        $fragments = [];
        $courant = '';
        $longueur = mb_strlen($mot);

        for ($i = 0; $i < $longueur; $i++) {
            $caractere = mb_substr($mot, $i, 1);

            if ($courant !== '' && $this->largeur($courant.$caractere, $police, $taille) > $largeurMax) {
                $fragments[] = $courant;
                $courant = '';
            }

            $courant .= $caractere;
        }

        if ($courant !== '') {
            $fragments[] = $courant;
        }

        return $fragments;
    }

    /** La largeur d'un texte rendu, en pixels. */
    public function largeur(string $texte, string $police, float $taille): int
    {
        if ($texte === '') {
            return 0;
        }

        $boite = imagettfbbox($taille, 0, $police, $texte);

        if ($boite === false) {
            throw new RuntimeException("FreeType n’a pas pu mesurer avec la police {$police}.");
        }

        $abscisses = [$boite[0], $boite[2], $boite[4], $boite[6]];

        return (int) (max($abscisses) - min($abscisses));
    }

    /**
     * Les polices sont un PRÉREQUIS, pas une option.
     *
     * GD ne lit pas le `.woff2` du site : il lui faut du `.ttf`. Sans police,
     * `imagestring()` dessinerait la fonte bitmap intégrée — cinq pixels de
     * haut, sans accents, illisible sur une image de 1200 pixels de large.
     * Mieux vaut aucune carte qu'une carte que personne ne peut lire.
     */
    private function exigerLesPolices(): void
    {
        foreach ([self::policeGrasse(), self::policeDemiGrasse(), self::policeNormale()] as $police) {
            if (! is_file($police)) {
                throw new RuntimeException("Police introuvable : {$police} — voir resources/fonts/LISEZ-MOI.md");
            }
        }
    }

    /**
     * Encode en PNG indexé.
     *
     * Le dessin ne compte qu'une douzaine de teintes, plus le lissage du texte.
     * `imagetruecolortopalette` sans tramage les ramène à 255 : le fichier perd
     * une bonne part de son poids, et l'œil ne voit rien — alors que la
     * différence entre 40 Ko et 140 Ko, sur une 3G, se compte en secondes.
     */
    private function encoder(GdImage $image): string
    {
        imagetruecolortopalette($image, false, 255);

        ob_start();
        imagepng($image, null, 9);
        $octets = (string) ob_get_clean();

        imagedestroy($image);

        return $octets;
    }
}
