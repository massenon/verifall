<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\ConnexionSocialeController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\StaffLogoutController;
use App\Http\Controllers\Citizen\AnnulerSuppressionController;
use App\Http\Controllers\Citizen\CalendrierLiveClassController;
use App\Http\Controllers\Citizen\SupportDeCoursController;
use App\Http\Controllers\Citizen\TelechargerExportController;
use App\Http\Controllers\LangueController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\MaintenancePage;
use App\Http\Controllers\Public\AbonnementController;
use App\Http\Controllers\Public\CarteDeDementiController;
use App\Http\Controllers\Public\ConditionsGeneralesController;
use App\Http\Controllers\Public\ContactController;
use App\Http\Controllers\Public\DepartVersLaSourceController;
use App\Http\Controllers\Public\FaqController;
use App\Http\Controllers\Public\FluxClaimReviewController;
use App\Http\Controllers\Public\InvitationController;
use App\Http\Controllers\Public\ManifesteController;
use App\Http\Controllers\Public\PlanDuSiteController;
use App\Http\Controllers\Public\RobotsController;
use App\Http\Controllers\Public\ServiceWorkerController;
use App\Http\Controllers\Public\SoutienController;
use App\Http\Controllers\Staff\DonneesDeLaBanqueDeQuestionsController;
use App\Http\Controllers\Staff\DonneesDeLaCorrectionController;
use App\Http\Controllers\Staff\DonneesDeLEnrolementAuxExamensController;
use App\Http\Controllers\Staff\DonneesDesAbonnesController;
use App\Http\Controllers\Staff\DonneesDesExamensController;
use App\Http\Controllers\Staff\DonneesDesFaitsVerifiesController;
use App\Http\Controllers\Staff\DonneesDesMessagesContactController;
use App\Http\Controllers\Staff\DonneesDesSignalementsContenuController;
use App\Http\Controllers\Staff\DonneesDesSourcesVerifieesController;
use App\Http\Controllers\Staff\DonneesDesUtilisateursController;
use App\Http\Controllers\Staff\DonneesDuJournalDesUnitesController;
use App\Http\Controllers\Staff\PieceKycController;
use App\Http\Controllers\Staff\VerrouillerLaSessionController;
use App\Http\Controllers\TourController;
use App\Http\Controllers\Webhooks\MomoWebhookController;
use App\Http\Middleware\CompteActif;
use App\Http\Middleware\GuardStaffParDefaut;
use App\Http\Middleware\SansIndexation;
use App\Livewire\Auth\ForgotPassword;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\Register;
use App\Livewire\Auth\StaffLogin;
use App\Livewire\Auth\VerrouDeSession as VerrouDeSessionEcran;
use App\Livewire\Citizen\ArticleHub;
use App\Livewire\Citizen\Catalogue;
use App\Livewire\Citizen\Certificat;
use App\Livewire\Citizen\Cours;
use App\Livewire\Citizen\Evaluations;
use App\Livewire\Citizen\Exercices;
use App\Livewire\Citizen\Forum;
use App\Livewire\Citizen\HubInfos;
use App\Livewire\Citizen\Lecon;
use App\Livewire\Citizen\LiveClasses;
use App\Livewire\Citizen\MesTutoriels;
use App\Livewire\Citizen\MesVerifications;
use App\Livewire\Citizen\MonParrainage;
use App\Livewire\Citizen\Outils;
use App\Livewire\Citizen\PasserQuiz;
use App\Livewire\Citizen\PortailsOfficiels as CitizenPortailsOfficiels;
use App\Livewire\Citizen\Profil;
use App\Livewire\Citizen\Rapport;
use App\Livewire\Citizen\Recompenses;
use App\Livewire\Citizen\Rediffusion;
use App\Livewire\Citizen\Reglages;
use App\Livewire\Citizen\Statistiques;
use App\Livewire\Citizen\Sujet;
use App\Livewire\Citizen\TableauDeBord;
use App\Livewire\Citizen\Unites;
use App\Livewire\Citizen\VerificationEnCours;
use App\Livewire\Citizen\Verifier;
use App\Livewire\Citizen\VerifierMonIdentite;
use App\Livewire\Public\ActualitesPubliques;
use App\Livewire\Public\ArticleBlog;
use App\Livewire\Public\ArticlePublic;
use App\Livewire\Public\BlogPublic;
use App\Livewire\Public\Corrections;
use App\Livewire\Public\Methodologie;
use App\Livewire\Public\RapportPublic;
use App\Livewire\Public\SourcesPubliques;
use App\Livewire\Public\TutorielPublic;
use App\Livewire\Public\TutorielsPublics;
use App\Livewire\Public\VerifierCertificat;
use App\Livewire\Public\VerifierInvite;
use App\Livewire\Staff\ActualitesHub as StaffActualitesHub;
use App\Livewire\Staff\AlertesIntrusion;
use App\Livewire\Staff\ArticlesDuBlog as StaffArticlesDuBlog;
use App\Livewire\Staff\BadgesDeProgression as StaffBadgesDeProgression;
use App\Livewire\Staff\BanqueDeQuestions as StaffBanqueDeQuestions;
use App\Livewire\Staff\CampagnesPublicitaires as StaffCampagnesPublicitaires;
use App\Livewire\Staff\CategoriesDeCours as StaffCategoriesDeCours;
use App\Livewire\Staff\CategoriesDeSources as StaffCategoriesDeSources;
use App\Livewire\Staff\CategoriesDuBlog as StaffCategoriesDuBlog;
use App\Livewire\Staff\CategoriesDuForum as StaffCategoriesDuForum;
use App\Livewire\Staff\Certificats as StaffCertificats;
use App\Livewire\Staff\ConstructeurDeCours as StaffConstructeurDeCours;
use App\Livewire\Staff\CorrectionExamens as StaffCorrectionExamens;
use App\Livewire\Staff\Cours as StaffCours;
use App\Livewire\Staff\EnrolementAuxCours as StaffEnrolementAuxCours;
use App\Livewire\Staff\EnrolementAuxExamens as StaffEnrolementAuxExamens;
use App\Livewire\Staff\Examens as StaffExamens;
use App\Livewire\Staff\FaitsVerifies;
use App\Livewire\Staff\ForumCitoyen as StaffForumCitoyen;
use App\Livewire\Staff\GabaritCertificat as StaffGabaritCertificat;
use App\Livewire\Staff\GabaritsEmails as StaffGabaritsEmails;
use App\Livewire\Staff\GestionFaq as StaffGestionFaq;
use App\Livewire\Staff\Infolettre;
use App\Livewire\Staff\JournalAudit;
use App\Livewire\Staff\JournalDesEmails as StaffJournalDesEmails;
use App\Livewire\Staff\JournalDesUnites as StaffJournalDesUnites;
use App\Livewire\Staff\LiveClasses as StaffLiveClasses;
use App\Livewire\Staff\Maintenance as StaffMaintenance;
use App\Livewire\Staff\Mentors as StaffMentors;
use App\Livewire\Staff\MesContenus;
use App\Livewire\Staff\MesEtudiants;
use App\Livewire\Staff\MessagesContact;
use App\Livewire\Staff\MotsSurveilles;
use App\Livewire\Staff\Opportunites;
use App\Livewire\Staff\Outils as StaffOutils;
use App\Livewire\Staff\PacksUnites;
use App\Livewire\Staff\Parametres;
use App\Livewire\Staff\Parrainages;
use App\Livewire\Staff\Partenaires as StaffPartenaires;
use App\Livewire\Staff\PerformanceDuMentor;
use App\Livewire\Staff\PortailsOfficiels as StaffPortailsOfficiels;
use App\Livewire\Staff\ProgressionDesApprenants;
use App\Livewire\Staff\Roles as StaffRoles;
use App\Livewire\Staff\SauvegardesBaseDeDonnees as StaffSauvegardesBaseDeDonnees;
use App\Livewire\Staff\Signalements;
use App\Livewire\Staff\SignalementsContenu;
use App\Livewire\Staff\SourcesVerifiees as StaffSourcesVerifiees;
use App\Livewire\Staff\StatistiquesDuCours;
use App\Livewire\Staff\StatistiquesExamens as StaffStatistiquesExamens;
use App\Livewire\Staff\TableauDeBord as TableauDeBordPersonnel;
use App\Livewire\Staff\TachesCron as StaffTachesCron;
use App\Livewire\Staff\Temoignages as StaffTemoignages;
use App\Livewire\Staff\Tipline;
use App\Livewire\Staff\UnitesDesCoursPremium as StaffUnitesDesCoursPremium;
use App\Livewire\Staff\Utilisateurs as StaffUtilisateurs;
use App\Livewire\Staff\VerificationKyc as StaffVerificationKyc;
use App\Models\AuditLog;
use App\Models\Certificate;
use App\Models\ContactMessage;
use App\Models\ContentReport;
use App\Models\Course;
use App\Models\CourseCategory;
use App\Models\Exam;
use App\Models\HubJob;
use App\Models\LiveClass;
use App\Models\ModerationKeyword;
use App\Models\NewsletterSubscriber;
use App\Models\QuestionBankItem;
use App\Models\Referral;
use App\Models\SecurityLog;
use App\Models\Setting;
use App\Models\SourceCategory;
use App\Models\Tool;
use App\Models\TrustedSource;
use App\Models\UnitPack;
use App\Models\User;
use App\Models\VerifiedFact;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Role as SpatieRole;

/*
|--------------------------------------------------------------------------
| Routes — Sprint 1a (socle)
|--------------------------------------------------------------------------
| À ce stade, les routes existent pour rendre les trois shells NAVIGABLES et
| vérifier leur conformité au prototype. Chaque entrée pointe vers une vue
| d'attente qui hérite du bon layout ; les composants Livewire les
| remplaceront sprint après sprint, sans toucher aux noms de routes.
|
| Les noms sont figés dès maintenant : ils sont déjà référencés dans les
| layouts extraits du prototype, et les renommer plus tard casserait la
| navigation transposée.
*/

/*
 | Accueil. Vue statique et non composant Livewire : cette page ne présente
 | aucun état, et lui donner un composant ajouterait une hydratation JavaScript
 | à la page la plus visitée du site pour n'en tirer aucun bénéfice.
 */
Route::view('/', 'public.accueil', ['title' => "Vérif'All — La boussole contre la désinformation numérique"])
    ->name('public.home');

/*
 | Vérification SANS COMPTE — la porte d'entrée du produit. Quelqu'un qui reçoit
 | une rumeur sur WhatsApp doit pouvoir la soumettre en dix secondes ; lui
 | opposer un formulaire d'inscription, c'est le laisser partager sans vérifier.
 |
 | Le quota par empreinte d'adresse tient lieu de garde-fou : sans compte, rien
 | d'autre ne borne l'appel à des services facturés.
 */
Route::get('/verifier-une-information', VerifierInvite::class)->name('public.verify');

/*
 | Vérification PUBLIQUE d'un certificat. Un diplôme que personne ne peut
 | contrôler ne vaut rien : obliger un recruteur à créer un compte reviendrait à
 | le rendre invérifiable. La cadence est limitée dans le composant — sans quoi
 | la page devient un oracle où l'on énumère des codes.
 */
Route::get('/verifier-un-certificat', VerifierCertificat::class)->name('public.verify-certificate');

/*
 | Consultation PUBLIQUE d'un rapport de verification.
 |
 | C'est l'adresse que porte le QR des rapports imprimes, et celle que le menu
 | « Partager » envoie sur WhatsApp. Celui qui scanne la feuille ou recoit le
 | lien n'a pas de compte : `citizen.report` lui renverrait un ecran de
 | connexion, et le controle promis par le papier serait creux.
 |
 | Hors du groupe `public.` ci-dessous, qui sert des pages EDITORIALES sans
 | etat. Celle-ci lit la base et porte une garde qui lui est propre : seuls les
 | rapports PUBLIES existent ici, le composant s'en assure dans la requete
 | elle-meme. Une analyse en cours, partielle ou soumise a arbitrage rend 404 —
 | 404 et non 403, un 403 confirmerait qu'un rapport existe a cette adresse.
 |
 | `SansIndexation` : ces pages ne sont pas du contenu editorial, ce sont les
 | demandes des gens. Les laisser indexer ferait de chaque soumission une page
 | referencee reprenant en titre l'affirmation qu'elle dement. Les dementis
 | destines au referencement sont les `HubArticle`, ecrits par la redaction.
 */
Route::get('/rapport/{verification}', RapportPublic::class)
    ->middleware(SansIndexation::class)
    ->name('public.report');

/*
 | Invitation de parrainage. Publique par nécessité — c'est le lien qu'on
 | partage sur WhatsApp — et sans effet de bord : elle dépose un code en session
 | puis renvoie sur l'inscription.
 */
Route::get('/invitation/{code}', InvitationController::class)
    ->where('code', '[A-Za-z0-9]{1,24}')
    ->name('public.invitation');

// ---------------------------------------------------------------- public
/*
 | Les deux fichiers qu'un moteur lit AVANT toute page.
 |
 | Hors du groupe `public.` ci-dessous parce qu'ils ne sont pas des pages : ils
 | ne portent ni gabarit, ni métadonnées, ni fil d'Ariane. Ils sont engendrés
 | plutôt que déposés dans `public/` — un fichier statique ne sait pas dans quel
 | environnement il se trouve, et un `robots.txt` permissif sur la
 | préproduction fait indexer le site de test.
 */
Route::get('/sitemap.xml', PlanDuSiteController::class)->name('public.sitemap');
Route::get('/robots.txt', RobotsController::class)->name('public.robots');

/*
 | APPLICATION WEB PROGRESSIVE.
 |
 | Le manifeste et le service worker sont engendrés, pas déposés : le premier
 | doit porter les URL du domaine courant, le second doit changer d'octet quand
 | le socle change — sinon il ne se met jamais à jour.
 |
 | `/sw.js` est à la RACINE, et pas ailleurs : la portée d'un service worker est
 | celle de son chemin. Servi depuis `/assets/`, il ne verrait que `/assets/`.
 */
Route::get('/manifest.webmanifest', ManifesteController::class)->name('public.manifest');
Route::get('/sw.js', ServiceWorkerController::class)->name('public.service-worker');
Route::view('/hors-ligne', 'public.hors-ligne')->name('public.offline');

/*
 | LE SÉLECTEUR DE LANGUE.
 |
 | Hors du groupe `public.` : il sert aussi bien au site public qu'à l'espace
 | citoyen, et le ranger dans l'un des deux obligerait l'autre à nommer une
 | route qui ne lui appartient pas.
 |
 | POST et non GET : changer la langue modifie l'état de la session, et un GET
 | qui modifie l'état se déclenche par un préchargement de navigateur.
 */
Route::post('langue/{langue}', LangueController::class)->name('langue');

/*
 | LE FLUX DE VÉRIFICATIONS, pour Google Fact Check Tools.
 |
 | À la racine, et hors du groupe `public.` : c'est une adresse technique,
 | déclarée une fois chez Google, et qui ne doit PLUS JAMAIS changer. Un flux
 | déplacé n'est pas suivi, il est perdu — et le silence qui suit ressemble à
 | s'y méprendre à un flux vide.
 */
Route::get('claimreview.json', FluxClaimReviewController::class)->name('claimreview');

Route::prefix('/')->name('public.')->group(function (): void {
    /*
     | Pages ÉDITORIALES : ce que le produit promet, comment il marche, qui le
     | fait. Contenu transposé du prototype, sans état, donc servi par une vue
     | plutôt que par un composant.
     */
    $pages = [
        'pourquoi-verifall' => ['why', 'pourquoi-verifall', "Pourquoi Vérif'All ?"],
        'fonctionnalites' => ['features', 'fonctionnalites', 'Fonctionnalités'],
        'comment-ca-marche' => ['how', 'comment-ca-marche', 'Comment ça marche ?'],
        'notre-equipe' => ['team', 'notre-equipe', 'Notre Équipe'],
        'financement' => ['funding', 'financement', 'Financement'],
        // AJOUT au prototype (arbitrage 171). Le programme existait, avec ses
        // deux écrans, mais aucune page ne l'expliquait à qui n'a pas encore de
        // compte — c'est-à-dire à la personne même qui reçoit un lien.
        'parrainage' => ['referral', 'parrainage', 'Parrainage'],
        'contact' => ['contact', 'contact', 'Contact'],
        'medias' => ['media', 'medias', 'Médias'],
        'mentions-legales' => ['legal', 'mentions-legales', 'Mentions légales'],
        'confidentialite' => ['privacy', 'confidentialite', 'Confidentialité'],
        'cookies' => ['cookies', 'cookies', 'Cookies'],
    ];

    foreach ($pages as $uri => [$name, $vue, $title]) {
        Route::view($uri, 'public.'.$vue, ['title' => $title." · Vérif'All"])->name($name);
    }

    /*
     | LA FAQ N'EST PLUS STATIQUE (lot 9). Ses dix-sept questions étaient
     | déroulées à la main dans le gabarit et lues dans les fichiers de langue.
     | Elles vivent en base, et `Staff\GestionFaq` les tient — l'écran que le
     | menu d'administration promettait tout en pointant sur CETTE page.
     */
    Route::get('faq', FaqController::class)->name('faq');

    /*
     | Pages qui LISTENT des vérifications ou des articles. Elles lisent la
     | base : figer un verdict en dur sur un site de fact-checking reviendrait à
     | publier une vérification qui n'a jamais eu lieu.
     */
    Route::get('actualites', ActualitesPubliques::class)->name('facts');
    Route::get('actualites/{article}', ArticlePublic::class)->name('fact');
    /*
     | La carte de démenti, engendrée à la demande puis servie en PNG. Deux
     | segments : elle ne peut donc pas être confondue avec la fiche
     | ci-dessus, qui n'en attend qu'un.
     */
    Route::get('actualites/{article}/carte.png', CarteDeDementiController::class)->name('fact.carte');
    /*
     | Le départ vers la source d'origine passe par nous, pour être compté.
     |
     | C'est la colonne « Clics » du panneau « Performance Articles » du
     | back-office : pas les ouvertures de l'article, mais les personnes
     | réellement allées voir la preuve.
     |
     | PUBLIQUE, et une seule pour les deux écrans : la fiche publique et la
     | brève de l'espace citoyen rendent le MÊME `HubArticle`. Deux routes
     | compteraient dans deux colonnes le même geste.
     |
     | Ce n'est PAS une redirection ouverte — la destination est lue en base,
     | jamais reçue en paramètre. Voir le contrôleur.
     */
    Route::get('actualites/{article}/source', DepartVersLaSourceController::class)->name('fact.source');
    /*
     | TRANSPARENCE. Le journal des corrections lit la base — il ne peut donc
     | pas être une simple vue — et la méthodologie lit `config/factcheck`,
     | pour qu'aucun chiffre affiché ne soit recopié à la main.
     */
    /*
     | INFOLETTRE. Les trois routes sont publiques, et chacune pour sa raison :
     | l'infolettre s'adresse aussi à qui n'a pas de compte ; la confirmation
     | est cliquée depuis une boîte mail, hors session ; et exiger une connexion
     | pour SORTIR d'une liste est le plus sûr moyen d'être signalé comme
     | indésirable — ce qui fait tomber la réputation du domaine entier.
     */
    /*
     | CONTACT. Six messages par minute et par IP : assez pour se reprendre
     | après une erreur de saisie, trop peu pour une rafale. Un second rideau
     | compte, côté service, les messages RÉELLEMENT écrits — il survit à un
     | vidage de cookies, là où `throttle` s'appuie aussi sur la session.
     */
    Route::post('contact', ContactController::class)
        ->middleware('throttle:6,1')->name('contact.send');

    // Intention de soutien, depuis la page « Financement ». Elle n'encaisse
    // rien : voir le contrôleur.
    Route::post('financement/soutenir', SoutienController::class)
        ->middleware('throttle:6,1')->name('funding.support');

    Route::post('infolettre/abonnement', [AbonnementController::class, 'souscrire'])
        ->middleware('throttle:6,1')->name('newsletter.subscribe');
    Route::get('infolettre/confirmation/{jeton}', [AbonnementController::class, 'confirmer'])
        ->middleware('throttle:30,1')->name('newsletter.confirm');
    // `signed` fait tout le travail : un identifiant modifié dans la barre
    // d'adresse produit un 403, on ne désabonne donc jamais quelqu'un d'autre.
    Route::get('infolettre/desabonnement/{uuid}', [AbonnementController::class, 'desabonner'])
        ->middleware('signed')->name('newsletter.unsubscribe');

    /*
     | CONDITIONS GÉNÉRALES. Un contrôleur et non `Route::view` : le texte cite
     | l'âge minimum (un réglage) et le délai de rétractation (la constante du
     | service qui l'applique). Les écrire à la main les ferait mentir au
     | premier changement — et c'est justement la clause qu'on invoquerait.
     */
    Route::get('conditions-generales', ConditionsGeneralesController::class)->name('terms');

    Route::get('corrections', Corrections::class)->name('corrections');
    Route::get('methodologie', Methodologie::class)->name('methodology');

    /*
     | NOS SOURCES — le pendant de la méthodologie.
     |
     | Celle-là dit COMMENT une affirmation devient un verdict ; celle-ci dit
     | SUR QUOI. Le référentiel décidait déjà, en silence, du crédit accordé à
     | chaque domaine dans une analyse : le publier rend vérifiable ce qui était
     | opaque, et c'est le minimum qu'une plateforme de fact-checking se doit à
     | elle-même.
     */
    Route::get('nos-sources', SourcesPubliques::class)->name('sources');

    Route::get('blog', BlogPublic::class)->name('blog');
    Route::get('blog/{post}', ArticleBlog::class)->name('blog.show');
    Route::get('tutoriels', TutorielsPublics::class)->name('tutorials');
    // La fiche d'un cours, publique : on ne cache pas ce qu'on propose
    // d'apprendre, et c'est ce lien qu'un mentor colle dans un message.
    Route::get('tutoriels/{tutoriel}', TutorielPublic::class)->name('tutorial');
});

// ------------------------------------------------------------------ auth
// Écrans réels au Sprint 1b ; les noms existent dès maintenant car les
// layouts publics et citoyen y renvoient (CTA Connexion, bouton Déconnexion).
//
// `guest` : servir l'écran de connexion à quelqu'un de déjà connecté lui fait
// croire qu'il est sorti alors que sa session est vivante. C'est exactement
// l'illusion que le bouton « Déconnexion » produisait.
/*
 | LA MAINTENANCE.
 |
 | Deux routes qui répondent MÊME quand le site est fermé — leurs noms figurent
 | dans les exemptions de `MaintenanceApplicative`. Le formulaire est un POST
 | ordinaire : passer par Livewire aurait obligé à exempter `/livewire/update`,
 | soit ouvrir une porte pour trois lignes de code.
 |
 | La page elle-même reste joignable hors maintenance : c'est ainsi qu'on la
 | relit avant de fermer le site, plutôt que de la découvrir en la montrant au
 | public.
 */
Route::get('/maintenance', MaintenancePage::class)->name('maintenance');

Route::post('/maintenance/prevenez-moi', MaintenanceController::class)
    ->middleware('throttle:6,1')->name('maintenance.watch');

Route::get('/connexion', Login::class)->middleware('guest')->name('login');

// POST exclusivement : un GET de deconnexion se declencherait depuis une
// balise <img> tierce ou un prechargeur de navigateur.
Route::post('/deconnexion', LogoutController::class)
    ->middleware('auth')->name('logout');
Route::get('/inscription', Register::class)->middleware('guest')->name('register');

/*
 | CONNEXION SOCIALE. Réservée aux visiteurs : relier un service à un compte
 | déjà connecté se fait depuis les réglages, pas par cette porte.
 |
 | Le nom du fournisseur est contraint ici ET vérifié dans le contrôleur : la
 | contrainte de route écarte le bruit, le contrôleur écarte un fournisseur
 | connu mais non configuré.
 |
 | Ces deux routes n'existent que parce que le cookie de session est en
 | `SameSite=lax` : en `strict`, le navigateur ne le renverrait pas au retour du
 | fournisseur, et l'état anti-CSRF serait perdu à chaque tentative.
 */
Route::middleware('guest')->group(function (): void {
    Route::get('/auth/{fournisseur}/redirection', [ConnexionSocialeController::class, 'rediriger'])
        ->where('fournisseur', '[a-z]+')
        ->name('social.redirect');

    Route::get('/auth/{fournisseur}/retour', [ConnexionSocialeController::class, 'retour'])
        ->where('fournisseur', '[a-z]+')
        ->name('social.callback');
});
Route::get('/mot-de-passe-oublie', ForgotPassword::class)->middleware('guest')->name('password.request');

// --------------------------------------------------------------- citoyen
//
// CompteActif garde tout le groupe : il exige une session ET un compte dont
// l'OTP a ete valide. Tant que ces pages n'etaient que des ebauches, leur
// ouverture ne montrait rien ; des qu'elles portent des donnees, l'absence de
// garde devient une brèche.
Route::prefix('mon-espace')->name('citizen.')->middleware(CompteActif::class)->group(function (): void {
    Route::get('profil', Profil::class)->name('profile');

    /*
     | VÉRIFICATION D'IDENTITÉ. Le profil affichait « Identité non vérifiée »
     | sans aucune action possible : la colonne était un drapeau que seule la
     | console pouvait lever. Cet écran est la moitié manquante, décidée par le
     | porteur du projet le 1er septembre 2026.
     */
    Route::get('verifier-mon-identite', VerifierMonIdentite::class)->name('kyc');
    Route::get('reglages', Reglages::class)->name('settings');
    Route::get('unites', Unites::class)->name('units');
    Route::get('verifier', Verifier::class)->name('verify');

    // Le parcours du prototype : vérifier → analyse en cours → rapport. Les
    // deux écrans sont bornés au PROPRIÉTAIRE : un uuid deviné ne doit
    // renseigner sur rien, pas même sur l'existence de la vérification.
    Route::get('verification/{verification}', VerificationEnCours::class)->name('verification');
    Route::get('rapport/{verification}', Rapport::class)->name('report');
    Route::get('mes-verifications', MesVerifications::class)->name('verifications');

    /*
     | Telechargement de l'archive de portabilite. TROIS gardes, aucune
     | redondante : la signature prouve que le lien vient de nous, la
     | session couvre le cas d'une boite mail compromise, et le controleur
     | verifie le PROPRIETAIRE — un lien transfere ne doit pas livrer les
     | donnees de quelqu'un d'autre a qui se trouve connecte.
     */
    Route::get('mes-donnees/{export}', TelechargerExportController::class)
        ->middleware('signed')->name('export.download');

    /*
     | ACADÉMIE. Les URL portent le slug du cours et l'uuid des ressources :
     | jamais d'identifiant auto-incrémenté, qui laisserait deviner le volume du
     | catalogue et énumérer ce qui n'est pas publié.
     */
    Route::get('catalogue', Catalogue::class)->name('catalogue');
    Route::get('cours/{course}', Cours::class)->name('course');
    Route::get('cours/{course}/lecon/{lesson}', Lecon::class)->name('lesson');
    Route::get('cours/{course}/quiz/{quiz}', PasserQuiz::class)->name('quiz.take');
    Route::get('certificat/{certificate}', Certificat::class)->name('certificate');

    /*
     | FORUM. Le sujet se route sur son slug : une URL lisible se partage sur
     | WhatsApp, et c'est par là que les rumeurs arrivent.
     */
    Route::get('forum', Forum::class)->name('forum');
    Route::get('forum/{thread}', Sujet::class)->name('thread');

    // HUB D'INFOS. La brève se route sur son slug : ces pages sont faites pour
    // circuler, et une URL lisible se recopie sans se casser.
    Route::get('hub-infos', HubInfos::class)->name('hub');
    Route::get('hub-infos/{article}', ArticleHub::class)->name('hub.article');

    /*
     | PORTAILS OFFICIELS. La rubrique est FERMÉE par défaut : le composant rend
     | 404 tant que le réglage `hub_portals_actif` n'est pas ouvert, à la
     | demande du porteur du projet. L'entrée figure dans le menu du compte,
     | rendue désactivée — on annonce ce qui vient sans le faire cliquer.
     |
     | La garde est DANS le composant et non ici : un middleware supplémentaire
     | pour une seule route ferait chercher la règle à deux endroits.
     */
    Route::get('portails-officiels', CitizenPortailsOfficiels::class)->name('portals');

    /*
     | SUPPORTS DE COURS. Le fichier vit sur le disque privé et n'a aucune URL
     | de stockage : un cours payant dont les PDF sont devinables est un cours
     | vendu une fois et diffusé cent.
     |
     | `signed` PROUVE que le lien vient de nous ; la garde du contrôleur
     | vérifie l'inscription. Les deux sont nécessaires — un lien signé se
     | transmet, et un lien transmis ne doit pas ouvrir le cours d'un autre.
     */
    Route::get('supports/{piece}', SupportDeCoursController::class)
        ->middleware('signed')->name('lesson-attachment');

    // GAMIFICATION ET PARRAINAGE.
    Route::get('recompenses', Recompenses::class)->name('rewards');
    Route::get('parrainage', MonParrainage::class)->name('referral');

    // TABLEAU DE BORD. La page d'arrivée après connexion.
    Route::get('tableau-de-bord', TableauDeBord::class)->name('dashboard');

    // LIVE CLASSES. Le fichier calendrier est fabriqué par le serveur : un
    // fuseau recalculé par le navigateur décale le rendez-vous deux fois par an.
    Route::get('live-classes', LiveClasses::class)->name('live-classes');
    Route::get('live-classes/{liveClass}/calendrier', CalendrierLiveClassController::class)
        ->name('live-class.ics');

    /*
     | LA REDIFFUSION. Elle n'existait pas : « Voir le replay » renvoyait sur la
     | liste des tutoriels, faute de la moindre colonne d'enregistrement (B-06).
     |
     | Un écran et non une redirection vers l'adresse de la vidéo : renvoyer
     | droit sur `replay_url` la mettrait dans la barre du navigateur, donc en
     | circulation hors de la plateforme — et la règle d'accès ne vaudrait plus
     | rien.
     */
    Route::get('live-classes/{liveClass}/rediffusion', Rediffusion::class)
        ->name('live-class.replay');

    // RÉPERTOIRE D'OUTILS. Annuaire d'outils externes, filtré côté serveur.
    Route::get('outils', Outils::class)->name('tools');

    // EXERCICES. Les travaux pratiques, corrigés par un mentor.
    Route::get('exercices', Exercices::class)->name('exercises');
    Route::get('evaluations', Evaluations::class)->name('evaluations');
    Route::get('mes-tutoriels', MesTutoriels::class)->name('tutorials');
    Route::get('statistiques', Statistiques::class)->name('stats');

    /*
     | `quiz.html` du prototype EST le lecteur de quiz : il se lit avec
     | `?c=<cours>&q=<quiz>`. Son équivalent ici est `citizen.quiz.take`, qui
     | porte les deux identifiants dans l'URL. Ouvrir `/mon-espace/quiz` sans
     | savoir de quel quiz il s'agit n'a pas de sens : on ramène donc vers la
     | liste des évaluations, qui est l'endroit où l'on choisit.
     */
    Route::redirect('quiz', '/mon-espace/evaluations')->name('quiz');
});

// ------------------------------------------------- rappel Mobile Money
//
// Route PUBLIQUE par necessite — l'operateur nous appelle sans session — et
// dont l'effet est de crediter des unites. Elle est donc protegee par une
// signature HMAC verifiee dans le controleur, et exclue de la protection
// CSRF : un serveur tiers ne peut pas porter notre jeton de formulaire.
Route::post('/webhooks/momo/{provider}', MomoWebhookController::class)
    ->name('webhooks.momo');

// ------------------------------------------------- retractation RGPD
//
// SANS session, et c'est le coeur du dispositif : le compte etant ferme, il
// n'est plus possible de se connecter pour changer d'avis. La signature de
// l'URL tient lieu de preuve, et sa duree est bornee a l'echeance.
Route::get('/compte/annuler-suppression/{uuid}', AnnulerSuppressionController::class)
    ->middleware('signed')->name('account.deletion.cancel');

// ------------------------------------------------------------------ staff
Route::prefix('admin')->name('staff.')->group(function (): void {
    // Le back-office exige le guard STAFF : une session citoyenne, meme
    // celle d'un administrateur, n'y donne aucun acces.
    //
    // GuardStaffParDefaut fait de `staff` le guard interroge par Gate : sans
    // lui, les Policies consultent le guard `web`, vide ici, et refusent un
    // administrateur legitime.
    Route::middleware(['auth:staff', GuardStaffParDefaut::class])->group(function (): void {
        /*
         | LA PREMIÈRE PAGE APRÈS CONNEXION, et elle servait un écran d'attente.
         |
         | `stub.staff` affichait « SPRINT 1A — le contenu de cette page arrive
         | à son sprint ». Tout le reste du back-office était construit ; seule
         | la page d'accueil, celle que l'on voit forcément, annonçait un
         | chantier. Signalé en production.
         */
        Route::get('tableau-de-bord', TableauDeBordPersonnel::class)
            ->name('dashboard');

        /*
         | ÉCONOMIE. La grille tarifaire, modifiable sans déploiement — c'est
         | l'arbitrage n° 15 qui se referme ici : le prix des packs est une
         | décision commerciale, elle appartient au porteur du projet.
         */
        /*
         | Parrainage : lecture seule. Aucune écriture n'est exposée — la
         | récompense est versée par le service au moment où le filleul remplit
         | la condition, et un second chemin vers le même effet ne serait pas
         | testé par les mêmes tests.
         */
        /*
         | Hub — opportunités. Lot 2 du sprint 6 : la couche métier existait
         | (portées, dix natures, fermeture automatique une semaine après
         | l'échéance) mais AUCUN écran ne permettait d'en créer une. Le Hub
         | était un présentoir qu'on ne pouvait pas garnir.
         */
        Route::get('hub-opportunites', Opportunites::class)
            ->middleware('can:viewAny,'.HubJob::class)->name('hub-jobs');

        /*
         | PORTAILS OFFICIELS — le dernier écran du prototype sans route.
         | `hub_portals` existait avec cinq lignes semées, et RIEN ne les
         | lisait : ni CRUD, ni affichage, ni API.
         */
        Route::get('hub-portails', StaffPortailsOfficiels::class)
            ->middleware('can:hub_portal.view')->name('hub-portals');

        /*
         | ACADÉMIE ET RESSOURCES — deux écrans qui n'existaient pas, et dont
         | les entrées du menu latéral pointaient vers l'ESPACE CITOYEN
         | (`citizen.live-classes`, `citizen.tools`). L'administrateur basculait
         | du guard `staff` au guard `web` et, sans session citoyenne ouverte,
         | tombait sur l'écran de connexion citoyen depuis le back-office.
         |
         | Les Live Classes portent en outre la saisie de la rediffusion : le
         | lot 1 avait ajouté les colonnes et l'écran citoyen qui les lit, sans
         | aucun moyen de les remplir.
         */
        Route::get('live-classes', StaffLiveClasses::class)
            ->middleware('can:viewAny,'.LiveClass::class)->name('live-classes');

        Route::get('outils', StaffOutils::class)
            ->middleware('can:viewAny,'.Tool::class)->name('tools');

        /*
         | FACT-CHECKING — le référentiel des sources et le journal des unités.
         |
         | Ces trois entrées du menu portaient la mention « à venir » depuis
         | l'origine. La première n'est pas un annuaire de plus : le MOTEUR
         | d'analyse consulte `trusted_sources` à chaque lien examiné, et une
         | fiche active y désarme la règle d'usurpation institutionnelle.
         |
         | Le journal, lui, ouvre le seul geste que le produit déclarait sans
         | l'offrir : `MotifMouvementUnite::AjustementAdmin` existait, et rien
         | ne l'émettait — corriger un solde demandait `tinker` en production,
         | alors même que les CGU s'y engagent par écrit.
         */
        Route::get('sources-verifiees', StaffSourcesVerifiees::class)
            ->middleware('can:viewAny,'.TrustedSource::class)->name('trusted-sources');

        Route::get('sources-verifiees-data', DonneesDesSourcesVerifieesController::class)
            ->middleware('can:viewAny,'.TrustedSource::class)->name('trusted-sources.data');

        Route::get('categories-de-sources', StaffCategoriesDeSources::class)
            ->middleware('can:viewAny,'.SourceCategory::class)->name('source-categories');

        /*
         | Garde par permission et non par Policy : le journal n'a pas de
         | modèle propriétaire — il porte sur TOUS les comptes.
         |
         | La garde de route se borne au droit du fact-checking ; le composant
         | accepte AUSSI `security.unit_logs`, parce que le prototype portait
         | deux entrées de menu pour cette table et qu'un seul écran les sert
         | (arbitré au lot 10). Un responsable sécurité y arrive par le lien de
         | son propre groupe, qui pointe ici.
         */
        Route::get('journal-des-unites', StaffJournalDesUnites::class)
            // Gate plutôt que `can:permission` : l'accès est un OU entre le
            // droit de l'économie et celui de la sécurité, que le middleware
            // `can:` ne sait pas exprimer. Voir `AppServiceProvider`.
            ->middleware('can:journal-des-unites')->name('unit-journal');

        Route::get('journal-des-unites-data', DonneesDuJournalDesUnitesController::class)
            ->middleware('can:journal-des-unites')->name('unit-journal.data');

        /*
         | ACADÉMIE (lot 6). Le constructeur est un écran PAR COURS, adressé par
         | le slug : on y revient, on le partage à un relecteur, il survit à un
         | rechargement. Un tiroir ouvert depuis une liste ne permet rien de
         | tout cela.
         |
         | Sa garde est `update` et non `viewAny` : ouvrir le constructeur, même
         | pour regarder, c'est ouvrir des formulaires d'écriture. La Policy
         | borne en outre le mentor à SES cours.
         */
        Route::get('cours', StaffCours::class)
            ->middleware('can:viewAny,'.Course::class)->name('courses');

        /*
         | ESPACE MENTOR (2 septembre 2026).
         |
         | Quatre écrans qui manquaient : le mentor empruntait jusqu'ici les
         | écrans d'administration, voyait TOUS les cours, et n'avait aucun
         | moyen de savoir qui suivait les siens.
         |
         | Leurs gardes emploient enfin `student.view` et `student.progress`,
         | attribuées au rôle Mentor depuis l'origine et **employées nulle
         | part** — deux permissions semées qu'aucun écran ne consommait.
         |
         | Ils ne sont PAS réservés au mentor : un administrateur y accède et y
         | voit la plateforme entière. Le cadrage se fait sur le droit
         | (`user.view`), pas sur le rôle — voir `EspaceDuMentor`.
         */
        /*
         | La file des vérifications citoyennes — le dernier écran que la
         | matrice promettait sans le tenir. `signalement.publish` et
         | `signalement.delete` y trouvent enfin leur emploi ; jusqu'ici, aucune
         | page ne listait les vérifications, et le lien « Tout voir » du
         | tableau de bord menait à la base de connaissances, un autre objet.
         */
        Route::get('signalements', Signalements::class)
            ->middleware('can:signalement.view')->name('verifications');

        Route::get('mes-etudiants', MesEtudiants::class)
            ->middleware('can:student.view')->name('my-students');

        Route::get('progression', ProgressionDesApprenants::class)
            ->middleware('can:student.progress')->name('progression');

        Route::get('performance', PerformanceDuMentor::class)
            ->middleware('can:student.progress')->name('mentor-analytics');

        // `lesson.view` et non `student.*` : cet écran liste des CONTENUS, pas
        // des personnes. Employer la permission des apprenants y donnerait accès
        // à qui suit une cohorte sans rien produire.
        Route::get('mes-contenus', MesContenus::class)
            ->middleware('can:lesson.view')->name('my-content');

        Route::get('cours/{slug}/constructeur', StaffConstructeurDeCours::class)
            ->name('course-builder');

        /*
         | STATISTIQUES D'UN COURS. Trois chiffres et la liste des inscrits, les
         | moins avancés en tête : cet écran sert à relancer, et on relance ceux
         | qui décrochent.
         |
         | Pas de `can:` ici : c'est la Policy `view` du COURS qui tranche, dans
         | le composant. Un mentor n'accède qu'aux siens, et une permission
         | globale ne saurait pas le dire.
         */
        Route::get('cours/{slug}/statistiques', StatistiquesDuCours::class)
            ->name('course-stats');

        Route::get('categories-de-cours', StaffCategoriesDeCours::class)
            ->middleware('can:viewAny,'.CourseCategory::class)->name('course-categories');

        Route::get('banque-de-questions', StaffBanqueDeQuestions::class)
            ->middleware('can:viewAny,'.QuestionBankItem::class)->name('question-bank');

        Route::get('banque-de-questions-data', DonneesDeLaBanqueDeQuestionsController::class)
            ->middleware('can:viewAny,'.QuestionBankItem::class)->name('question-bank.data');

        Route::get('certificats', StaffCertificats::class)
            ->middleware('can:viewAny,'.Certificate::class)->name('certificates');

        /*
         | La grille tarifaire est gardée par `course.view` : changer un prix,
         | c'est modifier un cours, et la Policy borne le mentor à ses cours.
         | Pas de permission neuve — la règle du chantier l'interdit.
         */
        Route::get('unites-des-cours', StaffUnitesDesCoursPremium::class)
            ->middleware('can:viewAny,'.Course::class)->name('course-units');

        /*
         | EXAMENS ET ENRÔLEMENT (lot 7). Les dépôts et les services existaient
         | tous — 18 méthodes pour les épreuves, 11 pour la planification, 8
         | pour l'enrôlement, 5 pour la correction. Il ne manquait que les
         | écrans : rien de tout cela n'était atteignable sans la console.
         |
         | Les gardes par PERMISSION plutôt que par Policy là où l'écran ne
         | porte pas sur un modèle propriétaire : la file de correction, les
         | statistiques et les deux enrôlements traversent plusieurs agrégats.
         */
        Route::get('examens', StaffExamens::class)
            ->middleware('can:viewAny,'.Exam::class)->name('exams');

        Route::get('examens-data', DonneesDesExamensController::class)
            ->middleware('can:viewAny,'.Exam::class)->name('exams.data');

        Route::get('correction-examens', StaffCorrectionExamens::class)
            ->middleware('can:exam.grade')->name('exam-grading');

        Route::get('correction-examens-data', DonneesDeLaCorrectionController::class)
            ->middleware('can:exam.grade')->name('exam-grading.data');

        Route::get('statistiques-examens', StaffStatistiquesExamens::class)
            ->middleware('can:exam_stat.view')->name('exam-stats');

        Route::get('enrolement-cours', StaffEnrolementAuxCours::class)
            ->middleware('can:enrolment.view')->name('course-enrolment');

        Route::get('enrolement-examens', StaffEnrolementAuxExamens::class)
            ->middleware('can:enrolment.view')->name('exam-enrolment');

        Route::get('enrolement-examens-data', DonneesDeLEnrolementAuxExamensController::class)
            ->middleware('can:enrolment.view')->name('exam-enrolment.data');

        Route::get('mentors', StaffMentors::class)
            /*
             | `mentor.view` et non `user.view`.
             |
             | Les deux permissions étaient déclarées, seule la seconde était
             | employée : `mentor.view` et `mentor.manage` dormaient dans la
             | matrice, et retirer `mentor.view` à quelqu'un ne lui retirait
             | rien. Une matrice qui promet un contrôle d'accès inexistant est
             | pire qu'une matrice incomplète — on s'y fie.
             |
             | Aucun changement d'accès : l'Admin détient les deux.
             */
            ->middleware('can:mentor.view')->name('mentors');

        /*
         | KYC — l'écran ne peut lever qu'un drapeau : aucune pièce n'est
         | déposée sur la plateforme, et aucun écran citoyen ne permet d'en
         | déposer.
         |
         | LEVÉ le 1er septembre 2026, sur décision du porteur du projet : la
         | chaîne documentaire complète existe — dépôt citoyen, disque privé,
         | file d'examen, motif de refus obligatoire, purge des pièces à trente
         | jours. Voir `App\Core\Identity\Services\DossiersKyc`.
         */
        Route::get('kyc', StaffVerificationKyc::class)
            ->middleware('can:kyc.view')->name('kyc');

        /*
         | La PIÈCE elle-même : le fichier le plus sensible de la plateforme.
         |
         | Signature à durée limitée + session du personnel + habilitation
         | `kyc.review` + présence du fichier. Aucune de ces quatre conditions
         | n'est redondante — le détail est dans le contrôleur.
         |
         | `{piece}` est borné à trois valeurs : un rôle inventé rend 404, et ne
         | renseigne donc pas sur ce que le dossier contient.
         */
        Route::get('kyc/{dossier}/piece/{piece}', PieceKycController::class)
            ->middleware(['signed', 'can:kyc.review'])
            ->whereIn('piece', ['recto', 'verso', 'selfie'])
            ->name('kyc.piece');

        /*
         | HUB, COMMUNAUTÉ, BLOG (lot 8).
         |
         | Deux écrans de modération coexistent et ne font PAS la même chose :
         | « Signalements » traite ce que les citoyens ont porté à la
         | modération ; « Forum citoyen » est la modération à l'initiative du
         | modérateur, sur ce qu'il lit. Les confondre ferait passer à côté de
         | ce que personne ne signale.
         |
         | Le Hub et le blog sont eux aussi distincts : un article du Hub porte
         | une affirmation et un verdict, et alimente le flux ClaimReview ; un
         | billet de blog est du texte de fond. Un texte d'opinion entré dans le
         | flux y serait lu comme une vérification.
         */
        Route::get('hub-actualites', StaffActualitesHub::class)
            ->middleware('can:hub_article.view')->name('hub-news');

        Route::get('forum', StaffForumCitoyen::class)
            ->middleware('can:forum.view')->name('forum');

        Route::get('forum-categories', StaffCategoriesDuForum::class)
            ->middleware('can:forum_category.view')->name('forum-categories');

        Route::get('blog', StaffArticlesDuBlog::class)
            ->middleware('can:post.view')->name('posts');

        Route::get('blog-categories', StaffCategoriesDuBlog::class)
            ->middleware('can:blog_category.view')->name('blog-categories');

        /*
         | RESSOURCES ET COMMUNICATION (lot 9).
         |
         | « Gestion FAQ » du menu pointait sur `route('public.faq')` : on
         | cliquait pour gérer la FAQ et on atterrissait sur la page publique.
         | Même famille que les deux défauts du lot 2 — et comme eux, le
         | correctif n'était pas un `href` : l'écran n'existait pas.
         */
        Route::get('faq', StaffGestionFaq::class)
            ->middleware('can:faq.view')->name('faq');

        Route::get('partenaires', StaffPartenaires::class)
            ->middleware('can:partner.view')->name('partners');

        Route::get('temoignages', StaffTemoignages::class)
            ->middleware('can:testimonial.view')->name('testimonials');

        Route::get('campagnes', StaffCampagnesPublicitaires::class)
            ->middleware('can:campaign.view')->name('campaigns');

        /*
         | Les gabarits d'e-mails et les badges relèvent de `system.*` : ce sont
         | des réglages de la plateforme, pas des contenus éditoriaux. La
         | matrice les range là, l'écran s'y tient.
         */
        Route::get('gabarits-emails', StaffGabaritsEmails::class)
            ->middleware('can:system.email_template')->name('email-templates');

        Route::get('badges', StaffBadgesDeProgression::class)
            ->middleware('can:system.badge')->name('badges');

        /*
         | SÉCURITÉ ET MAINTENANCE (lot 10) — les trois derniers écrans.
         |
         | Tous en LECTURE SEULE, et c'est ce que la matrice accorde :
         | `security` ne déclare que des verbes de lecture, `maintenance` un
         | seul verbe par sujet. Un journal qu'on peut modifier n'est plus un
         | journal, et il sert précisément à contredire ce qu'on croit.
         */
        Route::get('logs-emails', StaffJournalDesEmails::class)
            ->middleware('can:security.email_logs')->name('email-logs');

        Route::get('taches-cron', StaffTachesCron::class)
            ->middleware('can:maintenance.cron')->name('cron');

        Route::get('sauvegardes', StaffSauvegardesBaseDeDonnees::class)
            ->middleware('can:maintenance.backup')->name('backups');

        /*
         | Le mode maintenance. Sa propre permission : fermer le site n'est ni
         | sauvegarder la base ni consulter des tâches planifiées.
         */
        Route::get('mode-maintenance', StaffMaintenance::class)
            ->middleware('can:maintenance.mode')->name('maintenance-mode');

        /*
         | UTILISATEURS ET RÔLES — le trou le plus long du back-office.
         |
         | Les deux guards partagent la table `users` : un administrateur est un
         | `User` portant le rôle `Admin`. Et AUCUN écran ne permettait d'en
         | créer un — le seul chemin était `StaffSeeder`, c'est-à-dire un
         | développeur avec un accès console. Le porteur du projet ne pouvait
         | pas ouvrir l'accès d'un nouveau modérateur sans faire appel à
         | quelqu'un d'autre.
         |
         | « Rôles » est en LECTURE SEULE, et c'est argumenté dans le composant :
         | la matrice est du code, elle se relit en revue et porte la raison de
         | chaque arbitrage. La rendre modifiable ferait diverger la base et le
         | dépôt, et le prochain semis écraserait l'une des deux.
         */
        Route::get('utilisateurs', StaffUtilisateurs::class)
            ->middleware('can:viewAny,'.User::class)->name('users');

        Route::get('utilisateurs-data', DonneesDesUtilisateursController::class)
            ->middleware('can:viewAny,'.User::class)->name('users.data');

        Route::get('roles', StaffRoles::class)
            ->middleware('can:viewAny,'.SpatieRole::class)->name('roles');

        /*
         | GABARIT DU CERTIFICAT. Un aperçu, pas un formulaire : les réglages se
         | saisissent aux Paramètres avec les autres. Ce qui manquait était le
         | rendu — on réglait une couleur et l'on découvrait le résultat en
         | délivrant un vrai diplôme à une vraie personne.
         |
         | Garde `Setting` et non `Certificate` : cet écran ne délivre ni ne
         | révoque rien, il montre l'effet d'une configuration.
         */
        Route::get('certificat-gabarit', StaffGabaritCertificat::class)
            /*
             | `system.certificate_template`, déclarée et jamais employée. La
             | Policy `Setting` garde le MODÈLE ; la route garde l'ÉCRAN. Les
             | deux niveaux ne disent pas la même chose : le premier autorise à
             | écrire un réglage, le second à ouvrir cette page-là.
             */
            ->middleware(['can:system.certificate_template', 'can:viewAny,'.Setting::class])
            ->name('certificate-template');

        /*
         | Paramètres. Dix onglets, une soixantaine de réglages qui vivaient
         | jusqu'ici dans `.env` ou en dur — donc modifiables seulement par un
         | développeur, pour des décisions qui appartiennent au porteur.
         */
        Route::get('parametres', Parametres::class)
            ->middleware(['can:system.settings', 'can:viewAny,'.Setting::class])
            ->name('settings');

        Route::get('parrainages', Parrainages::class)
            ->middleware('can:viewAny,'.Referral::class)->name('referrals');

        Route::get('packs-unites', PacksUnites::class)
            ->middleware('can:viewAny,'.UnitPack::class)->name('unit-packs');

        Route::get('infolettre', Infolettre::class)
            ->middleware('can:viewAny,'.NewsletterSubscriber::class)->name('newsletter');

        Route::get('infolettre-abonnes-data', DonneesDesAbonnesController::class)
            ->middleware('can:viewAny,'.NewsletterSubscriber::class)->name('newsletter.data');

        /*
         | MODÉRATION ET COURRIER. Deux files qui se remplissaient sans que
         | personne puisse les lire — aucun écran n'existait pour l'une ni pour
         | l'autre. C'est la panne la plus discrète : rien ne casse, rien
         | n'alerte, et ce sont les citoyens qui en paient le prix.
         */
        Route::get('signalements-contenu', SignalementsContenu::class)
            ->middleware('can:viewAny,'.ContentReport::class)->name('content-reports');

        Route::get('signalements-contenu-data', DonneesDesSignalementsContenuController::class)
            ->middleware('can:viewAny,'.ContentReport::class)->name('content-reports.data');

        // La liste que consulte le filtre à chaque publication. En base et non
        // en configuration : une rumeur nouvelle apparaît un mardi matin, et
        // attendre un déploiement pour la surveiller revient à ne pas le faire.
        Route::get('mots-surveilles', MotsSurveilles::class)
            ->middleware('can:viewAny,'.ModerationKeyword::class)->name('moderation-keywords');

        /*
         | La tipline WhatsApp. Écran d'ÉTAT : il dit ce qui manque et pourquoi
         | le module reste éteint. Les valeurs se saisissent aux paramètres,
         | avec les autres secrets — deux endroits où écrire un jeton, c'est un
         | endroit où l'on oublie de le changer.
         */
        Route::get('tipline-whatsapp', Tipline::class)
            ->middleware('can:viewAny,'.Setting::class)->name('tipline');

        Route::get('messages', MessagesContact::class)
            ->middleware('can:viewAny,'.ContactMessage::class)->name('contact-messages');

        Route::get('messages-data', DonneesDesMessagesContactController::class)
            ->middleware('can:viewAny,'.ContactMessage::class)->name('contact-messages.data');

        // Journaux. L'autorisation passe par la POLICY, pas par la seule
        // permission : la Policy porte en plus l'interdiction d'ecrire, qui
        // vaut pour tout le monde et doit survivre aux ecrans a venir.
        Route::get('securite', JournalAudit::class)
            ->middleware('can:viewAny,'.AuditLog::class)->name('audit');

        Route::get('alertes', AlertesIntrusion::class)
            ->middleware('can:viewAny,'.SecurityLog::class)->name('alerts');

        // Base des faits tranches par l'equipe. Meme patron que les journaux :
        // la Policy porte la nuance qu'un middleware `permission:` ne sait pas
        // dire — rediger un fait et le PUBLIER ne demandent pas le meme droit.
        Route::get('base-de-connaissances', FaitsVerifies::class)
            ->middleware('can:viewAny,'.VerifiedFact::class)->name('knowledge');

        Route::get('base-de-connaissances-data', DonneesDesFaitsVerifiesController::class)
            ->middleware('can:viewAny,'.VerifiedFact::class)->name('knowledge.data');

        // POST exclusivement, comme côté citoyen ; la sortie est en outre
        // inscrite au journal d'audit.
        Route::post('deconnexion', StaffLogoutController::class)
            ->name('logout');

        // Verrouillage de session (arbitrage n° 180) : POST seulement.
        Route::post('verrouiller', VerrouillerLaSessionController::class)
            ->name('lock');
    });

    Route::get('connexion', StaffLogin::class)->middleware('guest:staff')->name('login');

    // L'écran de verrouillage : la garde staff y est DÉCONNECTÉE ; c'est la
    // session qui sait qui revient (VerrouDeSession).
    Route::get('verrou', VerrouDeSessionEcran::class)->middleware('guest:staff')->name('verrou');
});

// ------------------------------------------------ guide vivant des composants
// Fermé en production : c'est un outil d'équipe, pas une page du produit.
if (! app()->isProduction()) {
    Route::view('_composants', 'ds.showcase')->name('ds.showcase');
}

// ------------------------------------------------------------ visite guidée
// Accessible aux deux guards : la visite existe côté citoyen comme côté staff.
Route::middleware('auth:web,staff')->group(function (): void {
    Route::post('/visite-guidee/terminee', TourController::class)
        ->name('tour.done');

    Route::post('/visite-guidee/relancer', [TourController::class, 'relancer'])
        ->name('tour.restart');
});
