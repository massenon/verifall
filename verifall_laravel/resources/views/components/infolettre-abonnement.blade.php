{{--
  | Le bloc d'abonnement à l'infolettre du lundi.
  |
  | ── Pourquoi ce fichier existe, alors qu'il n'est pas au prototype ────────
  |
  | La campagne `newsletter-boom` promet « Recevez chaque lundi le récapitulatif
  | des pires intox détectées au Bénin », et son bouton pointe vers
  | `#newsletter-section` — une ancre qui n'existe NULLE PART dans le site. La
  | promesse était donc faite sans endroit où y répondre.
  |
  | Ce bloc est cet endroit. Il est autonome : à poser tel quel dans le pied de
  | page public ou sur l'accueil, sans autre ajout.
  |
  |     <x-infolettre-abonnement />
  |
  | ── Deux points à ne pas défaire ──────────────────────────────────────────
  |
  | 1. Le message de retour est le MÊME pour une adresse nouvelle et une adresse
  |    déjà abonnée. Ce n'est pas une approximation d'écriture : c'est ce qui
  |    empêche d'utiliser ce formulaire pour savoir qui est abonné.
  |
  | 2. Toutes les classes employées ici viennent du formulaire de contact
  |    (`public/contact`), donc du build Tailwind FIGÉ. Une classe inventée ne
  |    produirait aucune erreur — elle serait simplement sans effet.
--}}
<section id="newsletter-section" class="max-w-page mx-auto px-4" aria-labelledby="newsletter-titre">
    <div class="bg-white rounded-2xl shadow-cardlg border border-line p-6 sm:p-10">

        <h2 id="newsletter-titre" class="text-2xl font-display font-bold text-heading mb-2">
            {{ __('composants.infolettre_titre') }}
        </h2>
        <p class="text-slate2 mb-6">
            {{ __('composants.infolettre_chapo') }}
        </p>

        {{-- Retour des trois parcours : abonnement, confirmation, désabonnement.
             Ils redirigent tous ici, et sans ce bloc leur message se perdrait. --}}
        @if (session('infolettre'))
            @php
                $messages = [
                    'verifiez_votre_boite' => __('composants.infolettre_verifiez_votre_boite'),
                    'confirmee' => __('composants.infolettre_confirmee'),
                    'desabonnee' => __('composants.infolettre_desabonnee'),
                    'lien_invalide' => __('composants.infolettre_lien_invalide'),
                ];
            @endphp

            <p role="status"
               class="mb-6 rounded-lg border border-line bg-primary-soft px-4 py-3 text-sm text-heading">
                {{ $messages[session('infolettre')] ?? $messages['lien_invalide'] }}
            </p>
        @endif

        <form method="POST" action="{{ route('public.newsletter.subscribe') }}"
              class="flex flex-col md:flex-row gap-4">
            @csrf

            <div class="flex-1">
                <label class="block text-sm font-medium text-heading mb-2" for="nl-email">{{ __('composants.infolettre_adresse_email') }}</label>
                <input type="email" name="email" id="nl-email" required autocomplete="email"
                       value="{{ old('email') }}"
                       class="w-full border border-line rounded-lg px-4 py-3 text-heading outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 plain"
                       placeholder="{{ __('composants.infolettre_placeholder_email') }}">
                @error('email')
                    <span class="block mt-2 text-sm text-vfalse">{{ $message }}</span>
                @enderror
            </div>

            <div class="flex items-end">
                <button type="submit"
                        class="w-full inline-flex justify-center items-center rounded-lg bg-primary text-white text-lg font-semibold px-6 py-3.5 hover:bg-primary-dark transition-colors">
                    {{ __('composants.infolettre_je_m_abonne') }}
                </button>
            </div>
        </form>

        {{-- Dit AVANT l'envoi, pas après : quelqu'un qui sait comment sortir
             hésite moins à entrer, et ne cherche pas le bouton « indésirable »
             le jour où il voudra partir. --}}
        <p class="mt-4 text-sm text-slate2 mb-0">
            {{ __('composants.infolettre_mentions') }}
        </p>
    </div>
</section>
