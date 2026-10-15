{{--
  | Gabarit des pages d'erreur.
  |
  | Volontairement AUTONOME : ni layout applicatif, ni session, ni requête base,
  | ni composant Livewire. Une page d'erreur doit s'afficher précisément quand
  | le reste est en panne — si elle dépend de ce qui vient de tomber, elle
  | échoue au moment où l'on en a le plus besoin.
  |
  | Style du front public : canvas #F6F8F7, Instrument Sans, vert de marque.
  | Aucune trace technique n'est jamais exposée (SECURITE_RGPD §3).
--}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $code }} — {{ $titre }} · Vérif'All</title>
    <link rel="icon" href="{{ asset('assets/images/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/fonts/fonts.css') }}">
    <style>
        :root { --vf-primary:#09B850; --vf-ink:#1A2233; --vf-slate:#6B7686; --vf-canvas:#F6F8F7; --vf-line:#EDF0F4; }
        * { box-sizing:border-box; }
        body {
            margin:0; min-height:100vh; display:grid; place-items:center; padding:1.5rem;
            font-family:'Inter',system-ui,-apple-system,'Segoe UI',sans-serif;
            background:var(--vf-canvas); color:var(--vf-ink);
        }
        .vf-card {
            width:100%; max-width:36rem; background:#fff; border:1px solid var(--vf-line);
            border-radius:1.25rem; padding:2.75rem 2rem; text-align:center;
            box-shadow:0 .5rem 1.5rem rgba(15,23,42,.06);
        }
        .vf-code {
            font-family:'Instrument Sans','Inter',sans-serif; font-size:3.5rem; font-weight:800;
            line-height:1; margin:0 0 .5rem; color:var(--vf-primary);
        }
        h1 { font-family:'Instrument Sans','Inter',sans-serif; font-size:1.375rem; margin:0 0 .75rem; }
        p { margin:0 0 1.75rem; color:var(--vf-slate); line-height:1.6; }
        .vf-actions { display:flex; flex-wrap:wrap; gap:.6rem; justify-content:center; }
        .vf-btn {
            display:inline-flex; align-items:center; min-height:44px; padding:.7rem 1.4rem;
            border-radius:.75rem; font-weight:600; text-decoration:none;
            background:var(--vf-primary); color:#fff;
        }
        .vf-btn:hover { background:#078a3d; }
        .vf-btn--ghost { background:#fff; color:var(--vf-ink); border:1px solid var(--vf-line); }
        .vf-btn--ghost:hover { background:var(--vf-canvas); }
        .vf-btn:focus-visible { outline:3px solid var(--vf-ink); outline-offset:2px; }
        .vf-sign { margin:1.75rem 0 0; font-size:.8rem; color:var(--vf-slate); }
    </style>
</head>
<body>
    <main class="vf-card">
        <p class="vf-code">{{ $code }}</p>
        <h1>{{ $titre }}</h1>
        <p>{{ $message }}</p>

        <div class="vf-actions">
            @foreach ($actions ?? [] as $libelle => $url)
                <a class="vf-btn {{ $loop->first ? '' : 'vf-btn--ghost' }}" href="{{ $url }}">{{ $libelle }}</a>
            @endforeach
        </div>

        <p class="vf-sign">Vérif'All — la boussole contre la désinformation numérique.</p>
    </main>
</body>
</html>
