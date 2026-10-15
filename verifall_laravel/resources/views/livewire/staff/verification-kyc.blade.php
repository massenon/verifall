{{--
  | Vérification KYC — la file d'examen des dossiers d'identité.
  |
  | Cet écran ne savait que lever ou retirer un drapeau : rien n'existait à
  | examiner, et il l'annonçait en tête comme une demi-mesure assumée. Le
  | porteur du projet a tranché le 1er septembre 2026 — construire la chaîne.
  |
  | Deux règles se lisent dans le balisage :
  |
  |   · aucune image n'a de `src` sur un chemin de stockage. Chaque pièce passe
  |     par une URL SIGNÉE valable dix minutes, régénérée à chaque rendu ;
  |   · le drapeau ne se lève plus à la main. Approuver passe par un dossier ;
  |     seul le RETRAIT reste possible sans, parce qu'une vérification obtenue
  |     par fraude doit pouvoir tomber tout de suite.
--}}
<div class="p-4 sm:p-6 lg:p-8">

    <div class="mb-5">
        <h1 class="text-xl font-display font-bold text-[#0F172A] mb-1">Vérification d'identité</h1>
        <p class="text-sm text-[#64748B] mb-0">
            Les pièces sont détruites {{ $retentionJours }} jours après la décision. Ne subsiste
            alors que la preuve que le contrôle a eu lieu — type de pièce, décision, motif,
            examinateur.
        </p>
    </div>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['en_attente'], 0, ',', ' ')"
               label="En attente d'examen" />
        <x-kpi variant="staff" :value="number_format($indicateurs['approuves'], 0, ',', ' ')"
               label="Approuvés" />
        <x-kpi variant="staff" :value="number_format($indicateurs['refuses'], 0, ',', ' ')"
               label="Refusés" />
        <x-kpi variant="staff" :value="number_format($indicateurs['purges'], 0, ',', ' ')"
               label="Pièces détruites" />
    </x-kpi-grid>

    <div class="flex flex-wrap items-center gap-3 my-5">
        <input type="search" wire:model.live.debounce.350ms="recherche"
               class="st-input" style="max-width: 320px;"
               placeholder="Nom ou adresse e-mail…" aria-label="Rechercher une personne">

        <select wire:model.live="filtreEtat" class="st-select" style="max-width: 220px;" aria-label="Filtrer par état">
            <option value="pending">En attente</option>
            <option value="approved">Approuvés</option>
            <option value="rejected">Refusés</option>
            <option value="">Tous</option>
        </select>
    </div>

    @if ($dossiers->total() === 0)
        <x-empty-state variant="staff" icon="badge"
                       title="Aucun dossier"
                       description="Les dossiers déposés depuis l'espace citoyen apparaîtront ici." />
    @else
        <x-data-table variant="staff" :count="$dossiers->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Personne</th>
                <th scope="col">Pièce</th>
                <th scope="col">Déposé</th>
                <th scope="col">État</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($dossiers as $dossier)
                <tr wire:key="kyc-{{ $dossier->uuid }}">
                    <td>
                        <p class="font-semibold text-[#0F172A] mb-0">{{ $dossier->citoyen?->name ?? 'Compte supprimé' }}</p>
                        <p class="text-xs text-[#64748B] mb-0">{{ $dossier->citoyen?->email }}</p>
                    </td>
                    <td class="text-sm text-[#64748B]">{{ $dossier->document_type->libelle() }}</td>
                    <td class="whitespace-nowrap text-sm text-[#64748B]">
                        {{ $dossier->created_at?->translatedFormat('j M Y') }}
                    </td>
                    <td>
                        <span class="{{ $dossier->status->pastille() }}">{{ $dossier->status->libelle() }}</span>
                        @if ($dossier->purged_at !== null)
                            <span class="block text-xs text-[#64748B] mt-1">Pièces détruites</span>
                        @endif
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @if ($peutExaminer && $dossier->status->estOuvert())
                            <button type="button" wire:click="ouvrirLaDecision('{{ $dossier->uuid }}', 'approuver')"
                                    class="st-icnbtn" title="Examiner ce dossier">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">plagiarism</span>
                            </button>
                        @elseif ($peutExaminer && $dossier->citoyen?->kyc_verified_at !== null)
                            <button type="button" wire:click="ouvrirLaDecision('{{ $dossier->uuid }}', 'retirer')"
                                    class="st-icnbtn text-[#DC2626]" title="Retirer la vérification">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">gpp_bad</span>
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $dossiers->links() }}</div>
    @endif

    @if ($peutExaminer)
        <x-offcanvas-form :open="$dossierVise !== null"
                          :title="$geste === 'retirer' ? 'Retirer la vérification' : 'Examiner le dossier'"
                          close="fermerLaDecision">
            @if ($vise !== null)
                <div class="space-y-4">
                    <div class="st-card p-3">
                        <p class="font-semibold text-[#0F172A] mb-0">{{ $vise->citoyen?->name ?? 'Compte supprimé' }}</p>
                        <p class="text-xs text-[#64748B] mb-0">{{ $vise->citoyen?->email }}</p>
                        <p class="text-xs text-[#64748B] mb-0 mt-1">{{ $vise->document_type->libelle() }}</p>
                    </div>

                    {{-- ── LES PIÈCES ────────────────────────────────────────
                         Ouvertes dans un nouvel onglet par un lien SIGNÉ, jamais
                         intégrées avec un `src` sur un chemin de stockage. --}}
                    @if ($vise->piecesDisponibles())
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-[#64748B] mb-2">Pièces déposées</p>
                            <div class="flex flex-wrap gap-2">
                                @foreach ($vise->pieces() as $role => $chemin)
                                    <a href="{{ $this->lienDeLaPiece($vise, $role) }}"
                                       target="_blank" rel="noopener noreferrer"
                                       class="st-btn st-btn-secondary">
                                        <span class="material-symbols-rounded !text-[18px]" aria-hidden="true">image</span>
                                        {{ ucfirst($role) }}
                                    </a>
                                @endforeach
                            </div>
                            <p class="text-xs text-[#64748B] mt-2 mb-0">
                                Ces liens expirent au bout de dix minutes et ne se partagent pas :
                                ils exigent votre session et l'habilitation « kyc.review ».
                            </p>
                        </div>
                    @else
                        <p class="text-sm text-[#64748B] mb-0">
                            Les pièces de ce dossier ont été détruites {{ $retentionJours }} jours
                            après la décision. Il n'est plus examinable sur pièces.
                        </p>
                    @endif

                    {{-- Pas de `<form wire:submit>` ici : chaque bouton porte SON
                         action, avec le geste en argument.

                         La forme précédente — trois `type="submit"` plus un
                         `wire:click="$set('geste', …)"` — faisait se marcher
                         dessus deux mécanismes Livewire : le `$set` partait, la
                         soumission n'arrivait jamais, et RIEN ne se passait sans
                         la moindre erreur. Trouvé au navigateur. --}}
                    <div class="space-y-4">
                        <div class="ffield">
                            <textarea id="kyc-motif" wire:model="motif" rows="4" placeholder=" "></textarea>
                            <label for="kyc-motif">
                                {{ $geste === 'approuver' ? 'Note interne (facultative)' : 'Motif — montré à la personne' }}
                            </label>
                            <p class="text-xs text-[#64748B] mt-1 mb-0">
                                @if ($geste === 'approuver')
                                    Rien n'est transmis à la personne en cas d'approbation.
                                @else
                                    Écrivez ce qu'il faut corriger : « verso illisible » évite un
                                    second dépôt identique, « refusé » n'apprend rien.
                                @endif
                            </p>
                        </div>

                        <div class="flex flex-wrap gap-2 pt-2">
                            @if ($geste !== 'retirer')
                                <button type="button" wire:click="statuer('approuver')"
                                        wire:loading.attr="disabled"
                                        class="st-btn st-btn-primary flex-1"
                                        @disabled(! $vise->status->estOuvert())>Approuver</button>
                                <button type="button" wire:click="statuer('refuser')"
                                        wire:loading.attr="disabled"
                                        class="st-btn st-btn-danger flex-1"
                                        @disabled(! $vise->status->estOuvert())>Refuser</button>
                            @else
                                <button type="button" wire:click="statuer('retirer')"
                                        wire:loading.attr="disabled"
                                        class="st-btn st-btn-danger flex-1">Retirer la vérification</button>
                            @endif
                            <button type="button" wire:click="fermerLaDecision" class="st-btn st-btn-secondary">Annuler</button>
                        </div>
                    </div>
                </div>
            @endif
        </x-offcanvas-form>
    @endif
</div>
