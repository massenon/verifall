@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/pages/staff-tableau-de-bord.css') }}">
@endpush
{{--
  | 10 — Activité récente (§22-23) : une frise compacte, huit événements.
  |
  | Chaque source n'entre dans la frise que si l'écran qui la détaille est
  | permis à la personne connectée : un mentor n'y voit pas les affirmations
  | soumises, ni les inscriptions.
  |
  | Les liens mènent à l'ÉCRAN du domaine, pas à la fiche : les écrans staff ne
  | portent pas de route de détail par enregistrement, et un lien vers une
  | fiche inexistante serait pire qu'aucun lien.
--}}
<div>
    @if ($enErreur)
        @include('livewire.staff.tableau-de-bord.erreur', ['bloc' => 'Activité récente'])
    @else
        <article class="carte tdb-card">
            <div class="tdb-card-h">
                <div>
                    <h3 class="tdb-h3">Derniers événements</h3>
                    <p class="tdb-meta">Tous domaines confondus, du plus récent au plus ancien</p>
                </div>
            </div>
            <div class="tdb-card-b">
                @if ($d['evenements'] === [])
                    <p class="tdb-vide">Aucune activité pour l'instant. Les inscriptions, signalements et publications apparaîtront ici.</p>
                @else
                    <ol class="tdb-frise">
                        @foreach ($d['evenements'] as $evenement)
                            <li class="tdb-evt" wire:key="evt-{{ $loop->index }}">
                                <span class="tdb-evt-ico {{ 'is-'.$evenement['ton'] }}" aria-hidden="true">
                                    <x-lucide :name="$evenement['icone']" class="w-4 h-4" />
                                </span>
                                <div>
                                    <p class="tdb-evt-e">{{ $evenement['evenement'] }}</p>
                                    <p class="tdb-evt-t" title="{{ $evenement['titre'] }}">
                                        <a href="{{ route($evenement['route']) }}">{{ $evenement['titre'] }}</a>
                                    </p>
                                    <p class="tdb-evt-m">
                                        @if ($evenement['acteur']) <span style="font-weight: 700;">{{ $evenement['acteur'] }}</span> · @endif
                                        @if ($evenement['detail']) {{ $evenement['detail'] }} · @endif
                                        <time datetime="{{ $evenement['quand']->toIso8601String() }}" title="{{ $evenement['quand']->format('d/m/Y H:i') }}">{{ $evenement['quand']->diffForHumans() }}</time>
                                    </p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </div>
        </article>
    @endif
</div>
