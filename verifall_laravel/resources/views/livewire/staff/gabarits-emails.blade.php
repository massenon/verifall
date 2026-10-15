{{--
  | Gabarits d'emails.
  |
  | La table reprend les colonnes demandées — Événement déclencheur, Sujet,
  | Variables, Dernière modif., Actions — et en ajoute une que l'écran doit
  | porter : **câblé**. Plusieurs gabarits sont rédigeables alors que rien ne les
  | expédie encore ; soigner un texte que rien n'envoie est exactement le piège
  | que ce chantier a corrigé quatre fois, et le taire sur l'écran qui prétend
  | tout lister l'aurait recréé.
--}}
<div class="p-4 sm:p-6 lg:p-8">

    <div class="mb-5">
        <h1 class="text-xl font-display font-bold text-[#0F172A] mb-1">Gabarits d'emails</h1>
        <p class="text-sm text-[#64748B] mb-0">
            Le texte de tout ce que la plateforme envoie. La liste est fermée : elle énumère
            les envois que le code déclenche, et rien d'autre.
        </p>
    </div>

    <x-data-table variant="staff" :count="count($gabarits)" :selectable="false">
        <x-slot:head>
            <th scope="col">Événement déclencheur</th>
            <th scope="col">Sujet de l'email</th>
            <th scope="col">Variables</th>
            <th scope="col">Dernière modif.</th>
            <th class="text-right">Actions</th>
        </x-slot:head>

        @foreach ($gabarits as $ligne)
            <tr wire:key="gab-{{ $ligne['cle'] }}">
                <td style="min-width: 260px;">
                    <p class="font-semibold text-[#0F172A] mb-0">{{ $ligne['libelle'] }}</p>
                    <p class="text-xs text-[#64748B] mb-1">{{ $ligne['declencheur'] }}</p>

                    @if (! $ligne['porte'])
                        {{-- Dit à l'écran, pas caché dans un fichier : un texte
                             qu'on peut soigner sans que rien ne l'envoie doit
                             s'annoncer comme tel. --}}
                        <span class="st-badge st-badge-draft">Pas encore câblé</span>
                    @elseif ($ligne['verrouille'])
                        <span class="st-badge st-badge-pub">Indispensable</span>
                    @elseif (! $ligne['actif'])
                        <span class="st-badge st-badge-draft">Éteint</span>
                    @endif
                </td>

                <td class="text-sm text-[#334155]" style="min-width: 220px;">
                    {{ $ligne['sujet'] }}
                </td>

                <td style="min-width: 180px;">
                    <p class="text-xs text-[#64748B] mb-0" style="word-break: break-word;">
                        @foreach ($ligne['variables'] as $variable)
                            <code>{{ $variable }}</code>@if (! $loop->last), @endif
                        @endforeach
                    </p>
                </td>

                <td class="text-sm text-[#64748B] whitespace-nowrap">
                    @if ($ligne['modifieLe'])
                        {{ $ligne['modifieLe'] }}
                        {{-- Qui, et pas seulement quand : sur un texte qui part
                             au public, la date seule ne répond à personne. --}}
                        @if ($ligne['modifiePar'])
                            <span class="block text-xs">par {{ $ligne['modifiePar'] }}</span>
                        @endif
                    @else
                        Texte d'origine
                    @endif
                </td>

                <td class="text-right whitespace-nowrap">
                    @if ($peutGerer)
                        <button type="button" wire:click="ouvrirEdition('{{ $ligne['cle'] }}')"
                                class="text-xs font-bold text-primary">Modifier</button>

                        @unless ($ligne['verrouille'])
                            <span class="text-[#64748B]" aria-hidden="true">·</span>
                            <button type="button" wire:click="basculer('{{ $ligne['cle'] }}')"
                                    class="text-xs font-bold text-[#64748B]">
                                {{ $ligne['actif'] ? 'Éteindre' : 'Allumer' }}
                            </button>
                        @endunless

                        @if ($ligne['personnalise'])
                            <span class="text-[#64748B]" aria-hidden="true">·</span>
                            <button type="button" wire:click="reinitialiser('{{ $ligne['cle'] }}')"
                                    class="text-xs font-bold text-[#64748B]">Réinitialiser</button>
                        @endif
                    @else
                        <span class="text-xs text-[#64748B]">Lecture seule</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-data-table>

    {{-- ═══════════════════════════════════════════════════════ le formulaire --}}
    <x-offcanvas-form variant="staff"
                      :open="$formulaireOuvert"
                      title="Modifier le gabarit"
                      close="fermerLeTiroir">
        <form wire:submit="enregistrer" class="space-y-1">

        @if ($evenementEnEdition)
            <p class="text-sm text-[#64748B] mb-4">
                <strong class="text-[#0F172A]">{{ $evenementEnEdition['libelle'] }}</strong><br>
                Déclenché par : {{ $evenementEnEdition['declencheur'] }}
            </p>

            @unless ($evenementEnEdition['porte'])
                {{-- `st-badge-warn` plutôt qu'une couleur en dur : le build
                     Tailwind est figé, et `text-[#92400E]` n'y existe pas — la
                     phrase serait partie en gris sans que rien ne le signale.
                     Le vocabulaire du design system dit déjà « attention ». --}}
                <p class="text-sm text-[#64748B] mb-4">
                    <span class="st-badge st-badge-warn">Pas encore câblé</span>
                    Le texte est enregistré et partira dès que l'envoi sera branché.
                </p>
            @endunless
        @endif

        <div class="mb-4">
            <div class="ffield mb-1">
                <input id="gab-sujet" type="text" wire:model.blur="sujet" maxlength="200" placeholder=" ">
                <label for="gab-sujet">Sujet de l'email (Objet) *</label>
            </div>
            @error('sujet') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            <p class="text-xs text-[#64748B] mt-1 mb-0">
                La ligne visible dans la boîte de réception. Elle doit se lire hors contexte.
            </p>
        </div>

        <div class="mb-4">
            <div class="ffield mb-1">
                <input id="gab-titre" type="text" wire:model.blur="titre" maxlength="200" placeholder=" ">
                <label for="gab-titre">Titre affiché dans le message *</label>
            </div>
            @error('titre') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            <p class="text-xs text-[#64748B] mt-1 mb-0">
                Distinct de l'objet : il s'adresse à quelqu'un qui a déjà ouvert le message.
            </p>
        </div>

        <div class="mb-4">
            <div class="ffield mb-1">
                {{-- `.ffield` porte déjà les textarea : tous les autres écrans
                     staff s'en servent ainsi. La chasse fixe est en style EN
                     LIGNE — c'est une propriété du contenu (du Markdown), pas
                     une classe du design system. --}}
                <textarea id="gab-corps" wire:model.blur="corps" rows="12" placeholder=" "
                          style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace; line-height:1.6"></textarea>
                <label for="gab-corps">Contenu du message *</label>
            </div>
            @error('corps') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror

            {{-- Markdown et non un éditeur riche : un courriel part hors de
                 l'application, où aucun filtre ne rattrape une balise mal
                 fermée, et où les clients de messagerie retirent la moitié du
                 HTML sans prévenir. --}}
            <p class="text-xs text-[#64748B] mt-1 mb-0">
                Markdown : <code>**gras**</code>, <code>## titre</code>, <code>&gt; citation</code>,
                ligne vide pour un paragraphe. L'habillage — en-tête, couleurs, pied — est
                celui de la charte et ne se saisit pas ici.
            </p>
        </div>

        @if ($variablesDuGabarit !== [])
            <div class="mb-4">
                <p class="block text-sm font-medium text-[#0F172A] mb-1">Variables disponibles</p>
                {{-- Affichées parce qu'on n'écrit pas `{{ prenom }}` de mémoire —
                     et un marqueur inventé part tel quel dans toutes les boîtes.
                     L'enregistrement les refuse ; cette liste évite d'y arriver. --}}
                <p class="text-xs text-[#64748B] mb-0">
                    @foreach ($variablesDuGabarit as $variable)
                        <code>{{ $variable }}</code>@if (! $loop->last) &nbsp; @endif
                    @endforeach
                </p>
            </div>
        @endif

        <div class="mb-4">
            <div class="ffield mb-1">
                <input id="gab-cta" type="text" wire:model.blur="libelleDuBouton" maxlength="80" placeholder=" ">
                <label for="gab-cta">Libellé du bouton</label>
            </div>
            @error('libelleDuBouton') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            <p class="text-xs text-[#64748B] mt-1 mb-0">
                Laissé vide, le message ne porte pas de bouton. L'adresse, elle, est
                calculée par l'envoi — un lien saisi à la main vieillirait mal.
            </p>
        </div>

        @if ($evenementEnEdition && ! $evenementEnEdition['verrouille'])
            <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" wire:model="actif" class="mt-0.5">
                <span class="text-sm text-[#334155]">
                    Envoi actif
                    <span class="block text-xs text-[#64748B]">
                        Éteint, le gabarit reste enregistré mais aucun message ne part.
                    </span>
                </span>
            </label>
        @elseif ($evenementEnEdition)
            <p class="text-sm text-[#64748B] mb-0">
                Cet envoi ne peut pas être éteint : sans lui, la personne concernée n'aurait
                aucun moyen d'apprendre ce qui se passe sur son compte.
            </p>
        @endif

            <div class="flex gap-2 justify-end pt-4">
                <button type="button" wire:click="fermerLeTiroir" class="st-btn st-btn-ghost">Annuler</button>
                <button type="submit" class="st-btn st-btn-primary" wire:loading.attr="disabled">Enregistrer</button>
            </div>
        </form>
    </x-offcanvas-form>
</div>
