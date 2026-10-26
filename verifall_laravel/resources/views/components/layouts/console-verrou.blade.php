@props(['title' => null])
{{-- Écran de verrouillage de la console : feuille premium seule, aucune donnée
     de la console. Arbitrage n° 180. --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0E2A1E">
    <title>{{ $title ?? "Session verrouillée — Vérif'All" }}</title>
    <link rel="icon" href="{{ asset('assets/images/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/fonts/premium.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/staff-premium.css') }}">
</head>
<body class="min-h-screen bg-foret text-white font-sans antialiased flex flex-col">
  <div class="flex items-center justify-between px-6 h-16">
    <span class="inline-flex items-center gap-2.5 font-titre font-semibold"><span class="inline-flex w-8 h-8 rounded-ctrl bg-brand items-center justify-center"><x-lucide name="compass" class="w-[18px] h-[18px] text-foret" /></span>Vérif'All</span>
    <span class="text-sm text-white/60">Console</span>
  </div>
  <main class="flex-1 grid place-items-center px-4 pb-16">{{ $slot }}</main>
  <script>
    /* L'horloge suit la minute ; rien d'autre ne bouge. */
    setInterval(function () { var h = document.querySelector('[data-horloge]'); if (h) h.textContent = new Date().toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }); }, 15000);
  </script>
</body>
</html>
