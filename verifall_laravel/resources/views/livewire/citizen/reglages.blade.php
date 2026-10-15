{{--
  | « Réglages » — transposition de compte-user/reglages.html.
  |
  | Structure, classes et ordre des blocs repris du prototype. Les
  | interrupteurs gardent leur apparence ; deux d'entre eux — 2FA et lettre
  | d'information — déclenchent autre chose qu'une simple bascule, pour les
  | raisons expliquées dans le composant.
  |
  | Les blocs « Données personnelles » et « Zone de danger » arrivent avec
  | l'export et la suppression RGPD : les afficher avec des boutons inertes
  | vaudrait moins que de ne pas les afficher.
--}}
<div>
  <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4 mb-6">
    <div>
      <nav class="text-xs text-slate2 mb-2" aria-label="{{ __('cit_reglages.fil_d_ariane') }}">
        <a href="{{ route('citizen.dashboard') }}" class="hover:text-primary">{{ __('cit_reglages.dashboard') }}</a>
        <span class="text-line" aria-hidden="true">/</span>
        <span class="text-ink font-medium">{{ __('cit_reglages.reglages') }}</span>
      </nav>
      <h1 class="text-2xl md:text-3xl font-display font-bold text-ink leading-tight">{{ __('cit_reglages.reglages') }}</h1>
      <p class="text-slate2 mt-1 mb-0">{{ __('cit_reglages.securite_notifications_confidentialite_et_gestion_du_compte') }}</p>
    </div>
  </div>

  <div class="grid lg:grid-cols-2 gap-6 items-start">

    {{-- ═══════════════════════════════════════════════════════ Sécurité --}}
    <div class="card p-6 sm:p-8">
      <h2 class="text-lg font-display font-bold text-ink mb-5">{{ __('cit_reglages.securite') }}</h2>

      <div class="flex items-start justify-between gap-4 pb-4 border-b border-line">
        <div>
          <p class="text-base font-bold text-ink mb-0.5">{{ __('cit_reglages.double_authentification_2fa') }}</p>
          <p class="text-sm text-slate2 mb-0">{{ __('cit_reglages.code_de_confirmation_demande_a_chaque_connexion') }}</p>
        </div>
        <button type="button" role="switch" id="tg-2fa"
                aria-checked="{{ $deuxFacteursActive ? 'true' : 'false' }}"
                wire:click="{{ $deuxFacteursActive ? 'ouvrirDesactivation2fa' : 'activer2fa' }}"
                class="vf-switch {{ $deuxFacteursActive ? 'is-on' : '' }}"
                aria-label="{{ __('cit_reglages.activer_desactiver_la_double_authentification') }}">
          <span class="vf-switch-dot"></span>
        </button>
      </div>

      @php($alertes = App\Enums\PreferenceNotification::AlertesConnexion)
      <div class="flex items-start justify-between gap-4 py-4 border-b border-line">
        <div>
          <p class="text-base font-bold text-ink mb-0.5">{{ $alertes->libelle() }}</p>
          <p class="text-sm text-slate2 mb-0">{{ $alertes->description() }}</p>
        </div>
        <button type="button" role="switch" id="{{ $alertes->idBouton() }}"
                aria-checked="{{ $preferences[$alertes->value] ? 'true' : 'false' }}"
                wire:click="basculer('{{ $alertes->value }}')"
                class="vf-switch {{ $preferences[$alertes->value] ? 'is-on' : '' }}"
                aria-label="{{ __('cit_reglages.activer_desactiver') }}">
          <span class="vf-switch-dot"></span>
        </button>
      </div>

      {{-- Sessions réelles : le pilote de session est la base de données. --}}
      <div class="py-4">
        <p class="text-base font-bold text-ink mb-3">{{ __('cit_reglages.sessions_actives') }}</p>

        @foreach($sessions as $session)
          <div class="flex items-center gap-3 mb-3 last:mb-0" wire:key="session-{{ $session['id'] }}">
            <span aria-hidden="true">{{ in_array($session['appareil'], ['Android', 'iOS'], true) ? '📱' : '💻' }}</span>
            <p class="text-sm text-ink mb-0 flex-1">
              {{ $session['navigateur'] }} / {{ $session['appareil'] }}
              @if(! $session['courante']) · {{ $session['derniere'] }} @endif
            </p>
            @if($session['courante'])
              <span class="rounded-full bg-primary-soft text-primary-dark text-xs font-bold px-3 py-1">{{ __('cit_reglages.session_actuelle') }}</span>
            @else
              <button type="button" class="text-sm font-bold text-ink hover:text-vfalse"
                      wire:click="fermerSession('{{ $session['id'] }}')">{{ __('cit_reglages.deconnecter') }}</button>
            @endif
          </div>
        @endforeach

        @if(count($sessions) === 1)
          <p class="text-xs text-slate2 mt-2 mb-0">{{ __('cit_reglages.aucun_autre_appareil_connecte') }}</p>
        @endif
      </div>

      <button type="button" wire:click="ouvrirModaleMotDePasse"
              class="inline-flex rounded-xl border border-primary text-primary text-sm font-semibold px-5 py-2.5 hover:bg-primary hover:text-white transition-colors">{{ __('cit_reglages.changer_mon_mot_de_passe') }}</button>
    </div>

    {{-- ══════════════════════════════════════════════════ Notifications --}}
    <div class="card p-6 sm:p-8">
      <h2 class="text-lg font-display font-bold text-ink mb-5">{{ __('cit_reglages.notifications') }}</h2>

      @foreach($canaux as $canal)
        @continue($canal === App\Enums\PreferenceNotification::AlertesConnexion)
        <div class="flex items-start justify-between gap-4 pb-4 border-b border-line first:pt-0 [&:not(:first-child)]:py-4">
          <div>
            <p class="text-base font-bold text-ink mb-0.5">{{ $canal->libelle() }}</p>
            <p class="text-sm text-slate2 mb-0">{{ $canal->description() }}</p>
          </div>
          <button type="button" role="switch" id="{{ $canal->idBouton() }}"
                  aria-checked="{{ $preferences[$canal->value] ? 'true' : 'false' }}"
                  wire:click="basculer('{{ $canal->value }}')"
                  class="vf-switch {{ $preferences[$canal->value] ? 'is-on' : '' }}"
                  aria-label="{{ __('cit_reglages.activer_desactiver') }}">
            <span class="vf-switch-dot"></span>
          </button>
        </div>
      @endforeach

      <div class="flex items-start justify-between gap-4 pt-4">
        <div>
          <p class="text-base font-bold text-ink mb-0.5">{{ __('cit_reglages.lettre_d_information') }}</p>
          <p class="text-sm text-slate2 mb-0">{{ __('cit_reglages.le_recap_mensuel_des_rumeurs_verifiees') }}</p>
        </div>
        <button type="button" role="switch" id="tg-news"
                aria-checked="{{ $newsletter ? 'true' : 'false' }}"
                wire:click="basculerNewsletter"
                class="vf-switch {{ $newsletter ? 'is-on' : '' }}"
                aria-label="{{ __('cit_reglages.activer_desactiver') }}">
          <span class="vf-switch-dot"></span>
        </button>
      </div>
      <p class="text-xs text-slate2 mt-3 mb-0">{{ __('cit_reglages.votre_accord_et_son_retrait_sont_horodates') }}</p>
    </div>

    {{-- ═══════════════════════════════════════════ Données personnelles --}}
    <div class="card p-6 sm:p-8">
      <h2 class="text-lg font-display font-bold text-ink mb-4">{{ __('cit_reglages.donnees_personnelles') }}</h2>
      <p class="text-sm text-slate2 mb-5">{{ __('cit_reglages.conformement_au_code_du_numerique_beninois_vous') }}</p>

      <div class="flex flex-wrap items-center gap-4">
        <button type="button" wire:click="exporterMesDonnees"
                class="inline-flex rounded-xl border border-primary text-primary text-sm font-semibold px-5 py-2.5 hover:bg-primary hover:text-white transition-colors">{!! __('cit_reglages.exporter_mes_donnees_json_demande_en_cours') !!}</button>
        <a href="{{ route('public.home') }}" class="text-sm font-bold text-ink hover:text-primary">
          {{ __('cit_reglages.politique_de_confidentialite') }}
        </a>
      </div>

      @if($export)
        <div class="mt-5 pt-4 border-t border-line">
          @if($export->status === App\Models\DataExport::EN_ATTENTE)
            <p class="text-sm text-slate2 mb-0">{{ __('cit_reglages.archive_en_preparation_vous_recevrez_un_courriel') }}</p>
          @elseif($export->status === App\Models\DataExport::ECHEC)
            <p class="text-sm text-vfalse mb-0">{{ __('cit_reglages.la_preparation_a_echoue_aucune_donnee_n') }}</p>
          @elseif($export->estTelechargeable())
            <p class="text-sm text-ink mb-2">
              {{ __('cit_reglages.archive_prete') }} · {{ number_format($export->size_bytes / 1024, 0, ',', ' ') }} {{ __('cit_reglages.ko') }} ·
              {{ __('cit_reglages.expire') }} {{ $export->expires_at->diffForHumans() }}
            </p>
            {{-- Le lien est signé et de courte durée : le fichier lui-même
                 n'est jamais accessible par une URL de stockage. --}}
            <a href="{{ URL::temporarySignedRoute('citizen.export.download', $export->expires_at, ['export' => $export->uuid]) }}"
               class="inline-flex rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:bg-primary-dark">
              {{ __('cit_reglages.telecharger_l_archive') }}
            </a>
          @else
            <p class="text-sm text-slate2 mb-0">{{ __('cit_reglages.votre_precedente_archive_a_expire_et_a') }}</p>
          @endif
        </div>
      @endif
    </div>

    {{-- ═══════════════════════════════════════════════ Zone de danger --}}
    <div class="card p-6 sm:p-8 !border-vfalse/40">
      <h2 class="text-lg font-display font-bold text-vfalse mb-3">{{ __('cit_reglages.zone_de_danger') }}</h2>
      <p class="text-sm text-slate2 mb-5">{{ __('cit_reglages.la_suppression_du_compte_est_definitive_verifications') }}</p>
      <p class="text-sm text-slate2 mb-5">
        {{-- `e()` sur le paramètre, et pas seulement par principe : `__()`
             n'échappe PAS ce qu'il substitue. Ici la valeur est une constante
             entière, donc inoffensive — mais rien n'empêcherait d'y glisser un
             nom d'utilisateur demain, et le `{!! !!}` l'afficherait tel quel.
             Le HTML voulu vient du fichier de langue, jamais du paramètre. --}}
        {!! __('cit_reglages.votre_compte_sera_ferme_immediatement_mais_vos', ['jours' => e(App\Core\Identity\Services\SuppressionCompteService::JOURS_RETRACTATION)]) !!}
      </p>
      <button type="button" wire:click="ouvrirSuppression"
              class="inline-flex rounded-xl bg-vfalse text-white text-sm font-semibold px-5 py-3 hover:opacity-90">{{ __('cit_reglages.supprimer_mon_compte') }}</button>
    </div>

  </div>

  {{-- ═════════════════════════════════════════ modale : mot de passe --}}
  @if($modaleMotDePasse)
    <div class="modal-backdrop is-open" wire:key="modale-mot-de-passe">
      <div class="modal p-6" role="dialog" aria-modal="true" aria-labelledby="pw-title">
        <h2 id="pw-title" class="text-lg font-display font-bold text-ink mb-4">{{ __('cit_reglages.changer_mon_mot_de_passe') }}</h2>

        <form wire:submit="changerMotDePasse">
          <div class="ffield mb-3">
            <input id="pw-old" type="password" placeholder=" " autocomplete="current-password" wire:model="motDePasseActuel">
            <label for="pw-old">{{ __('cit_reglages.mot_de_passe_actuel') }}</label>
          </div>
          @error('motDePasseActuel') <p class="text-xs text-vfalse -mt-2 mb-3">{{ $message }}</p> @enderror

          <div class="ffield mb-3">
            <input id="pw-new" type="password" placeholder=" " autocomplete="new-password" wire:model="nouveauMotDePasse">
            {{-- 12 et non 8 : l'écran ne doit pas promettre moins que ce que la
                 règle exigera à la validation. --}}
            <label for="pw-new">{{ __('cit_reglages.nouveau_mot_de_passe_12_caracteres_min') }}</label>
          </div>
          @error('nouveauMotDePasse') <p class="text-xs text-vfalse -mt-2 mb-3">{{ $message }}</p> @enderror

          <div class="ffield mb-4">
            <input id="pw-confirm" type="password" placeholder=" " autocomplete="new-password" wire:model="confirmation">
            <label for="pw-confirm">{{ __('cit_reglages.confirmer_le_nouveau_mot_de_passe') }}</label>
          </div>

          <p class="text-xs text-slate2 mb-4">{{ __('cit_reglages.vos_autres_appareils_connectes_seront_deconnectes') }}</p>

          <div class="flex gap-2 justify-end">
            <button type="button" wire:click="fermerModaleMotDePasse"
                    class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate2 hover:bg-canvas">{{ __('cit_reglages.annuler') }}</button>
            <button type="submit"
                    class="rounded-xl bg-primary text-white px-5 py-2.5 text-sm font-semibold hover:bg-primary-dark">{!! __('cit_reglages.mettre_a_jour_mise_a_jour') !!}</button>
          </div>
        </form>
      </div>
    </div>
  @endif

  {{-- ══════════════════════════════════════════════ modale : 2FA --}}
  @if($etape2fa !== '')
    <div class="modal-backdrop is-open" wire:key="modale-2fa">
      <div class="modal p-6" role="dialog" aria-modal="true" aria-labelledby="tfa-title">

        @if($etape2fa === 'enrolement')
          <h2 id="tfa-title" class="text-lg font-display font-bold text-ink mb-2">{{ __('cit_reglages.activer_la_double_authentification') }}</h2>
          <p class="text-sm text-slate2 mb-4">{{ __('cit_reglages.scannez_ce_code_avec_google_authenticator_authy') }}</p>

          <div class="flex justify-center mb-4">
            {{-- SVG produit par bacon/bacon-qr-code : aucune image externe, et
                 le secret ne transite par aucun service tiers. --}}
            <div class="p-3 bg-white rounded-xl border border-line">
              {!! app(App\Core\Identity\Services\TwoFactorService::class)->qrCodeSvg($utilisateur) !!}
            </div>
          </div>

          <form wire:submit="confirmer2fa">
            <div class="ffield mb-2">
              <input id="tfa-code" type="text" inputmode="numeric" maxlength="6" placeholder=" "
                     autocomplete="one-time-code" wire:model="code2fa">
              <label for="tfa-code">{{ __('cit_reglages.code_a_6_chiffres') }}</label>
            </div>
            @error('code2fa') <p class="text-xs text-vfalse mb-3">{{ $message }}</p> @enderror

            <div class="flex gap-2 justify-end mt-4">
              <button type="button" wire:click="fermer2fa"
                      class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate2 hover:bg-canvas">{{ __('cit_reglages.annuler') }}</button>
              <button type="submit"
                      class="rounded-xl bg-primary text-white px-5 py-2.5 text-sm font-semibold hover:bg-primary-dark">{{ __('cit_reglages.confirmer') }}</button>
            </div>
          </form>

        @elseif($etape2fa === 'codes')
          <h2 id="tfa-title" class="text-lg font-display font-bold text-ink mb-2">{{ __('cit_reglages.vos_codes_de_recuperation') }}</h2>
          <p class="text-sm text-slate2 mb-4">{!! __('cit_reglages.conservez_les_hors_de_votre_telephone_ils') !!}</p>

          <ul class="grid grid-cols-2 gap-2 mb-5 list-none p-0">
            @foreach($codesRecuperation as $code)
              <li class="rounded-xl bg-canvas border border-line px-3 py-2 text-sm font-mono text-ink text-center">{{ $code }}</li>
            @endforeach
          </ul>

          <div class="flex justify-end">
            <button type="button" wire:click="fermer2fa"
                    class="rounded-xl bg-primary text-white px-5 py-2.5 text-sm font-semibold hover:bg-primary-dark">{{ __('cit_reglages.je_les_ai_notes') }}</button>
          </div>

        @else
          <h2 id="tfa-title" class="text-lg font-display font-bold text-vfalse mb-2">{{ __('cit_reglages.desactiver_la_double_authentification') }}</h2>
          <p class="text-sm text-slate2 mb-4">{{ __('cit_reglages.votre_compte_ne_sera_plus_protege_que') }}</p>

          <form wire:submit="desactiver2fa">
            <div class="ffield mb-3">
              <input id="tfa-pw" type="password" placeholder=" " autocomplete="current-password" wire:model="motDePasse2fa">
              <label for="tfa-pw">{{ __('cit_reglages.mot_de_passe') }}</label>
            </div>
            @error('motDePasse2fa') <p class="text-xs text-vfalse -mt-2 mb-3">{{ $message }}</p> @enderror

            <div class="ffield mb-2">
              <input id="tfa-off-code" type="text" placeholder=" " autocomplete="one-time-code" wire:model="code2fa">
              <label for="tfa-off-code">{{ __('cit_reglages.code_en_cours_ou_code_de_recuperation') }}</label>
            </div>
            @error('code2fa') <p class="text-xs text-vfalse mb-3">{{ $message }}</p> @enderror

            <div class="flex gap-2 justify-end mt-4">
              <button type="button" wire:click="fermer2fa"
                      class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate2 hover:bg-canvas">{{ __('cit_reglages.annuler') }}</button>
              <button type="submit"
                      class="rounded-xl bg-vfalse text-white px-5 py-2.5 text-sm font-semibold hover:opacity-90">{{ __('cit_reglages.desactiver') }}</button>
            </div>
          </form>
        @endif

      </div>
    </div>
  @endif

  {{-- ══════════════════════════════════ modale : suppression du compte --}}
  @if($etapeSuppression !== '')
    <div class="modal-backdrop is-open" wire:key="modale-suppression">
      <div class="modal p-6" role="dialog" aria-modal="true" aria-labelledby="del-title">

        @if($etapeSuppression === 'confirmation')
          <h2 id="del-title" class="text-lg font-display font-bold text-vfalse mb-2">{{ __('cit_reglages.supprimer_definitivement_le_compte') }}</h2>
          <p class="text-sm text-slate2 mb-4">{!! __('cit_reglages.tapez_supprimer_et_confirmez_avec_votre_mot') !!}</p>

          <form wire:submit="verifierSuppression">
            <div class="ffield mb-2">
              <input id="del-confirm" type="text" placeholder=" " autocomplete="off" wire:model="motSuppression">
              <label for="del-confirm">{{ __('cit_reglages.tapez_supprimer') }}</label>
            </div>
            @error('motSuppression') <p class="text-xs text-vfalse mb-3">{{ $message }}</p> @enderror

            <div class="ffield mb-2 mt-3">
              <input id="del-pw" type="password" placeholder=" " autocomplete="current-password" wire:model="motDePasseSuppression">
              <label for="del-pw">{{ __('cit_reglages.mot_de_passe') }}</label>
            </div>
            @error('motDePasseSuppression') <p class="text-xs text-vfalse mb-3">{{ $message }}</p> @enderror

            <div class="flex gap-2 justify-end mt-4">
              <button type="button" wire:click="fermerSuppression"
                      class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate2 hover:bg-canvas">{{ __('cit_reglages.annuler') }}</button>
              <button type="submit"
                      class="rounded-xl bg-vfalse text-white px-5 py-2.5 text-sm font-semibold hover:opacity-90">{{ __('cit_reglages.continuer') }}</button>
            </div>
          </form>

        @else
          <h2 id="del-title" class="text-lg font-display font-bold text-vfalse mb-2">{{ __('cit_reglages.derniere_etape') }}</h2>
          <p class="text-sm text-slate2 mb-4">
            {{ __('cit_reglages.saisissez_le_code_a_6_chiffres_envoye', ['jours' => App\Core\Identity\Services\SuppressionCompteService::JOURS_RETRACTATION]) }}
          </p>

          <form wire:submit="confirmerSuppression">
            <div class="ffield mb-2">
              <input id="del-code" type="text" inputmode="numeric" maxlength="6" placeholder=" "
                     autocomplete="one-time-code" wire:model="codeSuppression">
              <label for="del-code">{{ __('cit_reglages.code_a_6_chiffres') }}</label>
            </div>
            @error('codeSuppression') <p class="text-xs text-vfalse mb-3">{{ $message }}</p> @enderror

            <div class="flex gap-2 justify-end mt-4">
              <button type="button" wire:click="fermerSuppression"
                      class="rounded-xl px-4 py-2.5 text-sm font-semibold text-slate2 hover:bg-canvas">{{ __('cit_reglages.annuler') }}</button>
              <button type="submit"
                      class="rounded-xl bg-vfalse text-white px-5 py-2.5 text-sm font-semibold hover:opacity-90">{{ __('cit_reglages.supprimer_mon_compte') }}</button>
            </div>
          </form>
        @endif

      </div>
    </div>
  @endif
</div>
