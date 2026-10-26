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
<div>

    <x-premium.entete titre="Gabarits d'emails">
        Le texte de tout ce que la plateforme envoie. La liste est fermée : elle énumère les envois que le code déclenche, et rien d'autre.
    </x-premium.entete>

    <x-premium.barre-de-liste placeholder="Rechercher un événement, un sujet, une variable…" :actifs="$filtresActifs" reinitialiser="reinitialiserLaListe">
        <div>
            <label for="gab-f-etat" class="etiquette">État</label>
            <select id="gab-f-etat" wire:model.live="filtreEtat" class="champ">
                <option value="">Tous</option>
                <option value="actif">Actif</option>
                <option value="eteint">Éteint</option>
                <option value="indispensable">Indispensable</option>
                <option value="non_cable">Envoi non branché</option>
            </select>
        </div>
        <div>
            <label for="gab-f-texte" class="etiquette">Texte</label>
            <select id="gab-f-texte" wire:model.live="filtreTexte" class="champ">
                <option value="">Tous</option>
                <option value="modifie">Modifié par l'équipe</option>
                <option value="origine">Texte d'origine</option>
            </select>
        </div>
        <div>
            <label for="gab-f-variable" class="etiquette">Contient la variable</label>
            <select id="gab-f-variable" wire:model.live="filtreVariable" class="champ">
                <option value="">Toutes</option>
                @foreach ($variablesDisponibles as $variable)<option value="{{ $variable }}">{{ $variable }}</option>@endforeach
            </select>
        </div>
        <div>
            <label for="gab-f-auteur" class="etiquette">Dernière modification par</label>
            <select id="gab-f-auteur" wire:model.live="filtreAuteur" class="champ">
                <option value="">Tous</option>
                @foreach ($auteurs as $auteur)<option value="{{ $auteur }}">{{ $auteur }}</option>@endforeach
            </select>
        </div>
    </x-premium.barre-de-liste>

    <x-data-table variant="staff" :count="$gabarits->total()" :selectable="false">
        <x-slot:head>
            @foreach ([['libelle', 'Événement déclencheur'], ['sujet', "Sujet de l'email"]] as [$col, $lib])
                <th scope="col" aria-sort="{{ $tri === $col ? ($sens === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                    <button type="button" class="tri" wire:click="trierPar('{{ $col }}')">{{ $lib }}@if ($tri === $col)<x-lucide :name="$sens === 'asc' ? 'arrow-up' : 'arrow-down'" class="w-3 h-3" />@endif</button>
                </th>
            @endforeach
            <th scope="col">Variables</th>
            <th scope="col" aria-sort="{{ $tri === 'modifieLe' ? ($sens === 'asc' ? 'ascending' : 'descending') : 'none' }}">
                <button type="button" class="tri" wire:click="trierPar('modifieLe')">Dernière modif.@if ($tri === 'modifieLe')<x-lucide :name="$sens === 'asc' ? 'arrow-up' : 'arrow-down'" class="w-3 h-3" />@endif</button>
            </th>
            <th class="text-right">Actions</th>
        </x-slot:head>

        @foreach ($gabarits as $ligne)
            <tr wire:key="gab-{{ $ligne['cle'] }}">
                <td style="min-width: 260px;">
                    <p class="font-semibold text-encre dark:text-white mb-0">{{ $ligne['libelle'] }}</p>
                    <p class="text-xs text-encre-3 dark:text-nuit-3 mb-1">{{ $ligne['declencheur'] }}</p>

                    @if (! $ligne['porte'])
                        {{-- Dit à l'écran, pas caché dans un fichier : un texte
                             qu'on peut soigner sans que rien ne l'envoie doit
                             s'annoncer comme tel. --}}
                        <span class="pastille p-neutre">Pas encore câblé</span>
                    @elseif ($ligne['verrouille'])
                        <span class="pastille p-bon">Indispensable</span>
                    @elseif (! $ligne['actif'])
                        <span class="pastille p-neutre">Éteint</span>
                    @endif
                </td>

                <td class="text-sm text-encre-2 dark:text-nuit-2" style="min-width: 220px;">
                    {{ $ligne['sujet'] }}
                </td>

                <td style="min-width: 180px;">
                    <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0" style="word-break: break-word;">
                        @foreach ($ligne['variables'] as $variable)
                            <code>{{ $variable }}</code>@if (! $loop->last), @endif
                        @endforeach
                    </p>
                </td>

                <td class="text-sm text-encre-3 dark:text-nuit-3 whitespace-nowrap">
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
                                class="text-xs font-bold text-brand-700 dark:text-brand">Modifier</button>

                        @unless ($ligne['verrouille'])
                            <span class="text-encre-3 dark:text-nuit-3" aria-hidden="true">·</span>
                            <button type="button" wire:click="basculer('{{ $ligne['cle'] }}')"
                                    class="text-xs font-bold text-encre-3 dark:text-nuit-3">
                                {{ $ligne['actif'] ? 'Éteindre' : 'Allumer' }}
                            </button>
                        @endunless

                        @if ($ligne['personnalise'])
                            <span class="text-encre-3 dark:text-nuit-3" aria-hidden="true">·</span>
                            <button type="button" wire:click="reinitialiser('{{ $ligne['cle'] }}')"
                                    class="text-xs font-bold text-encre-3 dark:text-nuit-3">Réinitialiser</button>
                        @endif
                    @else
                        <span class="text-xs text-encre-3 dark:text-nuit-3">Lecture seule</span>
                    @endif
                </td>
            </tr>
        @endforeach
    </x-data-table>

    @if ($gabarits->isEmpty())
        <div class="carte"><x-premium.vide icone="mails" titre="Aucun modèle ne correspond" texte="Changez la recherche ou retirez un filtre.">
            <button type="button" class="btn-second" wire:click="reinitialiserLaListe">Réinitialiser les filtres</button>
        </x-premium.vide></div>
    @endif

    <x-premium.pied-de-liste :liste="$gabarits" />

    {{-- ═══════════════════════════════════════════════════════ le formulaire --}}
    <x-offcanvas-form variant="staff"
                      :open="$formulaireOuvert"
                      title="Modifier le gabarit"
                      close="fermerLeTiroir">
        <form wire:submit="enregistrer" class="space-y-1">

        @if ($evenementEnEdition)
            <p class="text-sm text-encre-3 dark:text-nuit-3 mb-4">
                <strong class="text-encre dark:text-white">{{ $evenementEnEdition['libelle'] }}</strong><br>
                Déclenché par : {{ $evenementEnEdition['declencheur'] }}
            </p>

            @unless ($evenementEnEdition['porte'])
                {{-- `p-alerte` plutôt qu'une couleur en dur : le build
                     Tailwind est figé, et `text-amber-800 dark:text-amber-300` n'y existe pas — la
                     phrase serait partie en gris sans que rien ne le signale.
                     Le vocabulaire du design system dit déjà « attention ». --}}
                <p class="text-sm text-encre-3 dark:text-nuit-3 mb-4">
                    <span class="pastille p-alerte">Pas encore câblé</span>
                    Le texte est enregistré et partira dès que l'envoi sera branché.
                </p>
            @endunless
        @endif

        <div class="mb-4">
            <div class="mb-1">
                <label for="gab-sujet" class="etiquette">Sujet de l'email (Objet) *</label>
<input id="gab-sujet" type="text" wire:model.blur="sujet" maxlength="200" class="champ">
            </div>
            @error('sujet') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">
                La ligne visible dans la boîte de réception. Elle doit se lire hors contexte.
            </p>
        </div>

        <div class="mb-4">
            <div class="mb-1">
                <label for="gab-titre" class="etiquette">Titre affiché dans le message *</label>
<input id="gab-titre" type="text" wire:model.blur="titre" maxlength="200" class="champ">
            </div>
            @error('titre') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">
                Distinct de l'objet : il s'adresse à quelqu'un qui a déjà ouvert le message.
            </p>
        </div>

        <div class="mb-4">
            <div class="mb-1">
                {{-- `.ffield` porte déjà les textarea : tous les autres écrans
                     staff s'en servent ainsi. La chasse fixe est en style EN
                     LIGNE — c'est une propriété du contenu (du Markdown), pas
                     une classe du design system. --}}
                <label for="gab-corps" class="etiquette">Contenu du message *</label>
<textarea id="gab-corps" wire:model.blur="corps" rows="12"
                          style="font-family: ui-monospace, SFMono-Regular, Menlo, monospace; line-height:1.6" class="champ !h-auto py-2.5"></textarea>
            </div>
            @error('corps') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror

            {{-- Markdown et non un éditeur riche : un courriel part hors de
                 l'application, où aucun filtre ne rattrape une balise mal
                 fermée, et où les clients de messagerie retirent la moitié du
                 HTML sans prévenir. --}}
            <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">
                Markdown : <code>**gras**</code>, <code>## titre</code>, <code>&gt; citation</code>,
                ligne vide pour un paragraphe. L'habillage — en-tête, couleurs, pied — est
                celui de la charte et ne se saisit pas ici.
            </p>
        </div>

        @if ($variablesDuGabarit !== [])
            {{-- Aide-mémoire des variables : un clic copie le marqueur. On n'écrit
                 pas `{{ prenom }}` de mémoire — un marqueur inventé part tel quel
                 dans toutes les boîtes. L'enregistrement les refuse ; cette liste
                 évite d'y arriver. Alimentée par `CatalogueDesCourriels::variables()`. --}}
            <div class="mb-4 rounded-ctrl border filet bg-papier dark:bg-nuit p-3.5" x-data="{ copie: null }">
                <p class="text-sm font-medium mb-2 flex items-center gap-1.5"><x-lucide name="braces" class="w-4 h-4" />Variables disponibles</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($variablesDuGabarit as $variable)
                        <button type="button" class="inline-flex items-center gap-1.5 h-7 px-2.5 rounded-ctrl border filet bg-white dark:bg-nuit-carte font-mono text-xs hover:border-brand/60 transition-colors"
                                x-on:click="navigator.clipboard?.writeText(@js($variable)); copie = @js($variable); setTimeout(() => copie = null, 1500)"
                                aria-label="Copier {{ $variable }}">
                            <code>{{ $variable }}</code>
                            <x-lucide name="copy" class="w-3 h-3 texte-3" x-show="copie !== @js($variable)" />
                            <x-lucide name="check" class="w-3 h-3 text-bon" x-show="copie === @js($variable)" x-cloak />
                        </button>
                    @endforeach
                </div>
                <p class="aide">Cliquez sur une variable pour la copier, puis collez-la dans le texte. Une variable qui n'est pas dans cette liste est refusée à l'enregistrement.</p>
            </div>
        @endif

        <div class="mb-4">
            <div class="mb-1">
                <label for="gab-cta" class="etiquette">Libellé du bouton</label>
<input id="gab-cta" type="text" wire:model.blur="libelleDuBouton" maxlength="80" class="champ">
            </div>
            @error('libelleDuBouton') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">
                Laissé vide, le message ne porte pas de bouton. L'adresse, elle, est
                calculée par l'envoi — un lien saisi à la main vieillirait mal.
            </p>
        </div>

        @if ($evenementEnEdition && ! $evenementEnEdition['verrouille'])
            <label class="flex items-start gap-2.5 cursor-pointer">
                <input type="checkbox" wire:model="actif" class="mt-0.5">
                <span class="text-sm text-encre-2 dark:text-nuit-2">
                    Envoi actif
                    <span class="block text-xs text-encre-3 dark:text-nuit-3">
                        Éteint, le gabarit reste enregistré mais aucun message ne part.
                    </span>
                </span>
            </label>
        @elseif ($evenementEnEdition)
            <p class="text-sm text-encre-3 dark:text-nuit-3 mb-0">
                Cet envoi ne peut pas être éteint : sans lui, la personne concernée n'aurait
                aucun moyen d'apprendre ce qui se passe sur son compte.
            </p>
        @endif

            <div class="flex gap-2 justify-end pt-4">
                <button type="button" wire:click="fermerLeTiroir" class="btn-fantome">Annuler</button>
                <button type="submit" class="btn-primaire" wire:loading.attr="disabled">Enregistrer</button>
            </div>
        </form>
    </x-offcanvas-form>
</div>
