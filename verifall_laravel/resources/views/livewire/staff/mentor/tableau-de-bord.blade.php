@php
  $debut = \App\Livewire\Staff\Mentor\TableauDeBord::DEBUT;
  $fin = \App\Livewire\Staff\Mentor\TableauDeBord::FIN;
  $pos = fn (float $h): float => max(0, min(100, ($h - $debut) / ($fin - $debut) * 100));
  $tons = [
      'critique' => 'bg-red-50 border-red-200 text-red-900 dark:bg-red-950/40 dark:border-red-900/60 dark:text-red-100',
      'alerte' => 'bg-amber-50 border-amber-200 text-amber-900 dark:bg-amber-950/30 dark:border-amber-900/60 dark:text-amber-100',
      'info' => 'bg-blue-50 border-blue-200 text-blue-900 dark:bg-blue-950/30 dark:border-blue-900/60 dark:text-blue-100',
      'live' => 'bg-brand-50 border-brand/40 text-foret dark:bg-brand/10 dark:border-brand/40 dark:text-white',
  ];
  $c = $donnees['chiffres'];
  $nbJournee = count($donnees['journee']);
@endphp
<div class="space-y-6">
  {{-- En-tête : la phrase dit ce qui attend, pas un bonjour générique. --}}
  <div class="flex flex-wrap items-end justify-between gap-4">
    <div>
      <p class="text-sm texte-2 first-letter:uppercase">{{ now()->locale('fr')->translatedFormat('l j F') }}</p>
      <h1 class="text-[28px] sm:text-[32px] font-semibold leading-tight text-encre dark:text-white">
        Bonjour {{ $prenom }}, {{ $nbJournee === 0 ? 'rien de planifié aujourd’hui' : $nbJournee.' rendez-vous aujourd’hui' }}
      </h1>
      <p class="mt-1 text-sm texte-2 max-w-2xl">
        @if ($c['aCorriger'] > 0) {{ $c['aCorriger'] }} copie{{ $c['aCorriger'] > 1 ? 's attendent' : ' attend' }} votre correction. @else Aucune copie en attente. @endif
        @if ($donnees['sessions'] !== []) Prochaine session : {{ $donnees['sessions'][0]['quand'] }}. @endif
      </p>
    </div>
    <div class="flex flex-wrap gap-2">
      <a href="{{ route('staff.my-students') }}" class="btn-second"><x-lucide name="users" />Mes apprenants</a>
      @if (\App\Support\Rbac\MenuDuBackOffice::autorise(auth('staff')->user(), 'staff.exam-grading'))
        <a href="{{ route('staff.exam-grading') }}" class="btn-primaire"><x-lucide name="pen-line" />Corriger maintenant</a>
      @endif
    </div>
  </div>

  {{-- Ma journée : la frise de 8 h à 22 h. --}}
  <section class="carte p-5" aria-labelledby="t-journee">
    <div class="flex flex-wrap items-baseline justify-between gap-2 mb-4">
      <h2 id="t-journee" class="carte-titre">Ma journée</h2>
      <p class="text-xs texte-3">De {{ $debut }} h à {{ $fin }} h — la ligne verte marque l’heure actuelle</p>
    </div>
    @if ($donnees['journee'] === [])
      <p class="text-sm texte-2">Aucun live, aucune échéance ni vérification assignée aujourd’hui.</p>
    @else
      <div class="hidden md:block relative h-[150px]">
        <div class="absolute inset-x-0 top-6 border-t filet"></div>
        @for ($h = $debut; $h <= $fin; $h += 2)
          <span class="absolute top-0 {{ $h === $fin ? '-translate-x-full' : ($h === $debut ? '' : '-translate-x-1/2') }} text-[11px] texte-3 tabular-nums whitespace-nowrap" style="left: {{ $pos($h) }}%">{{ $h }} h</span>
          <span class="absolute top-5 h-2 border-l filet" style="left: {{ $pos($h) }}%"></span>
        @endfor
        @if ($maintenant >= $debut && $maintenant <= $fin)
          <span class="absolute top-4 bottom-0 w-0.5 bg-brand rounded-full" style="left: {{ $pos($maintenant) }}%" aria-hidden="true"><span class="absolute -top-1 -left-[3px] w-2 h-2 rounded-full bg-brand"></span></span>
        @endif
        @foreach ($donnees['journee'] as $i => $e)
          <a href="{{ $e['lien'] }}" class="absolute top-10 rounded-ctrl border px-2.5 py-2 hover:shadow-flottant transition-shadow {{ $tons[$e['ton']] ?? $tons['info'] }} {{ $e['debut'] + $e['duree'] < $maintenant ? 'opacity-60' : '' }}"
             style="left: min({{ $pos($e['debut']) }}%, calc(100% - 210px)); width: max({{ $e['duree'] / ($fin - $debut) * 100 }}%, 210px); margin-top: {{ ($i % 2) * 46 }}px">
            <span class="flex items-center gap-1.5 text-[13px] font-medium leading-tight"><x-lucide :name="$e['icone']" class="w-3.5 h-3.5 shrink-0" /><span class="truncate">{{ $e['titre'] }}</span></span>
            <span class="block mt-0.5 text-xs opacity-80 truncate">{{ $e['heure'] }} — {{ $e['detail'] }}</span>
          </a>
        @endforeach
      </div>
      <ol class="md:hidden space-y-2">
        @foreach ($donnees['journee'] as $e)
          <li><a href="{{ $e['lien'] }}" class="flex items-start gap-3 rounded-ctrl border px-3 py-2.5 {{ $tons[$e['ton']] ?? $tons['info'] }}"><span class="text-xs font-semibold tabular-nums w-12 shrink-0 pt-0.5">{{ $e['heure'] }}</span><span class="min-w-0"><span class="flex items-center gap-1.5 text-sm font-medium"><x-lucide :name="$e['icone']" class="w-3.5 h-3.5" />{{ $e['titre'] }}</span><span class="block text-xs opacity-80">{{ $e['detail'] }}</span></span></a></li>
        @endforeach
      </ol>
    @endif
  </section>

  {{-- Chiffres clés : ce qui est mesuré, rien d'inventé. --}}
  <div class="grid grid-cols-2 xl:grid-cols-4 gap-4">
    @foreach ([
        ['Apprenants actifs', number_format($c['actifs'], 0, ',', ' '), 'sur 30 jours', 'users'],
        ['Copies à corriger', (string) $c['aCorriger'], $c['aCorriger'] ? 'les plus anciennes d’abord' : 'rien en attente', 'pen-line'],
        ['Complétion de mes cours', $c['completion'].' %', 'moyenne des cours publiés', 'circle-check'],
        ['Note des apprenants', $c['note'] !== null ? str_replace('.', ',', (string) $c['note']) : '—', $c['avis'] ? 'sur 5 — '.$c['avis'].' avis' : 'pas encore d’avis', 'star'],
    ] as [$libelle, $valeur, $aide, $icone])
      <div class="carte p-4">
        <div class="flex items-center justify-between gap-2"><p class="text-[13px] texte-2">{{ $libelle }}</p><span class="texte-3"><x-lucide :name="$icone" /></span></div>
        <p class="mt-2 text-[26px] leading-none font-semibold tracking-tight tabular-nums">{{ $valeur }}</p>
        <p class="mt-2 text-xs texte-3">{{ $aide }}</p>
      </div>
    @endforeach
  </div>

  <div class="grid lg:grid-cols-3 gap-4">
    <section class="carte min-w-0 lg:col-span-2 overflow-hidden">
      <div class="carte-tete"><div><h2 class="carte-titre">Boîte de correction</h2><p class="text-xs texte-3 mt-0.5">Les plus anciennes d’abord</p></div></div>
      @if ($donnees['copies'] === [])
        <p class="carte-corps text-sm texte-2">Aucune copie n’attend votre correction.</p>
      @else
        <ul class="divide-y divide-trait dark:divide-nuit-trait">
          @foreach ($donnees['copies'] as $copie)
            <li class="flex items-center gap-3 px-5 py-3">
              <span class="avatar w-9 h-9">{{ $copie['initiales'] }}</span>
              <div class="min-w-0 flex-1"><p class="text-sm font-medium truncate">{{ $copie['apprenant'] }} — {{ $copie['devoir'] }}</p><p class="text-xs texte-3 truncate">{{ $copie['cours'] }}, rendu {{ $copie['soumis'] }}</p></div>
              @if ($copie['urgent'])<span class="p-critique"><x-lucide name="clock-alert" />Urgent</span>@else<span class="p-neutre"><x-lucide name="inbox" />Nouveau</span>@endif
            </li>
          @endforeach
        </ul>
      @endif
    </section>

    <section class="carte min-w-0">
      <div class="carte-tete"><div><h2 class="carte-titre">Vérifications assignées</h2><p class="text-xs texte-3 mt-0.5">Objectif : verdict en 6 h</p></div></div>
      <div class="carte-corps">
        @forelse ($donnees['assignees'] as $v)
          <a href="{{ $v['lien'] }}" class="flex items-start gap-3 py-2 group">
            <span class="mt-0.5 {{ $v['reste'] < 120 ? 'text-red-700 dark:text-red-400' : 'texte-3' }}"><x-lucide :name="$v['reste'] < 120 ? 'clock-alert' : 'clock'" /></span>
            <span class="min-w-0"><span class="block text-sm font-medium group-hover:underline">{{ $v['texte'] }}</span>
              <span class="block text-xs mt-0.5 {{ $v['reste'] < 120 ? 'text-red-700 dark:text-red-400 font-medium' : 'texte-3' }}">{{ $v['reste'] > 0 ? 'reste '.intdiv($v['reste'], 60).' h '.str_pad((string) ($v['reste'] % 60), 2, '0', STR_PAD_LEFT) : 'délai dépassé' }}</span></span>
          </a>
        @empty
          <p class="text-sm texte-2">Aucune vérification ne vous est assignée.</p>
        @endforelse
      </div>
    </section>
  </div>

  <section class="carte min-w-0">
    <div class="carte-tete"><div><h2 class="carte-titre">Mes cours en un coup d’œil</h2><p class="text-xs texte-3 mt-0.5">Inscriptions des 12 dernières semaines</p></div><a href="{{ route('staff.courses') }}" class="btn-fantome btn-sm">Mes cours</a></div>
    @if ($donnees['cours'] === [])
      <p class="carte-corps text-sm texte-2">Vous n’avez pas encore de cours publié.</p>
    @else
      <div class="overflow-x-auto">
        <table class="tableau">
          <thead><tr><th>Cours</th><th>Inscrits</th><th>Actifs (7 j)</th><th>Complétion</th><th>Note</th><th>Tendance</th></tr></thead>
          <tbody>
            @foreach ($donnees['cours'] as $cours)
              @php
                $max = max(1, max($cours['tendance']));
                $points = collect($cours['tendance'])->map(fn ($v, $i) => round($i * 100 / 11, 1).','.round(28 - $v / $max * 24, 1))->implode(' ');
              @endphp
              <tr>
                <td class="font-medium">{{ $cours['titre'] }}</td>
                <td class="tabular-nums">{{ number_format($cours['inscrits'], 0, ',', ' ') }}</td>
                <td class="tabular-nums">{{ number_format($cours['actifs'], 0, ',', ' ') }}</td>
                <td><div class="flex items-center gap-2 min-w-[120px]"><div class="jauge"><span style="width: {{ $cours['completion'] }}%"></span></div><span class="text-xs tabular-nums texte-2 whitespace-nowrap">{{ $cours['completion'] }} %</span></div></td>
                <td>{{ $cours['note'] !== null ? str_replace('.', ',', (string) $cours['note']) : '—' }}</td>
                <td>
                  <svg viewBox="0 0 100 30" class="w-28 h-8 text-[#2a78d6] dark:text-[#3987e5]" role="img" aria-label="Inscriptions par semaine : {{ implode(', ', $cours['tendance']) }}">
                    <polyline points="{{ $points }}" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" stroke-linecap="round" vector-effect="non-scaling-stroke" />
                  </svg>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    @endif
  </section>

  <div class="grid lg:grid-cols-3 gap-4">
    <section class="carte min-w-0">
      <div class="carte-tete"><div><h2 class="carte-titre">À risque de décrochage</h2><p class="text-xs texte-3 mt-0.5">Aucune activité depuis 14 jours</p></div></div>
      <div class="carte-corps space-y-3">
        @forelse ($donnees['aRisque'] as $a)
          <div class="flex items-center gap-3"><span class="avatar w-8 h-8">{{ $a['initiales'] }}</span><div class="min-w-0 flex-1"><p class="text-sm font-medium truncate">{{ $a['nom'] }}</p><p class="text-xs texte-3 truncate">{{ $a['cours'] }} — {{ $a['progression'] }} %, {{ $a['derniere'] }}</p></div></div>
        @empty
          <p class="text-sm texte-2">Personne ne décroche : tous vos apprenants sont actifs.</p>
        @endforelse
        @if ($donnees['aRisque'] !== [])<a href="{{ route('staff.progression') }}" class="btn-second btn-sm"><x-lucide name="send" />Relancer depuis Progression</a>@endif
      </div>
    </section>
    <section class="carte min-w-0">
      <div class="carte-tete"><div><h2 class="carte-titre">Questions sans réponse</h2><p class="text-xs texte-3 mt-0.5">Forum, 7 derniers jours</p></div></div>
      <div class="carte-corps space-y-3">
        @forelse ($donnees['questions'] as $q)
          <a href="{{ $q['lien'] }}" class="flex items-start gap-3 group"><x-lucide name="message-circle-question" class="w-4 h-4 mt-0.5 texte-3 shrink-0" /><span class="min-w-0"><span class="block text-sm font-medium group-hover:underline">{{ $q['titre'] }}</span><span class="block text-xs texte-3">{{ $q['auteur'] }}, {{ $q['quand'] }}</span></span></a>
        @empty
          <p class="text-sm texte-2">Toutes les questions récentes ont une réponse.</p>
        @endforelse
      </div>
    </section>
    <section class="carte min-w-0">
      <div class="carte-tete"><h2 class="carte-titre">Prochaines sessions</h2><a href="{{ route('staff.live-classes') }}" class="btn-fantome btn-sm">Live classes</a></div>
      <div class="carte-corps space-y-3">
        @forelse ($donnees['sessions'] as $s)
          <div class="flex items-center gap-3 p-3 rounded-ctrl border filet"><span class="inline-flex w-9 h-9 items-center justify-center rounded-ctrl bg-brand-50 dark:bg-brand/10 text-brand-700 dark:text-brand"><x-lucide name="video" /></span><div class="min-w-0"><p class="text-sm font-medium truncate">{{ $s['titre'] }}</p><p class="text-xs texte-3">{{ $s['quand'] }} — {{ $s['inscrits'] }} inscrits</p></div></div>
        @empty
          <p class="text-sm texte-2">Aucune session programmée.</p>
        @endforelse
      </div>
    </section>
  </div>
</div>
