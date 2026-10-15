{{--
  | « Tâches Cron » (lot 10) — le planificateur tourne-t-il vraiment ?
  |
  | `verifall:diagnostic` compte les tâches DÉCLARÉES et admet dans son propre
  | commentaire que « rien n'atteste qu'un cron l'appelle ». Six tâches en
  | dépendent — dotation mensuelle, purge RGPD, réconciliation des paiements.
  | Un cron absent ne casse rien : il ne fait rien, et personne ne le sait.
  |
  | L'écran croise le DÉCLARÉ (le planificateur) et le RÉEL
  | (`scheduled_task_runs`). Une tâche sans aucune exécution enregistrée est
  | affichée en rouge — c'est le diagnostic que personne ne pouvait poser.
--}}
<div class="p-6 lg:p-8">

    <nav class="text-xs text-[#64748B] mb-2" aria-label="Fil d'Ariane">
        <a href="{{ route('staff.dashboard') }}" class="hover:text-[#065F46]">Administration</a>
        <span aria-hidden="true">/</span>
        <span class="text-[#0F172A] font-medium">Tâches planifiées</span>
    </nav>

    <header class="mb-6">
        <h1 class="text-2xl md:text-3xl font-display font-bold text-[#0F172A] leading-tight mb-1">Tâches planifiées</h1>
        <p class="text-[#64748B] text-sm mb-0">
            Ce que le code déclare, et ce qui s'est réellement exécuté.
            Les deux colonnes ne disent pas la même chose — et c'est tout l'intérêt.
        </p>
    </header>

    @if ($indicateurs['declarees'] > 0 && $indicateurs['jamais_executees'] === $indicateurs['declarees'])
        {{-- LE DIAGNOSTIC QUE PERSONNE NE POUVAIT POSER. Si AUCUNE tâche ne
             s'est jamais exécutée, il n'y a pas six pannes : il n'y a pas de
             cron. On le dit, plutôt que de laisser compter les lignes rouges. --}}
        <div class="rounded-lg border border-[#E2E8F0] bg-[#FEE2E2] p-4 mb-6">
            <p class="font-semibold text-[#DC2626] mb-1">Aucune tâche ne s'est jamais exécutée.</p>
            <p class="text-sm text-[#334155] mb-0">
                Ce n'est pas {{ $indicateurs['declarees'] }} pannes : c'est l'entrée cron qui manque.
                Le planificateur exige une ligne appelant
                <span class="font-mono">php artisan schedule:run</span> chaque minute.
                Tant qu'elle n'existe pas, la dotation mensuelle d'unités, la purge RGPD et la
                réconciliation des paiements ne tournent pas — sans qu'aucune erreur ne le signale.
                Voir <span class="font-mono">docs/CRONS.md</span>.
            </p>
        </div>
    @endif

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['declarees'], 0, ',', ' ')"
               label="Tâches déclarées" trend="Dans routes/console.php" />

        <x-kpi variant="staff" :value="number_format($indicateurs['jamais_executees'], 0, ',', ' ')"
               label="Jamais exécutées" trend="Aucune trace enregistrée" />

        <x-kpi variant="staff" :value="number_format($indicateurs['en_echec'], 0, ',', ' ')"
               label="En échec (7 j)" trend="Au moins un échec récent" />

        {{-- `null` tant que rien ne s'est exécuté : « il y a 0 minute » se
             lirait comme « à l'instant », soit l'inverse de la vérité. --}}
        <x-kpi variant="staff" :value="$indicateurs['derniere_activite']?->diffForHumans() ?? '—'"
               label="Dernière activité" trend="Toutes tâches confondues" />
    </x-kpi-grid>

    @if ($taches === [])
        <x-empty-state variant="staff" icon="schedule"
                       title="Aucune tâche déclarée"
                       description="Le planificateur est vide. C'est un état correct si le produit n'a rien à faire périodiquement — ce n'est pas le cas ici, ce qui mérite un coup d'œil à routes/console.php." />
    @else
        <x-data-table variant="staff" :count="count($taches)" :selectable="false">
            <x-slot:head>
                <th scope="col">Tâche</th>
                <th scope="col">Fréquence</th>
                <th scope="col">Dernière exécution</th>
                <th scope="col">Durée</th>
                <th scope="col">État</th>
            </x-slot:head>

            @foreach ($taches as $tache)
                <tr>
                    <td>
                        <p class="font-semibold text-[#0F172A] mb-0">
                            {{ $tache['description'] !== '' ? $tache['description'] : class_basename($tache['nom']) }}
                        </p>
                        <p class="text-xs text-[#64748B] mb-0 font-mono">{{ Str::limit($tache['nom'], 70) }}</p>
                    </td>
                    <td class="whitespace-nowrap">
                        <span class="font-mono text-xs text-[#334155]">{{ $tache['expression'] }}</span>
                    </td>
                    <td class="whitespace-nowrap text-sm">
                        @if ($tache['derniere'] === null)
                            <span class="text-[#DC2626] font-semibold">Jamais</span>
                        @else
                            <span class="text-[#334155]">{{ $tache['derniere']->ran_at?->translatedFormat('j M · H:i') }}</span>
                            <span class="block text-xs text-[#64748B]">{{ $tache['derniere']->ran_at?->diffForHumans() }}</span>
                        @endif
                    </td>
                    <td class="whitespace-nowrap text-sm text-[#64748B]">
                        @if ($tache['derniere']?->duration_ms !== null)
                            {{ number_format($tache['derniere']->duration_ms / 1000, 1, ',', ' ') }} s
                        @else
                            —
                        @endif
                    </td>
                    <td>
                        @if ($tache['derniere'] === null)
                            <span class="st-badge st-badge-danger">Sans trace</span>
                        @elseif ($tache['echecs'] > 0)
                            <span class="st-badge st-badge-warn">{{ $tache['echecs'] }} échec(s) sur 7 j</span>
                        @else
                            <x-badge-statut variant="staff" statut="publie" label="Régulière" />
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>
    @endif

    <p class="text-xs text-[#64748B] mt-4 mb-0">
        Pas de bouton « exécuter » : la purge RGPD et la dotation mensuelle ne sont pas
        idempotentes, et un double-clic doublerait la dotation de tous les comptes.
        Ces commandes se lancent en console.
    </p>
</div>
