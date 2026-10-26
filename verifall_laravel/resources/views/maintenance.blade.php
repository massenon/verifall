{{--
  | La page de maintenance publique — « la boussole se recalibre ».
  |
  | ## Autonome, et c'est délibéré
  |
  | Elle ne s'appuie ni sur le build Tailwind figé ni sur une mise en page : son
  | style est en ligne, ses polices sont locales (`fonts/premium.css`), aucun
  | script tiers. Une page qui s'affiche quand le site est en travaux ne doit
  | dépendre de rien — c'est le seul écran dont on est certain qu'il sera vu un
  | jour où quelque chose ne va pas.
  |
  | ## Un seul geste visuel
  |
  | La boussole de la marque, dont l'aiguille cherche le nord. Quand une heure
  | est annoncée, son anneau devient le cadran du temps écoulé ; sans heure,
  | l'aiguille oscille et la page ne promet rien.
  |
  | ## Le décompte ne ment pas
  |
  | Quand l'heure annoncée est dépassée, on le DIT. Un compteur figé sur
  | « 00:00 » pendant vingt minutes fait rafraîchir en boucle et donne
  | l'impression d'un site cassé, pas d'une opération en cours.
  |
  | ## La réouverture s'annonce d'elle-même
  |
  | La page interroge `maintenance.etat` toutes les 30 secondes (onglet visible
  | seulement) et le dit quand le service revient — plutôt que d'inviter à
  | rafraîchir en boucle.
--}}
@php
  $debut ??= null;
  $fermeA = $debut?->format('H:i');
@endphp
<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- `noindex` : cette page ne doit jamais devenir le résultat de recherche
         du site. Le 503 le dit déjà aux robots sérieux ; la balise couvre les
         autres. --}}
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#EEF2EC">
    <title>Maintenance en cours — Vérif'All</title>

    <link rel="icon" href="{{ asset('assets/images/favicon.png') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/fonts/premium.css') }}">

    <style>
        :root {
            --papier: #EEF2EC;
            --carte: #F8FAF6;
            --foret: #0E2A1E;
            --mousse: #536459;
            --trait: #CFDACF;
            --vert: #09B850;
            --vert-fonce: #067a36;
            --ambre: #8A5A00;
            --titre: 'Bricolage Grotesque', 'Segoe UI', system-ui, sans-serif;
            --texte: 'Public Sans', 'Segoe UI', system-ui, sans-serif;
        }

        * { box-sizing: border-box; }

        html { -webkit-text-size-adjust: 100%; }

        body {
            margin: 0;
            min-height: 100vh;
            background:
                radial-gradient(1200px 600px at 85% 20%, rgba(9, 184, 80, .07), transparent 60%),
                var(--papier);
            color: var(--foret);
            font: 400 17px/1.6 var(--texte);
        }

        .page {
            min-height: 100vh;
            max-width: 1180px;
            margin: 0 auto;
            padding: clamp(20px, 4vw, 48px) clamp(16px, 4vw, 48px);
            display: grid;
            grid-template-rows: auto 1fr auto;
            gap: clamp(28px, 5vw, 56px);
        }

        .marque { display: flex; align-items: center; gap: 10px; text-decoration: none; color: inherit; }
        .marque svg { flex: none; }
        .marque b { font: 700 18px/1 var(--titre); letter-spacing: -.01em; }
        .marque > span > span { display: block; font-size: 13px; color: var(--mousse); margin-top: 3px; }

        .scene {
            display: grid;
            grid-template-columns: minmax(0, 1.05fr) minmax(0, .95fr);
            align-items: center;
            gap: clamp(32px, 6vw, 88px);
        }

        h1 {
            margin: 0 0 20px;
            font: 650 clamp(40px, 7.2vw, 84px)/.96 var(--titre);
            letter-spacing: -.035em;
            max-width: 11ch;
            text-wrap: balance;
        }

        .message { margin: 0 0 28px; max-width: 34em; font-size: clamp(17px, 1.6vw, 19px); color: #24392E; }

        /* Les faits, en une ligne de repères — pas une rangée de cartes. */
        .reperes { display: flex; flex-wrap: wrap; gap: 10px 28px; margin: 0 0 32px; padding: 0; list-style: none; }
        .reperes li { display: flex; flex-direction: column; }
        .reperes small { font-size: 13px; color: var(--mousse); }
        .reperes strong { font: 600 20px/1.2 var(--titre); font-variant-numeric: tabular-nums; }

        .retard {
            margin: 0 0 32px;
            padding: 14px 16px;
            max-width: 34em;
            border-left: 3px solid #E0A100;
            background: rgba(224, 161, 0, .10);
            color: var(--ambre);
            border-radius: 0 10px 10px 0;
            font-size: 15px;
        }

        form { display: flex; gap: 8px; max-width: 30em; }
        label.cache { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

        input[type="email"] {
            flex: 1 1 auto;
            min-width: 0;
            height: 52px;
            padding: 0 16px;
            border: 1.5px solid var(--trait);
            border-radius: 12px;
            background: var(--carte);
            color: var(--foret);
            font: 400 16px var(--texte);
            transition: border-color .15s, box-shadow .15s;
        }

        input[type="email"]::placeholder { color: #7D8C82; }
        input[type="email"]:focus { outline: none; border-color: var(--vert); box-shadow: 0 0 0 4px rgba(9, 184, 80, .16); }

        .bouton {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            height: 52px;
            padding: 0 22px;
            border: 0;
            border-radius: 12px;
            background: var(--foret);
            color: #fff;
            font: 600 16px var(--texte);
            text-decoration: none;
            cursor: pointer;
            white-space: nowrap;
            transition: background .15s;
        }

        .bouton:hover { background: #18432f; }
        .bouton:focus-visible, input:focus-visible, .marque:focus-visible { outline: 3px solid var(--vert); outline-offset: 3px; }
        .bouton-vert { background: var(--vert-fonce); }
        .bouton-vert:hover { background: #055f2a; }

        .aide { margin: 12px 0 0; font-size: 14px; color: var(--mousse); max-width: 32em; }
        .erreur { margin: 10px 0 0; font-size: 14px; color: #B42323; }

        .confirme {
            display: flex; gap: 12px; align-items: flex-start;
            max-width: 32em; margin: 0; padding: 16px 18px;
            border-radius: 12px; background: rgba(9, 184, 80, .10); color: #075A2B; font-size: 15px;
        }

        /* ---------- la boussole ---------- */
        .boussole { position: relative; width: min(100%, 460px); aspect-ratio: 1; margin-left: auto; }
        .boussole svg { display: block; width: 100%; height: 100%; overflow: visible; }

        .graduation { stroke: var(--foret); }
        .anneau-fond { stroke: var(--trait); }
        .anneau-temps { stroke: var(--vert); transition: stroke-dashoffset 1s linear; }
        .cardinal { font: 600 15px var(--titre); fill: var(--foret); }
        .cardinal-n { fill: var(--vert-fonce); }

        .aiguille { transform-origin: 200px 200px; animation: chercher 2.6s cubic-bezier(.3, .6, .2, 1) both; }
        .boussole[data-libre] .aiguille { animation: chercher 2.6s cubic-bezier(.3, .6, .2, 1) both, osciller 7s ease-in-out 2.6s infinite; }

        @keyframes chercher {
            0% { transform: rotate(-118deg); }
            45% { transform: rotate(22deg); }
            70% { transform: rotate(-9deg); }
            86% { transform: rotate(4deg); }
            100% { transform: rotate(0deg); }
        }

        @keyframes osciller {
            0%, 100% { transform: rotate(0deg); }
            30% { transform: rotate(7deg); }
            65% { transform: rotate(-5deg); }
        }

        .cadran {
            position: absolute; left: 50%; top: 70%; transform: translate(-50%, -50%);
            display: flex; gap: 6px; align-items: baseline;
            font: 600 clamp(26px, 4.2vw, 38px)/1 var(--titre); font-variant-numeric: tabular-nums; letter-spacing: -.02em;
            white-space: nowrap;
        }
        .cadran i { font-style: normal; color: var(--mousse); font-weight: 500; }

        .revenu { display: none; }
        body[data-revenu] .revenu { display: flex; }
        body[data-revenu] .attente { display: none; }

        footer { display: flex; flex-wrap: wrap; justify-content: space-between; gap: 8px 24px; font-size: 14px; color: var(--mousse); border-top: 1px solid var(--trait); padding-top: 18px; }

        @media (max-width: 860px) {
            .scene { grid-template-columns: 1fr; }
            .boussole { order: -1; width: min(72vw, 300px); margin: 0 auto; }
            h1 { max-width: 13ch; }
        }

        @media (max-width: 480px) {
            form { flex-direction: column; }
            .bouton { width: 100%; }
        }

        @media (prefers-reduced-motion: reduce) {
            .aiguille, .boussole[data-libre] .aiguille { animation: none; }
            .anneau-temps { transition: none; }
        }
    </style>
</head>
<body data-fin="{{ $finAnnoncee?->toIso8601String() }}" data-retard="{{ $enRetard ? '1' : '0' }}">
<div class="page">
    <header>
        <a class="marque" href="{{ url('/') }}">
            <svg width="34" height="34" viewBox="0 0 34 34" aria-hidden="true"><rect width="34" height="34" rx="9" fill="#0E2A1E"/><path d="M17 6l4 11-4 11-4-11z" fill="#09B850"/><path d="M17 17l4 0-4 11-4-11z" fill="#fff" opacity=".85"/></svg>
            <span><b>Vérif’All</b><span>La boussole contre la désinformation numérique</span></span>
        </a>
    </header>

    <main class="scene">
        <div>
            <h1>La boussole se recalibre.</h1>

            <div class="attente">
                <p class="message">{{ $message }}</p>

                @if ($enRetard)
                    {{-- On le dit franchement. Un compteur à zéro qui ne bouge plus
                         se lit comme une panne ; « nous avons dépassé l'heure » se
                         lit comme du travail en cours. --}}
                    <p class="retard">
                        L’opération dure plus longtemps que prévu. Nous préférons vous le dire
                        plutôt que d’afficher un compte à rebours qui n’avance plus.
                    </p>
                @endif

                @if ($fermeA || ($finAnnoncee && ! $enRetard))
                    <ul class="reperes">
                        @if ($fermeA)
                            <li><small>Fermé depuis</small><strong>{{ $fermeA }}</strong></li>
                        @endif
                        @if ($finAnnoncee && ! $enRetard)
                            <li><small>Retour annoncé</small><strong>{{ $finAnnoncee->isToday() ? $finAnnoncee->format('H:i') : $finAnnoncee->locale('fr')->translatedFormat('j M, H:i') }}</strong></li>
                        @endif
                    </ul>
                @endif

                @if (session('maintenance_prevenu'))
                    <p class="confirme" role="status">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex:none;margin-top:2px"><path d="M20 6 9 17l-5-5"/></svg>
                        <span>C’est noté. Vous recevrez un courriel dès que le service sera rétabli — un seul, et rien d’autre.</span>
                    </p>
                @else
                    <form method="POST" action="{{ route('maintenance.watch') }}">
                        @csrf
                        <label for="email" class="cache">Votre adresse e-mail</label>
                        <input type="email" id="email" name="email" required
                               placeholder="votre@adresse.com" autocomplete="email"
                               value="{{ old('email') }}" @error('email') aria-invalid="true" aria-describedby="email-erreur" @enderror>
                        <button type="submit" class="bouton">Prévenez-moi</button>
                    </form>

                    @error('email')
                        <p class="erreur" id="email-erreur">{{ $message }}</p>
                    @enderror

                    {{-- Dit avant qu'on ne le demande : c'est ce qui décide quelqu'un
                         à laisser son adresse, ou à ne pas la laisser. --}}
                    <p class="aide">
                        Un seul message, à la réouverture. Votre adresse ne rejoint aucune liste
                        de diffusion et n’est pas conservée au-delà.
                    </p>
                @endif
            </div>

            <div class="revenu" role="status" aria-live="polite" style="flex-direction:column;gap:20px;align-items:flex-start">
                <p class="message" style="margin:0">Le service est revenu. Merci de votre patience.</p>
                <a class="bouton bouton-vert" href="{{ url('/') }}">Retourner sur Vérif’All</a>
            </div>
        </div>

        {{-- La boussole. Décor : son contenu chiffré est dit en texte ailleurs. --}}
        <div class="boussole" id="boussole"
             @if ($finAnnoncee && ! $enRetard && $debut) data-debut="{{ $debut->toIso8601String() }}" data-fin="{{ $finAnnoncee->toIso8601String() }}" @else data-libre @endif>
            <svg viewBox="0 0 400 400" aria-hidden="true" focusable="false">
                <circle class="anneau-fond" cx="200" cy="200" r="178" fill="none" stroke-width="10"/>
                <circle class="anneau-temps" id="anneau" cx="200" cy="200" r="178" fill="none" stroke-width="10"
                        stroke-linecap="round" transform="rotate(-90 200 200)"
                        stroke-dasharray="1118.4" stroke-dashoffset="1118.4"/>
                <g class="graduation">
                    @for ($a = 0; $a < 360; $a += 6)
                        <line x1="200" y1="{{ $a % 90 === 0 ? 34 : ($a % 30 === 0 ? 38 : 41) }}" x2="200" y2="48"
                              stroke-width="{{ $a % 30 === 0 ? 2 : 1 }}" opacity="{{ $a % 30 === 0 ? .9 : .35 }}"
                              transform="rotate({{ $a }} 200 200)"/>
                    @endfor
                </g>
                <text class="cardinal cardinal-n" x="200" y="76" text-anchor="middle">N</text>
                <text class="cardinal" x="330" y="205" text-anchor="middle">E</text>
                <text class="cardinal" x="200" y="336" text-anchor="middle">S</text>
                <text class="cardinal" x="70" y="205" text-anchor="middle">O</text>
                <circle cx="200" cy="200" r="118" fill="none" stroke="var(--trait)" stroke-width="1"/>
                <g class="aiguille">
                    <path d="M200 96 L214 200 L200 212 L186 200 Z" fill="var(--vert)"/>
                    <path d="M200 244 L214 200 L200 188 L186 200 Z" fill="var(--foret)" opacity=".85"/>
                    <circle cx="200" cy="200" r="9" fill="var(--papier)" stroke="var(--foret)" stroke-width="3"/>
                </g>
            </svg>

            @if ($finAnnoncee && ! $enRetard)
                <div class="cadran" id="compte" role="timer" aria-live="off" data-fin="{{ $finAnnoncee->toIso8601String() }}">
                    <span id="cpt-h">--</span><i>:</i><span id="cpt-m">--</span><i>:</i><span id="cpt-s">--</span>
                </div>
            @endif
        </div>
    </main>

    <footer>
        <span>Vérif’All, Bénin</span>
        <span>Une question urgente ? <a href="mailto:{{ config('mail.from.address') }}" style="color:inherit">{{ config('mail.from.address') }}</a></span>
    </footer>
</div>

<script>
    (function () {
        'use strict';

        /* Le décompte et l'anneau. Recalculés depuis les horodatages SERVEUR à
           chaque tic : partir d'une durée figée dériverait sur un onglet mis en
           veille, et le compteur mentirait au réveil. */
        var compte = document.getElementById('compte');
        var boussole = document.getElementById('boussole');
        var anneau = document.getElementById('anneau');
        var tour = 1118.4;

        if (compte) {
            var fin = new Date(compte.getAttribute('data-fin')).getTime();
            var debut = boussole.getAttribute('data-debut') ? new Date(boussole.getAttribute('data-debut')).getTime() : null;
            var h = document.getElementById('cpt-h');
            var m = document.getElementById('cpt-m');
            var s = document.getElementById('cpt-s');
            var deux = function (n) { return n < 10 ? '0' + n : String(n); };

            var tic = function () {
                var maintenant = Date.now();
                var reste = Math.max(0, Math.floor((fin - maintenant) / 1000));

                h.textContent = deux(Math.floor(reste / 3600));
                m.textContent = deux(Math.floor((reste % 3600) / 60));
                s.textContent = deux(reste % 60);

                if (debut !== null && fin > debut) {
                    var part = Math.min(1, Math.max(0, (maintenant - debut) / (fin - debut)));
                    anneau.setAttribute('stroke-dashoffset', String(tour * (1 - part)));
                }

                if (reste === 0) {
                    /* Arrivé à zéro, on RECHARGE plutôt que de laisser un compteur
                       figé : soit le site est revenu, soit la page dira « nous avons
                       dépassé l'heure ». Dans les deux cas, c'est plus honnête. */
                    window.location.reload();
                }
            };

            tic();
            setInterval(tic, 1000);
        }

        /* La réouverture s'annonce d'elle-même : un appel léger toutes les 30 s,
           seulement quand l'onglet est visible. Si l'équipe change l'heure
           annoncée, ou si elle est dépassée, la page se recharge pour le dire. */
        var etat = @json(route('maintenance.etat'));
        var finAffichee = document.body.getAttribute('data-fin') || '';
        var retardAffiche = document.body.getAttribute('data-retard') === '1';

        var sonder = function () {
            if (document.hidden || !window.fetch) { return; }

            fetch(etat, { headers: { Accept: 'application/json' }, cache: 'no-store' })
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (e) {
                    if (!e) { return; }
                    if (!e.actif) {
                        document.body.setAttribute('data-revenu', '');
                        clearInterval(minuterie);
                    } else if ((e.fin_annoncee || '') !== finAffichee || Boolean(e.en_retard) !== retardAffiche) {
                        window.location.reload();
                    }
                })
                .catch(function () { /* réseau coupé : on réessaiera au prochain tic */ });
        };

        var minuterie = setInterval(sonder, 30000);
    })();
</script>
</body>
</html>
