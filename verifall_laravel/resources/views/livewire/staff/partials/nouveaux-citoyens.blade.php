{{--
  | Nouveaux Citoyens — les dix derniers inscrits.
  |
  | L'adresse e-mail n'est PAS affichée. Une liste de bienvenue n'a aucun besoin
  | de la donnée la plus sensible du compte, et un tableau de bord reste ouvert
  | sur un écran partagé ; l'écran « Utilisateurs » la porte, avec la traçabilité
  | qui va avec.
  |
  | Ce qui est affiché à la place répond à la seule question utile ici : le
  | compte est-il ACTIVÉ ? Une inscription bloquée à l'étape du code est le
  | symptôme d'une file d'attente à l'arrêt, et c'est ainsi qu'on le voit.
--}}
<div class="carte">
    <div class="carte-tete flex items-center justify-between gap-3">
        <h2 class="font-titre font-bold text-encre dark:text-white mb-0">Nouveaux citoyens</h2>
        <a href="{{ route('staff.users') }}" class="text-xs font-bold text-brand-700 dark:text-brand">Tout voir →</a>
    </div>

    <div class="carte-corps">
        @forelse ($citoyens as $citoyen)
            <div class="flex items-center justify-between gap-3 py-2 border-b border-trait dark:border-nuit-trait last:border-0">
                <div class="min-w-0">
                    <p class="text-sm text-encre dark:text-white mb-0 truncate">{{ $citoyen->name }}</p>
                    {{-- Le pseudonyme est FACULTATIF, et le plus souvent nul :
                         « @ » tout seul suivi de rien s'affichait sur chaque
                         ligne. Une ligne vide vaut mieux qu'un signe orphelin. --}}
                    @if ($citoyen->username || $citoyen->city)
                        <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0 truncate">
                            @if ($citoyen->username){{ '@'.$citoyen->username }}@endif
                            @if ($citoyen->username && $citoyen->city) · @endif
                            @if ($citoyen->city){{ $citoyen->city }}@endif
                        </p>
                    @endif
                </div>
                <div class="shrink-0 text-right">
                    @if ($citoyen->email_verified_at === null)
                        {{-- Le seul état qui appelle une action : le compte
                             attend un code qui n'est peut-être jamais parti. --}}
                        <span class="pastille p-alerte">Non activé</span>
                    @endif
                    <p class="text-xs text-encre-3 dark:text-nuit-3 mb-0">{{ $citoyen->created_at?->diffForHumans() }}</p>
                </div>
            </div>
        @empty
            <p class="text-sm text-encre-3 dark:text-nuit-3 mb-0">Aucune inscription pour l'instant.</p>
        @endforelse
    </div>
</div>
