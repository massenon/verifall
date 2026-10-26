@php
  $onglets = ['academie' => 'Académie', 'ia' => 'IA de vérification', 'revenus' => 'Revenus et quotas', 'bi' => 'Business Intelligence', 'seo' => 'SEO'];
  $nf = fn (int $n): string => number_format($n, 0, ',', ' ');
@endphp
<div>
  <x-premium.entete titre="Analytique" :fil="['Analytique']" sous="Mesuré sur la base, sans estimation. Les chiffres sont recalculés toutes les dix minutes." />

  <div class="onglets mb-6" role="tablist">
    @foreach ($onglets as $cle => $libelle)
      <button type="button" role="tab" class="onglet" aria-selected="{{ $vue === $cle ? 'true' : 'false' }}" wire:click="$set('vue', '{{ $cle }}')">{{ $libelle }}</button>
    @endforeach
  </div>

  @if ($academie)
    @php $e = $academie['entonnoir']; $max = max(1, $e['inscrits']); @endphp
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
      <x-premium.chiffre icone="users" libelle="Inscriptions" :valeur="$nf($e['inscrits'])" />
      <x-premium.chiffre icone="activity" libelle="Apprenants actifs (7 j)" :valeur="$nf($academie['actifs_7j'])" />
      <x-premium.chiffre icone="clock" libelle="Sans activité depuis 14 j" :valeur="$nf($academie['inactifs'])" aide="Inscriptions non terminées" />
      <x-premium.chiffre icone="award" libelle="Certificats délivrés" :valeur="$nf($e['certificats'])" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <x-premium.carte titre="Entonnoir" aide="De l'inscription au certificat">
        <ul class="space-y-4">
          @foreach ([['Inscrits', $e['inscrits']], ['Ont commencé', $e['en_cours']], ['Ont terminé', $e['termines']], ['Certifiés', $e['certificats']]] as [$libelle, $n])
            <li>
              <div class="flex justify-between text-sm mb-1"><span>{{ $libelle }}</span><span class="tabular-nums font-medium">{{ $nf($n) }} <span class="texte-3 font-normal">· {{ (int) round($n / $max * 100) }} %</span></span></div>
              <x-premium.jauge :pct="(int) round($n / $max * 100)" />
            </li>
          @endforeach
        </ul>
      </x-premium.carte>

      <x-premium.carte titre="Cours les plus suivis" aide="Part des inscrits qui ont terminé">
        @if ($academie['cours'] === [])
          <x-premium.vide icone="library" titre="Aucune inscription" texte="Les cours apparaissent ici dès qu'un citoyen s'inscrit." />
        @else
          <ul class="space-y-4">
            @foreach ($academie['cours'] as $c)
              <li>
                <div class="flex justify-between gap-3 text-sm mb-1"><span class="truncate">{{ $c['titre'] }}</span><span class="tabular-nums shrink-0">{{ $c['completion'] }} % · {{ $c['inscrits'] }} inscrits</span></div>
                <x-premium.jauge :pct="$c['completion']" />
              </li>
            @endforeach
          </ul>
        @endif
      </x-premium.carte>
    </div>
  @endif

  @if ($moteurs !== null)
    <x-premium.carte titre="Moteurs d'analyse" aide="30 derniers jours, depuis les exécutions enregistrées">
      @if ($moteurs === [])
        <x-premium.vide icone="cpu" titre="Aucune exécution" texte="Aucun moteur n'a tourné sur la période. Les moteurs simulés n'enregistrent rien." />
      @else
        <div class="overflow-x-auto -mx-5">
          <table class="tableau">
            <thead><tr><th>Moteur</th><th>Analyses</th><th>Échecs</th><th>Latence moyenne</th><th>Confiance moyenne</th></tr></thead>
            <tbody>
              @foreach ($moteurs as $m)
                <tr>
                  <td class="font-medium">{{ $m['moteur'] }}</td>
                  <td class="tabular-nums">{{ $nf($m['analyses']) }}</td>
                  <td><span class="{{ $m['taux_echec'] >= 20 ? 'p-critique' : ($m['taux_echec'] >= 5 ? 'p-alerte' : 'p-bon') }}">{{ $m['taux_echec'] }} %</span> <span class="texte-3 text-xs">({{ $m['echecs'] }})</span></td>
                  <td class="tabular-nums">{{ $nf($m['latence_ms']) }} ms</td>
                  <td class="min-w-[140px]"><span class="tabular-nums text-sm">{{ $m['confiance'] }} %</span><x-premium.jauge :pct="$m['confiance']" /></td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </x-premium.carte>
  @endif

  @if ($revenus)
    @php $maxMois = max(1, ...array_column($revenus['mois'], 'fcfa')); @endphp
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4 mb-6">
      <x-premium.chiffre icone="wallet" libelle="Encaissé (6 mois)" :valeur="$nf($revenus['total_fcfa']).' FCFA'" />
      <x-premium.chiffre icone="check" libelle="Paiements réussis" :valeur="$nf($revenus['reussis'])" :aide="$nf($revenus['echoues']).' échoués ou expirés'" />
      <x-premium.chiffre icone="zap" libelle="Unités créditées" :valeur="$nf($revenus['credites'])" />
      <x-premium.chiffre icone="receipt" libelle="Unités consommées" :valeur="$nf($revenus['consommes'])" />
    </div>

    <div class="grid gap-6 lg:grid-cols-2">
      <x-premium.carte titre="Encaissements par mois" aide="Paiements Mobile Money réussis">
        <ul class="space-y-3">
          @foreach ($revenus['mois'] as $mois)
            <li>
              <div class="flex justify-between text-sm mb-1"><span>{{ \Carbon\Carbon::createFromFormat('Y-m', $mois['mois'])->locale('fr')->translatedFormat('F Y') }}</span><span class="tabular-nums">{{ $nf($mois['fcfa']) }} FCFA · {{ $mois['paiements'] }}</span></div>
              <x-premium.jauge :pct="(int) round($mois['fcfa'] / $maxMois * 100)" />
            </li>
          @endforeach
        </ul>
      </x-premium.carte>

      <x-premium.carte titre="Où partent les unités" aide="Consommation par motif">
        @if ($revenus['motifs'] === [])
          <x-premium.vide icone="receipt" titre="Aucune consommation" texte="Les motifs apparaissent dès que des unités sont dépensées." />
        @else
          @php $maxU = max(1, ...array_column($revenus['motifs'], 'unites')); @endphp
          <ul class="space-y-3">
            @foreach ($revenus['motifs'] as $motif)
              <li>
                <div class="flex justify-between text-sm mb-1"><span>{{ $motif['motif'] }}</span><span class="tabular-nums">{{ $nf($motif['unites']) }}</span></div>
                <x-premium.jauge :pct="(int) round($motif['unites'] / $maxU * 100)" />
              </li>
            @endforeach
          </ul>
        @endif
      </x-premium.carte>
    </div>
  @endif

  @if ($serie)
    @php $maxJour = max(1, ...array_column($serie['jours'], 'valeur')); $v = $serie['variation']; @endphp
    <x-premium.carte titre="Indicateur" aide="Choisi dans une liste fermée, calculé sur la base">
      <div class="grid gap-4 sm:grid-cols-3">
        <label class="block">
          <span class="etiquette">Indicateur</span>
          <select class="champ" wire:model.live="indicateur">
            @foreach (\App\Core\Application\Analytique\IndicateursLibres::CATALOGUE as $cle => $def)
              <option value="{{ $cle }}">{{ $def['libelle'] }}</option>
            @endforeach
          </select>
        </label>
        <label class="block">
          <span class="etiquette">Période</span>
          <select class="champ" wire:model.live="duree">
            @foreach (\App\Core\Application\Analytique\IndicateursLibres::DUREES as $j)
              <option value="{{ $j }}">{{ $j }} derniers jours</option>
            @endforeach
          </select>
        </label>
        <div class="flex items-end"><button type="button" class="btn-secondaire" wire:click="exporterLaSerie"><x-lucide name="download" class="size-4" /> Exporter en CSV</button></div>
      </div>
    </x-premium.carte>

    <div class="grid gap-4 sm:grid-cols-3 my-6">
      <x-premium.chiffre icone="bar-chart-3" :libelle="$serie['libelle']" :valeur="$nf($serie['total']).($serie['unite'] !== '' ? ' '.$serie['unite'] : '')" :aide="'Sur '.$duree.' jours'" />
      <x-premium.chiffre icone="history" libelle="Période précédente" :valeur="$nf($serie['precedent']).($serie['unite'] !== '' ? ' '.$serie['unite'] : '')" :aide="'Les '.$duree.' jours d\'avant'" />
      <x-premium.chiffre icone="trending-up" libelle="Variation" :valeur="$v === null ? '—' : (($v > 0 ? '+' : '').$v.' %')" :aide="$v === null ? 'Pas de période précédente à comparer' : null" />
    </div>

    <x-premium.carte titre="Jour par jour">
      @if ($serie['total'] === 0)
        <x-premium.vide icone="bar-chart-3" titre="Aucune donnée sur la période" texte="Rien n'a été enregistré pour cet indicateur ces {{ $duree }} derniers jours." />
      @else
        <div class="flex items-end gap-px h-40" role="img" aria-label="Histogramme quotidien de {{ $serie['libelle'] }}">
          @foreach ($serie['jours'] as $point)
            <div class="flex-1 rounded-t bg-[var(--st-accent,#2563eb)] min-h-[2px]" style="height: {{ max(1, (int) round($point['valeur'] / $maxJour * 100)) }}%" title="{{ \Carbon\Carbon::parse($point['jour'])->locale('fr')->translatedFormat('j F') }} : {{ $nf($point['valeur']) }}"></div>
          @endforeach
        </div>
        <div class="overflow-x-auto -mx-5 mt-6 max-h-72">
          <table class="tableau">
            <thead><tr><th>Jour</th><th>{{ $serie['libelle'] }}</th></tr></thead>
            <tbody>
              @foreach (array_reverse($serie['jours']) as $point)
                <tr><td>{{ \Carbon\Carbon::parse($point['jour'])->locale('fr')->translatedFormat('l j F Y') }}</td><td class="tabular-nums">{{ $nf($point['valeur']) }}</td></tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </x-premium.carte>
  @endif

  @if ($seo)
    @php $st = $seo['statut']; @endphp
    @if (! $st['ga4'] && ! $st['gsc'])
      <x-premium.carte titre="Google n'est pas encore relié">
        <x-premium.vide icone="search" titre="Aucun accès Google renseigné" texte="Saisissez la clé du compte de service, l'identifiant de propriété GA4 et le site Search Console dans Paramètres › SEO.">
          <a class="btn-primaire" href="{{ route('staff.settings', ['onglet' => 'seo']) }}">Ouvrir Paramètres › SEO</a>
        </x-premium.vide>
      </x-premium.carte>
    @else
      <div class="grid gap-6 lg:grid-cols-2">
        <x-premium.carte titre="Search Console" aide="28 derniers jours (décalés de deux jours)">
          @if (! $st['gsc'])
            <x-premium.vide icone="search" titre="Site non renseigné" texte="Ajoutez l'adresse du site Search Console dans Paramètres › SEO." />
          @elseif (isset($seo['gsc_erreur']))
            <x-premium.vide icone="alert-triangle" titre="Lecture impossible" :texte="$seo['gsc_erreur']" />
          @else
            @php $g = $seo['gsc']; @endphp
            <div class="grid grid-cols-2 gap-3 mb-5">
              <x-premium.chiffre icone="mouse-pointer-click" libelle="Clics" :valeur="$nf($g['clics'])" />
              <x-premium.chiffre icone="eye" libelle="Impressions" :valeur="$nf($g['impressions'])" />
              <x-premium.chiffre icone="percent" libelle="CTR" :valeur="$g['ctr'].' %'" />
              <x-premium.chiffre icone="list-ordered" libelle="Position moyenne" :valeur="(string) $g['position']" />
            </div>
            <h4 class="text-sm font-medium mb-2">Requêtes principales</h4>
            @if ($g['requetes'] === [])
              <p class="texte-3 text-sm">Aucune requête sur la période.</p>
            @else
              <div class="overflow-x-auto -mx-5"><table class="tableau"><thead><tr><th>Requête</th><th>Clics</th><th>Impr.</th><th>Pos.</th></tr></thead><tbody>
                @foreach ($g['requetes'] as $r)<tr><td class="max-w-[16rem] truncate">{{ $r['libelle'] }}</td><td class="tabular-nums">{{ $nf($r['clics']) }}</td><td class="tabular-nums">{{ $nf($r['impressions']) }}</td><td class="tabular-nums">{{ $r['position'] }}</td></tr>@endforeach
              </tbody></table></div>
            @endif
            <h4 class="text-sm font-medium mt-5 mb-2">Pages principales</h4>
            @if ($g['pages'] === [])
              <p class="texte-3 text-sm">Aucune page sur la période.</p>
            @else
              <div class="overflow-x-auto -mx-5"><table class="tableau"><thead><tr><th>Page</th><th>Clics</th><th>Impr.</th><th>Pos.</th></tr></thead><tbody>
                @foreach ($g['pages'] as $r)<tr><td class="max-w-[16rem] truncate">{{ $r['libelle'] }}</td><td class="tabular-nums">{{ $nf($r['clics']) }}</td><td class="tabular-nums">{{ $nf($r['impressions']) }}</td><td class="tabular-nums">{{ $r['position'] }}</td></tr>@endforeach
              </tbody></table></div>
            @endif
          @endif
        </x-premium.carte>

        <x-premium.carte titre="Google Analytics 4" aide="28 derniers jours">
          @if (! $st['ga4'])
            <x-premium.vide icone="bar-chart-3" titre="Propriété non renseignée" texte="Ajoutez l'identifiant de propriété GA4 dans Paramètres › SEO." />
          @elseif (isset($seo['ga4_erreur']))
            <x-premium.vide icone="alert-triangle" titre="Lecture impossible" :texte="$seo['ga4_erreur']" />
          @else
            @php $a = $seo['ga4']; @endphp
            <div class="grid grid-cols-2 gap-3 mb-5">
              <x-premium.chiffre icone="activity" libelle="Sessions" :valeur="$nf($a['sessions'])" />
              <x-premium.chiffre icone="users" libelle="Utilisateurs" :valeur="$nf($a['utilisateurs'])" />
              <x-premium.chiffre icone="eye" libelle="Pages vues" :valeur="$nf($a['vues'])" />
              <x-premium.chiffre icone="zap" libelle="Engagement" :valeur="$a['engagement'].' %'" />
            </div>
            <h4 class="text-sm font-medium mb-2">Canaux d'acquisition</h4>
            @if ($a['sources'] === [])
              <p class="texte-3 text-sm">Aucune session sur la période.</p>
            @else
              @php $maxS = max(1, ...array_column($a['sources'], 'sessions')); @endphp
              <ul class="space-y-3">
                @foreach ($a['sources'] as $src)
                  <li><div class="flex justify-between text-sm mb-1"><span>{{ $src['source'] }}</span><span class="tabular-nums">{{ $nf($src['sessions']) }}</span></div><x-premium.jauge :pct="(int) round($src['sessions'] / $maxS * 100)" /></li>
                @endforeach
              </ul>
            @endif
            <h4 class="text-sm font-medium mt-5 mb-2">Pages les plus vues</h4>
            @if ($a['pages'] !== [])
              <div class="overflow-x-auto -mx-5"><table class="tableau"><thead><tr><th>Page</th><th>Vues</th></tr></thead><tbody>
                @foreach ($a['pages'] as $r)<tr><td class="max-w-[18rem] truncate">{{ $r['page'] }}</td><td class="tabular-nums">{{ $nf($r['vues']) }}</td></tr>@endforeach
              </tbody></table></div>
            @endif
          @endif
        </x-premium.carte>
      </div>
    @endif
  @endif
</div>
