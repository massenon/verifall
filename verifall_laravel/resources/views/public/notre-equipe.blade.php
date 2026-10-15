@push('styles')
  {{-- Bloc <style> que le prototype déclare en ligne dans cette page. --}}
  <link rel="stylesheet" href="{{ asset('assets/css/pages/notre-equipe.css') }}">
@endpush

{{--
  | Notre équipe — transposition de notre-equipe.html.
  |
  | Contenu, structure et classes repris du prototype. Seuls les liens et les
  | chemins d'images sont réécrits : ce sont les seules choses qu'une
  | application doit calculer plutôt que figer.
--}}
<x-layouts.public :title="$title">
    <main id="contenu">
      <section class="bg-[#f8f9fa] pt-36 pb-14 lg:pb-16">
        <div class="max-w-3xl mx-auto px-4 text-center">
          <nav class="flex items-center justify-center gap-2 text-sm text-body/70 mb-4" aria-label="{{ __('notre_equipe.fil_d_ariane') }}">
            <a href="{{ route('public.home') }}" class="hover:text-primary">{{ __('notre_equipe.accueil') }}</a><span aria-hidden="true">›</span><span class="text-heading font-medium">{{ __('notre_equipe.notre_equipe') }}</span>
          </nav>
          <h1 class="text-4xl md:text-5xl font-display font-extrabold text-heading mb-3">{{ __('notre_equipe.notre_equipe') }}</h1>
          <p class="text-lg text-body/80 mb-0">{{ __('notre_equipe.journalistes_fact_checkeurs_ingenieurs_et_pedagogues_les') }}</p>
        </div>
      </section>

      <section class="py-14 lg:py-16">
        <div class="max-w-page mx-auto px-4">
          <div class="mb-16 sm:mb-20">
            <div class="pole-heading"><p class="pole-label">{{ __('notre_equipe.fondateur') }}</p><span class="pole-line" aria-hidden="true"></span></div>
            <div class="max-w-xl mx-auto">
            <div class="team-card text-center is-founder" style="--ring-color:#09B850">
              <div class="team-avatar-ring mx-auto mb-3" role="img" aria-label="{{ __('notre_equipe.photo_a_venir', ['nom' => __('notre_equipe.rene_fadonougbo')]) }}"><span class="team-avatar-fill">RF</span></div>
              <h6 class="team-name text-base">{{ __('notre_equipe.rene_fadonougbo') }}</h6>
              <div class="team-role">{{ __('notre_equipe.fondateur') }}</div>
            </div></div>
          </div>
          <div class="mb-16 sm:mb-20">
            <div class="pole-heading"><p class="pole-label">{{ __('notre_equipe.technique_communication_design') }}</p><span class="pole-line" aria-hidden="true"></span></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            <div class="team-card text-center" style="--ring-color:#1E3A8A">
              <div class="team-avatar-ring mx-auto mb-3" role="img" aria-label="{{ __('notre_equipe.photo_a_venir', ['nom' => __('notre_equipe.rhodes_massenon')]) }}"><span class="team-avatar-fill">RM</span></div>
              <h6 class="team-name text-base">{{ __('notre_equipe.rhodes_massenon') }}</h6>
              <div class="team-role">{{ __('notre_equipe.technique') }}</div>
            </div>
            <div class="team-card text-center" style="--ring-color:#1E3A8A">
              <div class="team-avatar-ring mx-auto mb-3" role="img" aria-label="{{ __('notre_equipe.photo_a_venir', ['nom' => __('notre_equipe.charles_o_dague')]) }}"><span class="team-avatar-fill">CD</span></div>
              <h6 class="team-name text-base">{{ __('notre_equipe.charles_o_dague') }}</h6>
              <div class="team-role">{{ __('notre_equipe.communication') }}</div>
            </div>
            <div class="team-card text-center" style="--ring-color:#1E3A8A">
              <div class="team-avatar-ring mx-auto mb-3" role="img" aria-label="{{ __('notre_equipe.photo_a_venir', ['nom' => __('notre_equipe.sergino_obossou')]) }}"><span class="team-avatar-fill">SO</span></div>
              <h6 class="team-name text-base">{{ __('notre_equipe.sergino_obossou') }}</h6>
              <div class="team-role">{{ __('notre_equipe.communication') }}</div>
            </div>
            <div class="team-card text-center" style="--ring-color:#1E3A8A">
              <div class="team-avatar-ring mx-auto mb-3" role="img" aria-label="{{ __('notre_equipe.photo_a_venir', ['nom' => __('notre_equipe.kevin_sobadji')]) }}"><span class="team-avatar-fill">KS</span></div>
              <h6 class="team-name text-base">{{ __('notre_equipe.kevin_sobadji') }}</h6>
              <div class="team-role">{{ __('notre_equipe.design') }}</div>
            </div></div>
          </div>
          <div class="mb-16 sm:mb-20">
            <div class="pole-heading"><p class="pole-label">{{ __('notre_equipe.formation') }}</p><span class="pole-line" aria-hidden="true"></span></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            <div class="team-card text-center" style="--ring-color:#F7C32E">
              <div class="team-avatar-ring mx-auto mb-3" role="img" aria-label="{{ __('notre_equipe.photo_a_venir', ['nom' => __('notre_equipe.atman_bouba')]) }}"><span class="team-avatar-fill">AB</span></div>
              <h6 class="team-name text-base">{{ __('notre_equipe.atman_bouba') }}</h6>
              <div class="team-role">{{ __('notre_equipe.formation') }}</div>
            </div>
            <div class="team-card text-center" style="--ring-color:#F7C32E">
              <div class="team-avatar-ring mx-auto mb-3" role="img" aria-label="{{ __('notre_equipe.photo_a_venir', ['nom' => __('notre_equipe.gloria_tchidime')]) }}"><span class="team-avatar-fill">GT</span></div>
              <h6 class="team-name text-base">{{ __('notre_equipe.gloria_tchidime') }}</h6>
              <div class="team-role">{{ __('notre_equipe.formation') }}</div>
            </div>
            <div class="team-card text-center" style="--ring-color:#F7C32E">
              <div class="team-avatar-ring mx-auto mb-3" role="img" aria-label="{{ __('notre_equipe.photo_a_venir', ['nom' => __('notre_equipe.emmanuel_codjo')]) }}"><span class="team-avatar-fill">EC</span></div>
              <h6 class="team-name text-base">{{ __('notre_equipe.emmanuel_codjo') }}</h6>
              <div class="team-role">{{ __('notre_equipe.formation') }}</div>
            </div></div>
          </div>
          <div class="mb-16 sm:mb-20">
            <div class="pole-heading"><p class="pole-label">{{ __('notre_equipe.juridique_international_conseil_appui') }}</p><span class="pole-line" aria-hidden="true"></span></div>
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-8">
            <div class="team-card text-center" style="--ring-color:#5C7A94">
              <div class="team-avatar-ring mx-auto mb-3" role="img" aria-label="{{ __('notre_equipe.photo_a_venir', ['nom' => __('notre_equipe.romaric_zinsou')]) }}"><span class="team-avatar-fill">RZ</span></div>
              <h6 class="team-name text-base">{{ __('notre_equipe.romaric_zinsou') }}</h6>
              <div class="team-role">{{ __('notre_equipe.juridique') }}</div>
            </div>
            <div class="team-card text-center" style="--ring-color:#5C7A94">
              <div class="team-avatar-ring mx-auto mb-3" role="img" aria-label="{{ __('notre_equipe.photo_a_venir', ['nom' => __('notre_equipe.souad_adechoubou')]) }}"><span class="team-avatar-fill">SA</span></div>
              <h6 class="team-name text-base">{{ __('notre_equipe.souad_adechoubou') }}</h6>
              <div class="team-role">{{ __('notre_equipe.international') }}</div>
            </div>
            <div class="team-card text-center" style="--ring-color:#5C7A94">
              <div class="team-avatar-ring mx-auto mb-3" role="img" aria-label="{{ __('notre_equipe.photo_a_venir', ['nom' => __('notre_equipe.herbert_ebo')]) }}"><span class="team-avatar-fill">HE</span></div>
              <h6 class="team-name text-base">{{ __('notre_equipe.herbert_ebo') }}</h6>
              <div class="team-role">{{ __('notre_equipe.conseil_appui') }}</div>
            </div></div>
          </div>
        </div>
      </section>

      <section class="py-14" style="background:#F6F8F7">
        <div class="max-w-page mx-auto px-4 grid lg:grid-cols-2 gap-12 items-center">
          <div>
            <h2 class="text-2xl font-display font-bold text-heading mb-4">{{ __('notre_equipe.le_mot_du_fondateur') }}</h2>
            <blockquote class="leading-relaxed text-body/85 mb-4">{{ __('notre_equipe.citation_du_fondateur') }}</blockquote>
            <p class="font-bold text-heading mb-0">{!! __('notre_equipe.rene_fadonougbo_fondateur_directeur_de_la_publication') !!}</p>
          </div>
          <img src="{{ asset('assets/images/landing/team/rene-fadonougbo.jpeg') }}" alt="{{ __('notre_equipe.portrait_du_fondateur') }}" width="520" height="420" loading="lazy" class="rounded-xl w-full object-cover bg-white" style="max-height:420px" data-reveal>
        </div>
      </section>

      <section class="py-14">
        <div class="max-w-page mx-auto px-4 text-center">
          <h2 class="text-2xl font-display font-bold text-heading mb-3">{{ __('notre_equipe.envie_de_rejoindre_la_mission') }}</h2>
          <p class="mb-6 max-w-xl mx-auto text-body/80">{{ __('notre_equipe.nous_recherchons_regulierement_des_mentors_fact_checkeurs') }}</p>
          <a href="{{ route('public.contact') }}" class="inline-flex items-center rounded-lg bg-primary text-white text-[0.9rem] font-semibold px-6 py-3 hover:bg-primary-dark transition-colors">{{ __('notre_equipe.nous_contacter') }}</a>
        </div>
      </section>
    </main>
</x-layouts.public>
