{{--
  | « Logs Emails » (lot 10) — l'écran qui répond à « le courriel est-il parti ? ».
  |
  | `CLAUDE.md` porte un encadré sur ce que la file fait échouer en silence :
  | sans `queue:work`, le code OTP ne part jamais, la page répond 200, aucune
  | erreur n'est levée. Le symptôme observé — « l'inscription s'arrête à la
  | deuxième étape » — a coûté un lot entier à diagnostiquer, faute de pouvoir
  | répondre à cette question. Cet écran y répond.
  |
  | Lecture seule ENTIÈREMENT : pas de suppression. La purge est le fait de
  | `PurgerDonneesExpirees`, qui applique une durée uniforme — un bouton
  | « vider » permettrait d'effacer la trace d'un envoi contesté.
--}}
<div>

<x-premium.entete titre="Journal des e-mails" :fil="['Journal des e-mails']">
        Ce qui est parti, et ce qui a échoué. Le contenu des messages n'est pas conservé : un code à usage unique ne doit apparaître ni en journal, ni en base.
    </x-premium.entete>

    @if ($indicateurs['echecs_24h'] > 0)
        {{-- L'alerte ne s'affiche que s'il y a matière : un bandeau permanent
             désapprend à le regarder — c'est le défaut de la cloche du lot 2. --}}
        <div class="rounded-lg border border-[#E2E8F0] bg-[#FEE2E2] p-4 mb-6">
            <p class="font-semibold text-[#DC2626] mb-1">
                {{ $indicateurs['echecs_24h'] }} envoi(s) en échec dans les dernières 24 heures.
            </p>
            <p class="text-sm text-[#334155] mb-0">
                Si tous les envois échouent, vérifiez d'abord que <span class="font-mono">queue:work</span>
                tourne : sans worker, les courriels attendent en file sans qu'aucune erreur ne le signale.
                <span class="font-mono">php artisan verifall:diagnostic</span> le dit en une commande.
            </p>
        </div>
    @endif

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['total'], 0, ',', ' ')"
               label="Envois" trend="Depuis l'origine du journal" />

        <x-kpi variant="staff" :value="number_format($indicateurs['envoyes'], 0, ',', ' ')"
               label="Partis" trend="Confirmés par le transport" />

        <x-kpi variant="staff" :value="number_format($indicateurs['echecs'], 0, ',', ' ')"
               label="En échec" trend="Jamais confirmés" />

        <x-kpi variant="staff" :value="number_format($indicateurs['echecs_24h'], 0, ',', ' ')"
               label="Échecs (24 h)" trend="Ce qui plante en ce moment" />
    </x-kpi-grid>

    <section class="mb-4 flex flex-wrap items-center gap-3">
        <div class="ffield flex-1 mb-0">
            <input id="je-q" type="search" wire:model.live.debounce.350ms="recherche" placeholder=" ">
            <label for="je-q">Rechercher un destinataire, un objet</label>
        </div>

        <select wire:model.live="filtreEtat" class="st-input w-auto" aria-label="Filtrer par état">
            <option value="">État : tous</option>
            <option value="sent">Partis</option>
            <option value="failed">En échec</option>
        </select>

        <select wire:model.live="filtreMailable" class="st-input w-auto" aria-label="Filtrer par type">
            <option value="">Type : tous</option>
            @foreach ($types as $type)
                <option value="{{ $type }}">{{ class_basename($type) }}</option>
            @endforeach
        </select>
    </section>

    @if ($courriels->isEmpty())
        <x-empty-state variant="staff" icon="outgoing_mail"
                       title="Aucun envoi journalisé"
                       description="Le journal est vide. Si la plateforme est en service et qu'aucun envoi n'apparaît, c'est que rien ne part : vérifiez le worker de file d'attente.">
            <x-slot:action>
                <button type="button" wire:click="reinitialiser" class="st-btn st-btn-secondary">
                    Réinitialiser les filtres
                </button>
            </x-slot:action>
        </x-empty-state>
    @else
        <x-data-table variant="staff" :count="$courriels->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Date</th>
                <th scope="col">Destinataire</th>
                <th scope="col">Objet</th>
                <th scope="col">Type</th>
                <th scope="col">État</th>
            </x-slot:head>

            @foreach ($courriels as $courriel)
                <tr>
                    <td class="whitespace-nowrap text-sm text-[#64748B]">
                        {{ $courriel->created_at?->translatedFormat('j M Y · H:i') }}
                    </td>
                    <td class="text-sm text-[#334155]">{{ $courriel->recipient }}</td>
                    <td class="text-sm text-[#334155]">{{ Str::limit($courriel->subject, 60) }}</td>
                    <td class="whitespace-nowrap text-xs text-[#64748B]">
                        {{ $courriel->mailable !== null ? class_basename($courriel->mailable) : '—' }}
                    </td>
                    <td>
                        @if ($courriel->aEchoue())
                            <span class="st-badge st-badge-danger">Échec</span>
                            @if ($courriel->error !== null)
                                <span class="block text-xs text-[#64748B] mt-1">{{ Str::limit($courriel->error, 70) }}</span>
                            @endif
                        @else
                            <x-badge-statut variant="staff" statut="publie" label="Parti" />
                        @endif
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <div class="mt-4">{{ $courriels->links() }}</div>
    @endif

    <p class="text-xs text-[#64748B] mt-4 mb-0">
        Journal en lecture seule. Les lignes sont effacées par la purge automatique des données
        expirées, jamais depuis cet écran : une trace qu'on peut supprimer n'en est plus une.
    </p>
</div>
