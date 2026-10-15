{{--
  | La page de maintenance publique.
  |
  | ## Autonome, et c'est délibéré
  |
  | Elle ne s'appuie ni sur le build Tailwind figé ni sur la mise en page
  | citoyenne : son style est en ligne. Une page qui s'affiche quand le site est
  | en travaux ne doit dépendre de rien — c'est le seul écran dont on est certain
  | qu'il sera vu un jour où quelque chose ne va pas.
  |
  | ## Le décompte ne ment pas
  |
  | Quand l'heure annoncée est dépassée, on le DIT. Un compteur figé sur
  | « 00:00 » pendant vingt minutes fait rafraîchir en boucle et donne
  | l'impression d'un site cassé, pas d'une opération en cours.
  |
  | ## Ce que la page NE promet PAS
  |
  | Aucune formule du genre « nous revenons très vite » quand aucune heure n'est
  | annoncée : une promesse qu'on ne tient pas coûte plus que le silence. Sans
  | heure, la page propose simplement de prévenir par courriel.
--}}
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- `noindex` : cette page ne doit jamais devenir le résultat de recherche
         du site. Le 503 le dit déjà aux robots sérieux ; la balise couvre les
         autres. --}}
    <meta name="robots" content="noindex, nofollow">
    <title>Maintenance en cours — Vérif'All</title>

    <link rel="icon" href="{{ asset('assets/images/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/fonts/fonts.css') }}">

    <style>
        :root {
            --vf-primary: #09B850;
            --vf-primary-dark: #079a43;
            --vf-dark: #202124;
            --vf-body: #606261;
            --vf-line: #e9ecef;
            --vf-ground: #f7f9f8;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            background: var(--vf-ground);
            color: var(--vf-body);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
        }

        .carte {
            width: 100%;
            max-width: 34rem;
            background: #fff;
            border: 1px solid var(--vf-line);
            border-radius: 16px;
            padding: 2.5rem 2rem;
            text-align: center;
            box-shadow: 0 1px 3px rgba(32, 33, 36, .05);
        }

        /* Le filet vert : la signature de la charte, et la seule décoration. */
        .filet {
            height: 4px;
            width: 56px;
            margin: 0 auto 1.75rem;
            border-radius: 2px;
            background: var(--vf-primary);
        }

        .marque {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--vf-dark);
            letter-spacing: -.2px;
            margin: 0 0 .35rem;
        }

        .baseline { font-size: .82rem; margin: 0 0 1.75rem; color: #8a8d8b; }

        h1 {
            font-family: 'Instrument Sans', -apple-system, sans-serif;
            font-size: 1.6rem;
            font-weight: 700;
            color: var(--vf-dark);
            margin: 0 0 .75rem;
            line-height: 1.25;
        }

        .message { margin: 0 0 1.75rem; font-size: 1rem; }

        .compte {
            display: inline-flex;
            gap: .6rem;
            padding: .85rem 1.25rem;
            margin-bottom: 1.75rem;
            border-radius: 12px;
            background: var(--vf-ground);
            border: 1px solid var(--vf-line);
            font-variant-numeric: tabular-nums;
        }

        .compte b { color: var(--vf-dark); font-size: 1.35rem; line-height: 1; display: block; }
        .compte span { font-size: .7rem; text-transform: uppercase; letter-spacing: .5px; }

        .retard {
            margin: 0 0 1.75rem;
            padding: .85rem 1rem;
            border-radius: 12px;
            background: rgba(247, 195, 46, .12);
            border: 1px solid rgba(247, 195, 46, .3);
            color: #8a6100;
            font-size: .9rem;
        }

        form { display: flex; gap: .5rem; flex-wrap: wrap; justify-content: center; }

        input[type="email"] {
            flex: 1 1 15rem;
            min-width: 0;
            padding: .8rem 1rem;
            border: 1px solid var(--vf-line);
            border-radius: 10px;
            font-size: .95rem;
            font-family: inherit;
            color: var(--vf-dark);
        }

        input[type="email"]:focus {
            outline: none;
            border-color: var(--vf-primary);
            box-shadow: 0 0 0 .2rem rgba(9, 184, 80, .12);
        }

        button {
            padding: .8rem 1.4rem;
            border: 0;
            border-radius: 10px;
            background: var(--vf-primary);
            color: #fff;
            font-size: .95rem;
            font-weight: 700;
            font-family: inherit;
            cursor: pointer;
        }

        button:hover { background: var(--vf-primary-dark); }

        .aide { font-size: .8rem; margin: 1rem 0 0; color: #8a8d8b; }
        .erreur { font-size: .85rem; margin: .75rem 0 0; color: #d6293e; }

        .confirme {
            padding: 1rem;
            border-radius: 12px;
            background: rgba(9, 184, 80, .08);
            border: 1px solid rgba(9, 184, 80, .25);
            color: #06703a;
            font-size: .95rem;
        }

        @media (prefers-color-scheme: dark) {
            :root { --vf-ground: #17181a; --vf-line: #2c2e31; --vf-body: #a9adb1; }
            body { background: var(--vf-ground); }
            .carte { background: #1f2124; border-color: var(--vf-line); }
            h1, .marque, .compte b { color: #f2f4f5; }
            input[type="email"] { background: #17181a; border-color: var(--vf-line); color: #f2f4f5; }
        }
    </style>
</head>
<body>
<main class="carte">
    <div class="filet" aria-hidden="true"></div>

    <p class="marque">Vérif’All</p>
    <p class="baseline">La boussole contre la désinformation numérique</p>

    <h1>Nous revenons dans un instant</h1>

    <p class="message">{{ $message }}</p>

    @if ($enRetard)
        {{-- On le dit franchement. Un compteur à zéro qui ne bouge plus se lit
             comme une panne ; « nous avons dépassé l'heure » se lit comme du
             travail en cours. --}}
        <p class="retard">
            L’opération dure plus longtemps que prévu. Nous préférons vous le dire
            plutôt que d’afficher un compte à rebours qui n’avance plus.
        </p>
    @elseif ($finAnnoncee)
        <div class="compte" id="compte" role="timer" aria-live="off"
             data-fin="{{ $finAnnoncee->toIso8601String() }}">
            <div><b id="cpt-h">--</b><span>heures</span></div>
            <div><b id="cpt-m">--</b><span>min</span></div>
            <div><b id="cpt-s">--</b><span>sec</span></div>
        </div>
    @endif

    @if (session('maintenance_prevenu'))
        <p class="confirme">
            C’est noté. Vous recevrez un courriel dès que le service sera rétabli —
            un seul, et rien d’autre.
        </p>
    @else
        <form method="POST" action="{{ route('maintenance.watch') }}">
            @csrf
            <label for="email" class="sr-only" style="position:absolute;width:1px;height:1px;overflow:hidden;clip:rect(0 0 0 0)">
                Votre adresse e-mail
            </label>
            <input type="email" id="email" name="email" required
                   placeholder="votre@adresse.com" autocomplete="email"
                   value="{{ old('email') }}">
            <button type="submit">Prévenez-moi</button>
        </form>

        @error('email')
            <p class="erreur">{{ $message }}</p>
        @enderror

        {{-- Dit avant qu'on ne le demande : c'est ce qui décide quelqu'un à
             laisser son adresse, ou à ne pas la laisser. --}}
        <p class="aide">
            Un seul message, à la réouverture. Votre adresse ne rejoint aucune liste
            de diffusion et n’est pas conservée au-delà.
        </p>
    @endif
</main>

@if ($finAnnoncee && ! $enRetard)
    <script>
        /* Le décompte. Recalculé depuis l'horodatage SERVEUR à chaque tick :
           partir d'une durée figée dériverait sur un onglet mis en veille, et
           le compteur mentirait au réveil. */
        (function () {
            var boite = document.getElementById('compte');
            var fin = new Date(boite.getAttribute('data-fin')).getTime();
            var h = document.getElementById('cpt-h');
            var m = document.getElementById('cpt-m');
            var s = document.getElementById('cpt-s');

            function deux(n) { return n < 10 ? '0' + n : String(n); }

            function tic() {
                var reste = Math.max(0, Math.floor((fin - Date.now()) / 1000));

                h.textContent = deux(Math.floor(reste / 3600));
                m.textContent = deux(Math.floor((reste % 3600) / 60));
                s.textContent = deux(reste % 60);

                if (reste === 0) {
                    /* Arrivé à zéro, on RECHARGE plutôt que de laisser un
                       compteur figé : soit le site est revenu, soit la page
                       affichera « nous avons dépassé l'heure ». Dans les deux
                       cas, c'est plus honnête que 00:00:00. */
                    window.location.reload();
                }
            }

            tic();
            setInterval(tic, 1000);
        })();
    </script>
@endif
</body>
</html>
