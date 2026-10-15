{{--
  | « Vérifiez avant de partager » — transposition de
  | verifier-une-information.html (page publique du prototype).
  |
  | Le FORMULAIRE RESTE À L'ÉCRAN et le résultat s'affiche À SA SUITE. Le
  | remplacer obligeait à chercher un bouton « recommencer » pour vérifier une
  | seconde chose, et faisait disparaître ce qu'on venait de coller — on ne
  | savait plus ce qui avait été analysé.
  |
  | Deux formats seulement, sans compte : un LIEN ou un TEXTE. Ni image, ni
  | vidéo, ni audio — ces analyses mobilisent des moteurs lourds, facturés à
  | l'appel, et ce n'est pas ce qu'on offre à un visiteur anonyme. C'est dit à
  | l'écran plutôt que laissé à deviner devant un bouton d'envoi absent.
--}}
<div>
  <section class="pt-36 pb-16">
    <div class="max-w-page mx-auto px-4">

      <div class="max-w-2xl mx-auto text-center mb-10">
        <h1 class="text-4xl md:text-5xl font-display font-bold text-heading leading-tight mb-4">
          {{ __('pub_verifier_invite.verifiez_avant_de') }} <span class="text-primary block md:inline">{{ __('pub_verifier_invite.partager') }}</span>
        </h1>
        <p class="text-lg text-body/85">
          {{ __('pub_verifier_invite.collez_un_lien_ou_un_texte_pour') }}
          <strong class="text-heading">{{ __('pub_verifier_invite.faux_deces_faux_coups_d_etat_faux') }}</strong>
        </p>
      </div>

      <div class="max-w-2xl mx-auto">

        {{-- ═══════════════════════════════════════════════ le formulaire --}}
        <div class="bg-white rounded-2xl shadow-cardlg border border-line p-6 sm:p-12">
          <form wire:submit="lancer">

            <div class="text-center mb-6" role="tablist" aria-label="{{ __('pub_verifier_invite.type_de_contenu_a_verifier') }}">
              <div class="inline-flex rounded-lg overflow-hidden border border-primary">
                <button type="button" role="tab" wire:click="choisirOnglet('url')"
                        aria-selected="{{ $onglet === 'url' ? 'true' : 'false' }}"
                        class="nav-pill !rounded-none !border-0 {{ $onglet === 'url' ? 'is-active' : '' }}">
                  {{ __('pub_verifier_invite.lien_url') }}
                </button>
                <button type="button" role="tab" wire:click="choisirOnglet('texte')"
                        aria-selected="{{ $onglet === 'texte' ? 'true' : 'false' }}"
                        class="nav-pill !rounded-none !border-0 !border-l !border-primary {{ $onglet === 'texte' ? 'is-active' : '' }}">
                  {{ __('pub_verifier_invite.texte_message') }}
                </button>
              </div>
            </div>

            @if($onglet === 'url')
              <label class="block text-sm font-medium text-body mb-2" for="gc-url">{{ __('pub_verifier_invite.collez_un_url_douteux') }}</label>
              <input type="url" id="gc-url" wire:model="lien" placeholder="https://..."
                     class="w-full border border-line rounded-lg px-4 py-3.5 text-heading outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 plain">
              @error('lien') <p class="text-sm text-vfalse mt-2 mb-0">{{ $message }}</p> @enderror
            @else
              <label class="block text-sm font-medium text-body mb-2" for="gc-text">{{ __('pub_verifier_invite.collez_le_message_douteux') }}</label>
              <textarea id="gc-text" rows="4" wire:model="texte"
                        placeholder="{{ __('pub_verifier_invite.copiez_ici_le_message_whatsapp_la_publication') }}"
                        class="w-full border border-line rounded-lg px-4 py-3.5 text-heading outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 plain"></textarea>
              @error('texte') <p class="text-sm text-vfalse mt-2 mb-0">{{ $message }}</p> @enderror
            @endif

            <button type="submit" @disabled($restantes <= 0)
                    class="mt-6 w-full inline-flex justify-center items-center gap-2 rounded-lg bg-primary text-white text-lg font-semibold px-4 py-3.5 hover:bg-primary-dark transition-colors disabled:opacity-40 disabled:cursor-not-allowed">
              <span wire:loading.remove wire:target="lancer">{{ __('pub_verifier_invite.lancer_la_verification_gratuite') }}</span>
              <span wire:loading wire:target="lancer">{{ __('pub_verifier_invite.analyse_lancee') }}</span>
            </button>

            <p class="text-sm text-body/85 text-center mt-4 mb-0">
              @if($restantes > 0)
                {{ trans_choice('pub_verifier_invite.verifications_gratuites_restantes', $restantes) }}
              @else
                {{ __('pub_verifier_invite.vous_avez_utilise_vos_verifications_gratuites') }}
                <a href="{{ route('register') }}" class="font-semibold text-primary">{{ __('pub_verifier_invite.creez_un_compte') }}</a>
                {{ __('pub_verifier_invite.pour_continuer') }}
              @endif
            </p>

            {{-- Dit franchement, plutôt que laissé à chercher : personne ne doit
                 traquer un bouton d'envoi de fichier qui n'existe pas ici. --}}
            <p class="text-xs text-body/70 text-center mt-2 mb-0">
              {{ __('pub_verifier_invite.sans_compte_lien_ou_texte_uniquement') }}
              {{ __('pub_verifier_invite.l_analyse_d_images_de_videos_et_d') }}
              <a href="{{ route('register') }}" class="font-semibold text-primary">{{ __('pub_verifier_invite.un_compte') }}</a>.
            </p>
          </form>
        </div>

        {{-- ═══════════════════════════════ le suivi, À LA SUITE du formulaire --}}
        @if($verification && ! $verification->estTerminee())
          <div class="bg-white rounded-2xl shadow-cardlg border border-line p-6 sm:p-10 text-center mt-6"
               wire:poll.{{ $reprise ? '30s' : '2s' }}="suivre">
            <div class="progress mb-5">
              <span style="width:{{ $verification->avancement() }}%"></span>
            </div>
            <p class="text-2xl font-display font-black text-primary mb-2">{{ $verification->avancement() }}%</p>
            <p class="text-base font-bold text-heading mb-1">{{ $verification->etape()?->libelle() ?? __('pub_verifier_invite.reception_du_contenu') }}</p>
            <p class="text-sm text-body/85 mb-0">{{ $verification->etape()?->description() ?? __('pub_verifier_invite.votre_soumission_est_enregistree') }}</p>

            @if($reprise)
              {{--
                | ATTENTE ANNONCÉE, pas échec. Rendre « verdict indisponible » à
                | un visiteur qui découvre le produit, ce serait lui dire que la
                | plateforme ne marche pas — alors qu'elle attend seulement que
                | les moteurs se libèrent.
              --}}
              <p class="text-sm font-bold text-heading mt-6 mb-1">{{ __('pub_verifier_invite.analyse_mise_en_attente') }}</p>
              <p class="text-sm text-body/85 mb-3">
                {{ $verification->retry_reason }}
                {{ __('pub_verifier_invite.plutot_que_de_vous_rendre_un_verdict') }}
                @if($relancable)
                  {{ __('pub_verifier_invite.des_maintenant') }}
                @else
                  {{ trans_choice('pub_verifier_invite.dans_minutes', $minutes) }}
                @endif
              </p>
              <p class="text-sm text-body/85 mb-4">
                {{ __('pub_verifier_invite.revenez_sur_cette_page_dans_une_dizaine') }}
              </p>

              @if($relancable)
                <button type="button" wire:click="relancer" wire:loading.attr="disabled" wire:target="relancer"
                        class="inline-flex items-center gap-2 rounded-xl bg-primary text-white text-sm font-semibold px-5 py-2.5 hover:opacity-90 disabled:opacity-40 disabled:cursor-not-allowed">
                  <span class="material-symbols-rounded !text-[18px]" aria-hidden="true">refresh</span>
                  <span wire:loading.remove wire:target="relancer">{{ __('pub_verifier_invite.relancer_maintenant') }}</span>
                  <span wire:loading wire:target="relancer">{{ __('pub_verifier_invite.relance') }}</span>
                </button>
              @endif

            @elseif($bloquee)
              {{-- Une roue qui tourne indéfiniment fait attendre pour rien. --}}
              <p class="text-sm text-vfalse font-bold mt-5 mb-1">{{ __('pub_verifier_invite.l_analyse_prend_plus_de_temps_que') }}</p>
              <p class="text-sm text-body/85 mb-0">
                {{ __('pub_verifier_invite.un_moteur_ne_repond_peut_etre_pas') }}
                <button type="button" wire:click="recommencer" class="font-semibold text-primary">{{ __('pub_verifier_invite.reessayer') }}</button>
              </p>
            @else
              <p class="text-xs text-body/85 mt-5 mb-0">
                {{ __('pub_verifier_invite.ne_fermez_pas_cette_page_sans_compte') }}
              </p>
            @endif
          </div>
        @endif

        {{-- ═══════════════════════════════ le résultat, À LA SUITE aussi --}}
        @if($verification && $verification->report)
          @php($rapport = $verification->report)

          <div class="bg-white rounded-2xl shadow-cardlg border border-line p-6 sm:p-12 mt-6" id="resultat">
            <div class="text-center mb-6">
              {{--
                | `verdict` est NULLABLE dès qu'un rapport est MIXTE OU
                | INSUFFISANT (contrat V-8) — `$rapport->verdict->badgePublic()`
                | planterait tel quel sur la page la plus exposée du produit :
                | un invité anonyme, sans compte à protéger de l'erreur.
                | `estMixte()` seul ne couvrait que le premier cas ; on teste
                | `verdict !== null` AVANT de le déréférencer, qui couvre les
                | deux à la fois.
              --}}
              <p class="mb-3">
                {{-- Pastille du shell PUBLIC : « vbadge » vit dans citizen.css,
                     que cette page ne charge pas. Le badge y sortait gris. --}}
                <span class="{{ $rapport->verdict !== null ? $rapport->verdict->badgePublic() : $rapport->composition_status->badgePublic() }} text-xs tracking-[.12em]">
                  {{ $rapport->verdict !== null ? mb_strtoupper($rapport->verdict->libelle()) : mb_strtoupper($rapport->composition_status->libelle()) }}
                </span>
              </p>
              <h2 class="text-xl sm:text-2xl font-display font-bold text-heading mb-3">
                {{ $rapport->verdict !== null ? $rapport->verdict->explication() : $rapport->composition_status->explication() }}
              </h2>

              @if($rapport->estMixte())
                <ul class="text-left max-w-sm mx-auto space-y-1 text-sm text-heading mb-4">
                  @foreach($rapport->claims() as $position => $claim)
                    @if($claim->verdict)
                      <li>{{ $position + 1 }}. « {{ \Illuminate\Support\Str::limit($claim->texte, 90) }} » — <strong>{{ mb_strtoupper($claim->verdict->libelle()) }}</strong></li>
                    @endif
                  @endforeach
                </ul>
              @endif

              @if($rapport->porteUneReserve())
                <p class="inline-flex items-start gap-2 rounded-xl bg-[#f8f9fa] border border-line text-sm text-heading px-4 py-3 mb-4 text-left">
                  {{ $rapport->caveat }}
                </p>
              @endif

              <div class="max-w-sm mx-auto">
                <div class="flex items-center justify-between text-sm mb-1.5">
                  <span class="text-body/85">{{ __('pub_verifier_invite.indice_de_fiabilite') }}</span>
                  <strong>{{ $rapport->confidence }} %</strong>
                </div>
                <div class="progress">
                  <span style="width:{{ $rapport->confidence }}%;background:{{ $rapport->verdict !== null ? $rapport->verdict->couleurBarre() : $rapport->composition_status->couleurBarre() }}"></span>
                </div>
              </div>
            </div>

            {{-- Ce qui a été analysé, rappelé : le formulaire est resté visible
                 mais il a été vidé, et un verdict sans son objet ne veut rien
                 dire. --}}
            <blockquote class="rounded-xl bg-[#f8f9fa] border border-line p-4 text-sm text-body/85 mb-4" style="font-style:italic">
              « {{ Str::limit($verification->claim, 240) }} »
            </blockquote>

            <p class="text-sm text-body/85 mb-6">{{ $rapport->summary }}</p>

            {{-- Ce qui reste derrière l'inscription est nommé précisément : on
                 ne cache pas la RÉPONSE, seulement le dossier qui la soutient.
                 Une promesse vague ne convaincrait personne, et laisserait
                 croire qu'on retient le verdict. --}}
            <div class="rounded-xl border border-primary/30 bg-primary-soft/40 p-5 mb-4">
              <p class="text-sm font-display font-bold text-heading mb-2">{{ __('pub_verifier_invite.le_dossier_complet_vous_attend') }}</p>
              <ul class="text-sm text-body/85 space-y-1 mb-4 list-none p-0">
                <li>· {{ __('pub_verifier_invite.les_moteurs_mobilises_et_l_avis_de', ['nombre' => $verification->engineRuns()->count()]) }}</li>
                <li>· {{ __('pub_verifier_invite.les_sources_consultees_avec_leur_position_et', ['nombre' => $verification->evidences()->count()]) }}</li>
                <li>· {{ __('pub_verifier_invite.l_indice_de_discours_haineux_du_contenu') }}</li>
                <li>· {{ __('pub_verifier_invite.l_analyse_des_images_videos_et_fichiers_audio') }}</li>
                <li>· {{ __('pub_verifier_invite.l_historique_de_vos_verifications_et_le_rapport') }}</li>
              </ul>
              <a href="{{ route('register') }}"
                 class="w-full inline-flex justify-center items-center rounded-lg bg-primary text-white font-semibold px-5 py-3 hover:bg-primary-dark transition-colors">
                {{ __('pub_verifier_invite.creer_un_compte_gratuit') }}
              </a>
            </div>

            <div class="text-center">
              @if($restantes > 0)
                <p class="text-sm text-body/85 mb-0">
                  {{ trans_choice('pub_verifier_invite.il_vous_reste_verifications_gratuites', $restantes) }}
                  {{ __('pub_verifier_invite.le_formulaire_est_reste_en_haut_de') }}
                </p>
              @else
                <p class="text-sm text-body/85 mb-0">
                  {{ __('pub_verifier_invite.vous_avez_utilise_vos_verifications_gratuites') }}
                  <a href="{{ route('register') }}" class="font-semibold text-primary">{{ __('pub_verifier_invite.creez_un_compte') }}</a>
                  {{ __('pub_verifier_invite.pour_continuer') }}
                </p>
              @endif
            </div>
          </div>
        @endif

        <div class="bg-[#f8f9fa] border border-line rounded-xl py-8 px-6 sm:px-10 mt-14">
          <div class="flex flex-col md:flex-row md:items-center gap-6">
            <div class="md:flex-1">
              <h2 class="text-lg font-display font-bold text-heading mb-1">{{ __('pub_verifier_invite.besoin_d_analyse_complete') }}</h2>
              <p class="text-body/85 mb-0">
                {{ __('pub_verifier_invite.en_creant_un_compte_vous_debloquez_l') }}
              </p>
            </div>
            <div class="md:w-56">
              <a href="{{ route('register') }}"
                 class="w-full inline-flex justify-center items-center rounded-lg bg-white border border-line text-heading font-semibold px-5 py-3 hover:bg-gray-50 transition-colors">
                {{ __('pub_verifier_invite.creer_un_compte') }}
              </a>
            </div>
          </div>
        </div>

      </div>
    </div>
  </section>
</div>
