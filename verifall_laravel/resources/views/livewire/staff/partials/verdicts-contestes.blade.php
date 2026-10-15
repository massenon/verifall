{{--
  | Verdicts contestés — la file des contre-expertises ouvertes.
  |
  | Une contestation dit qu'on s'est peut-être trompés. C'est le retour le plus
  | précieux qu'une plateforme de fact-checking reçoive, et le plus facile à
  | enterrer : il arrive par un formulaire, il n'a pas d'échéance, et personne
  | ne se plaint si on l'oublie — sauf la personne qui avait raison.
  |
  | Les trois issues sont côte à côte et NOMMÉES. Un unique bouton « traiter »
  | laisserait choisir la formulation après coup ; ici on choisit d'abord ce
  | qu'on répond, et « Verdict corrigé » est aussi accessible que les autres.
--}}
<div class="st-card lg:col-span-2">
    <div class="st-card-h flex items-center justify-between gap-3">
        <h2 class="font-display font-bold text-[#0F172A] mb-0">Verdicts contestés</h2>
        @if ($contreExpertises->isNotEmpty())
            <span class="st-badge st-badge-warn">{{ $contreExpertises->count() }} en attente</span>
        @endif
    </div>

    <div class="st-card-b">
        @forelse ($contreExpertises as $demande)
            <div class="py-3 border-b border-line last:border-0" wire:key="ce-{{ $demande->uuid }}">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm text-[#0F172A] mb-0 truncate">{{ $demande->verification?->apercu(80) }}</p>
                        <p class="text-xs text-[#64748B] mb-0">
                            {{ $demande->verification?->reference() }}
                            · {{ $demande->demandeur?->name ?? 'Compte supprimé' }}
                            · {{ $demande->created_at?->diffForHumans() }}
                        </p>
                    </div>
                    <span class="{{ $demande->status->pastille() }} shrink-0">{{ $demande->status->libelle() }}</span>
                </div>

                {{-- Le motif EN ENTIER, pas tronqué : c'est l'argument, et le
                     tronquer obligerait à ouvrir un autre écran pour décider. --}}
                <blockquote class="rounded-xl border border-line p-3 mt-2 mb-2">
                    <p class="text-xs text-[#64748B] mb-0">{{ $demande->reason }}</p>
                </blockquote>

                @if ($peutArbitrer)
                    <div class="mb-2">
                        <label for="res-{{ $demande->uuid }}" class="text-xs text-[#64748B]">Réponse au demandeur (facultative)</label>
                        <input id="res-{{ $demande->uuid }}" type="text"
                               wire:model="resolution.{{ $demande->uuid }}"
                               class="st-input text-xs" style="min-height: 36px;"
                               placeholder="Ce que vous avez vérifié, en une phrase.">
                    </div>

                    <div class="flex flex-wrap items-center gap-2">
                        @foreach ([
                            \App\Enums\StatutContreExpertise::VerdictMaintenu,
                            \App\Enums\StatutContreExpertise::VerdictCorrige,
                            \App\Enums\StatutContreExpertise::Ecartee,
                        ] as $issue)
                            <button type="button"
                                    wire:click="trancherLaContestation('{{ $demande->uuid }}', '{{ $issue->value }}')"
                                    wire:loading.attr="disabled"
                                    class="text-xs font-bold text-primary">{{ $issue->libelle() }}</button>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <p class="text-sm text-[#64748B] mb-0">
                Aucun verdict contesté. C'est une bonne nouvelle — et ce panneau se remplira
                dès qu'un lecteur demandera une contre-expertise.
            </p>
        @endforelse
    </div>
</div>
