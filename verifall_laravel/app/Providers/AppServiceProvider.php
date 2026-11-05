<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Application\Contracts\DepotAbonnes;
use App\Core\Application\Contracts\DepotAnalytique;
use App\Core\Application\Contracts\DepotBanqueQuestions;
use App\Core\Application\Contracts\DepotCopiesExamen;
use App\Core\Application\Contracts\DepotCorrections;
use App\Core\Application\Contracts\DepotExamens;
use App\Core\Application\Contracts\DepotFaitsVerifies;
use App\Core\Application\Contracts\DepotFicheDuCompte;
use App\Core\Application\Contracts\DepotInscriptionsExamen;
use App\Core\Application\Contracts\DepotMessagesContact;
use App\Core\Application\Contracts\DepotNotes;
use App\Core\Application\Contracts\DepotPlanDuSite;
use App\Core\Application\Contracts\DepotSignalementsContenu;
use App\Core\Application\Contracts\DepotSourcesFiables;
use App\Core\Application\Contracts\DepotStatistiquesDuTableauDeBord;
use App\Core\Application\Contracts\DepotTableauDeBordMentor;
use App\Core\Application\Contracts\DepotUtilisateurs;
use App\Core\Application\Contracts\UnitLogRepositoryInterface;
use App\Core\Application\Contracts\XpLogRepositoryInterface;
use App\Core\Application\Reglages\IdentiteLegale;
use App\Core\Economy\Providers\FakeMomoProvider;
use App\Core\Economy\Providers\MomoProviderInterface;
use App\Enums\UserRole;
use App\Http\Middleware\GuardStaffParDefaut;
use App\Infrastructure\Persistence\DepotAbonnesEloquent;
use App\Infrastructure\Persistence\DepotAnalytiqueEloquent;
use App\Infrastructure\Persistence\DepotBanqueQuestionsEloquent;
use App\Infrastructure\Persistence\DepotCopiesExamenEloquent;
use App\Infrastructure\Persistence\DepotCorrectionsEloquent;
use App\Infrastructure\Persistence\DepotExamensEloquent;
use App\Infrastructure\Persistence\DepotFaitsVerifiesEloquent;
use App\Infrastructure\Persistence\DepotFicheDuCompteEloquent;
use App\Infrastructure\Persistence\DepotInscriptionsExamenEloquent;
use App\Infrastructure\Persistence\DepotMessagesContactEloquent;
use App\Infrastructure\Persistence\DepotNotesEloquent;
use App\Infrastructure\Persistence\DepotPlanDuSiteEloquent;
use App\Infrastructure\Persistence\DepotSignalementsContenuEloquent;
use App\Infrastructure\Persistence\DepotSourcesFiablesEloquent;
use App\Infrastructure\Persistence\DepotStatistiquesDuTableauDeBordEloquent;
use App\Infrastructure\Persistence\DepotTableauDeBordMentorEloquent;
use App\Infrastructure\Persistence\DepotUtilisateursEloquent;
use App\Infrastructure\Persistence\EloquentUnitLogRepository;
use App\Infrastructure\Persistence\EloquentXpLogRepository;
use App\Models\Partner;
use App\Models\User;
use App\Policies\RolePolicy;
use Illuminate\Auth\Middleware\RedirectIfAuthenticated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View as VueRendue;
use Livewire\Livewire;
use RuntimeException;
use Spatie\Permission\Models\Role as SpatieRole;
use Throwable;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /*
         | Persistance : l'interface vit dans le domaine, l'implémentation dans
         | l'infrastructure. Le service ne connaît que le contrat, ce qui rend
         | le journal des unités remplaçable — et testable — sans le toucher.
         */
        // Le plan du site lit trois tables ; la règle 2 du chantier interdit
        // de le faire depuis un contrôleur.
        $this->app->bind(DepotPlanDuSite::class, DepotPlanDuSiteEloquent::class);

        /*
         | Notes de communauté. Le dépôt sert deux lecteurs aux besoins
         | opposés : l'écran d'un sujet, qui veut trois notes et les veut vite,
         | et la commande de pontage, qui veut tous les votes du forum d'un
         | coup. L'interface les nomme distinctement pour qu'on ne se trompe pas
         | d'appel depuis une requête web.
         */
        $this->app->bind(DepotNotes::class, DepotNotesEloquent::class);

        // Infolettre. Sans cette liaison le conteneur ne sait pas résoudre
        // l'interface, et le formulaire d'abonnement lève dès le premier envoi.
        $this->app->bind(DepotAbonnes::class, DepotAbonnesEloquent::class);

        /*
         | Pilote DataTables (lot 0). Liaison manquante constatée le 12
         | octobre 2026 en vérifiant la suite de l'écran « Utilisateurs » :
         | sans elle, `TableauDesUtilisateurs` lève une
         | `BindingResolutionException` et la source JSON répond 500 à
         | CHAQUE appel — l'écran de référence des ~58 migrations DataTables
         | était inutilisable dès son premier chargement.
         */
        $this->app->bind(DepotUtilisateurs::class, DepotUtilisateursEloquent::class);

        // Journal public des corrections. Le contrat porte déjà un attribut
        // `#[Bind]` qui suffirait ; la liaison est écrite ici pour que les huit
        // dépôts se lisent au même endroit.
        $this->app->bind(DepotCorrections::class, DepotCorrectionsEloquent::class);

        // Boîte de contact. Le formulaire du prototype ne postait nulle part ;
        // il poste désormais ici.
        $this->app->bind(DepotMessagesContact::class, DepotMessagesContactEloquent::class);

        // Tableau de bord du mentor (P4-2), borné à ses cours.
        $this->app->bind(DepotTableauDeBordMentor::class, DepotTableauDeBordMentorEloquent::class);
        $this->app->bind(DepotAnalytique::class, DepotAnalytiqueEloquent::class);
        $this->app->bind(DepotFicheDuCompte::class, DepotFicheDuCompteEloquent::class);
        $this->app->bind(DepotStatistiquesDuTableauDeBord::class, DepotStatistiquesDuTableauDeBordEloquent::class);

        // File de modération des contenus signalés. Elle existait en base
        // depuis des mois ; il lui manquait un lecteur.
        $this->app->bind(DepotSignalementsContenu::class, DepotSignalementsContenuEloquent::class);

        /*
         | Référentiel des sources (lot 5). Le seul dépôt que le MOTEUR
         | d'analyse consulte : il est lu à chaque analyse de lien, donc caché,
         | et écrit depuis le back-office, donc partagé. Deux des quatre
         | critères de `CLAUDE.md`, quand un seul suffirait.
         */
        $this->app->bind(DepotSourcesFiables::class, DepotSourcesFiablesEloquent::class);

        $this->app->bind(
            UnitLogRepositoryInterface::class,
            EloquentUnitLogRepository::class,
        );

        /*
         | Journal des points d'expérience. Même discipline que celui des
         | unités, et posé pour la même raison : un compteur sans journal ne
         | s'explique pas — on savait combien de points quelqu'un avait,
         | jamais d'où ils venaient.
         */
        $this->app->bind(
            XpLogRepositoryInterface::class,
            EloquentXpLogRepository::class,
        );

        /*
         | Le journal des unités s'ouvre à DEUX droits, au choix : celui de
         | l'économie (`ai_unit.view`) et celui de la sécurité
         | (`security.unit_logs`). Un responsable sécurité y arrive par le lien
         | de son propre groupe.
         |
         | Le middleware `can:` ne sait pas exprimer un OU — plusieurs `can:` se
         | cumulent en ET. La garde vivait donc DANS le composant, invisible du
         | routeur et de la barre latérale : le menu ne pouvait pas savoir s'il
         | fallait afficher le lien, et l'affichait à tout le monde.
         |
         | Une Gate nommée la rend déclarative. Le routeur la lit, le menu aussi.
         */
        Gate::define(
            'journal-des-unites',
            static fn (User $membre): bool => $membre->can('ai_unit.view')
                || $membre->can('security.unit_logs'),
        );

        /*
         | Base des faits tranchés par l'équipe. Lue avant CHAQUE analyse pour
         | éviter un appel payant : le dépôt met la lecture en cache, et
         | l'oublie à chaque écriture.
         */
        $this->app->bind(
            DepotFaitsVerifies::class,
            DepotFaitsVerifiesEloquent::class,
        );

        /*
         | Examens. Quatre dépôts parce qu'il y a quatre agrégats qui ne vivent
         | pas au même rythme : l'épreuve et ses sessions, la banque de
         | questions, l'enrôlement, les copies. Les réunir aurait donné une
         | interface que personne n'implémente sans en ignorer la moitié.
         */
        $this->app->bind(DepotExamens::class, DepotExamensEloquent::class);
        $this->app->bind(DepotBanqueQuestions::class, DepotBanqueQuestionsEloquent::class);
        $this->app->bind(DepotInscriptionsExamen::class, DepotInscriptionsExamenEloquent::class);
        $this->app->bind(DepotCopiesExamen::class, DepotCopiesExamenEloquent::class);

        /*
         | Fournisseur Mobile Money. « fake » tant qu'aucun contrat opérateur
         | n'est signé ; le simulateur refuse de lui-même de tourner en
         | production, où il créditerait des unités sans encaissement.
         */
        $this->app->bind(MomoProviderInterface::class, static function (): MomoProviderInterface {
            return match (config('economy.momo.driver')) {
                'fake' => new FakeMomoProvider,
                default => throw new RuntimeException(
                    'Fournisseur Mobile Money inconnu : '.(string) config('economy.momo.driver')
                ),
            };
        });
    }

    public function boot(): void
    {
        $this->gardeFousEloquent();
        $this->politiqueMotDePasse();
        $this->guardStaffSurLesRequetesLivewire();
        $this->ouRenvoyerQuelquUnDejaConnecte();
        $this->identiteLegaleSurLesPagesLegales();
        $this->partenairesSurLAccueil();
        $this->policyDesRolesSpatie();

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }

    /**
     * Les partenaires du carrousel de l'accueil (lot 9).
     *
     * ## Un composeur plutôt qu'un contrôleur
     *
     * L'accueil est servi par `Route::view` — délibérément : le commentaire de
     * la route dit qu'on ne met pas de calcul sur la page la plus visitée du
     * site pour n'en tirer aucun bénéfice. Le convertir en contrôleur pour six
     * logos irait contre cette décision.
     *
     * ## Caché une heure, et rien ne casse s'il échoue
     *
     * La table change quelques fois par an. Le cache lui évite une requête à
     * chaque visite de la page la plus vue du site.
     *
     * Le `try/catch` reprend le raisonnement de `SeoServiceProvider` pour les
     * campagnes : **un carrousel de logos ne vaut pas une page d'erreur**. Si la
     * base est indisponible, on n'affiche aucun partenaire et l'accueil
     * fonctionne. C'est aussi ce qui protège la page pendant une migration —
     * la même précaution que celle prise au lot 3 après qu'un réglage illisible
     * ait mis l'accueil en 500.
     */
    private function partenairesSurLAccueil(): void
    {
        View::composer('public.accueil', function (VueRendue $vue): void {
            try {
                $partenaires = Cache::remember(
                    'partenaires-accueil',
                    now()->addHour(),
                    static fn () => Partner::query()->publies()->get(),
                );
            } catch (Throwable $souci) {
                // Journalisé, pas silencieux : un carrousel vide pour toujours
                // doit laisser une trace quelque part.
                Log::warning('Partenaires indisponibles : '.$souci->getMessage());
                $partenaires = collect();
            }

            $vue->with('partenaires', $partenaires);
        });
    }

    /**
     * La Policy du modèle `Role`, qui ne peut PAS être découverte seule.
     *
     * Laravel devine `App\Policies\XPolicy` pour `App\Models\X`. `Role` vient de
     * `Spatie\Permission\Models\` : la convention ne s'applique pas, et sans
     * cette ligne `RolePolicy` ne serait jamais consultée.
     *
     * Le défaut serait silencieux et dans le mauvais sens : `can:viewAny,Role`
     * sans Policy tombe sur le Gate par défaut, qui REFUSE. On verrait un 403
     * sur l'écran des rôles et l'on chercherait la permission manquante — alors
     * qu'elle est bien accordée, mais que personne ne la lit.
     */
    private function policyDesRolesSpatie(): void
    {
        Gate::policy(SpatieRole::class, RolePolicy::class);
    }

    /**
     * `GuardStaffParDefaut` doit survivre aux requêtes Livewire.
     *
     * Livewire ne rejoue, sur ses appels `/livewire/update`, que les
     * middlewares déclarés persistants. `GuardStaffParDefaut` n'en faisait pas
     * partie : le premier affichage d'un écran du back-office passait par la
     * route et voyait donc le bon guard, mais la première action — publier,
     * supprimer — repartait sur le guard `web`, vide dans le back-office.
     * Toutes les Policies auraient répondu 403 à un administrateur légitime,
     * et seulement en navigateur : les tests Livewire, eux, positionnent le
     * guard eux-mêmes et n'auraient rien vu.
     */
    private function guardStaffSurLesRequetesLivewire(): void
    {
        Livewire::addPersistentMiddleware([GuardStaffParDefaut::class]);
    }

    /**
     * Politique de mot de passe — UNE seule définition, pour toute l'application.
     *
     * `Password::defaults()` n'était pas configuré : il retombait donc sur le
     * minimum de Laravel, HUIT caractères. L'inscription s'en tirait parce
     * qu'elle répétait la règle en dur ; tout écran faisant confiance à
     * `defaults()` — le changement de mot de passe, par exemple — aurait
     * silencieusement accepté des mots de passe plus faibles que ceux exigés à
     * l'inscription. Une règle de sécurité recopiée à deux endroits finit
     * toujours par diverger : elle vit désormais ici, et là seulement.
     *
     * `uncompromised()` interroge la base des fuites connues par k-anonymat —
     * seuls les cinq premiers caractères de l'empreinte sont transmis, jamais
     * le mot de passe.
     */
    private function politiqueMotDePasse(): void
    {
        Password::defaults(static fn (): Password => Password::min(12)
            ->mixedCase()
            ->numbers()
            ->symbols()
            ->uncompromised());
    }

    /**
     * Garde-fous Eloquent, actifs hors production.
     */
    /**
     * Porte l'identité de l'éditeur aux trois pages légales.
     *
     * Un composeur plutôt qu'une variable passée par la route : ces pages sont
     * servies par `Route::view`, qui ne calcule rien. Sans cela, il faudrait
     * leur donner un contrôleur chacune pour une donnée qu'elles partagent
     * toutes les trois.
     */
    private function identiteLegaleSurLesPagesLegales(): void
    {
        View::composer(
            ['public.mentions-legales', 'public.confidentialite', 'public.cookies', 'public.conditions-generales', 'public.supprimer-mon-compte'],
            function (VueRendue $vue): void {
                $vue->with('legal', $this->app->make(IdentiteLegale::class));
            },
        );
    }

    private function gardeFousEloquent(): void
    {
        $horsProduction = ! $this->app->isProduction();

        /*
         | Une affectation de masse portant sur un attribut non declare etait
         | jusqu'ici IGNOREE EN SILENCE : c'est ainsi que l'inscription creait
         | des comptes sans uuid, sans prenom et sans telephone, sans qu'aucune
         | erreur ne le signale. Desormais elle leve.
         */
        Model::preventSilentlyDiscardingAttributes($horsProduction);

        // Une relation non chargee leve au lieu de declencher une requete N+1
        // silencieuse qui ne se verra qu'en production.
        Model::preventLazyLoading($horsProduction);
    }

    /**
     * Où atterrit quelqu'un qui rouvre l'écran de connexion en étant connecté.
     *
     * Laravel renverrait à l'accueil public. Ce n'est pas faux, mais ce n'est
     * pas où la personne voulait aller : elle a cliqué « Connexion » parce
     * qu'elle cherchait son espace. On l'y emmène.
     *
     * Le staff et le citoyen n'atterrissent pas au même endroit — un
     * administrateur renvoyé sur le tableau de bord citoyen croirait avoir
     * perdu ses droits.
     */
    private function ouRenvoyerQuelquUnDejaConnecte(): void
    {
        RedirectIfAuthenticated::redirectUsing(static function (Request $requete): string {
            if (Auth::guard('staff')->check()) {
                return route(UserRole::Admin->routeApresConnexion());
            }

            $utilisateur = Auth::guard('web')->user();

            if ($utilisateur === null) {
                return route('public.home');
            }

            foreach (UserRole::cases() as $role) {
                if ($utilisateur->hasRole($role->value)) {
                    return route($role->routeApresConnexion());
                }
            }

            return route(UserRole::Citoyen->routeApresConnexion());
        });
    }
}
