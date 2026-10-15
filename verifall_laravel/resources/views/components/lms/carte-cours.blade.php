@props(['cours', 'inscription' => null])

{{--
  | UNE carte, QUATRE états — le prototype le dit ainsi, et c'est le bon
  | découpage. Le libellé du bouton dit à quoi s'attendre : « Commencer » sur un
  | cours à moitié fait ferait croire à une progression perdue.
--}}
<article class="card flex flex-col overflow-hidden">
  <a href="{{ route('citizen.course', $cours) }}" class="block" tabindex="-1" aria-hidden="true">
    <img src="{{ asset($cours->cover_path ?? 'assets/images/courses/placeholder-a.svg') }}"
         alt="" width="640" height="360" loading="lazy"
         class="w-full object-cover" style="aspect-ratio:16/9">
  </a>

  <div class="p-4 flex flex-col flex-1">
    <p class="text-[10px] font-bold tracking-[.08em] text-primary uppercase mb-1.5">{{ $cours->category }}</p>

    <h3 class="text-base font-display font-bold text-ink leading-snug mb-1">
      <a href="{{ route('citizen.course', $cours) }}" class="hover:text-primary transition-colors">{{ $cours->title }}</a>
    </h3>

    <p class="text-sm text-slate2 mb-2.5">{{ Str::limit($cours->description, 110) }}</p>

    {{-- Pas d'étoile sans avis : une moyenne calculée sur rien est un
         chiffre inventé. « Nouveau » est vrai, et n'a rien d'humiliant. --}}
    <p class="text-xs text-slate2 mb-2">
      @if($cours->reviews_count > 0)
        ★ {{ number_format($cours->rating, 1, ',', ' ') }} ·
      @else
        <span class="font-semibold text-primary">{{ __('composants.cours_nouveau') }}</span> ·
      @endif
      {{ $cours->lessons_count ?? $cours->lessons()->count() }} {{ __('composants.cours_lecons') }}
      · {{ $cours->duration_label }}
      · {{ $cours->level->libelle() }}
    </p>

    <p class="mb-3">
      @if($cours->estGratuit())
        <span class="vbadge vbadge-true">{{ __('composants.cours_gratuit') }}</span>
        @if($cours->estOffert())
          {{-- Un cours normalement payant, offert ce mois-ci : le dire, sinon
               personne ne sait que l'occasion est temporaire. --}}
          <span class="text-[11px] text-slate2">{{ __('composants.cours_offert_jusqu_au') }} {{ $cours->free_until->translatedFormat('j F') }}</span>
        @endif
      @else
        <span class="vbadge" style="background:rgba(124,58,237,.1);color:#6D28D9">{{ __('composants.cours_premium') }} {{ $cours->cost_units }} {{ __('composants.cours_unites') }}</span>
      @endif
    </p>

    <div class="mt-auto">
      @if($inscription?->estTerminee())
        <p class="mb-2"><span class="vbadge vbadge-true">{{ __('composants.cours_termine') }}</span></p>
        <a href="{{ route('citizen.course', $cours) }}"
           class="inline-flex items-center rounded-xl border border-line text-sm font-semibold text-ink px-4 py-2.5 hover:bg-canvas min-h-[44px]">
          {{ __('composants.cours_revoir') }}
        </a>

      @elseif($inscription)
        <div class="flex items-center gap-2 mb-2.5">
          <span class="progress flex-1"><span style="width:{{ $inscription->progress }}%"></span></span>
          <strong class="text-xs">{{ $inscription->progress }} %</strong>
        </div>
        <a href="{{ route('citizen.course', $cours) }}"
           class="inline-flex items-center rounded-xl bg-primary text-white text-sm font-semibold px-4 py-2.5 hover:bg-primary-dark min-h-[44px]">
          {{ $inscription->libelleDeReprise() }}
          <x-icone nom="arrow_forward" :taille="15" />
        </a>

      @else
        <a href="{{ route('citizen.course', $cours) }}"
           class="inline-flex items-center rounded-xl bg-primary text-white text-sm font-semibold px-4 py-2.5 hover:bg-primary-dark min-h-[44px]">
          {{ __('composants.cours_commencer') }}
          <x-icone nom="arrow_forward" :taille="15" />
        </a>
      @endif
    </div>
  </div>
</article>
