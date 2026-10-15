@props(['campagnes' => null])
@php
    use App\Enums\TeinteCampagne;
    use App\Enums\TypeCampagne;

    $liste = $campagnes ?? collect();
    $bandeaux = $liste->where('type', TypeCampagne::Bandeau);
    $fenetres = $liste->where('type', TypeCampagne::Fenetre);
@endphp

{{--
  | Les campagnes d'affichage — bandeaux et fenêtres.
  |
  | AJOUT au prototype, consigné.
  |
  | ## Pourquoi aucune classe utilitaire ici
  |
  | La première version employait `max-w-[440px]`, `h-[180px]`, `py-2.5`,
  | `text-body/70` et six autres. DIX étaient absentes du build Tailwind, qui
  | est figé — donc la fenêtre n'avait ni largeur maximale ni hauteur d'image,
  | et remplissait l'écran. Une classe absente ne produit aucune erreur : elle
  | est écrite, elle est lisible, et elle ne fait rien.
  |
  | Toute la mise en forme vit donc dans `campagnes.css`, où elle est déclarée.
  |
  | ## Pourquoi le bandeau est en bas
  |
  | L'en-tête du prototype est en `position: absolute; top: 0` — il FLOTTE
  | au-dessus du contenu, et chaque page compense par un `padding-top`. Un
  | bandeau posé dans le flux passait donc SOUS l'en-tête. Le sortir du flux
  | est la seule issue qui ne touche à aucune page.
--}}

@foreach ($bandeaux as $bandeau)
  <aside class="vf-campaign-banner" data-campagne="{{ $bandeau->slug }}" hidden
         aria-label="{{ $bandeau->title }}">
    <div class="vf-campaign-banner-inner">
      @if ($bandeau->image)
        <img src="{{ asset($bandeau->image) }}" alt="" width="48" height="48"
             class="vf-campaign-banner-media" loading="lazy">
      @endif

      <div class="vf-campaign-banner-copy">
        <p class="vf-campaign-banner-title">{{ $bandeau->title }}</p>
        <p class="vf-campaign-banner-text">{{ $bandeau->text }}</p>
      </div>

      {{-- PAS de classe de teinte ici, et c'est délibéré : la barre porte le
           vert de marque, et son bouton prend l'inverse — fond blanc, texte
           vert. Un bouton vert sur fond vert ne se distingue pas, et la classe
           de teinte, déclarée plus bas dans la feuille, l'emporterait sur le
           fond blanc. --}}
      <a href="{{ $bandeau->url }}"
         @if ($bandeau->estExterne()) target="_blank" rel="noopener noreferrer" @endif
         class="vf-campaign-banner-cta">
        {{ $bandeau->cta }}
      </a>

      <button type="button" data-campagne-fermer="{{ $bandeau->slug }}"
              class="vf-campaign-close" aria-label="{{ __('campagnes.masquer_ce_message') }}">
        <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708"/></svg>
      </button>
    </div>
  </aside>
@endforeach

@foreach ($fenetres as $fenetre)
  <div class="vf-campaign-overlay" data-campagne="{{ $fenetre->slug }}"
       data-campagne-delai="{{ $fenetre->delay_seconds }}" hidden>
    <div class="vf-campaign-dialog @if ($fenetre->image) vf-campaign-dialog--avec-media @endif"
         role="dialog" aria-modal="true"
         aria-labelledby="campagne-titre-{{ $fenetre->slug }}"
         aria-describedby="campagne-texte-{{ $fenetre->slug }}">

      @if ($fenetre->image)
        {{-- Aucune dimension n'est déclarée ici, et c'est délibéré.
             `width="440" height="180"` affirmait un format que ces visuels
             n'ont pas : ils sont téléversés depuis l'administration, dans les
             proportions que leur auteur a choisies. Annoncer un rapport faux
             pour éviter un décalage de mise en page revenait à déformer chaque
             image pour protéger une modale déjà bornée en hauteur par sa
             feuille de style. --}}
        <img src="{{ asset($fenetre->image) }}" alt=""
             class="vf-campaign-media" loading="lazy">
      @endif

      <div class="vf-campaign-body">
        <h2 id="campagne-titre-{{ $fenetre->slug }}" class="vf-campaign-title">{{ $fenetre->title }}</h2>
        <p id="campagne-texte-{{ $fenetre->slug }}" class="vf-campaign-text">{{ $fenetre->text }}</p>

        <div class="vf-campaign-actions">
          <a href="{{ $fenetre->url }}"
             @if ($fenetre->estExterne()) target="_blank" rel="noopener noreferrer" @endif
             data-campagne-fermer="{{ $fenetre->slug }}"
             class="vf-campaign-cta {{ TeinteCampagne::depuis($fenetre->color)->classe() }}">
            {{ $fenetre->cta }}
          </a>
          <button type="button" data-campagne-fermer="{{ $fenetre->slug }}"
                  class="vf-campaign-secondary">
            {{ __('campagnes.plus_tard') }}
          </button>
        </div>
      </div>
    </div>
  </div>
@endforeach

@if ($liste->isNotEmpty())
  @once
    {{-- Seul le SCRIPT se pousse d'ici : `@stack('scripts')` est en fin de
         corps, donc après ce composant. La feuille, elle, est déclarée dans le
         `<head>` du gabarit — voir le commentaire qui l'y accompagne. --}}
    @push('scripts')
      <script src="{{ asset('assets/js/pages/campagnes.js') }}" defer></script>
    @endpush
  @endonce
@endif
