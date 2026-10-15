@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/pages/blog-details.css') }}">
@endpush

{{--
  | Un article du blog — transposition de blog-details.html.
  |
  | Structure du prototype : chapeau centré (rubrique, titre, auteur, partage,
  | temps de lecture), visuel pleine largeur, corps de texte en `content-style`,
  | puis le carrousel « Articles similaires ».
  |
  | Le nombre de VUES du prototype (« 3 240 vues ») n'est pas repris : rien ne
  | le compte. Le temps de lecture, lui, est calculé sur le texte réel.
--}}
<main id="contenu">
  <section class="pt-36 pb-16">
    <div class="max-w-page mx-auto px-4">
      <div class="max-w-3xl mx-auto text-center">
        @if($article->category)
          <a href="{{ route('public.blog') }}" class="inline-block rounded-full bg-dark text-white text-xs font-semibold px-4 py-2 mb-6">{{ $article->category->name }}</a>
        @endif
        <h1 class="text-3xl md:text-4xl font-display font-bold text-heading mb-0">{{ $article->title }}</h1>
        <div class="sm:flex justify-center sm:justify-between items-center text-left mt-10">
          <div class="flex justify-center items-center mb-8 sm:mb-0">
            <span class="inline-flex items-center justify-center w-11 h-11 rounded-full border border-line bg-primary/10 text-primary font-bold text-sm" aria-hidden="true">{{ Str::of($article->author?->name ?? "Vérif'All")->substr(0, 2)->upper() }}</span>
            <div class="ml-2.5">
              <h6 class="text-sm font-bold text-heading mb-0">{{ $article->author?->name ?? "Vérif'All Lab" }}</h6>
              <small class="text-body/70">{{ $article->published_at?->translatedFormat('j F Y') }}</small>
            </div>
          </div>
          <div class="flex justify-center items-center flex-wrap gap-2">
            <div class="relative" data-dropdown>
              <button type="button" class="inline-flex items-center gap-2 rounded-full bg-[#f5f6f7] text-sm font-medium text-heading px-4 py-2 hover:bg-gray-200" data-dropdown-toggle aria-expanded="false" aria-haspopup="true">{{ __('pub_article_blog.partager') }}</button>
              <ul class="hidden absolute left-1/2 -translate-x-1/2 top-full mt-2 w-44 bg-white rounded-xl shadow-cardlg border border-line py-2 z-20" data-dropdown-menu>
                <li><a class="block px-4 py-2 text-sm text-heading hover:bg-gray-50" target="_blank" rel="noopener noreferrer" href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}">Facebook</a></li>
                <li><a class="block px-4 py-2 text-sm text-heading hover:bg-gray-50" target="_blank" rel="noopener noreferrer" href="https://api.whatsapp.com/send?text={{ urlencode($article->title.' - '.url()->current()) }}">Whatsapp</a></li>
                <li><a class="block px-4 py-2 text-sm text-heading hover:bg-gray-50" target="_blank" rel="noopener noreferrer" href="https://twitter.com/intent/tweet?text={{ urlencode($article->title) }}&url={{ urlencode(url()->current()) }}">Twitter</a></li>
              </ul>
            </div>
            <span class="text-body/30 mx-1" aria-hidden="true">|</span>
            <span class="text-sm font-bold text-body/70 inline-flex items-center gap-1.5">{{ $tempsDeLecture }} {{ __('pub_article_blog.min_de_lecture') }}</span>
          </div>
        </div>
      </div>

      @if($article->cover_path)
        <div class="max-w-4xl mx-auto text-center mt-10">
          <img src="{{ asset($article->cover_path) }}" class="w-full rounded-2xl shadow-card" alt="{{ $article->title }}" loading="lazy">
        </div>
      @endif

      <div class="max-w-3xl mx-auto mt-10">
        <div class="content-style text-[#606261]">
          {{-- Purifié à l'affichage comme à l'écriture : la double barrière est
               la règle du chantier, et elle protège d'un contenu inséré avant
               que la purification à l'écriture n'existe. --}}
          {!! clean($article->body) !!}
        </div>
      </div>
    </div>
  </section>

  @if($voisins->isNotEmpty())
    <section class="pb-24">
      <div class="max-w-page mx-auto px-4">
        <div data-carousel class="carousel-cols !overflow-visible">
          <div class="flex justify-between items-center mb-8">
            <h3 class="text-2xl font-display font-bold text-heading mb-0">{{ __('pub_article_blog.articles_similaires') }}</h3>
            <div class="flex gap-2">
              <button type="button" class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-[#f5f6f7] text-heading hover:bg-gray-200" data-carousel-prev aria-label="{{ __('pub_article_blog.articles_precedents') }}">‹</button>
              <button type="button" class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-[#f5f6f7] text-heading hover:bg-gray-200" data-carousel-next aria-label="{{ __('pub_article_blog.articles_suivants') }}">›</button>
            </div>
          </div>
          <div class="overflow-hidden">
            <div class="carousel-track">
              @foreach($voisins as $voisin)
                <div class="carousel-slide px-0 sm:px-0" wire:key="voisin-{{ $voisin->id }}">
                  <article class="h-full">
                    @if($voisin->cover_path)
                      <img src="{{ asset($voisin->cover_path) }}" class="w-full h-[180px] object-cover rounded-2xl" alt="{{ $voisin->title }}" loading="lazy">
                    @endif
                    <div class="pt-3">
                      <h6 class="font-display font-bold leading-snug mb-2"><a href="{{ route('public.blog.show', $voisin) }}" class="card-title-link text-heading">{{ $voisin->title }}</a></h6>
                      <div class="flex items-center justify-between mt-2">
                        <p class="text-sm text-body/70 mb-0">{{ $voisin->author?->name ?? "Vérif'All Lab" }}</p>
                        <a href="{{ route('public.blog.show', $voisin) }}" class="inline-flex items-center gap-1 text-primary text-sm font-bold hover:gap-2 transition-all">{{ __('pub_article_blog.lire') }}</a>
                      </div>
                    </div>
                  </article>
                </div>
              @endforeach
            </div>
          </div>
        </div>
      </div>
    </section>
  @endif

  {{-- Infolettre : posée sur les pages ÉDITORIALES seulement (arbitrage 166).
       Quelqu'un qui vient de lire un démenti est la personne la plus susceptible
       d'en vouloir la suite ; sur les mentions légales, ce serait du bruit. --}}
  <div class="pb-16">
    <x-infolettre-abonnement />
  </div>
</main>
