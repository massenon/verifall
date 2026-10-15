{{--
  | Inscription — stepper 4 étapes transposé du prototype.
  |
  | Classes reprises telles quelles : stepper-header, step, stepper-circle,
  | stepper-line, form-floating. L'état actif, figé dans le prototype, devient
  | ici une condition sur $etape.
--}}
<div data-stepper>

    <div class="stepper-header mb-2" role="tablist" aria-label="Étapes de l'inscription">
        @foreach ([1, 2, 3, 4] as $n)
            <div class="step {{ $etape >= $n ? 'active' : '' }}"
                 role="tab"
                 aria-selected="{{ $etape === $n ? 'true' : 'false' }}"
                 aria-label="Étape {{ $n }} sur 4">
                <span class="stepper-circle">{{ $n }}</span>
            </div>
            @if ($n < 4)
                <span class="stepper-line" aria-hidden="true"></span>
            @endif
        @endforeach
    </div>

    <a href="{{ route('public.home') }}">
        <img src="{{ asset('assets/images/logo.png') }}" alt="Vérif'All" class="h-[50px] w-auto mb-6 mt-8">
    </a>

    {{-- ═══════════════════════════════════════════ VOUS AVEZ ÉTÉ INVITÉ

         Le code déposé par `/invitation/{code}` était consommé en silence : le
         formulaire ne disait ni par qui l'on avait été invité, ni que des
         unités attendaient. Quelqu'un arrivant d'un lien WhatsApp voyait une
         inscription ordinaire.

         C'est le maillon le plus fragile du parrainage : celui qui partage
         promet quelque chose, et si la page d'arrivée n'en dit rien, c'est LUI
         qui paraît avoir menti.

         Affiché jusqu'à l'étape 3 — après, le compte est créé et le
         rattachement est fait ; l'étape 4 le redit à sa façon.              --}}
    @php $leParrain = $this->parrain(app(\App\Core\Marketing\Services\Parrainage::class)); @endphp

    @if ($leParrain && $etape < 4)
        <div class="mb-6 rounded-lg border border-primary/30 bg-primary-soft/40 p-4 flex items-start gap-3">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="currentColor" class="text-primary shrink-0 mt-0.5" aria-hidden="true">
                <path d="M12 2a10 10 0 100 20 10 10 0 000-20zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8z"/>
            </svg>
            <div class="text-sm">
                <p class="font-bold text-heading mb-0.5">{{ $leParrain->name }} vous a invité</p>
                <p class="mb-0 text-body">
                    {{ $this->unitesFilleul(app(\App\Core\Marketing\Services\Parrainage::class)) }} unités vous attendent
                    après votre première vérification.
                </p>
            </div>
        </div>
    @endif

    {{-- ------------------------------------------------ ÉTAPE 1 : IDENTITÉ --}}
    @if ($etape === 1)
        <div data-step-panel="1" class="is-active">
            <h2 class="text-3xl font-display font-bold text-heading mb-1">Créez votre compte</h2>
            <p class="mb-6">Identité de votre compte Vérif'All.</p>

            <form wire:submit="versEtape2" class="grid md:grid-cols-2 gap-3">
                @foreach ([
                    ['prenom', 'Prénom *', 'text', 'given-name'],
                    ['nom', 'Nom *', 'text', 'family-name'],
                    ['telephone', 'Téléphone *', 'tel', 'tel'],
                    ['profession', 'Profession *', 'text', 'organization-title'],
                ] as [$champ, $libelle, $type, $autocomplete])
                    <div class="form-floating">
                        <input type="{{ $type }}"
                               id="reg-{{ $champ }}"
                               wire:model.blur="{{ $champ }}"
                               placeholder="{{ $libelle }}"
                               autocomplete="{{ $autocomplete }}"
                               @error($champ) aria-invalid="true" aria-describedby="err-{{ $champ }}" @enderror>
                        <label for="reg-{{ $champ }}">{{ $libelle }}</label>
                        @error($champ)
                            <p id="err-{{ $champ }}" class="mt-1 text-sm text-vfalse">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach

                <button type="submit"
                        class="md:col-span-2 inline-flex justify-center items-center rounded-lg bg-dark text-white font-semibold px-4 py-3.5 hover:bg-black transition-colors">
                    Continuer
                </button>
            </form>

            {{-- Sur la PREMIÈRE étape seulement : plus loin, le compte est déjà
                 créé et un détour par Google le laisserait à moitié fait. --}}
            <x-auth.boutons-sociaux />
        </div>
    @endif

    {{-- ------------------------------------------------ ÉTAPE 2 : SÉCURITÉ --}}
    @if ($etape === 2)
        {{-- `is-active` sur CHAQUE panneau, et ce n'est pas décoratif.

         `verifall.css` pose `[data-step-panel] { display: none }` et ne montre
         que `.is-active`. Le gabarit ne portait la classe QUE sur l'étape 1 :
         les étapes 2, 3 et 4 étaient rendues dans le DOM et **invisibles à
         l'écran**. On remplissait l'identité, on cliquait « Continuer », le
         stepper avançait — et la page devenait blanche.

         C'est le défaut signalé, mot pour mot : « je me suis arrêté à
         l'étape 2 ». Aucun test ne pouvait le voir : le balisage EST présent,
         et `assertSee` le trouve. Seul un affichage réel le montre.

         Blade ne rend qu'un panneau à la fois (`@if ($etape === n)`) : la
         classe dit donc simplement « c'est celui-ci ». --}}
        <div data-step-panel="2" class="is-active">
            <h2 class="text-3xl font-display font-bold text-heading mb-1">Sécurité</h2>
            <p class="mb-6">Vos identifiants de connexion.</p>

            <form wire:submit="versEtape3" class="space-y-3">
                <div class="form-floating">
                    <input type="email" id="reg-email" wire:model.blur="email"
                           placeholder="Adresse Email" autocomplete="email"
                           @error('email') aria-invalid="true" @enderror>
                    <label for="reg-email">Adresse Email *</label>
                    @error('email') <p class="mt-1 text-sm text-vfalse">{{ $message }}</p> @enderror
                </div>

                {{-- autocomplete="new-password" : sans lui, les gestionnaires
                     de mots de passe proposent l'ancien au lieu d'en générer. --}}
                <x-champ-mot-de-passe id="reg-password" label="Mot de passe *"
                                      placeholder="Mot de passe" modele="motDePasse"
                                      autocomplete="new-password" />
                @error('motDePasse') <p class="mt-1 text-sm text-vfalse">{{ $message }}</p> @enderror

                {{-- Jauge de force : elle se calcule dans le navigateur, à la
                     frappe. L'envoyer au serveur à chaque caractère ferait
                     voyager le mot de passe en clair une fois par touche. --}}
                <div>
                    {{-- `id` ajouté : le témoin se visait par attribut, depuis un
                         script qui ne s'exécutait jamais sur cette étape. --}}
                    <div class="psw-meter" id="psw-meter" aria-hidden="true"></div>
                    <div class="flex items-center justify-between mt-1.5">
                        <p class="text-sm font-semibold text-heading" id="psw-meter-message" aria-live="polite"></p>
                    </div>
                </div>

                <x-champ-mot-de-passe id="reg-password-confirm" label="Confirmation *"
                                      placeholder="Confirmation" modele="motDePasseConfirmation"
                                      autocomplete="new-password" />

                {{-- ---------------------------------------------------------
                     CONSENTEMENTS — ajout au prototype, imposé par le RGPD.
                     Aucune case n'est pré-cochée : un consentement pré-coché
                     n'est pas un consentement. La newsletter est séparée des
                     obligatoires, sinon le consentement marketing ne serait
                     ni libre ni spécifique.
                --------------------------------------------------------- --}}
                <fieldset class="pt-2 space-y-2">
                    <legend class="sr-only">Consentements</legend>

                    <label class="flex items-start gap-2.5 text-sm cursor-pointer">
                        <input type="checkbox" wire:model="accepteCgu" class="mt-1 shrink-0">
                        <span>
                            J'accepte les
                            <a href="{{ route('public.terms') }}" class="text-primary underline" target="_blank" rel="noopener">conditions générales d'utilisation</a> *
                        </span>
                    </label>
                    @error('accepteCgu') <p class="text-sm text-vfalse">{{ $message }}</p> @enderror

                    <label class="flex items-start gap-2.5 text-sm cursor-pointer">
                        <input type="checkbox" wire:model="acceptePrivacy" class="mt-1 shrink-0">
                        <span>
                            J'ai lu la
                            <a href="{{ route('public.privacy') }}" class="text-primary underline" target="_blank" rel="noopener">politique de confidentialité</a> *
                        </span>
                    </label>
                    @error('acceptePrivacy') <p class="text-sm text-vfalse">{{ $message }}</p> @enderror

                    <label class="flex items-start gap-2.5 text-sm cursor-pointer">
                        <input type="checkbox" wire:model="accepteNewsletter" class="mt-1 shrink-0">
                        <span>Je souhaite recevoir la lettre d'information <span class="text-body/60">(facultatif)</span></span>
                    </label>
                </fieldset>

                <div class="flex items-center gap-3 pt-2">
                    <button type="button" wire:click="retour"
                            class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-gray-100 hover:bg-gray-200 text-heading shrink-0"
                            aria-label="Étape précédente">
                        <span aria-hidden="true">←</span>
                    </button>

                    <button type="submit"
                            wire:loading.attr="disabled" wire:target="versEtape3"
                            class="flex-1 inline-flex justify-center items-center rounded-lg bg-primary text-white font-semibold px-4 py-3.5 hover:bg-primary-dark transition-colors disabled:opacity-60">
                        <span wire:loading.remove wire:target="versEtape3">Créer mon compte</span>
                        <span wire:loading wire:target="versEtape3">Envoi du code…</span>
                    </button>
                </div>
            </form>
        </div>
    @endif

    {{-- -------------------------------------------- ÉTAPE 3 : VÉRIFICATION --}}
    @if ($etape === 3)
        {{-- `is-active` sur CHAQUE panneau, et ce n'est pas décoratif.

         `verifall.css` pose `[data-step-panel] { display: none }` et ne montre
         que `.is-active`. Le gabarit ne portait la classe QUE sur l'étape 1 :
         les étapes 2, 3 et 4 étaient rendues dans le DOM et **invisibles à
         l'écran**. On remplissait l'identité, on cliquait « Continuer », le
         stepper avançait — et la page devenait blanche.

         C'est le défaut signalé, mot pour mot : « je me suis arrêté à
         l'étape 2 ». Aucun test ne pouvait le voir : le balisage EST présent,
         et `assertSee` le trouve. Seul un affichage réel le montre.

         Blade ne rend qu'un panneau à la fois (`@if ($etape === n)`) : la
         classe dit donc simplement « c'est celui-ci ». --}}
        <div data-step-panel="3" class="is-active">
            <h2 class="text-3xl font-display font-bold text-heading mb-1">Vérification</h2>
            <p class="mb-1">
                Saisissez le code reçu par mail à <strong>{{ $email }}</strong>.
            </p>
            {{-- B-04. L'écran ne disait rien de l'attente : ni que le courriel
                 part par une file et met un instant, ni que le code expire.
                 Devant six cases vides et aucune indication, on ne sait pas
                 s'il faut patienter, renvoyer, ou recommencer. --}}
            <p class="mb-6 text-sm text-slate2">
                Il arrive en général en moins d'une minute et reste valable
                {{ \App\Core\Identity\Services\OtpService::TTL_MINUTES }} minutes.
                Pensez à regarder vos indésirables.
            </p>

            <form wire:submit="verifier">
                {{-- Six cases plutôt qu'un champ unique : c'est la forme du
                     prototype, et celle que le clavier des téléphones attend —
                     chaque chiffre saisi fait avancer le curseur tout seul. Le
                     code assemblé est recopié dans un champ caché, seul élément
                     que Livewire observe. --}}
                <div class="flex justify-between gap-2 mb-6" data-otp-groupe data-otp-cible="reg-otp">
                    @foreach (range(1, 6) as $rang)
                        <input type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code" class="otp-input" aria-label="Chiffre {{ $rang }} du code">
                    @endforeach
                </div>
                <input type="hidden" id="reg-otp" wire:model="code">
                @error('code') <p class="mb-3 text-sm text-vfalse">{{ $message }}</p> @enderror

                <button type="submit"
                        class="w-full inline-flex justify-center items-center gap-2 rounded-full bg-primary text-white font-semibold px-4 py-3.5 hover:bg-primary-dark transition-colors">
                    Valider le code
                </button>

                <div class="text-center mt-6 text-sm">
                    <p>Vous n'avez pas reçu le code ?</p>
                    {{-- Le renvoi n'avait aucun retour visible : on cliquait, la
                         page ne bougeait pas, on recliquait — jusqu'à épuiser la
                         limite de débit, qui refusait alors les renvois
                         légitimes. --}}
                    <button type="button" wire:click="renvoyer"
                            wire:loading.attr="disabled" wire:target="renvoyer"
                            class="mt-2 inline-flex items-center gap-1.5 font-bold text-primary hover:text-primary-dark disabled:opacity-60">
                        <span wire:loading.remove wire:target="renvoyer">Renvoyer le code</span>
                        <span wire:loading wire:target="renvoyer">Envoi en cours…</span>
                    </button>
                </div>
            </form>

            {{-- ══════════════════════════════════ DÉVELOPPEMENT UNIQUEMENT

                 Sans `queue:work`, ou avec un SMTP injoignable depuis le poste,
                 le code ne part jamais : l'écran attend un courriel qui
                 n'arrivera pas, et le formulaire semble cassé alors qu'il est
                 intact. C'est le symptôme signalé (B-04).

                 On affiche le MOYEN d'obtenir un code, jamais le code
                 lui-même : un gabarit qui rend un secret sous condition
                 d'environnement est à une variable mal réglée de le rendre
                 partout, sans que rien ne casse pour le signaler.           --}}
            @if (app()->environment('local'))
                <div class="mt-6 rounded-lg border border-line bg-canvas p-4 text-sm">
                    <p class="font-bold text-heading mb-1">Développement — le code n'arrive pas ?</p>
                    <p class="mb-2 text-slate2">
                        Le courriel part par la file d'attente. Vérifiez d'abord que
                        <code>php artisan queue:work</code> tourne
                        (<code>php artisan verifall:diagnostic</code> le dit), ou tirez un code
                        directement&nbsp;:
                    </p>
                    <code class="block break-all">php artisan verifall:code-otp {{ $email }}</code>
                </div>
            @endif
        </div>
    @endif

    {{-- ----------------------------------------------- ÉTAPE 4 : BIENVENUE --}}
    @if ($etape === 4)
        {{-- `is-active` sur CHAQUE panneau, et ce n'est pas décoratif.

         `verifall.css` pose `[data-step-panel] { display: none }` et ne montre
         que `.is-active`. Le gabarit ne portait la classe QUE sur l'étape 1 :
         les étapes 2, 3 et 4 étaient rendues dans le DOM et **invisibles à
         l'écran**. On remplissait l'identité, on cliquait « Continuer », le
         stepper avançait — et la page devenait blanche.

         C'est le défaut signalé, mot pour mot : « je me suis arrêté à
         l'étape 2 ». Aucun test ne pouvait le voir : le balisage EST présent,
         et `assertSee` le trouve. Seul un affichage réel le montre.

         Blade ne rend qu'un panneau à la fois (`@if ($etape === n)`) : la
         classe dit donc simplement « c'est celui-ci ». --}}
        <div data-step-panel="4" class="is-active">
            <div class="text-center">
                <span class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-primary-soft mb-5" aria-hidden="true">
                    <svg width="36" height="36" viewBox="0 0 16 16" fill="#09B850"><path d="M10.97 4.97a.75.75 0 0 1 1.07 1.05l-3.99 4.99a.75.75 0 0 1-1.08.02L4.324 8.384a.75.75 0 1 1 1.06-1.06l2.094 2.093 3.473-4.425z"/></svg>
                </span>
                <h2 class="text-3xl font-display font-bold text-heading mb-2">Bienvenue dans la communauté !</h2>
                <p class="mb-5">Votre compte Vérif'All est prêt. Vérifiez votre première information dès maintenant et développez vos réflexes de fact-checker.</p>

                {{-- CE QUE LE COMPTE A REÇU, en toutes lettres.
                     Le bonus était crédité sans que rien ne le dise — et avant
                     ce correctif il n'était même pas crédité du tout : on
                     arrivait au tableau de bord avec zéro unité, incapable de
                     lancer la vérification qu'on venait s'inscrire pour faire. --}}
                @if ($bonusRecu > 0)
                    <div class="mb-6 rounded-lg border border-primary/30 bg-primary-soft/40 p-4">
                        <p class="text-sm mb-0">
                            <strong class="text-heading">{{ $bonusRecu }} unités</strong> vous sont offertes pour commencer.
                            @if ($parrainNom)
                                {{-- Le versant filleul : ce qui reste à faire pour
                                     débloquer la récompense promise par l'invitation. --}}
                                <span class="block mt-1 text-body">
                                    {{ $this->unitesFilleul(app(\App\Core\Marketing\Services\Parrainage::class)) }} de plus
                                    après votre première vérification, grâce à l'invitation de {{ $parrainNom }}.
                                </span>
                            @endif
                        </p>
                    </div>
                @endif

                <a href="{{ route('citizen.verify') }}" class="w-full inline-flex justify-center items-center rounded-lg bg-primary text-white font-semibold px-4 py-3.5 hover:bg-primary-dark transition-colors mb-2">Vérifier ma première information</a>
                <a href="{{ route('citizen.dashboard') }}" class="w-full inline-flex justify-center items-center rounded-lg border border-line text-heading font-semibold px-4 py-3.5 hover:bg-canvas transition-colors">Accéder à mon espace</a>
            </div>
        </div>
    @endif

    @push('scripts')
        <script src="{{ asset('assets/js/pages/saisie-code.js') }}" defer></script>
    @endpush

    @if ($etape < 3)
        <div class="mt-6 text-center text-sm">
            <span>Déjà inscrit ? <a href="{{ route('login') }}" class="font-bold text-primary">Connectez-vous</a></span>
        </div>
    @endif
</div>
