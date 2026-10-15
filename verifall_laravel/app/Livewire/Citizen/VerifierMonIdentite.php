<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Core\Identity\Services\DossiersKyc;
use App\Enums\TypePieceIdentite;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;

/**
 * « Vérifier mon identité » — le dépôt citoyen qui n'existait pas.
 *
 * Jusqu'au 1er septembre 2026, le profil affichait « Identité non vérifiée »
 * **sans aucune action possible** : la colonne `users.kyc_verified_at` était un
 * drapeau que seule la console pouvait lever. Cet écran est la moitié
 * manquante.
 *
 * ## Ce que l'écran dit AVANT de demander une pièce
 *
 * Ce qui est fait des images, combien de temps elles sont gardées, et qui les
 * voit. Demander une carte d'identité sans répondre à ces trois questions est
 * la meilleure façon de n'en recevoir aucune — et la pire façon de traiter des
 * données de cette nature.
 */
#[Layout('components.layouts.citizen', ['title' => "Vérifier mon identité · Vérif'All"])]
final class VerifierMonIdentite extends Component
{
    use InteractsWithToasts;
    use WithFileUploads;

    public string $type = TypePieceIdentite::CarteNationale->value;

    public string $numero = '';

    public ?TemporaryUploadedFile $recto = null;

    public ?TemporaryUploadedFile $verso = null;

    public ?TemporaryUploadedFile $selfie = null;

    /** Le consentement est EXPLICITE, et refusé par défaut. */
    public bool $consentement = false;

    public function deposer(DossiersKyc $dossiers): void
    {
        $regles = [
            'type' => ['required', 'string'],
            'numero' => ['required', 'string', 'min:4', 'max:40'],
            // `image` et non `file` : refuse un PDF, un ZIP, ou un exécutable
            // renommé. La taille borne ce qu'un téléphone produit.
            'recto' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.DossiersKyc::TAILLE_MAXIMUM_KO],
            'verso' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.DossiersKyc::TAILLE_MAXIMUM_KO],
            'selfie' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.DossiersKyc::TAILLE_MAXIMUM_KO],
            'consentement' => ['accepted'],
        ];

        $this->validate($regles, attributes: [
            'recto' => 'recto de la pièce',
            'verso' => 'verso de la pièce',
            'selfie' => 'photo de vous',
            'consentement' => 'consentement',
        ]);

        $choisi = TypePieceIdentite::tryFrom($this->type);

        if ($choisi === null) {
            $this->addError('type', 'Choisissez une pièce dans la liste.');

            return;
        }

        try {
            $dossiers->deposer(
                $this->utilisateur(),
                $choisi,
                $this->numero,
                /*
                 | Passés TELS QUELS : `TemporaryUploadedFile` hérite de
                 | `UploadedFile`. Chercher à « convertir » appelle une méthode
                 | qui n'existe pas, et le dépôt tombe au dernier moment — après
                 | que la personne a envoyé sa carte d'identité.
                 */
                $this->recto,
                $this->verso,
                $this->selfie,
            );
        } catch (RuntimeException $refus) {
            $this->toastError($refus->getMessage());

            return;
        }

        /*
         | On efface le numéro et les fichiers temporaires de l'état du
         | composant tout de suite. Livewire garde l'état sérialisé entre deux
         | requêtes : y laisser un numéro de carte d'identité après le dépôt
         | n'aurait aucune utilité et prolongerait son séjour.
         */
        $this->reset(['numero', 'recto', 'verso', 'selfie', 'consentement']);

        $this->toastSuccess(
            'Dossier déposé. Un membre de l’équipe l’examine ; vous serez prévenu de la décision.'
        );
    }

    public function render(DossiersKyc $dossiers): View
    {
        $citoyen = $this->utilisateur();

        return view('livewire.citizen.verifier-mon-identite', [
            'utilisateur' => $citoyen,
            'dossier' => $dossiers->dernierDossier($citoyen),
            'pieces' => TypePieceIdentite::cases(),
            'choisi' => TypePieceIdentite::tryFrom($this->type),
            'retentionJours' => DossiersKyc::RETENTION_JOURS,
        ]);
    }

    private function utilisateur(): User
    {
        $utilisateur = Auth::guard('web')->user();

        abort_if($utilisateur === null, 403);

        return $utilisateur;
    }
}
