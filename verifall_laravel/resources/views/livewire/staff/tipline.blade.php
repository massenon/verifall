{{--
  | « Tipline WhatsApp » — l'état du dispositif.
  |
  | Un écran d'ÉTAT, pas un écran de réglages. Les trois valeurs se saisissent
  | dans « Paramètres », onglet API externes, où elles sont chiffrées et jamais
  | renvoyées au navigateur. Les dupliquer ici aurait signifié deux endroits où
  | écrire un jeton, donc un endroit où l'on oublie de le changer.
--}}
<div>

<x-premium.entete titre="Tipline WhatsApp" :fil="['Tipline WhatsApp']">
        Au Bénin, la désinformation ne circule pas sur le web : elle circule sur WhatsApp, en images, en captures d'écran et en messages vocaux transférés. Une tipline transforme la vérification d'une démarche en un réflexe.
    </x-premium.entete>

    @if ($active)
        <section class="carte mb-6" aria-label="État de la tipline">
            <div class="flex items-center gap-3 mb-2">
                <x-badge-statut variant="staff" statut="publie" label="En service" />
                <span class="text-sm text-encre-3 dark:text-nuit-3">Numéro déclaré : {{ $numero }}</span>
            </div>
            <p class="text-sm text-encre dark:text-white mb-0">
                Les trois valeurs sont renseignées. Le rappel peut être déclaré chez Meta.
            </p>
        </section>
    @else
        {{-- L'écran dit ce qui MANQUE, nommément. Un module absent et un module
             en attente de configuration se ressemblent exactement : sans cette
             liste, on conclut que la fonctionnalité n'a pas été faite. --}}
        <section class="carte mb-6" aria-label="État de la tipline">
            <div class="flex items-center gap-3 mb-3">
                <x-badge-statut variant="staff" statut="archive" label="Désactivée" />
                <span class="text-sm text-encre-3 dark:text-nuit-3">{{ count($manquants) }} valeur(s) à renseigner</span>
            </div>

            <p class="text-sm text-encre dark:text-white mb-3">
                La tipline reste éteinte tant que les valeurs ci-dessous ne sont pas fournies.
                Ce qui manque n'est pas du code : ce sont des identifiants délivrés par Meta.
            </p>

            <ul class="mb-4 space-y-1">
                @foreach ($manquants as $cle => $libelle)
                    <li class="text-sm text-encre dark:text-white flex items-center gap-2">
                        <x-lucide name="circle" class="w-4 h-4 text-critique" />
                        {{ $libelle }}
                        <code class="text-xs text-encre-3 dark:text-nuit-3">{{ $cle }}</code>
                    </li>
                @endforeach
            </ul>

            <a href="{{ route('staff.settings') }}" class="btn-primaire">
                Renseigner dans les paramètres
            </a>
        </section>
    @endif

    <section class="carte" aria-label="Marche à suivre">
        <h2 class="text-lg font-titre font-bold text-encre dark:text-white mb-3">Ce qu'il faut obtenir, et dans cet ordre</h2>

        <ol class="space-y-3 mb-0">
            <li class="text-sm text-encre dark:text-white">
                <strong>1. Un compte Meta Business vérifié.</strong>
                La vérification porte sur l'organisation, pas sur la personne : elle demande
                des pièces au nom de l'éditeur. C'est l'étape la plus longue.
            </li>
            <li class="text-sm text-encre dark:text-white">
                <strong>2. Un numéro dédié à la tipline.</strong>
                Il ne peut pas être un numéro déjà employé sur l'application WhatsApp
                ordinaire : le rattacher à l'API le retire de l'application.
            </li>
            <li class="text-sm text-encre dark:text-white">
                <strong>3. Un rappel déclaré chez Meta.</strong>
                Il pointe vers cette plateforme et présente le jeton de vérification que vous
                aurez choisi. C'est ce jeton qui prouve que le rappel s'adresse bien ici.
            </li>
        </ol>
    </section>

    {{-- Ce que le module NE FAIT PAS, écrit plutôt que sous-entendu. --}}
    <section class="carte mt-6" aria-label="Limites connues">
        <h2 class="text-lg font-titre font-bold text-encre dark:text-white mb-3">Ce qui n'est pas encore branché</h2>
        <p class="text-sm text-encre-3 dark:text-nuit-3 mb-2">
            Le service porte l'état de configuration et la vérification du jeton de rappel.
            La réception des messages et l'envoi des verdicts restent à écrire : ils supposent
            le contrat d'API réel, que l'on ne peut pas éprouver sans compte ouvert.
        </p>
        <p class="text-sm text-encre-3 dark:text-nuit-3 mb-0">
            Écrire un client contre un contrat deviné produirait du code intestable, faux sur
            les détails, et à réécrire le jour de l'ouverture. Ce qui est là est ce qui peut
            l'être honnêtement.
        </p>
    </section>
</div>
