<?php

declare(strict_types=1);

use App\Enums\UserRole;
use App\Livewire\Staff\Mediatheque;
use App\Models\AuditLog;
use App\Models\MediaFile;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake(MediaFile::DISQUE);
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->actif()->create();
    $this->admin->assignRole(UserRole::Admin->value);
    $this->mentor = User::factory()->actif()->create();
    $this->mentor->assignRole(UserRole::Mentor->value);
    $this->autre = User::factory()->actif()->create();
    $this->autre->assignRole(UserRole::Mentor->value);
});

function unMedia(User $auteur, string $nom = 'photo.png'): MediaFile
{
    $chemin = 'mediatheque/2026/10/'.Str::uuid().'.png';
    Storage::disk(MediaFile::DISQUE)->put($chemin, UploadedFile::fake()->image($nom, 20, 20)->getContent());

    return MediaFile::query()->create(['user_id' => $auteur->id, 'chemin' => $chemin, 'nom_origine' => $nom, 'mime' => 'image/png', 'octets' => 100, 'largeur' => 20, 'hauteur' => 20]);
}

it('dépose une image sous un nom aléatoire, et le journalise', function (): void {
    Livewire::actingAs($this->mentor, 'staff')->test(Mediatheque::class)
        ->set('fichiers', [UploadedFile::fake()->image('Mon logo.png', 40, 30)])
        ->call('deposer')->assertHasNoErrors();

    $media = MediaFile::query()->firstOrFail();
    expect($media->chemin)->toMatch('#^mediatheque/\d{4}/\d{2}/[0-9a-f-]{36}\.png$#')
        ->and($media->user_id)->toBe($this->mentor->id)
        ->and($media->largeur)->toBe(40)
        ->and(Storage::disk(MediaFile::DISQUE)->exists($media->chemin))->toBeTrue()
        ->and(AuditLog::where('action', 'media.upload')->exists())->toBeTrue();
});

it('refuse ce qui n’est pas une image, et le SVG', function (): void {
    Livewire::actingAs($this->mentor, 'staff')->test(Mediatheque::class)
        ->set('fichiers', [UploadedFile::fake()->createWithContent('faux.jpg', '<?php echo 1;')])
        ->call('deposer')->assertHasErrors('fichiers.0');

    Livewire::actingAs($this->mentor, 'staff')->test(Mediatheque::class)
        ->set('fichiers', [UploadedFile::fake()->createWithContent('icone.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>')])
        ->call('deposer')->assertHasErrors('fichiers.0');

    expect(MediaFile::count())->toBe(0);
});

it('ne montre à un mentor que ses images, à l’administrateur toutes', function (): void {
    unMedia($this->mentor, 'a-moi.png');
    unMedia($this->autre, 'a-lautre.png');

    Livewire::actingAs($this->mentor, 'staff')->test(Mediatheque::class)->assertSee('a-moi.png')->assertDontSee('a-lautre.png');
    Livewire::actingAs($this->admin, 'staff')->test(Mediatheque::class)->assertSee('a-moi.png')->assertSee('a-lautre.png');
});

it('ne supprime que ses propres images, sauf droit de tout supprimer', function (): void {
    $sienne = unMedia($this->mentor);
    $etrangere = unMedia($this->autre);

    Livewire::actingAs($this->mentor, 'staff')->test(Mediatheque::class)
        ->call('demanderSuppression', $etrangere->uuid)->assertSet('aSupprimer', null);
    Livewire::actingAs($this->mentor, 'staff')->test(Mediatheque::class)
        ->call('demanderSuppression', $sienne->uuid)->call('supprimer');

    expect(MediaFile::find($sienne->id))->toBeNull()
        ->and(Storage::disk(MediaFile::DISQUE)->exists($sienne->chemin))->toBeFalse()
        ->and(MediaFile::find($etrangere->id))->not->toBeNull();

    Livewire::actingAs($this->admin, 'staff')->test(Mediatheque::class)
        ->call('demanderSuppression', $etrangere->uuid)->call('supprimer');
    expect(MediaFile::find($etrangere->id))->toBeNull();
});

it('sert le fichier par lien signé seulement, et à qui a le droit de le voir', function (): void {
    $media = unMedia($this->mentor);

    $this->actingAs($this->mentor, 'staff')->get(route('staff.media.file', $media->uuid))->assertForbidden();

    $this->actingAs($this->mentor, 'staff')->get($media->lienTemporaire())
        ->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

    $this->actingAs($this->autre, 'staff')->get($media->lienTemporaire())->assertForbidden();
    $this->actingAs($this->admin, 'staff')->get($media->lienTemporaire())->assertOk();
});

it('est fermée au public et au modérateur', function (): void {
    $this->get(route('staff.media'))->assertRedirect();

    $moderateur = User::factory()->actif()->create();
    $moderateur->assignRole(UserRole::Moderateur->value);
    $this->actingAs($moderateur, 'staff')->get(route('staff.media'))->assertForbidden();
});
