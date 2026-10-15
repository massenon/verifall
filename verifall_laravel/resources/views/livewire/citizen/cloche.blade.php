{{--
  | La cloche de notifications.
  |
  | Le MARQUAGE est celui du prototype, au caractère près : bouton rond de
  | 44 px, pastille rouge en absolu, menu de 340 px, trois `pill-tab`, liste à
  | hauteur bornée avec défilement. Rien n'a bougé de place.
  |
  | Ce qui a changé est en dessous : la liste vient du serveur, et la pastille
  | n'apparaît que s'il y a réellement quelque chose à lire.
--}}
<div class="relative" data-dropdown>
  <button type="button"
          class="relative inline-flex items-center justify-center w-11 h-11 rounded-full hover:bg-canvas text-ink"
          data-dropdown-toggle aria-expanded="false" aria-haspopup="true"
          aria-label="{{ $nonLues > 0 ? $nonLues.' notification(s) non lue(s)' : 'Notifications' }}">
    <span class="material-symbols-rounded !text-[24px]" aria-hidden="true">notifications</span>

    {{-- La pastille SUIT les données. Elle était auparavant écrite en dur dans
         le gabarit, donc allumée en permanence : un signal permanent n'est
         plus un signal, et on cesse de le regarder. --}}
    @if ($nonLues > 0)
      <span class="absolute top-2 right-2.5 w-2.5 h-2.5 rounded-full bg-vfalse ring-2 ring-surface" data-notif-dot></span>
    @endif
  </button>

  <div class="hidden absolute right-0 top-full mt-2 w-[340px] bg-surface border border-line rounded-2xl shadow-cardlg z-30" data-dropdown-menu>
    <div class="px-4 pt-4 pb-2 flex items-center justify-between">
      <h6 class="font-display font-bold text-ink mb-0">{{ __('cit_cloche.notifications') }}</h6>
      @if ($nonLues > 0)
        <button type="button" wire:click="toutMarquerLu" class="text-xs font-semibold text-primary">{{ __('cit_cloche.tout_marquer_lu') }}</button>
      @endif
    </div>

    <div class="px-4 pb-2 flex gap-1.5">
      <button type="button" wire:click="filtrer('')"
              class="pill-tab {{ $onglet === '' ? 'is-active' : '' }}">{{ __('cit_cloche.tout') }}</button>
      @foreach ($categories as $categorie)
        <button type="button" wire:click="filtrer('{{ $categorie->value }}')"
                class="pill-tab {{ $onglet === $categorie->value ? 'is-active' : '' }}">{{ $categorie->libelle() }}</button>
      @endforeach
    </div>

    <div class="max-h-72 overflow-auto pb-2">
      @forelse ($notifications as $notification)
        @php
          $donnees = $notification->data;
          $lue = $notification->read_at !== null;
        @endphp

        @if (($donnees['lien'] ?? null) !== null)
          <a href="{{ $donnees['lien'] }}" class="flex gap-3 px-4 py-3 hover:bg-canvas">
        @else
          <div class="flex gap-3 px-4 py-3">
        @endif

          <span class="material-symbols-rounded shrink-0 {{ $lue ? 'text-slate2' : 'text-primary' }}" aria-hidden="true">{{ $donnees['icone'] ?? 'notifications' }}</span>
          <span class="flex-1 min-w-0">
            <span class="block text-sm {{ $lue ? 'text-slate2' : 'text-ink font-medium' }}">{{ $donnees['texte'] ?? '' }}</span>
            {{-- `diffForHumans` et non une date : à cet endroit, « il y a
                 3 minutes » se lit d'un coup d'œil, « 25/08/2026 14:03 »
                 demande un calcul. --}}
            <span class="block text-xs text-slate2 mt-0.5">{{ $notification->created_at?->diffForHumans() }}</span>
          </span>

        @if (($donnees['lien'] ?? null) !== null)
          </a>
        @else
          </div>
        @endif
      @empty
        {{-- Le texte du prototype, mot pour mot. --}}
        <p class="px-4 py-6 text-sm text-slate2 text-center mb-0">{{ __('cit_cloche.aucune_notification_pour_le_moment') }}</p>
      @endforelse
    </div>
  </div>
</div>
