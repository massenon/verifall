{{--
  | « Sauvegardes BD » (lot 10) — et ce que cet écran NE FAIT PAS.
  |
  | Il n'existe AUCUN mécanisme de sauvegarde dans ce projet : pas de paquet,
  | pas de commande, pas de tâche planifiée, et le guide de déploiement n'en dit
  | pas un mot.
  |
  | Le réflexe serait un bouton « sauvegarder maintenant » appelant `mysqldump`.
  | Ce serait irresponsable : `exec()` est souvent désactivé sur l'hébergement
  | cPanel visé, une sauvegarde qu'on ne peut pas restaurer ne sert à rien, et
  | un dump contient TOUT — adresses, mots de passe hachés, journaux d'audit.
  |
  | L'écran lit donc le disque et dit ce qu'il y trouve. C'est ce qui répond à
  | la seule question qu'on se pose : « sommes-nous sauvegardés ? »
--}}
<div>

    <nav class="text-xs text-encre-3 dark:text-nuit-3 mb-2" aria-label="Fil d'Ariane">
        <a href="{{ route('staff.dashboard') }}" class="hover:text-brand-700">Administration</a>
        <span aria-hidden="true">/</span>
        <span class="text-encre dark:text-white font-medium">Sauvegardes</span>
    </nav>

    <x-premium.entete titre="Sauvegardes de la base">
        L'état de ce qui existe sur le disque de sauvegarde.
    </x-premium.entete>

    {{-- La limite est ANNONCÉE en tête, comme pour le KYC au lot 7 : un écran
         qui laisse croire à plus qu'il ne fait est pire qu'un écran absent —
         ici, il ferait croire qu'on est sauvegardé. --}}
    <div class="rounded-lg border border-trait dark:border-nuit-trait bg-amber-100 dark:bg-amber-950 p-4 mb-6">
        <p class="font-semibold text-amber-700 dark:text-amber-300 mb-1">La plateforme ne fabrique aucune sauvegarde.</p>
        <p class="text-sm text-encre-2 dark:text-nuit-2 mb-2">
            Cet écran <span class="font-semibold">lit</span> le dossier
            <span class="font-mono">storage/app/{{ $dossier }}</span> et rend compte de ce qu'il
            y trouve. Il ne crée rien, et c'est délibéré :
        </p>
        <ul class="text-sm text-encre-2 dark:text-nuit-2 mb-0 pl-5 space-y-1">
            <li><span class="font-mono">mysqldump</span> n'est pas garanti disponible sur un
                hébergement mutualisé — un bouton qui échoue en silence sur un écran de
                sauvegarde est pire que pas de bouton : on se croit protégé.</li>
            <li>Une sauvegarde qu'on ne peut pas <span class="font-semibold">restaurer</span> ne
                sert à rien, et personne ne s'en aperçoit avant le jour où il faut le faire.</li>
            <li>Un dump contient tout : adresses, mots de passe hachés, journaux d'audit,
                dossiers de vérification. Le proposer au téléchargement depuis un navigateur
                créerait une fuite bien pire que l'absence de sauvegarde.</li>
        </ul>
    </div>

    <x-kpi-grid variant="staff" :cols="3">
        <x-kpi variant="staff" :value="number_format($indicateurs['nombre'], 0, ',', ' ')"
               label="Fichiers présents" trend="Sur le disque de sauvegarde" />

        {{-- L'ÂGE est le seul chiffre qui compte : « 12 sauvegardes » ne dit
             rien si la plus récente date de six mois. --}}
        <x-kpi variant="staff" :value="$indicateurs['age_jours'] !== null ? $indicateurs['age_jours'].' j' : '—'"
               label="Âge de la plus récente" trend="Le chiffre qui compte" />

        <x-kpi variant="staff" :value="$indicateurs['poids'] > 0 ? number_format($indicateurs['poids'] / 1e6, 1, ',', ' ').' Mo' : '—'"
               label="Poids total" trend="Tous fichiers confondus" />
    </x-kpi-grid>

    @if ($fichiers === [])
        <x-empty-state variant="staff" icon="backup"
                       title="Aucune sauvegarde sur le disque"
                       description="Le dossier est vide ou n'existe pas. Aucune sauvegarde n'est produite par la plateforme : elle doit être mise en place hors d'elle — tâche cron du serveur, outil de l'hébergeur, ou sauvegarde cPanel programmée." />
    @else
        <x-premium.barre-de-liste placeholder="Rechercher un fichier…" />

        <x-data-table variant="staff" :count="$page->total()" :selectable="false">
            <x-slot:head>
                <th scope="col">Fichier</th>
                <th scope="col">Date</th>
                <th scope="col">Âge</th>
                <th scope="col">Taille</th>
            </x-slot:head>

            @foreach ($page as $fichier)
                <tr>
                    <td class="font-mono text-sm text-encre dark:text-white">{{ $fichier['nom'] }}</td>
                    <td class="whitespace-nowrap text-sm text-encre-2 dark:text-nuit-2">
                        {{ $fichier['date']?->translatedFormat('j M Y · H:i') }}
                    </td>
                    <td class="whitespace-nowrap text-sm text-encre-3 dark:text-nuit-3">
                        {{ $fichier['date']?->diffForHumans() }}
                    </td>
                    <td class="whitespace-nowrap text-sm text-encre-3 dark:text-nuit-3">
                        {{ number_format($fichier['taille'] / 1e6, 1, ',', ' ') }} Mo
                    </td>
                </tr>
            @endforeach
        </x-data-table>

        <x-premium.pied-de-liste :liste="$page" />
    @endif

    <p class="text-xs text-encre-3 dark:text-nuit-3 mt-4 mb-0">
        Aucun téléchargement depuis cet écran : un dump complet servi par une URL serait
        une fuite intégrale. Les fichiers se récupèrent par le canal d'administration du serveur.
    </p>
</div>
