<?php

declare(strict_types=1);

namespace App\Livewire\Staff;

use App\Core\Identity\Services\AuditLogService;
use App\Models\MediaFile;
use App\Models\User;
use App\Support\Trait\InteractsWithToasts;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

/**
 * La médiathèque : les images de l'équipe, sur le disque PRIVÉ.
 *
 *  - le nom stocké est un uuid, l'extension vient du type DÉTECTÉ et non du nom
 *    envoyé : `photo.php.jpg` ne devient jamais un fichier exécutable ;
 *  - pas de SVG (c'est du code), 4 Mo et 6 000 px au plus ;
 *  - on ne supprime que le sien, sauf droit `media.delete` ;
 *  - chaque dépôt et chaque suppression est journalisé.
 *
 * Un mentor ne voit que ses dépôts ; qui a `media.delete` voit tout.
 */
#[Layout('components.layouts.staff', ['title' => "Médiathèque · Vérif'All"])]
final class Mediatheque extends Component
{
    use InteractsWithToasts;
    use WithFileUploads;
    use WithPagination;

    private const COTE_MAX = 6000;

    /** @var array<int, UploadedFile> */
    public array $fichiers = [];

    #[Url(as: 'q', except: '')]
    public string $recherche = '';

    public ?string $aSupprimer = null;

    public function mount(): void
    {
        abort_unless($this->membre()->can('media.view'), 403);
    }

    public function updatingRecherche(): void
    {
        $this->resetPage();
    }

    public function deposer(AuditLogService $journal): void
    {
        abort_unless($this->membre()->can('media.upload'), 403);

        $this->validate([
            'fichiers' => ['required', 'array', 'max:10'],
            'fichiers.*' => ['image', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.(MediaFile::OCTETS_MAX / 1024), 'dimensions:max_width='.self::COTE_MAX.',max_height='.self::COTE_MAX],
        ], [
            'fichiers.*.image' => 'Ce fichier n’est pas une image.',
            'fichiers.*.mimes' => 'Formats acceptés : JPEG, PNG, WebP, GIF.',
            'fichiers.*.max' => 'Une image ne dépasse pas 4 Mo.',
            'fichiers.*.dimensions' => 'Une image ne dépasse pas '.self::COTE_MAX.' px de côté.',
        ]);

        $deposes = 0;

        foreach ($this->fichiers as $fichier) {
            $mime = (string) $fichier->getMimeType();

            if (! in_array($mime, MediaFile::MIMES, true)) {
                continue;
            }

            $extension = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'][$mime];
            $chemin = 'mediatheque/'.now()->format('Y/m').'/'.Str::uuid().'.'.$extension;
            [$largeur, $hauteur] = array_pad((array) @getimagesize($fichier->getRealPath()), 2, null);

            Storage::disk(MediaFile::DISQUE)->putFileAs(dirname($chemin), $fichier, basename($chemin));

            $media = MediaFile::query()->create([
                'user_id' => $this->membre()->id,
                'chemin' => $chemin,
                'nom_origine' => Str::limit(preg_replace('/[^\p{L}\p{N}._ -]/u', '', $fichier->getClientOriginalName()) ?: 'image', 150, ''),
                'mime' => $mime,
                'octets' => (int) $fichier->getSize(),
                'largeur' => $largeur,
                'hauteur' => $hauteur,
            ]);

            $journal->record(action: 'media.upload', module: 'media', acteur: $this->membre(), cible: $media, cibleLibelle: $media->nom_origine);
            $deposes++;
        }

        $this->reset('fichiers');
        $this->toastSuccess($deposes > 1 ? "{$deposes} images déposées." : 'Image déposée.');
    }

    public function demanderSuppression(string $uuid): void
    {
        $this->aSupprimer = $this->mediaAutorise($uuid)?->uuid;
    }

    public function annuler(): void
    {
        $this->aSupprimer = null;
    }

    public function supprimer(AuditLogService $journal): void
    {
        $media = $this->aSupprimer !== null ? $this->mediaAutorise($this->aSupprimer) : null;
        $this->aSupprimer = null;

        if ($media === null) {
            return;
        }

        Storage::disk(MediaFile::DISQUE)->delete($media->chemin);
        $journal->record(action: 'media.delete', module: 'media', acteur: $this->membre(), cibleLibelle: $media->nom_origine);
        $media->delete();

        $this->toastSuccess('Image supprimée.');
    }

    public function render(): View
    {
        $membre = $this->membre();

        $medias = MediaFile::query()
            ->when(! $membre->can('media.delete'), fn ($q) => $q->where('user_id', $membre->id))
            ->when($this->recherche !== '', fn ($q) => $q->where('nom_origine', 'like', '%'.addcslashes($this->recherche, '%_\\').'%'))
            ->with('auteur:id,name')
            ->latest('id')
            ->paginate(24);

        return view('livewire.staff.mediatheque', [
            'medias' => $medias,
            'peutDeposer' => $membre->can('media.upload'),
            'peutToutSupprimer' => $membre->can('media.delete'),
            'membreId' => $membre->id,
        ]);
    }

    /** Le média, seulement si la personne a le droit de le voir ET de le supprimer. */
    private function mediaAutorise(string $uuid): ?MediaFile
    {
        $membre = $this->membre();
        $media = MediaFile::query()->where('uuid', $uuid)->first();

        if ($media === null) {
            return null;
        }

        return ($membre->can('media.delete') || ($media->user_id === $membre->id && $membre->can('media.upload'))) ? $media : null;
    }

    private function membre(): User
    {
        /** @var User $membre */
        $membre = Auth::guard('staff')->user();

        return $membre;
    }
}
