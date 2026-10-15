<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? "Vérif'All" }}</title>
    {{-- Les écrans d'authentification ne sont jamais indexés. --}}
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ asset('assets/images/favicon.png') }}">
    <x-pwa />
    <link rel="stylesheet" href="{{ asset('assets/css/fonts/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/verifall.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/tailwind.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/verifall-app.css') }}">
    @stack('styles')
</head>
<body class="font-sans text-body bg-white antialiased">

<main id="contenu" class="min-h-screen grid lg:grid-cols-12">
  <div class="hidden lg:block lg:col-span-7 relative overflow-hidden" data-auth-carousel>
    <div class="absolute inset-0">
      <div class="auth-slide is-active" style="background-image:url({{ asset('assets/images/slider/verifall_fact_news_step_1.webp') }})">
        <div class="absolute inset-0 bg-gradient-to-t from-[#063B2A]/90 via-dark/50 to-dark/30" aria-hidden="true"></div>
        <div class="relative z-10 flex flex-col justify-between h-full p-10 xl:p-14 text-white">
          <div>
            <span class="inline-flex items-baseline gap-3">
              <span class="text-5xl font-display font-black text-white/25">01</span>
              <span class="text-sm font-bold tracking-[.25em] text-primary">{{ __('auth.carrousel_identifier') }}</span>
            </span>
            <p class="text-white/70 mt-1">{{ __('auth.carrousel_comprendre_avant_de_croire') }}</p>
          </div>
          <div>
            <h4 class="text-2xl xl:text-3xl font-light leading-snug">{{ __('auth.carrousel_savoir_distinguer_le_vrai_du_faux') }}</h4>
            <div class="flex items-end justify-between mt-8">
              <div>
                <h5 class="text-lg font-display font-bold mb-0">Vérif'All Lab</h5>
                <span class="text-white/70">{{ __('auth.carrousel_boussole_de_l_information') }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="auth-slide" style="background-image:url({{ asset('assets/images/slider/verifall_fact_news_step_2.webp') }})">
        <div class="absolute inset-0 bg-gradient-to-t from-[#063B2A]/90 via-dark/50 to-dark/30" aria-hidden="true"></div>
        <div class="relative z-10 flex flex-col justify-between h-full p-10 xl:p-14 text-white">
          <div>
            <span class="inline-flex items-baseline gap-3">
              <span class="text-5xl font-display font-black text-white/25">02</span>
              <span class="text-sm font-bold tracking-[.25em] text-primary">{{ __('auth.carrousel_questionner') }}</span>
            </span>
            <p class="text-white/70 mt-1">{{ __('auth.carrousel_qui_parle_et_pourquoi') }}</p>
          </div>
          <div>
            <h4 class="text-2xl xl:text-3xl font-light leading-snug">{{ __('auth.carrousel_qui_parle_une_source_fiable_cite') }}</h4>
            <div class="flex items-end justify-between mt-8">
              <div>
                <h5 class="text-lg font-display font-bold mb-0">{{ __('auth.carrousel_verifiez_l_origine') }}</h5>
                <span class="text-white/70">{{ __('auth.carrousel_ne_partagez_pas_l_inconnu') }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="auth-slide" style="background-image:url({{ asset('assets/images/slider/verifall_fact_news_step_3.webp') }})">
        <div class="absolute inset-0 bg-gradient-to-t from-[#063B2A]/90 via-dark/50 to-dark/30" aria-hidden="true"></div>
        <div class="relative z-10 flex flex-col justify-between h-full p-10 xl:p-14 text-white">
          <div>
            <span class="inline-flex items-baseline gap-3">
              <span class="text-5xl font-display font-black text-white/25">03</span>
              <span class="text-sm font-bold tracking-[.25em] text-primary">{{ __('auth.carrousel_verifier') }}</span>
            </span>
            <p class="text-white/70 mt-1">{{ __('auth.carrousel_l_emotion_n_est_pas_une_preuve_point') }}</p>
          </div>
          <div>
            <h4 class="text-2xl xl:text-3xl font-light leading-snug">{{ __('auth.carrousel_si_une_info_provoque_une_colere_ou') }}</h4>
            <div class="flex items-end justify-between mt-8">
              <div>
                <h5 class="text-lg font-display font-bold mb-0">{{ __('auth.carrousel_gardez_la_tete_froide') }}</h5>
                <span class="text-white/70">{{ __('auth.carrousel_l_emotion_n_est_pas_une_preuve') }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="auth-slide" style="background-image:url({{ asset('assets/images/slider/verifall_fact_news_step_4.webp') }})">
        <div class="absolute inset-0 bg-gradient-to-t from-[#063B2A]/90 via-dark/50 to-dark/30" aria-hidden="true"></div>
        <div class="relative z-10 flex flex-col justify-between h-full p-10 xl:p-14 text-white">
          <div>
            <span class="inline-flex items-baseline gap-3">
              <span class="text-5xl font-display font-black text-white/25">04</span>
              <span class="text-sm font-bold tracking-[.25em] text-primary">{{ __('auth.carrousel_comprendre') }}</span>
            </span>
            <p class="text-white/70 mt-1">{{ __('auth.carrousel_la_forme_trahit_souvent_le_fond_point') }}</p>
          </div>
          <div>
            <h4 class="text-2xl xl:text-3xl font-light leading-snug">{{ __('auth.carrousel_un_communique_officiel_truffe_de_fautes') }}</h4>
            <div class="flex items-end justify-between mt-8">
              <div>
                <h5 class="text-lg font-display font-bold mb-0">{{ __('auth.carrousel_soyez_attentifs_aux_details') }}</h5>
                <span class="text-white/70">{{ __('auth.carrousel_la_forme_trahit_souvent_le_fond') }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="absolute bottom-8 inset-x-0 z-20 px-14"><div class="verification-trace" role="tablist" aria-label="{{ __('auth.demarche_les_4_etapes_de_la_demarche') }}"><button type="button" class="trace-point is-active" data-auth-dot="0" role="tab" aria-label="{{ __('auth.demarche_claim_identifier') }}"><span class="trace-top">CLAIM</span><span class="trace-dot" aria-hidden="true"></span><span class="trace-bottom">{{ __('auth.demarche_identifier') }}</span></button><span class="trace-line" aria-hidden="true"></span><button type="button" class="trace-point" data-auth-dot="1" role="tab" aria-label="{{ __('auth.demarche_source_questionner') }}"><span class="trace-top">SOURCE</span><span class="trace-dot" aria-hidden="true"></span><span class="trace-bottom">{{ __('auth.demarche_questionner') }}</span></button><span class="trace-line" aria-hidden="true"></span><button type="button" class="trace-point" data-auth-dot="2" role="tab" aria-label="{{ __('auth.demarche_evidence_verifier') }}"><span class="trace-top">EVIDENCE</span><span class="trace-dot" aria-hidden="true"></span><span class="trace-bottom">{{ __('auth.demarche_verifier') }}</span></button><span class="trace-line" aria-hidden="true"></span><button type="button" class="trace-point" data-auth-dot="3" role="tab" aria-label="{{ __('auth.demarche_verdict_comprendre') }}"><span class="trace-top">VERDICT</span><span class="trace-dot" aria-hidden="true"></span><span class="trace-bottom">{{ __('auth.demarche_comprendre') }}</span></button></div></div>
  </div>
  <div class="lg:col-span-5 flex min-h-screen bg-white">
    <div class="w-full max-w-md mx-auto my-auto px-5 py-10">
      <div class="lg:hidden mb-8">
        <div class="flex items-center gap-2 text-[11px] font-bold tracking-[.18em] text-body/60" aria-hidden="true">
          <span class="text-primary">CLAIM</span><span class="flex-1 h-px bg-line"></span>
          <span>SOURCE</span><span class="flex-1 h-px bg-line"></span>
          <span>EVIDENCE</span><span class="flex-1 h-px bg-line"></span>
          <span>VERDICT</span>
        </div>
      </div>

{{ $slot }}


    </div>
  </div>
</main>
<script src="{{ asset('assets/js/verifall.js') }}" defer></script>
<x-toast variant="citizen" />
<script src="{{ asset('assets/js/verifall-app.js') }}" defer></script>

{{-- Ces deux-ci corrigent des comportements du prototype qui ne survivent pas
     à Livewire : l'œil « voir / cacher » et le témoin de force. Voir l'en-tête
     de `auth-forms.js` pour le détail. --}}
<script src="{{ asset('assets/js/password-meter.js') }}" defer></script>
<script src="{{ asset('assets/js/auth-forms.js') }}" defer></script>
@stack('scripts')
</body>
</html>
