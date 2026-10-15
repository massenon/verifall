<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Adapters;

use App\Core\Application\Contracts\DepotSourcesFiables;
use App\Core\FactChecking\Contracts\AnalysisAdapterInterface;
use App\Core\FactChecking\Services\CleRefusee;
use App\Core\FactChecking\Services\QuotaEpuise;
use App\Core\FactChecking\ValueObjects\SacDePreuves;
use App\Core\FactChecking\ValueObjects\VerdictMoteur;
use App\Enums\MoteurAnalyse;
use App\Enums\TypeContenu;
use App\Enums\VerdictFactCheck;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Socle commun des adaptateurs qui appellent un service distant.
 *
 * Il tient à lui seul la promesse du contrat : `analyse()` NE LÈVE JAMAIS.
 * Quoi qu'il arrive — clé absente, service éteint, JSON malformé, exception
 * inattendue — la méthode rend un `VerdictMoteur`. Sans cette garantie, un
 * fournisseur en panne ferait tomber l'analyse entière alors que onze autres
 * moteurs ont répondu, et la personne perdrait ses unités pour rien.
 *
 * Les classes filles n'implémentent que `executer()`, où elles peuvent lever
 * librement.
 */
abstract class AdaptateurHttp implements AnalysisAdapterInterface
{
    public function supporte(TypeContenu $type): bool
    {
        return in_array($this->nom(), MoteurAnalyse::pour($type), true);
    }

    public function coutUnites(): int
    {
        return 0;
    }

    final public function analyse(SacDePreuves $entree): VerdictMoteur
    {
        $debut = microtime(true);

        if (! $this->configure()) {
            /*
             | Clé absente : le moteur s'abstient, il n'échoue pas bruyamment.
             | Une installation sans clé DeepSeek doit fonctionner avec les
             | autres moteurs, pas afficher une erreur à chaque analyse.
             */
            return VerdictMoteur::echec($this->nom(), 'Moteur non configuré', $this->latence($debut));
        }

        try {
            $resultat = $this->executer($entree);

            return $resultat ?? VerdictMoteur::echec(
                $this->nom(), 'Aucune réponse exploitable', $this->latence($debut),
            );
        } catch (QuotaEpuise) {
            return VerdictMoteur::echec($this->nom(), 'Quota atteint', $this->latence($debut));
        } catch (Throwable $erreur) {
            /*
             | Le message d'exception ne remonte PAS dans le verdict : il peut
             | contenir une URL de service, un identifiant de compte, voire une
             | clé recopiée par une bibliothèque tierce. Il va au journal, où
             | son accès est contrôlé ; le rapport ne reçoit qu'un motif neutre.
             */
            Log::error('Adaptateur en échec', [
                'moteur' => $this->nom()->value,
                'erreur' => $erreur->getMessage(),
            ]);

            return VerdictMoteur::echec($this->nom(), 'Erreur technique du moteur', $this->latence($debut));
        }
    }

    /**
     * Le travail réel. Peut lever : le socle rattrape.
     */
    abstract protected function executer(SacDePreuves $entree): ?VerdictMoteur;

    /** La configuration nécessaire est-elle présente ? */
    abstract protected function configure(): bool;

    /*
    |---------------------------------------------------------------------------
    | Dire la vérité sur son propre état
    |---------------------------------------------------------------------------
    |
    | `RegistreDesMoteurs::etat()` répondait « disponible » dès qu'une classe
    | d'adaptateur existait. Sur la configuration du 1er septembre 2026, cela
    | annonçait Perplexity opérationnel avec une clé VIDE, et Google Fact Check
    | opérationnel avec une clé que Google refuse. La carte « Santé du système »
    | aurait affiché du vert sur deux moteurs morts.
    |
    | Les deux méthodes ci-dessous séparent les deux seules questions qui ont
    | une réponse vérifiable : la clé est-elle là, et le fournisseur l'accepte-t-il.
    |
    */

    /** La clé est-elle renseignée ? Question locale, sans appel réseau. */
    public function estConfigure(): bool
    {
        return $this->configure();
    }

    /**
     * Ce que la dernière sonde a compris du refus.
     *
     * Rempli par {@see joignable()}, lu par {@see precision()}. Jamais une clé :
     * un état de moteur se lit à l'écran « Santé du système », se recopie dans
     * un courriel au support et se colle dans un ticket.
     */
    private ?string $motifDeLaSonde = null;

    /**
     * Un mot de plus, quand l'état seul ne suffit pas à décider.
     *
     * Deux usages, et ils se complètent. Gemini le surcharge pour dire COMBIEN
     * de ses clés sont acceptées — « 5 clés sur 5 » et « 1 clé sur 5 » sont
     * tous deux « opérationnel », et pourtant le second annonce une panne pour
     * l'après-midi. Le socle, lui, y met le motif du dernier refus : « clé
     * refusée » et « injoignable » n'appellent pas le même geste.
     */
    public function precision(): ?string
    {
        return $this->motifDeLaSonde;
    }

    /**
     * Point d'appel de CONTRÔLE : gratuit, en lecture seule, sans effet de bord.
     *
     * `null` quand le fournisseur n'en expose pas — mieux vaut avouer qu'on ne
     * sait pas que déclencher une requête facturée pour peindre une pastille.
     *
     * @return array{0: string, 1: array<string, string>}|null URL, puis en-têtes
     */
    protected function sonde(): ?array
    {
        return null;
    }

    /**
     * Le fournisseur accepte-t-il notre clé, à l'instant ?
     *
     * `null` signifie « pas vérifiable », et se distingue de `false` qui
     * signifie « vérifié, et refusé ». Confondre les deux ferait passer un
     * moteur sain pour une panne.
     *
     * Le délai est court exprès : une sonde n'est pas une analyse, et elle
     * s'exécute pendant qu'un écran attend.
     */
    public function joignable(): ?bool
    {
        if (! $this->configure()) {
            return null;
        }

        $sonde = $this->sonde();

        if ($sonde === null) {
            return null;
        }

        [$url, $entetes] = $sonde;
        $this->motifDeLaSonde = null;

        try {
            $reponse = Http::timeout(8)->withHeaders($entetes)->get($url);

            if ($reponse->successful()) {
                return true;
            }

            /*
             | « Le fournisseur refuse la clé OU ne répond pas » : c'est ce que
             | disait le diagnostic, et les deux cas n'appellent pas du tout le
             | même geste. Le premier se règle en régénérant une clé, le second
             | en ouvrant une sortie réseau ou en attendant.
             |
             | Le 10 septembre 2026, trois moteurs Google sortaient
             | « Injoignable » sans qu'on sache lequel des deux c'était. Ils
             | rendaient en réalité `400 API_KEY_INVALID` — une clé morte,
             | partagée par les trois, et personne ne pouvait le lire.
             */
            $this->motifDeLaSonde = $this->motifDuRefus($reponse);

            return false;
        } catch (Throwable $souci) {
            // Une sonde qui lève est une sonde qui répond non : le contrat de
            // l'adaptateur interdit de laisser remonter quoi que ce soit.
            $this->motifDeLaSonde = 'injoignable ('.class_basename($souci).')';

            return false;
        }
    }

    /**
     * Le refus, dit en clair — et sans jamais recopier la clé.
     *
     * Google est le cas qui a motivé cette méthode : il rend **400**, et non
     * 401, pour une clé invalide. Aucune garde ne s'en saisissait, et l'erreur
     * ressemblait à n'importe quel autre échec.
     */
    protected function motifDuRefus(Response $reponse): string
    {
        $corps = $reponse->json();

        $statut = data_get($corps, 'error.status')
            ?? data_get($corps, 'error.errors.0.reason')
            ?? data_get($corps, 'error.code');

        $message = (string) (data_get($corps, 'error.message') ?? data_get($corps, 'message') ?? '');

        if ($this->cleRefusee($reponse)) {
            return 'clé refusée'.(is_string($statut) && $statut !== '' ? ' ('.$statut.')' : '');
        }

        return sprintf(
            'HTTP %d%s',
            $reponse->status(),
            $message === '' ? '' : ' — '.mb_substr($message, 0, 90),
        );
    }

    /**
     * Le fournisseur refuse-t-il la CLÉ, plutôt que la requête ?
     *
     * 401 et 403 sont les codes attendus. Google, lui, rend **400** avec
     * `API_KEY_INVALID` — un code qui sert par ailleurs à toutes les requêtes
     * mal formées. On ne peut donc pas déduire du seul 400 qu'une clé est
     * morte : il faut le motif, sans quoi une requête maladroite ferait mettre
     * une clé saine au repos pour six heures.
     */
    protected function cleRefusee(Response $reponse): bool
    {
        if (in_array($reponse->status(), [401, 403], true)) {
            return true;
        }

        if ($reponse->status() !== 400) {
            return false;
        }

        $corps = mb_strtolower((string) $reponse->body());

        return str_contains($corps, 'api_key_invalid')
            || str_contains($corps, 'api key not valid')
            || str_contains($corps, 'api key not found');
    }

    protected function latence(float $debut): int
    {
        return (int) ((microtime(true) - $debut) * 1000);
    }

    protected function delai(): int
    {
        return (int) config('factcheck.runtime.timeout_seconds', 60);
    }

    /**
     * Traduit un refus du fournisseur.
     *
     * 429 et 403 signifient « pas avec cette clé, pas maintenant » : ils
     * déclenchent la rotation. Les autres codes sont des échecs ordinaires,
     * qu'il ne sert à rien de rejouer avec une autre clé.
     *
     * @throws QuotaEpuise
     */
    protected function refuserSiQuota(Response $reponse): void
    {
        /*
         | 401/403 et 429 étaient traités ensemble. Ils n'ont rien à voir, et la
         | confusion coûtait cher en silence :
         |
         |   · 429 — quota épuisé. Il se RECHARGE. Reposer la clé quelques
         |     minutes puis la reprendre est la bonne conduite.
         |   · 401/403 — la clé est refusée. Elle ne se rechargera JAMAIS :
         |     révoquée, mal recopiée, ou sans les droits demandés. La reposer
         |     cinq minutes revenait à la réessayer indéfiniment, toutes les cinq
         |     minutes, en la comptant comme une clé saine dans la rotation.
         |
         | La documentation de Gemini est explicite : « Do not retry on client
         | errors (like 400 or 403) as they indicate issues like invalid API
         | keys. »
         */
        if ($this->cleRefusee($reponse)) {
            throw new CleRefusee;
        }

        /*
         | 402 — « Payment Required ».
         |
         | Winston AI le rend quand les CRÉDITS sont épuisés, et il n'était
         | couvert nulle part : la réponse tombait dans l'échec ordinaire, la
         | clé restait considérée comme saine, et chaque analyse suivante
         | repayait un aller-retour pour se faire refuser de la même façon.
         |
         | Rangé avec le quota et non avec la clé refusée : des crédits se
         | rechargent — on les rachète — là où une clé révoquée ne revient pas.
         */
        if (in_array($reponse->status(), [402, 429], true)) {
            throw new QuotaEpuise;
        }
    }

    /**
     * Le modèle demandé n'existe pas — 404.
     *
     * Cas nommé parce qu'il est le plus insidieux de tous : rien ne le
     * signalait. Le 404 ne figurait dans aucune liste, `successful()` valait
     * faux, la boucle passait au modèle suivant puis à la clé suivante, où le
     * même 404 se reproduisait, et l'adaptateur rendait « rien » sans une ligne
     * de journal.
     *
     * C'est exactement l'état du modèle de repli `gemini-1.5-flash`, retiré du
     * catalogue : il n'a jamais pris le relais et ne pouvait pas le prendre.
     * Une panne muette est plus coûteuse qu'une panne bruyante — elle se
     * découvre des semaines plus tard, sur une réclamation.
     */
    protected function signalerSiModeleInconnu(Response $reponse, string $modele): void
    {
        if ($reponse->status() !== 404) {
            return;
        }

        Log::error('Modèle inconnu du fournisseur', [
            'moteur' => $this->nom()->value,
            'modele' => $modele,
            'indice' => 'Le modèle a probablement été retiré du catalogue. Vérifiez la configuration.',
        ]);
    }

    /**
     * Lit un verdict rendu en toutes lettres.
     *
     * Tolérante par nécessité : les modèles varient d'une version à l'autre et
     * rendent « FAUX », « false », « faux ». Un parseur strict transformerait
     * chaque mise à jour de modèle en panne silencieuse. Ce qui n'est pas
     * reconnu devient « non vérifiable » — jamais un verdict par défaut.
     */
    protected function lireVerdict(?string $brut): ?VerdictFactCheck
    {
        $normalise = mb_strtolower(trim((string) $brut));

        return match (true) {
            $normalise === '' => null,
            str_contains($normalise, 'trompeur'), str_contains($normalise, 'misleading'),
            str_contains($normalise, 'partiel') => VerdictFactCheck::Trompeur,
            str_contains($normalise, 'vrai'), str_contains($normalise, 'true'),
            str_contains($normalise, 'exact') => VerdictFactCheck::Vrai,
            str_contains($normalise, 'faux'), str_contains($normalise, 'false'),
            str_contains($normalise, 'incorrect') => VerdictFactCheck::Faux,
            default => VerdictFactCheck::NonVerifiable,
        };
    }

    /**
     * Extrait un objet JSON d'une réponse de modèle.
     *
     * Les modèles enrobent volontiers leur JSON dans un bloc markdown malgré
     * la consigne. Le retirer coûte deux lignes ; ne pas le faire coûte un
     * moteur qui échoue une fois sur trois.
     *
     * @return array<string, mixed>|null
     */
    protected function lireJson(?string $brut): ?array
    {
        $texte = trim((string) $brut);
        $texte = (string) preg_replace('/^```(?:json)?\s*/i', '', $texte);
        $texte = (string) preg_replace('/\s*```$/', '', $texte);

        $decode = json_decode(trim($texte), true);

        return is_array($decode) ? $decode : null;
    }

    /** Confiance bornée : un modèle rend parfois 120, ou -5. */
    /**
     * La fiabilité d'une source citée — le référentiel d'abord, l'heuristique après.
     *
     * ## Ce que cette méthode referme
     *
     * `trusted_sources` existait, la rédaction pouvait y noter chaque source
     * depuis le back-office… et **aucun adaptateur ne lisait cette note**. Le
     * commentaire de `GoogleCustomSearchAdapter::fiabilite()` l'annonçait
     * pourtant depuis l'origine : « elle sera remplacée par la table
     * `trusted_sources` du back-office, où une rédaction pourra être notée à la
     * main. En attendant… ». Cet « en attendant » a duré, et le référentiel est
     * passé de 22 à 408 fiches sans que rien ne change au score.
     *
     * Concrètement, avant : `lemonde.fr` valait 55 comme un blog anonyme, et
     * amender une crédibilité dans l'administration n'avait **aucun effet** sur
     * une vérification. Le seul adaptateur à consulter le référentiel,
     * `AnalyseTechniqueAdapter`, s'en servait pour écrire une phrase — pas pour
     * pondérer.
     *
     * ## Pourquoi le référentiel l'emporte, et pourquoi le défaut survit
     *
     * Une fiche du référentiel est une **décision éditoriale** ; l'heuristique
     * est une supposition sur la forme d'un nom de domaine. Quand les deux
     * parlent, la décision gagne.
     *
     * Mais elle ne parle que des domaines qu'elle connaît. Pour tous les autres
     * — l'immense majorité — le défaut de l'appelant est rendu tel quel : aucun
     * score ne bouge pour une source non référencée, et rien de ce qui marchait
     * hier ne change.
     */
    protected function fiabiliteDeLaSource(string $url, int $defaut): int
    {
        $hote = parse_url($url, PHP_URL_HOST);

        if (! is_string($hote) || $hote === '') {
            return $defaut;
        }

        $source = app(DepotSourcesFiables::class)->trouverParDomaine($hote);

        return $source?->credibility ?? $defaut;
    }

    protected function confiance(mixed $brut, int $defaut = 50): int
    {
        return max(0, min(100, (int) (is_numeric($brut) ? $brut : $defaut)));
    }
}
