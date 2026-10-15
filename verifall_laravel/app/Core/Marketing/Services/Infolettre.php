<?php

declare(strict_types=1);

namespace App\Core\Marketing\Services;

use App\Core\Application\Contracts\DepotAbonnes;
use App\Core\Application\Reglages\Reglages;
use App\Core\Marketing\Enums\StatutCampagne;
use App\Mail\ConfirmationAbonnement;
use App\Models\Campaign;
use App\Models\HubArticle;
use App\Models\NewsletterSubscriber;
use App\Models\User;
use App\Support\Hashing\IdentityHasher;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

/**
 * L'infolettre du lundi — abonnement, consentement, et matière du récapitulatif.
 *
 * ## Ce que ce service protège
 *
 * **1. Une réponse identique, abonné ou non.** `abonner()` ne rend RIEN qui
 * permette de distinguer une adresse nouvelle d'une adresse déjà inscrite. Un
 * formulaire qui répondrait « déjà abonné » serait un annuaire : on y teste une
 * adresse, on sait si la personne lit Vérif'All. C'est le même défaut qu'un
 * formulaire de connexion qui dit « ce compte n'existe pas ».
 *
 * **2. Le double opt-in, sans exception.** Une adresse saisie ne prouve pas son
 * propriétaire. Tant que le clic de confirmation n'est pas venu, rien ne part
 * vers elle — c'est ce qui empêche d'abonner son voisin, et ce qui garde la
 * réputation d'envoi du domaine.
 *
 * **3. Le désabonnement sans mot de passe.** Le lien signé vit dans chaque
 * courriel. Quelqu'un qui ne retrouve pas comment sortir ne cherche pas
 * longtemps : il marque l'expéditeur comme indésirable, et c'est la
 * délivrabilité de TOUT le domaine qui tombe — y compris celle des codes de
 * vérification, sans lesquels plus personne ne s'inscrit.
 */
final class Infolettre
{
    /** Audience de la table `campaigns` visée par le récapitulatif. */
    public const AUDIENCE = 'subscribers';

    /**
     * Fenêtre du récapitulatif : les sept derniers jours.
     *
     * Sept et non « depuis le dernier envoi » : un rendez-vous hebdomadaire se
     * lit par sa période, pas par les aléas du planificateur. Si un lundi a été
     * manqué, l'édition suivante ne rattrape pas — elle le dit, et c'est à
     * l'équipe de décider.
     */
    public const JOURS_DE_FENETRE = 7;

    /**
     * Plafond de brèves dans un courriel.
     *
     * Au-delà, personne ne lit ; et une semaine à trente fact-checks est une
     * bonne nouvelle qui mérite une édition écrite à la main, pas une liste.
     */
    public const ARTICLES_MAX = 12;

    /** Taille des tranches d'envoi — voir `destinataires()` du dépôt. */
    public const TAILLE_LOT = 200;

    public function __construct(
        private readonly DepotAbonnes $depot,
        private readonly IdentityHasher $hasher,
        private readonly Reglages $reglages,
    ) {}

    // ══════════════════════════════════════════════════════════ abonnement

    /**
     * Inscrit une adresse — ou fait semblant, ce qui doit se voir pareil.
     *
     * Quatre cas, une seule réponse pour l'appelant :
     *
     *   · inconnue → ligne créée, courriel de confirmation envoyé ;
     *   · connue mais non confirmée → nouveau jeton, courriel renvoyé (la
     *     personne a pu perdre le premier) ;
     *   · connue et désabonnée → le double opt-in repart de zéro ;
     *   · connue et confirmée → RIEN. Pas de courriel, pas d'erreur, pas de
     *     différence observable.
     *
     * Le dernier cas est le seul qui compte pour la sécurité : c'est lui qui
     * rend le formulaire inutilisable comme outil d'énumération.
     */
    public function abonner(string $email, ?string $nom = null, ?string $ip = null): void
    {
        $email = mb_strtolower(trim($email));
        $nom = $nom !== null && trim($nom) !== '' ? mb_substr(trim($nom), 0, 120) : null;
        $empreinte = $ip !== null && $ip !== '' ? $this->hasher->hash($ip) : null;

        $abonne = $this->depot->parEmail($email);

        if ($abonne !== null && $abonne->estDestinataire()) {
            /*
             | Déjà abonné et confirmé : on ne renvoie pas de courriel. Le faire
             | transformerait le formulaire en machine à harceler une boîte
             | tierce — il suffirait de le soumettre en boucle.
             */
            return;
        }

        $jeton = self::jeton();

        $abonne = $abonne === null
            ? $this->depot->creer([
                'email' => $email,
                'name' => $nom,
                'confirmation_token' => $jeton,
                'unsubscribe_token' => self::jeton(),
                'consent_ip_hash' => $empreinte,
            ])
            : $this->depot->reinitialiserConfirmation($abonne, $jeton, $nom, $empreinte);

        Mail::to($abonne->email)->send(new ConfirmationAbonnement(
            lien: $this->lienDeConfirmation($abonne),
            lienDeDesabonnement: $this->lienDeDesabonnement($abonne),
            nomDuSite: $this->nomDuSite(),
            prenom: $abonne->name,
        ));
    }

    /**
     * Abonne un compte dont l'adresse est DÉJÀ prouvée.
     *
     * Point d'entrée réservé à la bascule « lettre d'information » des réglages
     * citoyen : la personne est connectée et son adresse a été vérifiée à
     * l'inscription. Redemander une confirmation par courriel ne prouverait
     * rien de plus et ferait perdre l'abonnement à qui ne rouvre pas sa boîte.
     *
     * Le consentement, lui, reste tracé — par `Consent`, côté identité, et par
     * la date de confirmation posée ici.
     */
    public function abonnerCompteVerifie(User $utilisateur, ?string $ip = null): void
    {
        $email = mb_strtolower(trim((string) $utilisateur->email));

        if ($email === '') {
            return;
        }

        $abonne = $this->depot->parEmail($email);
        $empreinte = $ip !== null && $ip !== '' ? $this->hasher->hash($ip) : null;

        if ($abonne === null) {
            $abonne = $this->depot->creer([
                'email' => $email,
                'name' => $utilisateur->name,
                'confirmation_token' => self::jeton(),
                'unsubscribe_token' => self::jeton(),
                'consent_ip_hash' => $empreinte,
            ]);
        }

        $this->depot->confirmer($abonne);
    }

    /** Pendant de `abonnerCompteVerifie()` : la bascule remise à zéro. */
    public function desabonnerCompteVerifie(User $utilisateur): void
    {
        $abonne = $this->depot->parEmail((string) $utilisateur->email);

        if ($abonne !== null) {
            $this->depot->desabonner($abonne);
        }
    }

    /**
     * Confirme un abonnement à partir du jeton reçu par courriel.
     *
     * Renvoie `null` si le jeton est inconnu — jamais d'exception : un lien
     * recopié à la main dans un client mail qui coupe les URL doit produire un
     * message, pas une page d'erreur.
     */
    public function confirmer(string $jeton): ?NewsletterSubscriber
    {
        $abonne = $this->depot->parJetonDeConfirmation($jeton);

        if ($abonne === null) {
            return null;
        }

        return $this->depot->confirmer($abonne);
    }

    /**
     * Désabonne — sans session, sans mot de passe, sans question.
     *
     * L'authenticité vient de la SIGNATURE de l'URL, vérifiée par le middleware
     * `signed` avant d'arriver ici. Demander quoi que ce soit de plus serait un
     * obstacle de plus entre quelqu'un qui veut partir et le bouton
     * « indésirable » de son client mail.
     */
    public function desabonner(string $uuid): bool
    {
        $abonne = $this->depot->parUuid($uuid);

        if ($abonne === null) {
            return false;
        }

        $this->depot->desabonner($abonne);

        return true;
    }

    // ════════════════════════════════════════════════════════════ liens

    public function lienDeConfirmation(NewsletterSubscriber $abonne): string
    {
        return route('public.newsletter.confirm', ['jeton' => $abonne->confirmation_token]);
    }

    /**
     * Lien de désabonnement, signé et SANS échéance.
     *
     * Sans échéance parce qu'un courriel se relit six mois plus tard, et qu'un
     * lien de désabonnement expiré est exactement le scénario qui produit une
     * plainte pour spam. La signature suffit : elle empêche de désabonner
     * quelqu'un d'autre en changeant l'identifiant dans l'URL.
     */
    public function lienDeDesabonnement(NewsletterSubscriber $abonne): string
    {
        return URL::signedRoute('public.newsletter.unsubscribe', ['uuid' => $abonne->uuid]);
    }

    // ═══════════════════════════════════════════════════════════ édition

    /**
     * La clé de l'édition d'un jour donné — `hebdo-2026-W35`.
     *
     * Année ISO et semaine ISO : le seul découpage où la semaine commence bien
     * un lundi, et le seul qui ne fasse pas sauter une édition fin décembre.
     */
    public function cleEdition(CarbonInterface $jour): string
    {
        return 'hebdo-'.CarbonImmutable::instance($jour)->format('o-\WW');
    }

    /**
     * La fenêtre couverte : les sept jours qui précèdent l'exécution.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public function fenetre(CarbonInterface $jour): array
    {
        $fin = CarbonImmutable::instance($jour);

        return [$fin->subDays(self::JOURS_DE_FENETRE), $fin];
    }

    /**
     * @return Collection<int, HubArticle>
     */
    public function factChecksDeLaSemaine(CarbonInterface $jour): Collection
    {
        [$debut, $fin] = $this->fenetre($jour);

        return $this->depot->factChecksPublies($debut, $fin, self::ARTICLES_MAX);
    }

    public function editionExistante(CarbonInterface $jour): ?Campaign
    {
        return $this->depot->campagneDeLEdition($this->cleEdition($jour));
    }

    /**
     * Ouvre (ou retrouve) l'édition de la semaine.
     *
     * `firstOr…` maison plutôt qu'un `firstOrCreate` : la clé d'édition n'est
     * pas au `$fillable` de `Campaign`, modèle partagé — voir l'arbitrage 159.
     *
     * @param  Collection<int, HubArticle>  $articles
     */
    public function ouvrirEdition(CarbonInterface $jour, Collection $articles): Campaign
    {
        $cle = $this->cleEdition($jour);
        $existante = $this->depot->campagneDeLEdition($cle);

        if ($existante !== null) {
            return $existante;
        }

        return $this->depot->creerCampagne($cle, [
            'name' => 'Infolettre — '.$cle,
            'subject' => $this->objet($jour),
            /*
             | Le corps est archivé en TEXTE, pas en HTML : ce qui compte ici
             | est de pouvoir répondre six mois plus tard à « qu'est-ce qui est
             | parti le 26 août ? ». Le rendu, lui, appartient au gabarit.
             */
            'body' => $this->corpsArchive($jour, $articles),
            'audience' => self::AUDIENCE,
            'status' => StatutCampagne::Brouillon->value,
        ]);
    }

    /**
     * L'objet du courriel — daté, parce qu'un rendez-vous se reconnaît.
     *
     * « Les intox de la semaine » sans date se noie dans une boîte ; avec ses
     * bornes, il se retrouve et se cite.
     */
    public function objet(CarbonInterface $jour): string
    {
        [$debut, $fin] = $this->fenetre($jour);

        return sprintf(
            'Les intox de la semaine — du %s au %s',
            $debut->locale('fr')->isoFormat('D MMMM'),
            $fin->locale('fr')->isoFormat('D MMMM YYYY'),
        );
    }

    public function nomDuSite(): string
    {
        return (string) ($this->reglages->get('site_name') ?? "Vérif'All");
    }

    /**
     * Journalise une semaine sans matière.
     *
     * Une semaine sans fact-check publié est une information pour l'équipe, pas
     * un non-événement : soit la production s'est arrêtée, soit la publication
     * est bloquée quelque part. Dans les deux cas, quelqu'un doit le savoir
     * avant le lundi suivant.
     */
    public function signalerSemaineVide(CarbonInterface $jour): void
    {
        Log::warning('Infolettre : aucune brève publiée sur la fenêtre, rien n\'a été envoyé.', [
            'edition' => $this->cleEdition($jour),
            'fenetre_jours' => self::JOURS_DE_FENETRE,
        ]);
    }

    /**
     * @param  Collection<int, HubArticle>  $articles
     */
    private function corpsArchive(CarbonInterface $jour, Collection $articles): string
    {
        $lignes = $articles->map(static fn (HubArticle $a): string => sprintf(
            '- [%s] %s — %s',
            $a->libelleDuBandeau(),
            $a->title,
            route('public.fact', ['article' => $a->slug]),
        ))->all();

        return $this->objet($jour)."\n\n".implode("\n", $lignes);
    }

    /**
     * 64 caractères hexadécimaux, la longueur exacte des colonnes de jetons.
     *
     * `random_bytes()` puise dans le générateur cryptographique du système, et
     * non dans `rand()` : un jeton devinable laisserait confirmer un abonnement
     * au nom d'autrui, ou désabonner quelqu'un d'autre.
     */
    private static function jeton(): string
    {
        return bin2hex(random_bytes(32));
    }
}
