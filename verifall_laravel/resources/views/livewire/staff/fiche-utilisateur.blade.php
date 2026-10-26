{{--
  | Fiche d'un compte (14 septembre 2026) — citoyens ET mentors.
  |
  | À gauche la carte d'identité, à droite un onglet DataTables par historique.
  | Chaque onglet est une racine `[data-vdt]` du contrat commun
  | (`docs/DATATABLES_CONTRAT.md`, section « Fiche profil ») : il n'est monté
  | qu'à sa première ouverture — une table mesurée dans un panneau masqué
  | calcule mal ses colonnes, et seize requêtes au chargement pour un onglet
  | regardé seraient du gaspillage.
  |
  | Toute la zone des onglets est en `wire:ignore` : Livewire redessine la
  | carte après une suspension, jamais les tables. Les modales sont DANS la
  | racine du composant (contrat §6.1).
--}}
<div>

    <x-premium.entete titre="Fiche du compte">
        L'identité du compte et tout son historique sur la plateforme.
        <x-slot:actions>
            <a href="{{ route('staff.users') }}" class="btn-second">
            <x-lucide name="arrow-left" class="w-4 h-4" />
            Retour aux utilisateurs
        </a>
        </x-slot:actions>
    </x-premium.entete>

    <div class="vfu-grille">

        {{-- ═══════════════════════════════════════════ carte d'identité --}}
        <aside class="carte p-5 vfu-carte" aria-label="Identité du compte">
            <div class="vfu-entete">
                <span class="vfu-avatar" aria-hidden="true">{{ $identite['initiales'] }}</span>
                <div class="vfu-entete-texte">
                    <p class="font-titre font-bold text-encre dark:text-white text-lg leading-tight mb-0">{{ $identite['nom'] }}</p>
                    <p class="text-sm text-encre-3 dark:text-nuit-3 mb-0 vfu-coupure">{{ $identite['email'] }}</p>
                    @if ($identite['profession'])
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">{{ $identite['profession'] }}</p>
                    @endif
                </div>
            </div>

            <div class="flex flex-wrap gap-2 mb-4">
                @foreach ($identite['roles'] as $role)
                    <span class="pastille p-info">{{ $role }}</span>
                @endforeach

                @if ($identite['statut'] === 'suspendu')
                    <span class="pastille p-alerte">Suspendu</span>
                @elseif ($identite['statut'] === 'actif')
                    <span class="pastille p-bon">Actif</span>
                @else
                    <span class="pastille p-neutre">Non activé</span>
                @endif
            </div>

            @if ($identite['suspension'])
                <div class="vfu-alerte mb-4" role="note">
                    <p class="text-sm font-semibold mb-1">
                        Suspendu le {{ $identite['suspension']['depuis'] }}@if ($identite['suspension']['par']) par {{ $identite['suspension']['par'] }}@endif
                    </p>
                    @if ($identite['suspension']['motif'])
                        <p class="text-sm mb-0">{{ $identite['suspension']['motif'] }}</p>
                    @endif
                </div>
            @endif

            @if ($identite['suppressionDemandee'])
                <div class="vfu-alerte mb-4" role="note">
                    <p class="text-sm mb-0">Suppression demandée le {{ $identite['suppressionDemandee'] }} (délai de rétractation en cours).</p>
                </div>
            @endif

            <dl class="vfu-champs">
                <div>
                    <dt>Dernière connexion</dt>
                    <dd>{{ $identite['derniereConnexion'] ?? 'Jamais' }}</dd>
                </div>
                <div>
                    <dt>Inscrit le</dt>
                    <dd>{{ $identite['inscritLe'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Adresse vérifiée (OTP)</dt>
                    <dd>{{ $identite['otpVerifie'] ? 'Oui, le '.$identite['otpVerifie'] : 'Non' }}</dd>
                </div>
                <div>
                    <dt>E-mail confirmé</dt>
                    <dd>{{ $identite['emailVerifie'] ? 'Oui, le '.$identite['emailVerifie'] : 'Non' }}</dd>
                </div>
                @if ($identite['deuxFacteurs'] !== null)
                    <div>
                        <dt>Double authentification</dt>
                        <dd>{{ $identite['deuxFacteurs'] }}</dd>
                    </div>
                @endif
                <div>
                    <dt>Ville</dt>
                    <dd>{{ $identite['ville'] ?? '—' }}</dd>
                </div>
                <div>
                    <dt>Identité (KYC)</dt>
                    <dd>{{ $identite['kyc'] }}</dd>
                </div>
            </dl>

            <div class="vfu-compteurs">
                <div>
                    <p class="vfu-compteur-valeur">{{ number_format($identite['unites'], 0, ',', ' ') }}</p>
                    <p class="vfu-compteur-libelle">Unités IA</p>
                </div>
                <div>
                    <p class="vfu-compteur-valeur">{{ number_format($identite['points'], 0, ',', ' ') }}</p>
                    <p class="vfu-compteur-libelle">Points</p>
                </div>
                <div>
                    <p class="vfu-compteur-valeur">{{ $identite['serie'] }}</p>
                    <p class="vfu-compteur-libelle">Série (record {{ $identite['meilleureSerie'] }})</p>
                </div>
            </div>

            @if ($peutModifier || $peutSuspendre || ($peutVoirMentors && in_array('Mentor', $compte->getRoleNames()->all(), true)))
                <div class="vfu-actions">
                    @if ($peutModifier)
                        {{-- Le tiroir EXISTANT de l'écran Utilisateurs : il porte les
                             garde-fous du lot 3 (propres rôles, dernier admin). --}}
                        <a href="{{ route('staff.users', ['modifier' => $compte->uuid]) }}" class="btn-second">
                            <x-lucide name="pencil" class="w-4 h-4" />
                            Modifier
                        </a>
                    @endif

                    @if ($peutSuspendre)
                        @if ($identite['statut'] === 'suspendu')
                            <button type="button" wire:click="ouvrirLeRetablissement" class="btn-second">
                                <x-lucide name="lock-open" class="w-4 h-4" />
                                Rétablir
                            </button>
                        @else
                            <button type="button" wire:click="ouvrirLaSuspension" class="btn-danger">
                                <x-lucide name="ban" class="w-4 h-4" />
                                Suspendre
                            </button>
                        @endif
                    @endif

                    @if ($peutVoirMentors && in_array('Mentor', $compte->getRoleNames()->all(), true))
                        <a href="{{ route('staff.mentors', ['q' => $compte->email]) }}" class="btn-fantome">
                            <x-lucide name="presentation" class="w-4 h-4" />
                            Voir dans Mentors
                        </a>
                    @endif
                </div>
            @endif
        </aside>

        {{-- ═══════════════════════════════════════════ historiques --}}
        <section class="vfu-historique" aria-label="Historique du compte" wire:ignore data-vfu-onglets>
            <div class="onglets mb-4" role="tablist" aria-label="Historiques">
                @foreach ($onglets as $onglet)
                    <button type="button" role="tab" class="onglet {{ $loop->first ? 'is-active' : '' }}"
                            id="vfu-onglet-{{ $onglet['cle'] }}"
                            aria-controls="vfu-panneau-{{ $onglet['cle'] }}"
                            aria-selected="{{ $loop->first ? 'true' : 'false' }}"
                            tabindex="{{ $loop->first ? '0' : '-1' }}"
                            data-vfu-onglet="{{ $onglet['cle'] }}">
                        {{ $onglet['libelle'] }}
                        <span class="vfu-pastille">{{ number_format($onglet['nombre'], 0, ',', ' ') }}</span>
                    </button>
                @endforeach
            </div>

            @foreach ($onglets as $onglet)
                <div role="tabpanel" id="vfu-panneau-{{ $onglet['cle'] }}" aria-labelledby="vfu-onglet-{{ $onglet['cle'] }}"
                     @if (! $loop->first) hidden @endif
                     data-vdt="fiche-{{ $onglet['cle'] }}"
                     data-vdt-source="{{ route('staff.users.show.data', ['user' => $compte->uuid, 'onglet' => $onglet['cle']]) }}"
                     data-vdt-langue="{{ asset('assets/vendor/datatables/i18n/fr-FR-2.3.8.json') }}"
                     data-vdt-vendor="{{ asset('assets/vendor/datatables/js') }}"
                     @if ($onglet['cle'] === 'journal') data-vdt-evenement="{{ \App\Livewire\Staff\FicheUtilisateur::EVENEMENT_TABLE_A_RECHARGER }}" @endif>

                    <div class="mb-4 flex flex-wrap items-center gap-3">
                        <div class="flex-1 mb-0">
                            <label for="vfu-q-{{ $onglet['cle'] }}" class="etiquette">Rechercher dans « {{ $onglet['libelle'] }} »</label>
<input id="vfu-q-{{ $onglet['cle'] }}" type="search" autocomplete="off" data-vdt-recherche class="champ">
                        </div>

                        @foreach (\App\Core\Identity\Services\FicheDuCompte::optionsDesFiltres($onglet['cle']) as $cle => $filtre)
                            <select class="champ w-auto" aria-label="Filtrer par {{ mb_strtolower($filtre['libelle']) }}" data-vdt-filtre="{{ $cle }}">
                                <option value="">{{ $filtre['libelle'] }} : tous</option>
                                @foreach ($filtre['options'] as $valeur => $texte)
                                    <option value="{{ $valeur }}">{{ $texte }}</option>
                                @endforeach
                            </select>
                        @endforeach
                    </div>

                    <div class="carte p-4 mb-4" role="alert" hidden data-vdt-erreur>
                        <p class="text-sm text-encre dark:text-white mb-2" data-vdt-erreur-message>Impossible de charger cet historique.</p>
                        <button type="button" class="btn-second" data-vdt-reessayer>Réessayer</button>
                    </div>

                    <div class="carte p-4">
                        <table id="table-fiche-{{ $onglet['cle'] }}" style="width:100%" data-vdt-table></table>
                        <noscript>
                            <p class="text-sm text-encre-3 dark:text-nuit-3 mb-0">Cet historique demande JavaScript.</p>
                        </noscript>
                    </div>
                </div>
            @endforeach
        </section>
    </div>

{{-- ══════════════════════════════════════════════ suspendre ce compte --}}
@if ($suspensionOuverte)
    <div class="voile !z-[79]" wire:click="annulerLaSuspension" aria-hidden="true"></div>

    {{-- `z-index` EN LIGNE : même raison que sur l'écran Utilisateurs (build figé). --}}
    <div class="carte fixed left-4 right-4 top-1/2 -translate-y-1/2 mx-auto max-w-md p-6"
         style="z-index:90" role="alertdialog" aria-modal="true" aria-labelledby="vfu-susp-titre">

        <h2 id="vfu-susp-titre" class="text-lg font-titre font-bold text-encre dark:text-white mb-2">
            Suspendre {{ $identite['nom'] }} ?
        </h2>

        <p class="text-sm text-encre-2 dark:text-nuit-2 mb-3">
            Le compte perdra l'accès immédiatement et ses sessions ouvertes seront fermées.
            La personne recevra ce motif par courriel.
        </p>

        <form wire:submit="suspendre">
            <div class="mb-1">
                <label for="vfu-susp-motif" class="etiquette">Motif communiqué à la personne *</label>
<textarea id="vfu-susp-motif" wire:model.blur="motifDeSuspension" rows="3" class="champ !h-auto py-2.5"></textarea>
            </div>
            @error('motifDeSuspension')
                <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p>
            @enderror

            <div class="flex gap-2 justify-end mt-5">
                <button type="button" wire:click="annulerLaSuspension" class="btn-second">Annuler</button>
                <button type="submit" wire:loading.attr="disabled" class="btn-primaire">Suspendre</button>
            </div>
        </form>
    </div>
@endif

{{-- ══════════════════════════════════════════════ rétablir ce compte --}}
@if ($retablissementOuvert)
    <div class="voile !z-[79]" wire:click="annulerLaSuspension" aria-hidden="true"></div>

    <div class="carte fixed left-4 right-4 top-1/2 -translate-y-1/2 mx-auto max-w-md p-6"
         style="z-index:90" role="alertdialog" aria-modal="true" aria-labelledby="vfu-reta-titre">

        <h2 id="vfu-reta-titre" class="text-lg font-titre font-bold text-encre dark:text-white mb-2">
            Rétablir {{ $identite['nom'] }} ?
        </h2>

        <p class="text-sm text-encre-3 dark:text-nuit-3 mb-5">
            La personne retrouve l'ensemble de ses accès et en est informée par courriel.
            Le motif de la suspension est effacé ; la décision reste au journal d'audit.
        </p>

        <div class="flex gap-2 justify-end">
            <button type="button" wire:click="annulerLaSuspension" class="btn-second">Annuler</button>
            <button type="button" wire:click="retablir" wire:loading.attr="disabled"
                    class="btn-primaire">Rétablir</button>
        </div>
    </div>
@endif
</div>

{{-- ═══════════════════════ DataTables — chargé sur CET écran seulement --}}
@push('styles')
    <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.dataTables-2.3.8.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/buttons.dataTables-3.2.6.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/responsive.dataTables-3.0.8.min.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/pages/datatables-staff.css') }}">
    <link rel="stylesheet" href="{{ asset('assets/css/pages/staff-fiche-utilisateur.css') }}">
@endpush

@push('scripts')
    {{-- Ni DateTime ni SearchBuilder : aucun onglet n'a de période ni de
         filtre avancé. Ni html5 ni print : la fiche ne propose pas d'export. --}}
    <script src="{{ asset('assets/vendor/datatables/js/jquery-3.7.1.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/datatables/js/dataTables-2.3.8.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/datatables/js/dataTables.buttons-3.2.6.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/datatables/js/buttons.colVis-3.2.6.min.js') }}"></script>
    <script src="{{ asset('assets/vendor/datatables/js/dataTables.responsive-3.0.8.min.js') }}"></script>
    <script src="{{ asset('assets/js/verifall-datatables.js') }}"></script>
    <script src="{{ asset('assets/js/pages/staff-fiche-utilisateur.js') }}"></script>
@endpush
