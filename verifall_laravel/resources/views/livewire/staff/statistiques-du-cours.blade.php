{{--
  | Statistiques d'un cours.
  |
  | Trois chiffres, et un seul sur lequel on peut agir : la progression
  | moyenne. Inscrits et certificats disent l'entrée et la sortie ; c'est ce qui
  | se passe entre les deux qui se corrige.
  |
  | La liste est triée par les MOINS avancés, parce que cet écran sert à
  | relancer — et qu'on relance ceux qui décrochent, pas ceux qui ont fini.
--}}
<div class="p-4 sm:p-6 lg:p-8">

    <div class="mb-5">
        <p class="text-xs text-[#64748B] mb-1">
            <a href="{{ route('staff.courses') }}" class="hover:text-primary">Cours</a>
            <span aria-hidden="true">/</span> Statistiques
        </p>
        <h1 class="text-xl font-display font-bold text-[#0F172A] mb-1">{{ $cours->title }}</h1>
        <p class="text-sm text-[#64748B] mb-0">
            {{ $cours->category }}@if ($cours->instructor) · {{ $cours->instructor->name }}@endif
            · {{ $cours->cost_units > 0 ? $cours->cost_units.' unité'.($cours->cost_units > 1 ? 's' : '') : 'Gratuit' }}
        </p>
    </div>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($chiffres['inscrits'], 0, ',', ' ')" label="Citoyens inscrits" />
        <x-kpi variant="staff" :value="number_format($chiffres['certificats'], 0, ',', ' ')" label="Certificats délivrés" />
        <x-kpi variant="staff" :value="$chiffres['progression_moyenne'].' %'" label="Progression moyenne" />
        <x-kpi variant="staff" :value="number_format($chiffres['jamais_commences'], 0, ',', ' ')" label="Jamais commencé" />
    </x-kpi-grid>

    {{-- La moyenne écarte ceux qui n'ont jamais ouvert la première leçon : ils
         n'ont pas abandonné le cours, ils ne l'ont pas commencé. Les compter
         tirerait la moyenne vers le bas sans rien apprendre du contenu. --}}
    <p class="text-xs text-[#64748B] mt-3 mb-5">
        {{-- L'accord suit le nombre : « les 1 inscrits restés » se lisait sur
             chaque cours n'ayant qu'un seul inscrit à 0 %. --}}
        La progression moyenne ne compte que ceux qui ont commencé.
        {{ trans_choice(
            '{0}Aucun inscrit n’est resté à 0 %.|{1}Un inscrit est resté à 0 %, compté à part.|[2,*]:count inscrits sont restés à 0 %, comptés à part.',
            $chiffres['jamais_commences'],
        ) }}
        Si ce nombre est élevé, le problème est à l'inscription, pas dans le cours.
    </p>

    @if ($inscrits->total() === 0)
        <x-empty-state variant="staff" icon="group"
                       title="Aucun inscrit"
                       description="Les citoyens inscrits à ce cours apparaîtront ici, les moins avancés en tête." />
    @else
        <x-data-table variant="staff" :count="$inscrits->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Citoyen</th>
                <th scope="col">Progression</th>
                <th scope="col">Date d'enrôlement</th>
                <th class="text-right">Action</th>
            </x-slot:head>

            @foreach ($inscrits as $inscription)
                <tr wire:key="insc-{{ $inscription->uuid }}">
                    <td>
                        <p class="font-semibold text-[#0F172A] mb-0">{{ $inscription->user?->name ?? 'Compte supprimé' }}</p>
                        <p class="text-xs text-[#64748B] mb-0">{{ $inscription->user?->email }}</p>
                    </td>
                    <td style="min-width: 160px;">
                        <div class="flex items-center gap-2">
                            {{-- Largeur en style EN LIGNE : une largeur est une
                                 donnée, pas une classe. `w-[40%]` n'existerait
                                 pas dans le build Tailwind figé. --}}
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
                        {{ $inscription->created_at?->translatedFormat('j M Y') }}
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @if ($inscription->completed_at !== null)
                            {{-- Pas de relance pour qui a terminé : « poursuivez
                                 votre cours » après le certificat est le genre de
                                 message qui fait se désabonner de tout. --}}
                            <span class="text-xs text-[#64748B]">Rien à relancer</span>
                        @else
                            <button type="button" wire:click="relancer('{{ $inscription->uuid }}')"
                                    wire:loading.attr="disabled"
                                    class="text-xs font-bold text-primary">
                                Relancer
                            </button>
                            @if ($inscription->last_reminder_at !== null)
                                {{-- « relancé » imposerait un accord, donc un
                                     genre qu'on ne connaît pas. Le substantif
                                     l'évite. --}}
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
            Une relance part par notification et par courriel. Un même inscrit ne peut pas
            être relancé deux fois en moins de {{ $reposJours }} jours — c'est l'application qui
            tient le délai, pas la mémoire de qui clique.
        </p>
    @endif
</div>
