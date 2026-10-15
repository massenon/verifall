<?php

declare(strict_types=1);

namespace App\Core\FactChecking\Services;

use App\Core\Economy\Exceptions\SoldeInsuffisant;
use App\Core\Economy\Services\EconomyService;
use App\Enums\MotifMouvementUnite;
use App\Enums\StatutVerification;
use App\Enums\TypeContenu;
use App\Models\User;
use App\Models\Verification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Enregistre une demande de vérification et prélève son coût.
 *
 * L'ordre importe : on DÉBITE d'abord, on crée ensuite, le tout dans une même
 * transaction. Créer d'abord laisserait, en cas de solde insuffisant, une
 * vérification orpheline que rien n'analyserait jamais.
 *
 * Le coût est lu dans la configuration et figé sur la ligne : le client
 * n'envoie qu'un type de contenu.
 */
final class SoumissionVerificationService
{
    public function __construct(
        private readonly EconomyService $economie,
        private readonly EmpreinteContenu $empreintes,
    ) {}

    /**
     * @throws SoldeInsuffisant
     */
    public function soumettre(
        User $utilisateur,
        TypeContenu $type,
        string $affirmation,
        ?string $contexte = null,
        ?UploadedFile $fichier = null,
    ): Verification {
        $this->refuserSiLeModuleEstFerme($type);

        $cout = $type->cout();

        return DB::transaction(function () use ($utilisateur, $type, $affirmation, $contexte, $fichier, $cout): Verification {
            $chemin = $fichier !== null ? $this->rangerLeFichier($fichier) : null;

            $verification = Verification::create([
                'uuid' => (string) Str::uuid(),
                'user_id' => $utilisateur->id,
                'type' => $type->value,
                'status' => StatutVerification::EnFile->value,
                'claim' => trim($affirmation),
                'context' => $contexte !== null && trim($contexte) !== '' ? trim($contexte) : null,
                'file_path' => $chemin,
                // Calculée a la soumission : c'est elle qui permettra de
                // reconnaitre la meme rumeur signalee par quelqu'un d'autre.
                'content_fingerprint' => $this->empreintes->pour(
                    $affirmation,
                    $chemin !== null ? hash_file('sha256', Storage::disk('local')->path($chemin)) : null,
                ),
                'cost_units' => $cout,
            ]);

            /*
             | Le débit vient APRÈS la création mais DANS la même transaction :
             | si le solde manque, l'exception annule aussi l'insertion, et rien
             | ne subsiste. Le fichier téléversé, lui, est nettoyé par la purge
             | des orphelins — un disque n'est pas transactionnel.
             */
            $this->economie->debiter(
                $utilisateur, $cout, MotifMouvementUnite::Verification,
                'Vérification '.$type->libelle().' ('.$verification->reference().')',
                $verification,
            );

            return $verification;
        });
    }

    /**
     * Soumission d'un VISITEUR, sans compte ni débit.
     *
     * Le coût est enregistré à zéro et non au tarif : ces analyses sont
     * offertes, et laisser un coût sur la ligne ferait croire à une dette au
     * moment où le visiteur créerait un compte.
     *
     * L'empreinte du visiteur sert au rapprochement d'analyses, jamais à
     * l'identifier : c'est le haché de son adresse, comme partout ailleurs.
     */
    public function soumettreEnInvite(
        TypeContenu $type,
        string $affirmation,
        string $empreinteVisiteur,
    ): Verification {
        $this->refuserSiLeModuleEstFerme($type);

        return Verification::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => null,
            'type' => $type->value,
            'status' => StatutVerification::EnFile->value,
            'claim' => trim($affirmation),
            'content_fingerprint' => $this->empreintes->pour($affirmation),
            // Offerte : aucun débit, donc aucun remboursement possible.
            'cost_units' => 0,
            'guest_hash' => $empreinteVisiteur,
        ]);
    }

    /**
     * Soumission décidée par la PLATEFORME, sans compte ni débit.
     *
     * C'est le chemin de la « transmission au labo » : une rumeur signalée sur
     * le forum devient une analyse. Personne n'est débité — c'est la plateforme
     * qui décide de vérifier, pas le citoyen qui commande. Lui facturer des
     * unités pour avoir rendu service à tout le monde serait absurde.
     */
    public function soumettreSansDebit(
        TypeContenu $type,
        string $affirmation,
        ?string $contexte = null,
    ): Verification {
        return Verification::create([
            'uuid' => (string) Str::uuid(),
            'user_id' => null,
            'type' => $type->value,
            'status' => StatutVerification::EnFile->value,
            'claim' => trim($affirmation),
            'context' => $contexte,
            'content_fingerprint' => $this->empreintes->pour($affirmation),
            'cost_units' => 0,
        ]);
    }

    /**
     * Range un média sur le disque privé.
     *
     * Nom aléatoire, extension conservée : un nom d'origine peut porter
     * l'identité de la personne — « photo-carte-identite-adderly.jpg » — et
     * finirait lisible dans une liste de fichiers.
     */
    private function rangerLeFichier(UploadedFile $fichier): string
    {
        $nom = Str::uuid()->toString().'.'.$fichier->extension();

        return (string) $fichier->storeAs('verifications', $nom, 'local');
    }

    /**
     * Le solde couvre-t-il ce type de vérification ?
     *
     * Pour AFFICHER, jamais pour autoriser : entre la question et la dépense,
     * le solde peut avoir bougé. Seul le débit tranche, sous verrou.
     */
    public function peutSoumettre(User $utilisateur, TypeContenu $type): bool
    {
        return $this->economie->peutPayer($utilisateur, $type->cout());
    }

    /** Purge des médias dont la vérification a disparu. */
    public function nettoyerLesOrphelins(): int
    {
        $connus = Verification::whereNotNull('file_path')->pluck('file_path')->all();
        $supprimes = 0;

        foreach (Storage::disk('local')->files('verifications') as $fichier) {
            if (! in_array($fichier, $connus, true)) {
                Storage::disk('local')->delete($fichier);
                $supprimes++;
            }
        }

        return $supprimes;
    }

    /**
     * Refuse un type dont le module est fermé.
     *
     * AVANT toute écriture et avant tout débit : la promesse est « zéro unité
     * débitée, zéro déception ». Placée ici et non dans l'écran, parce que
     * l'API mobile passe par ce service sans jamais voir l'écran — et qu'elle
     * acceptait jusqu'ici vidéo et audio sans rien vérifier.
     *
     * @throws ModuleIndisponible
     */
    private function refuserSiLeModuleEstFerme(TypeContenu $type): void
    {
        if (! app(RegistreDesMoteurs::class)->moduleOuvert($type)) {
            throw ModuleIndisponible::pour($type);
        }
    }
}
