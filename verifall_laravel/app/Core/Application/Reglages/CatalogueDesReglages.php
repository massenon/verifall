<?php

declare(strict_types=1);

namespace App\Core\Application\Reglages;

use App\Core\Application\Services\ModeMaintenance;
use App\Enums\MoteurAnalyse;

/**
 * TOUS les réglages de la plateforme, déclarés une seule fois.
 *
 * Le formulaire, la validation, le chiffrement, le masquage des secrets et la
 * purge du journal d'audit se déduisent de ce fichier. Ajouter un réglage, ici,
 * c'est ajouter une ligne — pas toucher à quatre endroits dont le quatrième
 * s'oublie.
 *
 * ## Deux règles à respecter en ajoutant une entrée
 *
 * **`secret: true` sur tout ce qui ouvre une porte.** Clé d'API, mot de passe,
 * jeton. Sans ce marquage, la valeur part en clair dans la base, dans chaque
 * sauvegarde, et repart vers le navigateur à chaque affichage du formulaire.
 *
 * **Une aide qui dit ce qu'on ne devine pas.** « Serveur SMTP » n'apprend rien ;
 * « laissez vide pour désactiver l'envoi de courriels » évite une panne.
 */
final class CatalogueDesReglages
{
    public const GROUPE_GENERAL = 'general';

    public const GROUPE_APPARENCE = 'apparence';

    public const GROUPE_SEO = 'seo';

    public const GROUPE_ECONOMIE = 'economie';

    public const GROUPE_EMAIL = 'email';

    public const GROUPE_PAIEMENT = 'paiement';

    public const GROUPE_ANALYTIQUE = 'analytique';

    public const GROUPE_API = 'api';

    public const GROUPE_MOTEURS = 'moteurs';

    public const GROUPE_SECURITE = 'securite';

    public const GROUPE_CERTIFICATS = 'certificats';

    /**
     * Les onglets, dans l'ordre d'affichage.
     *
     * L'ordre suit la fréquence d'usage, pas la logique technique : ce qu'on
     * modifie souvent vient d'abord.
     *
     * @return array<string, array{libelle: string, icone: string, aide: string}>
     */
    public static function groupes(): array
    {
        return [
            self::GROUPE_GENERAL => [
                'libelle' => 'Général',
                'icone' => 'tune',
                'aide' => "L'identité du produit et ses coordonnées publiques.",
            ],
            self::GROUPE_APPARENCE => [
                'libelle' => 'Apparence',
                'icone' => 'design_services',
                'aide' => 'Logos, icônes et image de partage. Un aperçu accompagne chaque dépôt.',
            ],
            self::GROUPE_SEO => [
                'libelle' => 'SEO & réseaux',
                'icone' => 'travel_explore',
                'aide' => 'Ce que les moteurs et les réseaux sociaux affichent de vous.',
            ],
            self::GROUPE_ECONOMIE => [
                'libelle' => 'Économie',
                'icone' => 'toll',
                'aide' => "Ce que reçoit un citoyen, et ce qu'une action lui rapporte.",
            ],
            self::GROUPE_EMAIL => [
                'libelle' => 'E-mail (SMTP)',
                'icone' => 'mail',
                'aide' => "Sans ces réglages, aucun code de vérification ne part — et personne ne peut s'inscrire.",
            ],
            self::GROUPE_PAIEMENT => [
                'libelle' => 'Paiement',
                'icone' => 'payments',
                'aide' => 'Clés des opérateurs. Elles ne repartent jamais vers le navigateur.',
            ],
            self::GROUPE_ANALYTIQUE => [
                'libelle' => 'Analytique',
                'icone' => 'monitoring',
                'aide' => 'Mesure d’audience — et le consentement qu’elle exige.',
            ],
            self::GROUPE_API => [
                'libelle' => 'API externes',
                'icone' => 'science',
                'aide' => 'Les moteurs de vérification. Une clé vide désactive son moteur.',
            ],
            self::GROUPE_MOTEURS => [
                'libelle' => 'Moteurs d’analyse',
                'icone' => 'toggle_on',
                'aide' => 'Allumer ou éteindre un moteur, indépendamment de sa clé. Un moteur éteint n’est pas appelé et ne coûte rien.',
            ],
            self::GROUPE_SECURITE => [
                'libelle' => 'Sécurité & accès',
                'icone' => 'shield',
                'aide' => 'Ce qui décide de qui entre, et comment.',
            ],
            self::GROUPE_CERTIFICATS => [
                'libelle' => 'Certificats',
                'icone' => 'workspace_premium',
                'aide' => 'Mise en page du PDF délivré à la fin d’un cours.',
            ],
        ];
    }

    /**
     * @return list<ReglageDefini>
     */
    public static function tous(): array
    {
        return array_merge(
            self::general(),
            self::apparence(),
            self::seo(),
            self::economie(),
            self::email(),
            self::paiement(),
            self::analytique(),
            self::api(),
            self::moteurs(),
            self::securite(),
            self::certificats(),
        );
    }

    /**
     * @return list<ReglageDefini>
     */
    public static function duGroupe(string $groupe): array
    {
        return array_values(array_filter(
            self::tous(),
            static fn (ReglageDefini $r): bool => $r->groupe === $groupe,
        ));
    }

    public static function trouver(string $cle): ?ReglageDefini
    {
        foreach (self::tous() as $reglage) {
            if ($reglage->cle === $cle) {
                return $reglage;
            }
        }

        return null;
    }

    // ————————————————————————————————————————————————————— GÉNÉRAL

    /**
     * @return list<ReglageDefini>
     */
    private static function general(): array
    {
        return [
            /*
             | Le mode maintenance. Quatre clés, écrites par `ModeMaintenance` et
             | jamais à la main : elles ne figurent pas à l'écran des réglages,
             | qui n'est pas l'endroit où l'on ferme un site — on y arrive par
             | l'écran dédié, qui annonce ce qui va se passer.
             |
             | Elles sont déclarées ici parce que `Reglages::set()` refuse toute
             | clé hors catalogue, et c'est une garde qu'on ne contourne pas.
             */
            new ReglageDefini(ModeMaintenance::CLE_ACTIVE, 'Mode maintenance actif', TypeDeReglage::Booleen,
                self::GROUPE_GENERAL, defaut: '0',
                aide: 'Piloté depuis l’écran Maintenance, pas ici.'),

            new ReglageDefini(ModeMaintenance::CLE_DEBUT, 'Maintenance ouverte le', TypeDeReglage::Texte,
                self::GROUPE_GENERAL, defaut: ''),

            new ReglageDefini(ModeMaintenance::CLE_FIN, 'Fin annoncée', TypeDeReglage::Texte,
                self::GROUPE_GENERAL, defaut: '',
                aide: 'Une PROMESSE affichée au public : elle ne rouvre rien toute seule.'),

            new ReglageDefini(ModeMaintenance::CLE_MESSAGE, 'Message de maintenance', TypeDeReglage::TexteLong,
                self::GROUPE_GENERAL, defaut: ''),

            new ReglageDefini('site_name', "Nom de l'application", TypeDeReglage::Texte,
                self::GROUPE_GENERAL, defaut: "Vérif'All",
                aide: 'Employé dans les courriels, le manifeste et les titres de page.'),

            new ReglageDefini('site_tagline', 'Slogan', TypeDeReglage::Texte,
                self::GROUPE_GENERAL, defaut: 'La boussole contre la désinformation numérique.'),

            new ReglageDefini('contact_email', 'Adresse de contact publique', TypeDeReglage::Courriel,
                self::GROUPE_GENERAL, exemple: 'contact@verifall.com',
                aide: 'Affichée sur la page Contact et dans les mentions légales.'),

            new ReglageDefini('site_url', 'URL officielle', TypeDeReglage::Url,
                self::GROUPE_GENERAL, exemple: 'https://verifall.com',
                aide: 'Sert aux liens absolus des courriels et du plan de site.'),

            /*
             |------------------------------------------------------------------
             | IDENTITÉ LÉGALE
             |------------------------------------------------------------------
             |
             | Ces six réglages alimentent les mentions légales, la politique de
             | confidentialité et la politique des cookies.
             |
             | POURQUOI ILS SONT ICI plutôt qu'écrits dans les vues. Le prototype
             | les portait en clair sous forme d'espaces réservés — « [Votre
             | Adresse Complète] », « [Nom de votre hébergeur] » — et ces crochets
             | étaient PUBLIÉS tels quels. Des mentions légales à trous ne valent
             | pas mieux que pas de mentions légales : c'est précisément le
             | document qu'on lit quand on veut savoir à qui l'on a affaire.
             |
             | En base plutôt qu'en dur, ils se renseignent depuis le
             | back-office, sans déploiement — ce qui compte le jour où l'on
             | change d'hébergeur.
             */
            /*
             | L'ÂGE MINIMUM POUR OUVRIR UN COMPTE.
             |
             | Il apparaît dans les conditions générales, et c'est une décision
             | de politique — pas de code. 15 ans par défaut : c'est le seuil de
             | consentement numérique retenu par le RGPD, et le plus défendable
             | à défaut de règle béninoise explicite sur les services en ligne.
             |
             | En réglage plutôt qu'en dur pour la même raison que le reste de
             | cette section : le porteur du projet doit pouvoir le changer sans
             | déploiement, et le texte des conditions suit.
             */
            new ReglageDefini('legal_minimum_age', 'Âge minimum pour ouvrir un compte', TypeDeReglage::Nombre,
                self::GROUPE_GENERAL, defaut: '15',
                aide: 'Cité dans les conditions générales. 15 ans est le seuil de consentement numérique du RGPD.'),

            new ReglageDefini('legal_entity', 'Raison sociale', TypeDeReglage::Texte,
                self::GROUPE_GENERAL, defaut: "Vérif'All Lab",
                aide: "L'éditeur de la plateforme, tel qu'il est enregistré."),

            new ReglageDefini('legal_address', 'Adresse du siège', TypeDeReglage::TexteLong,
                self::GROUPE_GENERAL, defaut: 'Bénin', exemple: 'Lot 1234, Quartier…, Cotonou, Bénin',
                aide: 'Obligatoire aux mentions légales (Code du Numérique, art. 448).'),

            new ReglageDefini('legal_director', 'Directeur de la publication', TypeDeReglage::Texte,
                self::GROUPE_GENERAL, defaut: 'René Fadonougbo',
                aide: 'Nom de la personne responsable du contenu publié.'),

            new ReglageDefini('legal_host_name', "Nom de l'hébergeur", TypeDeReglage::Texte,
                self::GROUPE_GENERAL, defaut: 'Hostinger Hosting',
                aide: "L'hébergeur doit être identifiable : c'est une obligation, pas un détail."),

            new ReglageDefini('legal_host_address', "Adresse de l'hébergeur", TypeDeReglage::TexteLong,
                self::GROUPE_GENERAL),

            new ReglageDefini('legal_host_contact', "Contact de l'hébergeur", TypeDeReglage::Texte,
                self::GROUPE_GENERAL, exemple: 'support@hebergeur.com'),

            new ReglageDefini('legal_dpo_email', 'Contact « protection des données »', TypeDeReglage::Courriel,
                self::GROUPE_GENERAL, exemple: 'donnees@verifall.com',
                aide: "L'adresse par laquelle s'exercent les droits d'accès, de rectification et d'effacement. À défaut, l'adresse de contact publique est employée."),

            new ReglageDefini('google_play_url', 'Google Play', TypeDeReglage::Url,
                self::GROUPE_GENERAL, exemple: 'https://play.google.com/store/apps/details?id=…',
                aide: "Laissez vide tant que l'application n'est pas publiée : un bouton qui mène à une page vide coûte plus qu'il ne rapporte."),

            new ReglageDefini('apple_store_url', 'App Store', TypeDeReglage::Url,
                self::GROUPE_GENERAL, exemple: 'https://apps.apple.com/app/…'),

            new ReglageDefini('show_testimonials', "Afficher les témoignages sur l'accueil", TypeDeReglage::Booleen,
                self::GROUPE_GENERAL, defaut: '0'),

            new ReglageDefini('enable_reviews', 'Activer les avis sur le site public', TypeDeReglage::Booleen,
                self::GROUPE_GENERAL, defaut: '0',
                aide: "Tant qu'aucun avis n'existe, les écrans affichent « Nouveau » plutôt qu'une note calculée sur rien."),
        ];
    }

    // ——————————————————————————————————————————————————— APPARENCE

    /**
     * @return list<ReglageDefini>
     */
    private static function apparence(): array
    {
        return [
            new ReglageDefini('site_logo_light', 'Logo clair', TypeDeReglage::Image,
                self::GROUPE_APPARENCE, aide: 'Sur fond clair. PNG ou SVG, 2 Mo maximum.'),

            new ReglageDefini('site_logo_dark', 'Logo sombre', TypeDeReglage::Image,
                self::GROUPE_APPARENCE, aide: 'Sur fond sombre — pied de page, back-office.'),

            new ReglageDefini('site_favicon_32', 'Favicon', TypeDeReglage::Image,
                self::GROUPE_APPARENCE, aide: "32 × 32. L'icône de l'onglet du navigateur."),

            new ReglageDefini('site_favicon_apple', 'Icône Apple', TypeDeReglage::Image,
                self::GROUPE_APPARENCE, aide: '180 × 180. iOS ne gère pas la transparence : prévoyez un fond.'),

            new ReglageDefini('site_avatar', 'Avatar par défaut', TypeDeReglage::Image,
                self::GROUPE_APPARENCE, aide: "Affiché tant que la personne n'a pas déposé de portrait."),

            new ReglageDefini('brand_color', 'Couleur de marque', TypeDeReglage::Texte,
                self::GROUPE_APPARENCE, defaut: '#09B850', exemple: '#09B850',
                reglesSupplementaires: ['regex:/^#[0-9A-Fa-f]{6}$/'],
                aide: "Code hexadécimal à six chiffres. La feuille de style du prototype étant figée, cette valeur ne s'applique qu'aux éléments qui la lisent explicitement."),
        ];
    }

    // ————————————————————————————————————————————————————————— SEO

    /**
     * @return list<ReglageDefini>
     */
    private static function seo(): array
    {
        return [
            new ReglageDefini('site_title', 'Titre SEO', TypeDeReglage::Texte,
                self::GROUPE_SEO, reglesSupplementaires: ['max:60'],
                aide: 'Au-delà de 60 caractères, Google coupe. Autant couper soi-même.'),

            new ReglageDefini('site_description', 'Description SEO', TypeDeReglage::TexteLong,
                self::GROUPE_SEO, reglesSupplementaires: ['max:160'],
                aide: "160 caractères : c'est ce qu'un résultat de recherche affiche."),

            new ReglageDefini('site_keywords', 'Mots-clés', TypeDeReglage::Texte,
                self::GROUPE_SEO, exemple: 'fact-checking, Bénin, désinformation',
                aide: 'Ignorés par Google depuis longtemps ; conservés pour les moteurs qui les lisent encore.'),

            new ReglageDefini('og_image', 'Image de partage (OpenGraph)', TypeDeReglage::Image,
                self::GROUPE_SEO,
                aide: "1200 × 630. C'est elle qu'affichent WhatsApp et Facebook quand on partage un lien."),

            new ReglageDefini('facebook_url', 'Facebook', TypeDeReglage::Url, self::GROUPE_SEO),
            new ReglageDefini('instagram_url', 'Instagram', TypeDeReglage::Url, self::GROUPE_SEO),
            new ReglageDefini('linkedin_url', 'LinkedIn', TypeDeReglage::Url, self::GROUPE_SEO),
            new ReglageDefini('whatsapp_group_url', 'Groupe WhatsApp', TypeDeReglage::Url, self::GROUPE_SEO),
        ];
    }

    // ———————————————————————————————————————————————————— ÉCONOMIE

    /**
     * @return list<ReglageDefini>
     */
    private static function economie(): array
    {
        return [
            new ReglageDefini('default_user_units', 'Bonus de bienvenue', TypeDeReglage::Nombre,
                self::GROUPE_ECONOMIE, defaut: '5',
                aide: "Unités créditées à l'inscription, une seule fois."),

            new ReglageDefini('default_monthly_units', 'Recharge mensuelle', TypeDeReglage::Nombre,
                self::GROUPE_ECONOMIE, defaut: '10',
                aide: 'Attribuée le 1ᵉʳ de chaque mois par une tâche planifiée. Si le cron ne tourne pas, personne ne la reçoit.'),

            new ReglageDefini('xp_per_verified_signal', 'XP par signalement vérifié', TypeDeReglage::Nombre,
                self::GROUPE_ECONOMIE, defaut: '20',
                aide: "Accordée quand l'équipe confirme le signalement, pas à l'envoi."),

            /*
             | LE BARÈME DU PARRAINAGE.
             |
             | Il vivait dans deux CONSTANTES DE CLASSE — `Parrainage::POINTS_PARRAIN`
             | et `POINTS_FILLEUL`, à 100 et 50 — donc modifiables par un
             | développeur et par lui seul, pour une décision commerciale qui
             | appartient au porteur du projet. Même arbitrage que la grille
             | tarifaire des packs d'unités.
             |
             | Les deux montants sont séparés, et ce n'est pas de la
             | granularité gratuite : ils répondent à deux questions
             | différentes. Le parrain se rémunère pour un effort déjà fourni ;
             | le filleul reçoit une avance sur un usage qu'il n'a pas encore.
             | Un curseur unique forcerait à bouger l'un pour régler l'autre.
             |
             | La récompense est versée à la PREMIÈRE VÉRIFICATION du filleul,
             | jamais à l'inscription : payer l'inscription seule, c'est payer
             | les faux comptes.
             */
            new ReglageDefini('referral_referrer_units', 'Parrainage — unités au parrain', TypeDeReglage::Nombre,
                self::GROUPE_ECONOMIE, defaut: '10',
                aide: 'Créditées quand le filleul réalise sa première vérification, pas à son inscription.'),

            new ReglageDefini('referral_referred_units', 'Parrainage — unités au filleul', TypeDeReglage::Nombre,
                self::GROUPE_ECONOMIE, defaut: '5',
                aide: 'Versées au même moment. Les parrainages déjà récompensés gardent le montant reçu.'),
        ];
    }

    // ——————————————————————————————————————————————————— E-MAIL

    /**
     * @return list<ReglageDefini>
     */
    private static function email(): array
    {
        return [
            new ReglageDefini('mail_host', 'Serveur SMTP', TypeDeReglage::Texte,
                self::GROUPE_EMAIL, exemple: 'smtp.titan.email'),

            new ReglageDefini('mail_port', 'Port', TypeDeReglage::Nombre,
                self::GROUPE_EMAIL, defaut: '465', reglesSupplementaires: ['max:65535'],
                aide: '465 pour SSL, 587 pour TLS.'),

            new ReglageDefini('mail_username', 'Utilisateur SMTP', TypeDeReglage::Texte, self::GROUPE_EMAIL),

            new ReglageDefini('mail_password', 'Mot de passe SMTP', TypeDeReglage::MotDePasse,
                self::GROUPE_EMAIL, secret: true,
                aide: 'Chiffré en base, jamais réaffiché. Laissez vide pour conserver la valeur actuelle.'),

            new ReglageDefini('mail_from_address', "Adresse de l'expéditeur", TypeDeReglage::Courriel,
                self::GROUPE_EMAIL, exemple: 'no-reply@verifall.com',
                aide: 'Doit appartenir à un domaine que vous contrôlez, sinon les courriels partent en indésirable.'),

            new ReglageDefini('mail_from_name', "Nom de l'expéditeur", TypeDeReglage::Texte,
                self::GROUPE_EMAIL, defaut: "Vérif'All"),

            new ReglageDefini('mail_encryption', 'Chiffrement', TypeDeReglage::Choix,
                self::GROUPE_EMAIL, defaut: 'ssl', choix: [
                    ['valeur' => 'ssl', 'libelle' => 'SSL'],
                    ['valeur' => 'tls', 'libelle' => 'TLS'],
                    ['valeur' => '', 'libelle' => 'Aucun (déconseillé)'],
                ],
                aide: 'Sans chiffrement, le mot de passe SMTP circule en clair sur le réseau.'),
        ];
    }

    // —————————————————————————————————————————————————— PAIEMENT

    /**
     * @return list<ReglageDefini>
     */
    private static function paiement(): array
    {
        return [
            new ReglageDefini('stripe_public_key', 'Clé publique Stripe', TypeDeReglage::Texte,
                self::GROUPE_PAIEMENT, exemple: 'pk_live_…',
                aide: 'Publique par nature : elle est visible dans le navigateur.'),

            new ReglageDefini('stripe_secret_key', 'Clé secrète Stripe', TypeDeReglage::MotDePasse,
                self::GROUPE_PAIEMENT, secret: true, exemple: 'sk_live_…',
                aide: 'Elle autorise des prélèvements. Chiffrée, jamais réaffichée.'),

            new ReglageDefini('stripe_webhook_secret', 'Secret de webhook Stripe', TypeDeReglage::MotDePasse,
                self::GROUPE_PAIEMENT, secret: true,
                aide: "C'est lui qui prouve qu'un rappel de paiement vient bien de Stripe. Sans lui, n'importe qui peut déclarer un paiement reçu."),

            new ReglageDefini('paypal_client_id', 'Identifiant client PayPal', TypeDeReglage::Texte,
                self::GROUPE_PAIEMENT),

            new ReglageDefini('paypal_secret', 'Secret PayPal', TypeDeReglage::MotDePasse,
                self::GROUPE_PAIEMENT, secret: true),
        ];
    }

    // ————————————————————————————————————————————————— ANALYTIQUE

    /**
     * @return list<ReglageDefini>
     */
    private static function analytique(): array
    {
        return [
            new ReglageDefini('analytics_consent_required', 'Exiger le consentement avant toute mesure', TypeDeReglage::Booleen,
                self::GROUPE_ANALYTIQUE, defaut: '1',
                aide: "À laisser activé. Charger un traceur avant le consentement est précisément ce que le RGPD interdit — et l'amende porte sur le chargement, pas sur l'usage."),

            new ReglageDefini('ga4_measurement_id', 'Google Analytics 4', TypeDeReglage::Texte,
                self::GROUPE_ANALYTIQUE, exemple: 'G-XXXXXXXXXX',
                reglesSupplementaires: ['regex:/^(G-[A-Z0-9]{6,12})?$/']),

            new ReglageDefini('search_console_token', 'Vérification Search Console', TypeDeReglage::Texte,
                self::GROUPE_ANALYTIQUE,
                aide: 'Le contenu de la balise `google-site-verification`, sans les guillemets.'),

            new ReglageDefini('meta_pixel_id', 'Pixel Meta', TypeDeReglage::Texte,
                self::GROUPE_ANALYTIQUE, reglesSupplementaires: ['regex:/^[0-9]{10,20}$|^$/'],
                aide: 'Un pixel publicitaire suit les gens hors du site. Le consentement ci-dessus le conditionne.'),
        ];
    }

    // ——————————————————————————————————————————————— API EXTERNES

    /**
     * @return list<ReglageDefini>
     */
    private static function api(): array
    {
        return [
            new ReglageDefini('gemini_api_key', 'Google Gemini (analyse sémantique)', TypeDeReglage::MotDePasse,
                self::GROUPE_API, secret: true,
                aide: 'Clé vide = moteur désactivé. Les autres continuent de tourner.'),

            new ReglageDefini('google_vision_api_key', 'Google Vision (OCR)', TypeDeReglage::MotDePasse,
                self::GROUPE_API, secret: true,
                aide: "Lit le texte contenu dans une image — l'essentiel des captures partagées sur WhatsApp."),

            new ReglageDefini('google_safe_browsing_key', 'Google Safe Browsing', TypeDeReglage::MotDePasse,
                self::GROUPE_API, secret: true,
                aide: 'Signale les liens de hameçonnage et de logiciels malveillants.'),

            new ReglageDefini('google_factcheck_key', 'Google Fact Check Tools', TypeDeReglage::MotDePasse,
                self::GROUPE_API, secret: true,
                aide: "Interroge les fact-checks déjà publiés ailleurs. C'est le moteur qui évite de refaire un travail fait."),

            new ReglageDefini('gptzero_api_key', 'GPTZero (détection de texte IA)', TypeDeReglage::MotDePasse,
                self::GROUPE_API, secret: true,
                aide: 'Retiré de la v1 : la fiabilité est faible et les faux positifs frappent les locuteurs non natifs. Voir docs/ALTERNATIVES_API_VERIFICATION.md.'),

            new ReglageDefini('tineye_api_key', 'TinEye (antériorité d’image)', TypeDeReglage::MotDePasse,
                self::GROUPE_API, secret: true,
                aide: "200 $/mois pour 5 000 recherches. Remplacé par la recherche d'image Google, qui ne donne pas la date de première apparition."),

            /*
            |------------------------------------------------------------------
            | TIPLINE WHATSAPP
            |------------------------------------------------------------------
            |
            | Au Bénin, la désinformation ne circule pas sur le web : elle
            | circule sur WhatsApp, en images, en captures d'écran et en
            | messages vocaux transférés. Une tipline transforme la vérification
            | d'une démarche — aller sur un site — en un réflexe : transférer.
            | C'est un changement de nature, pas de degré.
            |
            | LE MODULE RESTE ÉTEINT tant que ces trois valeurs ne sont pas
            | renseignées, et c'est délibéré. Les obtenir suppose un compte Meta
            | Business, un numéro vérifié et un rappel déclaré chez Meta : trois
            | démarches administratives qu'aucun code ne remplace. Un écran qui
            | prétendrait fonctionner sans elles ferait croire à une panne là où
            | il n'y a qu'un dossier à déposer.
            */
            new ReglageDefini('whatsapp_phone_number_id', 'Identifiant du numéro WhatsApp', TypeDeReglage::Texte,
                self::GROUPE_API,
                aide: "Fourni par Meta après vérification du numéro. Tant qu'il est vide, la tipline reste désactivée."),

            new ReglageDefini('whatsapp_token', 'Jeton WhatsApp Business', TypeDeReglage::MotDePasse,
                self::GROUPE_API, secret: true,
                aide: 'Jeton permanent du compte Meta Business. Il autorise à répondre aux messages reçus.'),

            new ReglageDefini('whatsapp_verify_token', 'Jeton de vérification du rappel', TypeDeReglage::MotDePasse,
                self::GROUPE_API, secret: true,
                aide: "Chaîne de votre choix, recopiée chez Meta. Elle prouve que le rappel s'adresse bien à cette plateforme."),
        ];
    }

    // ——————————————————————————————————————————————————— SÉCURITÉ

    // ————————————————————————————————————————————————— MOTEURS

    /**
     * Un interrupteur par moteur, ENGENDRÉ depuis l'enum.
     *
     * ## Pourquoi engendré et non écrit à la main
     *
     * Douze lignes recopiées, c'est douze occasions d'oublier un moteur le jour
     * où l'enum en gagne un treizième — et un moteur sans interrupteur serait
     * un moteur qu'on ne peut plus éteindre, découvert le jour où il coûte.
     *
     * ## Éteint n'est pas « non configuré »
     *
     * Un moteur éteint N'EST PAS APPELÉ, même avec une clé valide : c'est une
     * décision d'exploitation, prise le 1er septembre 2026 par le porteur du
     * projet — TinEye est payant et le budget ne le permet pas encore, Vision
     * dépend d'une clé Google refusée, aucun fournisseur de transcription n'est
     * souscrit. L'interrupteur existe pour que le retour soit une case à cocher
     * et non un déploiement.
     *
     * @return list<ReglageDefini>
     */
    private static function moteurs(): array
    {
        return array_map(
            static fn (MoteurAnalyse $moteur): ReglageDefini => new ReglageDefini(
                'moteur_actif_'.$moteur->value,
                $moteur->libelle(),
                TypeDeReglage::Booleen,
                self::GROUPE_MOTEURS,
                defaut: $moteur->allumeParDefaut() ? '1' : '0',
                aide: $moteur->raisonDeLEtatParDefaut(),
            ),
            MoteurAnalyse::cases(),
        );
    }

    /**
     * @return list<ReglageDefini>
     */
    private static function securite(): array
    {
        return [
            new ReglageDefini('coming_soon_mode', 'Mode « bientôt disponible »', TypeDeReglage::Booleen,
                self::GROUPE_SECURITE, defaut: '0',
                aide: 'Ferme le site public aux visiteurs. Le back-office et les comptes connectés restent accessibles.'),

            new ReglageDefini('signup_stepper', 'Inscription en plusieurs étapes', TypeDeReglage::Booleen,
                self::GROUPE_SECURITE, defaut: '1',
                aide: 'Découpe le formulaire. Un formulaire long en une seule page fait abandonner davantage.'),

            new ReglageDefini('force_welcome_quiz', "Imposer le quiz d'accueil", TypeDeReglage::Booleen,
                self::GROUPE_SECURITE, defaut: '0'),

            /*
             | La rubrique « Portails officiels » du Hub.
             |
             | FERMÉE PAR DÉFAUT, à la demande du porteur du projet : le CRUD
             | d'administration existe, la rubrique citoyenne attend. L'entrée
             | figure dans le menu du compte, DÉSACTIVÉE, et cette case l'ouvre.
             |
             | Une bascule et non un déploiement : le jour où le contenu est
             | prêt, quelqu'un coche. Et tant qu'elle est fermée, la route
             | citoyenne rend 404 — un lien désactivé qui répondrait quand même
             | ne serait désactivé que pour la forme.
             */
            new ReglageDefini('hub_portals_actif', 'Ouvrir la rubrique « Portails officiels »', TypeDeReglage::Booleen,
                self::GROUPE_SECURITE, defaut: '0',
                aide: "Tant qu'elle est fermée, l'entrée reste visible mais désactivée dans le menu du compte, et l'adresse répond 404."),

            new ReglageDefini('onboarding_quiz_question', "Question du quiz d'accueil", TypeDeReglage::TexteLong,
                self::GROUPE_SECURITE,
                aide: "Sans question renseignée, le quiz ne s'affiche pas même s'il est imposé."),
        ];
    }

    // ————————————————————————————————————————————————— CERTIFICATS

    /**
     * @return list<ReglageDefini>
     */
    private static function certificats(): array
    {
        /*
         |----------------------------------------------------------------------
         | TROIS RÉGLAGES DE COORDONNÉES ONT ÉTÉ RETIRÉS ICI (lot 4)
         |----------------------------------------------------------------------
         |
         | `cert_name_coords`, `cert_date_coords`, `cert_qr_coords` décrivaient
         | des positions en points — « x:100,y:200 » — sur un fond A4. C'est le
         | modèle d'une composition PDF : on pose un gabarit, puis on écrit
         | dessus à des coordonnées absolues.
         |
         | Ce modèle n'existe pas et n'est pas prévu. **Aucune bibliothèque PDF
         | n'est installée** — c'est d'ailleurs la raison pour laquelle
         | `certificates/{code}/pdf` n'est pas servi par l'API. Le certificat est
         | une PAGE HTML que le navigateur imprime : elle se recompose selon la
         | largeur, et des coordonnées en points n'y veulent rien dire.
         |
         | Ces trois clés étaient donc enregistrables et lues par personne —
         | exactement l'écart É-02 relevé au lot 0. Les laisser en place aurait
         | été pire que de ne rien faire : un porteur de projet règle une
         | position, l'écran confirme, et rien ne bouge jamais.
         |
         | Elles sont remplacées par ce qui s'applique RÉELLEMENT à un document
         | HTML imprimable. Le jour où une composition PDF est décidée, elles
         | reviendront avec la bibliothèque qui les exécute.
         |
         | Les lignes déjà enregistrées en base pour ces clés deviennent
         | inertes : le formulaire est engendré depuis ce catalogue, il ne les
         | affiche plus, et `Reglages::get()` ne les consulte plus.
         */
        return [
            new ReglageDefini('cert_signature', 'Signature numérisée', TypeDeReglage::Image,
                self::GROUPE_CERTIFICATS,
                aide: 'PNG à fond transparent, environ 400 × 150 px. Sans elle, seul le nom est imprimé.'),

            new ReglageDefini('cert_signature_name', 'Nom du signataire', TypeDeReglage::Texte,
                self::GROUPE_CERTIFICATS, defaut: 'René Fadonougbo',
                aide: 'Imprimé sous la signature. Un diplôme non signé ne vaut rien.'),

            new ReglageDefini('cert_signature_role', 'Qualité du signataire', TypeDeReglage::Texte,
                self::GROUPE_CERTIFICATS, defaut: 'Directeur de la publication',
                aide: 'La fonction qui engage la plateforme.'),

            new ReglageDefini('cert_background', 'Filigrane de fond', TypeDeReglage::Image,
                self::GROUPE_CERTIFICATS,
                aide: 'Facultatif. Posé en très faible opacité derrière le texte, il ne doit jamais gêner la lecture.'),

            /*
             | La couleur des vagues. Un seul réglage pour tout le dégradé : deux
             | curseurs laisseraient composer des paires illisibles, et la
             | seconde teinte se déduit de la première par une rotation de
             | teinte, dans la feuille de style.
             */
            new ReglageDefini('cert_accent_color', 'Couleur du décor', TypeDeReglage::Texte,
                self::GROUPE_CERTIFICATS, defaut: '#065F46', exemple: '#065F46',
                reglesSupplementaires: ['regex:/^#[0-9A-Fa-f]{6}$/'],
                aide: 'Hexadécimal à six chiffres. Teinte des vagues dégradées, en tête et en pied du document.'),

            new ReglageDefini('cert_mention', 'Mention de bas de page', TypeDeReglage::TexteLong,
                self::GROUPE_CERTIFICATS,
                defaut: "Ce certificat atteste d'un parcours de formation. Son authenticité se vérifie à tout moment sur verifall.com.",
                aide: "Imprimée sous le code. C'est elle que lit un recruteur qui ne connaît pas Vérif'All."),
        ];
    }
}
