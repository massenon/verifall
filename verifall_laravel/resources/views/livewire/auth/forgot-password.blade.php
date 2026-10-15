{{-- Mot de passe oublié — transposition de mot-de-passe-oublie.html.
     La formulation « lien » du prototype devient « code » : le mécanisme
     imposé par SECURITE_RGPD est un OTP, pas un lien cliquable. --}}
<div>
    <div class="text-center lg:text-left">
        <a href="{{ route('public.home') }}"><img src="{{ asset('assets/images/logo.png') }}" alt="Vérif'All" class="h-[50px] w-auto mb-6 mx-auto lg:mx-0"></a>
        @if ($etape === 1)
            <h2 class="text-3xl font-display font-bold text-heading mb-0">Mot de passe oublié ?</h2>
            <p class="mb-6">Saisissez l'adresse e-mail de votre compte : nous vous enverrons un code de vérification.</p>
        @else
            <h2 class="text-3xl font-display font-bold text-heading mb-0">Vérification</h2>
        @endif
    </div>

    @if ($etape === 1)
        <form wire:submit="demander">
            <div class="form-floating mb-4">
                <input type="email" id="fp-email" wire:model.blur="email"
                       placeholder="nom@exemple.com" autocomplete="email"
                       @error('email') aria-invalid="true" @enderror>
                <label for="fp-email">Adresse Email</label>
                @error('email') <p class="mt-1 text-sm text-vfalse">{{ $message }}</p> @enderror
            </div>

            <button type="submit"
                    wire:loading.attr="disabled" wire:target="demander"
                    class="w-full inline-flex justify-center items-center rounded-lg bg-dark text-white font-semibold px-4 py-3 hover:bg-black transition-colors disabled:opacity-60">
                <span wire:loading.remove wire:target="demander">Envoyer le code</span>
                <span wire:loading wire:target="demander">Envoi…</span>
            </button>
        </form>
    @else
        {{-- Message rigoureusement identique que le compte existe ou non :
             c'est lui qui empêche d'utiliser cet écran pour savoir si une
             adresse est inscrite. --}}
        <p class="mb-6" role="status">{{ $this->messageNeutre() }}</p>

        <form wire:submit="reinitialiser" class="space-y-3">
            <div class="form-floating">
                <input type="text" id="fp-code" wire:model.blur="code"
                       inputmode="numeric" autocomplete="one-time-code" maxlength="6"
                       placeholder="Code à 6 chiffres">
                <label for="fp-code">Code à 6 chiffres</label>
                @error('code') <p class="mt-1 text-sm text-vfalse">{{ $message }}</p> @enderror
            </div>

            <div>
                <x-champ-mot-de-passe id="fp-password" label="Nouveau mot de passe"
                                      modele="motDePasse" autocomplete="new-password" />
                @error('motDePasse') <p class="mt-1 text-sm text-vfalse">{{ $message }}</p> @enderror
            </div>

            <x-champ-mot-de-passe id="fp-password-confirm" label="Confirmation"
                                  modele="motDePasseConfirmation" autocomplete="new-password" />

            <p class="text-xs text-body/70">
                Par sécurité, la modification fermera vos autres sessions ouvertes.
            </p>

            <button type="submit"
                    class="w-full inline-flex justify-center items-center rounded-lg bg-dark text-white font-semibold px-4 py-3 hover:bg-black transition-colors">
                Changer mon mot de passe
            </button>
        </form>
    @endif

    <div class="mt-6 text-center text-sm">
        <span>Retour à la <a href="{{ route('login') }}" class="font-bold text-primary">connexion</a> · Pas encore de compte ? <a href="{{ route('register') }}" class="font-bold text-primary">Créer un compte</a></span>
    </div>
</div>
