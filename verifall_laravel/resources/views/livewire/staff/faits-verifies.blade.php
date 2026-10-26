@php
    use App\Models\VerifiedFact;

    $peutPublier = auth('staff')->user()?->can('publish', VerifiedFact::class) ?? false;
@endphp

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.dataTables-2.3.8.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/buttons.dataTables-3.2.6.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.dateTime-1.6.3.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/searchBuilder.dataTables-1.8.4.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/responsive.dataTables-3.0.8.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/pages/datatables-staff.css') }}">
@endpush

<div>

    {{-- Fil d'Ariane — même chemin que le prototype. --}}
<x-premium.entete titre="Fact checks">
        La base des vérifications publiées : verdicts, sources et démentis officiels.
        <x-slot:actions>
            <div class="flex flex-wrap gap-2">
            @can('create', VerifiedFact::class)
                <button type="button" class="btn-second" wire:click="ouvrirImport">
                    <x-lucide name="upload" class="w-4 h-4" /> Importer
                </button>
            @endcan

            @can('knowledge_base.export')
                <button type="button" class="btn-second" wire:click="exporter">
                    <x-lucide name="download" class="w-4 h-4" /> Exporter
                </button>
            @endcan

            @can('create', VerifiedFact::class)
                <button type="button" class="btn-primaire" wire:click="ouvrirCreation">
                    <x-lucide name="plus" class="w-4 h-4" /> Nouveau fact check
                </button>
            @endcan
        </div>
        </x-slot:actions>
    </x-premium.entete>

    {{-- Quatre indicateurs, tous MESURÉS : ils viennent de
         DepotFaitsVerifies::indicateurs(), aucun n'est écrit dans le gabarit. --}}
    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff"
               :value="number_format($indicateurs['publies'], 0, ',', ' ')"
               label="Fact checks publiés"
               trend="servis sans consulter un moteur" />

        <x-kpi variant="staff"
               :value="number_format($indicateurs['dementis'], 0, ',', ' ')"
               label="Démentis"
               trend="verdicts « faux » et « trompeur »" />

        <x-kpi variant="staff"
               :value="$indicateurs['publies'] > 0
                    ? round($indicateurs['sources_multiples'] * 100 / $indicateurs['publies']).' %'
                    : '—'"
               label="Sourcés multi-références"
               :trend="$indicateurs['sources_multiples'].' fait(s) appuyé(s) par au moins deux sources'" />

        <x-kpi variant="staff"
               :value="$indicateurs['publies'] > 0
                    ? number_format($indicateurs['anciennete_jours'], 1, ',', ' ').' j'
                    : '—'"
               label="Ancienneté moyenne"
               trend="fraîcheur du corpus publié" />
    </x-kpi-grid>

    <div wire:ignore
         data-vdt="faits-verifies"
         data-vdt-source="{{ route('staff.knowledge.data') }}"
         data-vdt-langue="{{ asset('assets/vendor/datatables/i18n/fr-FR-2.3.8.json') }}"
         data-vdt-vendor="{{ asset('assets/vendor/datatables/js') }}"
         data-vdt-evenement="{{ \App\Livewire\Staff\FaitsVerifies::EVENEMENT_TABLE_A_RECHARGER }}">

        {{-- Recherche débouncée 350 ms + filtres. --}}
        <div class="carte p-4 mb-4">
            <div class="flex flex-col md:flex-row gap-3">
                <div class="relative flex-1">
                    <label class="sr-only" for="crud-search">Rechercher</label>
                    <x-lucide name="search" class="absolute left-3.5 top-1/2 -translate-y-1/2 text-encre-3 dark:text-nuit-3 w-4 h-4" />                    <input id="crud-search" type="search" class="champ !pl-10"
                           data-vdt-recherche data-vdt-url="q" data-vdt-livewire="recherche"
                           placeholder="Rechercher — affirmation ou mot-clé…">
                </div>

                <div class="flex flex-wrap gap-2">
                    <select class="champ !w-auto" data-vdt-filtre="verdict" data-vdt-url="verdict" data-vdt-livewire="filtreVerdict" aria-label="Filtrer par verdict">
                        <option value="">Verdict : tous</option>
                        @foreach ($verdicts as $cas)
                            <option value="{{ $cas->value }}">{{ $cas->libelle() }}</option>
                        @endforeach
                    </select>

                    <select class="champ !w-auto" data-vdt-filtre="statut" data-vdt-url="statut" data-vdt-livewire="filtreStatut" aria-label="Filtrer par statut">
                        <option value="">Statut : tous</option>
                        <option value="publie">Publié</option>
                        <option value="brouillon">Brouillon</option>
                    </select>

                    <button type="button" class="btn-fantome" data-vdt-reinitialiser>Réinitialiser</button>
                </div>
            </div>
        </div>

        <div role="alert" hidden data-vdt-erreur class="rounded-lg border border-red-300 dark:border-red-900 bg-red-50 dark:bg-red-950/40 text-red-800 dark:text-red-300 text-sm px-4 py-3 mb-4">
            <p data-vdt-erreur-message class="mb-2">Impossible de charger les fact checks.</p>
            <button type="button" data-vdt-reessayer class="btn-second">Réessayer</button>
        </div>

        <table id="table-faits-verifies" style="width:100%" data-vdt-table>
            <thead>
                <tr>
                    <th scope="col">Affirmation vérifiée</th>
                    <th scope="col">Verdict</th>
                    <th scope="col">Sources</th>
                    <th scope="col">Réutilisations</th>
                    <th scope="col">Mise à jour</th>
                    <th scope="col">Statut</th>
                    <th scope="col">Mots-clés</th>
                    <th scope="col" class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody></tbody>
        </table>
    </div>

    {{-- ------------------------------------------------------- tiroir de saisie --}}
    <x-offcanvas-form variant="staff" id="oc-fact"
                      :open="$tiroirOuvert && $mode !== 'apercu'"
                      close="fermerLeTiroir"
                      :title="$mode === 'edition' ? 'Modifier le fact check' : 'Nouveau fact check'">

        <p class="text-xs font-bold tracking-[.12em] uppercase text-encre-3 dark:text-nuit-3 mb-4">Informations</p>

        <label class="mb-4">
            <span class="etiquette">Affirmation à vérifier</span>
            <input type="text" class="champ" wire:model.live.debounce.500ms="affirmation"
                   placeholder="Texte exact de l'affirmation"
                   @error('affirmation') aria-invalid="true" @enderror>
            @error('affirmation') <span class="aide text-critique">{{ $message }}</span> @enderror
        </label>

        <label class="mb-4">
            <span class="etiquette">Verdict</span>
            <select class="champ" wire:model="verdict">
                @foreach ($verdicts as $cas)
                    <option value="{{ $cas->value }}">{{ $cas->libelle() }}</option>
                @endforeach
            </select>
            @error('verdict') <span class="aide text-critique">{{ $message }}</span> @enderror
        </label>

        {{-- Correction d'un verdict déjà rendu : la raison part au journal
             PUBLIC des corrections. Le champ n'apparaît qu'en édition, parce
             qu'un premier verdict n'est pas une correction. --}}
        @if ($mode === 'edition')
            <label class="mb-4">
                <span class="etiquette">Raison de la correction — publiée</span>
                <textarea rows="3" class="champ !h-auto py-2.5" wire:model="raisonDeLaCorrection"
                          placeholder="Ce que nous avons appris depuis, et qui change le verdict…"></textarea>
                <span class="aide">
                    Obligatoire si vous changez le verdict. Elle paraîtra sur la page publique
                    des corrections, à côté de l'ancien verdict — qui, lui, ne disparaît pas.
                </span>
                @error('raisonDeLaCorrection') <span class="aide text-critique">{{ $message }}</span> @enderror
            </label>
        @endif

        <label class="mb-4">
            <span class="etiquette">Sources (une par ligne — « url » ou « url | nom »)</span>
            <textarea rows="3" class="champ !h-auto py-2.5" wire:model="sourcesTexte"
                      placeholder="https://arcep.bj/communique-2026 | ARCEP Bénin"></textarea>
            <span class="aide">
                Une ligne sans URL valide est ignorée. Un verdict publié sans source demande
                qu'on vous croie sur parole.
            </span>
            @error('sourcesTexte') <span class="aide text-critique">{{ $message }}</span> @enderror
        </label>

        <label class="mb-4">
            <span class="etiquette">Mots-clés de rapprochement</span>
            <input type="text" class="champ" wire:model.live.debounce.500ms="motsCles"
                   placeholder="mots séparés par des espaces">
            {{-- La phrase demandée par le sprint : c'est CE champ qui décide si
                 le fait ressortira le jour où la rumeur revient. --}}
            <span class="aide">
                Ce sont ces mots qui feront retrouver le fait quand la rumeur reviendra :
                laissés vides, il dormira dans une base que personne ne consultera.
            </span>
            @if ($motsClesProposes !== '')
                <span class="aide">
                    Proposition depuis l'affirmation : <strong>{{ $motsClesProposes }}</strong>
                    @if ($motsCles !== $motsClesProposes)
                        — <button type="button" class="font-bold text-brand-700 dark:text-brand underline"
                                  wire:click="$set('motsCles', @js($motsClesProposes))">reprendre</button>
                    @endif
                </span>
            @endif
            @error('motsCles') <span class="aide text-critique">{{ $message }}</span> @enderror
        </label>

        <div class="mb-4">
            <span class="etiquette">Analyse complète (publiée)</span>

            {{-- Quill écrit dans son propre DOM : sans wire:ignore, le premier
                 rafraîchissement Livewire effacerait la barre d'outils et la
                 saisie en cours. Le pont vers l'état est explicite, plus bas. --}}
            <div class="editeur-riche" wire:ignore>
                <div data-quill-hote></div>
            </div>

            <span class="aide">Éditeur riche — gras, listes, liens, citations.</span>
            @error('analyse') <span class="aide text-critique">{{ $message }}</span> @enderror
        </div>

        <x-slot:footer>
            <button type="button" class="btn-second" wire:click="fermerLeTiroir">Annuler</button>

            <button type="button" class="btn-second" wire:click="enregistrer"
                    wire:loading.attr="disabled">
                Enregistrer le brouillon
            </button>

            {{-- Geste DISTINCT : publier engage la plateforme, et le libellé le
                 dit plutôt que de laisser croire à une simple sauvegarde. --}}
            @if ($peutPublier)
                <button type="button" class="btn-primaire" wire:click="publier"
                        wire:loading.attr="disabled">
                    Publier
                </button>
            @endif
        </x-slot:footer>
    </x-offcanvas-form>

    {{-- ------------------------------------------------------------- aperçu --}}
    <x-offcanvas-form variant="staff" id="oc-apercu"
                      :open="$tiroirOuvert && $mode === 'apercu'"
                      close="fermerLeTiroir"
                      title="Aperçu du fact check">

        <p class="text-lg font-titre font-bold text-encre dark:text-white mb-2">{{ $affirmation }}</p>

        <p class="mb-4">
            @php $cas = \App\Enums\VerdictFactCheck::tryFrom($verdict); @endphp
            @if ($cas)
                <x-badge-statut variant="staff"
                                :statut="$cas === \App\Enums\VerdictFactCheck::Vrai ? 'vrai' : ($cas === \App\Enums\VerdictFactCheck::Faux ? 'faux' : ($cas === \App\Enums\VerdictFactCheck::Trompeur ? 'trompeur' : 'non-verifiable'))"
                                :label="$cas->libelle()" />
            @endif
        </p>

        <p class="text-xs font-bold tracking-[.12em] uppercase text-encre-3 dark:text-nuit-3 mb-2">Sources</p>
        @php $lignes = collect(preg_split('/\R/', $sourcesTexte))->filter(fn ($l) => trim($l) !== ''); @endphp
        @if ($lignes->isEmpty())
            <p class="text-sm text-encre-3 dark:text-nuit-3 mb-4">Aucune source enregistrée pour ce fait.</p>
        @else
            <ul class="text-sm mb-4 space-y-1">
                @foreach ($lignes as $ligne)
                    <li class="break-words">{{ trim($ligne) }}</li>
                @endforeach
            </ul>
        @endif

        <p class="text-xs font-bold tracking-[.12em] uppercase text-encre-3 dark:text-nuit-3 mb-2">Mots-clés</p>
        <p class="text-sm mb-4">{{ $motsCles !== '' ? $motsCles : '—' }}</p>

        <p class="text-xs font-bold tracking-[.12em] uppercase text-encre-3 dark:text-nuit-3 mb-2">Analyse</p>
        {{-- Purifié À L'AFFICHAGE en plus de la persistance : cette table est
             justement celle dont le contenu est resservi partout. --}}
        <div class="prose-lesson text-sm text-encre-2 dark:text-nuit-2">{!! clean($analyse) !!}</div>

        <x-slot:footer>
            <button type="button" class="btn-second" wire:click="fermerLeTiroir">Fermer</button>
            @if ($uuidEnCours)
                @can('update', VerifiedFact::class)
                    <button type="button" class="btn-primaire"
                            wire:click="ouvrirEdition('{{ $uuidEnCours }}')">Modifier</button>
                @endcan
            @endif
        </x-slot:footer>
    </x-offcanvas-form>

    {{-- ------------------------------------------------------------- import --}}
    <x-offcanvas-form variant="staff" id="oc-import"
                      :open="$importOuvert"
                      close="fermerImport"
                      title="Importer des fact checks">

        <p class="text-sm text-encre-2 dark:text-nuit-2 mb-4">
            Un fichier CSV, séparateur « ; », une ligne par affirmation :
            <strong>affirmation ; verdict ; sources ; analyse ; mots-clés</strong>.
            Le verdict s'écrit <code>true</code>, <code>false</code>, <code>misleading</code>
            ou <code>unverifiable</code> — ou son libellé français.
        </p>

        <p class="text-sm text-encre-2 dark:text-nuit-2 mb-4">
            Tout ce qui entre par là est créé <strong>en brouillon</strong>, jamais publié :
            un fichier ne relit rien, et publier engage la plateforme.
        </p>

        <label class="mb-4">
            <span class="etiquette">Fichier CSV (2 Mo maximum, 500 lignes)</span>
            <input type="file" class="champ" accept=".csv,text/csv" wire:model="fichierImport">
            @error('fichierImport') <span class="aide text-critique">{{ $message }}</span> @enderror
        </label>

        <x-slot:footer>
            <button type="button" class="btn-second" wire:click="fermerImport">Annuler</button>
            <button type="button" class="btn-primaire" wire:click="importer"
                    wire:loading.attr="disabled" wire:target="importer,fichierImport">
                Importer en brouillons
            </button>
        </x-slot:footer>
    </x-offcanvas-form>

    {{-- -------------------------------------------------------- suppression --}}
    <x-delete-modal variant="staff"
                    id="suppression-fact"
                    :open="$uuidASupprimer !== null"
                    :cible="$libelleASupprimer"
                    :cascades="['Le verdict cesse d\'être servi : les prochains signalements sur cette rumeur relanceront les moteurs payants.']"
                    confirm="supprimer"
                    cancel="annulerSuppression" />


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
  <script src="{{ asset('assets/js/pages/staff-faits-verifies.js') }}"></script>
@endpush

{{-- ----------------------------------------------------- pont vers Quill --}}
@push('scripts')
    {{-- Sans `defer` : Quill doit exister quand Livewire s'initialise, et le
         script de Livewire s'injecte juste après cette pile. --}}
    <script src="{{ asset('assets/js/vendor/quill.js') }}"></script>

    {{-- @push et non @script : Livewire évalue le contenu de @script comme une
         EXPRESSION Alpine, qui n'accepte un bloc d'instructions que s'il
         commence par `if`, `let` ou `const` — et le fait au prix d'un
         « root element » supplémentaire que la détection de Livewire n'arrive
         pas à analyser en mode debug. Une pile classique n'a ni l'un ni
         l'autre problème, et le pont vers l'état reste explicite. --}}
    <script>
        /* `livewire:initialized` et non `livewire:init` : au moment de `init`
           les composants ne sont pas encore enregistrés et `@this` vaut
           `undefined` — l'éditeur ne se montait jamais, sans autre symptôme
           qu'une erreur en console. */
        document.addEventListener('livewire:initialized', function () {
            var composant = @this;
            var hote = document.querySelector('[data-quill-hote]');

            if (!hote || typeof Quill === 'undefined') { return; }

            var editeur = new Quill(hote, {
                theme: 'snow',
                modules: {
                    toolbar: [
                        [{ header: [2, 3, false] }],
                        ['bold', 'italic', 'underline', 'strike'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['blockquote', 'code-block', 'link'],
                        ['clean'],
                    ],
                },
                placeholder: 'Rédigez l’analyse — pourquoi ce verdict, et sur quoi il s’appuie…',
            });

            /* Le chargement programmatique déclenche lui aussi « text-change » :
               sans ce verrou, ouvrir un fait le marquerait aussitôt comme
               modifié et renverrait au serveur ce qu'il vient d'envoyer. */
            var enChargement = false;

            editeur.on('text-change', function () {
                if (enChargement) { return; }

                var html = editeur.root.innerHTML;

                /* Troisième argument à false : rien ne part tout de suite. Une
                   requête par frappe rendrait l'éditeur inutilisable ; la
                   valeur accompagne le prochain « Enregistrer ». */
                composant.set('analyse', html === '<p><br></p>' ? '' : html, false);
            });

            /* L'hôte est en wire:ignore : Livewire n'y touche plus une fois
               monté. Sans cet événement, ouvrir un second fait afficherait
               l'analyse du premier — et l'enregistrer l'écraserait par elle. */
            composant.on('analyse-chargee', function (charge) {
                var detail = Array.isArray(charge) ? charge[0] : charge;

                enChargement = true;
                editeur.root.innerHTML = (detail && detail.html) ? detail.html : '';
                enChargement = false;
            });
        });
    </script>
@endpush
