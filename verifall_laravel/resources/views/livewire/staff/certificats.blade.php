{{--
  | « Certificats » (lot 6).
  |
  | AUCUNE SUPPRESSION, et c'est tout le dispositif. Chaque certificat porte un
  | code que n'importe qui contrôle sur /verifier-un-certificat. Effacer une
  | ligne rendrait ce contrôle NÉGATIF : le porteur d'un certificat authentique
  | passerait pour un fraudeur, sans recours et sans trace.
  |
  | La révocation est la réponse — motivée, datée, et visible sur la page
  | publique. Les CGU §7 s'y engagent par écrit.
--}}
<div>

<x-premium.entete titre="Certificats" :fil="['Certificats']">
        Les attestations délivrées à l'achèvement d'un parcours. Un certificat ne s'efface pas : il se révoque, avec un motif que la page publique de contrôle affiche.
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Délivrés" trend="Depuis l'origine" />

        <x-kpi variant="staff" :value="number_format($indicateurs['valides'], 0, ',', ' ')"
               label="Valides" trend="Contrôle positif" />

        <x-kpi variant="staff" :value="number_format($indicateurs['revoques'], 0, ',', ' ')"
               label="Révoqués" trend="Motif visible publiquement" />

        <x-kpi variant="staff" :value="number_format($indicateurs['cours'], 0, ',', ' ')"
               label="Cours concernés" trend="Ayant délivré au moins un certificat" />
    </x-kpi-grid>

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="ffield flex-1 mb-0">
            <input id="cert-q" type="search" wire:model.live.debounce.350ms="recherche" placeholder=" ">
            <label for="cert-q">Rechercher un code, un titulaire, un cours</label>
        </div>

        <select wire:model.live="filtreCours" class="st-input w-auto" aria-label="Filtrer par cours">
            <option value="">Cours : tous</option>
            @foreach ($cours as $unCours)
                <option value="{{ $unCours->uuid }}">{{ Str::limit($unCours->title, 40) }}</option>
            @endforeach
        </select>

        <select wire:model.live="filtreEtat" class="st-input w-auto" aria-label="Filtrer par état">
            <option value="">État : tous</option>
            <option value="valides">Valides</option>
            <option value="revoques">Révoqués</option>
        </select>
    </section>

    @if ($certificats->isEmpty())
        <x-empty-state variant="staff" icon="workspace_premium"
                       title="Aucun certificat"
                       description="Les certificats sont délivrés automatiquement à l'achèvement réel d'un parcours. Aucun ne se crée à la main : ce serait une attestation que rien n'adosse.">
            <x-slot:action>
                <button type="button" wire:click="reinitialiser" class="st-btn st-btn-secondary">
                    Réinitialiser les filtres
                </button>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-data-table variant="staff" :count="$certificats->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Code</th>
                <th scope="col">Titulaire</th>
                <th scope="col">Cours</th>
                <th scope="col">Délivré le</th>
                <th scope="col">État</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($certificats as $certificat)
                <tr>
                    <td class="whitespace-nowrap">
                        <span class="font-mono text-sm text-[#0F172A]">{{ $certificat->code }}</span>
                    </td>
                    <td>
                        {{-- `holder_name` est FIGÉ à la délivrance : le certificat
                             atteste d'un nom au moment où il a été gagné. Le compte
                             peut avoir changé de nom depuis, ou disparu. --}}
                        <p class="font-semibold text-[#0F172A] mb-0">{{ $certificat->holder_name }}</p>
                        @if ($certificat->user !== null)
                            <p class="text-xs text-[#64748B] mb-0">{{ $certificat->user->email }}</p>
                        @else
                            <p class="text-xs text-[#64748B] mb-0">Compte supprimé</p>
                        @endif
                    </td>
                    <td class="text-sm text-[#334155]">{{ Str::limit($certificat->course_title, 40) }}</td>
                    <td class="whitespace-nowrap text-sm text-[#64748B]">
                        {{ $certificat->issued_at?->translatedFormat('j M Y') }}
                    </td>
                    <td>
                        @if ($certificat->revoked_at === null)
                            <x-badge-statut variant="staff" statut="publie" label="Valide" />
                        @else
                            <span class="st-badge st-badge-danger">Révoqué</span>
                            <span class="block text-xs text-[#64748B] mt-1">{{ Str::limit($certificat->revoked_reason, 60) }}</span>
                        @endif
                    </td>
                    <td class="text-right whitespace-nowrap">
                        <a href="{{ route('public.verify-certificate', ['code' => $certificat->code]) }}"
                           target="_blank" rel="noopener"
                           class="st-icnbtn" title="Voir la page publique de contrôle">
                            <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">open_in_new</span>
                        </a>
                        @can('revoke', $certificat)
                            @if ($certificat->revoked_at === null)
                                <button type="button" wire:click="demanderLaRevocation('{{ $certificat->uuid }}')"
                                        class="st-icnbtn text-[#DC2626]" title="Révoquer">
                                    <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">block</span>
                                </button>
                            @else
                                {{-- Une révocation se défait : une erreur sur un document
                                     opposable doit pouvoir être réparée, et tracée. --}}
                                <button type="button" wire:click="retablir('{{ $certificat->uuid }}')"
                                        class="st-icnbtn" title="Rétablir">
                                    <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">restore</span>
                                </button>
                            @endif
                        @endcan
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $certificats->links() }}</div>
    @endif

    {{-- La révocation demande un motif : un tiroir, pas une simple confirmation,
         parce qu'il y a quelque chose à écrire — et que ce texte sera public. --}}
    <x-offcanvas-form :open="$aRevoquer !== null"
                      title="Révoquer ce certificat"
                      close="annulerLaRevocation">
        <form wire:submit="revoquer" class="space-y-4">
            @if ($certificatVise !== null)
                <div class="st-card p-3">
                    <p class="font-mono text-sm text-[#0F172A] mb-1">{{ $certificatVise->code }}</p>
                    <p class="text-sm text-[#334155] mb-0">{{ $certificatVise->holder_name }}</p>
                    <p class="text-xs text-[#64748B] mb-0">{{ $certificatVise->course_title }}</p>
                </div>
            @endif

            <div class="ffield">
                <textarea id="cert-motif" wire:model="motifDeRevocation" rows="3" placeholder=" "></textarea>
                <label for="cert-motif">Motif de la révocation</label>
                <p class="text-xs text-[#64748B] mt-1 mb-0">
                    <span class="font-semibold">Ce texte sera public :</span> il s'affiche sur la page de contrôle,
                    que consulte quiconque vérifie ce certificat. Un employeur le lira.
                </p>
                @error('motifDeRevocation') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="st-btn st-btn-danger flex-1">Révoquer</button>
                <button type="button" wire:click="annulerLaRevocation" class="st-btn st-btn-secondary">Annuler</button>
            </div>
        </form>
    </x-offcanvas-form>
</div>
