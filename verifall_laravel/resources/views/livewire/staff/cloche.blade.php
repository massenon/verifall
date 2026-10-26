{{--
  | La cloche du back-office (lot 2).
  |
  | Le marquage est celui que le gabarit portait — c'est le prototype, et il ne
  | bouge pas. Seule la SOURCE change : quatre notifications écrites en dur et
  | une pastille figée sur « 3 » deviennent une lecture de la table
  | `notifications`.
  |
  | Une cloche vide est un état correct, et elle le dit. Meubler avec de faux
  | messages désapprend à la regarder — le jour où douze signalements arrivent
  | vraiment, plus personne ne distingue ce chiffre de celui d'hier.
--}}
<div class="relative" data-dropdown>
  <button data-tour="notifications" type="button"
          class="relative inline-flex items-center justify-center w-11 h-11 rounded-lg hover:bg-papier dark:hover:bg-nuit text-encre dark:text-white"
          data-dropdown-toggle aria-expanded="false" aria-haspopup="true"
          aria-label="{{ $nonLues > 0 ? 'Notifications — '.$nonLues.' non lues' : 'Notifications' }}">
    <x-lucide name="bell" class="w-4 h-4" />
    {{-- La pastille n'apparaît que s'il y a réellement quelque chose. --}}
    @if ($nonLues > 0)
      <span class="absolute top-1.5 right-1.5 min-w-[18px] h-[18px] px-1 rounded-full bg-critique text-white text-[10px] font-bold inline-flex items-center justify-center"
            data-notif-count>{{ $nonLues > 99 ? '99+' : $nonLues }}</span>
    @endif
  </button>

  <div class="hidden absolute right-0 top-full mt-2 w-[340px] max-w-[92vw] bg-white dark:bg-nuit-carte border border-trait dark:border-nuit-trait rounded-2xl shadow-flottant z-30" data-dropdown-menu>
    <div class="flex items-center justify-between px-4 py-3 border-b border-trait dark:border-nuit-trait">
      <p class="font-titre font-bold text-encre dark:text-white mb-0">Notifications</p>
      @if ($nonLues > 0)
        <button type="button" wire:click="toutMarquerLu" class="text-xs font-bold text-brand-700 dark:text-brand">Tout marquer lu</button>
      @endif
    </div>

    <div class="max-h-[320px] overflow-y-auto" data-notif-list>
      @forelse ($notifications as $notification)
        @php
          $donnees = $notification->data;
          $lue = $notification->read_at !== null;
        @endphp

        @if (($donnees['lien'] ?? null) !== null)
          <a href="{{ $donnees['lien'] }}" class="w-full text-left flex gap-3 px-4 py-3 hover:bg-papier dark:hover:bg-nuit border-b border-trait dark:border-nuit-trait last:border-0 {{ $lue ? '' : 'bg-brand-50 dark:bg-brand/10' }}">
        @else
          <div class="w-full text-left flex gap-3 px-4 py-3 border-b border-trait dark:border-nuit-trait last:border-0 {{ $lue ? '' : 'bg-brand-50 dark:bg-brand/10' }}">
        @endif

          <span class="w-9 h-9 rounded-full bg-green-100 dark:bg-green-950 text-brand-700 dark:text-brand inline-flex items-center justify-center shrink-0">
            <x-lucide :name="$donnees['icone'] ?? 'notifications'" class="w-4 h-4" />
          </span>
          <span class="flex-1 min-w-0">
            <span class="block text-sm font-bold text-encre dark:text-white truncate">{{ $donnees['texte'] ?? '' }}</span>
            @if (($donnees['detail'] ?? null) !== null)
              <span class="block text-xs text-encre-2 dark:text-nuit-2 truncate">{{ $donnees['detail'] }}</span>
            @endif
            <span class="block text-[10px] text-encre-2 dark:text-nuit-2 mt-0.5">{{ $notification->created_at?->diffForHumans() }}</span>
          </span>
          @unless ($lue)
            <span class="w-2 h-2 rounded-full bg-brand mt-1.5 shrink-0" data-unread-dot aria-label="Non lue"></span>
          @endunless

        @if (($donnees['lien'] ?? null) !== null)
          </a>
        @else
          </div>
        @endif
      @empty
        {{-- Rien à afficher, et on le dit. La phrase explique aussi POURQUOI,
             pour qu'une cloche vide ne passe pas pour une panne. --}}
        <p class="px-4 py-6 text-sm text-encre-2 dark:text-nuit-2 text-center mb-0">
          Aucune notification.
          <span class="block text-xs mt-1">L'activité de l'équipe se lit au journal d'audit.</span>
        </p>
      @endforelse
    </div>

    <div class="px-4 py-2.5 border-t border-trait dark:border-nuit-trait text-center">
      <a href="{{ route('staff.audit') }}" class="text-xs font-bold text-brand-700 dark:text-brand">Voir toute l'activité →</a>
    </div>
  </div>
</div>
