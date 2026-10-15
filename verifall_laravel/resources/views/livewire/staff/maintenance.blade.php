{{--
  | Mode maintenance.
  |
  | Deux gestes irréversibles à leur manière : fermer coupe le service à tout le
  | monde, rouvrir envoie un courriel à chaque personne qui attendait. Les deux
  | passent par une confirmation qui annonce ce qui va se produire, avec des
  | chiffres.
--}}
<div class="p-4 sm:p-6 lg:p-8">

    <div class="mb-5">
        <h1 class="text-xl font-display font-bold text-[#0F172A] mb-1">Mode maintenance</h1>
        <p class="text-sm text-[#64748B] mb-0">
            Ferme le site au public. Le personnel connecté garde son accès — sans quoi
            personne ne pourrait rouvrir depuis ici.
        </p>
    </div>

    @if ($actif)
        <div class="st-card p-5 mb-5" style="border-left: 4px solid #DC2626;">
            <p class="text-sm font-bold text-[#0F172A] mb-1">Le site est actuellement FERMÉ au public.</p>
            <p class="text-sm text-[#64748B] mb-0">
                Depuis {{ $debut?->diffForHumans() ?? 'quelques instants' }}.
                @if ($finReelle && ! $enRetard)
                    Réouverture annoncée pour {{ $finReelle->translatedFormat('j M à H:i') }}.
                @elseif ($enRetard)
                    {{-- L'écran le dit à l'équipe aussi : c'est le moment où
                         quelqu'un doit décider de rouvrir ou de repousser. --}}
                    <strong class="text-[#DC2626]">L’heure annoncée est dépassée</strong> — la page
                    publique affiche « l’opération dure plus longtemps que prévu ».
                @else
                    Aucune heure de fin n’a été annoncée.
                @endif
            </p>
        </div>
    @endif

    <x-kpi-grid variant="staff" :cols="3">
        <x-kpi variant="staff" :value="$actif ? 'Fermé' : 'Ouvert'" label="État du service" />
        {{-- Le chiffre qui pèse sur la décision de rouvrir : c'est le nombre de
             courriels qui partiront. --}}
        <x-kpi variant="staff" :value="number_format($enAttente, 0, ',', ' ')"
               label="Personnes à prévenir" />
        <x-kpi variant="staff" :value="number_format($dejaPrevenus, 0, ',', ' ')"
               label="Déjà prévenues" />
    </x-kpi-grid>

    <div class="st-card p-5 max-w-2xl mt-5">
        @if ($actif)
            <h2 class="text-lg font-bold text-[#0F172A] mb-2">Rouvrir le service</h2>
            <p class="text-sm text-[#64748B] mb-5">
                Le site redevient accessible immédiatement, et les
                {{ $enAttente }} personne(s) qui ont demandé à être prévenues reçoivent un
                courriel — un seul.
            </p>

            <button type="button" wire:click="demander('fermer')" class="st-btn st-btn-primary">
                Rouvrir le site
            </button>
        @else
            <h2 class="text-lg font-bold text-[#0F172A] mb-2">Fermer le service</h2>

            <form wire:submit="demander('ouvrir')">
                <div class="ffield mb-1">
                    <input id="mt-fin" type="datetime-local" wire:model="finAnnoncee">
                    <label for="mt-fin">Heure de fin annoncée</label>
                </div>
                @error('finAnnoncee') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror

                {{-- Dit ici plutôt que découvert : l'heure n'éteint RIEN. Une
                     maintenance qui se rouvrirait au chronomètre rouvrirait un
                     site à moitié migré si l'opération a pris du retard. --}}
                <p class="text-xs text-[#64748B] mt-1 mb-4">
                    Facultative, et c’est une <strong>promesse, pas une minuterie</strong> : elle
                    n’ouvre rien toute seule. Elle sert au décompte affiché au public, et permet
                    à la page de dire « nous avons du retard » plutôt que d’afficher un compteur
                    figé à zéro. Sans heure, la page ne promet rien.
                </p>

                <div class="ffield mb-1">
                    <textarea id="mt-message" wire:model="message" rows="3" placeholder=" "></textarea>
                    <label for="mt-message">Message affiché au public</label>
                </div>
                @error('message') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                <p class="text-xs text-[#64748B] mt-1 mb-5">
                    Laissé vide, un texte neutre est affiché. Dites ce qui se passe, pas
                    « nous améliorons votre expérience ».
                </p>

                <button type="submit" class="st-btn st-btn-primary">Fermer le site…</button>
            </form>
        @endif

        <p class="text-xs text-[#64748B] mt-5 mb-0">
            <a href="{{ route('maintenance') }}" target="_blank" rel="noopener"
               class="font-bold text-primary">Voir la page publique</a> — elle reste joignable
            hors maintenance, pour qu’on puisse la relire avant de fermer plutôt que de la
            découvrir en la montrant.
        </p>
    </div>

    {{-- ═══════════════════════════════════════════════ les confirmations --}}
    @if ($confirmation)
        <div class="st-oc-backdrop is-open" wire:click="annuler" aria-hidden="true"></div>

        {{-- `z-index` EN LIGNE : le build Tailwind est figé et ne contient aucune
             valeur arbitraire ; `z-50` laisserait la modale sous le fond du
             tiroir staff (z-79). Même raison que dans `<x-delete-modal>`. --}}
        <div class="st-card fixed left-4 right-4 top-1/2 -translate-y-1/2 mx-auto max-w-md p-6"
             style="z-index:90" role="alertdialog" aria-modal="true" aria-labelledby="mt-titre">

            @if ($confirmation === 'ouvrir')
                <h2 id="mt-titre" class="text-lg font-display font-bold text-[#0F172A] mb-2">
                    Fermer le site au public ?
                </h2>
                <p class="text-sm text-[#334155] mb-2">
                    Plus personne ne pourra soumettre une vérification, suivre un cours ou
                    lire un démenti. <strong>Votre accès au back-office est préservé.</strong>
                </p>
                <p class="text-sm text-[#64748B] mb-5">
                    @if ($finAnnoncee !== '')
                        La page annoncera une réouverture pour
                        {{ \Carbon\Carbon::parse($finAnnoncee)->translatedFormat('j M à H:i') }}.
                    @else
                        Aucune heure ne sera annoncée : la page proposera simplement de prévenir
                        par courriel.
                    @endif
                </p>

                <div class="flex gap-2 justify-end">
                    <button type="button" wire:click="annuler" class="st-btn st-btn-secondary">Annuler</button>
                    <button type="button" wire:click="ouvrirLaMaintenance" wire:loading.attr="disabled"
                            class="st-btn st-btn-primary">Fermer maintenant</button>
                </div>
            @else
                <h2 id="mt-titre" class="text-lg font-display font-bold text-[#0F172A] mb-2">
                    Rouvrir le service ?
                </h2>
                <p class="text-sm text-[#334155] mb-2">
                    Le site redevient accessible immédiatement.
                    @if ($enAttente > 0)
                        <strong>{{ $enAttente }} courriel(s) partiront</strong> vers les personnes
                        qui ont demandé à être prévenues.
                    @else
                        Personne n’a demandé à être prévenu.
                    @endif
                </p>

                {{-- Le rappel qui compte : un courriel parti ne se rappelle pas. --}}
                <p class="text-sm text-[#64748B] mb-5">
                    Assurez-vous que le service fonctionne vraiment avant de rouvrir : ces
                    messages ne se rappellent pas, et une réouverture prématurée renverrait tout
                    le monde sur un site cassé.
                </p>

                <div class="flex gap-2 justify-end">
                    <button type="button" wire:click="annuler" class="st-btn st-btn-secondary">Annuler</button>
                    <button type="button" wire:click="fermerLaMaintenance" wire:loading.attr="disabled"
                            class="st-btn st-btn-primary">Rouvrir et prévenir</button>
                </div>
            @endif
        </div>
    @endif
</div>
