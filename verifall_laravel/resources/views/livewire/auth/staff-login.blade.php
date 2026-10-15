<div>
    <img src="{{ asset('assets/images/logo.png') }}" alt="Vérif'All" class="h-10 w-auto mb-5">

    @if (! $attenteCode)
        <h1 class="text-xl font-display font-bold text-[#0F172A] mb-1">Administration</h1>
        <p class="text-sm text-[#475569] mb-5">Accès réservé aux membres de l'équipe.</p>

        <form wire:submit="verifierIdentifiants">
            <div class="ffield mb-3">
                <input id="a-email" type="email" wire:model.blur="email"
                       placeholder=" " autocomplete="username">
                <label for="a-email">Adresse e-mail administrateur</label>
            </div>

            {{-- L'œil, enfin. Il manquait ici seul, et j'avais renoncé en
                 supposant que `.ffield` ne supporterait pas un bouton en
                 position absolue. Vérification faite, `.ffield` EST
                 `position: relative` — la règle vient de `citizen.css`, que
                 cette mise en page charge aussi. --}}
            <div class="mb-4">
                <x-champ-mot-de-passe variante="staff" id="a-pass"
                                      label="Mot de passe" modele="motDePasse" />
            </div>

            @error('email') <p class="mb-3 text-sm text-vfalse" role="alert">{{ $message }}</p> @enderror
            @error('motDePasse') <p class="mb-3 text-sm text-vfalse" role="alert">{{ $message }}</p> @enderror

            <button type="submit" class="st-btn st-btn-primary w-full justify-center">
                Accéder au back-office
            </button>
        </form>
    @elseif ($enrolement)
        {{-- ENRÔLEMENT — première connexion d'un compte staff.
             Le back-office exige un second facteur ; sans cet écran, un compte
             neuf n'avait aucun moyen d'en obtenir un, et restait dehors. --}}
        <h1 class="text-xl font-display font-bold text-[#0F172A] mb-1">Activer la double authentification</h1>
        <p class="text-sm text-[#475569] mb-5">
            Le back-office l'exige. Scannez ce code avec votre application
            d'authentification, puis saisissez le code qu'elle affiche.
        </p>

        <div class="st-card p-4 mb-4 text-center">
            {{-- QR rendu par le SERVEUR : un code dessiné en JavaScript
                 disparaît quand le script échoue, et on ne le photographie pas
                 avec le téléphone qu'on est en train de configurer. --}}
            <div class="inline-block bg-white p-2 rounded-xl border border-[#E2E8F0]"
                 role="img" aria-label="Code à scanner avec votre application d'authentification">
                {!! $qrCode !!}
            </div>
            <p class="mt-3 text-xs text-[#64748B] mb-1">Impossible de scanner ? Saisissez cette clé :</p>
            <p class="font-mono text-sm text-[#0F172A] break-all mb-0">{{ $secret }}</p>
        </div>

        @if ($codesRecuperation !== [])
            {{-- Montrés UNE SEULE FOIS. Ils ne sont conservés que hachés :
                 personne ne pourra les réafficher, pas même un administrateur. --}}
            <div class="st-card p-4 mb-4 !border-[#D6A419]/40" style="background:#FFFBEB">
                <p class="text-sm font-bold text-[#0F172A] mb-1">Vos codes de récupération</p>
                <p class="text-xs text-[#64748B] mb-3">
                    Notez-les maintenant : ils ne seront plus jamais affichés. Chacun
                    ne sert qu'une fois, et c'est votre seule issue si vous perdez
                    votre téléphone.
                </p>
                <ul class="grid grid-cols-2 gap-1 font-mono text-sm text-[#0F172A] list-none pl-0 mb-0">
                    @foreach ($codesRecuperation as $codeRecuperation)
                        <li>{{ $codeRecuperation }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form wire:submit="verifierCode">
            <div class="ffield mb-4">
                <input id="a-enrol" type="text" wire:model.blur="code"
                       placeholder=" " inputmode="numeric" autocomplete="one-time-code"
                       maxlength="12" autofocus>
                <label for="a-enrol">Code affiché par l'application</label>
            </div>

            @error('code') <p class="mb-3 text-sm text-vfalse" role="alert">{{ $message }}</p> @enderror

            <button type="submit" class="st-btn st-btn-primary w-full justify-center mb-3">
                Activer et se connecter
            </button>

            <button type="button" wire:click="annuler"
                    class="text-sm text-[#059669] font-semibold underline">
                ← Recommencer
            </button>
        </form>
    @else
        <h1 class="text-xl font-display font-bold text-[#0F172A] mb-1">Vérification en deux étapes</h1>
        <p class="text-sm text-[#475569] mb-5">
            Saisissez le code affiché par votre application d'authentification.
        </p>

        <form wire:submit="verifierCode">
            <div class="ffield mb-4">
                <input id="a-totp" type="text" wire:model.blur="code"
                       placeholder=" " inputmode="numeric" autocomplete="one-time-code"
                       maxlength="12" autofocus>
                <label for="a-totp">Code à 6 chiffres</label>
            </div>

            @error('code') <p class="mb-3 text-sm text-vfalse" role="alert">{{ $message }}</p> @enderror

            <button type="submit" class="st-btn st-btn-primary w-full justify-center mb-3">
                Valider
            </button>

            <p class="text-xs text-[#64748B]">
                Application indisponible ? Saisissez l'un de vos codes de récupération.
            </p>

            <button type="button" wire:click="annuler"
                    class="mt-3 text-sm text-[#059669] font-semibold underline">
                ← Recommencer
            </button>
        </form>
    @endif

    <a href="{{ route('public.home') }}" class="mt-6 block text-sm text-[#64748B]">
        ← Retour au site public
    </a>
</div>
