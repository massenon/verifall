{{--
  | 07 — Blog & Actualités : la performance éditoriale (§14-19, §56-57).
  |
  | Ordre imposé (§57) : performance globale → tendance → contenus top → détail.
  | La table vient APRÈS la lecture graphique.
  |
  | ## Ce qui est mesuré, et ce qui ne l'est pas — dit à l'écran
  |
  |   · VUES : `content_visits`, horodatées — une par personne, par contenu et
  |     par jour, robots et aperçus de partage exclus. Ventilables par période.
  |   · VISITEURS UNIQUES : empreintes distinctes. L'empreinte change chaque jour
  |     (c'est voulu, elle ne peut pas suivre quelqu'un) : sur plusieurs jours,
  |     le chiffre compte des « visiteurs-jours ».
  |   · CLICS : actualités seulement (départs vers la source citée), et CUMULÉS —
  |     la colonne n'est pas horodatée. Le blog n'a aucun lien suivi.
  |   · CTR : affiché UNIQUEMENT pour les actualités, en cumul. Jamais pour le
  |     blog (§18 : « ne jamais afficher un CTR si les données ne sont pas
  |     réellement calculées »).
  |   · PARTAGES, TEMPS MOYEN : non collectés. « Non mesuré ».
  |
  | ## Pourquoi pas une DataTable ici
  |
  | La table est BORNÉE (huit lignes) et le standard DataTables du projet
  | l'écarte du tableau de bord (« Le dashboard n'est pas une page DataTables »).
  | Elle garde la structure `st-table` des écrans d'administration ; la vraie
  | table triable et filtrable est l'écran de chaque type de contenu.
--}}
<div>
    @if ($enErreur)
        @include('livewire.staff.tableau-de-bord.erreur', ['bloc' => 'Blog & Actualités'])
    @else
        {{-- ── PERFORMANCE GLOBALE ── --}}
        <div class="tdb-grid tdb-grid-2">
            @foreach ($d['cartes'] as $cle => $carte)
                @php $e = $carte['donnees']; @endphp
                <article class="st-card tdb-card" wire:key="edito-{{ $cle }}">
                    <div class="tdb-card-h">
                        <div>
                            <h3 class="tdb-h3">{{ $carte['titre'] }}</h3>
                            <p class="tdb-meta">
                                {{ number_format($e['publies'], 0, ',', ' ') }} publié(s) sur {{ number_format($e['total'], 0, ',', ' ') }}
                                · {{ number_format($e['publies_periode'], 0, ',', ' ') }} sur la période
                            </p>
                        </div>
                        <a class="tdb-lien" href="{{ route($cle === 'blog' ? 'staff.posts' : 'staff.hub-news') }}">
                            Gérer <span class="material-symbols-rounded" aria-hidden="true">arrow_forward</span>
                        </a>
                    </div>

                    <div class="tdb-card-b">
                        <dl class="tdb-stats">
                            <div class="tdb-stat">
                                <dt>Vues</dt>
                                <dd>{{ number_format($e['vues'], 0, ',', ' ') }}</dd>
                                <dd class="tdb-stat-x">
                                    @include('livewire.staff.tableau-de-bord.variation', ['valeur' => $carte['variation_vues']])
                                    {{ $periodeCalculee->libelleComparaison() }}
                                </dd>
                            </div>
                            <div class="tdb-stat">
                                <dt>Visiteurs uniques</dt>
                                <dd>{{ number_format($e['visiteurs'], 0, ',', ' ') }}</dd>
                                <dd class="tdb-stat-x">Par jour, sans cookie</dd>
                            </div>
                            <div class="tdb-stat">
                                <dt>Clics</dt>
                                @if ($e['clics_cumul'] === null)
                                    <dd><span class="tdb-na" title="Aucun lien suivi dans les articles du blog.">Non mesuré</span></dd>
                                @else
                                    <dd>{{ number_format($e['clics_cumul'], 0, ',', ' ') }}</dd>
                                    <dd class="tdb-stat-x">Vers la source · cumul</dd>
                                @endif
                            </div>
                            <div class="tdb-stat">
                                <dt>CTR</dt>
                                @if ($carte['ctr_cumul'] === null)
                                    <dd><span class="tdb-na" title="{{ $e['clics_cumul'] === null ? 'Sans clics mesurés, aucun taux n’est calculé.' : 'Aucune vue enregistrée : pas de taux sans dénominateur.' }}">Non mesuré</span></dd>
                                @else
                                    <dd>{{ number_format($carte['ctr_cumul'], 1, ',', ' ') }} %</dd>
                                    <dd class="tdb-stat-x">Clics / vues · cumul</dd>
                                @endif
                            </div>
                            <div class="tdb-stat">
                                <dt>Partages</dt>
                                <dd><span class="tdb-na" title="Les partages ne sont pas comptés.">Non mesuré</span></dd>
                            </div>
                            <div class="tdb-stat">
                                <dt>Temps moyen</dt>
                                <dd><span class="tdb-na" title="La durée de lecture n’est pas collectée.">Non mesuré</span></dd>
                            </div>
                        </dl>

                        <div class="tdb-mt">
                            <p class="tdb-evt-e">Le plus consulté sur la période</p>
                            @if ($e['top_periode'] !== null)
                                <p class="tdb-evt-t" title="{{ $e['top_periode']['titre'] }}">
                                    <a href="{{ route($cle === 'blog' ? 'public.blog.show' : 'public.fact', $e['top_periode']['slug']) }}" target="_blank" rel="noopener">{{ $e['top_periode']['titre'] }}</a>
                                </p>
                                <p class="tdb-evt-m">{{ number_format($e['top_periode']['vues'], 0, ',', ' ') }} vue(s) sur la période</p>
                            @else
                                <p class="tdb-evt-m">Aucune visite sur la période.
                                    @if ($e['plus_vu_cumul'] !== null)
                                        Depuis l'origine : « {{ \Illuminate\Support\Str::limit($e['plus_vu_cumul']['titre'], 60) }} » ({{ number_format($e['plus_vu_cumul']['vues'], 0, ',', ' ') }} vues).
                                    @endif
                                </p>
                            @endif
                        </div>

                        @if (array_sum($e['serie_vues']) > 0 && count($e['serie_vues']) > 1)
                            @php
                                $pic = max(1, max($e['serie_vues']));
                                $n = count($e['serie_vues']);
                                $points = collect($e['serie_vues'])->map(fn (int $v, int $i): string => round($i * 100 / ($n - 1), 2).','.round(26 - $v * 24 / $pic, 2))->implode(' ');
                            @endphp
                            <svg class="tdb-kpi-spark" viewBox="0 0 100 28" preserveAspectRatio="none" aria-hidden="true" focusable="false" style="margin-top: .75rem;">
                                <polyline points="{{ $points }}" fill="none" stroke="#059669" stroke-width="1.6" stroke-linejoin="round" vector-effect="non-scaling-stroke" />
                            </svg>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>

        {{-- ── TENDANCE ── --}}
        @php
            $couleurs = ['blog' => '#059669', 'actualites' => '#2563EB'];
            $series = [];
            foreach ($d['cartes'] as $cle => $carte) {
                $series[] = ['nom' => 'Vues · '.$carte['titre'], 'couleur' => $couleurs[$cle], 'valeurs' => $carte['donnees']['serie_vues']];
            }
            $totalVues = array_sum(array_map(static fn (array $s): int => array_sum($s['valeurs']), $series));
        @endphp

        <article class="st-card tdb-card tdb-mt">
            <div class="tdb-card-h">
                <div>
                    <h3 class="tdb-h3">Vues sur la période</h3>
                    <p class="tdb-meta">
                        Les clics ne sont pas horodatés : aucune courbe « clics » ne peut être tracée honnêtement.
                        Leur cumul figure dans la carte Actualités.
                    </p>
                </div>
            </div>
            <div class="tdb-card-b">
                @if ($totalVues === 0)
                    <p class="tdb-vide">Aucune consultation enregistrée sur la période.</p>
                @else
                    @include('livewire.staff.tableau-de-bord.graphe-courbe', [
                        'series' => $series,
                        'etiquettes' => $d['etiquettes'],
                        'titre' => 'Vues des contenus éditoriaux',
                    ])
                @endif
            </div>
        </article>

        {{-- ── CONTENUS TOP ── --}}
        <article class="st-card tdb-card tdb-mt">
            <div class="tdb-card-h">
                <div>
                    <h3 class="tdb-h3">Contenus les plus performants</h3>
                    <p class="tdb-meta">
                        @if ($d['classement'] === 'vues')
                            Classés par vues sur la période.
                        @else
                            Aucune visite sur la période : voici les dernières publications — ce n'est pas un classement.
                        @endif
                    </p>
                </div>
            </div>

            @if ($d['lignes'] === [])
                <div class="tdb-card-b">
                    <p class="tdb-vide">Aucun contenu publié pour l'instant.</p>
                </div>
            @else
                <div class="tdb-table-wrap">
                    <table class="st-table tdb-table">
                        <thead>
                            <tr>
                                <th scope="col">Contenu</th>
                                <th scope="col" class="is-secondaire">Type</th>
                                <th scope="col" class="is-secondaire">Statut</th>
                                <th scope="col" class="is-num">Vues</th>
                                <th scope="col" class="is-num is-secondaire">Clics</th>
                                <th scope="col" class="is-num">CTR</th>
                                <th scope="col" class="is-num is-secondaire">Partages</th>
                                <th scope="col" class="is-secondaire">Publié le</th>
                                <th scope="col"><span class="sr-only">Actions</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($d['lignes'] as $ligne)
                                <tr wire:key="top-{{ $ligne['type'] }}-{{ $ligne['slug'] }}">
                                    <td class="tdb-titre-cell">
                                        <span style="display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $ligne['titre'] }}">
                                            {{ $ligne['titre'] }}
                                        </span>
                                    </td>
                                    <td class="is-secondaire">{{ $ligne['type'] === 'blog' ? 'Blog' : 'Actualité' }}</td>
                                    <td class="is-secondaire">
                                        <span class="st-badge {{ $ligne['publie'] ? 'st-badge-pub' : 'st-badge-draft' }}">{{ $ligne['publie'] ? 'Publié' : 'Brouillon' }}</span>
                                    </td>
                                    <td class="is-num">
                                        @if ($d['classement'] === 'vues')
                                            {{ number_format($ligne['vues_periode'], 0, ',', ' ') }}
                                        @else
                                            <span title="Vues cumulées depuis la publication">{{ number_format($ligne['vues_cumul'], 0, ',', ' ') }}*</span>
                                        @endif
                                    </td>
                                    <td class="is-num is-secondaire">
                                        @if ($ligne['clics_cumul'] === null)
                                            <span class="tdb-na" title="Aucun lien suivi dans les articles du blog.">—</span>
                                        @else
                                            {{ number_format($ligne['clics_cumul'], 0, ',', ' ') }}
                                        @endif
                                    </td>
                                    <td class="is-num">
                                        @if ($ligne['ctr_cumul'] === null)
                                            <span class="tdb-na" title="Pas de taux sans clics mesurés ni vues.">—</span>
                                        @else
                                            {{ number_format($ligne['ctr_cumul'], 1, ',', ' ') }} %
                                        @endif
                                    </td>
                                    <td class="is-num is-secondaire"><span class="tdb-na" title="Les partages ne sont pas comptés.">—</span></td>
                                    <td class="is-secondaire" style="white-space: nowrap;">
                                        {{ $ligne['publie_le'] ? \Illuminate\Support\Carbon::parse($ligne['publie_le'])->format('d/m/Y') : '—' }}
                                    </td>
                                    <td style="white-space: nowrap; text-align: right;">
                                        @if ($ligne['publie'])
                                            <a class="tdb-icnlien" href="{{ route($ligne['route_publique'], $ligne['slug']) }}" target="_blank" rel="noopener"
                                               title="Voir en ligne" aria-label="Voir « {{ $ligne['titre'] }} » en ligne">
                                                <span class="material-symbols-rounded" aria-hidden="true">open_in_new</span>
                                            </a>
                                        @endif
                                        <a class="tdb-icnlien" href="{{ route($ligne['route_gestion']) }}"
                                           title="Gérer" aria-label="Gérer les contenus de ce type">
                                            <span class="material-symbols-rounded" aria-hidden="true">edit</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="tdb-card-b" style="padding-top: .75rem;">
                    <p class="tdb-meta">
                        Clics et CTR : cumul depuis la publication (actualités seulement).
                        @if ($d['classement'] !== 'vues') * Vues cumulées. @endif
                        Colonnes secondaires masquées sur petit écran.
                    </p>
                </div>
            @endif
        </article>
    @endif
</div>
