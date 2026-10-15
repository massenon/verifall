@props([
    'variant' => 'staff',
    'id' => 'delete-modal',
    // Nom de l'élément visé : la confirmation doit être NOMINATIVE.
    // « Supprimer cet élément ? » ne dit pas à l'utilisateur ce qu'il détruit.
    'cible' => null,
    // Conséquences en cascade, comptées (MODAL_DELETE_POPUP_WARNING).
    'cascades' => [],
    'action' => null,
    /*
     | Pilotage serveur, pour les appelants Livewire : l'ouverture suit l'état
     | du composant (« quelle cible ai-je armée ? ») et non une classe posée en
     | JavaScript. Deux états pour la même question finissent par se
     | contredire, et sur une suppression, se tromper de cible est définitif.
     |
     | Non renseignés, ces attributs laissent le comportement JS d'origine
     | intact : la modale reste `hidden` et les boutons portent leurs
     | `data-delete-*`.
     */
    'open' => false,
    'confirm' => null,
    'cancel' => null,
])

@php $staff = $variant === 'staff'; @endphp

<div class="{{ $staff ? 'st-oc-backdrop' : 'modal-backdrop' }} {{ $open ? 'is-open' : '' }}"
     data-delete-backdrop
     @if ($cancel) wire:click="{{ $cancel }}" @endif
     aria-hidden="true"></div>

{{-- z-index en ligne : le build Tailwind du prototype est FIGÉ et ne contient
     aucune valeur arbitraire. `z-50` laissait la modale SOUS le fond du tiroir
     staff (`.st-oc-backdrop`, z-79) — la confirmation était grisée et
     inutilisable. --}}
<div class="{{ $staff ? 'st-card' : 'card' }} fixed left-4 right-4 top-1/2 -translate-y-1/2 mx-auto max-w-md p-6 {{ $open ? '' : 'hidden' }}"
     style="z-index:90"
     data-delete-modal
     id="{{ $id }}"
     role="alertdialog"
     aria-modal="true"
     aria-labelledby="{{ $id }}-title"
     aria-describedby="{{ $id }}-desc">

    <div class="flex items-start gap-3">
        <span class="material-symbols-rounded !text-[28px] text-brandred shrink-0" aria-hidden="true">warning</span>
        <div class="min-w-0">
            <h2 id="{{ $id }}-title" class="text-lg font-display font-bold {{ $staff ? 'text-[#0F172A]' : 'text-ink' }}">
                {{ __('composants.modale_supprimer_avant') }}&nbsp;<span data-delete-target>{{ $cible }}</span>&nbsp;{{ __('composants.modale_supprimer_apres') }}
            </h2>

            <p id="{{ $id }}-desc" class="mt-1 text-sm {{ $staff ? 'text-[#475569]' : 'text-slate2' }}">
                {{ __('composants.modale_cette_action_est_definitive') }}
            </p>

            @if (! empty($cascades))
                {{-- On énonce ce qui disparaît AVEC l'élément, chiffré : une
                     suppression en cascade non annoncée est une perte de
                     données du point de vue de l'utilisateur. --}}
                <ul class="mt-3 space-y-1 text-sm {{ $staff ? 'text-[#475569]' : 'text-slate2' }}">
                    @foreach ($cascades as $cascade)
                        <li class="flex items-start gap-2">
                            <span class="material-symbols-rounded !text-[16px] mt-0.5 text-brandred"
                                  aria-hidden="true">subdirectory_arrow_right</span>
                            <span>{{ $cascade }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>

    <div class="mt-6 flex flex-wrap justify-end gap-2">
        {{-- Annuler en premier et non destructif : l'action sûre doit être la
             plus facile à atteindre, y compris au clavier. --}}
        <button type="button"
                class="{{ $staff ? 'st-btn st-btn-ghost' : 'px-4 py-2 rounded-xl font-semibold' }}"
                data-delete-cancel
                @if ($cancel) wire:click="{{ $cancel }}" @endif>
            {{ __('composants.modale_annuler') }}
        </button>

        <button type="button"
                class="{{ $staff ? 'st-btn st-btn-danger' : 'px-4 py-2 rounded-xl bg-brandred text-white font-semibold' }}"
                data-delete-confirm
                @if ($confirm) wire:click="{{ $confirm }}" @endif
                @if ($action) data-delete-action="{{ $action }}" @endif>
            {{ __('composants.modale_supprimer_definitivement') }}
        </button>
    </div>
</div>
