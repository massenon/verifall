{{--
  | « Statistiques Globales » (lot 7).
  |
  | Lecture seule : `exam_stat => ['view']`, un seul verbe, et l'écran n'en
  | invente pas. Un tableau de bord qui modifie ce qu'il mesure finit par
  | mesurer ses propres modifications.
  |
  | Partout où la mesure n'a pas de sens, on affiche un TIRET et non zéro.
  | « 0 % de réussite » sur une plateforme qui n'a corrigé personne est un
  | chiffre inventé — et c'est celui qu'on retient et qu'on cite.
--}}
<div>

<x-premium.entete titre="Statistiques des examens" :fil="['Statistiques des examens']">
        Un examen par ligne, ce qu'il produit. Les moyennes sont calculées à la lecture, jamais stockées : une colonne figée aurait dérivé au premier recalcul oublié.
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($examens['actifs'], 0, ',', ' ')"
               label="Examens publiés" trend="Disponibles aux candidats" />

        {{-- « Certificats issus d'examens » de stats-globales.html, le dernier
             chiffre qui manquait. Compté sur les COPIES réussies et non sur la
             table des certificats : celle-ci mêle les diplômes de cours et ceux
             d'examen, et les additionner donnerait un chiffre qui ne veut rien
             dire sur une page consacrée aux épreuves. --}}
        <x-kpi variant="staff" :value="number_format($examens['certificats'], 0, ',', ' ')"
               label="Certificats d'examen" trend="Copies réussies" />

        <x-kpi variant="staff" :value="number_format($examens['candidats_30j'], 0, ',', ' ')"
               label="Candidats (30 j)" trend="Copies rendues" />

        <x-kpi variant="staff" :value="$examens['taux_reussite'] !== null ? $examens['taux_reussite'].' %' : '—'"
               label="Taux de réussite" trend="Sur les copies corrigées" />

        <x-kpi variant="staff" :value="number_format($examens['abandons'], 0, ',', ' ')"
               label="Abandons" trend="Ni réussite, ni échec" />
    </x-kpi-grid>

    <x-kpi-grid variant="staff" :cols="3">
        <x-kpi variant="staff" :value="number_format($correction['en_attente'], 0, ',', ' ')"
               label="File de correction" trend="Copies en attente" />

        <x-kpi variant="staff" :value="$correction['delai_moyen_jours'] !== null ? round($correction['delai_moyen_jours'], 1).' j' : '—'"
               label="Délai moyen" trend="Du rendu à la note" />

        <x-kpi variant="staff" :value="number_format($correction['correcteurs_actifs'], 0, ',', ' ')"
               label="Correcteurs actifs" trend="Ayant corrigé récemment" />
    </x-kpi-grid>

    <section class="mb-4">
        <div class="ffield mb-0">
            <input id="st-q" type="search" wire:model.live.debounce.350ms="recherche" placeholder=" ">
            <label for="st-q">Rechercher une épreuve</label>
        </div>
    </section>

    @if ($lignes->isEmpty())
        <x-empty-state variant="staff" icon="monitoring"
                       title="Aucune donnée"
                       description="Les statistiques apparaissent dès qu'une épreuve a été passée. Un tableau vide est un état correct, pas une panne." />
    @else
        <x-data-table variant="staff" :count="$lignes->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Épreuve</th>
                <th scope="col">Statut</th>
                <th scope="col">Sessions</th>
                <th scope="col">Candidats</th>
                <th scope="col">Seuil</th>
                <th scope="col">Note moyenne</th>
                <th scope="col">Réussite</th>
            </x-slot:head>

            @foreach ($lignes as $examen)
                <tr>
                    <td>
                        <p class="font-semibold text-[#0F172A] mb-0">{{ $examen->title }}</p>
                        <p class="text-xs text-[#64748B] mb-0">
                            {{ $examen->course?->title ?? 'Transverse' }}
                        </p>
                    </td>
                    <td>
                        <span class="st-badge {{ $examen->status?->value === 'published' ? 'st-badge-pub' : 'st-badge-draft' }}">
                            {{ $examen->status?->libelle() ?? $examen->status }}
                        </span>
                    </td>
                    <td class="whitespace-nowrap">{{ $examen->sessions_count ?? 0 }}</td>

                    {{-- Le nombre de candidats à côté de la moyenne, et pas
                         ailleurs : une note moyenne de 82 % sur trois copies et
                         sur trois cents ne se lisent pas de la même façon. --}}
                    <td class="whitespace-nowrap">{{ $examen->corrigees_count ?? 0 }}</td>

                    <td class="whitespace-nowrap text-sm text-[#64748B]">{{ $examen->pass_score }} %</td>
                    <td class="whitespace-nowrap">
                        @if ($examen->average_score === null)
                            {{-- Aucune copie corrigée : le tiret est l'information. --}}
                            <span class="text-[#64748B]">—</span>
                        @else
                            <span class="{{ $examen->average_score >= $examen->pass_score ? 'text-[#059669]' : 'text-[#DC2626]' }} font-semibold">
                                {{ round((float) $examen->average_score) }} %
                            </span>
                        @endif
                    </td>

                    <td class="whitespace-nowrap">
                        @if (($examen->corrigees_count ?? 0) === 0)
                            {{-- Pas de copie corrigée, donc pas de taux. « 0 % »
                                 se lirait comme un échec de l'épreuve, et ce n'en
                                 est pas un. Le tiret est l'information. --}}
                            <span class="text-[#64748B]">—</span>
                        @else
                            @php($taux = (int) round(($examen->reussies_count ?? 0) / $examen->corrigees_count * 100))
                            <span class="{{ $taux >= 50 ? 'text-[#059669]' : 'text-[#DC2626]' }} font-semibold">{{ $taux }} %</span>
                            <span class="block text-xs text-[#64748B]">
                                {{ $examen->reussies_count ?? 0 }} sur {{ $examen->corrigees_count }}
                            </span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $lignes->links() }}</div>
    @endif

    <p class="text-xs text-[#64748B] mt-4 mb-0">
        Un tiret signifie « pas encore mesurable », jamais « zéro ». Les deux se lisent différemment
        et ne se décident pas de la même façon.
    </p>
</div>
