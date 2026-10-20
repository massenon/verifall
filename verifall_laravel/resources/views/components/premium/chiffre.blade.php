@props(['valeur', 'libelle', 'aide' => null, 'icone' => null])
{{-- Tuile de chiffre clé. Un chiffre non mesuré ne s'affiche pas : l'appelant passe « — ». --}}
<div {{ $attributes->class('carte p-4') }}>
  <div class="flex items-center justify-between gap-2">
    <p class="text-[13px] texte-2">{{ $libelle }}</p>
    @if ($icone)<span class="texte-3"><x-lucide :name="$icone" class="w-4 h-4" /></span>@endif
  </div>
  <p class="mt-2 text-[26px] leading-none font-semibold tracking-tight text-encre dark:text-white tabular-nums">{{ $valeur }}</p>
  @if ($aide)<p class="mt-2 text-xs texte-3">{{ $aide }}</p>@endif
</div>
