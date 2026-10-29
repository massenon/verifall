{{--
  | Conditions générales d'utilisation.
  |
  | ── POURQUOI CETTE PAGE EXISTE ──────────────────────────────────────────────
  |
  | Le formulaire d'inscription faisait cocher « j'accepte les conditions
  | générales d'utilisation » et enregistrait le consentement `ConsentType::Cgu`
  | — alors qu'AUCUNE page de conditions n'existait. Le lien menait à la FAQ.
  |
  | Ce n'était pas un lien cassé mais un défaut de fond : on ne peut pas
  | consentir à un document qu'on ne peut pas lire, et sans conditions la
  | plateforme n'a aucune règle opposable — ni pour écarter un compte abusif, ni
  | pour borner sa responsabilité sur des verdicts.
  |
  | ── AUCUN ESPACE RÉSERVÉ PUBLIÉ ─────────────────────────────────────────────
  |
  | Même règle que les mentions légales : l'identité de l'éditeur vient des
  | réglages, et `<x-mention-legale>` affiche « Non communiqué » au visiteur
  | tout en signalant au PERSONNEL connecté quel réglage renseigner.
  | Le prototype publiait « [Votre Adresse Complète] » ; on ne recommence pas.
  |
  | ── LA DATE EST FIXE, ET C'EST VOULU ────────────────────────────────────────
  |
  | `mentions-legales.blade.php` affiche `now()` en « dernière mise à jour » :
  | la page prétend donc avoir été révisée CHAQUE JOUR. Sur un document
  | juridique, une date qui bouge toute seule est une fausse information — et
  | c'est celle qu'on invoque pour savoir quelle version on a acceptée.
  | Ici, la date est une constante, changée à la main quand le texte change.
--}}
<x-layouts.public :title="$title">
    <main id="contenu">
      <section class="pt-36 pb-10">
        <div class="max-w-3xl mx-auto px-4">

          <div class="text-center mb-10">
            <h1 class="text-4xl md:text-5xl font-display font-bold text-heading">{{ __('cgu.conditions_generales') }}</h1>
            <p class="text-lg text-body/80 mt-2 mb-0">
              {{ __('cgu.en_vigueur_depuis', ['date' => $entreeEnVigueur->translatedFormat('j F Y')]) }}
            </p>
          </div>

          {{-- Le sommaire n'est pas décoratif : seize sections sur un téléphone,
               c'est un défilement long, et l'on vient presque toujours chercher
               UNE clause précise. --}}
          <nav class="mb-10 rounded-lg border border-line bg-[#f8f9fa] p-5" aria-label="{{ __('cgu.sommaire') }}">
            <p class="font-bold text-heading mb-3">{{ __('cgu.sommaire') }}</p>
            <ol class="grid sm:grid-cols-2 gap-x-6 gap-y-1 list-none pl-0 mb-0">
              @foreach ($sections as $rang => $section)
                <li>
                  <a href="#cgu-{{ $rang + 1 }}" class="text-sm text-primary hover:underline">{{ $section['titre'] }}</a>
                </li>
              @endforeach
            </ol>
          </nav>

          <div class="prose-vf text-[#606261]">

            {{-- L'éditeur, en tête : on doit savoir qui s'engage avant de lire
                 ce à quoi il s'engage. --}}
            <p>
              {{ __('cgu.edite_par') }}
              <x-mention-legale :valeur="$legal->entite()" reglage="legal_entity"
                                :libelle="__('mentions_legales.libelle_raison_sociale')" />{{ __('cgu.point') }}
            </p>

            @foreach ($sections as $rang => $section)
              <h6 id="cgu-{{ $rang + 1 }}">{{ $section['titre'] }}</h6>
              @foreach ($section['paragraphes'] as $paragraphe)
                <p>{{ $paragraphe }}</p>
              @endforeach
            @endforeach

            <h6>{{ __('cgu.nous_ecrire') }}</h6>
            <p>
              {{ __('cgu.pour_toute_question') }}
              <a href="{{ route('public.contact') }}" class="text-primary">{{ __('cgu.formulaire_de_contact') }}</a>{{ __('cgu.ou_par_courriel') }}
              {{ $legal->contact() ?? 'contact@verifall.com' }}{{ __('cgu.point') }}
            </p>

            <p class="text-sm text-body/70">
              {{ __('cgu.version_fait_foi') }}
              {{ __('cgu.voir_aussi') }}
              <a href="{{ route('public.privacy') }}" class="text-primary">{{ __('cgu.politique_de_confidentialite') }}</a>,
              {{ __('cgu.et') }}
              <a href="{{ route('public.legal') }}" class="text-primary">{{ __('cgu.mentions_legales') }}</a>
              {{ __('cgu.et_la_page_de_suppression') }}
              <a href="{{ route('public.account-deletion') }}" class="text-primary">{{ __('cgu.supprimer_mon_compte') }}</a>{{ __('cgu.point') }}
            </p>
          </div>
        </div>
      </section>
    </main>
</x-layouts.public>
