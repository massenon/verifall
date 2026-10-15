@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/pages/public-facts.css') }}">
@endpush

{{--
  | « Actualités » — transposition de facts.html.
  |
  | Structure du prototype : héros centré sur fond gris avec la barre de
  | recherche en pilule, puis « Dernières analyses publiées » avec son menu de
  | thèmes et la grille de cartes en trois colonnes.
  |
  | Le contenu vient de la BASE. Le prototype fige sept analyses dans son HTML ;
  | ici elles sont semées et relues, si bien qu'une correction de la rédaction
  | se voit sans redéploiement.
--}}
<main id="contenu">
  <section class="bg-[#f8f9fa] pt-36 pb-20">
    <div class="max-w-page mx-auto px-4">
      <div class="max-w-3xl mx-auto text-center">
        <h1 class="text-4xl md:text-5xl font-display font-extrabold text-heading mb-3">{{ __('pub_actualites.la_verite_en_un_clic') }}</h1>
        <p class="text-lg text-body/80 mb-10">{{ __('pub_actualites.recherchez_parmi_nos_verifications_ou_interrogez') }}</p>
        <div class="flex items-center bg-white rounded-full shadow-card border border-line overflow-hidden">
          <span class="pl-5 text-primary" aria-hidden="true">
            <svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor"><path d="M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001q.044.06.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1 1 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0"/></svg>
          </span>
          <label for="facts-search" class="sr-only">{{ __('pub_actualites.rechercher_une_verification') }}</label>
          <input id="facts-search" type="search" wire:model.live.debounce.350ms="recherche"
                 class="w-full px-4 py-4 text-heading outline-none plain"
                 placeholder="{{ __('pub_actualites.entrez_un_mot_cle_ou_une_rumeur') }}">
        </div>
      </div>
    </div>
  </section>

  <section class="py-16">
    <div class="max-w-page mx-auto px-4">
      <div class="flex items-center justify-between mb-6">
        <h4 class="text-2xl font-display font-bold text-heading">{{ __('pub_actualites.dernieres_analyses_publiees') }}</h4>
        @if($categories !== [])
          <div class="relative" data-dropdown>
            <button type="button" class="inline-flex items-center gap-2 rounded-lg bg-[#f8f9fa] border border-line text-sm font-medium text-heading px-3.5 py-2 hover:bg-gray-100" data-dropdown-toggle aria-expanded="false" aria-haspopup="true">
              {{ $categorie === '' ? __('pub_actualites.filtrer_par_theme') : $categorie }}
              <svg width="12" height="12" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M7.247 11.14 2.451 5.658C1.885 5.013 2.345 4 3.204 4h9.592a1 1 0 0 1 .753 1.659l-4.796 5.48a1 1 0 0 1-1.506 0z"/></svg>
            </button>
            <ul class="hidden absolute right-0 top-full mt-2 w-44 bg-white rounded-xl shadow-cardlg border border-line py-2 z-20" data-dropdown-menu>
              <li><button type="button" wire:click="choisirCategorie('')" class="block w-full text-left px-4 py-2 text-sm text-heading hover:bg-gray-50">{{ __('pub_actualites.tous') }}</button></li>
              @foreach($categories as $nom)
                <li><button type="button" wire:click="choisirCategorie(@js($nom))" wire:key="actu-cat-{{ $loop->index }}" class="block w-full text-left px-4 py-2 text-sm text-heading hover:bg-gray-50">{{ $nom }}</button></li>
              @endforeach
            </ul>
          </div>
        @endif
      </div>

      @if($articles->isNotEmpty())
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
          @foreach($articles as $article)
            <article class="bg-white rounded-xl shadow-card hover:shadow-cardlg transition-shadow flex flex-col" data-reveal wire:key="actu-{{ $article->id }}">
              @if($article->cover_path)
                <a href="{{ route('public.fact', $article) }}" tabindex="-1" aria-hidden="true">
                  <img src="{{ asset($article->cover_path) }}" alt="" loading="lazy" class="w-full h-40 object-cover rounded-t-xl">
                </a>
              @endif
              <div class="p-6 pb-0 flex-1">
                <div class="flex items-start justify-between mb-4">
                  <span class="{{ $article->verdict?->pastillePublique() ?? 'fact-pill fact-pill--inconnu' }}">
                    {{ $article->libelleDuBandeau() }}
                  </span>
                  <small class="text-sm text-body/70">{{ $article->published_at?->diffForHumans() }}</small>
                </div>
                <h5 class="text-lg font-display font-bold leading-snug mb-2">
                  <a href="{{ route('public.fact', $article) }}" class="card-title-link text-heading">{{ $article->title }}</a>
                </h5>
                <p class="text-sm text-body/80 leading-relaxed">{{ $article->excerpt }}</p>
              </div>
              <div class="p-6 pt-4">
                <a href="{{ route('public.fact', $article) }}" class="inline-flex items-center gap-2 text-primary text-sm font-bold hover:gap-3 transition-all">
                  {{ __('pub_actualites.lire_l_analyse_complete') }}
                  <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/></svg>
                </a>
              </div>
            </article>
          @endforeach
        </div>

        <div class="mt-10">{{ $articles->links() }}</div>
      @else
        <p class="text-center text-body/70 mt-10">
          {{ $this->filtresActifs()
              ? __('pub_actualites.aucune_analyse_ne_correspond_a_votre_recherche')
              : __('pub_actualites.la_redaction_publie_ici_les_rumeurs_qu_elle') }}
        </p>
      @endif
    </div>
  </section>

  {{-- Infolettre : posée sur les pages ÉDITORIALES seulement (arbitrage 166).
       Quelqu'un qui vient de lire un démenti est la personne la plus susceptible
       d'en vouloir la suite ; sur les mentions légales, ce serait du bruit. --}}
  <div class="pb-16">
    <x-infolettre-abonnement />
  </div>
</main>
