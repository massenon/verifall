{{--
  | Mes contenus — la production d'un mentor, à plat.
  |
  | Le constructeur de cours répond à « comment ce cours est-il structuré ».
  | Celui-ci répond à « qu'ai-je produit, et qu'est-ce qui traîne en brouillon »,
  | à travers tous les cours. D'où la liste plate et le filtre par FORMAT :
  | trier par cours reproduirait le constructeur, en moins bien.
--}}
<div>

    <x-premium.entete titre="Mes contenus">
        Leçons, quiz et classes en direct de tous vos cours, dans une seule liste.
    </x-premium.entete>

    <x-premium.barre-de-liste placeholder="Rechercher un contenu ou un cours…" :actifs="$filtresActifs">
        <div>
            <label for="mc-f-format" class="etiquette">Format</label>
            <select id="mc-f-format" wire:model.live="filtreFormat" class="champ">
                <option value="">Tous les formats</option>
                <option value="lecon">Leçons</option>
                <option value="quiz">Quiz</option>
                <option value="direct">Classes en direct</option>
            </select>
        </div>
        <div>
            <label for="mc-f-etat" class="etiquette">Statut</label>
            <select id="mc-f-etat" wire:model.live="filtreEtat" class="champ">
                <option value="">Tous</option>
                <option value="en_ligne">En ligne</option>
                <option value="brouillon">Brouillon</option>
            </select>
        </div>
    </x-premium.barre-de-liste>

    <p class="text-sm texte-2 mb-3">{{ number_format($minutes, 0, ',', ' ') }} minutes au total sur la sélection.</p>

    @if ($contenus->total() === 0)
        <x-empty-state variant="staff" icon="library"
                       title="Aucun contenu"
                       description="Vos leçons, quiz et classes en direct apparaîtront ici, ou la recherche est trop stricte." />
    @else
        <x-data-table variant="staff" :count="$contenus->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Contenu</th>
                <th scope="col">Format</th>
                <th scope="col">Cours</th>
                <th scope="col">Durée</th>
                <th scope="col">Statut</th>
            </x-slot:head>

            @foreach ($contenus as $index => $contenu)
                <tr wire:key="cont-{{ $index }}">
                    <td class="font-semibold text-encre dark:text-white">{{ $contenu['titre'] }}</td>
                    <td class="text-sm text-encre-2 dark:text-nuit-2 whitespace-nowrap">{{ $contenu['format'] }}</td>
                    <td class="text-sm text-encre-3 dark:text-nuit-3">{{ $contenu['cours'] }}</td>
                    <td class="text-sm text-encre-3 dark:text-nuit-3 whitespace-nowrap">
                        {{ $contenu['duree'] > 0 ? $contenu['duree'].' min' : '—' }}
                    </td>
                    <td class="whitespace-nowrap">
                        @if ($contenu['publie'])
                            <span class="pastille p-bon">En ligne</span>
                        @else
                            <span class="pastille p-neutre">Brouillon</span>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <x-premium.pied-de-liste :liste="$contenus" />

        {{-- Dit plutôt que laissé deviner : afficher « En ligne » sur chaque
             leçon donnerait l'illusion d'une information qui n'en est pas une. --}}
        <p class="text-xs text-encre-3 dark:text-nuit-3 mt-3 mb-0">
            Une leçon et un quiz suivent la publication de leur cours : ils n'ont pas d'état
            propre. Le statut ne distingue vraiment que les classes en direct.
        </p>
    @endif
</div>
