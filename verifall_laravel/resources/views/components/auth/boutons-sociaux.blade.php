{{--
  | « ou continuer avec » — transposition de connexion.html / inscription.html.
  |
  | Le prototype pose deux `<button type="button">` inertes. Ils deviennent des
  | LIENS vers la redirection OAuth : un bouton hors formulaire qui ne soumet
  | rien n'a aucune raison d'être un bouton, et un lien se partage, s'ouvre dans
  | un onglet, s'annonce correctement aux lecteurs d'écran.
  |
  | Rien ne s'affiche si aucun fournisseur n'est configuré — pas même le
  | séparateur. Proposer une connexion qui se solde par une erreur du
  | fournisseur est pire que ne rien proposer.
--}}
@php($fournisseurs = App\Enums\FournisseurSocial::disponibles())

@if($fournisseurs !== [])
  <div class="flex items-center gap-3 my-5" aria-hidden="true">
    <span class="flex-1 h-px bg-line"></span>
    <span class="text-xs text-body/60">{{ __('composants.sociaux_ou_continuer_avec') }}</span>
    <span class="flex-1 h-px bg-line"></span>
  </div>

  <div class="grid grid-cols-{{ count($fournisseurs) > 1 ? '2' : '1' }} gap-3 mb-5">
    @foreach($fournisseurs as $fournisseur)
      <a href="{{ route('social.redirect', $fournisseur->value) }}"
         class="inline-flex items-center justify-center gap-2 rounded-lg border border-line bg-white px-4 py-2.5 text-sm font-semibold text-heading hover:bg-gray-50 transition-colors min-h-[44px]"
         rel="nofollow">
        @if($fournisseur === App\Enums\FournisseurSocial::Google)
          <svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.1H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.9 1.2 8 3l5.7-5.7C34.1 6.1 29.3 4 24 4 13 4 4 13 4 24s9 20 20 20 20-9 20-20c0-1.3-.1-2.6-.4-3.9z"/><path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.9 1.2 8 3l5.7-5.7C34.1 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.6 39.6 16.3 44 24 44z"/><path fill="#1976D2" d="M43.6 20.1H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C36.9 40.4 44 36 44 24c0-1.3-.1-2.6-.4-3.9z"/></svg>
        @else
          <svg width="18" height="18" viewBox="0 0 16 16" fill="#1877F2" aria-hidden="true"><path d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951"/></svg>
        @endif
        {{ $fournisseur->libelle() }}
      </a>
    @endforeach
  </div>

  {{-- Le consentement est recueilli ici aussi. Un compte créé par Google sans
       trace de consentement serait un compte non conforme : la mention doit
       donc être lue AVANT le clic, pas après. --}}
  <p class="text-xs text-body/70 text-center mb-5">
    {{ __('composants.sociaux_en_continuant_vous_acceptez_nos') }}
    <a href="{{ route('public.legal') }}" class="text-primary underline">{{ __('composants.sociaux_conditions_d_utilisation') }}</a>
    {{ __('composants.sociaux_et_notre') }}
    <a href="{{ route('public.privacy') }}" class="text-primary underline">{{ __('composants.sociaux_politique_de_confidentialite') }}</a>.
  </p>
@endif
