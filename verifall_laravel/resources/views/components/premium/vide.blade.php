@props(['titre', 'texte' => null, 'icone' => 'inbox'])
{{-- État vide : une invitation à agir, pas une excuse. --}}
<div {{ $attributes->class('text-center py-12 px-6') }}>
  <span class="inline-flex w-11 h-11 items-center justify-center rounded-full bg-brand-50 text-brand-700 dark:bg-brand/10 dark:text-brand"><x-lucide :name="$icone" class="w-5 h-5" /></span>
  <p class="mt-3 font-medium">{{ $titre }}</p>
  @if ($texte)<p class="mt-1 text-sm texte-2 max-w-sm mx-auto">{{ $texte }}</p>@endif
  @if (! $slot->isEmpty())<div class="mt-4">{{ $slot }}</div>@endif
</div>
