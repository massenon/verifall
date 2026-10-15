@props(['title' => null])

{{-- Shell de connexion au back-office — transposé de compte-admin/login.html.
     Cascade CSS staff complète, fond deep-green, carte centrée. --}}
<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? "Administration — Vérif'All" }}</title>
    {{-- Le back-office n'est jamais indexé (SEO_PERFORMANCE : staff disallow). --}}
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{ asset('assets/images/favicon.png') }}">

    <link rel="stylesheet" href="{{ asset('assets/css/fonts/fonts.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/citizen.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/staff.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/tailwind.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/verifall-app.css') }}">
    @stack('styles')
</head>
<body class="font-sans bg-forest antialiased min-h-screen flex items-center justify-center p-4">

    <main class="w-full max-w-sm bg-white rounded-2xl shadow-cardlg p-8">
        {{ $slot }}
    </main>

    <x-toast variant="staff" />
    <script src="{{ asset('assets/js/verifall-app.js') }}" defer></script>

    {{-- L'œil « voir / cacher ». Sans ce script, le bouton du champ de mot de
         passe serait du balisage mort : il s'afficherait, et un clic ne ferait
         rien. La mise en page citoyenne le charge depuis le 2 septembre ;
         celle-ci ne le chargeait pas, faute de champ qui en avait besoin. --}}
    <script src="{{ asset('assets/js/auth-forms.js') }}" defer></script>
    @livewireScripts
    @stack('scripts')
</body>
</html>
