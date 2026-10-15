{{--
  | Mes contenus — la production d'un mentor, à plat.
  |
  | Le constructeur de cours répond à « comment ce cours est-il structuré ».
  | Celui-ci répond à « qu'ai-je produit, et qu'est-ce qui traîne en brouillon »,
  | à travers tous les cours. D'où la liste plate et le filtre par FORMAT :
  | trier par cours reproduirait le constructeur, en moins bien.
--}}
<div class="p-4 sm:p-6 lg:p-8">

    <div class="mb-5">
        <h1 class="text-xl font-display font-bold text-[#0F172A] mb-1">Mes contenus</h1>
        <p class="text-sm text-[#64748B] mb-0">
            Leçons, quiz et classes en direct de tous vos cours, dans une seule liste.
        </p>
    </div>

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <select wire:model.live="filtreFormat" class="st-input w-auto" aria-label="Filtrer par format">
            <option value="">Tous les formats</option>
            <option value="lecon">Leçons</option>
            <option value="quiz">Quiz</option>
            <option value="direct">Classes en direct</option>
        </select>

        <span class="text-sm text-[#64748B]">
            {{ count($contenus) }} élément(s) · {{ number_format($minutes, 0, ',', ' ') }} minutes au total
        </span>
    </section>

    @if ($contenus === [])
        <x-empty-state variant="staff" icon="library_books"
                       title="Aucun contenu"
                       description="Vos leçons, quiz et classes en direct apparaîtront ici." />
    @else
        <x-data-table variant="staff" :count="count($contenus)" :selectable="false">
            <x-slot:head>
                <th scope="col">Contenu</th>
                <th scope="col">Format</th>
                <th scope="col">Cours</th>
                <th scope="col">Durée</th>
                <th scope="col">Statut</th>
            </x-slot:head>

            @foreach ($contenus as $index => $contenu)
                <tr wire:key="cont-{{ $index }}">
                    <td class="font-semibold text-[#0F172A]">{{ $contenu['titre'] }}</td>
                    <td class="text-sm text-[#334155] whitespace-nowrap">{{ $contenu['format'] }}</td>
                    <td class="text-sm text-[#64748B]">{{ $contenu['cours'] }}</td>
                    <td class="text-sm text-[#64748B] whitespace-nowrap">
                        {{ $contenu['duree'] > 0 ? $contenu['duree'].' min' : '—' }}
                    </td>
                    <td class="whitespace-nowrap">
                        @if ($contenu['publie'])
                            <span class="st-badge st-badge-pub">En ligne</span>
                        @else
                            <span class="st-badge st-badge-draft">Brouillon</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        {{-- Dit plutôt que laissé deviner : afficher « En ligne » sur chaque
             leçon donnerait l'illusion d'une information qui n'en est pas une. --}}
        <p class="text-xs text-[#64748B] mt-3 mb-0">
            Une leçon et un quiz suivent la publication de leur cours : ils n'ont pas d'état
            propre. Le statut ne distingue vraiment que les classes en direct.
        </p>
    @endif
</div>
