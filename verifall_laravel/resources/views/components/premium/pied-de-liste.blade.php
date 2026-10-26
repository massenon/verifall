@props(['liste'])
{{-- Pied de liste : « Lignes 1 à 25 sur 130 » et la pagination. --}}
<div {{ $attributes->class('mt-4 flex flex-wrap items-center justify-between gap-3') }}>
  <p class="text-sm texte-2">
    @if ($liste->total() === 0) Aucun résultat.
    @else Lignes {{ $liste->firstItem() }} à {{ $liste->lastItem() }} sur {{ number_format($liste->total(), 0, ',', ' ') }}.
    @endif
  </p>
  @if ($liste->hasPages())<div>{{ $liste->onEachSide(1)->links() }}</div>@endif
</div>
