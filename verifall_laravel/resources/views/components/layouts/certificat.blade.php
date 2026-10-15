<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? __('composants.certificat_titre_page') }}</title>
    <meta name="description" content="{{ __('composants.certificat_meta_description') }}">
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:title" content="{{ __('composants.certificat_titre_page') }}">
    <meta property="og:description" content="{{ __('composants.certificat_og_description') }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:locale" content="{{ app()->getLocale() === 'en' ? 'en_GB' : 'fr_FR' }}">
    <meta name="twitter:card" content="summary">
    <link rel="icon" href="{{ asset('assets/images/favicon.png') }}">
    <x-pwa />
    <link rel="stylesheet" href="{{ asset('assets/css/fonts/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/verifall.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/tailwind.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/verifall-app.css') }}">
    @stack('styles')
</head>
{{--
  | Gabarit AUTONOME, et c'est délibéré : le prototype donne à cette page son
  | propre en-tête réduit — logo, un seul lien — au lieu du menu complet du
  | site.
  |
  | La raison tient à qui s'en sert. Un recruteur arrive ici par un lien ou un
  | QR code imprimé sur un diplôme, avec une seule question en tête. Lui
  | dérouler la navigation entière, l'Académie, le Hub et le forum, c'est lui
  | proposer six sorties avant qu'il ait obtenu sa réponse.
--}}
<body class="font-sans bg-[#F6F8F7] text-slate2 antialiased min-h-screen flex flex-col">
<header class="bg-white border-b border-line">
  <div class="max-w-3xl mx-auto px-4 h-16 flex items-center justify-between">
    <a href="{{ route('public.home') }}"><img src="{{ asset('assets/images/logo.png') }}" alt="Vérif'All" class="h-9 w-auto" width="171" height="60"></a>
    <a href="{{ route('login') }}" class="text-sm font-semibold text-ink hover:text-primary">{{ __('composants.certificat_se_connecter') }}</a>
  </div>
</header>

{{ $slot }}

<footer class="py-6 text-center text-xs text-slate2">© {{ now()->year }} Vérif'All. {{ __('composants.certificat_tous_droits_reserves') }} · <a href="{{ route('public.home') }}" class="text-primary font-semibold">verifall.com</a></footer>

@livewireScripts
@stack('scripts')
</body>
</html>
