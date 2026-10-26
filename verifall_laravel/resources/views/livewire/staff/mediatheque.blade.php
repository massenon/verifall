<div>
  <x-premium.entete titre="Médiathèque" :fil="['Communauté']"
      sous="Les images de l'équipe. Elles restent privées : un lien de console dure 30 minutes, il ne se partage pas.">
    @if ($peutDeposer)
      <x-slot:actions>
        <label for="media-depot" class="btn-primaire cursor-pointer"><x-lucide name="upload" />Déposer des images</label>
      </x-slot:actions>
    @endif
  </x-premium.entete>

  @if ($peutDeposer)
    <div x-data="{ survol: false }" class="carte p-5 mb-6 border-dashed text-center transition-colors"
         :class="survol ? 'border-brand bg-brand-50/50 dark:bg-brand/10' : ''"
         x-on:dragover.prevent="survol = true" x-on:dragleave.prevent="survol = false"
         x-on:drop.prevent="survol = false; $refs.depot.files = $event.dataTransfer.files; $refs.depot.dispatchEvent(new Event('change', { bubbles: true }))">
      <x-lucide name="image" class="w-6 h-6 mx-auto texte-3" />
      <p class="mt-2 text-sm">Glissez des images ici, ou <label for="media-depot" class="underline cursor-pointer">parcourez</label>.</p>
      <p class="aide">JPEG, PNG, WebP ou GIF · 4 Mo et 6 000 px au plus · 10 images par dépôt · pas de SVG.</p>
      <input id="media-depot" x-ref="depot" type="file" multiple accept="image/jpeg,image/png,image/webp,image/gif" class="sr-only" wire:model="fichiers">
      <div wire:loading wire:target="fichiers" class="aide">Envoi en cours…</div>
      @error('fichiers') <p class="aide-erreur justify-center" role="alert">{{ $message }}</p> @enderror
      @error('fichiers.*') <p class="aide-erreur justify-center" role="alert">{{ $message }}</p> @enderror
      @if (count($fichiers))
        <button type="button" class="btn-primaire mt-3" wire:click="deposer" wire:loading.attr="disabled">Enregistrer {{ count($fichiers) }} image(s)</button>
      @endif
    </div>
  @endif

  <div class="mb-4">
    <label for="media-q" class="sr-only">Chercher une image</label>
    <input id="media-q" type="search" class="champ max-w-sm" placeholder="Chercher par nom…" wire:model.live.debounce.350ms="recherche">
  </div>

  @if ($medias->isEmpty())
    <div class="carte"><x-premium.vide icone="images" titre="Aucune image" :texte="$recherche !== '' ? 'Aucune image ne porte ce nom.' : 'Déposez la première pour la retrouver ici.'" /></div>
  @else
    <ul class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-4">
      @foreach ($medias as $media)
        <li class="carte overflow-hidden group" wire:key="media-{{ $media->uuid }}">
          <a href="{{ $media->lienTemporaire() }}" target="_blank" rel="noopener" class="block aspect-square bg-papier dark:bg-nuit">
            <img src="{{ $media->lienTemporaire() }}" alt="{{ $media->texte_alternatif ?: $media->nom_origine }}" loading="lazy" class="w-full h-full object-cover">
          </a>
          <div class="p-2.5">
            <p class="text-[13px] font-medium truncate" title="{{ $media->nom_origine }}">{{ $media->nom_origine }}</p>
            <p class="text-xs texte-3">{{ $media->largeur }}×{{ $media->hauteur }} · {{ number_format($media->octets / 1024, 0, ',', ' ') }} Ko</p>
            @if ($peutToutSupprimer)<p class="text-xs texte-3 truncate">{{ $media->auteur?->name ?? 'Compte supprimé' }}</p>@endif
            @if ($peutToutSupprimer || $media->user_id === $membreId)
              <button type="button" class="btn-fantome btn-sm -ml-2 mt-1" wire:click="demanderSuppression('{{ $media->uuid }}')"><x-lucide name="trash-2" />Supprimer</button>
            @endif
          </div>
        </li>
      @endforeach
    </ul>
    <div class="mt-6">{{ $medias->links() }}</div>
  @endif

  @if ($aSupprimer)
    <div class="voile !z-[80]" wire:click="annuler" aria-hidden="true"></div>
    <div class="fixed inset-x-4 top-1/2 -translate-y-1/2 z-[90] mx-auto max-w-sm carte shadow-flottant p-6" role="alertdialog" aria-modal="true" aria-labelledby="mm-titre">
      <h2 id="mm-titre" class="font-titre text-lg font-semibold text-encre dark:text-white">Supprimer cette image ?</h2>
      <p class="mt-2 text-sm texte-2">Elle disparaît du disque. Un article qui l'utiliserait afficherait un trou.</p>
      <div class="mt-5 flex justify-end gap-2">
        <button type="button" class="btn-second" wire:click="annuler">Annuler</button>
        <button type="button" class="btn-danger" wire:click="supprimer"><x-lucide name="trash-2" />Supprimer</button>
      </div>
    </div>
  @endif
</div>
