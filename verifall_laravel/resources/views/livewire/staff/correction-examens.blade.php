{{--
  | « Correction Examens » (lot 7).
  |
  | La file répond à « qu'est-ce qui attend ? », la copie à « combien vaut cette
  | réponse-là ». On passe de l'une à l'autre des dizaines de fois par séance :
  | la copie s'ouvre donc EN PLACE, pas dans une autre page.
  |
  | Les champs de notes sont indexés par POSITION, jamais par identifiant :
  | `exam_answers` n'a pas de colonne `uuid`, et publier un `id` dans un
  | `wire:model` le rendrait lisible dans le DOM.
  |
  | DataTables (contrat : docs/DATATABLES_CONTRAT.md, migration Blade du
  | 12 octobre 2026). La file reste TOUJOURS dans le DOM : seule son enveloppe
  | est masquée (`hidden`) pendant qu'une copie est ouverte. La démonter via un
  | `@if` détruirait et recréerait la table DataTables à chaque aller-retour —
  | `staff-correction-examens.js` observe précisément cet attribut `hidden`
  | sur le parent direct de `[data-vdt]` pour recalculer ses largeurs.
--}}
@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.dataTables-2.3.8.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/buttons.dataTables-3.2.6.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.dateTime-1.6.3.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/responsive.dataTables-3.0.8.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/pages/datatables-staff.css') }}">
@endpush

<div>

@if ($copie !== null)
        {{-- ─────────────────────────────────────────── la copie ouverte --}}
        <section id="correction-copie-ouverte">
            <button type="button" wire:click="refermerLaCopie"
                    class="text-sm text-brand-700 dark:text-brand font-semibold underline mb-2">
                ← Revenir à la file
            </button>

            <header class="mb-4">
                <h1 class="text-2xl font-titre font-bold text-encre dark:text-white leading-tight mb-1">
                    Copie {{ $copie->reference }}
                </h1>
                <p class="text-encre-3 dark:text-nuit-3 text-sm mb-0">
                    {{ $copie->user?->name ?? 'Candidat supprimé' }} ·
                    {{ $copie->exam?->title ?? 'Épreuve archivée' }} ·
                    barème {{ $copie->max_points }} points · seuil {{ $copie->pass_score }} %
                </p>
            </header>

            <form wire:submit="rendreLaCopie" class="space-y-4">
                @foreach ($reponses as $reponse)
                    <article class="carte p-4">
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mb-1">
                            Question {{ $reponse->position }} · {{ $reponse->points_max }} point(s)
                        </p>
                        <p class="font-semibold text-encre dark:text-white mb-2">{{ $reponse->prompt }}</p>

                        <div class="rounded-lg bg-papier dark:bg-nuit p-3 mb-3">
                            @if ($reponse->given_text !== null && $reponse->given_text !== '')
                                <p class="text-sm text-encre-2 dark:text-nuit-2 mb-0">{{ $reponse->given_text }}</p>
                            @elseif (! empty($reponse->given_options))
                                <ul class="list-none pl-0 mb-0 space-y-1">
                                    @foreach ((array) $reponse->given_options as $choix)
                                        <li class="text-sm text-encre-2 dark:text-nuit-2">{{ $choix }}</li>
                                    @endforeach
                                </ul>
                            @else
                                {{-- Une non-réponse se dit : elle vaut zéro, mais le
                                     correcteur doit voir que rien n'a été rendu plutôt
                                     que de chercher un texte absent. --}}
                                <p class="text-sm text-critique mb-0">Aucune réponse rendue.</p>
                            @endif
                        </div>

                        <div class="grid sm:grid-cols-3 gap-3">
                            <div class="mb-0">
                                <input id="cx-pts-{{ $reponse->position }}" type="number" min="0"
                                       max="{{ $reponse->points_max }}"
                                       wire:model="points.{{ $reponse->position }}" placeholder=" ">
                                <label for="cx-pts-{{ $reponse->position }}">Points / {{ $reponse->points_max }}</label>
                                @error('points.'.$reponse->position)
                                    <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p>
                                @enderror
                            </div>

                            <div class="col-span-2 mb-0">
                                <input id="cx-com-{{ $reponse->position }}" type="text"
                                       wire:model="commentaires.{{ $reponse->position }}" placeholder=" ">
                                <label for="cx-com-{{ $reponse->position }}">Commentaire (facultatif)</label>
                            </div>
                        </div>
                    </article>
                @endforeach

                <div>
                    <label for="cx-appr" class="etiquette">Appréciation générale</label>
<textarea id="cx-appr" wire:model="appreciation" rows="3" class="champ !h-auto py-2.5"></textarea>
                </div>

                <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">
                    Un champ de points laissé vide n'est pas un zéro : c'est « pas encore noté ».
                    La copie ne peut pas être rendue tant qu'une réponse attend sa note.
                </p>

                <div class="flex flex-wrap gap-2 pt-2">
                    <button type="submit" class="btn-primaire">Rendre la copie</button>
                    <button type="button" wire:click="enregistrerLesNotes" class="btn-second">
                        Enregistrer sans rendre
                    </button>
                    <button type="button" wire:click="demanderLAbandon('{{ $copie->uuid }}')"
                            class="btn-fantome">
                        Sortir de la file
                    </button>
                </div>
            </form>
        </section>
    @endif

    {{--
      | La file reste TOUJOURS rendue — seule l'enveloppe ci-dessous est
      | masquée pendant qu'une copie est ouverte, pour que la table DataTables
      | sous-jacente ne soit jamais démontée (voir la note en tête de fichier).
    --}}
    <div @if($copie !== null) hidden @endif>
        <x-premium.entete titre="Correction des examens" :fil="['Correction des examens']">
        La file de correction, la plus ancienne copie d'abord. Une copie sortie de la file ne compte ni dans les réussites, ni dans les échecs.
    </x-premium.entete>

        <x-kpi-grid variant="staff" :cols="4">
            <x-kpi variant="staff" :value="number_format($indicateurs['en_attente'], 0, ',', ' ')"
                   label="En attente" trend="Copies à corriger" />

            <x-kpi variant="staff" :value="number_format($indicateurs['urgentes'], 0, ',', ' ')"
                   label="Urgentes" trend="Au-delà du délai raisonnable" />

            <x-kpi variant="staff" :value="number_format($indicateurs['corrigees_30j'], 0, ',', ' ')"
                   label="Corrigées (30 j)" trend="Copies rendues" />

            {{-- `null` tant qu'aucune copie n'a été corrigée : un délai moyen sur
                 zéro copie serait un chiffre inventé. --}}
            <x-kpi variant="staff" :value="$indicateurs['delai_moyen_jours'] !== null ? round($indicateurs['delai_moyen_jours'], 1).' j' : '—'"
                   label="Délai moyen" trend="Du rendu à la note" />
        </x-kpi-grid>

        <div wire:ignore
             data-vdt="correction-examens"
             data-vdt-source="{{ route('staff.exam-grading.data') }}"
             data-vdt-langue="{{ asset('assets/vendor/datatables/i18n/fr-FR-2.3.8.json') }}"
             data-vdt-vendor="{{ asset('assets/vendor/datatables/js') }}"
             data-vdt-evenement="{{ \App\Livewire\Staff\CorrectionExamens::EVENEMENT_TABLE_A_RECHARGER }}">

            <section class="mb-4 flex flex-wrap items-center gap-3">
                <div class="flex-1 mb-0">
                    <label for="cx-q" class="etiquette">Rechercher un candidat, une référence</label>
<input id="cx-q" type="search" data-vdt-recherche data-vdt-url="q" data-vdt-livewire="recherche" value="{{ $recherche }}" class="champ">
                </div>

                <select data-vdt-filtre="statut" data-vdt-url="statut" data-vdt-livewire="filtreStatut" class="champ w-auto" aria-label="Filtrer par statut">
                    <option value="">Statut : tous</option>
                    @foreach ($statuts as $unStatut)
                        <option value="{{ $unStatut->value }}" @selected($filtreStatut === $unStatut->value)>{{ $unStatut->libelle() }}</option>
                    @endforeach
                </select>

                <select data-vdt-filtre="correcteur" data-vdt-url="correcteur" data-vdt-livewire="filtreCorrecteur" class="champ w-auto" aria-label="Filtrer par correcteur">
                    <option value="">Correcteur : tous</option>
                    <option value="moi" @selected($filtreCorrecteur === 'moi')>Les miennes</option>
                    @foreach ($correcteurs as $correcteur)
                        <option value="{{ $correcteur->uuid }}" @selected($filtreCorrecteur === $correcteur->uuid)>{{ $correcteur->name }}</option>
                    @endforeach
                </select>

                <select data-vdt-filtre="examen" data-vdt-url="examen" class="champ w-auto" aria-label="Filtrer par épreuve">
                    <option value="">Épreuve : toutes</option>
                    @foreach ($examens as $uuid => $titre)
                        <option value="{{ $uuid }}">{{ Str::limit($titre, 40) }}</option>
                    @endforeach
                </select>

                <div class="mb-0">
                    <label class="etiquette">Rendue depuis le</label>
<input type="text" data-vdt-filtre="soumise_du" data-vdt-url="du" data-vdt-date class="champ">
                </div>
                <div class="mb-0">
                    <label class="etiquette">jusqu'au</label>
<input type="text" data-vdt-filtre="soumise_au" data-vdt-url="au" data-vdt-date class="champ">
                </div>

                <button type="button" data-vdt-reinitialiser class="btn-second">
                    Réinitialiser
                </button>
            </section>

            <div role="alert" hidden data-vdt-erreur class="rounded-lg border border-red-300 dark:border-red-900 bg-red-50 dark:bg-red-950/40 text-red-800 dark:text-red-300 text-sm px-4 py-3 mb-4">
                <p data-vdt-erreur-message class="mb-2">Impossible de charger la file.</p>
                <button type="button" data-vdt-reessayer class="btn-second">Réessayer</button>
            </div>

            <table id="table-correction-examens" style="width:100%" data-vdt-table>
                <thead>
                    <tr>
                        <th scope="col">Copie</th>
                        <th scope="col">Candidat</th>
                        <th scope="col">Épreuve</th>
                        <th scope="col">Rendue</th>
                        <th scope="col">Note</th>
                        <th scope="col">Correcteur</th>
                        <th scope="col">Statut</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    <x-delete-modal :open="$aAbandonner !== null"
                    title="Sortir cette copie de la file ?"
                    description="La copie ne sera pas notée. Elle ne comptera NI dans les réussites, NI dans les échecs — traiter une épreuve non rendue comme un échec ferait chuter un taux affiché sans que personne comprenne pourquoi."
                    confirm="abandonner"
                    cancel="annulerLAbandon" />
</div>

@push('scripts')
  <script src="{{ asset('assets/vendor/datatables/js/jquery-3.7.1.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables-2.3.8.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.buttons-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/buttons.html5-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/buttons.print-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/buttons.colVis-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.dateTime-1.6.3.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.responsive-3.0.8.min.js') }}"></script>
  <script src="{{ asset('assets/js/verifall-datatables.js') }}"></script>
  <script src="{{ asset('assets/js/pages/staff-correction-examens.js') }}"></script>
@endpush
