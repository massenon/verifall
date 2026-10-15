@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/pages/blog.css') }}">
@endpush

{{--
  | « Notre Blog » — transposition de blog.html.
  |
  | Structure du prototype : titre et pastilles de rubriques sur une ligne, puis
  | la bannière de l'article à la une, puis la grille en trois colonnes.
  |
  | Le contenu vient de la base : un blog dont les articles sont figés dans le
  | gabarit ne se met jamais à jour, et le premier vrai article obligerait à
  | réécrire la page.
--}}
<main id="contenu">
  <section class="pt-36 pb-8">
    <div class="max-w-page mx-auto px-4">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-10">
        <h4 class="text-3xl font-display font-black text-heading mb-0">{{ __('pub_blog.notre_blog') }}</h4>
        @if($categories->isNotEmpty())
          <div class="flex flex-wrap gap-2">
            <button type="button" wire:click="choisirCategorie('')" class="nav-pill {{ $categorie === '' ? 'is-active' : '!border-line !text-body hover:!text-primary hover:!border-primary' }}">{{ __('pub_blog.tous') }}</button>
            @foreach($categories as $rubrique)
              <button type="button" wire:click="choisirCategorie(@js($rubrique->slug))" wire:key="blog-cat-{{ $rubrique->id }}" class="nav-pill {{ $categorie === $rubrique->slug ? 'is-active' : '!border-line !text-body hover:!text-primary hover:!border-primary' }}">{{ $rubrique->name }}</button>
            @endforeach
          </div>
        @endif
      </div>

      @if($aLaUne)
        <div class="featured-banner">
          <div class="grid lg:grid-cols-2">
            <div class="min-h-[280px]">
              @if($aLaUne->cover_path)
                <img src="{{ asset($aLaUne->cover_path) }}" class="w-full h-full object-cover min-h-[280px]" alt="{{ $aLaUne->title }}" loading="lazy">
              @endif
            </div>
            <div class="p-6 sm:p-12 flex flex-col justify-center items-start">
              @if($aLaUne->category)
                <span class="inline-block rounded-full bg-primary text-white text-sm font-semibold px-4 py-1.5 mb-4">{{ $aLaUne->category->name }}</span>
              @endif
              <h2 class="text-3xl font-display font-bold text-white mb-4">{{ $aLaUne->title }}</h2>
              <p class="text-white/80 mb-6">{{ $aLaUne->excerpt }}</p>
              <a href="{{ route('public.blog.show', $aLaUne) }}" class="inline-flex items-center gap-2 rounded-full bg-primary text-white font-semibold px-5 py-2.5 hover:bg-primary-dark hover:gap-3 transition-all">{{ __('pub_blog.lire_l_article_complet') }}
                <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/></svg>
              </a>
            </div>
          </div>
        </div>
      @endif
    </div>
  </section>

  <section class="pb-16">
    <div class="max-w-page mx-auto px-4">
      @if($articles->isNotEmpty())
        <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6 xl:gap-10 mt-2">
          @foreach($articles as $article)
            <article class="bg-white rounded-2xl shadow-card hover:shadow-cardlg transition-shadow overflow-hidden flex flex-col" data-reveal wire:key="post-{{ $article->id }}">
              <div class="relative">
                @if($article->category)
                  <div class="absolute top-3 left-3 rounded-full bg-dark text-white text-xs font-semibold px-3 py-1.5 z-10">{{ $article->category->name }}</div>
                @endif
                @if($article->cover_path)
                  <img src="{{ asset($article->cover_path) }}" class="w-full h-[220px] object-cover" alt="{{ $article->title }}" loading="lazy">
                @endif
              </div>
              <div class="p-5 flex flex-col flex-1">
                <h6 class="font-display font-bold text-heading leading-snug mb-2">
                  <a href="{{ route('public.blog.show', $article) }}" class="card-title-link">{{ $article->title }}</a>
                </h6>
                <p class="text-sm text-body/80 leading-relaxed flex-1">{{ $article->excerpt }}</p>
                <div class="flex items-center justify-between mt-3">
                  <p class="text-sm text-body/70 mb-0">{{ $article->author?->name ?? "Vérif'All Team" }}</p>
                  <a class="inline-flex items-center gap-1 text-primary text-sm font-bold hover:gap-2 transition-all" href="{{ route('public.blog.show', $article) }}">{{ __('pub_blog.lire_la_suite') }}
                    <svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/></svg>
                  </a>
                </div>
              </div>
            </article>
          @endforeach
        </div>

        <nav class="flex justify-center mt-14" aria-label="{{ __('pub_blog.pagination_du_blog') }}">{{ $articles->links() }}</nav>
      @else
        <div class="bg-white rounded-2xl shadow-card p-12 text-center">
          <span class="text-3xl block mb-2" aria-hidden="true">✍️</span>
          <p class="text-lg font-display font-bold text-heading mb-1">{{ $this->filtresActifs() ? __('pub_blog.aucun_article_ne_correspond') : __('pub_blog.le_blog_demarre_bientot') }}</p>
          <p class="text-sm text-body/85 mb-0">{{ $this->filtresActifs() ? __('pub_blog.essayez_une_autre_recherche_ou_une_autre') : __('pub_blog.nos_premiers_decryptages_arrivent_en_attendant_le') }}</p>
        </div>
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
