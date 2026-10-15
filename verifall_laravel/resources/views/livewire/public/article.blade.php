@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/pages/blog-details.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/pages/public-facts.css') }}">
  {{-- Le bloc « cette vérification a été corrigée » emprunte la pastille barrée
       du journal des corrections : elle vit dans cette feuille-là. --}}
  <link rel="stylesheet" href="{{ asset('assets/css/pages/transparence.css') }}">
@endpush

{{--
  | Une vérification publiée — transposition de facts-details.html.
  |
  | Publique et sans compte : un démenti que seuls les inscrits peuvent lire ne
  | dément rien. C'est cette page qui circulera en réponse à la rumeur, et
  | c'est pourquoi le verdict est la première chose qu'on y lit.
--}}
<main id="contenu">
  <section class="pt-36 pb-16">
    <div class="max-w-4xl mx-auto px-4">
      <div class="text-center mb-10">
        <span class="{{ $article->verdict?->pastilleVerdict() ?? 'fact-verdict fact-verdict--inconnu' }}">{{ __('pub_article.verdict') }} {{ $article->libelleDuBandeau() }}</span>
        <h1 class="text-3xl md:text-4xl font-display font-bold text-heading leading-tight">{{ $article->title }}</h1>
        @if($article->excerpt)
          <p class="text-lg text-body/85 mt-4 max-w-2xl mx-auto leading-relaxed">{{ $article->excerpt }}</p>
        @endif
        <p class="text-sm text-body/70 mt-3">{{ __('pub_article.publie_le') }} {{ $article->published_at?->translatedFormat('j M Y') }} • {{ __('pub_article.par_l_equipe_verif_all') }}</p>
      </div>

      <div class="bg-white rounded-2xl shadow-card p-6 md:p-12 mb-12">
        @if($article->cover_path)
          <img src="{{ asset($article->cover_path) }}" alt="{{ $article->title }}" loading="lazy" class="w-full rounded-xl mb-8" style="aspect-ratio:16/9;object-fit:cover">
        @endif

        {{-- Purifié à l'affichage : cette page rend du HTML rédigé par un
             humain, et une seule vue qui l'oublierait suffirait. --}}
        <div class="prose-vf text-[#606261]">
          {!! clean($article->body ?? '') !!}
        </div>

        @if($article->source_url)
          <div class="mt-10 p-4 bg-[#f8f9fa] rounded-lg border-l-4 border-primary">
            <h6 class="text-sm font-bold text-heading mb-1">{{ __('pub_article.source_officielle_de_preuve') }}</h6>
            {{-- `nofollow` : on ne transmet pas notre autorité de référencement
                 à ce qu'on est en train de démentir. --}}
            <a href="{{ route('public.fact.source', $article) }}" target="_blank" rel="noopener nofollow" class="text-sm text-primary inline-flex items-start flex-wrap gap-1">
              @if($article->source_name)
                <span class="font-semibold text-heading shrink-0">{{ $article->source_name }}</span>
                <span class="shrink-0">—</span>
              @endif
              <span class="break-all">{{ $article->source_url }}</span>
              <span class="material-symbols-rounded !text-[15px] shrink-0" aria-hidden="true">open_in_new</span>
            </a>
          </div>
        @endif

        <div class="mt-8 pt-6 border-t border-line flex items-center justify-between flex-wrap gap-3">
          <p class="text-sm font-semibold text-heading mb-0">{{ __('pub_article.partager_cette_verification') }}</p>
          <x-menu-partage
            :lien="route('public.fact', $article)"
            :verdict="$article->verdict"
            :affirmation="$article->claim ?? $article->title"
            aligne="droite" />
        </div>
      </div>

      {{-- CETTE VÉRIFICATION A ÉTÉ CORRIGÉE.
           Placé juste sous l'analyse, avant l'appel à l'action : quelqu'un qui
           arrive ici par un partage doit apprendre que le verdict a changé
           AVANT de repartager. Un renvoi en bas de page n'atteindrait personne. --}}
      @if($corrections->isNotEmpty())
        <div class="bg-white rounded-2xl shadow-card border border-line p-6 md:p-12 mb-12">
          <h2 class="text-xl font-display font-bold text-heading mb-4">{{ __('pub_article.cette_verification_a_ete_corrigee') }}</h2>
          <ol class="list-none space-y-6 mb-4">
            @foreach($corrections as $correction)
              <li wire:key="correction-{{ $correction->uuid }}">
                <div class="flex items-center flex-wrap gap-2 mb-2">
                  <span class="verdict-ancien">{{ $correction->ancien_verdict->libelle() }}</span>
                  <svg class="verdict-fleche" width="18" height="18" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/></svg>
                  <span class="{{ $correction->nouveau_verdict->badgePublic() }}">{{ $correction->nouveau_verdict->libelle() }}</span>
                  <time class="ml-auto text-sm text-body/70" datetime="{{ $correction->created_at?->toDateString() }}">
                    {{ $correction->created_at?->translatedFormat('j F Y') }}
                  </time>
                </div>
                <p class="text-body/85 leading-relaxed mb-0">{{ $correction->raison }}</p>
              </li>
            @endforeach
          </ol>
          <a href="{{ route('public.corrections') }}" class="inline-flex items-center gap-1.5 text-primary text-sm font-bold hover:gap-3 transition-all">
            {{ __('pub_article.voir_toutes_nos_corrections') }}
            <svg width="14" height="14" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M1 8a.5.5 0 0 1 .5-.5h11.793l-3.147-3.146a.5.5 0 0 1 .708-.708l4 4a.5.5 0 0 1 0 .708l-4 4a.5.5 0 0 1-.708-.708L13.293 8.5H1.5A.5.5 0 0 1 1 8"/></svg>
          </a>
        </div>
      @endif

      <div class="text-center bg-dark text-white p-10 md:p-12 rounded-2xl shadow-cardlg mb-12">
        <h4 class="text-2xl md:text-3xl font-display font-bold text-white mb-3">{{ __('pub_article.vous_avez_un_doute_sur_une_info') }}</h4>
        <p class="text-white/75 mb-6">{{ __('pub_article.faites_verifier_gratuitement_un_lien_ou_un') }}</p>
        <a href="{{ route('public.verify') }}" class="inline-flex items-center rounded-full bg-primary text-white font-semibold px-10 py-3 hover:bg-primary-dark transition-colors">{{ __('pub_article.verifier_une_information_gratuitement') }}</a>
      </div>

      @if($voisines->isNotEmpty())
        <div>
          <h5 class="text-xl font-display font-bold text-heading mb-6">{{ __('pub_article.analyses_similaires') }}</h5>
          <div class="grid md:grid-cols-3 gap-6">
            @foreach($voisines as $voisine)
              <div class="bg-white rounded-xl shadow-card hover:shadow-cardlg transition-shadow p-4" wire:key="voisine-{{ $voisine->id }}">
                <h6 class="text-sm font-semibold leading-snug">
                  <a href="{{ route('public.fact', $voisine) }}" class="card-title-link text-heading">{{ $voisine->title }}</a>
                </h6>
              </div>
            @endforeach
          </div>
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
