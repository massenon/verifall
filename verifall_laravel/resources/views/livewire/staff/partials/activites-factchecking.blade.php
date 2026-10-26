{{--
  | Dernières Activités de Fact-Checking — Sujet · Statut · Assigné à · Action.
  |
  | La colonne « Assigné à » était décorative dans le prototype : rien ne
  | portait la prise en charge d'une analyse. Elle est réelle ici, et le geste
  | se fait DEPUIS CETTE LIGNE : ouvrir un autre écran pour assigner ferait
  | qu'on ne le fait pas.
  |
  | Le sélecteur n'apparaît qu'aux personnes qui détiennent `signalement.assign`.
  | Pour les autres, la colonne reste en lecture — voir qui traite quoi est
  | utile à tout le monde, pouvoir le changer ne l'est pas.
--}}
<div class="carte lg:col-span-2">
    <div class="carte-tete flex items-center justify-between gap-3">
        <h2 class="font-titre font-bold text-encre dark:text-white mb-0">Dernières activités de fact-checking</h2>
        {{-- Pointait vers la base de connaissances, faute de mieux : ce panneau
             liste des VÉRIFICATIONS, la base contient des faits versés. Deux
             objets différents, et le lien menait donc à côté. --}}
        <a href="{{ route('staff.verifications') }}" class="text-xs font-bold text-brand-700 dark:text-brand">Tout voir →</a>
    </div>

    <div class="carte-corps">
        @forelse ($activites as $activite)
            <div class="py-2.5 border-b border-trait dark:border-nuit-trait last:border-0">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0">
                        <p class="text-sm text-encre dark:text-white mb-0 truncate" title="{{ $activite->claim }}">
                            {{ $activite->apercu() }}
                        </p>
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">
                            {{ $activite->reference() }}
                            @if ($activite->theme) · {{ $activite->theme->libelle() }} @endif
                            · {{ $activite->created_at?->diffForHumans() }}
                        </p>
                    </div>
                    <span class="pastille {{ $activite->status->badge() }} shrink-0">
                        {{ $activite->status->libelle() }}
                    </span>
                </div>

                <div class="flex items-center justify-between gap-3 mt-2">
                    <span class="text-xs text-encre-3 dark:text-nuit-3 min-w-0 truncate">
                        @if ($activite->assignee)
                            Confié à <span class="font-bold">{{ $activite->assignee->name }}</span>
                            {{ $activite->assigned_at?->diffForHumans() }}
                        @else
                            Personne n'est encore sur ce dossier.
                        @endif
                    </span>

                    @if ($peutAssigner)
                        <form wire:submit.prevent="assigner('{{ $activite->uuid }}')"
                              class="flex items-center gap-2 shrink-0">
                            <select wire:model="destinataire.{{ $activite->uuid }}"
                                    class="champ text-xs" style="min-height: 34px; padding: 0 .5rem;"
                                    aria-label="Confier {{ $activite->reference() }} à">
                                <option value="">Confier à…</option>
                                @foreach ($assignables as $membre)
                                    <option value="{{ $membre->uuid }}"
                                            @selected($activite->assigned_to === $membre->id)>
                                        {{ $membre->name }}
                                    </option>
                                @endforeach
                            </select>
                            <button type="submit" class="text-xs font-bold text-brand-700 dark:text-brand"
                                    wire:loading.attr="disabled"
                                    wire:target="assigner('{{ $activite->uuid }}')">
                                Confier
                            </button>
                        </form>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-sm text-encre-3 dark:text-nuit-3 mb-0">
                Aucune vérification pour l'instant. Les analyses apparaîtront ici dès la première
                demande citoyenne.
            </p>
        @endforelse

        @if ($peutAssigner && $assignables->isEmpty())
            {{-- Un sélecteur vide est une impasse silencieuse : on dit pourquoi,
                 et ce qu'il faut faire. --}}
            <p class="text-xs text-critique mb-0 mt-2">
                Aucun membre ne détient le droit de rendre un verdict : personne ne peut être
                assigné. Attribuez le rôle Modérateur ou Administrateur depuis l'écran Rôles.
            </p>
        @endif
    </div>
</div>
