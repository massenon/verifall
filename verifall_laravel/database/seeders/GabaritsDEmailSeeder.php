<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Marketing\Emails\CatalogueDesCourriels;
use App\Core\Marketing\Emails\RenduDeGabarit;
use App\Models\EmailTemplate;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Le texte par défaut de chaque courriel.
 *
 * ## Idempotent, et il le doit
 *
 * `firstOrNew` puis écriture des seuls champs VIDES : rejouer ce semis sur une
 * base de production ne doit pas effacer ce qu'un rédacteur a corrigé. C'est la
 * différence entre un semis qu'on peut lancer et un semis qu'on n'ose plus
 * toucher.
 *
 * `variables` fait exception : elle est TOUJOURS réécrite. Elle décrit ce que
 * le code fournit, pas ce que quelqu'un a choisi — la laisser diverger ferait
 * afficher à l'écran des marqueurs qui n'existent plus.
 *
 * ## Sur le ton
 *
 * Deux règles tenues partout : on tutoie personne, et on ne promet rien qu'on
 * ne tienne. Pas de « votre sécurité est notre priorité » — la phrase ne dit
 * rien et occupe la place où devrait figurer ce qu'il faut faire.
 */
final class GabaritsDEmailSeeder extends Seeder
{
    /**
     * Objet, titre, corps et bouton, pour chaque clé du catalogue.
     *
     * @var array<string, array{sujet: string, titre: string, corps: string, bouton?: string}>
     */
    private const TEXTES = [

        'otp' => [
            'sujet' => 'Votre code de vérification',
            'titre' => 'Votre code à usage unique',
            // Le code est répété en gros ET la durée annoncée : sans elle, on
            // ressaisit un code périmé sans comprendre pourquoi il est refusé.
            'corps' => "Bonjour {{ prenom }},\n\nVoici votre code de vérification :\n\n## {{ code }}\n\nIl est valable **{{ minutes }} minutes**. Passé ce délai, demandez-en un nouveau depuis l'écran où vous étiez.\n\nSi vous n'êtes pas à l'origine de cette demande, ignorez ce message : sans le code, rien ne se passe.",
        ],

        'bienvenue_compte' => [
            'sujet' => 'Bienvenue sur Vérif’All',
            'titre' => 'Votre compte est prêt',
            'corps' => "Bonjour {{ prenom }},\n\nVotre compte est actif. Vous pouvez dès maintenant soumettre un contenu douteux à la vérification, suivre les cours de l'Académie et participer au forum.\n\n**{{ unites_offertes }} unités** vous ont été offertes pour commencer : chaque vérification en consomme une.\n\nUn conseil pour la première fois : envoyez-nous la rumeur qui vous a fait créer ce compte. C'est souvent comme cela que commence une vérification utile.",
            'bouton' => 'Ouvrir mon tableau de bord',
        ],

        'mot_de_passe_change' => [
            'sujet' => 'Votre mot de passe a été modifié',
            'titre' => 'Mot de passe modifié',
            // Ce message n'a qu'un travail : permettre à quelqu'un dont le
            // compte vient d'être pris de s'en apercevoir. Tout le reste est
            // du bruit qui retarde cette phrase.
            'corps' => "Bonjour {{ prenom }},\n\nLe mot de passe de votre compte a été modifié le {{ quand }}. Vos autres sessions ont été fermées.\n\n**Si ce n'est pas vous**, votre compte est compromis : réinitialisez immédiatement votre mot de passe et écrivez-nous.",
        ],

        'export_pret' => [
            'sujet' => 'Votre export de données est prêt',
            'titre' => 'Votre archive est prête',
            'corps' => "Bonjour {{ prenom }},\n\nL'export de vos données personnelles est prêt au téléchargement.\n\nLe lien expire le **{{ expire_le }}**. Passé cette date, l'archive est effacée de nos serveurs et il faut en redemander une — c'est une mesure de protection, pas une contrainte administrative : un lien de téléchargement qui vit éternellement finit par circuler.",
            'bouton' => 'Télécharger mon archive',
        ],

        'suppression_programmee' => [
            'sujet' => 'Suppression de votre compte programmée',
            'titre' => 'Votre compte sera supprimé le {{ date_effacement }}',
            'corps' => "Bonjour {{ prenom }},\n\nVotre demande de suppression est enregistrée. Le compte et les données qui s'y rattachent seront effacés le **{{ date_effacement }}**.\n\nD'ici là, tout est réversible : il suffit de vous reconnecter, ou de suivre le lien ci-dessous.\n\nAprès cette date, l'effacement est définitif et nous ne pourrons rien restaurer.",
            'bouton' => 'Annuler la suppression',
        ],

        'compte_suspendu' => [
            'sujet' => 'Votre compte a été suspendu',
            'titre' => 'Compte suspendu',
            'corps' => "Bonjour {{ prenom }},\n\nVotre compte est suspendu pour le motif suivant :\n\n> {{ motif }}\n\nVous ne pouvez plus publier ni soumettre de vérification. Vos données restent intactes.\n\nSi vous estimez que cette décision est une erreur, répondez-nous : une suspension se conteste, et nous relisons.",
            'bouton' => 'Nous écrire',
        ],

        'compte_retabli' => [
            'sujet' => 'Votre compte est rétabli',
            'titre' => 'Compte rétabli',
            'corps' => "Bonjour {{ prenom }},\n\nLa suspension de votre compte est levée. Vous retrouvez l'ensemble de vos accès.",
            'bouton' => 'Revenir sur Vérif’All',
        ],

        'kyc_approuve' => [
            'sujet' => 'Votre identité est vérifiée',
            'titre' => 'Identité vérifiée',
            'corps' => "Bonjour {{ prenom }},\n\nVotre dossier a été approuvé : la mention « Identité vérifiée » figure désormais sur votre profil.\n\nLes pièces que vous aviez déposées ont été effacées — nous ne les conservons pas au-delà de la décision.",
            'bouton' => 'Voir mon profil',
        ],

        'kyc_refuse' => [
            'sujet' => 'Votre vérification d’identité n’a pas abouti',
            'titre' => 'Dossier non retenu',
            // Le motif est en tête, pas en bas : un refus sans raison lisible
            // se transforme en message au support, et en défiance.
            'corps' => "Bonjour {{ prenom }},\n\nVotre dossier n'a pas pu être validé, pour la raison suivante :\n\n> {{ motif }}\n\nVous pouvez déposer un nouveau dossier dès maintenant. Les pièces précédentes ont été effacées.",
            'bouton' => 'Déposer un nouveau dossier',
        ],

        'analyse_terminee' => [
            'sujet' => 'Votre vérification est terminée',
            'titre' => '{{ verdict }} — {{ sujet }}',
            'corps' => "Bonjour {{ prenom }},\n\nL'analyse que vous aviez demandée est terminée. Le verdict est **{{ verdict }}**.\n\nLe rapport complet détaille les sources consultées et le raisonnement suivi. Il est fait pour être partagé : c'est en circulant qu'un démenti sert à quelque chose.",
            'bouton' => 'Lire le rapport',
        ],

        'alerte_intox' => [
            'sujet' => 'Alerte : {{ titre }}',
            'titre' => '{{ titre }}',
            'corps' => "Bonjour {{ prenom }},\n\nUne intox circule en ce moment et l'équipe vient de publier un démenti.\n\n{{ resume }}\n\nSi vous l'avez reçue, le lien ci-dessous est ce qu'il faut renvoyer à la personne qui vous l'a transmise.",
            'bouton' => 'Lire le démenti',
        ],

        'verification_assignee' => [
            'sujet' => 'Une vérification vous est assignée',
            'titre' => 'Vérification à prendre en charge',
            'corps' => "Bonjour {{ prenom }},\n\nUne vérification vous a été assignée :\n\n> {{ sujet }}\n\nElle apparaît dans votre file de travail.",
            'bouton' => 'Ouvrir la vérification',
        ],

        'contre_expertise_tranchee' => [
            'sujet' => 'Réponse à votre demande de contre-expertise',
            'titre' => 'Votre demande a été examinée',
            'corps' => "Bonjour {{ prenom }},\n\nVotre demande de contre-expertise sur « {{ sujet }} » a été examinée.\n\n**Décision : {{ decision }}**\n\nLe rapport a été mis à jour en conséquence, et l'historique des modifications y figure.",
            'bouton' => 'Relire le rapport',
        ],

        'cours_termine' => [
            'sujet' => 'Vous avez terminé « {{ cours }} »',
            'titre' => 'Cours terminé',
            'corps' => "Bonjour {{ prenom }},\n\nVous venez de terminer **{{ cours }}**. {{ xp }} points d'expérience ont été crédités sur votre compte.\n\nLe savoir-faire ne se garde pas : la meilleure suite est de vérifier une information qui circule autour de vous.",
            'bouton' => 'Voir mon certificat',
        ],

        'relance_de_cours' => [
            'sujet' => 'Vous étiez à {{ progression }} % de « {{ cours }} »',
            'titre' => 'Il vous reste peu de chemin',
            'corps' => "Bonjour {{ prenom }},\n\nVous aviez commencé **{{ cours }}** et vous en étiez à {{ progression }} %.\n\nLes leçons sont courtes ; celle qui vous attend prend quelques minutes.",
            'bouton' => 'Reprendre le cours',
        ],

        'nouveau_cours' => [
            'sujet' => 'Nouveau cours : {{ cours }}',
            'titre' => '{{ cours }}',
            'corps' => "Bonjour {{ prenom }},\n\nUn nouveau cours vient d'être publié à l'Académie : **{{ cours }}** ({{ niveau }}).",
            'bouton' => 'Découvrir le cours',
        ],

        'badge_debloque' => [
            'sujet' => 'Badge débloqué : {{ badge }}',
            'titre' => '{{ badge }}',
            'corps' => "Bonjour {{ prenom }},\n\nVous venez de débloquer le badge **{{ badge }}**, et {{ xp }} points d'expérience avec lui.",
            'bouton' => 'Voir mes récompenses',
        ],

        'unites_creditees' => [
            'sujet' => '{{ unites }} unités créditées sur votre compte',
            'titre' => 'Unités créditées',
            'corps' => "Bonjour {{ prenom }},\n\n**{{ unites }} unités** viennent d'être créditées sur votre compte ({{ motif }}).\n\nVotre solde est désormais de {{ solde }} unités. Chaque vérification en consomme une.",
            'bouton' => 'Voir mon solde',
        ],

        'mention_forum' => [
            'sujet' => '{{ auteur }} vous a cité dans le forum',
            'titre' => 'Vous êtes cité',
            'corps' => "Bonjour {{ prenom }},\n\n{{ auteur }} vous a cité dans la discussion « {{ sujet }} ».",
            'bouton' => 'Lire le message',
        ],

        'reaction_recue' => [
            'sujet' => 'Une réaction sur votre message',
            'titre' => 'Votre message a fait réagir',
            'corps' => "Bonjour {{ prenom }},\n\n{{ auteur }} a réagi à votre message dans « {{ sujet }} ».",
            'bouton' => 'Voir la discussion',
        ],

        'confirmation_abonnement' => [
            'sujet' => 'Confirmez votre abonnement',
            'titre' => 'Une dernière étape',
            // Le double opt-in est expliqué en une phrase : sans elle, ce
            // message ressemble à un obstacle administratif.
            'corps' => "Bonjour {{ prenom }},\n\nConfirmez votre adresse pour recevoir l'infolettre. Nous demandons cette confirmation pour être certains que personne n'a inscrit votre adresse à votre place.\n\nSi vous n'avez rien demandé, ignorez ce message : sans confirmation, nous ne vous écrirons pas.",
            'bouton' => 'Confirmer mon abonnement',
        ],

        'recapitulatif_hebdomadaire' => [
            'sujet' => 'Les intox de la semaine',
            'titre' => 'Ce que l’équipe a vérifié {{ periode }}',
            'corps' => "Bonjour {{ prenom }},\n\nVoici les vérifications publiées {{ periode }}. Chaque titre renvoie au fact-check complet, avec ses sources.",
            'bouton' => 'Voir tous les fact-checks',
        ],

        'diffusion_infolettre' => [
            'sujet' => 'Vérif’All',
            'titre' => 'Vérif’All',
            // Le corps est écrit à l'envoi depuis l'administration : celui-ci
            // n'est qu'un repli, jamais affiché en pratique.
            'corps' => 'Bonjour {{ prenom }},',
        ],

        'maintenance_terminee' => [
            'sujet' => 'Vérif’All est de nouveau en ligne',
            'titre' => 'C’est reparti',
            /*
             | Court, et sans excuse appuyée. La personne a demandé UN message :
             | le lui envoyer accompagné d'un paragraphe d'excuses transformerait
             | un service rendu en corvée de lecture.
             |
             | La durée est dite parce qu'elle est la seule information qu'on ait
             | à ajouter : elle permet de juger si l'interruption a été tenue.
             */
            'corps' => "Bonjour {{ prenom }},\n\nLe service est rétabli. L'interruption aura duré {{ duree }}.\n\nVous receviez ce message parce que vous l'aviez demandé sur la page de maintenance. C'était le seul : votre adresse ne rejoint aucune liste.",
            'bouton' => 'Retourner sur Vérif’All',
        ],

        'message_contact' => [
            'sujet' => 'Nous avons bien reçu votre message',
            'titre' => 'Message reçu',
            'corps' => "Bonjour {{ prenom }},\n\nNous avons bien reçu votre message au sujet de « {{ sujet }} ».\n\nUne personne de l'équipe vous répondra. Nous ne promettons pas de délai — nous préférons ne rien annoncer plutôt qu'annoncer et tenir mal.",
        ],
    ];

    /**
     * Le texte d'origine d'une clé, ou `null` si elle n'en a pas.
     *
     * Exposé pour l'écran « Réinitialiser ». Rejouer le semis entier depuis un
     * composant marcherait aussi, mais ferait 24 écritures pour en corriger une
     * — et toucherait des lignes que personne n'a demandé de rétablir.
     *
     * @return array{sujet: string, titre: string, corps: string, bouton?: string}|null
     */
    public static function texteParDefaut(string $cle): ?array
    {
        return self::TEXTES[$cle] ?? null;
    }

    public function run(): void
    {
        /*
         | Indexation DIRECTE, sans garde. Le catalogue et ce tableau doivent se
         | répondre exactement, et c'est un TEST qui le tient — pas un `continue`
         | silencieux qui laisserait un courriel sans texte sans que personne ne
         | s'en aperçoive. L'analyse statique le confirme d'ailleurs : la garde
         | était provablement morte.
         */
        foreach (CatalogueDesCourriels::EVENEMENTS as $cle => $evenement) {
            $textes = self::TEXTES[$cle];

            $gabarit = EmailTemplate::query()->firstOrNew(['key' => $cle]);

            if (! $gabarit->exists) {
                $gabarit->uuid = (string) Str::uuid();
                $gabarit->is_active = $evenement['parDefautActif'];
            }

            // Seuls les champs VIDES sont remplis : rejouer le semis ne doit pas
            // effacer ce qu'un rédacteur a corrigé.
            $gabarit->subject = $gabarit->subject ?: $textes['sujet'];
            $gabarit->heading = $gabarit->heading ?: $textes['titre'];
            $gabarit->body = $gabarit->body ?: $textes['corps'];
            $gabarit->cta_label = $gabarit->cta_label ?: ($textes['bouton'] ?? null);

            // Exception : `variables` décrit ce que le CODE fournit, pas ce que
            // quelqu'un a choisi. La laisser diverger ferait afficher à l'écran
            // des marqueurs qui n'existent plus.
            $gabarit->variables = $evenement['variables'];

            $gabarit->save();
        }

        // Sans cet oubli, les gabarits fraîchement semés resteraient invisibles
        // pendant trente minutes — le genre de défaut qu'on met une journée à croire.
        app(RenduDeGabarit::class)->oublier();
    }
}
