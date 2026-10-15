<?php

declare(strict_types=1);

namespace App\Core\Identity\Services;

use App\Core\Identity\Notifications\DossierKycDepose;
use App\Core\Identity\Notifications\DossierKycTranche;
use App\Enums\StatutDossierKyc;
use App\Enums\TypePieceIdentite;
use App\Models\KycSubmission;
use App\Models\User;
use App\Support\Hashing\IdentityHasher;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * La chaîne documentaire KYC, de bout en bout.
 *
 * Décidée par le porteur du projet le 1er septembre 2026, en remplacement de la
 * demi-mesure du lot 7 : un drapeau `users.kyc_verified_at` que seule
 * l'administration pouvait lever, sans que personne puisse rien déposer.
 *
 * ## Les quatre règles qui tiennent ce service
 *
 * 1. **Aucune pièce n'est servie par une URL de stockage.** Disque privé, nom
 *    aléatoire, et un contrôleur qui vérifie signature, session ET habilitation.
 * 2. **Le numéro n'est jamais conservé en clair** — HMAC seulement, pour
 *    reconnaître une pièce déjà présentée par un autre compte.
 * 3. **Les pièces s'effacent** {@see self::RETENTION_JOURS} jours après la
 *    décision. Ce qui reste prouve le contrôle sans garder le scan.
 * 4. **Un refus porte toujours un motif**, et il est montré au citoyen : un
 *    refus muet fait redéposer la même photo illisible.
 */
final class DossiersKyc
{
    /**
     * Trente jours après la décision, les images sont détruites.
     *
     * Assez pour qu'un refus se conteste et se réexamine sur pièces ; trop
     * court pour constituer une réserve de cartes d'identité. C'est la purge
     * RGPD déjà planifiée (`verifall:purger-donnees-expirees`, toutes les
     * heures) qui l'exécute — aucun nouveau mécanisme.
     */
    public const RETENTION_JOURS = 30;

    /** Le disque privé. Jamais `public` : ces images ne doivent avoir aucune URL. */
    private const DISQUE = 'local';

    private const DOSSIER = 'kyc';

    /** 6 Mo : une photo de téléphone passe, un PDF de dossier scanné non. */
    public const TAILLE_MAXIMUM_KO = 6144;

    public function __construct(
        private readonly IdentityHasher $empreintes,
        private readonly AuditLogService $journal,
    ) {}

    /**
     * Dépose un dossier.
     *
     * ## Un seul dossier ouvert à la fois
     *
     * Verrou de transaction : deux envois du formulaire — un double-clic
     * suffit — créeraient deux dossiers, et deux examinateurs regarderaient les
     * mêmes pièces sans le savoir.
     *
     * Un compte DÉJÀ vérifié ne redépose pas : il n'y a rien à examiner, et
     * accepter la demande ferait croire à un contrôle en cours.
     *
     * @throws RuntimeException
     */
    public function deposer(
        User $citoyen,
        TypePieceIdentite $type,
        string $numero,
        UploadedFile $recto,
        ?UploadedFile $verso = null,
        ?UploadedFile $selfie = null,
    ): KycSubmission {
        if ($citoyen->identiteVerifiee()) {
            throw new RuntimeException('Votre identité est déjà vérifiée.');
        }

        if ($type->exigeLeVerso() && $verso === null) {
            throw new RuntimeException(
                'Le verso de '.$type->libelle().' est nécessaire : il porte les informations '
                .'que le recto ne montre pas.'
            );
        }

        $numero = trim($numero);

        if ($numero === '') {
            throw new RuntimeException('Le numéro de la pièce est nécessaire.');
        }

        $dossier = DB::transaction(function () use ($citoyen, $type, $numero, $recto, $verso, $selfie): KycSubmission {
            $ouvert = KycSubmission::query()
                ->where('user_id', $citoyen->getKey())
                ->enAttente()
                ->lockForUpdate()
                ->exists();

            if ($ouvert) {
                throw new RuntimeException(
                    'Un dossier est déjà en cours d’examen. Vous serez prévenu de la décision.'
                );
            }

            $dossier = KycSubmission::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $citoyen->getKey(),
                'document_type' => $type->value,
                // HMAC, jamais le numéro : il ne sert qu'à reconnaître une
                // pièce déjà présentée ailleurs.
                'document_number_hash' => $this->empreintes->hash(mb_strtoupper($numero)),
                'front_path' => $this->ranger($recto),
                'back_path' => $verso !== null ? $this->ranger($verso) : null,
                'selfie_path' => $selfie !== null ? $this->ranger($selfie) : null,
                'status' => StatutDossierKyc::EnAttente->value,
            ]);

            $this->journal->record(
                action: 'kyc.depot',
                module: 'identity',
                acteur: $citoyen,
                cible: $dossier,
                // Aucune donnée de la pièce au journal : le journal est lu par
                // du personnel qui n'a pas à connaître le numéro.
                apres: ['type' => $type->value],
            );

            return $dossier;
        });

        $this->prevenirLesExaminateurs();

        return $dossier;
    }

    /**
     * Prévient les personnes habilitées à examiner un dossier.
     *
     * Sur la PERMISSION `kyc.review`, jamais sur un rôle : la matrice peut
     * évoluer, et une liste bâtie sur « Admin » se désynchroniserait le jour où
     * le périmètre change. Sans cette notification, la file d'examen n'aurait
     * aucun moyen de se faire remarquer, et un dossier attendrait des semaines.
     */
    private function prevenirLesExaminateurs(): void
    {
        User::query()
            ->permission('kyc.review')
            ->whereNull('anonymized_at')
            ->get()
            ->each(fn (User $examinateur) => $examinateur->notify(new DossierKycDepose));
    }

    /** Le dossier le plus récent d'un compte, s'il y en a un. */
    public function dernierDossier(User $citoyen): ?KycSubmission
    {
        return KycSubmission::query()
            ->where('user_id', $citoyen->getKey())
            ->latest('id')
            ->first();
    }

    /**
     * Approuve un dossier : le drapeau se lève, et il n'est plus posé à la main.
     *
     * @throws RuntimeException
     */
    public function approuver(KycSubmission $dossier, User $examinateur): void
    {
        $this->refuserSiDejaTranche($dossier);

        DB::transaction(function () use ($dossier, $examinateur): void {
            $dossier->forceFill([
                'status' => StatutDossierKyc::Approuve->value,
                'reviewed_by' => $examinateur->getKey(),
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ])->save();

            $dossier->citoyen?->forceFill(['kyc_verified_at' => now()])->save();
        });

        $this->journal->record(
            action: 'kyc.approuve',
            module: 'identity',
            acteur: $examinateur,
            cible: $dossier,
            apres: ['citoyen' => $dossier->citoyen?->uuid],
        );

        $dossier->citoyen?->notify(new DossierKycTranche($dossier->fresh()));
    }

    /**
     * Refuse un dossier, avec un motif OBLIGATOIRE.
     *
     * Le motif est montré au citoyen tel quel. C'est ce qui distingue un refus
     * utile — « la photo du verso est floue » — d'un refus qui fait
     * redéposer exactement la même chose.
     *
     * @throws RuntimeException
     */
    public function refuser(KycSubmission $dossier, User $examinateur, string $motif): void
    {
        $this->refuserSiDejaTranche($dossier);

        $motif = trim($motif);

        if (mb_strlen($motif) < 10) {
            throw new RuntimeException(
                'Expliquez le refus en une phrase : sans motif, la personne redéposera la même pièce.'
            );
        }

        $dossier->forceFill([
            'status' => StatutDossierKyc::Refuse->value,
            'reviewed_by' => $examinateur->getKey(),
            'reviewed_at' => now(),
            'rejection_reason' => $motif,
        ])->save();

        $this->journal->record(
            action: 'kyc.refuse',
            module: 'identity',
            acteur: $examinateur,
            cible: $dossier,
            apres: ['motif' => $motif],
        );

        $dossier->citoyen?->notify(new DossierKycTranche($dossier->fresh()));
    }

    /**
     * Détruit les pièces des dossiers tranchés il y a plus de trente jours.
     *
     * Appelée par la purge RGPD horaire. Le dossier SURVIT à ses pièces : type,
     * haché, décision, motif et examinateur restent, et suffisent à prouver que
     * le contrôle a eu lieu.
     *
     * @return int nombre de dossiers purgés
     */
    public function purgerLesPiecesExpirees(): int
    {
        $limite = now()->subDays(self::RETENTION_JOURS);
        $purges = 0;

        KycSubmission::query()
            ->whereNotNull('reviewed_at')
            ->where('reviewed_at', '<', $limite)
            ->whereNull('purged_at')
            ->chunkById(100, function ($dossiers) use (&$purges): void {
                foreach ($dossiers as $dossier) {
                    foreach ($dossier->pieces() as $chemin) {
                        Storage::disk(self::DISQUE)->delete($chemin);
                    }

                    $dossier->forceFill([
                        'front_path' => null,
                        'back_path' => null,
                        'selfie_path' => null,
                        'purged_at' => now(),
                    ])->save();

                    $purges++;
                }
            });

        return $purges;
    }

    /** @throws RuntimeException */
    private function refuserSiDejaTranche(KycSubmission $dossier): void
    {
        if (! $dossier->status->estOuvert()) {
            throw new RuntimeException('Ce dossier a déjà été examiné.');
        }
    }

    /**
     * Range une pièce sur le disque privé, sous un nom aléatoire.
     *
     * Le nom d'origine n'est jamais conservé : « cni-kossi-adjovi.jpg » sur un
     * disque partagé est déjà une fuite, et un nom deviné est un fichier
     * atteint si jamais une mauvaise configuration exposait le dossier.
     */
    private function ranger(UploadedFile $fichier): string
    {
        return $fichier->storeAs(
            self::DOSSIER,
            Str::uuid().'.'.$fichier->extension(),
            ['disk' => self::DISQUE],
        );
    }
}
