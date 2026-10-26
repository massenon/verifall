{{--
  | Journal d'Audit — les six derniers gestes, et « Tout voir ».
  |
  | `actor_label` et non la relation vers l'utilisateur : le journal conserve le
  | libellé de l'auteur AU MOMENT DU GESTE. Un compte supprimé depuis ne doit
  | pas effacer la trace de ce qu'il a fait — c'est tout l'intérêt d'un journal
  | d'audit, et une jointure le trahirait en affichant un vide.
--}}
<div class="carte">
    <div class="carte-tete flex items-center justify-between gap-3">
        <h2 class="font-titre font-bold text-encre dark:text-white mb-0">Journal d'audit</h2>
        <a href="{{ route('staff.audit') }}" class="text-xs font-bold text-brand-700 dark:text-brand">Tout voir →</a>
    </div>

    <div class="carte-corps">
        @forelse ($audit as $trace)
            <div class="flex items-start justify-between gap-3 py-2 border-b border-trait dark:border-nuit-trait last:border-0">
                <div class="min-w-0">
                    <p class="text-sm text-encre dark:text-white mb-0 truncate">
                        <span class="font-bold">{{ $trace->actor_label ?? 'Système' }}</span>
                        · {{ $trace->action }}
                    </p>
                    <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0 truncate">
                        {{ $trace->module }}@if ($trace->target_label) — {{ $trace->target_label }}@endif
                    </p>
                </div>
                <span class="text-xs text-encre-3 dark:text-nuit-3 shrink-0">{{ $trace->created_at?->diffForHumans() }}</span>
            </div>
        @empty
            <p class="text-sm text-encre-3 dark:text-nuit-3 mb-0">
                Aucun geste enregistré. Le journal se remplit à la première action sensible.
            </p>
        @endforelse
    </div>
</div>
