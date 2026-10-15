<?php

declare(strict_types=1);

namespace App\Core\Marketing\Services;

use App\Core\Application\Reglages\Reglages;
use App\Core\Economy\Services\EconomyService;
use App\Enums\MotifMouvementUnite;
use App\Enums\StatutParrainage;
use App\Models\Referral;
use App\Models\User;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Parrainage — code personnel, suivi, récompenses.
 *
 * La récompense n'arrive PAS à l'inscription mais à la première vérification du
 * filleul. La raison est simple : payer l'inscription seule, c'est payer les
 * faux comptes. Le prototype le dit dans ses mêmes termes — « lorsque votre
 * filleul réalise sa première vérification éligible ».
 */
final class Parrainage
{
    /**
     * Le barème DE REPLI, en unités du produit.
     *
     * ## Ce ne sont plus les montants versés
     *
     * Ils l'étaient : 100 et 50, écrits ici, modifiables par un développeur et
     * par lui seul — pour une décision commerciale qui appartient au porteur du
     * projet. Le barème vit désormais aux réglages
     * (`referral_referrer_units` · `referral_referred_units`, onglet Économie),
     * à **10 et 5** par défaut.
     *
     * ## Pourquoi ces constantes SURVIVENT
     *
     * Trois raisons, et la première suffirait. Une lecture de réglage suppose
     * la base joignable et la table `settings` migrée ; ces constantes rendent
     * le service utilisable hors requête HTTP — dans un test unitaire, une
     * commande, un seeder — sans que rien ne s'effondre.
     *
     * Elles nomment ensuite ce que le code attend : « `nombre($cle, 10)` »
     * cache un chiffre magique, `POINTS_PARRAIN` ne le cache pas.
     *
     * Elles restent enfin le dernier filet si quelqu'un vide la ligne en base :
     * le catalogue rend alors son défaut, et ces constantes le doublent.
     *
     * **Elles ne doivent donc jamais être lues directement pour verser.**
     * Passer par `unitesParrain()` et `unitesFilleul()`.
     */
    public const POINTS_PARRAIN = 10;

    public const POINTS_FILLEUL = 5;

    /** Clés du catalogue — nommées ici pour n'exister qu'à un endroit. */
    public const CLE_UNITES_PARRAIN = 'referral_referrer_units';

    public const CLE_UNITES_FILLEUL = 'referral_referred_units';

    /**
     * Nombre de filleuls récompensés par palier.
     *
     * Sert uniquement à montrer une progression : « 2 / 3 invitations
     * éligibles ». Aucun bonus automatique n'y est encore attaché — la barre
     * dit donc où l'on en est, pas ce qu'on gagnera.
     */
    public const PALIER = 3;

    /**
     * Longueur de la partie aléatoire du code.
     *
     * Le code est PUBLIC et se dicte : quatre caractères sur un alphabet réduit
     * suffisent à éviter les collisions à l'échelle visée, sans rendre le code
     * pénible à recopier.
     */
    private const LONGUEUR_ALEATOIRE = 4;

    /** Sans O/0, I/1, S/5 : un code se lit à voix haute. */
    private const ALPHABET = 'ABCDEFGHJKLMNPQRTUVWXYZ2346789';

    public function __construct(
        private readonly EconomyService $economie,
        private readonly Reglages $reglages,
    ) {}

    /**
     * Les unités versées au parrain, telles qu'elles sont réglées aujourd'hui.
     *
     * Publique, et c'est le point : les écrans qui ANNONCENT le barème doivent
     * lire la même valeur que le code qui le VERSE. Une page d'accueil promettant
     * 100 unités pendant que le service en crédite 10 est pire qu'un bug — c'est
     * une promesse non tenue, sur un programme de recommandation.
     */
    public function unitesParrain(): int
    {
        return $this->reglage(self::CLE_UNITES_PARRAIN, self::POINTS_PARRAIN);
    }

    public function unitesFilleul(): int
    {
        return $this->reglage(self::CLE_UNITES_FILLEUL, self::POINTS_FILLEUL);
    }

    /**
     * Un montant du barème, qui NE LÈVE JAMAIS.
     *
     * ## Pourquoi ce `try` n'est pas de la paranoïa
     *
     * Ces deux montants sont affichés par la PAGE D'ACCUEIL, la plus visitée du
     * site. Avant ce lot ils venaient d'une constante : zéro requête, aucun
     * risque. Les faire venir des réglages a introduit une dépendance à la base
     * sur ce chemin-là — et une exception y mettrait le site entier à terre
     * pour un chiffre promotionnel.
     *
     * Le cas s'est présenté immédiatement, dans la suite de tests : un test qui
     * ne migre pas la base rendait la page d'accueil en 500, sur
     * « no such table: settings ». En production, la même chose arriverait
     * pendant une migration, ou avant que la table n'existe sur un déploiement
     * neuf.
     *
     * Le service suit donc ici la conduite que `Reglages::dechiffrer()` tient
     * déjà pour les secrets : on traite le réglage comme absent plutôt que de
     * laisser filer. C'est très exactement le rôle de repli annoncé par les
     * constantes ci-dessus — encore fallait-il que le code le tienne.
     */
    private function reglage(string $cle, int $repli): int
    {
        try {
            return $this->reglages->nombre($cle, $repli);
        } catch (Throwable) {
            return $repli;
        }
    }

    /**
     * Le code de quelqu'un, créé à la demande.
     *
     * Créé PARESSEUSEMENT plutôt qu'à l'inscription : la grande majorité des
     * comptes ne parraine jamais personne, et générer un code pour chacun
     * remplirait la table d'identifiants inutiles — tout en obligeant à une
     * migration de rattrapage pour les comptes existants.
     */
    public function codeDe(User $utilisateur): string
    {
        if ($utilisateur->referral_code !== null && $utilisateur->referral_code !== '') {
            return $utilisateur->referral_code;
        }

        $code = $this->codeLibre($utilisateur->name);

        $utilisateur->forceFill(['referral_code' => $code])->save();

        return $code;
    }

    public function lienDe(User $utilisateur): string
    {
        return route('public.invitation', ['code' => $this->codeDe($utilisateur)]);
    }

    /**
     * QR code du lien d'invitation, en SVG.
     *
     * Rendu côté SERVEUR et non dessiné dans un canvas : un QR généré en
     * JavaScript ne s'imprime pas, ne se copie pas dans un message, et
     * disparaît quand le script échoue. En SVG il reste net à toute taille —
     * un QR flou ne se scanne pas.
     */
    public function qrCode(User $utilisateur, int $taille = 220): string
    {
        $writer = new Writer(new ImageRenderer(new RendererStyle($taille), new SvgImageBackEnd));

        return $writer->writeString($this->lienDe($utilisateur));
    }

    /** Le parrain derrière un code, s'il existe. */
    public function parrainDuCode(string $code): ?User
    {
        $code = mb_strtoupper(trim($code));

        return $code === '' ? null : User::where('referral_code', $code)->first();
    }

    /**
     * Quelqu'un a ouvert une invitation.
     *
     * Un simple compteur, aucune donnée personnelle : c'est la seule mesure
     * honnête de « combien de personnes ont vu mon invitation ». Compter les
     * envois supposerait de suivre chaque partage WhatsApp, ce que personne ne
     * peut faire.
     */
    public function compterUneVisite(User $parrain): void
    {
        $parrain->increment('referral_visits');
    }

    /**
     * Rattache un nouveau compte à son parrain.
     *
     * Refuse le rattachement à soi-même — un lien ouvert dans son propre
     * navigateur puis une inscription, et quelqu'un se parrainerait lui-même.
     */
    public function rattacher(User $filleul, string $code): ?Referral
    {
        $parrain = $this->parrainDuCode($code);

        if ($parrain === null || $parrain->id === $filleul->id) {
            return null;
        }

        return DB::transaction(function () use ($parrain, $filleul): ?Referral {
            // Un compte n'est le filleul que d'une seule personne. La
            // contrainte le garantit ; ce test évite de lui faire lever une
            // exception pendant une inscription.
            if (Referral::where('referred_id', $filleul->id)->exists()) {
                return null;
            }

            return Referral::create([
                'uuid' => (string) Str::uuid(),
                'referrer_id' => $parrain->id,
                'referred_id' => $filleul->id,
                'status' => StatutParrainage::Inscrit->value,
                'registered_at' => now(),
            ]);
        });
    }

    /**
     * Le filleul vient de faire son action éligible.
     *
     * Idempotent : appelé à chaque vérification, il ne récompense qu'à la
     * première. Sans cela, un filleul assidu enrichirait son parrain à chaque
     * analyse.
     */
    public function honorer(User $filleul): ?Referral
    {
        return DB::transaction(function () use ($filleul): ?Referral {
            $parrainage = Referral::where('referred_id', $filleul->id)
                ->whereNull('qualified_at')
                ->lockForUpdate()
                ->first();

            if ($parrainage === null) {
                return null;
            }

            $parrain = $parrainage->referrer;

            if ($parrain === null) {
                // Le parrain a fermé son compte : le filleul garde tout de même
                // son avantage, il n'y est pour rien.
                $parrainage->forceFill([
                    'status' => StatutParrainage::Eligible->value,
                    'qualified_at' => now(),
                ])->save();

                return $parrainage->refresh();
            }

            /*
             | Le barème est lu UNE FOIS, ici, et les deux montants sont écrits
             | sur la ligne de parrainage.
             |
             | C'est ce qui rend le réglage sûr à modifier : `referrer_points`
             | et `referred_points` HISTORISENT ce qui a réellement été versé.
             | Baisser le barème demain ne réécrit pas le passé, et les
             | indicateurs de l'écran « Mon parrainage » — qui somment ces
             | colonnes — continuent de dire la vérité.
             |
             | Relire le réglage à l'affichage, au lieu de la colonne, ferait
             | varier un total déjà acquis à chaque changement de tarif.
             */
            $unitesParrain = $this->unitesParrain();
            $unitesFilleul = $this->unitesFilleul();

            $this->economie->crediter(
                $parrain, $unitesParrain, MotifMouvementUnite::Parrainage,
                'Parrainage — '.$parrainage->nomDuFilleul(), $parrainage,
            );

            $this->economie->crediter(
                $filleul, $unitesFilleul, MotifMouvementUnite::Parrainage,
                'Bienvenue — parrainé par '.$parrain->name, $parrainage,
            );

            $parrainage->forceFill([
                'status' => StatutParrainage::Recompense->value,
                'qualified_at' => now(),
                'rewarded_at' => now(),
                'referrer_points' => $unitesParrain,
                'referred_points' => $unitesFilleul,
            ])->save();

            return $parrainage->refresh();
        });
    }

    /**
     * Le parrainage vu par le FILLEUL, ou null s'il n'a pas été parrainé.
     *
     * Cette lecture manquait entièrement. L'écran « Mon Parrainage » ne
     * montrait que le versant parrain : quelqu'un arrivé par le lien d'un ami
     * n'y trouvait aucune trace de ce qui lui avait été promis, ni de ce qui
     * lui restait à faire pour l'obtenir.
     *
     | C'est le côté qui compte le plus au départ, parce que c'est celui qui a
     | encore quelque chose à faire : le parrain, lui, n'a plus qu'à attendre.
     */
    public function vuDuFilleul(User $filleul): ?Referral
    {
        return Referral::query()
            ->with('referrer:id,name')
            ->where('referred_id', $filleul->id)
            ->first();
    }

    /**
     * Indicateurs de la page.
     *
     * @return array{visites: int, inscrits: int, actifs: int, points: int}
     */
    public function indicateurs(User $parrain): array
    {
        $base = Referral::where('referrer_id', $parrain->id);

        return [
            'visites' => (int) $parrain->referral_visits,
            'inscrits' => (clone $base)->count(),
            'actifs' => (clone $base)->whereNotNull('qualified_at')->count(),
            'points' => (int) (clone $base)->sum('referrer_points'),
        ];
    }

    /**
     * Un code libre, vérifié en base.
     *
     * Préfixé du prénom en majuscules, comme le prototype (« ADDERLY7X9 ») :
     * un code reconnaissable se dicte et se retient, là où huit caractères
     * aléatoires se recopient de travers.
     */
    private function codeLibre(string $nom): string
    {
        $prefixe = mb_strtoupper(Str::ascii(Str::before(trim($nom), ' ')));
        $prefixe = preg_replace('/[^A-Z]/', '', $prefixe) ?: 'VERIF';
        $prefixe = mb_substr($prefixe, 0, 10);

        do {
            $code = $prefixe.$this->suffixe();
        } while (User::where('referral_code', $code)->exists());

        return $code;
    }

    private function suffixe(): string
    {
        $sortie = '';
        $max = strlen(self::ALPHABET) - 1;

        for ($i = 0; $i < self::LONGUEUR_ALEATOIRE; $i++) {
            $sortie .= self::ALPHABET[random_int(0, $max)];
        }

        return $sortie;
    }
}
