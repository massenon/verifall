{{--
  | « Utilisateurs » (lot 3) — la tranche qui débloque : ouvrir un accès.
  |
  | Jusqu'ici, créer un compte d'administration passait par `StaffSeeder`,
  | c'est-à-dire par un développeur avec un accès console. Le porteur du projet
  | ne pouvait pas ouvrir l'accès d'un nouveau modérateur sans faire appel à
  | quelqu'un d'autre.
  |
  | Ni suspension ni suppression : la première demande une colonne qui n'existe
  | pas, la seconde est un geste RGPD déjà servi en deux temps, avec fenêtre de
  | rétractation. Le module complet — citoyens, mentors, KYC — est au lot 7.
  |
  | DataTables (pilote du 14 septembre 2026, migration Blade du 12 octobre) :
  | contrat complet dans `docs/DATATABLES_CONTRAT.md`. Les modales de
  | suspension/rétablissement sont DANS cette racine et pas après — Livewire ne
  | suit que le premier élément racine du composant.
--}}
@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.dataTables-2.3.8.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/buttons.dataTables-3.2.6.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.dateTime-1.6.3.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/searchBuilder.dataTables-1.8.4.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/responsive.dataTables-3.0.8.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/pages/datatables-staff.css') }}">
@endpush

<div>

    <x-premium.entete titre="Utilisateurs" :fil="['Utilisateurs']"
        sous="Les comptes et leurs rôles. Un compte du personnel n'entre qu'après avoir enrôlé sa double authentification — elle se pose à la première connexion, pas ici.">
        @can('create', App\Models\User::class)
            <x-slot:actions>
                <button type="button" wire:click="ouvrirCreation" class="btn-primaire"><x-lucide name="user-plus" />Nouveau compte</button>
            </x-slot:actions>
        @endcan
    </x-premium.entete>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
        <x-premium.chiffre icone="users" libelle="Comptes" :valeur="number_format($indicateurs['total'], 0, ',', ' ')"
            :aide="$indicateurs['personnel'].' au personnel'" />
        <x-premium.chiffre icone="id-card" libelle="Personnel" :valeur="number_format($indicateurs['personnel'], 0, ',', ' ')"
            aide="Portent au moins un rôle" />
        <x-premium.chiffre icone="key-round" libelle="Administrateurs" :valeur="number_format($indicateurs['administrateurs'], 0, ',', ' ')"
            :aide="$indicateurs['administrateurs'] <= 1 ? 'Le dernier ne peut pas être rétrogradé' : 'Accès complet'" />
        {{-- Le chiffre qui évite un faux signalement de panne : ces comptes ne
             peuvent pas encore entrer, et c'est normal — ils n'ont pas franchi
             l'enrôlement du second facteur. --}}
        <x-premium.chiffre icone="shield-check" libelle="Sans 2FA confirmée" :valeur="number_format($indicateurs['sans2fa'], 0, ',', ' ')"
            aide="Enrôlement à la première connexion" />
    </div>

    <div wire:ignore
         data-vdt="utilisateurs"
         data-vdt-source="{{ route('staff.users.data') }}"
         data-vdt-fiche="{{ route('staff.users.show', ['user' => '00000000-0000-0000-0000-000000000000']) }}"
         data-vdt-langue="{{ asset('assets/vendor/datatables/i18n/fr-FR-2.3.8.json') }}"
         data-vdt-vendor="{{ asset('assets/vendor/datatables/js') }}"
         data-vdt-evenement="{{ \App\Livewire\Staff\Utilisateurs::EVENEMENT_TABLE_A_RECHARGER }}"
         @can('user.export') data-vdt-export="1" @endcan>

        <section class="mb-4 flex flex-wrap items-center gap-3">
            <div class="flex-1 mb-0">
                <label for="users-q" class="etiquette">Rechercher un nom, une adresse</label>
<input id="users-q" type="search" data-vdt-recherche data-vdt-url="q" data-vdt-livewire="recherche" value="{{ $recherche }}" class="champ">
            </div>

            <select data-vdt-filtre="type" data-vdt-url="type" data-vdt-livewire="filtreType" class="champ w-auto" aria-label="Filtrer par type de compte">
                <option value="">Type : tous</option>
                <option value="personnel" @selected($filtreType === 'personnel')>Personnel</option>
                <option value="citoyens" @selected($filtreType === 'citoyens')>Citoyens</option>
            </select>

            <select data-vdt-filtre="role" data-vdt-url="role" data-vdt-livewire="filtreRole" class="champ w-auto" aria-label="Filtrer par rôle">
                <option value="">Rôle : tous</option>
                @foreach ($rolesDisponibles as $role)
                    <option value="{{ $role->value }}" @selected($filtreRole === $role->value)>{{ $role->label() }}</option>
                @endforeach
            </select>

            <select data-vdt-filtre="statut" data-vdt-url="statut" class="champ w-auto" aria-label="Filtrer par statut">
                <option value="">Statut : tous</option>
                <option value="actif">Actif</option>
                <option value="non_active">Non activé</option>
                <option value="suspendu">Suspendu</option>
            </select>

            <select data-vdt-filtre="kyc" data-vdt-url="kyc" class="champ w-auto" aria-label="Filtrer par état KYC">
                <option value="">KYC : tous</option>
                <option value="verifiee">Vérifiée</option>
                <option value="en_attente">Dossier en attente</option>
                <option value="non_verifiee">Non vérifiée</option>
            </select>

            <select data-vdt-filtre="activite" data-vdt-url="activite" class="champ w-auto" aria-label="Filtrer par activité">
                <option value="">Activité : toutes</option>
                <option value="recente">Récente</option>
                <option value="inactive">Inactive</option>
                <option value="jamais">Jamais connecté</option>
            </select>

            <div class="mb-0">
                <label class="etiquette">Inscrit depuis le</label>
<input type="text" data-vdt-filtre="inscrit_du" data-vdt-url="du" data-vdt-date class="champ">
            </div>
            <div class="mb-0">
                <label class="etiquette">jusqu'au</label>
<input type="text" data-vdt-filtre="inscrit_au" data-vdt-url="au" data-vdt-date class="champ">
            </div>

            <button type="button" data-vdt-reinitialiser class="btn-second">
                Réinitialiser
            </button>
        </section>

        <div role="alert" hidden data-vdt-erreur class="rounded-lg border border-red-300 dark:border-red-900 bg-red-50 dark:bg-red-950/40 text-red-800 dark:text-red-300 text-sm px-4 py-3 mb-4">
            <p data-vdt-erreur-message class="mb-2">Impossible de charger les comptes.</p>
            <button type="button" data-vdt-reessayer class="btn-second">Réessayer</button>
        </div>

        <table id="table-utilisateurs" style="width:100%" data-vdt-table>
            <thead>
                <tr>
                    <th scope="col">Personne</th>
                    <th scope="col">E-mail</th>
                    <th scope="col">Rôles</th>
                    <th scope="col">Statut</th>
                    <th scope="col">2FA</th>
                    <th scope="col">KYC</th>
                    <th scope="col">Unités</th>
                    <th scope="col">Points</th>
                    <th scope="col">Inscrit</th>
                    <th scope="col">Dernière connexion</th>
                    <th scope="col" class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    <x-offcanvas-form :open="$formulaireOuvert"
                      :title="$enEdition === null ? 'Nouveau compte' : 'Modifier le compte'"
                      close="fermerLeTiroir">
        <form wire:submit="enregistrer" class="space-y-4">
            <div class="grid sm:grid-cols-2 gap-4">
                <div class="mb-0">
                    <label for="u-prenom" class="etiquette">Prénom</label>
<input id="u-prenom" type="text" wire:model="prenom" class="champ">
                    @error('prenom') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
                <div class="mb-0">
                    <label for="u-nom" class="etiquette">Nom</label>
<input id="u-nom" type="text" wire:model="nom" class="champ">
                    @error('nom') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="u-email" class="etiquette">Adresse e-mail</label>
<input id="u-email" type="email" wire:model="email" class="champ">
                @error('email') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="u-profession" class="etiquette">Fonction</label>
<input id="u-profession" type="text" wire:model="profession" class="champ">
                @error('profession') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="u-mdp" class="etiquette">{{ $enEdition === null ? 'Mot de passe' : 'Nouveau mot de passe (vide = inchangé)' }}</label>
<input id="u-mdp" type="password" wire:model="motDePasse" autocomplete="new-password" class="champ">
                @error('motDePasse') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <fieldset class="border-t border-trait dark:border-nuit-trait pt-4">
                <legend class="text-sm font-bold text-encre dark:text-white mb-1">Rôles</legend>
                <p class="text-xs text-encre-3 dark:text-nuit-3 mb-3">
                    Un compte sans rôle est un citoyen ordinaire. Les droits exacts de chaque
                    rôle se lisent à l'écran <a href="{{ route('staff.roles') }}" class="text-brand-700 dark:text-brand underline">Rôles &amp; permissions</a>.
                </p>

                @php $soiMeme = $enEdition !== null && auth('staff')->user()?->uuid === $enEdition; @endphp

                @if ($soiMeme)
                    {{-- On ne modifie pas ses propres rôles : se retirer
                         `role.manage` d'un clic maladroit ferme la porte derrière
                         soi, et plus aucun écran ne permet de la rouvrir. --}}
                    <p class="text-sm text-amber-800 dark:text-amber-300 mb-3">
                        Ce sont vos propres rôles : ils ne peuvent pas être modifiés depuis ici.
                    </p>
                @endif

                <div class="space-y-2">
                    @foreach ($rolesDisponibles as $role)
                        <label class="flex items-start gap-2.5 {{ $soiMeme ? 'opacity-60' : 'cursor-pointer' }}">
                            <input type="checkbox" value="{{ $role->value }}" wire:model="roles"
                                   class="mt-0.5" @disabled($soiMeme)>
                            <span class="text-sm text-encre-2 dark:text-nuit-2">
                                {{ $role->label() }}
                                <span class="block text-xs text-encre-3 dark:text-nuit-3">{{ $role->description() }}</span>
                            </span>
                        </label>
                    @endforeach
                </div>
                @error('roles') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </fieldset>

            <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">
                La double authentification n'est pas posée ici : le guard du back-office
                l'exige, et la personne enrôle son propre secret à sa première connexion.
                La poser à sa place supposerait de le lui transmettre — par un canal qui
                annulerait l'intérêt du second facteur.
            </p>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="btn-primaire flex-1">Enregistrer</button>
                <button type="button" wire:click="fermerLeTiroir" class="btn-second">Annuler</button>
            </div>
        </form>
    </x-offcanvas-form>

    {{--
      | Les deux modales ci-dessous sont VOLONTAIREMENT dans cette racine, et
      | pas après sa fermeture : Livewire ne suit que le premier élément
      | racine du composant — ce que ce pilote a appris à ses dépens avant la
      | migration DataTables (`docs/DATATABLES_CONTRAT.md` §6.1). Les y laisser
      | posait l'état côté serveur sans jamais rien afficher au navigateur.
    --}}

    {{-- ══════════════════════════════════════════════ suspendre un compte --}}
    @if ($aSuspendre)
    @php($cible = \App\Models\User::query()->where('uuid', $aSuspendre)->first())
    <div class="voile !z-[79]" wire:click="annulerLaSuspension" aria-hidden="true"></div>

    {{-- `z-index` EN LIGNE : le build Tailwind est figé et ne contient aucune
         valeur arbitraire ; un `z-50` laisserait la modale SOUS le fond du
         tiroir staff (z-79). Même raison que dans `<x-delete-modal>`. --}}
    <div class="carte fixed left-4 right-4 top-1/2 -translate-y-1/2 mx-auto max-w-md p-6"
         style="z-index:90" role="alertdialog" aria-modal="true" aria-labelledby="susp-titre">

        <h2 id="susp-titre" class="text-lg font-titre font-bold text-encre dark:text-white mb-2">
            Suspendre {{ $cible?->name }} ?
        </h2>

        <p class="text-sm text-encre-2 dark:text-nuit-2 mb-3">
            Le compte perdra l'accès immédiatement et ses sessions ouvertes seront fermées.
            La personne recevra ce motif par courriel.
        </p>

        <form wire:submit="suspendre">
            <div class="mb-1">
                {{-- Obligatoire, et pas par formalisme : une sanction sans
                     raison lisible n'est pas contestable, et se transforme en
                     message au support puis en défiance. --}}
                <label for="susp-motif" class="etiquette">Motif communiqué à la personne *</label>
<textarea id="susp-motif" wire:model.blur="motifDeSuspension" rows="3" class="champ !h-auto py-2.5"></textarea>
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

{{-- ══════════════════════════════════════════════ rétablir un compte --}}
@if ($aRetablir)
    @php($cible = \App\Models\User::query()->where('uuid', $aRetablir)->first())
    <div class="voile !z-[79]" wire:click="annulerLaSuspension" aria-hidden="true"></div>

    <div class="carte fixed left-4 right-4 top-1/2 -translate-y-1/2 mx-auto max-w-md p-6"
         style="z-index:90" role="alertdialog" aria-modal="true" aria-labelledby="reta-titre">

        <h2 id="reta-titre" class="text-lg font-titre font-bold text-encre dark:text-white mb-2">
            Rétablir {{ $cible?->name }} ?
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

@push('scripts')
  <script src="{{ asset('assets/vendor/datatables/js/jquery-3.7.1.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables-2.3.8.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.buttons-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/buttons.html5-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/buttons.print-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/buttons.colVis-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.dateTime-1.6.3.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.searchBuilder-1.8.4.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.responsive-3.0.8.min.js') }}"></script>
  <script src="{{ asset('assets/js/verifall-datatables.js') }}"></script>
  <script src="{{ asset('assets/js/pages/staff-utilisateurs.js') }}"></script>
@endpush
