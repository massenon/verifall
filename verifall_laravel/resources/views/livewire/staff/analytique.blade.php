@php
  $onglets = ['academie' => 'Académie', 'ia' => 'IA de vérification', 'revenus' => 'Revenus et quotas'];
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
</div>
