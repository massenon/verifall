{{--
  | « Nos Partenaires » (lot 9).
  |
  | Remplace un carrousel de logos écrit EN DUR dans `public/accueil.blade.php`.
  | Ajouter un partenaire — geste ordinaire d'un projet financé, et souvent une
  | obligation contractuelle — exigeait un développeur et un déploiement.
  |
  | Le logo est un CHEMIN relatif à `public/`, pas un téléversement : ouvrir une
  | chaîne complète de dépôt de fichier pour six images qui bougent une fois par
  | an serait disproportionné.
--}}
<div>

<x-premium.entete titre="Nos partenaires" :fil="['Nos partenaires']">
        Le carrousel de l'accueil. L'ordre d'apparition se négocie parfois par convention : c'est <span class="font-semibold">l'ordre d'affichage</span> qui le fixe.
        <x-slot:actions>
            @if ($peutGerer)
            <button type="button" wire:click="ouvrirCreation" class="st-btn st-btn-primary">
                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">add</span>
                Ajouter un partenaire
            </button>
        @endif
        </x-slot:actions>
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="3">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Partenaires" trend="Référencés" />

        <x-kpi variant="staff" :value="number_format($indicateurs['publies'], 0, ',', ' ')"
               label="Affichés" trend="Visibles sur l'accueil" />

        {{-- Un partenaire publié sans logo laisse un trou dans le carrousel.
             Personne d'autre ne le signale. --}}
        <x-kpi variant="staff" :value="number_format($indicateurs['sans_logo'], 0, ',', ' ')"
               label="Sans logo" trend="Affichés mais sans image" />
    </x-kpi-grid>

    <section class="mb-4">
        <div class="ffield mb-0">
            <input id="pt-q" type="search" wire:model.live.debounce.350ms="recherche" placeholder=" ">
            <label for="pt-q">Rechercher un partenaire</label>
        </div>
    </section>

    @if ($partenaires->isEmpty())
        <x-empty-state variant="staff" icon="handshake"
                       title="Aucun partenaire"
                       description="Le carrousel de l'accueil sera vide. Chaque partenaire ajouté ici y paraît, dans l'ordre choisi." />
    @else
        <x-data-table variant="staff" :count="$partenaires->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Partenaire</th>
                <th scope="col">Logo</th>
                <th scope="col">Site</th>
                <th scope="col">Ordre</th>
                <th scope="col">État</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($partenaires as $partenaire)
                <tr>
                    <td>
                        <p class="font-semibold text-[#0F172A] mb-0">{{ $partenaire->name }}</p>
                        @if ($partenaire->description !== null)
                            <p class="text-xs text-[#64748B] mb-0">{{ Str::limit($partenaire->description, 70) }}</p>
                        @endif
                    </td>
                    <td class="whitespace-nowrap">
                        @if ($partenaire->logo_path !== null)
                            <img src="{{ asset($partenaire->logo_path) }}" alt=""
                                 class="h-8 object-contain" loading="lazy">
                        @else
                            <span class="text-xs text-[#DC2626]">Manquant</span>
                        @endif
                    </td>
                    <td>
                        @if ($partenaire->url !== null)
                            {{-- `rel` complet : un lien vers un site tiers ouvert depuis
                                 le back-office ne doit ni transmettre notre adresse, ni
                                 garder la main sur l'onglet d'origine. --}}
                            <a href="{{ $partenaire->url }}" target="_blank" rel="noopener noreferrer nofollow"
                               class="text-sm text-[#059669] underline break-all">{{ Str::limit($partenaire->url, 40) }}</a>
                        @else
                            <span class="text-xs text-[#64748B]">—</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-[#64748B]">{{ $partenaire->sort_order }}</td>
                    <td>
                        <x-badge-statut variant="staff"
                                        :statut="$partenaire->is_published ? 'publie' : 'brouillon'"
                                        :label="$partenaire->is_published ? 'Affiché' : 'Masqué'" />
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @if ($peutGerer)
                            <button type="button" wire:click="basculerLaPublication('{{ $partenaire->uuid }}')"
                                    class="st-icnbtn" title="{{ $partenaire->is_published ? 'Retirer de l\'accueil' : 'Afficher' }}">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">{{ $partenaire->is_published ? 'visibility_off' : 'visibility' }}</span>
                            </button>
                            <button type="button" wire:click="ouvrirEdition('{{ $partenaire->uuid }}')"
                                    class="st-icnbtn" title="Modifier">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">edit</span>
                            </button>
                            <button type="button" wire:click="demanderLaSuppression('{{ $partenaire->uuid }}')"
                                    class="st-icnbtn text-[#DC2626]" title="Supprimer">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">delete</span>
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $partenaires->links() }}</div>
    @endif

    @if ($peutGerer)
        <x-offcanvas-form :open="$formulaireOuvert"
                          :title="$enEdition === null ? 'Ajouter un partenaire' : 'Modifier le partenaire'"
                          close="fermerLeTiroir">
            <form wire:submit="enregistrer" class="space-y-4">
                <div class="ffield">
                    <input id="pt-nom" type="text" wire:model="nom" placeholder=" ">
                    <label for="pt-nom">Nom du partenaire</label>
                    @error('nom') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="ffield">
                    <input id="pt-logo" type="text" wire:model="logo" placeholder=" ">
                    <label for="pt-logo">Chemin du logo</label>
                    <p class="text-xs text-[#64748B] mt-1 mb-0">
                        Relatif au dossier public, par exemple
                        <span class="font-mono">assets/images/partners/ofaj.png</span>.
                        Déposez l'image sur le serveur, puis indiquez son chemin ici.
                    </p>
                    @error('logo') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="ffield">
                    <input id="pt-url" type="url" wire:model="lien" placeholder=" ">
                    <label for="pt-url">Site du partenaire (https)</label>
                    @error('lien') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="ffield">
                    <textarea id="pt-desc" wire:model="descriptif" rows="2" placeholder=" "></textarea>
                    <label for="pt-desc">Description (interne)</label>
                    @error('descriptif') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="ffield">
                    <input id="pt-ordre" type="number" min="0" wire:model="ordre" placeholder=" ">
                    <label for="pt-ordre">Ordre d'affichage</label>
                    @error('ordre') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input type="checkbox" wire:model="publie" class="mt-0.5">
                    <span class="text-sm text-[#334155]">Afficher sur l'accueil</span>
                </label>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="st-btn st-btn-primary flex-1">Enregistrer</button>
                    <button type="button" wire:click="fermerLeTiroir" class="st-btn st-btn-secondary">Annuler</button>
                </div>
            </form>
        </x-offcanvas-form>
    @endif

    <x-delete-modal :open="$aSupprimer !== null"
                    title="Supprimer ce partenaire ?"
                    description="La suppression est définitive. Pour le retirer du carrousel sans perdre sa fiche, masquez-le plutôt."
                    confirm="supprimer"
                    cancel="annulerLaSuppression" />
</div>
