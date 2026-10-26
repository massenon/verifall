@php
  $onglets = ['profil' => ['circle-user-round', 'Profil'], 'securite' => ['shield-check', 'Sécurité'], 'sessions' => ['monitor-smartphone', 'Sessions'], 'connexions' => ['history', 'Connexions']];
  $initiales = \Illuminate\Support\Str::of($membre->name)->explode(' ')->filter()->take(2)->map(fn (string $m): string => mb_strtoupper(mb_substr($m, 0, 1)))->implode('');
@endphp
<div class="space-y-6 max-w-4xl">
  <div class="flex flex-wrap items-center gap-4">
    <span class="avatar w-14 h-14 !text-lg">{{ $initiales }}</span>
    <div>
      <h1 class="text-[26px] sm:text-[28px] font-semibold leading-tight text-encre dark:text-white">{{ $membre->name }}</h1>
      <p class="text-sm texte-2">{{ $membre->email }} · {{ $roles->implode(', ') ?: 'Équipe' }}</p>
    </div>
    <a href="{{ route('staff.users.show', $membre) }}" class="btn-second ml-auto"><x-lucide name="id-card" />Ma fiche d'activité</a>
  </div>

  <div class="onglets" role="tablist">
    @foreach ($onglets as $cle => [$icone, $libelle])
      <button type="button" role="tab" class="onglet" aria-selected="{{ $onglet === $cle ? 'true' : 'false' }}" wire:click="$set('onglet', '{{ $cle }}')">{{ $libelle }}</button>
    @endforeach
  </div>

  @if ($onglet === 'profil')
    <form wire:submit="enregistrerLeProfil" class="carte p-5 grid sm:grid-cols-2 gap-4">
      <div><label class="etiquette" for="mc-prenom">Prénom</label><input id="mc-prenom" class="champ @error('prenom') champ-erreur @enderror" wire:model="prenom">@error('prenom')<p class="aide-erreur">{{ $message }}</p>@enderror</div>
      <div><label class="etiquette" for="mc-nom">Nom</label><input id="mc-nom" class="champ @error('nom') champ-erreur @enderror" wire:model="nom">@error('nom')<p class="aide-erreur">{{ $message }}</p>@enderror</div>
      <div><label class="etiquette" for="mc-tel">Téléphone</label><input id="mc-tel" class="champ" wire:model="telephone" inputmode="tel"></div>
      <div><label class="etiquette" for="mc-ville">Ville</label><input id="mc-ville" class="champ" wire:model="ville"></div>
      <p class="sm:col-span-2 aide">L’adresse {{ $membre->email }} et les rôles se changent depuis « Utilisateurs » et « Rôles et permissions ».</p>
      <div class="sm:col-span-2 flex justify-end"><button class="btn-primaire" type="submit">Enregistrer</button></div>
    </form>
  @elseif ($onglet === 'securite')
    <form wire:submit="changerLeMotDePasse" class="carte p-5 space-y-4">
      <h2 class="carte-titre">Mot de passe</h2>
      <div><label class="etiquette" for="mc-actuel">Mot de passe actuel</label><input id="mc-actuel" type="password" autocomplete="current-password" class="champ @error('motDePasseActuel') champ-erreur @enderror" wire:model="motDePasseActuel">@error('motDePasseActuel')<p class="aide-erreur">{{ $message }}</p>@enderror</div>
      <div class="grid sm:grid-cols-2 gap-4">
        <div><label class="etiquette" for="mc-nouveau">Nouveau mot de passe</label><input id="mc-nouveau" type="password" autocomplete="new-password" class="champ @error('nouveauMotDePasse') champ-erreur @enderror" wire:model="nouveauMotDePasse">@error('nouveauMotDePasse')<p class="aide-erreur">{{ $message }}</p>@enderror</div>
        <div><label class="etiquette" for="mc-conf">Confirmation</label><input id="mc-conf" type="password" autocomplete="new-password" class="champ" wire:model="nouveauMotDePasse_confirmation"></div>
      </div>
      <div class="flex justify-end"><button class="btn-primaire" type="submit">Changer le mot de passe</button></div>
    </form>
    <section class="carte p-5 flex items-center justify-between gap-3">
      <div><h2 class="carte-titre">Double authentification</h2><p class="text-sm texte-2 mt-1">{{ $deuxFacteurs ? 'Activée : un code de votre application est demandé à chaque connexion.' : 'Non activée. Elle s’active à la prochaine connexion à la console.' }}</p></div>
      <span class="{{ $deuxFacteurs ? 'p-bon' : 'p-alerte' }}"><x-lucide :name="$deuxFacteurs ? 'shield-check' : 'triangle-alert'" />{{ $deuxFacteurs ? 'Activée' : 'Inactive' }}</span>
    </section>
  @elseif ($onglet === 'sessions')
    <section class="carte">
      <div class="carte-tete"><h2 class="carte-titre">Appareils connectés</h2><button type="button" class="btn-second btn-sm" wire:click="fermerLesAutres" wire:confirm="Fermer toutes les autres sessions ?">Fermer toutes les autres</button></div>
      <ul class="divide-y divide-trait dark:divide-nuit-trait">
        @forelse ($sessions as $s)
          <li class="flex items-center gap-3 px-5 py-3">
            <x-lucide :name="$s['appareil'] === 'Mobile' ? 'smartphone' : 'monitor'" class="w-5 h-5 texte-3" />
            <span class="flex-1 min-w-0"><span class="block text-sm font-medium">{{ $s['navigateur'] }} · {{ $s['appareil'] }}</span><span class="block text-xs texte-3">Dernière activité {{ $s['derniere'] }}</span></span>
            @if ($s['courante'])<span class="p-bon">Cette session</span>@else<button type="button" class="btn-fantome btn-sm" wire:click="fermerLaSession('{{ $s['id'] }}')">Fermer</button>@endif
          </li>
        @empty
          <li class="px-5 py-4 text-sm texte-2">Aucune session enregistrée.</li>
        @endforelse
      </ul>
    </section>
  @else
    <section class="carte">
      <div class="carte-tete"><h2 class="carte-titre">Mes connexions</h2><span class="text-xs texte-3">12 mois · adresse IP tronquée</span></div>
      @if ($connexions->isEmpty())
        <p class="carte-corps text-sm texte-2">Aucune connexion enregistrée.</p>
      @else
        <div class="overflow-x-auto"><table class="tableau"><thead><tr><th>Date</th><th>Résultat</th><th>Appareil</th><th>Réseau</th></tr></thead><tbody>
          @foreach ($connexions as $c)
            <tr>
              <td class="whitespace-nowrap tabular-nums">{{ $c->created_at->locale('fr')->translatedFormat('j M Y, H:i') }}</td>
              <td><span class="{{ $c->resultat === 'reussie' ? 'p-bon' : 'p-critique' }}">{{ $c->resultat === 'reussie' ? 'Réussie' : ($c->resultat === 'bloquee' ? 'Bloquée' : 'Échouée') }}</span></td>
              <td>{{ trim(($c->navigateur ?? '').' · '.($c->systeme ?? ''), ' ·') ?: '—' }}</td>
              <td class="texte-2">{{ $c->ip_tronquee ?? '—' }}{{ $c->pays ? ' ('.$c->pays.')' : '' }}</td>
            </tr>
          @endforeach
        </tbody></table></div>
      @endif
    </section>
  @endif
</div>
