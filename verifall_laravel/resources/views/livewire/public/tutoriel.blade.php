@push('styles')
  {{-- `.course-tab` et `.course-tab.is-active` vivent ICI. Sans cette ligne,
       les quatre onglets s'affichent en texte nu et l'onglet actif ne se
       distingue pas — le défaut signalé sur cette page. --}}
  <link rel="stylesheet" href="{{ asset('assets/css/pages/tutoriels-details.css') }}">
@endpush

{{--
  | Fiche publique d'un cours — transposition de tutoriels-details.html.
  |
  | Cinq onglets, colonne latérale collante, puis « Explorez d'autres
  | tutoriels ». Les étoiles n'apparaissent que si quelqu'un a noté : une
  | moyenne calculée sur zéro avis serait un chiffre inventé, sur la plateforme
  | dont le métier est de dire ce qui est vrai.
--}}
<main id="contenu">
  <section class="pt-28 lg:pt-32 pb-10 bg-[#F6F8F7] border-b border-line">
    <div class="max-w-page mx-auto px-4">
      <nav aria-label="{{ __('pub_tutoriel.fil_d_ariane') }}">
        <ol class="flex flex-wrap items-center gap-2 text-sm">
          <li><a href="{{ route('public.home') }}" class="text-heading/50 hover:text-heading">{{ __('pub_tutoriel.accueil') }}</a></li>
          <li class="text-[#8a94a6]" aria-hidden="true">•</li>
          <li><a href="{{ route('public.tutorials') }}" class="text-heading/50 hover:text-heading">{{ __('pub_tutoriel.tutoriels') }}</a></li>
          <li class="text-[#8a94a6]" aria-hidden="true">•</li>
          <li class="text-heading truncate max-w-[250px]" aria-current="page">{{ $cours->title }}</li>
        </ol>
      </nav>
      <div class="mt-6 max-w-3xl">
        <span class="inline-block rounded-full bg-primary text-heading text-sm font-semibold px-4 py-1.5 mb-4">{{ $cours->category }}</span>
        <h1 class="text-3xl md:text-4xl font-display font-extrabold text-heading mb-4">{{ $cours->title }}</h1>
        <p class="text-body mb-6">{{ $cours->description }}</p>
        <div class="flex items-center flex-wrap gap-2 text-sm">
          @if($cours->reviews_count > 0)
            <span class="flex items-center gap-0.5"><strong class="text-heading">{{ number_format((float) $cours->rating, 1, ',', ' ') }}</strong></span>
            <span class="text-heading/30 mx-2 hidden sm:inline" aria-hidden="true">|</span>
            <a href="#tab-reviews" class="text-heading/50 font-semibold hover:text-heading">{{ $cours->reviews_count }} {{ trans_choice('pub_tutoriel.avis', $cours->reviews_count) }}</a>
          @else
            <span class="rounded-full bg-primary/10 text-primary text-xs font-bold px-3 py-1">{{ __('pub_tutoriel.nouveau') }}</span>
          @endif
          <span class="text-heading/30 mx-2 hidden sm:inline" aria-hidden="true">|</span>
          <span class="text-heading/50">{{ $cours->lessons->count() }} {{ trans_choice('pub_tutoriel.lecons', $cours->lessons->count()) }}</span>
          <span class="text-heading/30 mx-2 hidden sm:inline" aria-hidden="true">|</span>
          <span class="text-heading/50">{{ $cours->duration_label }}</span>
        </div>
      </div>
    </div>
  </section>

  <section class="py-12">
    <div class="max-w-page mx-auto px-4">
      <div class="grid lg:grid-cols-3 gap-8 lg:gap-12">
        <div class="lg:col-span-2">
          <div class="border-b border-line" data-tabs role="tablist" aria-label="{{ __('pub_tutoriel.sections_du_cours') }}">
            <ul class="flex flex-nowrap overflow-x-auto -mb-px">
              <li><button type="button" class="course-tab is-active" data-tab="overview" role="tab" aria-selected="true">{{ __('pub_tutoriel.apercu') }}</button></li>
              <li><button type="button" class="course-tab" data-tab="curriculum" role="tab" aria-selected="false">{{ __('pub_tutoriel.curriculum') }}</button></li>
              <li><button type="button" class="course-tab" data-tab="instructor" role="tab" aria-selected="false">{{ __('pub_tutoriel.instructeur') }}</button></li>
              <li><button type="button" class="course-tab" data-tab="reviews" role="tab" aria-selected="false">{{ __('pub_tutoriel.avis_onglet') }}</button></li>
            </ul>
          </div>
          <div class="mt-6" data-tabs-scope>
            <div data-tab-panel="overview" class="is-active">
              @if(!empty($cours->outcomes))
                <h5 class="text-lg font-display font-bold text-heading mb-4">{{ __('pub_tutoriel.ce_que_vous_allez_apprendre') }}</h5>
                <ul class="grid md:grid-cols-2 gap-3 mb-8">
                  @foreach($cours->outcomes as $acquis)
                    <li class="flex gap-2 items-start"><span class="text-primary mt-1" aria-hidden="true">✓</span><span>{{ $acquis }}</span></li>
                  @endforeach
                </ul>
              @endif
              <h5 class="text-lg font-display font-bold text-heading mb-4">{{ __('pub_tutoriel.description_du_cours') }}</h5>
              <div class="leading-relaxed mb-10">
                <p class="mb-0">{{ $cours->description }}</p>
              </div>
            </div>

            <div data-tab-panel="curriculum">
              <h5 class="text-lg font-display font-bold text-heading mb-4">{{ __('pub_tutoriel.programme') }}</h5>
              <div data-accordion="multi">
                <div class="accordion-item mb-3 !bg-white">
                  <button class="accordion-button" type="button" aria-expanded="true">{{ __('pub_tutoriel.lecons_titre') }}<span class="acc-icon" aria-hidden="true">▾</span></button>
                  <div class="accordion-body">
                    @forelse($cours->lessons as $lecon)
                      <div class="flex justify-between items-center py-2 border-b border-line last:border-0">
                        <span class="flex items-center gap-2 text-heading">{{ $lecon->position }}. {{ $lecon->title }}</span>
                        <span class="text-sm text-body/70">{{ $lecon->minutes }} {{ __('pub_tutoriel.min') }}</span>
                      </div>
                    @empty
                      <p class="text-sm text-body/70 mb-0">{{ __('pub_tutoriel.le_programme_de_ce_cours_est_en') }}</p>
                    @endforelse
                  </div>
                </div>
                @if($cours->quizzes->isNotEmpty())
                  <div class="accordion-item mb-3 !bg-white">
                    <button class="accordion-button" type="button" aria-expanded="false">{{ __('pub_tutoriel.evaluations') }}<span class="acc-icon" aria-hidden="true">▾</span></button>
                    <div class="accordion-body">
                      @foreach($cours->quizzes as $quiz)
                        <div class="flex justify-between items-center py-2 border-b border-line last:border-0">
                          <span class="flex items-center gap-2 text-heading">{{ $quiz->title }}</span>
                          <span class="text-sm text-body/70">{{ __('pub_tutoriel.minimum') }} {{ $quiz->pass_score }} %</span>
                        </div>
                      @endforeach
                    </div>
                  </div>
                @endif
              </div>
            </div>

            <div data-tab-panel="instructor">
              <div class="border border-line rounded-xl p-6">
                @if($cours->instructor)
                  <div class="flex items-center mb-5">
                    <span class="inline-flex items-center justify-center w-14 h-14 rounded-full bg-primary/10 text-primary font-bold text-lg shrink-0" aria-hidden="true">{{ Str::of($cours->instructor->name)->substr(0, 2)->upper() }}</span>
                    <div class="min-w-0 ms-4">
                      <h5 class="text-lg font-display font-bold text-heading mb-0.5">{{ $cours->instructor->name }}</h5>
                      <p class="text-sm text-body/70 mb-0">{{ $cours->instructor->profession ?: __('pub_tutoriel.formateur_verif_all') }}</p>
                    </div>
                  </div>
                  @if($cours->instructor->bio)
                    <p class="text-sm text-body/85 mb-0">{{ $cours->instructor->bio }}</p>
                  @endif
                @else
                  <p class="text-sm text-body/70 mb-0">{{ __('pub_tutoriel.ce_cours_est_porte_par_l_equipe') }}</p>
                @endif
              </div>
            </div>

            <div data-tab-panel="reviews" id="tab-reviews">
              <h2 class="text-xl font-display font-bold text-heading mb-6">{{ __('pub_tutoriel.notes_avis_des_apprenants') }}</h2>
              @if($avis->isNotEmpty())
                <div class="grid md:grid-cols-12 gap-6">
                  <div class="md:col-span-5">
                    <div class="border border-line rounded-xl p-6 text-center">
                      <h1 class="text-6xl font-display font-extrabold text-primary mb-2">{{ number_format((float) $cours->rating, 1, ',', ' ') }}</h1>
                      <p class="text-sm text-body/70 mb-4">{{ __('pub_tutoriel.base_sur') }} {{ $cours->reviews_count }} {{ trans_choice('pub_tutoriel.avis', $cours->reviews_count) }}</p>
                      <div class="space-y-2">
                        @foreach($repartition as $note => $ligne)
                          <div class="flex items-center justify-center gap-2">
                            <span class="text-sm text-body/70 w-3">{{ $note }}</span>
                            <span class="w-full max-w-[150px] h-1.5 rounded-full bg-line overflow-hidden"><span class="block h-full rounded-full bg-warning" style="width:{{ $ligne['part'] }}%"></span></span>
                            <span class="text-sm text-body/70 w-6 text-left">{{ $ligne['nombre'] }}</span>
                          </div>
                        @endforeach
                      </div>
                    </div>
                  </div>
                  <div class="md:col-span-7">
                    @foreach($avis as $unAvis)
                      <div class="flex mb-4">
                        <span class="inline-flex items-center justify-center w-11 h-11 rounded-full bg-primary/10 text-primary font-bold text-sm shrink-0" aria-hidden="true">{{ Str::of($unAvis->user?->name ?? '?')->substr(0, 2)->upper() }}</span>
                        <div class="flex-1 ms-3">
                          <div class="flex justify-between items-center mb-1">{{ $unAvis->user?->name ?? __('pub_tutoriel.compte_supprime') }}<small class="text-body/60">{{ $unAvis->created_at->translatedFormat('j M Y') }}</small></div>
                          <p class="text-sm text-body/85 mb-0">{{ $unAvis->comment }}</p>
                        </div>
                      </div>
                    @endforeach
                  </div>
                </div>
              @else
                {{-- Pas d'avis, pas de note : une moyenne sur zéro avis
                     n'existe pas, et l'afficher tromperait. --}}
                <p class="text-sm text-body/70 mb-0">{{ __('pub_tutoriel.ce_cours_n_a_pas_encore_ete_note') }}</p>
              @endif
            </div>
          </div>
        </div>

        <div class="lg:col-span-1">
          <div class="lg:sticky lg:top-24">
            <div class="border border-line rounded-xl p-4 shadow-card bg-white">
              @if($cours->cover_path)
                <div class="rounded-lg overflow-hidden mb-4"><img src="{{ asset($cours->cover_path) }}" alt="{{ $cours->title }}" class="w-full object-cover" style="aspect-ratio:16/9" loading="lazy"></div>
              @endif
              <div class="flex justify-between items-center mb-4">
                <span class="rounded-full bg-dark text-white text-sm px-4 py-1.5">{{ $cours->level->libelle() }}</span>
                <span class="rounded-full bg-primary text-white text-sm font-bold px-4 py-1.5">{{ $cours->cost_units === 0 ? __('pub_tutoriel.gratuit') : $cours->cost_units.' '.trans_choice('pub_tutoriel.unites_majuscules', $cours->cost_units) }}</span>
              </div>
              <a href="{{ route('register') }}" class="w-full inline-flex justify-center items-center rounded-lg bg-primary text-white font-semibold px-4 py-3 hover:bg-primary-dark transition-colors">{{ $cours->cost_units === 0 ? __('pub_tutoriel.s_inscrire_gratuitement') : __('pub_tutoriel.s_inscrire') }}</a>
              <ul class="mt-5 text-sm">
                <li class="flex justify-between py-2 border-b border-line"><span class="text-body/75">{{ __('pub_tutoriel.langue') }}</span><strong class="text-heading">{{ __('pub_tutoriel.francais') }}</strong></li>
                <li class="flex justify-between py-2 border-b border-line"><span class="text-body/75">{{ __('pub_tutoriel.duree') }}</span><strong class="text-heading">{{ $cours->duration_label }}</strong></li>
                <li class="flex justify-between py-2"><span class="text-body/75">{{ __('pub_tutoriel.certificat') }}</span><strong class="text-heading">{{ $cours->delivers_certificate ? __('pub_tutoriel.oui') : __('pub_tutoriel.non') }}</strong></li>
              </ul>
              <h6 class="pt-4 mb-3 font-bold text-sm text-heading">{{ __('pub_tutoriel.partager_ce_tutoriel') }}</h6>
              <ul class="flex gap-2">
                <li><a href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('pub_tutoriel.partager_sur_facebook') }}" class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-[#f5f6f7] text-[#5d82d1] hover:bg-[#eceef0]">f</a></li>
                <li><a href="https://api.whatsapp.com/send?text={{ urlencode($cours->title.' - '.url()->current()) }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('pub_tutoriel.partager_sur_whatsapp') }}" class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-[#f5f6f7] text-primary hover:bg-[#eceef0]">w</a></li>
                <li><a href="https://twitter.com/intent/tweet?url={{ urlencode(url()->current()) }}" target="_blank" rel="noopener noreferrer" aria-label="{{ __('pub_tutoriel.partager_sur_x') }}" class="inline-flex items-center justify-center w-9 h-9 rounded-full bg-[#f5f6f7] text-[#1da1f2] hover:bg-[#eceef0]">x</a></li>
              </ul>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>

  @if($autres->isNotEmpty())
    <section class="pb-24">
      <div class="max-w-page mx-auto px-4">
        <div class="flex flex-col sm:flex-row justify-between sm:items-center gap-2 mb-6">
          <h3 class="text-2xl font-display font-bold text-heading mb-0">{{ __('pub_tutoriel.explorez_d_autres_tutoriels') }}</h3>
          <a href="{{ route('public.tutorials') }}" class="font-semibold text-primary">{{ __('pub_tutoriel.voir_tous_les_tutoriels') }}</a>
        </div>
        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-6">
          @foreach($autres as $autre)
            <div class="bg-transparent border border-line rounded-xl overflow-hidden h-full flex flex-col hover:shadow-cardlg hover:-translate-y-1 transition-all" wire:key="autre-{{ $autre->id }}">
              <div class="bg-[#f8f9fa] rounded-lg m-2 relative overflow-hidden" style="aspect-ratio:16/9">
                @if($autre->cover_path)
                  <img src="{{ asset($autre->cover_path) }}" class="w-full h-full object-cover" alt="{{ $autre->title }}" loading="lazy">
                @endif
              </div>
              <div class="px-4 pt-2 flex-1">
                <span class="inline-block rounded-md bg-dark text-white text-xs px-2 py-0.5 mb-2">{{ $autre->level->libelle() }}</span>
                <h6 class="font-display font-bold leading-snug mb-2"><a href="{{ route('public.tutorial', $autre) }}" class="card-title-link text-heading">{{ $autre->title }}</a></h6>
              </div>
              <div class="p-4 pt-3 mt-auto">
                <div class="grid grid-cols-2 gap-2 items-center">
                  <span class="font-bold text-primary text-sm">{{ $autre->cost_units === 0 ? __('pub_tutoriel.acces_libre') : $autre->cost_units.' '.trans_choice('pub_tutoriel.unites', $autre->cost_units) }}</span>
                  <a href="{{ route('public.tutorial', $autre) }}" class="inline-flex justify-center items-center rounded-lg bg-primary text-white text-sm font-semibold px-3 py-2 hover:bg-primary-dark transition-colors">{{ __('pub_tutoriel.decouvrir') }}</a>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      </div>
    </section>
  @endif
</main>
