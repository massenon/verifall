{{-- La fiche d'un apprenant vue par SON mentor : progression dans ses cours, rien d'autre. --}}
<div>
  <x-premium.entete titre="{{ $identite['nom'] }}" :fil="['Mes apprenants']">
    Sa progression dans vos cours. Les autres informations de ce compte ne vous sont pas ouvertes.
    <x-slot:actions>
      <a href="{{ route('staff.my-students') }}" class="btn-second"><x-lucide name="arrow-left" />Retour à mes apprenants</a>
    </x-slot:actions>
  </x-premium.entete>

  <div class="grid gap-4 sm:grid-cols-3 mb-6">
    <x-premium.chiffre icone="calendar-days" libelle="Inscrit depuis" :valeur="$identite['inscritLe'] ?? '—'" :aide="$identite['ville']" />
    <x-premium.chiffre icone="award" libelle="Points" :valeur="number_format($identite['points'], 0, ',', ' ')" />
    <x-premium.chiffre icone="flame" libelle="Série en cours" :valeur="$identite['serie'].' j'" />
  </div>

  <x-premium.carte titre="Dans vos cours">
    @if ($inscriptions->isEmpty())
      <x-premium.vide icone="library" titre="Aucune inscription" texte="Cet apprenant n'est inscrit à aucun de vos cours." />
    @else
      <ul class="divide-y divide-trait dark:divide-nuit-trait -mx-5">
        @foreach ($inscriptions as $inscription)
          <li class="px-5 py-3 flex flex-wrap items-center gap-x-4 gap-y-1">
            <span class="font-medium flex-1 min-w-[200px]">{{ $inscription->course?->title ?? '—' }}</span>
            <span class="w-40"><x-premium.jauge :pct="(int) $inscription->progress" /></span>
            <span class="tabular-nums text-sm w-12 text-right">{{ (int) $inscription->progress }} %</span>
            <span class="text-xs texte-3 w-40 text-right">{{ $inscription->last_activity_at ? 'Actif '.$inscription->last_activity_at->locale('fr')->diffForHumans() : 'Jamais commencé' }}</span>
          </li>
        @endforeach
      </ul>
    @endif
  </x-premium.carte>
</div>
