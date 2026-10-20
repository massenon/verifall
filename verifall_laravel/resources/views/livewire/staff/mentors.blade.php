{{--
  | « Mentors » (lot 7).
  |
  | Ce que « Utilisateurs » ne montre pas : CE QUE CHAQUE MENTOR PORTE. Combien
  | de cours, combien publiés, combien de copies l'attendent. Ce sont les seules
  | questions qu'on se pose sur un mentor — « à qui confier cette correction ? »,
  | « qui est débordé ? » — et aucune ne se répond depuis un annuaire de comptes.
  |
  | Aucune création ici : elle reste sur « Utilisateurs », qui porte les trois
  | garde-fous du lot 3. Les dupliquer les ferait diverger au premier correctif.
--}}
<div>

<x-premium.entete titre="Mentors" :fil="['Mentors']">
        L'équipe pédagogique et sa charge réelle. Pour créer un compte ou donner le rôle, passez par <a href="{{ route('staff.users') }}" class="text-[#059669] underline">Utilisateurs</a>.
    </x-premium.entete>

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Mentors" trend="Rôle actif" />

        <x-kpi variant="staff" :value="number_format($indicateurs['avec_cours'], 0, ',', ' ')"
               label="Avec cours" trend="Portent au moins un cours" />

        {{-- Un accès au back-office qui ne sert à rien est une surface
             d'attaque de plus : le compteur existe pour cela. --}}
        <x-kpi variant="staff" :value="number_format($indicateurs['sans_cours'], 0, ',', ' ')"
               label="Sans cours" trend="Accès sans usage" />

        <x-kpi variant="staff" :value="number_format($indicateurs['copies_en_attente'], 0, ',', ' ')"
               label="Copies assignées" trend="En attente de correction" />
    </x-kpi-grid>

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="ffield flex-1 mb-0">
            <input id="mt-q" type="search" wire:model.live.debounce.350ms="recherche" placeholder=" ">
            <label for="mt-q">Rechercher un mentor</label>
        </div>

        <select wire:model.live="filtreEtat" class="st-input w-auto" aria-label="Filtrer">
            <option value="">Tous</option>
            <option value="actifs">Avec cours</option>
            <option value="sans-cours">Sans cours</option>
        </select>
    </section>

    @if ($mentors->isEmpty())
        <x-empty-state variant="staff" icon="co_present"
                       title="Aucun mentor"
                       description="Personne ne porte le rôle Mentor. Tant qu'il n'y en a pas, les cours restent sans formateur et les copies sans correcteur.">
            <x-slot:action>
                <a href="{{ route('staff.users') }}" class="st-btn st-btn-primary">Aller aux utilisateurs</a>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-data-table variant="staff" :count="$mentors->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Mentor</th>
                <th scope="col">Cours portés</th>
                <th scope="col">Publiés</th>
                <th scope="col">Compte</th>
                <th class="text-right">Actions</th>
            </x-slot:head>

            @foreach ($mentors as $mentor)
                <tr>
                    <td>
                        <p class="font-semibold text-[#0F172A] mb-0"><a href="{{ route('staff.users.show', $mentor) }}" class="hover:underline">{{ $mentor->name }}</a></p>
                        <p class="text-xs text-[#64748B] mb-0">{{ $mentor->email }}</p>
                    </td>
                    <td class="whitespace-nowrap">
                        @if ($mentor->cours_enseignes_count === 0)
                            <span class="text-[#DC2626] text-sm">Aucun</span>
                        @else
                            <span class="text-sm text-[#334155]">{{ $mentor->cours_enseignes_count }}</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-sm text-[#64748B]">{{ $mentor->cours_publies_count }}</td>
                    <td>
                        @if ($mentor->estActive())
                            <x-badge-statut variant="staff" statut="publie" label="Actif" />
                        @else
                            {{-- Un compte dont l'adresse n'a jamais été validée ne peut
                                 rien faire : le signaler évite de chercher pourquoi ce
                                 mentor « ne voit pas » ses cours. --}}
                            <span class="st-badge st-badge-warn">Adresse non validée</span>
                        @endif
                    </td>
                    <td class="text-right whitespace-nowrap">
                        @if ($peutRetrograder)
                            <button type="button" wire:click="demanderLaRetrogradation('{{ $mentor->uuid }}')"
                                    class="st-icnbtn text-[#DC2626]" title="Retirer le rôle Mentor">
                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">person_remove</span>
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $mentors->links() }}</div>
    @endif

    <x-delete-modal :open="$aRetrograder !== null"
                    title="Retirer le rôle Mentor ?"
                    :description="$mentorVise !== null
                        ? ($copiesEnAttente > 0
                            ? $mentorVise->name.' a '.$copiesEnAttente.' copie(s) en attente de correction : elles n\'auront plus de correcteur, et rien ne le signalera ailleurs. Le compte n\'est pas supprimé — seul l\'accès au back-office pédagogique est retiré.'
                            : $mentorVise->name.' ne porte aucune copie en attente. Le compte n\'est pas supprimé — seul l\'accès au back-office pédagogique est retiré.')
                        : ''"
                    confirm="retrograder"
                    cancel="annulerLaRetrogradation" />
</div>
