{{--
  | Le document « certificat » — gabarit SVG premium.
  |
  | Transposé de `certificat.xml` (fourni par le porteur du projet), câblé aux
  | données réelles. UN SEUL composant pour les trois écrans qui l'affichent :
  | le titulaire, le recruteur qui contrôle un code, et l'aperçu
  | d'administration.
  |
  | ── POURQUOI UN SVG, ET PLUS DU HTML MIS EN PAGE ────────────────────────────
  |
  | La version précédente composait le document en HTML et en CSS. Trois raisons
  | l'ont fait remplacer, et la première a été rencontrée pour de vrai :
  |
  |  · **Le build Tailwind est figé.** Sept classes de mise en page employées par
  |    cette version n'y figuraient pas : elles n'avaient aucun effet, et le QR
  |    sortait au double de sa taille. Un SVG ne dépend d'aucune feuille.
  |  · **Un diplôme a des proportions.** En HTML il se recompose selon la
  |    largeur ; en SVG avec `viewBox`, il garde son cadrage de l'écran au
  |    papier, du téléphone à l'A4 paysage.
  |  · **L'impression.** Les navigateurs retirent fonds et dégradés d'un bloc
  |    HTML ; le contenu d'un SVG, lui, EST le document.
  |
  | ── LES SIX NIVEAUX DE PREUVE ───────────────────────────────────────────────
  |
  | Le document ne décore pas : chaque bloc atteste quelque chose de vérifiable.
  |
  |   1. l'identité du titulaire            4. les compétences validées
  |   2. le cours réellement terminé        5. le numéro unique du certificat
  |   3. la progression à 100 %             6. le QR de contrôle public
  |
  | Un diplôme que personne ne peut contrôler ne vaut rien : c'est le sixième
  | qui donne leur valeur aux cinq autres.
  |
  | ── UN ÉCART ASSUMÉ AVEC LE GABARIT FOURNI ──────────────────────────────────
  |
  | Le gabarit lit `$certificate->user->name` et `$certificate->course->title`.
  | On emploie ici `holder_name` et `course_title`, DÉNORMALISÉS sur le
  | certificat au moment de sa délivrance. Ce n'est pas un raccourci : un cours
  | renommé, ou un compte dont le nom change, ne doit pas réécrire un diplôme
  | déjà remis. Ce qui est imprimé sur le papier de quelqu'un doit rester ce que
  | l'écran affiche dix ans plus tard.
--}}
@props([
    'certificat',
    'habillage',
    'qrCode' => null,
])

@php
    /*
     | La teinte réglée pilote le vert du document. Validée côté service contre
     | `^#[0-9A-Fa-f]{6}$` AVANT d'arriver ici : cette valeur entre dans des
     | attributs SVG, une chaîne libre y ouvrirait une injection.
     |
     | L'or, lui, reste fixe : c'est la marque d'un document officiel, pas une
     | préférence de charte.
     */
    $vert = $habillage['couleur'];

    /*
     | Identifiants uniques par rendu. Deux certificats sur une même page —
     | l'aperçu d'administration aujourd'hui, une liste demain — partageraient
     | sinon leurs dégradés, et le second hériterait des couleurs du premier :
     | en SVG, un `id` en double est silencieusement gagné par le premier venu.
     */
    $id = 'c'.substr(md5((string) ($certificat->code ?? 'exemple')), 0, 8);
@endphp

<svg class="cert-svg" viewBox="0 0 1600 1100" role="img"
     xmlns="http://www.w3.org/2000/svg"
     aria-labelledby="{{ $id }}-titre {{ $id }}-desc">

    <title id="{{ $id }}-titre">{{ __('cit_certificat.svg_titre', ['nom' => $certificat->holder_name, 'cours' => $certificat->course_title]) }}</title>
    <desc id="{{ $id }}-desc">{{ __('cit_certificat.svg_description', ['code' => $certificat->code]) }}</desc>

    <defs>
        <linearGradient id="{{ $id }}-vertProfond" x1="0" y1="0" x2="0" y2="1">
            <stop offset="0%" stop-color="#022C22"/>
            <stop offset="100%" stop-color="{{ $vert }}"/>
        </linearGradient>

        <linearGradient id="{{ $id }}-or" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0%" stop-color="#8A6A22"/>
            <stop offset="25%" stop-color="#C9A227"/>
            <stop offset="50%" stop-color="#F3D675"/>
            <stop offset="75%" stop-color="#C9A227"/>
            <stop offset="100%" stop-color="#8A6A22"/>
        </linearGradient>

        <linearGradient id="{{ $id }}-orDoux" x1="0" y1="0" x2="1" y2="0">
            <stop offset="0%" stop-color="#A57C24"/>
            <stop offset="50%" stop-color="#E2C15C"/>
            <stop offset="100%" stop-color="#A57C24"/>
        </linearGradient>

        <radialGradient id="{{ $id }}-papier" cx="50%" cy="45%" r="75%">
            <stop offset="0%" stop-color="#FFFFFF"/>
            <stop offset="72%" stop-color="#FAFAF7"/>
            <stop offset="100%" stop-color="#F1F2ED"/>
        </radialGradient>

        {{-- Trame de sécurité : elle donne au fond la texture d'un papier
             officiel, et se voit surtout à l'impression. --}}
        <pattern id="{{ $id }}-trame" width="28" height="28" patternUnits="userSpaceOnUse">
            <circle cx="2" cy="2" r="0.8" fill="{{ $vert }}" opacity="0.10"/>
            <circle cx="16" cy="14" r="0.7" fill="#B28A2E" opacity="0.09"/>
        </pattern>

        <filter id="{{ $id }}-ombre" x="-20%" y="-20%" width="140%" height="140%">
            <feDropShadow dx="0" dy="8" stdDeviation="12" flood-color="#022C22" flood-opacity="0.18"/>
        </filter>
    </defs>

    {{-- ──────────────────────────────────────────── fond et encadrement --}}
    <rect x="0" y="0" width="1600" height="1100" fill="#E8EAE4"/>
    <rect x="24" y="24" width="1552" height="1052" rx="22" fill="url(#{{ $id }}-papier)" filter="url(#{{ $id }}-ombre)"/>
    <rect x="40" y="40" width="1520" height="1020" rx="18" fill="none" stroke="{{ $vert }}" stroke-width="5"/>
    <rect x="52" y="52" width="1496" height="996" rx="14" fill="none" stroke="url(#{{ $id }}-or)" stroke-width="2"/>
    <rect x="64" y="64" width="1472" height="972" rx="10" fill="url(#{{ $id }}-trame)" opacity="0.45"/>

    {{-- ─────────────────────────────────── bandeau d'identité de marque --}}
    <path d="M40 40 H365 C300 95 240 160 205 235 C160 330 125 440 112 550 H40Z" fill="url(#{{ $id }}-vertProfond)"/>
    <path d="M40 40 H390" stroke="url(#{{ $id }}-or)" stroke-width="9"/>

    {{-- Le logo vient de NOS assets, pas d'une URL publique : le gabarit fourni
         pointait sur `verifall.com/landing/…`. Un document dont l'en-tête dépend
         d'un domaine extérieur perd son logo dès que ce domaine bouge, et ne
         s'imprime pas hors ligne.

         Il n'existe PAS d'asset « icône seule » dans ce dépôt, seulement le
         logotype complet. Le gabarit fourni posait une icône carrée PUIS le mot
         « Vérif'All » en texte à côté : avec le logotype, les deux se
         chevauchaient — vu à l'écran. Le logotype porte donc le nom lui-même, et
         le texte redondant a disparu. Une signature de marque ne s'écrit pas
         deux fois. --}}
    <image x="88" y="96" width="215" height="76" preserveAspectRatio="xMinYMid meet"
           href="{{ asset('assets/images/logo.png') }}"/>

    {{-- TROIS lignes courtes, et non deux longues : le panneau se rétrécit en
         descendant (c'est une courbe), et « DÉSINFORMATION NUMÉRIQUE » débordait
         sur le fond clair — texte pâle sur crème, illisible. Vu à l'écran. --}}
    <text x="90" y="200" font-size="12" font-weight="600" letter-spacing="1" fill="#DDEDE7">{{ __('cit_certificat.svg_baseline_1') }}</text>
    <text x="90" y="218" font-size="12" font-weight="600" letter-spacing="1" fill="#DDEDE7">{{ __('cit_certificat.svg_baseline_2') }}</text>
    <text x="90" y="236" font-size="12" font-weight="600" letter-spacing="1" fill="#DDEDE7">{{ __('cit_certificat.svg_baseline_3') }}</text>

    {{-- ══ PREUVE 5 ══ le numéro unique. C'est le CODE réel, celui que la page
         publique de vérification accepte — pas un numéro d'ordre décoratif. --}}
    <rect x="1195" y="82" width="310" height="48" rx="24" fill="{{ $vert }}"/>
    <circle cx="1222" cy="106" r="7" fill="#D7B94C"/>
    <text x="1240" y="112" font-family="monospace" font-size="15" font-weight="700" letter-spacing="1" fill="#FFFFFF">{{ $certificat->code }}</text>

    {{-- ────────────────────────────────────────────────────── en-tête --}}
    <text x="800" y="160" text-anchor="middle" font-family="Georgia, serif" font-size="25" font-weight="700" letter-spacing="7" fill="{{ $vert }}">{{ __('cit_certificat.svg_academie') }}</text>
    <line x1="535" y1="188" x2="1065" y2="188" stroke="url(#{{ $id }}-orDoux)" stroke-width="2"/>
    <circle cx="525" cy="188" r="5" fill="#C9A227"/>
    <circle cx="1075" cy="188" r="5" fill="#C9A227"/>

    <text x="800" y="270" text-anchor="middle" font-family="Georgia, serif" font-size="86" font-weight="700" letter-spacing="5" fill="#022C22">{{ __('cit_certificat.svg_certificat') }}</text>
    <text x="800" y="316" text-anchor="middle" font-size="25" font-weight="700" letter-spacing="8" fill="#B28A2E">{{ __('cit_certificat.svg_de_fin_de_formation') }}</text>

    {{-- ══ PREUVE 1 ══ l'identité du titulaire. L'élément le plus grand du
         document : c'est ce qu'on compare à une pièce d'identité. --}}
    <path d="M520 350 H720 M880 350 H1080" stroke="#C9A227" stroke-width="2"/>
    <text x="800" y="359" text-anchor="middle" font-size="14" font-weight="700" letter-spacing="4" fill="{{ $vert }}">{{ __('cit_certificat.svg_decerne_a') }}</text>

    <text x="800" y="430" text-anchor="middle" font-family="Georgia, serif" font-size="58" font-style="italic" font-weight="600" fill="{{ $vert }}">{{ $certificat->holder_name }}</text>
    <line x1="450" y1="450" x2="1150" y2="450" stroke="url(#{{ $id }}-orDoux)" stroke-width="2"/>

    {{-- ══ PREUVE 2 ══ le cours réellement terminé --}}
    <text x="800" y="493" text-anchor="middle" font-size="18" fill="#374151">{{ __('cit_certificat.svg_a_termine') }}</text>
    <text x="800" y="536" text-anchor="middle" font-family="Georgia, serif" font-size="31" font-weight="700" fill="{{ $vert }}">« {{ Str::limit($certificat->course_title, 52) }} »</text>

    {{-- ══ PREUVE 3 ══ la progression à 100 %. Le certificat n'est délivré qu'à
         ce moment-là : la carte le rappelle, elle ne le suppose pas. --}}
    <rect x="425" y="575" width="750" height="105" rx="12" fill="#FFFFFF" stroke="#D9D8CB" stroke-width="1"/>
    <rect x="425" y="575" width="7" height="105" rx="3" fill="url(#{{ $id }}-or)"/>
    <circle cx="490" cy="627" r="30" fill="#ECF5F1" stroke="{{ $vert }}" stroke-width="2"/>
    <path d="M475 622 L486 633 L507 609" fill="none" stroke="{{ $vert }}" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
    <text x="540" y="614" font-size="12" font-weight="700" letter-spacing="2" fill="#8A6A22">{{ __('cit_certificat.svg_cours_termine') }}</text>
    <text x="540" y="642" font-size="17" font-weight="600" fill="#1F2937">{{ Str::limit($certificat->course_title, 56) }}</text>
    <text x="540" y="664" font-size="12" fill="#6B7280">{{ __('cit_certificat.svg_progression') }}</text>

    {{-- ─────────────────────────────────────────── la boussole du produit --}}
    <g transform="translate(1230 585)">
        <circle cx="70" cy="70" r="68" fill="#FFFFFF" stroke="#C9A227" stroke-width="2"/>
        <circle cx="70" cy="70" r="57" fill="none" stroke="{{ $vert }}" stroke-width="2"/>
        <g stroke="#B28A2E" stroke-width="2">
            <line x1="70" y1="12" x2="70" y2="25"/><line x1="70" y1="115" x2="70" y2="128"/>
            <line x1="12" y1="70" x2="25" y2="70"/><line x1="115" y1="70" x2="128" y2="70"/>
            <line x1="29" y1="29" x2="38" y2="38"/><line x1="102" y1="102" x2="111" y2="111"/>
            <line x1="111" y1="29" x2="102" y2="38"/><line x1="38" y1="102" x2="29" y2="111"/>
        </g>
        <path d="M70 25 L82 70 L70 115 L58 70 Z" fill="{{ $vert }}"/>
        <path d="M70 25 L82 70 L70 70 Z" fill="#D7B94C"/>
        <circle cx="70" cy="70" r="7" fill="#FFFFFF" stroke="{{ $vert }}" stroke-width="3"/>
        <text x="70" y="-8" text-anchor="middle" font-size="11" font-weight="700" fill="{{ $vert }}">{{ __('cit_certificat.svg_verifier') }}</text>
        <text x="70" y="148" text-anchor="middle" font-size="10" font-weight="700" letter-spacing="1" fill="{{ $vert }}">{{ __('cit_certificat.svg_avant_de_partager') }}</text>
    </g>

    {{-- ────────────────────────────────────────────────── écusson citoyen --}}
    <g transform="translate(100 700)">
        <circle cx="95" cy="95" r="92" fill="#FFFFFF" stroke="url(#{{ $id }}-or)" stroke-width="5"/>
        <circle cx="95" cy="95" r="76" fill="none" stroke="{{ $vert }}" stroke-width="2" stroke-dasharray="3 6"/>
        <path d="M95 38 L132 52 L128 98 C126 125 110 143 95 151 C80 143 64 125 62 98 L58 52Z" fill="{{ $vert }}"/>
        <path d="M76 91 L90 105 L116 76" fill="none" stroke="#D7B94C" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
        {{-- Sous l'anneau doré (r=92), pas dessus : à y=184 le libellé chevauchait
             le cercle extérieur. Vu à l'écran. --}}
        <text x="95" y="208" text-anchor="middle" font-size="12" font-weight="700" letter-spacing="2" fill="{{ $vert }}">{{ __('cit_certificat.svg_citoyen') }}</text>
        <text x="95" y="226" text-anchor="middle" font-size="12" font-weight="700" letter-spacing="2" fill="{{ $vert }}">{{ __('cit_certificat.svg_verificateur') }}</text>
    </g>

    {{-- ══ PREUVE 4 ══ les compétences validées --}}
    <text x="800" y="735" text-anchor="middle" font-size="13" font-weight="700" letter-spacing="4" fill="#8A6A22">{{ __('cit_certificat.svg_competences') }}</text>

    <g transform="translate(480 765)">
        <circle cx="28" cy="28" r="27" fill="#EDF6F2" stroke="{{ $vert }}" stroke-width="2"/>
        <circle cx="28" cy="28" r="10" fill="none" stroke="{{ $vert }}" stroke-width="3"/>
        <line x1="36" y1="36" x2="45" y2="45" stroke="{{ $vert }}" stroke-width="3" stroke-linecap="round"/>
        <text x="70" y="24" font-size="12" font-weight="700" fill="{{ $vert }}">{{ __('cit_certificat.svg_competence_1a') }}</text>
        <text x="70" y="42" font-size="12" fill="#6B7280">{{ __('cit_certificat.svg_competence_1b') }}</text>
    </g>

    <g transform="translate(730 765)">
        <circle cx="28" cy="28" r="27" fill="#EDF6F2" stroke="{{ $vert }}" stroke-width="2"/>
        <path d="M17 28 L25 36 L41 19" fill="none" stroke="{{ $vert }}" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
        <text x="70" y="24" font-size="12" font-weight="700" fill="{{ $vert }}">{{ __('cit_certificat.svg_competence_2a') }}</text>
        <text x="70" y="42" font-size="12" fill="#6B7280">{{ __('cit_certificat.svg_competence_2b') }}</text>
    </g>

    <g transform="translate(960 765)">
        <circle cx="28" cy="28" r="27" fill="#EDF6F2" stroke="{{ $vert }}" stroke-width="2"/>
        <path d="M28 13 L40 18 L39 31 C38 39 33 44 28 47 C23 44 18 39 17 31 L16 18Z" fill="none" stroke="{{ $vert }}" stroke-width="3"/>
        <text x="70" y="24" font-size="12" font-weight="700" fill="{{ $vert }}">{{ __('cit_certificat.svg_competence_3a') }}</text>
        <text x="70" y="42" font-size="12" fill="#6B7280">{{ __('cit_certificat.svg_competence_3b') }}</text>
    </g>

    {{-- ───────────────────────────────────────────────── date d'émission --}}
    <g transform="translate(1220 780)">
        <rect x="0" y="0" width="235" height="75" rx="10" fill="#F7F8F4" stroke="#D8D7CA"/>
        <text x="18" y="25" font-size="10" font-weight="700" letter-spacing="2" fill="#8A6A22">{{ __('cit_certificat.svg_date_emission') }}</text>
        <text x="18" y="53" font-size="16" font-weight="700" fill="{{ $vert }}">{{ $certificat->issued_at?->translatedFormat('j F Y') }}</text>
    </g>

    {{-- ──────────────────────────────────────────────────────── signatures --}}
    <g transform="translate(410 895)">
        @if ($habillage['signatureUrl'])
            <image x="55" y="-18" width="200" height="70" preserveAspectRatio="xMidYMax meet" href="{{ $habillage['signatureUrl'] }}"/>
        @endif
        <line x1="0" y1="55" x2="310" y2="55" stroke="#374151" stroke-width="1"/>
        <text x="155" y="83" text-anchor="middle" font-size="13" font-weight="700" fill="{{ $vert }}">{{ $habillage['signatureNom'] }}</text>
        <text x="155" y="104" text-anchor="middle" font-size="11" fill="#6B7280">{{ $habillage['signatureQualite'] }}</text>
    </g>

    <g transform="translate(850 895)">
        <line x1="0" y1="55" x2="310" y2="55" stroke="#374151" stroke-width="1"/>
        <text x="155" y="83" text-anchor="middle" font-size="13" font-weight="700" fill="{{ $vert }}">{{ __('cit_certificat.svg_academie_verifall') }}</text>
        <text x="155" y="104" text-anchor="middle" font-size="11" fill="#6B7280">{{ __('cit_certificat.svg_validation_pedagogique') }}</text>
    </g>

    {{-- ══ PREUVE 6 ══ le QR de contrôle public.
         Le gabarit fourni dessinait une grille factice ; celui-ci est un VRAI
         QR, engendré côté serveur par `bacon/bacon-qr-code`, et il mène à la
         page publique de vérification — jamais à l'espace citoyen : le
         recruteur qui le scanne n'a pas de compte. --}}
    @if ($qrCode)
        <g transform="translate(1280 875)">
            <rect x="0" y="0" width="125" height="125" rx="6" fill="#FFFFFF" stroke="{{ $vert }}" stroke-width="2"/>
            <image x="8" y="8" width="109" height="109" href="{{ $qrCode }}"/>
        </g>
        <text x="1342" y="1022" text-anchor="middle" font-size="10" font-weight="700" letter-spacing="1" fill="{{ $vert }}">{{ __('cit_certificat.svg_verifier_authenticite') }}</text>
    @endif

    {{-- ───────────────────────────────────────────────────────────── pied --}}
    <path d="M40 1032 H1560 V1042 A18 18 0 0 1 1542 1060 H58 A18 18 0 0 1 40 1042 Z" fill="url(#{{ $id }}-vertProfond)"/>
    <text x="90" y="1052" font-size="12" font-weight="700" letter-spacing="2" fill="#FFFFFF">VÉRIF'ALL</text>
    <text x="800" y="1052" text-anchor="middle" font-size="11" font-weight="600" letter-spacing="1.5" fill="#D7B94C">{{ __('cit_certificat.svg_devise') }}</text>
    <text x="1510" y="1052" text-anchor="end" font-size="11" fill="#DDEDE7">verifall.com</text>

    {{-- ─────────────────────────────────────────────── coins décoratifs --}}
    <g fill="none" stroke="url(#{{ $id }}-or)" stroke-width="3">
        <path d="M70 90 H130 M70 90 V150"/>
        <path d="M1470 90 H1530 M1530 90 V150"/>
        <path d="M70 1010 H130 M70 1010 V950"/>
        <path d="M1470 1010 H1530 M1530 1010 V950"/>
    </g>
</svg>
