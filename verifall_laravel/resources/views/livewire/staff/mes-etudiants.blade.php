{{--
  | Mes Étudiants — le trombinoscope de la cohorte d'un mentor.
  |
  | Une ligne par INSCRIPTION : quelqu'un inscrit à trois cours du même mentor
  | apparaît trois fois. La question posée ici est « où en est cette personne
  | dans ce cours », et fusionner les lignes obligerait à choisir un avancement
  | parmi trois.
--}}
<div>

    <x-premium.entete titre="Mes Étudiants">
        @if ($toutVoir) Tous les inscrits de la plateforme, tous formateurs confondus. @else Les personnes inscrites à vos cours. Les moins avancées en tête. @endif
    </x-premium.entete>

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="ffield flex-1 mb-0" style="min-width: 220px;">
            <input id="etu-q" type="search" wire:model.live.debounce.350ms="recherche" placeholder=" ">
            <label for="etu-q">Rechercher un nom, une adresse</label>
        </div>

        <select wire:model.live="filtreCours" class="st-input w-auto" aria-label="Filtrer par cours">
            <option value="">Tous mes cours</option>
            @foreach ($mesCours as $cours)
                <option value="{{ $cours->uuid }}">{{ $cours->title }}</option>
            @endforeach
        </select>

        @if ($recherche !== '' || $filtreCours !== '')
            <button type="button" wire:click="reinitialiser" class="text-sm text-[#059669] font-semibold underline">
                Réinitialiser
            </button>
        @endif
    </section>

    @if ($mesCours->isEmpty())
        {{-- Distinct de « aucun inscrit » : un mentor sans cours n'a pas perdu
             ses étudiants, il n'a pas encore de cours à leur proposer. --}}
        <x-empty-state variant="staff" icon="school"
                       title="Aucun cours ne vous est attribué"
                       description="Vos étudiants apparaîtront ici dès qu'un cours vous sera confié comme formateur." />
    @elseif ($inscrits->total() === 0)
        <x-empty-state variant="staff" icon="group"
                       title="Personne ne correspond"
                       description="Aucune inscription ne répond à cette recherche." />
    @else
        <x-data-table variant="staff" :count="$inscrits->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Étudiant</th>
                <th scope="col">Cours suivi</th>
                <th scope="col">Progression</th>
                <th scope="col">Dernier accès</th>
            </x-slot:head>

            @foreach ($inscrits as $inscription)
                <tr wire:key="etu-{{ $inscription->uuid }}">
                    <td>
                        <p class="font-semibold text-[#0F172A] mb-0">{{ $inscription->user?->name ?? 'Compte supprimé' }}</p>
                        <p class="text-xs text-[#64748B] mb-0">{{ $inscription->user?->email }}</p>
                    </td>

                    <td class="text-sm text-[#334155]">{{ $inscription->course?->title ?? '—' }}</td>

                    <td style="min-width: 160px;">
                        <div class="flex items-center gap-2">
                            {{-- Largeur en style EN LIGNE : c'est une donnée, pas
                                 une classe. `w-[40%]` n'existe pas dans le build
                                 Tailwind figé. --}}
                            <div style="flex:1; height:6px; border-radius:3px; background:#E2E8F0;">
                                <div style="height:6px; border-radius:3px; background:#059669;
                                            width: {{ max(0, min(100, (int) $inscription->progress)) }}%;"></div>
                            </div>
                            <span class="text-xs text-[#64748B] shrink-0">{{ (int) $inscription->progress }} %</span>
                        </div>
                        @if ($inscription->completed_at !== null)
                            <span class="st-badge st-badge-pub mt-1">Terminé</span>
                        @endif
                    </td>

                    <td class="whitespace-nowrap text-sm text-[#64748B]">
                        {{-- La date relative plutôt que le format long : sur cet
                             écran, « il y a 2 mois » se lit d'un coup d'œil là où
                             « 3 juil. 2026 » demande un calcul. --}}
                        {{ $inscription->last_activity_at?->diffForHumans() ?? 'Jamais ouvert' }}
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $inscrits->links() }}</div>
    @endif
</div>
