{{--
  | Mode maintenance — console premium (prototype-premium, écran `maintenance`).
  |
  | Deux gestes irréversibles à leur manière : fermer coupe le service à tout le
  | monde, rouvrir envoie un courriel à chaque personne qui attendait. Les deux
  | passent par une confirmation qui annonce ce qui va se produire, avec des
  | chiffres.
  |
  | L'aperçu de droite est une MINIATURE dessinée ici, pas une iframe : la page
  | publique part avec `X-Frame-Options: DENY`, et c'est très bien ainsi.
--}}
@php
  $motifs = \App\Livewire\Staff\Maintenance::MOTIFS;
  $durees = \App\Livewire\Staff\Maintenance::DUREES;
  $membre = auth('staff')->user();
  $peutVoirSauvegardes = \App\Support\Rbac\MenuDuBackOffice::autorise($membre, 'staff.backups');
  $peutVoirCron = \App\Support\Rbac\MenuDuBackOffice::autorise($membre, 'staff.cron');

  // La part du temps annoncé déjà écoulée. Ce n'est PAS l'avancement des
  // travaux — la jauge le dit en toutes lettres.
  $ecoule = null;
  if ($actif && $debut && $finReelle && $finReelle->greaterThan($debut)) {
      $ecoule = (int) round(min(100, max(0, $debut->diffInSeconds(now()) / $debut->diffInSeconds($finReelle) * 100)));
  }

  // Ce que la miniature annonce : l'heure de retour, et la part du temps
  // écoulée que dessine l'anneau de la boussole, comme sur la vraie page.
  $finApercu = $actif ? ($enRetard ? null : $finReelle) : ($finAnnoncee !== '' ? \Carbon\CarbonImmutable::parse($finAnnoncee) : null);
  $restant = $finApercu === null ? null : ['heure' => $finApercu->format('H:i'), 'part' => $actif ? ($ecoule ?? 0) : 0];

  $duree = fn (?int $minutes): string => $minutes === null ? '—'
      : ($minutes < 60 ? $minutes.' min' : intdiv($minutes, 60).' h'.($minutes % 60 ? ' '.str_pad((string) ($minutes % 60), 2, '0', STR_PAD_LEFT) : ''));

  $passages = [
      ['user-check', 'Le personnel connecté', 'Administration et espace mentor, sans restriction.', true],
      ['log-in', 'La connexion à la console', 'Mot de passe, double authentification et écran de verrouillage.', true],
      ['webhook', 'Les rappels Mobile Money', 'Signés, ils créditent les unités déjà payées.', true],
      ['activity', 'La sonde de disponibilité', '/up répond toujours aux outils de supervision.', true],
      ['timer', 'Les tâches planifiées et la file', 'Elles continuent de tourner : suspendez-les si la mise à jour l’exige.', true],
      ['globe', 'Le public et les citoyens', 'Page de maintenance en 503, avec Retry-After quand une heure est annoncée.', false],
      ['smartphone', 'L’application mobile (API)', 'Réponse JSON 503 : l’application affiche son propre message.', false],
  ];
@endphp
<div class="space-y-6">

  {{-- En-tête --}}
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <p class="text-sm texte-2">Système</p>
      <h1 class="text-[28px] sm:text-[32px] font-semibold leading-tight text-encre dark:text-white">Mode maintenance</h1>
      <p class="mt-1 text-sm texte-2 max-w-2xl">
        Ferme la plateforme au public pendant une mise à jour importante. L’équipe connectée garde
        tout son accès — sans quoi personne ne pourrait rouvrir depuis ici.
      </p>
    </div>
    <a href="{{ route('maintenance') }}" target="_blank" rel="noopener" class="btn-second">
      <x-lucide name="external-link" />Voir la page publique
    </a>
  </div>

  {{-- L'état, en grand : c'est la seule chose à savoir en arrivant ici. --}}
  @if ($actif)
    <section class="carte overflow-hidden !border-amber-300 dark:!border-amber-900/70" aria-labelledby="t-etat">
      <div class="p-5 sm:p-6 bg-gradient-to-br from-amber-50 to-white dark:from-amber-950/40 dark:to-nuit-carte">
        <div class="flex flex-wrap items-start gap-4">
          <span class="relative inline-flex w-12 h-12 shrink-0 items-center justify-center rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-200" aria-hidden="true">
            <x-lucide name="construction" class="w-6 h-6" />
            <span class="absolute -top-0.5 -right-0.5 flex w-3 h-3"><span class="absolute inset-0 rounded-full bg-amber-500 animate-ping opacity-60"></span><span class="relative w-3 h-3 rounded-full bg-amber-500 ring-2 ring-white dark:ring-nuit-carte"></span></span>
          </span>
          <div class="flex-1 min-w-[220px]">
            <p class="p-alerte mb-1.5"><x-lucide name="lock-keyhole" />Fermé au public</p>
            <h2 id="t-etat" class="font-titre text-xl sm:text-2xl font-semibold leading-snug text-encre dark:text-white">
              Le site est fermé depuis {{ $debut?->locale('fr')->diffForHumans(syntax: \Carbon\CarbonInterface::DIFF_ABSOLUTE) ?? 'quelques instants' }}
            </h2>
            <p class="mt-1 text-sm texte-2">
              @if ($finReelle && ! $enRetard)
                Réouverture annoncée {{ $finReelle->locale('fr')->translatedFormat('l j F à H:i') }} — une promesse, pas une minuterie : rien ne rouvre tout seul.
              @elseif ($enRetard)
                {{-- L'écran le dit à l'équipe aussi : c'est le moment où quelqu'un
                     doit décider de rouvrir ou de repousser. --}}
                <strong class="text-critique">L’heure annoncée est dépassée.</strong> La page publique affiche « l’opération dure plus longtemps que prévu ».
              @else
                Aucune heure de fin n’a été annoncée : la page propose seulement d’être prévenu par courriel.
              @endif
            </p>
          </div>
          <button type="button" wire:click="demander('fermer')" class="btn-primaire !h-10 !px-4">
            <x-lucide name="power" />Rouvrir le site
          </button>
        </div>

        @if ($ecoule !== null)
          <div class="mt-5">
            <div class="flex justify-between text-xs texte-3 mb-1.5">
              <span>Fermé à {{ $debut->format('H:i') }}</span>
              <span>Temps annoncé écoulé : {{ $ecoule }} %</span>
              <span>Annoncé pour {{ $finReelle->format('H:i') }}</span>
            </div>
            <div class="jauge !h-2" role="progressbar" aria-valuenow="{{ $ecoule }}" aria-valuemin="0" aria-valuemax="100" aria-label="Part du temps annoncé déjà écoulée">
              <span class="!bg-amber-500" style="width: {{ $ecoule }}%"></span>
            </div>
          </div>
        @endif
      </div>
      <dl class="grid grid-cols-1 sm:grid-cols-3 divide-y sm:divide-y-0 sm:divide-x divide-trait dark:divide-nuit-trait border-t filet">
        <div class="p-4"><dt class="text-xs texte-3">Personnes à prévenir</dt><dd class="mt-0.5 font-titre text-2xl font-semibold tabular-nums">{{ number_format($enAttente, 0, ',', ' ') }}</dd></div>
        <div class="p-4"><dt class="text-xs texte-3">Déjà prévenues</dt><dd class="mt-0.5 font-titre text-2xl font-semibold tabular-nums">{{ number_format($dejaPrevenus, 0, ',', ' ') }}</dd></div>
        <div class="p-4"><dt class="text-xs texte-3">Code renvoyé au public</dt><dd class="mt-0.5 font-titre text-2xl font-semibold tabular-nums">503</dd></div>
      </dl>
    </section>
  @else
    <section class="carte p-5 sm:p-6" aria-labelledby="t-etat">
      <div class="flex flex-wrap items-center gap-4">
        <span class="relative inline-flex w-12 h-12 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-700 dark:bg-brand/15 dark:text-brand" aria-hidden="true">
          <x-lucide name="radio" class="w-6 h-6" />
        </span>
        <div class="flex-1 min-w-[220px]">
          <p class="p-bon mb-1.5"><x-lucide name="circle-check" />En service</p>
          <h2 id="t-etat" class="font-titre text-xl sm:text-2xl font-semibold leading-snug text-encre dark:text-white">Le site est ouvert à tout le monde</h2>
          <p class="mt-1 text-sm texte-2">
            @if ($historique !== [] && $historique[0]['fin'])
              Dernière maintenance le {{ $historique[0]['debut']->locale('fr')->translatedFormat('j F Y') }}, pendant {{ $duree($historique[0]['duree_minutes']) }}.
            @else
              Aucune maintenance n’a encore été enregistrée.
            @endif
          </p>
        </div>
        <dl class="flex gap-6 sm:text-right">
          <div><dt class="text-xs texte-3">À prévenir</dt><dd class="font-titre text-xl font-semibold tabular-nums">{{ $enAttente }}</dd></div>
          <div><dt class="text-xs texte-3">Déjà prévenues</dt><dd class="font-titre text-xl font-semibold tabular-nums">{{ number_format($dejaPrevenus, 0, ',', ' ') }}</dd></div>
        </dl>
      </div>
    </section>
  @endif

  <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_360px]">
    <div class="space-y-6 min-w-0">

      @if (! $actif)
        {{-- Préparer la fermeture --}}
        <section class="carte" aria-labelledby="t-preparer">
          <div class="carte-tete">
            <h2 id="t-preparer" class="carte-titre">Préparer la fermeture</h2>
            <span class="text-xs texte-3">Rien n’est fermé avant la confirmation</span>
          </div>
          <form wire:submit="demander('ouvrir')" class="carte-corps space-y-5">
            <div>
              <p class="etiquette">Motif</p>
              <div class="flex flex-wrap gap-2">
                @foreach ($motifs as $cle => [$libelle, $icone])
                  <button type="button" wire:click="appliquerMotif('{{ $cle }}')" class="btn-second btn-sm"><x-lucide :name="$icone" />{{ $libelle }}</button>
                @endforeach
              </div>
              <p class="aide">Un clic reprend un texte de départ ; précisez-le ensuite.</p>
            </div>

            <div>
              <div class="flex items-baseline justify-between">
                <label for="mt-message" class="etiquette">Message affiché au public</label>
                <span class="text-xs texte-3 tabular-nums">{{ mb_strlen($message) }} / 500</span>
              </div>
              <textarea id="mt-message" wire:model.live.debounce.300ms="message" rows="3" maxlength="500"
                        class="champ !h-auto py-2.5 @error('message') champ-erreur @enderror"
                        placeholder="Nous mettons Vérif’All à jour…"></textarea>
              @error('message') <p class="aide-erreur"><x-lucide name="circle-alert" class="w-3.5 h-3.5" />{{ $message }}</p> @enderror
              <p class="aide">Dites ce qui se passe et ce qui est préservé, pas « nous améliorons votre expérience ».</p>
            </div>

            <div>
              <label for="mt-fin" class="etiquette">Réouverture annoncée <span class="texte-3 font-normal">(facultative)</span></label>
              <div class="flex flex-wrap items-center gap-2">
                <input id="mt-fin" type="datetime-local" wire:model.live="finAnnoncee"
                       class="champ !w-auto min-w-[220px] @error('finAnnoncee') champ-erreur @enderror">
                <div class="segments" role="group" aria-label="Durées courantes">
                  @foreach ($durees as $minutes)
                    <button type="button" class="segment" wire:click="annoncerDans({{ $minutes }})">+{{ $duree($minutes) }}</button>
                  @endforeach
                </div>
                @if ($finAnnoncee !== '')
                  <button type="button" class="btn-fantome btn-sm" wire:click="$set('finAnnoncee', '')"><x-lucide name="x" />Sans heure</button>
                @endif
              </div>
              @error('finAnnoncee') <p class="aide-erreur"><x-lucide name="circle-alert" class="w-3.5 h-3.5" />{{ $message }}</p> @enderror
              {{-- Dit ici plutôt que découvert : l'heure n'éteint RIEN. --}}
              <p class="aide">
                Une <strong>promesse, pas une minuterie</strong> : elle n’ouvre rien toute seule. Elle alimente le
                décompte public et l’en-tête <code>Retry-After</code> ; dépassée, la page le dit franchement.
              </p>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t filet">
              <p class="text-xs texte-3 max-w-md">Votre session et celle de l’équipe connectée restent ouvertes. Le public reçoit la page de maintenance.</p>
              <button type="submit" class="btn-danger !h-10 !px-4"><x-lucide name="lock-keyhole" />Fermer le site…</button>
            </div>
          </form>
        </section>
      @else
        {{-- Pendant la maintenance --}}
        <section class="carte" aria-labelledby="t-pendant">
          <div class="carte-tete"><h2 id="t-pendant" class="carte-titre">Ce que voit le public</h2></div>
          <div class="carte-corps">
            <blockquote class="rounded-ctrl border filet bg-papier dark:bg-nuit p-4 text-sm leading-relaxed">« {{ $messageAffiche }} »</blockquote>
            <p class="aide">Pour changer le message ou l’heure, rouvrez puis refermez : chaque geste est journalisé séparément.</p>
          </div>
        </section>

        <section class="carte" aria-labelledby="t-avant-rouvrir">
          <div class="carte-tete"><h2 id="t-avant-rouvrir" class="carte-titre">Avant de rouvrir</h2></div>
          <ol class="carte-corps space-y-3 text-sm">
            <li class="flex gap-3"><span class="avatar w-6 h-6 !text-[11px]">1</span><span>Parcourez les écrans touchés par la mise à jour <strong>depuis cette console</strong> : vous voyez la plateforme comme le public la verra.</span></li>
            <li class="flex gap-3"><span class="avatar w-6 h-6 !text-[11px]">2</span><span>Vérifiez que la file tourne @if ($peutVoirCron)(<a class="underline underline-offset-2" href="{{ route('staff.cron') }}">tâches planifiées</a>)@endif : les courriels de réouverture partent par elle.</span></li>
            <li class="flex gap-3"><span class="avatar w-6 h-6 !text-[11px]">3</span><span>Rouvrez : {{ $enAttente > 0 ? $enAttente.' courriel(s) partiront' : 'personne n’attend de courriel' }}. Un courriel parti ne se rappelle pas.</span></li>
          </ol>
        </section>
      @endif

      {{-- Historique --}}
      <section class="carte" aria-labelledby="t-historique">
        <div class="carte-tete">
          <h2 id="t-historique" class="carte-titre">Dernières maintenances</h2>
          <span class="text-xs texte-3">Relues dans le journal d’audit</span>
        </div>
        @if ($historique === [])
          <p class="carte-corps text-sm texte-2">Aucune pour l’instant.</p>
        @else
          <div class="overflow-x-auto">
            <table class="tableau">
              <thead><tr><th>Fermeture</th><th>Durée</th><th>Motif annoncé</th><th>Par</th></tr></thead>
              <tbody>
                @foreach ($historique as $periode)
                  <tr>
                    <td class="whitespace-nowrap tabular-nums">{{ $periode['debut']->locale('fr')->translatedFormat('j M Y, H:i') }}</td>
                    <td class="whitespace-nowrap">@if ($periode['fin']){{ $duree($periode['duree_minutes']) }}@else<span class="p-alerte">En cours</span>@endif</td>
                    <td class="max-w-[360px]"><span class="line-clamp-2 texte-2">{{ $periode['message'] !== '' ? $periode['message'] : 'Message par défaut' }}</span></td>
                    <td class="whitespace-nowrap">{{ $periode['par'] ?: '—' }}</td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>
        @endif
      </section>
    </div>

    <aside class="space-y-6 min-w-0">
      {{-- Aperçu en direct --}}
      <section class="carte overflow-hidden" aria-labelledby="t-apercu">
        <div class="carte-tete">
          <h2 id="t-apercu" class="carte-titre">Aperçu</h2>
          <a href="{{ route('maintenance') }}" target="_blank" rel="noopener" class="btn-fantome btn-sm"><x-lucide name="maximize" />Plein format</a>
        </div>
        <div class="px-5 pb-5">
          <div class="rounded-ctrl overflow-hidden border filet shadow-sm" aria-hidden="true">
            <div class="flex items-center gap-1.5 px-3 h-7 bg-papier dark:bg-nuit border-b filet">
              <span class="w-2 h-2 rounded-full bg-red-300"></span><span class="w-2 h-2 rounded-full bg-amber-300"></span><span class="w-2 h-2 rounded-full bg-green-300"></span>
              <span class="ml-2 text-[10px] texte-3 truncate">verifall.com — 503</span>
            </div>
            <div class="relative px-5 py-6 overflow-hidden text-foret" style="background:#EEF2EC">
              <svg class="absolute -right-6 top-4 w-28 h-28" viewBox="0 0 100 100" fill="none"><circle cx="50" cy="50" r="44" stroke="#CFDACF" stroke-width="4"/>@if ($restant !== null)<circle cx="50" cy="50" r="44" stroke="#09B850" stroke-width="4" stroke-linecap="round" pathLength="100" stroke-dasharray="{{ 100 - $restant['part'] }} 100" transform="rotate(-90 50 50)"/>@endif<path d="M50 22l5 28-5 5-5-5z" fill="#09B850"/><path d="M50 62l5-12-5-4-5 4z" fill="#0E2A1E"/></svg>
              <p class="flex items-center gap-1.5 text-[11px] font-semibold"><span class="inline-block w-3.5 h-3.5 rounded bg-foret"></span>Vérif’All</p>
              <p class="mt-3 font-titre text-[22px] font-semibold leading-[1] tracking-tight max-w-[9ch]">La boussole se recalibre.</p>
              <p class="mt-2 pr-16 text-[11px] leading-relaxed text-foret/75 line-clamp-4">{{ $actif ? $messageAffiche : ($message !== '' ? $message : 'Nous mettons Vérif’All à jour. Le service revient dès que l’opération est terminée.') }}</p>
              @if ($restant !== null)
                <p class="mt-2 text-[11px]"><span class="text-foret/60">Retour annoncé</span> <strong class="font-titre tabular-nums">{{ $restant['heure'] }}</strong></p>
              @endif
              <p class="mt-3 flex gap-1.5"><span class="h-6 flex-1 rounded border border-foret/15 bg-white/70"></span><span class="h-6 w-16 rounded bg-foret"></span></p>
            </div>
          </div>
          <p class="aide">Le message se met à jour pendant que vous l’écrivez.</p>
        </div>
      </section>

      {{-- Qui passe --}}
      <section class="carte" aria-labelledby="t-passages">
        <div class="carte-tete"><h2 id="t-passages" class="carte-titre">Pendant la maintenance</h2></div>
        <ul class="carte-corps space-y-3">
          @foreach ($passages as [$icone, $titre, $detail, $passe])
            <li class="flex gap-3">
              <span class="inline-flex w-8 h-8 shrink-0 items-center justify-center rounded-ctrl {{ $passe ? 'bg-brand-50 text-brand-700 dark:bg-brand/15 dark:text-brand' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' }}"><x-lucide :name="$icone" class="w-4 h-4" /></span>
              <span class="min-w-0">
                <span class="flex items-center gap-1.5 text-sm font-medium">{{ $titre }}<x-lucide :name="$passe ? 'check' : 'x'" class="w-3.5 h-3.5 {{ $passe ? 'text-bon' : 'text-critique' }}" /><span class="sr-only">{{ $passe ? 'passe' : 'bloqué' }}</span></span>
                <span class="block text-xs texte-3">{{ $detail }}</span>
              </span>
            </li>
          @endforeach
        </ul>
      </section>

      @if (! $actif)
        <section class="carte" aria-labelledby="t-avant-fermer">
          <div class="carte-tete"><h2 id="t-avant-fermer" class="carte-titre">Avant de fermer</h2></div>
          <ul class="carte-corps space-y-2.5 text-sm">
            <li class="flex gap-2.5"><x-lucide name="database-backup" class="w-4 h-4 mt-0.5 shrink-0 texte-3" /><span>Une sauvegarde récente existe @if ($peutVoirSauvegardes)— <a class="underline underline-offset-2" href="{{ route('staff.backups') }}">vérifier</a>@endif.</span></li>
            <li class="flex gap-2.5"><x-lucide name="megaphone" class="w-4 h-4 mt-0.5 shrink-0 texte-3" /><span>L’équipe et les mentors qui ont un live sont prévenus.</span></li>
            <li class="flex gap-2.5"><x-lucide name="eye" class="w-4 h-4 mt-0.5 shrink-0 texte-3" /><span>La page publique a été relue — elle reste joignable hors maintenance.</span></li>
          </ul>
        </section>
      @endif
    </aside>
  </div>

  {{-- ═══════════════════════════════════════════════ les confirmations --}}
  @if ($confirmation)
    <div class="voile !z-[80]" wire:click="annuler" aria-hidden="true"></div>
    <div class="fixed inset-x-4 top-1/2 -translate-y-1/2 z-[90] mx-auto max-w-md carte shadow-flottant p-6"
         role="alertdialog" aria-modal="true" aria-labelledby="mt-titre" aria-describedby="mt-texte"
         x-data x-init="$nextTick(() => $el.querySelector('[data-focus]')?.focus())" x-on:keydown.escape.window="$wire.annuler()">

      @if ($confirmation === 'ouvrir')
        <span class="inline-flex w-11 h-11 items-center justify-center rounded-full bg-red-50 text-critique dark:bg-red-950" aria-hidden="true"><x-lucide name="lock-keyhole" class="w-5 h-5" /></span>
        <h2 id="mt-titre" class="mt-3 font-titre text-lg font-semibold text-encre dark:text-white">Fermer le site au public ?</h2>
        <div id="mt-texte" class="mt-2 space-y-2 text-sm texte-2">
          <p>Plus personne ne pourra soumettre une vérification, suivre un cours ou lire un démenti. <strong class="text-encre dark:text-white">Votre accès à la console est préservé.</strong></p>
          <p>
            @if ($finAnnoncee !== '')
              La page annoncera une réouverture {{ \Carbon\Carbon::parse($finAnnoncee)->locale('fr')->translatedFormat('l j F à H:i') }}.
            @else
              Aucune heure ne sera annoncée : la page proposera simplement de prévenir par courriel.
            @endif
          </p>
        </div>
        <div class="mt-6 flex flex-wrap gap-2 justify-end">
          <button type="button" wire:click="annuler" class="btn-second" data-focus>Annuler</button>
          <button type="button" wire:click="ouvrirLaMaintenance" wire:loading.attr="disabled" class="btn-danger">
            <x-lucide name="lock-keyhole" />Fermer maintenant
          </button>
        </div>
      @else
        <span class="inline-flex w-11 h-11 items-center justify-center rounded-full bg-brand-50 text-brand-700 dark:bg-brand/15 dark:text-brand" aria-hidden="true"><x-lucide name="power" class="w-5 h-5" /></span>
        <h2 id="mt-titre" class="mt-3 font-titre text-lg font-semibold text-encre dark:text-white">Rouvrir le service ?</h2>
        <div id="mt-texte" class="mt-2 space-y-2 text-sm texte-2">
          <p>
            Le site redevient accessible immédiatement.
            @if ($enAttente > 0)
              <strong class="text-encre dark:text-white">{{ $enAttente }} courriel(s) partiront</strong> vers les personnes qui ont demandé à être prévenues.
            @else
              Personne n’a demandé à être prévenu.
            @endif
          </p>
          {{-- Le rappel qui compte : un courriel parti ne se rappelle pas. --}}
          <p>Assurez-vous que le service fonctionne vraiment : une réouverture prématurée renverrait tout le monde sur un site cassé.</p>
        </div>
        <div class="mt-6 flex flex-wrap gap-2 justify-end">
          <button type="button" wire:click="annuler" class="btn-second" data-focus>Annuler</button>
          <button type="button" wire:click="fermerLaMaintenance" wire:loading.attr="disabled" class="btn-primaire">
            <x-lucide name="power" />Rouvrir et prévenir
          </button>
        </div>
      @endif
    </div>
  @endif
</div>
