{{--
  | « Paramètres » — dix onglets, une soixantaine de réglages.
  |
  | Le formulaire est ENGENDRÉ depuis `CatalogueDesReglages` : chaque champ tire
  | son libellé, son type, son aide et ses règles de la définition. Ajouter un
  | réglage se fait dans le catalogue, pas ici.
  |
  | Deux choses méritent d'être vues en lisant ce gabarit :
  |
  |  · un champ SECRET arrive toujours vide, et l'écran le dit — « une valeur
  |    est enregistrée ». Sans cette mention, on croirait le champ non
  |    renseigné et on le réécrirait à chaque passage ;
  |  · un champ FICHIER montre l'existant en aperçu à côté du sélecteur, parce
  |    qu'on ne remplace pas un logo sans voir celui qu'on remplace.
--}}
<div>

<x-premium.entete titre="Paramètres" :fil="['Paramètres']">
        Ce qui se décide sans développeur. Les clés et mots de passe sont chiffrés en base et ne sont jamais renvoyés au navigateur.
    </x-premium.entete>

    {{-- `reglages` plutôt que des utilitaires : `lg:grid-cols-[240px_1fr]`,
         `lg:top-6`, `lg:self-start` et `lg:flex-col` N'EXISTENT PAS dans le
         build Tailwind figé. Elles étaient écrites ici depuis l'origine et
         n'avaient aucun effet : le panneau ne collait pas, la grille ne se
         formait pas. La règle vit désormais dans `staff.css`, qui est du CSS
         de projet écrit à la main — c'est `tailwind.css` qui est gelé, pas
         la feuille du back-office. --}}
    <div class="reglages">

        {{-- Les onglets, en colonne sur grand écran et en bandeau défilant sur
             téléphone — dix onglets ne tiennent pas côte à côte sur 390 px. --}}
        <nav class="reglages-nav" aria-label="Sections des paramètres">
            <div class="reglages-onglets">
                @foreach ($groupes as $cle => $groupe)
                    <button type="button" wire:click="changerDOnglet('{{ $cle }}')"
                            @class([
                                'flex items-center gap-2 px-3 py-2.5 rounded-xl text-sm whitespace-nowrap transition-colors text-left',
                                'bg-foret text-white font-semibold' => $onglet === $cle,
                                'text-encre-2 dark:text-nuit-2 hover:bg-papier dark:hover:bg-nuit' => $onglet !== $cle,
                            ])
                            @if ($onglet === $cle) aria-current="page" @endif>
                        <x-lucide :name="$groupe['icone']" class="w-4 h-4" />
                        {{ $groupe['libelle'] }}
                    </button>
                @endforeach
            </div>
        </nav>

        <section>
            <div class="carte p-5 lg:p-6">
                <header class="mb-5 pb-4 border-b border-trait dark:border-nuit-trait">
                    <h2 class="font-titre font-bold text-lg text-encre dark:text-white mb-1">
                        {{ $groupes[$onglet]['libelle'] }}
                    </h2>
                    <p class="text-sm text-encre-3 dark:text-nuit-3 mb-0">{{ $groupes[$onglet]['aide'] }}</p>
                </header>

                <form wire:submit="enregistrer" class="space-y-5">
                    @foreach ($reglages as $reglage)
                        @php
                            $champ = $reglage->type->estUnFichier()
                                ? 'fichiers.'.$reglage->cle
                                : 'valeurs.'.$reglage->cle;
                            $identifiant = 'reglage-'.$reglage->cle;
                        @endphp

                        <div>
                            @if ($reglage->type->controle() === 'checkbox')
                                <label class="flex items-start gap-2.5 cursor-pointer">
                                    <input type="checkbox" id="{{ $identifiant }}"
                                           wire:model="valeurs.{{ $reglage->cle }}" class="mt-0.5">
                                    <span class="text-sm text-encre-2 dark:text-nuit-2">
                                        {{ $reglage->libelle }}
                                        @if ($reglage->aide)
                                            <span class="block text-xs text-encre-3 dark:text-nuit-3 mt-0.5">{{ $reglage->aide }}</span>
                                        @endif
                                    </span>
                                </label>

                            @elseif ($reglage->type->controle() === 'select')
                                <label for="{{ $identifiant }}" class="block text-sm font-medium text-encre dark:text-white mb-1">{{ $reglage->libelle }}</label>
                                <select id="{{ $identifiant }}" wire:model="valeurs.{{ $reglage->cle }}" class="champ w-full">
                                    @foreach ($reglage->choix as $choix)
                                        <option value="{{ $choix['valeur'] }}">{{ $choix['libelle'] }}</option>
                                    @endforeach
                                </select>
                                @if ($reglage->aide)
                                    <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">{{ $reglage->aide }}</p>
                                @endif

                            @elseif ($reglage->type->controle() === 'textarea')
                                <label for="{{ $identifiant }}" class="block text-sm font-medium text-encre dark:text-white mb-1">{{ $reglage->libelle }}</label>
                                <textarea id="{{ $identifiant }}" rows="3"
                                          wire:model="valeurs.{{ $reglage->cle }}"
                                          placeholder="{{ $reglage->exemple }}"
                                          class="champ w-full"></textarea>
                                @if ($reglage->aide)
                                    <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">{{ $reglage->aide }}</p>
                                @endif

                            @elseif ($reglage->type->controle() === 'file')
                                <label for="{{ $identifiant }}" class="block text-sm font-medium text-encre dark:text-white mb-1">{{ $reglage->libelle }}</label>

                                <div class="flex items-center gap-4">
                                    {{-- L'aperçu de l'existant. On ne remplace pas un logo
                                         sans voir celui qu'on remplace. --}}
                                    @if (($valeurs[$reglage->cle] ?? null))
                                        <img src="{{ asset($valeurs[$reglage->cle]) }}"
                                             alt="Aperçu de {{ $reglage->libelle }}"
                                             class="w-14 h-14 object-contain rounded-lg border border-trait dark:border-nuit-trait bg-white p-1 shrink-0">
                                    @else
                                        <span class="w-14 h-14 rounded-lg border border-dashed border-trait dark:border-nuit-trait flex items-center justify-center text-encre-3 dark:text-nuit-3 shrink-0">
                                            <x-lucide name="image" class="w-4 h-4" />
                                        </span>
                                    @endif

                                    <div class="flex-1 min-w-0">
                                        <input type="file" id="{{ $identifiant }}"
                                               wire:model="fichiers.{{ $reglage->cle }}"
                                               class="text-sm w-full">

                                        {{-- Aperçu LIVE du fichier qu'on vient de choisir,
                                             avant enregistrement. --}}
                                        @if (isset($fichiers[$reglage->cle]) && $fichiers[$reglage->cle])
                                            <div wire:key="apercu-{{ $reglage->cle }}" class="mt-2 flex items-center gap-2">
                                                @if (method_exists($fichiers[$reglage->cle], 'temporaryUrl') && str_starts_with((string) $fichiers[$reglage->cle]->getMimeType(), 'image/'))
                                                    <img src="{{ $fichiers[$reglage->cle]->temporaryUrl() }}"
                                                         alt="Aperçu du nouveau fichier"
                                                         class="w-10 h-10 object-contain rounded border border-trait dark:border-nuit-trait bg-white p-1">
                                                @endif
                                                <span class="text-xs text-brand-700 dark:text-brand font-medium">Sera enregistré</span>
                                            </div>
                                        @endif
                                    </div>
                                </div>

                                @if ($reglage->aide)
                                    <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1.5 mb-0">{{ $reglage->aide }}</p>
                                @endif

                            @else
                                <label for="{{ $identifiant }}" class="block text-sm font-medium text-encre dark:text-white mb-1">
                                    {{ $reglage->libelle }}
                                    @if ($reglage->secret)
                                        <x-lucide name="lock" class="w-4 h-4 text-encre-3 dark:text-nuit-3" />
                                        <span class="sr-only">(valeur chiffrée)</span>
                                    @endif
                                </label>

                                <input type="{{ $reglage->type->typeHtml() }}" id="{{ $identifiant }}"
                                       wire:model="valeurs.{{ $reglage->cle }}"
                                       placeholder="{{ $reglage->exemple }}"
                                       @if ($reglage->secret) autocomplete="new-password" @endif
                                       class="champ w-full">

                                {{-- LA mention qui évite un contresens.
                                     Le champ arrive vide parce que la valeur ne
                                     descend jamais vers le navigateur — pas parce
                                     qu'elle est absente. Sans ce texte, on la
                                     ressaisirait à chaque passage. --}}
                                @if ($reglage->secret && ($secretsRenseignes[$reglage->cle] ?? false))
                                    <p class="text-xs text-brand-700 dark:text-brand mt-1 mb-0">
                                        Une valeur est enregistrée. Laissez vide pour la conserver.
                                    </p>
                                @endif

                                @if ($reglage->aide)
                                    <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">{{ $reglage->aide }}</p>
                                @endif
                            @endif

                            @error($champ)
                                <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach

                    <div class="pt-3 border-t border-trait dark:border-nuit-trait flex items-center gap-3">
                        <button type="submit" class="btn-primaire">
                            <span wire:loading.remove wire:target="enregistrer">Enregistrer</span>
                            <span wire:loading wire:target="enregistrer">Enregistrement…</span>
                        </button>
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">
                            Seul l'onglet affiché est enregistré.
                        </p>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>
