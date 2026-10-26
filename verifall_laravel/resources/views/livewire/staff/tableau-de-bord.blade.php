{{--
  | Le tableau de bord du personnel.
  |
  | ## L'ordre des blocs n'est pas décoratif
  |
  | La première question de quelqu'un qui ouvre un back-office est « qu'est-ce
  | qui m'attend ? », jamais « combien d'inscrits ? ». Ce qui exige une décision
  | humaine vient donc AVANT les volumes, et les avertissements d'installation
  | avant tout le reste.
  |
  | ## Les classes employées
  |
  | Uniquement celles du build Tailwind figé et de `staff.css` : `st-kpi`,
  | `st-card`, `pastille`. Une classe absente du build ne produit AUCUNE erreur
  | — elle est écrite, lisible, et ne fait rien. C'est le piège de ce projet.
--}}
<div class="p-6 lg:p-8">

    {{-- ── CE QUI NE VA PAS DANS L'INSTALLATION ────────────────────────────
         Un back-office qui n'affiche pas l'état de sa propre configuration
         laisse découvrir les pannes par les visiteurs. Les deux cas surveillés
         se sont réellement produits lors de la mise en ligne. --}}
    @foreach ($avertissements as $avertissement)
        <div class="mb-4 rounded-xl border p-4 {{ $avertissement['niveau'] === 'grave' ? 'border-critique bg-red-50 dark:bg-red-950/30' : 'border-amber-500 bg-amber-50 dark:bg-amber-950/30' }}">
            <p class="flex items-start gap-2 text-sm font-semibold {{ $avertissement['niveau'] === 'grave' ? 'text-critique' : 'text-amber-700 dark:text-amber-300' }} mb-0">
                <x-lucide name="triangle-alert" class="w-4 h-4" />
                <span>{{ $avertissement['texte'] }}</span>
            </p>
        </div>
    @endforeach

    <div class="mb-6">
        <h1 class="text-2xl md:text-3xl font-titre font-bold text-encre dark:text-white leading-tight mb-1">
            Bon retour, {{ $membre?->first_name ?? $membre?->name ?? 'à vous' }}
        </h1>
        <p class="text-encre-3 dark:text-nuit-3 text-sm mb-0">
            Ce qui attend une décision, puis l'activité de la plateforme.
        </p>
    </div>

    {{-- ── CE QUI ATTEND UNE DÉCISION HUMAINE ──────────────────────────────
         Ce bloc peut valoir zéro partout, et c'est une bonne nouvelle : elle
         s'affiche comme telle plutôt que de faire disparaître le bloc. Une
         file vide qu'on ne voit pas est indistinguable d'une file qu'on a
         oublié de regarder. --}}
    <h2 class="text-sm font-titre font-bold text-encre dark:text-white mb-3">À traiter maintenant</h2>

    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-8">
        @php
            $files = [
                ['cle' => 'arbitrages', 'libelle' => 'En arbitrage', 'route' => null,
                 'aide' => 'Les moteurs se contredisent : un humain tranche.'],
                ['cle' => 'signalements', 'libelle' => 'Signalements', 'route' => 'staff.content-reports',
                 'aide' => 'Contenus signalés, en attente d\'examen.'],
                ['cle' => 'messages', 'libelle' => 'Messages', 'route' => 'staff.contact-messages',
                 'aide' => 'Reçus ou pris en charge sans être terminés.'],
                ['cle' => 'interrompues', 'libelle' => 'Analyses interrompues', 'route' => null,
                 'aide' => 'Une panne technique a coupé l\'analyse.'],
                // Deux files nées le 1er septembre 2026. Elles n'existaient pas :
                // l'identité était un drapeau posé à la main, et une contestation
                // n'avait nulle part où atterrir.
                //
                // Commentaire PHP et non Blade : nous sommes DANS un bloc @php,
                // où `{{-- --}}` n'est pas interprété et casse la syntaxe.
                ['cle' => 'kyc', 'libelle' => 'Identités à examiner', 'route' => 'staff.kyc',
                 'aide' => 'Des pièces d\'identité attendent : la personne attend, et nous les détenons.'],
                ['cle' => 'contestations', 'libelle' => 'Verdicts contestés', 'route' => null,
                 'aide' => 'Quelqu\'un dit que nous nous sommes trompés.'],
            ];
        @endphp

        @foreach ($files as $file)
            @php $valeur = $aTraiter[$file['cle']]; @endphp

            <div class="carte p-4">
                <div class="font-titre text-[26px] leading-none font-semibold tabular-nums {{ $valeur > 0 ? 'text-critique' : '' }}">{{ number_format($valeur, 0, ',', ' ') }}</div>
                <div class="mt-2 text-[13px] texte-2">{{ $file['libelle'] }}</div>
                <p class="text-xs text-encre-3 dark:text-nuit-3 mt-2 mb-0">{{ $file['aide'] }}</p>

                @if ($valeur > 0 && $file['route'] !== null)
                    <a href="{{ route($file['route']) }}" class="inline-flex items-center gap-1 text-xs font-bold text-brand-700 dark:text-brand mt-2">
                        Ouvrir la file
                        <x-lucide name="arrow-right" class="w-4 h-4" />
                    </a>
                @endif
            </div>
        @endforeach
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        @include('livewire.staff.partials.sante-du-systeme')
        @include('livewire.staff.partials.activite-signalements')
        @include('livewire.staff.partials.performance-articles')
        @include('livewire.staff.partials.verdicts-contestes')
        {{-- Conditionné : la file des vérifications citoyennes — l'affirmation
             soumise, et par qui — n'a rien à faire sous les yeux d'un mentor ou
             d'un rédacteur. Le composant rend déjà une collection vide sans le
             droit ; masquer aussi le cadre évite un panneau vide qui laisserait
             croire qu'il ne se passe rien. --}}
        @if ($peutVoirLesSignalements)
            @include('livewire.staff.partials.activites-factchecking')
        @endif
        @include('livewire.staff.partials.navigateurs-thematiques')
        @include('livewire.staff.partials.nouveaux-citoyens')
        @include('livewire.staff.partials.journal-audit')


        {{-- ── L'ÉTAT DU LABORATOIRE ─────────────────────────────────────── --}}
        <div class="carte">
            <div class="carte-tete">
                <h2 class="font-titre font-bold text-encre dark:text-white mb-0">Le laboratoire, en direct</h2>
            </div>
            <div class="carte-corps">
                <dl class="mb-0">
                    @foreach ([
                        'en_cours' => 'Analyses en cours',
                        'en_file' => 'En file d\'attente',
                        'reprises' => 'Reprises programmées',
                    ] as $cle => $libelle)
                        <div class="flex items-baseline justify-between py-2 border-b border-trait dark:border-nuit-trait last:border-0">
                            <dt class="text-sm text-encre-3 dark:text-nuit-3">{{ $libelle }}</dt>
                            <dd class="font-titre font-bold text-encre dark:text-white mb-0">{{ $laboratoire[$cle] }}</dd>
                        </div>
                    @endforeach
                </dl>

                {{-- Une reprise programmée n'est PAS un échec : l'analyse
                     repartira d'elle-même. Le dire ici évite qu'on la traite
                     comme une panne. --}}
                @if ($laboratoire['reprises'] > 0)
                    <p class="text-xs text-encre-3 dark:text-nuit-3 mt-3 mb-0">
                        Une reprise programmée repart d'elle-même : il n'y a rien à faire.
                    </p>
                @endif
            </div>
        </div>

        {{-- ── LES VOLUMES, TELS QUE LE PROTOTYPE LES POSE ────────────────── --}}
        <div class="carte lg:col-span-2">
            <div class="carte-tete">
                <h2 class="font-titre font-bold text-encre dark:text-white mb-0">La plateforme</h2>
            </div>
            <div class="carte-corps">
                <div class="grid grid-cols-2 lg:grid-cols-5 gap-4">
                    @foreach ([
                        'citoyens' => 'Citoyens inscrits',
                        'abonnes' => 'Abonnés confirmés',
                        'faits' => 'Faits publiés',
                        'cours' => 'Cours publiés',
                        'signalements' => 'Signalements reçus',
                    ] as $cle => $libelle)
                        <div>
                            <div class="font-titre text-[26px] leading-none font-semibold tabular-nums">{{ number_format($volumes[$cle], 0, ',', ' ') }}</div>
                            <div class="mt-2 text-[13px] texte-2">{{ $libelle }}</div>
                        </div>
                    @endforeach
                </div>

                {{-- « Confirmés » et non « inscrits » : compter les adresses non
                     confirmées gonflerait un chiffre que le premier envoi
                     démentirait publiquement. --}}
                <p class="text-xs text-encre-3 dark:text-nuit-3 mt-4 mb-0">
                    Les abonnés comptés sont ceux qui ont confirmé leur adresse et ne se sont pas désabonnés.
                </p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5 mt-5">

        {{-- ── DERNIÈRES VÉRIFICATIONS ───────────────────────────────────── --}}
        <div class="carte">
            <div class="carte-tete">
                <h2 class="font-titre font-bold text-encre dark:text-white mb-0">Dernières vérifications</h2>
            </div>
            <div class="carte-corps">
                @forelse ($verifications as $verification)
                    <div class="flex items-start gap-3 py-2.5 border-b border-trait dark:border-nuit-trait last:border-0">
                        <span class="pastille {{ $verification->status->badge() }}">{{ $verification->status->libelle() }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-encre dark:text-white mb-0 truncate">
                                {{ \Illuminate\Support\Str::limit($verification->claim ?? $verification->type->libelle(), 70) }}
                            </p>
                            <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">{{ $verification->created_at?->diffForHumans() }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-encre-3 dark:text-nuit-3 mb-0">
                        Aucune vérification pour l'instant. La première soumission apparaîtra ici.
                    </p>
                @endforelse
            </div>
        </div>

        {{-- ── DERNIERS FAITS PUBLIÉS ────────────────────────────────────── --}}
        <div class="carte">
            <div class="carte-tete flex items-center justify-between">
                <h2 class="font-titre font-bold text-encre dark:text-white mb-0">Derniers faits publiés</h2>
                <a href="{{ route('staff.knowledge') }}" class="text-xs font-bold text-brand-700 dark:text-brand">Tout voir →</a>
            </div>
            <div class="carte-corps">
                @forelse ($faits as $fait)
                    <div class="flex items-start gap-3 py-2.5 border-b border-trait dark:border-nuit-trait last:border-0">
                        <span class="pastille {{ $fait->verdict->badge() }}">{{ $fait->verdict->libelle() }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="text-sm text-encre dark:text-white mb-0 truncate">{{ $fait->title }}</p>
                            <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">{{ $fait->created_at?->diffForHumans() }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-encre-3 dark:text-nuit-3 mb-0">
                        Aucun fait publié. Le premier verdict publié apparaîtra ici.
                    </p>
                @endforelse
            </div>
        </div>
    </div>
</div>
