@push('styles')
  {{-- Bloc <style> que le prototype déclare en ligne dans cette page. --}}
  <link rel="stylesheet" href="{{ asset('assets/css/pages/medias.css') }}">
@endpush

{{--
  | Médias — transposition de medias.html.
  |
  | Contenu, structure et classes repris du prototype. Seuls les liens et les
  | chemins d'images sont réécrits : ce sont les seules choses qu'une
  | application doit calculer plutôt que figer.
--}}
<x-layouts.public :title="$title">
    <main id="contenu">
      <section class="pt-32 pb-8">
        <div class="max-w-page mx-auto px-4">
          <div class="bg-dark rounded-xl relative overflow-hidden p-6 sm:p-14">
            <div class="grid lg:grid-cols-12 gap-8 lg:gap-10 items-center">
              <div class="lg:col-span-7">
                <span class="inline-block rounded-full bg-primary text-white text-sm font-semibold px-4 py-1.5 mb-4">{{ __('medias.lancement_de_verif_all') }}</span>
                <h1 class="text-3xl md:text-4xl font-display font-bold text-white mb-4">{{ __('medias.les_medias_parlent_de_nous') }}</h1>
                <p class="text-white/80 mb-4">{!! __('medias.verifall_a_ete_officiellement_lance_le_16') !!}</p>
                <div class="flex flex-wrap items-center gap-4 text-white/80 text-sm">
                  <span class="inline-flex items-center gap-1.5"><svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M11 6.5a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5h-1a.5.5 0 0 1-.5-.5z"/><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5M1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4z"/></svg>{{ __('medias.date_du_lancement') }}</span>
                  <span class="inline-flex items-center gap-1.5"><svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A32 32 0 0 1 8 14.58a32 32 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10"/><path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4m0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/></svg>{{ __('medias.lieu_du_lancement') }}</span>
                </div>
              </div>
              <div class="lg:col-span-5">
                <div class="press-tile on-dark" role="img" aria-label="{{ __('medias.photo_officielle_du_lancement_a_venir') }}">
                  <svg width="28" height="28" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M15 12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h1.172a3 3 0 0 0 2.12-.879l.83-.828A1 1 0 0 1 6.827 3h2.344a1 1 0 0 1 .707.293l.828.828A3 3 0 0 0 12.828 5H14a1 1 0 0 1 1 1zM2 4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-1.172a2 2 0 0 1-1.414-.586l-.828-.828A2 2 0 0 0 9.172 2H6.828a2 2 0 0 0-1.414.586l-.828.828A2 2 0 0 1 3.172 4z"/><path d="M8 11a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5m0 1a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7M3 6.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0"/></svg>
                  <span class="text-sm font-semibold mt-2">{{ __('medias.photo_officielle_du_lancement_a_venir') }}</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="py-16">
        <div class="max-w-page mx-auto px-4">
          <div class="max-w-2xl mx-auto text-center mb-12">
            <h2 class="text-3xl md:text-4xl font-display font-bold text-heading mb-2">{{ __('medias.galerie_du_lancement') }}</h2>
            <p class="text-body/85">{{ __('medias.conference_de_presse_ceremonie_officielle_et_temps') }}</p>
          </div>
          <div class="grid grid-cols-2 md:grid-cols-3 gap-4 sm:gap-6">
            <div class="press-tile" role="img" aria-label="{{ __('medias.photo_de_l_evenement_a_venir') }}">
              <svg width="28" height="28" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M15 12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h1.172a3 3 0 0 0 2.12-.879l.83-.828A1 1 0 0 1 6.827 3h2.344a1 1 0 0 1 .707.293l.828.828A3 3 0 0 0 12.828 5H14a1 1 0 0 1 1 1zM2 4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-1.172a2 2 0 0 1-1.414-.586l-.828-.828A2 2 0 0 0 9.172 2H6.828a2 2 0 0 0-1.414.586l-.828.828A2 2 0 0 1 3.172 4z"/><path d="M8 11a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5m0 1a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7M3 6.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0"/></svg>
              <span class="text-sm font-semibold mt-2">{{ __('medias.photo_de_l_evenement_a_venir') }}</span>
            </div>
            <div class="press-tile" role="img" aria-label="{{ __('medias.photo_de_l_evenement_a_venir') }}">
              <svg width="28" height="28" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M15 12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h1.172a3 3 0 0 0 2.12-.879l.83-.828A1 1 0 0 1 6.827 3h2.344a1 1 0 0 1 .707.293l.828.828A3 3 0 0 0 12.828 5H14a1 1 0 0 1 1 1zM2 4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-1.172a2 2 0 0 1-1.414-.586l-.828-.828A2 2 0 0 0 9.172 2H6.828a2 2 0 0 0-1.414.586l-.828.828A2 2 0 0 1 3.172 4z"/><path d="M8 11a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5m0 1a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7M3 6.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0"/></svg>
              <span class="text-sm font-semibold mt-2">{{ __('medias.photo_de_l_evenement_a_venir') }}</span>
            </div>
            <div class="press-tile" role="img" aria-label="{{ __('medias.photo_de_l_evenement_a_venir') }}">
              <svg width="28" height="28" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M15 12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h1.172a3 3 0 0 0 2.12-.879l.83-.828A1 1 0 0 1 6.827 3h2.344a1 1 0 0 1 .707.293l.828.828A3 3 0 0 0 12.828 5H14a1 1 0 0 1 1 1zM2 4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-1.172a2 2 0 0 1-1.414-.586l-.828-.828A2 2 0 0 0 9.172 2H6.828a2 2 0 0 0-1.414.586l-.828.828A2 2 0 0 1 3.172 4z"/><path d="M8 11a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5m0 1a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7M3 6.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0"/></svg>
              <span class="text-sm font-semibold mt-2">{{ __('medias.photo_de_l_evenement_a_venir') }}</span>
            </div>
            <div class="press-tile" role="img" aria-label="{{ __('medias.photo_de_l_evenement_a_venir') }}">
              <svg width="28" height="28" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M15 12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h1.172a3 3 0 0 0 2.12-.879l.83-.828A1 1 0 0 1 6.827 3h2.344a1 1 0 0 1 .707.293l.828.828A3 3 0 0 0 12.828 5H14a1 1 0 0 1 1 1zM2 4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-1.172a2 2 0 0 1-1.414-.586l-.828-.828A2 2 0 0 0 9.172 2H6.828a2 2 0 0 0-1.414.586l-.828.828A2 2 0 0 1 3.172 4z"/><path d="M8 11a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5m0 1a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7M3 6.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0"/></svg>
              <span class="text-sm font-semibold mt-2">{{ __('medias.photo_de_l_evenement_a_venir') }}</span>
            </div>
            <div class="press-tile" role="img" aria-label="{{ __('medias.photo_de_l_evenement_a_venir') }}">
              <svg width="28" height="28" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M15 12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h1.172a3 3 0 0 0 2.12-.879l.83-.828A1 1 0 0 1 6.827 3h2.344a1 1 0 0 1 .707.293l.828.828A3 3 0 0 0 12.828 5H14a1 1 0 0 1 1 1zM2 4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-1.172a2 2 0 0 1-1.414-.586l-.828-.828A2 2 0 0 0 9.172 2H6.828a2 2 0 0 0-1.414.586l-.828.828A2 2 0 0 1 3.172 4z"/><path d="M8 11a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5m0 1a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7M3 6.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0"/></svg>
              <span class="text-sm font-semibold mt-2">{{ __('medias.photo_de_l_evenement_a_venir') }}</span>
            </div>
            <div class="press-tile" role="img" aria-label="{{ __('medias.photo_de_l_evenement_a_venir') }}">
              <svg width="28" height="28" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M15 12a1 1 0 0 1-1 1H2a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1h1.172a3 3 0 0 0 2.12-.879l.83-.828A1 1 0 0 1 6.827 3h2.344a1 1 0 0 1 .707.293l.828.828A3 3 0 0 0 12.828 5H14a1 1 0 0 1 1 1zM2 4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-1.172a2 2 0 0 1-1.414-.586l-.828-.828A2 2 0 0 0 9.172 2H6.828a2 2 0 0 0-1.414.586l-.828.828A2 2 0 0 1 3.172 4z"/><path d="M8 11a2.5 2.5 0 1 1 0-5 2.5 2.5 0 0 1 0 5m0 1a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7M3 6.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0"/></svg>
              <span class="text-sm font-semibold mt-2">{{ __('medias.photo_de_l_evenement_a_venir') }}</span>
            </div>
          </div>
        </div>
      </section>

      <section class="py-16 bg-[#f8f9fa]">
        <div class="max-w-page mx-auto px-4">
          <div class="max-w-2xl mx-auto text-center mb-12">
            <h2 class="text-3xl md:text-4xl font-display font-bold text-heading mb-2">{{ __('medias.revue_de_presse') }}</h2>
            <p class="text-body/85">{{ __('medias.les_medias_ci_dessous_sont_des_exemples') }}</p>
          </div>
          <div class="grid md:grid-cols-2 lg:grid-cols-3 gap-6">
            <div class="press-card p-6 flex flex-col h-full">
              <div class="flex items-center gap-3 mb-4">
                <div class="press-logo">A</div>
                <div><div class="font-bold text-heading">{{ __('medias.media_a') }}</div><div class="text-sm text-body/70">{{ __('medias.date_a_confirmer') }}</div></div>
              </div>
              <p class="text-sm text-body/70 mb-4 flex-1">{{ __('medias.titre_de_l_article_a_confirmer') }}</p>
              <span class="self-start rounded-full text-xs font-semibold px-3 py-1 mb-4" style="background:rgba(247,195,46,.15);color:#a97c00">{{ __('medias.exemple_a_remplacer') }}</span>
              <span class="text-sm font-semibold text-body/60 inline-flex items-center gap-1" aria-disabled="true">{{ __('medias.lire_l_article') }}
                <svg width="12" height="12" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5"/><path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0z"/></svg>
              </span>
            </div>
            <div class="press-card p-6 flex flex-col h-full">
              <div class="flex items-center gap-3 mb-4">
                <div class="press-logo">B</div>
                <div><div class="font-bold text-heading">{{ __('medias.media_b') }}</div><div class="text-sm text-body/70">{{ __('medias.date_a_confirmer') }}</div></div>
              </div>
              <p class="text-sm text-body/70 mb-4 flex-1">{{ __('medias.titre_de_l_article_a_confirmer') }}</p>
              <span class="self-start rounded-full text-xs font-semibold px-3 py-1 mb-4" style="background:rgba(247,195,46,.15);color:#a97c00">{{ __('medias.exemple_a_remplacer') }}</span>
              <span class="text-sm font-semibold text-body/60 inline-flex items-center gap-1" aria-disabled="true">{{ __('medias.lire_l_article') }}
                <svg width="12" height="12" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5"/><path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0z"/></svg>
              </span>
            </div>
            <div class="press-card p-6 flex flex-col h-full">
              <div class="flex items-center gap-3 mb-4">
                <div class="press-logo">C</div>
                <div><div class="font-bold text-heading">{{ __('medias.media_c') }}</div><div class="text-sm text-body/70">{{ __('medias.date_a_confirmer') }}</div></div>
              </div>
              <p class="text-sm text-body/70 mb-4 flex-1">{{ __('medias.titre_de_l_article_a_confirmer') }}</p>
              <span class="self-start rounded-full text-xs font-semibold px-3 py-1 mb-4" style="background:rgba(247,195,46,.15);color:#a97c00">{{ __('medias.exemple_a_remplacer') }}</span>
              <span class="text-sm font-semibold text-body/60 inline-flex items-center gap-1" aria-disabled="true">{{ __('medias.lire_l_article') }}
                <svg width="12" height="12" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path fill-rule="evenodd" d="M8.636 3.5a.5.5 0 0 0-.5-.5H1.5A1.5 1.5 0 0 0 0 4.5v10A1.5 1.5 0 0 0 1.5 16h10a1.5 1.5 0 0 0 1.5-1.5V7.864a.5.5 0 0 0-1 0V14.5a.5.5 0 0 1-.5.5h-10a.5.5 0 0 1-.5-.5v-10a.5.5 0 0 1 .5-.5h6.636a.5.5 0 0 0 .5-.5"/><path fill-rule="evenodd" d="M16 .5a.5.5 0 0 0-.5-.5h-5a.5.5 0 0 0 0 1h3.793L6.146 9.146a.5.5 0 1 0 .708.708L15 1.707V5.5a.5.5 0 0 0 1 0z"/></svg>
              </span>
            </div>
          </div>
        </div>
      </section>

      <section class="py-16">
        <div class="max-w-page mx-auto px-4">
          <div class="bg-dark rounded-xl relative overflow-hidden p-6 sm:p-14 text-center">
            <div class="max-w-2xl mx-auto">
              <h3 class="text-2xl md:text-3xl font-display font-bold text-white mb-3">{{ __('medias.vous_aussi_rejoignez_le_mouvement') }}</h3>
              <p class="text-white/80 mb-6">{{ __('medias.verifiez_votre_premiere_information_gratuitement_et_decouvre') }}</p>
              <a href="{{ route('public.verify') }}" class="inline-flex items-center rounded-lg bg-primary text-white font-semibold px-5 py-2.5 hover:bg-primary-dark transition-colors">{{ __('medias.verifier_une_information_maintenant') }}</a>
            </div>
          </div>
        </div>
      </section>
    </main>
</x-layouts.public>
