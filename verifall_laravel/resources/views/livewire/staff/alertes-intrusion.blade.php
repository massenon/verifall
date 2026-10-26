<div>

    <x-premium.entete titre="Alertes Intrusion">
        Tentatives refusées et comportements anormaux. Les identités et les adresses IP n'apparaissent que sous forme d'empreintes : elles suffisent à corréler deux lignes sans exposer personne.
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="3">
        <x-kpi variant="staff" :value="number_format($this->indicateurs['bloquees'], 0, ',', ' ')"
               label="Tentatives bloquées (7 j)" trend="connexions et débits" />
        <x-kpi variant="staff" :value="number_format($this->indicateurs['critiques'], 0, ',', ' ')"
               label="Événements critiques" trend="à examiner en priorité" />
        <x-kpi variant="staff" :value="number_format($this->indicateurs['empreintes'], 0, ',', ' ')"
               label="Sources distinctes" trend="salve unique ou campagne répartie" />
    </x-kpi-grid>

    <x-premium.barre-de-liste placeholder="Rechercher un événement ou un compte…" :actifs="collect([$filtreEvenement, $filtreSeverite])->filter()->count()" reinitialiser="reinitialiserLaListe">
        <div>
            <label for="ai-f-type" class="etiquette">Type</label>
            <select id="ai-f-type" wire:model.live="filtreEvenement" class="champ">
                <option value="">Tous</option>
                @foreach ($evenements as $evenement)
                    <option value="{{ $evenement }}">{{ $this->libelle($evenement) }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label for="ai-f-niveau" class="etiquette">Criticité</label>
            <select id="ai-f-niveau" wire:model.live="filtreSeverite" class="champ">
                <option value="">Toutes</option>
                <option value="info">Info</option>
                <option value="warning">Sensible</option>
                <option value="critical">Critique</option>
            </select>
        </div>
    </x-premium.barre-de-liste>

    @if ($alertes->isEmpty())
        <x-empty-state variant="staff" icon="shield"
                       title="Aucune alerte"
                       description="Aucun événement de sécurité ne correspond à ces critères. C'est une bonne nouvelle." />
    @else
        <x-data-table variant="staff" :count="$alertes->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Alerte</th>
                <th scope="col">Criticité</th>
                <th scope="col">Source (empreinte)</th>
                <th scope="col">Compte</th>
                <th scope="col">Détection</th>
            </x-slot:head>

            @foreach ($alertes as $alerte)
                <tr data-row-el wire:key="alerte-{{ $alerte->id }}">
                    <td>{{ $this->libelle($alerte->event) }}</td>
                    <td>
                        <x-badge-statut variant="staff"
                                        :statut="$this->statutBadge($alerte->severity)"
                                        :label="ucfirst($alerte->severity)" />
                    </td>
                    {{-- Empreinte tronquée : corréler sans exposer. --}}
                    <td><code class="text-xs">{{ Str::limit($alerte->ip_hash ?? '—', 12, '…') }}</code></td>
                    <td>{{ $alerte->user?->name ?? '—' }}</td>
                    <td>{{ $alerte->created_at?->translatedFormat('j M Y H:i') }}</td>
                </tr>
            @endforeach
        </x-data-table>

        <x-card-list variant="staff">
            @foreach ($alertes as $alerte)
                <div class="carte p-4" wire:key="alerte-carte-{{ $alerte->id }}">
                    <div class="flex items-start justify-between gap-3">
                        <p class="font-semibold text-encre dark:text-white">{{ $this->libelle($alerte->event) }}</p>
                        <x-badge-statut variant="staff"
                                        :statut="$this->statutBadge($alerte->severity)"
                                        :label="ucfirst($alerte->severity)" />
                    </div>
                    <p class="mt-1 text-xs text-encre-3 dark:text-nuit-3">
                        <code>{{ Str::limit($alerte->ip_hash ?? '—', 12, '…') }}</code>
                        · {{ $alerte->created_at?->translatedFormat('j M Y H:i') }}
                    </p>
                </div>
            @endforeach
        </x-card-list>

        <x-premium.pied-de-liste :liste="$alertes" />
    @endif
</div>
