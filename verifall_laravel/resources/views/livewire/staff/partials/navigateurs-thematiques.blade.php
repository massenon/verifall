{{--
  | Deux panneaux courts, côte à côte : Navigateurs et Top Thématiques.
  |
  | Ni l'un ni l'autre ne remplit une carte entière, et deux cartes à moitié
  | vides valent moins qu'une carte pleine.
--}}
<div class="carte">
    <div class="carte-corps">

        {{-- ── NAVIGATEURS ───────────────────────────────────────────────────
             Alimenté par un compteur qui ne retient QUE la famille et la date :
             pas d'adresse IP, pas d'identifiant, pas de cookie, aucun tiers.
             Il ne peut pas servir à suivre quelqu'un, par construction. --}}
        <div class="flex items-center justify-between gap-3 mb-2">
            <h2 class="font-titre font-bold text-encre dark:text-white mb-0">Navigateurs</h2>
            <span class="text-xs text-encre-3 dark:text-nuit-3">14 derniers jours</span>
        </div>

        @if ($navigateurs['total'] === 0)
            <p class="text-sm text-encre-3 dark:text-nuit-3 mb-4">
                Aucune visite enregistrée pour l'instant. Le comptage démarre à la première
                page publique ouverte.
            </p>
        @else
            <div class="mb-4">
                @foreach ($navigateurs['familles'] as $famille)
                    <div class="py-1.5 border-b border-trait dark:border-nuit-trait last:border-0">
                        <div class="flex items-baseline justify-between gap-3">
                            <span class="text-sm text-encre dark:text-white">{{ $famille['nom'] }}</span>
                            <span class="text-sm font-titre font-bold text-encre dark:text-white">{{ $famille['part'] }} %</span>
                        </div>
                        {{-- Largeur en style EN LIGNE : une largeur est une
                             donnée. `w-[68%]` n'existe pas dans le build figé
                             et ne produirait aucune barre. --}}
                        <div style="height: 4px; border-radius: 2px; background: #E2E8F0; margin-top: 4px;">
                            <div style="height: 4px; border-radius: 2px; background: #059669;
                                        width: {{ $famille['part'] }}%;"></div>
                        </div>
                    </div>
                @endforeach
            </div>

            <p class="text-xs text-encre-3 dark:text-nuit-3 mb-4">
                {{ number_format($navigateurs['total'], 0, ',', ' ') }} visite(s).
                Seule la famille du navigateur est conservée — aucune donnée personnelle.
            </p>
        @endif

        {{-- ── TOP THÉMATIQUES ───────────────────────────────────────────────
             Classement des vérifications par thème. La liste est close (six
             thèmes) et classée par une heuristique lexicale déterministe, pas
             par un modèle : « pourquoi ce dossier est-il en Arnaque ? » a donc
             une réponse vérifiable. --}}
        <div class="flex items-center justify-between gap-3 mb-2">
            <h2 class="font-titre font-bold text-encre dark:text-white mb-0">Top thématiques</h2>
            <span class="text-xs text-encre-3 dark:text-nuit-3">Depuis l'origine</span>
        </div>

        @forelse ($thematiques as $ligne)
            <div class="flex items-baseline justify-between gap-3 py-1.5 border-b border-trait dark:border-nuit-trait last:border-0">
                <span class="text-sm text-encre dark:text-white">{{ $ligne['theme']->libelle() }}</span>
                <span class="text-sm font-titre font-bold text-encre dark:text-white">
                    {{ number_format($ligne['nombre'], 0, ',', ' ') }}
                </span>
            </div>
        @empty
            <p class="text-sm text-encre-3 dark:text-nuit-3 mb-0">
                Aucune vérification enregistrée. Le classement apparaîtra à la première analyse.
            </p>
        @endforelse
    </div>
</div>
