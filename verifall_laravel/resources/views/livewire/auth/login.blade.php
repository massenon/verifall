{{-- Connexion — transposition de connexion.html. --}}
<div>
    <div class="text-center lg:text-left">
        <a href="{{ route('public.home') }}"><img src="{{ asset('assets/images/logo.png') }}" alt="Vérif'All" class="h-[50px] w-auto mb-6 mx-auto lg:mx-0"></a>
        <h2 class="text-3xl font-display font-bold text-heading mb-0">Connectez vous !</h2>
        <p class="mb-6">Entrez vos détails pour accéder à votre espace.</p>
    </div>

    <form wire:submit="seConnecter">
        <div class="form-floating mb-3">
            <input type="email" class="peer" id="email" wire:model.blur="email"
                   placeholder="nom@exemple.com" required autocomplete="email"
                   @error('email') aria-invalid="true" aria-describedby="err-email" @enderror>
            <label for="email">Adresse Email</label>
        </div>

        {{-- autocomplete="current-password" : ne jamais le désactiver, cela
             casserait les gestionnaires de mots de passe et pousserait les
             utilisateurs vers des mots de passe mémorisables donc faibles. --}}
        {{--
          | Les deux refus que le middleware oppose, enfin DITS.
          |
          | `compte_inactif` était posé en session depuis l'origine et n'était
          | affiché nulle part : la personne était renvoyée à la connexion sans
          | un mot, et réessayait le même mot de passe. Sixième occurrence sur ce
          | chantier d'une donnée écrite que rien ne lit.
          |
          | `compte_suspendu` ne reprend PAS le motif : ce bandeau s'affiche
          | après une déconnexion, sans mot de passe fourni. Le motif, lui,
          | n'apparaît qu'à qui a prouvé connaître le compte — voir
          | `CompteSuspendu`.
        --}}
        @if (session('compte_inactif'))
            <p class="text-sm text-vfalse mb-3" role="alert">
                Votre compte n'est pas encore activé. Saisissez le code reçu par courriel
                à l'inscription, ou recommencez l'inscription pour en recevoir un nouveau.
            </p>
        @endif

        @if (session('compte_suspendu'))
            <p class="text-sm text-vfalse mb-3" role="alert">
                Ce compte est suspendu. Le motif vous a été envoyé par courriel ;
                écrivez-nous si vous pensez qu'il s'agit d'une erreur.
            </p>
        @endif

        <div class="mb-3">
            <x-champ-mot-de-passe id="psw-input" label="Mot de passe" modele="motDePasse" :requis="true" />
        </div>

        {{-- Erreur unique et générique, affichée sous les deux champs : préciser
             lequel est en cause transformerait la page en oracle d'énumération. --}}
        @error('email')
            <p id="err-email" class="text-sm text-vfalse" role="alert">{{ $message }}</p>
        @enderror
        @error('motDePasse')
            <p class="text-sm text-vfalse" role="alert">{{ $message }}</p>
        @enderror

        <div class="mb-6 mt-6 flex items-center justify-between text-sm">
            <label class="inline-flex items-center gap-2 cursor-pointer">
                <input type="checkbox" class="form-check-input" id="remember" wire:model="seSouvenir">
                <span>Se souvenir de moi</span>
            </label>
            <a href="{{ route('password.request') }}" class="text-primary underline font-medium">Mot de passe Oublié ?</a>
        </div>

        <button type="submit"
                wire:loading.attr="disabled" wire:target="seConnecter"
                class="w-full inline-flex justify-center items-center rounded-lg bg-dark text-white font-semibold px-4 py-3 hover:bg-black transition-colors disabled:opacity-60">
            <span wire:loading.remove wire:target="seConnecter">Se connecter</span>
            <span wire:loading wire:target="seConnecter">Connexion…</span>
        </button>

        <x-auth.boutons-sociaux />
    </form>

    <div class="mt-6 text-center text-sm">
        <span>Pas encore de compte ? <a href="{{ route('register') }}" class="font-bold text-primary">Créer un compte ici</a></span>
    </div>
</div>
