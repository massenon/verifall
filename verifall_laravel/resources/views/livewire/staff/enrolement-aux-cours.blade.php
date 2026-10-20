{{--
  | « Enrôlement aux Cours » (lot 7).
  |
  | L'écran qui répond aux deux questions les plus posées au support : « je me
  | suis inscrit et le cours n'apparaît pas », « j'ai payé et je n'ai pas
  | accès ». Personne ne pouvait le vérifier — `enrollments` n'avait aucun
  | lecteur côté administration.
  |
  | La PROGRESSION est affichée, jamais modifiable : elle se calcule sur les
  | leçons réellement terminées. Un champ modifiable la ferait mentir dès la
  | leçon suivante — et une progression forcée à 100 % déclencherait un
  | certificat que rien n'adosse.
--}}
<div>

<x-premium.entete titre="Enrôlement aux cours" :fil="['Enrôlement aux cours']">
        Qui suit quoi, et où il en est. Une inscription donnée ici ne débite aucune unité — elle est marquée comme telle pour qu'un accès offert reste distinguable d'un accès acheté.
        <x-slot:actions>
            @if ($peutInscrire)
            <button type="button" wire:click="ouvrirInscription" class="st-btn st-btn-primary">
                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">how_to_reg</span>
                Donner un accès
            </button>
        @endif
        </x-slot:actions>
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Inscriptions" trend="Tous cours confondus" />

        <x-kpi variant="staff" :value="number_format($indicateurs['en_cours'], 0, ',', ' ')"
               label="En cours" trend="Parcours commencés" />

        <x-kpi variant="staff" :value="number_format($indicateurs['termines'], 0, ',', ' ')"
               label="Terminés" trend="Parcours achevés" />

        <x-kpi variant="staff" :value="number_format($indicateurs['offerts'], 0, ',', ' ')"
               label="Accès offerts" trend="Cours payant, zéro unité débitée" />
    </x-kpi-grid>

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="ffield flex-1 mb-0">
            <input id="ec-q" type="search" wire:model.live.debounce.350ms="recherche" placeholder=" ">
            <label for="ec-q">Rechercher un inscrit</label>
        </div>

        <select wire:model.live="filtreCours" class="st-input w-auto" aria-label="Filtrer par cours">
            <option value="">Cours : tous</option>
            @foreach ($cours as $unCours)
                <option value="{{ $unCours->uuid }}">{{ Str::limit($unCours->title, 40) }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtreStatut" class="st-input w-auto" aria-label="Filtrer par statut">
            <option value="">Statut : tous</option>
            @foreach ($statuts as $unStatut)
                <option value="{{ $unStatut->value }}">{{ $unStatut->libelle() }}</option>
            @endforeach
        </select>
    </section>

    @if ($inscriptions->isEmpty())
        <x-empty-state variant="staff" icon="how_to_reg"
                       title="Aucune inscription"
                       description="Aucune inscription ne correspond à ces critères.">
            <x-slot:action>
                <button type="button" wire:click="reinitialiser" class="st-btn st-btn-secondary">
                    Réinitialiser les filtres
                </button>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-data-table variant="staff" :count="$inscriptions->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Inscrit</th>
                <th scope="col">Cours</th>
                <th scope="col">Progression</th>
                <th scope="col">Payé</th>
                <th scope="col">Statut</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($inscriptions as $inscription)
                <tr>
                    <td>
                        <p class="font-semibold text-[#0F172A] mb-0">{{ $inscription->user?->name ?? 'Compte supprimé' }}</p>
                        <p class="text-xs text-[#64748B] mb-0">{{ $inscription->user?->email }}</p>
                    </td>
                    <td class="text-sm text-[#334155]">{{ Str::limit($inscription->course?->title ?? '—', 32) }}</td>
                    <td class="whitespace-nowrap">
                        {{-- Affichée, jamais modifiable : elle se calcule sur les leçons
                             réellement terminées. --}}
                        <span class="text-sm text-[#334155]">{{ $inscription->progress }} %</span>
                    </td>
                    <td class="whitespace-nowrap">
                        @if ($inscription->paid_units > 0)
                            <span class="text-sm text-[#334155]">{{ $inscription->paid_units }} u.</span>
                        @elseif (($inscription->course?->cost_units ?? 0) > 0)
                            {{-- Cours payant, zéro unité : c'est un accès DONNÉ, et
                                 c'est le chiffre qu'on cherche quand les recettes ne
                                 tombent pas juste. --}}
                            <span class="st-badge st-badge-warn">Offert</span>
                        @else
                            <span class="st-badge st-badge-pub">Gratuit</span>
                        @endif
                    </td>
                    <td>
                        <span class="st-badge st-badge-info">{{ $inscription->status?->libelle() ?? $inscription->status }}</span>
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @if ($peutRetirer)
                            <button type="button" wire:click="demanderLeRetrait('{{ $inscription->uuid }}')"
                                    class="st-icnbtn text-[#DC2626]" title="Retirer l'inscription">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">person_remove</span>
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $inscriptions->links() }}</div>
    @endif

    @if ($peutInscrire)
        <x-offcanvas-form :open="$formulaireOuvert"
                          title="Donner un accès à un cours"
                          close="fermerLeTiroir">
            <form wire:submit="inscrire" class="space-y-4">
                <p class="text-sm text-[#334155] mb-0">
                    Ce geste <span class="font-semibold">contourne le paiement</span> : aucune unité ne sera débitée,
                    et l'inscription sera marquée « offerte ».
                </p>

                <div class="ffield">
                    <input id="ec-compte" type="text" wire:model.live.debounce.500ms="compte" placeholder=" ">
                    <label for="ec-compte">Adresse e-mail ou identifiant</label>
                    @error('compte') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                @if ($compte !== '')
                    @if ($compteTrouve !== null)
                        @php($cible = App\Models\User::query()->where('uuid', $compteTrouve)->first())
                        @if ($cible !== null)
                            <div class="rounded-lg border border-[#E2E8F0] bg-[#D1FAE5] p-3">
                                <p class="text-sm font-semibold text-[#065F46] mb-0">{{ $cible->name }}</p>
                                <p class="text-xs text-[#065F46] mb-0">{{ $cible->email }}</p>
                            </div>
                        @endif
                    @else
                        <div class="rounded-lg border border-[#E2E8F0] bg-[#FEE2E2] p-3">
                            <p class="text-sm text-[#DC2626] mb-0">Aucun compte ne correspond à cette saisie.</p>
                        </div>
                    @endif
                @endif

                <div>
                    <label for="ec-cours" class="block text-sm font-medium text-[#0F172A] mb-1">Cours</label>
                    <select id="ec-cours" wire:model="coursUuid" class="st-input w-full">
                        <option value="">— Choisir —</option>
                        @foreach ($cours as $unCours)
                            <option value="{{ $unCours->uuid }}">
                                {{ $unCours->title }}{{ $unCours->cost_units > 0 ? ' ('.$unCours->cost_units.' u.)' : ' (gratuit)' }}
                            </option>
                        @endforeach
                    </select>
                    @error('coursUuid') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="ffield">
                    <textarea id="ec-motif" wire:model="motif" rows="3" placeholder=" "></textarea>
                    <label for="ec-motif">Justification</label>
                    <p class="text-xs text-[#64748B] mt-1 mb-0">
                        Obligatoire : sans elle, un accès offert ressemble à un accès acheté dans six mois.
                    </p>
                    @error('motif') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="flex gap-2 pt-2">
                    <button type="submit" class="st-btn st-btn-primary flex-1">Donner l'accès</button>
                    <button type="button" wire:click="fermerLeTiroir" class="st-btn st-btn-secondary">Annuler</button>
                </div>
            </form>
        </x-offcanvas-form>
    @endif

    <x-delete-modal :open="$aRetirer !== null"
                    title="Retirer cette inscription ?"
                    description="La progression et l'historique de l'inscrit seront perdus. AUCUNE unité ne sera recréditée : le retrait et le remboursement sont deux gestes distincts, et le second passe par le journal des unités."
                    confirm="retirer"
                    cancel="annulerLeRetrait" />
</div>
