{{--
  | « Rôles & permissions » (lot 3) — la matrice, rendue lisible.
  |
  | LECTURE SEULE, et c'est un choix argumenté, pas un manque : la matrice est
  | du code, elle se relit en revue et porte la raison de chaque arbitrage. Une
  | grille de cases à cocher perdrait tout cela et ferait diverger deux vérités,
  | celle du dépôt et celle de la base — au prochain semis, l'une écraserait
  | l'autre.
  |
  | Un accès se donne en attribuant un RÔLE, à l'écran « Utilisateurs ».
--}}
<div>

<x-premium.entete titre="Rôles &amp; permissions" :fil="['Rôles &amp; permissions']">
        Ce que chaque rôle recouvre exactement. Pour donner un accès à quelqu'un, attribuez-lui un rôle depuis <a href="{{ route('staff.users') }}" class="text-[#059669] underline">Utilisateurs</a>.
    </x-premium.entete>

    {{-- L'explication du caractère non modifiable, à l'écran et pas seulement
         dans le code : sans elle, l'absence de bouton « Modifier » passe pour
         une fonctionnalité oubliée. --}}
    <div class="st-card p-4 mb-6 border-l-4" style="border-left-color:#8A5A0B">
        <p class="text-sm text-[#334155] mb-0">
            <strong>Cette matrice ne se modifie pas depuis l'interface</strong>, et c'est délibéré.
            Elle vit dans le code, où chaque arbitrage porte sa raison — pourquoi un modérateur
            rédige un fait vérifié mais ne le publie pas, pourquoi un mentor alimente la banque
            de questions sans la publier. Une grille de cases perdrait ces raisons, et ferait
            diverger deux vérités : celle du dépôt et celle de la base.
            Changer ce qu'un rôle recouvre passe par une revue de code.
        </p>
    </div>

    <x-kpi-grid variant="staff" :cols="3">
        <x-kpi variant="staff" :value="count($roles)" label="Rôles" trend="Liste fermée" />
        <x-kpi variant="staff" :value="$totalPermissions" label="Permissions" trend="Réparties en modules" />
        <x-kpi variant="staff" :value="count($modules)" label="Modules" trend="Un domaine métier chacun" />
    </x-kpi-grid>

    <div class="space-y-3">
        @foreach ($roles as $ligne)
            @php $role = $ligne['role']; @endphp

            <article class="st-card overflow-hidden">
                <button type="button" wire:click="ouvrir('{{ $role->value }}')"
                        class="w-full text-left p-5 flex flex-wrap items-start gap-4"
                        aria-expanded="{{ $roleOuvert === $role->value ? 'true' : 'false' }}">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <h2 class="font-display font-bold text-lg text-[#0F172A] mb-0">{{ $role->label() }}</h2>
                            @if ($role->accedeAuBackOffice())
                                <span class="st-badge st-badge-info">Back-office</span>
                            @endif
                            @unless ($ligne['seme'])
                                {{-- Un rôle absent de la base n'est attribuable par
                                     personne : le dire plutôt que le présenter actif. --}}
                                <span class="st-badge st-badge-danger">Non semé en base</span>
                            @endunless
                        </div>
                        <p class="text-sm text-[#64748B] mb-0">{{ $role->description() }}</p>
                    </div>

                    <div class="text-right shrink-0">
                        <p class="font-display font-bold text-xl text-[#0F172A] mb-0" style="font-variant-numeric:tabular-nums">{{ count($ligne['permissions']) }}</p>
                        <p class="text-xs text-[#64748B] mb-0">permission{{ count($ligne['permissions']) > 1 ? 's' : '' }}</p>
                        <p class="text-xs text-[#64748B] mb-0">{{ $ligne['comptes'] }} compte{{ $ligne['comptes'] > 1 ? 's' : '' }}</p>
                    </div>

                    <span class="material-symbols-rounded !text-[22px] text-[#64748B] shrink-0" aria-hidden="true">{{ $roleOuvert === $role->value ? 'expand_less' : 'expand_more' }}</span>
                </button>

                @if ($roleOuvert === $role->value)
                    <div class="border-t border-[#E2E8F0] p-5">
                        @if ($ligne['permissions'] === [])
                            <p class="text-sm text-[#64748B] mb-0">
                                Aucune permission de back-office. C'est le rôle par défaut :
                                l'espace citoyen n'en demande aucune.
                            </p>
                        @else
                            @php
                                // Regroupées par module — cent permissions à plat ne se
                                // lisent pas, et c'est par module qu'on raisonne.
                                $parModule = [];
                                foreach ($ligne['permissions'] as $permission) {
                                    [$module, $action] = explode('.', $permission, 2);
                                    $parModule[$module][] = $action;
                                }
                                ksort($parModule);
                            @endphp

                            <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
                                @foreach ($parModule as $module => $actions)
                                    <div>
                                        <p class="text-xs font-bold uppercase tracking-wide text-[#64748B] mb-1.5">{{ str_replace('_', ' ', $module) }}</p>
                                        <div class="flex flex-wrap gap-1">
                                            @foreach ($actions as $action)
                                                <span class="st-badge st-badge-info">{{ $action }}</span>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </article>
        @endforeach
    </div>
</div>
