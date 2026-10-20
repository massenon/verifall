<div>
    <a href="{{ route('staff.login') }}" class="btn-fantome btn-sm -ml-2.5 mb-4"><x-lucide name="arrow-left" />Retour à la connexion</a>

    @if ($etape === 1)
        <h1 class="font-titre text-[28px] font-semibold leading-tight">Mot de passe oublié</h1>
        <p class="mt-2 texte-2">Saisissez l’adresse de votre compte : nous y envoyons un code de vérification.</p>

        <form wire:submit="demander" class="mt-7 space-y-5" novalidate>
            <div>
                <label for="f-email" class="etiquette">Adresse électronique</label>
                <input id="f-email" type="email" wire:model.blur="email" class="champ !h-11 @error('email') champ-erreur @enderror" autocomplete="username" inputmode="email" placeholder="prenom.nom@verifall.com">
                @error('email') <p class="aide-erreur" role="alert"><x-lucide name="circle-alert" class="w-3.5 h-3.5" />{{ $message }}</p> @enderror
            </div>
            <button type="submit" class="btn-primaire w-full !h-11 text-[15px]" wire:loading.attr="disabled">Envoyer le code</button>
        </form>
    @else
        <h1 class="font-titre text-[28px] font-semibold leading-tight">Vérification</h1>
        {{-- Message identique que le compte existe ou non : il empêche d'utiliser
             cet écran pour savoir si une adresse appartient à l'équipe. --}}
        <p class="mt-2 texte-2" role="status">Si cette adresse correspond à un compte, un code à 6 chiffres vient d’y être envoyé. Il expire dans quelques minutes.</p>

        <form wire:submit="reinitialiser" class="mt-7 space-y-5" novalidate>
            <div>
                <label for="f-code" class="etiquette">Code à 6 chiffres</label>
                <input id="f-code" type="text" wire:model.blur="code" class="champ !h-11 @error('code') champ-erreur @enderror" inputmode="numeric" autocomplete="one-time-code" maxlength="6">
                @error('code') <p class="aide-erreur" role="alert"><x-lucide name="circle-alert" class="w-3.5 h-3.5" />{{ $message }}</p> @enderror
            </div>
            <div>
                <x-champ-mot-de-passe variante="console" id="f-pass" label="Nouveau mot de passe" modele="motDePasse" autocomplete="new-password" />
                @error('motDePasse') <p class="aide-erreur" role="alert"><x-lucide name="circle-alert" class="w-3.5 h-3.5" />{{ $message }}</p> @enderror
            </div>
            <x-champ-mot-de-passe variante="console" id="f-pass2" label="Confirmation" modele="motDePasseConfirmation" autocomplete="new-password" />
            <p class="aide">Toutes vos sessions seront fermées. Vous repasserez par la double authentification à la connexion.</p>
            <button type="submit" class="btn-primaire w-full !h-11 text-[15px]" wire:loading.attr="disabled">Changer le mot de passe</button>
        </form>
    @endif
</div>
