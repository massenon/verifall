{{--
  | Progression — l'outil de rattrapage.
  |
  | « Mes Étudiants » dit qui est là ; celui-ci dit qui décroche et où. Même
  | source, usage différent : les fondre donnerait un écran qui répond mal aux
  | deux questions.
--}}
<div class="p-4 sm:p-6 lg:p-8">

    <div class="mb-5 flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-xl font-display font-bold text-[#0F172A] mb-1">Progression</h1>
            <p class="text-sm text-[#64748B] mb-0">
                Qui avance, qui a décroché. Les moins avancés en tête — c'est à eux qu'on écrit.
            </p>
        </div>

        @if ($inscrits->total() > 0)
            <button type="button" wire:click="exporter" class="st-btn st-btn-secondary">
                Exporter la cohorte
            </button>
        @endif
    </div>

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="ffield flex-1 mb-0" style="min-width: 220px;">
            <input id="prog-q" type="search" wire:model.live.debounce.350ms="recherche" placeholder=" ">
            <label for="prog-q">Rechercher un nom, une adresse</label>
        </div>

        <select wire:model.live="filtreCours" class="st-input w-auto" aria-label="Filtrer par cours">
            <option value="">Tous mes cours</option>
            @foreach ($mesCours as $cours)
                <option value="{{ $cours->uuid }}">{{ $cours->title }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtreEtat" class="st-input w-auto" aria-label="Filtrer par état">
            <option value="">Tous les états</option>
            <option value="en-cours">En cours</option>
            <option value="termine">Terminé</option>
            <option value="decroche">Décroché</option>
        </select>

        @if ($recherche !== '' || $filtreCours !== '' || $filtreEtat !== '')
            <button type="button" wire:click="reinitialiser" class="text-sm text-[#059669] font-semibold underline">
                Réinitialiser
            </button>
        @endif
    </section>

    {{-- « Décroché » n'existe pas en base : il se dérive. Le dire à l'écran évite
         qu'on cherche la colonne, et rend le seuil discutable plutôt qu'occulte. --}}
    <p class="text-xs text-[#64748B] mb-5">
        « Décroché » désigne une inscription commencée, jamais terminée, et sans activité
        depuis plus d'un mois. Ce n'est pas un statut enregistré : il se recalcule à chaque
        affichage, et le seuil peut donc changer sans rien migrer.
    </p>

    @if ($inscrits->total() === 0)
        <x-empty-state variant="staff" icon="trending_up"
                       title="Aucun apprenant à suivre"
                       description="Personne ne correspond à ces filtres." />
    @else
        <x-data-table variant="staff" :count="$inscrits->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Apprenant</th>
                <th scope="col">Cours</th>
                <th scope="col">Progression</th>
                <th scope="col">Quiz</th>
                <th scope="col">Dernière activité</th>
                <th class="text-right">Action</th>
            </x-slot:head>

            @foreach ($inscrits as $inscription)
                @php($cle = $inscription->user_id.':'.$inscription->course_id)
                <tr wire:key="prog-{{ $inscription->uuid }}">
                    <td>
                        <p class="font-semibold text-[#0F172A] mb-0">{{ $inscription->user?->name ?? 'Compte supprimé' }}</p>
                        <p class="text-xs text-[#64748B] mb-0">{{ $inscription->user?->email }}</p>
                    </td>

                    <td class="text-sm text-[#334155]">{{ $inscription->course?->title ?? '—' }}</td>

                    <td style="min-width: 150px;">
                        <div class="flex items-center gap-2">
                            <div style="flex:1; height:6px; border-radius:3px; background:#E2E8F0;">
                                <div style="height:6px; border-radius:3px; background:#059669;
                                            width: {{ max(0, min(100, (int) $inscription->progress)) }}%;"></div>
                            </div>
                            <span class="text-xs text-[#64748B] shrink-0">{{ (int) $inscription->progress }} %</span>
                        </div>
                    </td>

                    <td class="text-sm whitespace-nowrap">
                        @if (isset($scores[$cle]))
                            <span class="font-semibold text-[#0F172A]">{{ $scores[$cle] }} %</span>
                        @else
                            {{-- « Pas encore passé » et non « 0 % » : un zéro se lit
                                 comme un échec, et ce n'en est pas un. --}}
                            <span class="text-[#64748B]">Pas encore passé</span>
                        @endif
                    </td>

                    <td class="whitespace-nowrap text-sm text-[#64748B]">
                        {{ $inscription->last_activity_at?->diffForHumans() ?? 'Jamais ouvert' }}
                        @if ($inscription->completed_at !== null)
                            <span class="block"><span class="st-badge st-badge-pub">Terminé</span></span>
                        @elseif ($inscription->progress > 0
                            && ($inscription->last_activity_at === null
                                || $inscription->last_activity_at->lt(now()->subMonth())))
                            <span class="block"><span class="st-badge st-badge-warn">Décroché</span></span>
                        @endif
                    </td>

                    <td class="text-right whitespace-nowrap">
                        @if ($inscription->completed_at !== null)
                            {{-- « Poursuivez votre cours » après le certificat est
                                 le genre de message qui fait se désabonner de tout. --}}
                            <span class="text-xs text-[#64748B]">Rien à relancer</span>
                        @else
                            <button type="button" wire:click="relancer('{{ $inscription->uuid }}')"
                                    wire:loading.attr="disabled" class="text-xs font-bold text-primary">
                                Relancer
                            </button>
                            @if ($inscription->last_reminder_at !== null)
                                <span class="block text-xs text-[#64748B]">
                                    relance {{ $inscription->last_reminder_at->diffForHumans() }}
                                </span>
                            @endif
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $inscrits->links() }}</div>

        <p class="text-xs text-[#64748B] mt-3 mb-0">
            L'export reprend les filtres en cours, dans la limite de
            {{ number_format($exportMaximum, 0, ',', ' ') }} lignes. Une relance part par
            notification et par courriel ; un même inscrit ne peut pas être relancé deux fois
            en moins de sept jours.
        </p>
    @endif
</div>
