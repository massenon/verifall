{{--
  | Supprimer mon compte — page publique.
  |
  | Cette page sert deux objectifs distincts :
  |
  | 1. INFORMER : elle décrit, sans ambiguïté, la procédure de suppression
  |    in-app et web, ce qui est effacé, ce qui est conservé anonymisé, et
  |    le délai de rétractation. Elle est requise par le Play Store avant
  |    toute soumission d'une app permettant la création de compte.
  |
  | 2. AGIR (Lot B, conditionné à OQ1) : la section 5 accueille un formulaire
  |    e-mail permettant à un utilisateur sans accès à son compte de déclencher
  |    la suppression via un lien signé valable 24 h.
  |
  | Le délai :jours est remplacé par le contrôleur avec
  | SuppressionCompteService::JOURS_RETRACTATION — pas codé en dur.
  |
  | Route : GET /supprimer-mon-compte → public.account-deletion
  | Contrôleur : Route::view (pour l'instant), Passeront via un vrai
  |              contrôleur en Lot B pour gérer le formulaire.
--}}
<x-layouts.public :title="$title">
    <main id="contenu">
      <section class="pt-36 pb-10">
        <div class="max-w-3xl mx-auto px-4">
          <div class="text-center mb-12">
            <h1 class="text-4xl md:text-5xl font-display font-bold text-heading">{{ __('supprimer_mon_compte.titre_page') }}</h1>
            <p class="text-lg text-body/80 mt-2 mb-0">{{ __('supprimer_mon_compte.derniere_mise_a_jour') }} {{ now()->translatedFormat('j F Y') }}</p>
          </div>

          <div class="prose-vf text-[#606261]">
            <p>{{ __('supprimer_mon_compte.intro') }}</p>

            {{-- Section 1 — Depuis l'application mobile --}}
            <h6>{{ __('supprimer_mon_compte.1_depuis_l_application') }}</h6>
            <p>{{ __('supprimer_mon_compte.depuis_l_application_intro') }}</p>
            <ol>
              <li>{!! __('supprimer_mon_compte.depuis_l_app_etape_1') !!}</li>
              <li>{!! __('supprimer_mon_compte.depuis_l_app_etape_2') !!}</li>
              <li>{!! __('supprimer_mon_compte.depuis_l_app_etape_3') !!}</li>
              <li>{!! __('supprimer_mon_compte.depuis_l_app_etape_4') !!}</li>
            </ol>
            <p>{{ __('supprimer_mon_compte.depuis_l_app_resultat', ['jours' => $jours]) }}</p>

            {{-- Section 2 — Depuis le site web --}}
            <h6>{{ __('supprimer_mon_compte.2_depuis_le_site_web') }}</h6>
            <p>{{ __('supprimer_mon_compte.depuis_le_web_intro') }}</p>
            <ol>
              <li>{!! __('supprimer_mon_compte.depuis_le_web_etape_1') !!}</li>
              <li>{!! __('supprimer_mon_compte.depuis_le_web_etape_2') !!}</li>
              <li>{!! __('supprimer_mon_compte.depuis_le_web_etape_3') !!}</li>
              <li>{!! __('supprimer_mon_compte.depuis_le_web_etape_4') !!}</li>
            </ol>

            {{-- Section 3 — Ce qui est effacé / conservé --}}
            <h6>{{ __('supprimer_mon_compte.3_ce_qui_est_efface_et_conserve') }}</h6>
            <p>{{ __('supprimer_mon_compte.ce_qui_est_efface_conserve_intro') }}</p>

            <div class="not-prose overflow-x-auto mt-4 mb-6">
              <table class="w-full text-sm border border-line rounded-lg overflow-hidden">
                <thead class="bg-[#f3f4f4] text-heading font-semibold">
                  <tr>
                    <th class="px-4 py-3 text-left w-1/3">Quand</th>
                    <th class="px-4 py-3 text-left">Ce qui se passe</th>
                  </tr>
                </thead>
                <tbody>
                  <tr class="border-t border-line">
                    <td class="px-4 py-3 font-medium text-heading align-top">{{ __('supprimer_mon_compte.fermeture_immediate') }}</td>
                    <td class="px-4 py-3 align-top">{{ __('supprimer_mon_compte.fermeture_immediate_texte') }}</td>
                  </tr>
                  <tr class="border-t border-line">
                    <td class="px-4 py-3 font-medium text-heading align-top">{{ __('supprimer_mon_compte.apres_jours_jours', ['jours' => $jours]) }}</td>
                    <td class="px-4 py-3 align-top">{{ __('supprimer_mon_compte.apres_jours_jours_texte') }}</td>
                  </tr>
                  <tr class="border-t border-line bg-[#f9fafb]">
                    <td class="px-4 py-3 font-medium text-heading align-top">{{ __('supprimer_mon_compte.ce_qui_est_conserve') }}</td>
                    <td class="px-4 py-3 align-top">{{ __('supprimer_mon_compte.ce_qui_est_conserve_texte') }}</td>
                  </tr>
                </tbody>
              </table>
            </div>

            {{-- Section 4 — Annuler la suppression --}}
            <h6>{{ __('supprimer_mon_compte.4_annuler_la_suppression') }}</h6>
            <p><strong>{{ __('supprimer_mon_compte.annuler_intro', ['jours' => $jours]) }}</strong></p>
            <p>{{ __('supprimer_mon_compte.annuler_texte', ['jours' => $jours]) }}</p>

            {{-- Section 5 — Utilisateur sans accès (formulaire) --}}
            <h6>{{ __('supprimer_mon_compte.5_sans_acces_au_compte') }}</h6>
            <p>{{ __('supprimer_mon_compte.sans_acces_intro') }}</p>

            <div class="not-prose bg-[#f8f9fa] border border-line rounded-xl p-6 mt-4">

              {{-- Flash : lien envoyé --}}
              @if(session('suppression_lien'))
                <div class="mb-4 rounded-lg bg-primary/10 border border-primary/20 px-4 py-3 text-sm text-heading">
                  {{ session('suppression_lien') }}
                </div>
              @endif

              {{-- Flash : confirmation honorée --}}
              @if(session('suppression_confirmer') === 'reussie')
                <div class="mb-4 rounded-lg bg-primary/10 border border-primary/20 px-4 py-3 text-sm text-heading">
                  {{ __('supprimer_mon_compte.compte_ferme_confirmation') }}
                </div>
              @elseif(session('suppression_confirmer') === 'deja_programme')
                <div class="mb-4 rounded-lg bg-amber-50 border border-amber-200 px-4 py-3 text-sm text-heading">
                  {{ __('supprimer_mon_compte.suppression_deja_en_cours') }}
                </div>
              @endif

              @unless(session('suppression_confirmer') === 'reussie')
                <form method="POST" action="{{ route('public.account-deletion.send') }}" class="space-y-4">
                  @csrf
                  <div>
                    <label for="email" class="block text-sm font-medium text-heading mb-1">
                      {{ __('supprimer_mon_compte.champ_email_label') }}
                    </label>
                    <input type="email" id="email" name="email"
                           value="{{ old('email') }}"
                           placeholder="{{ __('supprimer_mon_compte.champ_email_placeholder') }}"
                           class="w-full rounded-lg border border-line px-4 py-2.5 text-body focus:outline-none focus:ring-2 focus:ring-primary/50"
                           required>
                    @error('email')
                      <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                  </div>
                  <button type="submit"
                          class="inline-flex items-center rounded-lg bg-primary text-white font-semibold px-5 py-2.5 hover:bg-primary-dark transition-colors">
                    {{ __('supprimer_mon_compte.bouton_envoyer') }}
                  </button>
                </form>
                <p class="text-sm text-body/70 mt-4">
                  {{ __('supprimer_mon_compte.note_securite') }}
                  {{ $legal->contact() ?? 'contact@verifall.com' }}.
                </p>
              @endunless
            </div>

            {{-- Footer légal --}}
            <p class="text-sm text-body/70 mt-8">
              {{ __('supprimer_mon_compte.footer_confidentialite') }}
              <a href="{{ route('public.privacy') }}" class="text-primary font-bold">{{ __('supprimer_mon_compte.footer_lien_confidentialite') }}</a>
              {{ __('supprimer_mon_compte.footer_et') }}
              <a href="{{ route('public.legal') }}" class="text-primary font-bold">{{ __('supprimer_mon_compte.footer_lien_mentions') }}</a>{{ __('supprimer_mon_compte.footer_point') }}
            </p>
          </div>
        </div>
      </section>
    </main>
</x-layouts.public>
