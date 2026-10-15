{{--
  | Journal d'Audit — les six derniers gestes, et « Tout voir ».
  |
  | `actor_label` et non la relation vers l'utilisateur : le journal conserve le
  | libellé de l'auteur AU MOMENT DU GESTE. Un compte supprimé depuis ne doit
  | pas effacer la trace de ce qu'il a fait — c'est tout l'intérêt d'un journal
  | d'audit, et une jointure le trahirait en affichant un vide.
--}}
<div class="st-card">
    <div class="st-card-h flex items-center justify-between gap-3">
        <h2 class="font-display font-bold text-[#0F172A] mb-0">Journal d'audit</h2>
        <a href="{{ route('staff.audit') }}" class="text-xs font-bold text-primary">Tout voir →</a>
    </div>

    <div class="st-card-b">
        @forelse ($audit as $trace)
            <div class="flex items-start justify-between gap-3 py-2 border-b border-line last:border-0">
                <div class="min-w-0">
                    <p class="text-sm text-[#0F172A] mb-0 truncate">
                        <span class="font-bold">{{ $trace->actor_label ?? 'Système' }}</span>
                        · {{ $trace->action }}
                    </p>
                    <p class="text-xs text-[#64748B] mb-0 truncate">
                        {{ $trace->module }}@if ($trace->target_label) — {{ $trace->target_label }}@endif
                    </p>
                </div>
                <span class="text-xs text-[#64748B] shrink-0">{{ $trace->created_at?->diffForHumans() }}</span>
            </div>
        @empty
            <p class="text-sm text-[#64748B] mb-0">
                Aucun geste enregistré. Le journal se remplit à la première action sensible.
            </p>
        @endforelse
    </div>
</div>
