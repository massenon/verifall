<div>
    {{-- Bandeaux de retour : sortie, inactivité, verrou fermé après 5 échecs. --}}
    @if (session('mot_de_passe_change'))
        <div class="mb-6 rounded-ctrl border px-3.5 py-3 text-sm flex items-start gap-2.5 bg-green-50 border-green-200 text-green-900 dark:bg-green-950/30 dark:border-green-900/60 dark:text-green-100" role="status"><x-lucide name="check" class="w-4 h-4 mt-0.5 shrink-0" />Mot de passe modifié. Connectez-vous avec le nouveau.</div>
    @endif
    @if (session('inactivite'))
        <div class="mb-6 rounded-ctrl border px-3.5 py-3 text-sm flex items-start gap-2.5 bg-blue-50 border-blue-200 text-blue-900 dark:bg-blue-950/30 dark:border-blue-900/60 dark:text-blue-100" role="status"><x-lucide name="clock" class="w-4 h-4 mt-0.5 shrink-0" />Votre session a expiré après une période d'inactivité. Reconnectez-vous pour continuer.</div>
    @elseif (session('verrou_ferme'))
        <div class="mb-6 rounded-ctrl border px-3.5 py-3 text-sm flex items-start gap-2.5 bg-red-50 border-red-200 text-red-900 dark:bg-red-950/40 dark:border-red-900/60 dark:text-red-100" role="status"><x-lucide name="lock" class="w-4 h-4 mt-0.5 shrink-0" />Cinq mots de passe incorrects : la session a été fermée. Reconnectez-vous.</div>
    @endif

    @if (! $attenteCode)
        <h1 class="font-titre text-[28px] font-semibold leading-tight">Connexion à la console</h1>
        <p class="mt-2 texte-2">Réservée au personnel et aux mentors de l'Académie.</p>

        <form wire:submit="verifierIdentifiants" class="mt-7 space-y-5" novalidate>
            <div>
                <label for="a-email" class="etiquette">Adresse électronique</label>
                <input id="a-email" type="email" wire:model.blur="email" class="champ !h-11 @error('email') champ-erreur @enderror"
                       autocomplete="username" inputmode="email" placeholder="prenom.nom@verifall.com" @error('email') aria-invalid="true" aria-describedby="a-email-erreur" @enderror>
                @error('email') <p id="a-email-erreur" class="aide-erreur" role="alert"><x-lucide name="circle-alert" class="w-3.5 h-3.5" />{{ $message }}</p> @enderror
            </div>
            <div>
                <x-champ-mot-de-passe variante="console" id="a-pass" label="Mot de passe" modele="motDePasse" />
                @error('motDePasse') <p class="aide-erreur" role="alert"><x-lucide name="circle-alert" class="w-3.5 h-3.5" />{{ $message }}</p> @enderror
            </div>
            <p class="text-right -mt-2"><a href="{{ route('staff.password.request') }}" class="text-sm underline underline-offset-2 texte-2 hover:text-encre">Mot de passe oublié ?</a></p>
            <button type="submit" class="btn-primaire w-full !h-11 text-[15px]" wire:loading.attr="disabled">
                {{-- Classes basculées par Livewire : aucune dépendance aux styles
                     injectés de `wire:loading`. --}}
                <span wire:loading.class="hidden" wire:target="verifierIdentifiants">Se connecter</span>
                <span class="hidden" wire:loading.class.remove="hidden" wire:target="verifierIdentifiants">Vérification…</span>
            </button>
        </form>
    @elseif ($enrolement)
        {{-- ENRÔLEMENT — première connexion d'un compte de la console : le
             second facteur est exigé, cet écran permet de l'obtenir. --}}
        <button type="button" wire:click="annuler" class="btn-fantome btn-sm -ml-2.5 mb-4"><x-lucide name="arrow-left" />Recommencer</button>
        <h1 class="font-titre text-[28px] font-semibold leading-tight">Activer la double authentification</h1>
        <p class="mt-2 texte-2">La console l'exige. Scannez ce code avec votre application d'authentification, puis saisissez le code qu'elle affiche.</p>

        <div class="carte p-4 mt-6 text-center">
            {{-- QR rendu par le SERVEUR (BaconQrCode), jamais d'une saisie. --}}
            <div class="inline-block bg-white p-2 rounded-ctrl border filet" role="img" aria-label="Code à scanner avec votre application d'authentification">
                {!! $qrCode !!}
            </div>
            <p class="mt-3 text-xs texte-3">Impossible de scanner ? Saisissez cette clé :</p>
            <p class="font-mono text-sm break-all">{{ $secret }}</p>
        </div>

        @if ($codesRecuperation !== [])
            {{-- Montrés UNE SEULE FOIS : ils ne sont conservés que hachés. --}}
            <div class="carte p-4 mt-4 !border-amber-300 !bg-amber-50 dark:!bg-amber-950/30">
                <p class="text-sm font-semibold">Vos codes de récupération</p>
                <p class="text-xs texte-2 mt-1 mb-3">Notez-les maintenant : ils ne seront plus jamais affichés. Chacun ne sert qu'une fois, et c'est votre seule issue si vous perdez votre téléphone.</p>
                <ul class="grid grid-cols-2 gap-1 font-mono text-sm">
                    @foreach ($codesRecuperation as $codeRecuperation)
                        <li>{{ $codeRecuperation }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form wire:submit="verifierCode" class="mt-6 space-y-4" novalidate>
            <div>
                <label for="a-enrol" class="etiquette">Code affiché par l'application</label>
                <input id="a-enrol" type="text" wire:model.blur="code" class="champ !h-12 text-center font-titre !text-xl tracking-[.3em] tabular-nums"
                       inputmode="numeric" autocomplete="one-time-code" maxlength="12" autofocus>
                @error('code') <p class="aide-erreur" role="alert"><x-lucide name="circle-alert" class="w-3.5 h-3.5" />{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-primaire w-full !h-11 text-[15px]">Activer et se connecter</button>
        </form>
    @else
        <button type="button" wire:click="annuler" class="btn-fantome btn-sm -ml-2.5 mb-4"><x-lucide name="arrow-left" />Changer de compte</button>
        <h1 class="font-titre text-[28px] font-semibold leading-tight">Vérification en deux étapes</h1>
        <p class="mt-2 texte-2">Saisissez le code à 6 chiffres affiché par votre application d'authentification.</p>

        <form wire:submit="verifierCode" class="mt-7 space-y-4" novalidate>
            <div>
                <label for="a-totp" class="etiquette">Code à 6 chiffres</label>
                <input id="a-totp" type="text" wire:model.blur="code" class="champ !h-14 text-center font-titre !text-2xl tracking-[.4em] tabular-nums"
                       inputmode="numeric" autocomplete="one-time-code" maxlength="12" autofocus>
                @error('code') <p class="aide-erreur" role="alert"><x-lucide name="circle-alert" class="w-3.5 h-3.5" />{{ $message }}</p> @enderror
                <p class="aide">Le code change toutes les 30 secondes. Application indisponible ? Saisissez l'un de vos codes de récupération.</p>
            </div>
            <button type="submit" class="btn-primaire w-full !h-11 text-[15px]">Vérifier et entrer</button>
        </form>
    @endif
</div>
