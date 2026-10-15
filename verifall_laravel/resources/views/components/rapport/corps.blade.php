@props([
    'verification',
    'rapport',
    'moteurs',
    'sources',
    'haine',
    'synthese' => null,
    'dissidents' => 0,
    // Vrai sur `/rapport/{uuid}` : le lecteur n'a pas de compte, et n'est pas
    // l'auteur de la demande.
    'public' => false,
    // Le SVG du QR de contrôle, pour le pied de page imprimé. Absent à l'écran.
    'qrCode' => null,
])

{{--
  | Le corps d'un rapport de vérification — Hero, argumentation, preuves.
  |
  | ## Pourquoi un composant partagé
  |
  | Le même rapport est servi par DEUX gabarits : l'espace citoyen, pour celui
  | qui a demandé l'analyse, et `/rapport/{uuid}`, pour celui qui scanne le QR
  | d'une feuille imprimée ou suit un lien reçu sur WhatsApp. Ces deux-là
  | doivent lire EXACTEMENT le même verdict, la même synthèse et les mêmes
  | sources — un rapport qui varierait selon le lecteur ne prouverait rien.
  |
  | Ce qui diffère est autour, pas dedans : l'un porte le menu de partage et la
  | contre-expertise de son auteur, l'autre une invitation à vérifier à son
  | tour. C'est le rôle du `$slot`.
  |
  | Les deux gabarits chargent `assets/css/pages/rapport.css`. Le shell public
  | ne charge PAS `citizen.css` — d'où le `.vf-card` ci-dessous plutôt que le
  | `.card` de la feuille citoyenne, qui y serait sans effet.
--}}
<div class="vf-report">

    {{-- ══════════════════════════════════════════════════════════════
         EN-TÊTE OFFICIEL — PAPIER UNIQUEMENT
         Un rapport imprimé sans en-tête n'est qu'une capture d'écran.
    ══════════════════════════════════════════════════════════════ --}}
    <div class="vf-print-only vf-print-entete">
        <div class="vf-print-marque">
            <p class="vf-print-titre">Vérif'All — Laboratoire de fact-checking</p>
            <p class="vf-print-sous">Plateforme de vérification de l'information · République du Bénin</p>
        </div>
        <div class="vf-print-ref">
            <p><strong>Dossier réf. : {{ $verification->reference() }}</strong></p>
            <p>Analyse du {{ $verification->completed_at?->translatedFormat('d/m/Y à H:i') }}</p>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         HERO — LA DÉCISION
    ══════════════════════════════════════════════════════════════ --}}
    {{--
      | `verdict` est NULLABLE dès qu'un rapport est MIXTE ou INSUFFISANT
      | (contrat V-8) : `$rapport->verdict->carteRapport()` planterait tel
      | quel. `$vedette` branche UNE SEULE FOIS — `CompositionDuRapport`
      | expose exactement les mêmes six méthodes que `VerdictFactCheck`,
      | pour ce contrat précis.
    --}}
    {{--
      | Brancher sur `verdict === null`, PAS sur `composition_status`
      | directement : un claim UNIQUE non vérifiable porte
      | `composition_status = Insuffisante` (rien n'a pu être tranché) tout
      | en gardant un `verdict` non nul (`NonVerifiable`) — exactement le
      | comportement d'avant cette phase, à préserver à l'identique. Seuls
      | les DEUX cas où `verdict` est réellement `null` (rapport MIXTE, ou
      | aucun claim vérifiable du tout) doivent lire `composition_status`.
    --}}
    @php($vedette = $rapport->verdict ?? $rapport->composition_status)

    <div class="vf-card vf-report-hero text-center mb-6 {{ $vedette->carteRapport() }}">

        <div class="vf-hero-medal">
            <span class="material-symbols-rounded {{ $vedette->texteRapport() }}" aria-hidden="true">
                {{ $vedette->medaillon() }}
            </span>
        </div>

        <div class="mb-4">
            <span class="vf-hero-badge {{ $vedette->badgeRapport() }}">
                {{ __('cit_rapport.verdict') }} : {{ $vedette->libelleRapport() }}
            </span>
        </div>

        <h1 class="vf-report-claim font-display font-extrabold text-ink mb-4 mx-auto">
            {{ $verification->claim }}
        </h1>

        <div class="vf-hero-pill mb-6">
            <span class="material-symbols-rounded {{ $vedette->texteRapport() }}" aria-hidden="true">
                {{ $vedette->medaillon() }}
            </span>
            <span class="text-sm font-bold text-ink">{{ $vedette->phraseRapport() }}</span>
        </div>

        @if($rapport->estMixte())
            {{-- Chaque claim garde son verdict propre — jamais résumé en un
                 mot-valise (NEWINSTRUCTION24, contrat V-8). --}}
            <ul class="vf-reserve mb-5 text-left max-w-xl mx-auto space-y-1">
                @foreach($rapport->claims() as $position => $claim)
                    @if($claim->verdict)
                        <li class="text-sm text-ink">
                            {{ $position + 1 }}. « {{ \Illuminate\Support\Str::limit($claim->texte, 140) }} » —
                            <strong class="{{ $claim->verdict->texteRapport() }}">{{ mb_strtoupper($claim->verdict->libelle()) }}</strong>
                        </li>
                    @endif
                @endforeach
            </ul>
        @endif

        @if($rapport->porteUneReserve())
            <div class="vf-reserve mb-5 text-left max-w-xl mx-auto flex items-start gap-3">
                <span class="material-symbols-rounded text-vinvest shrink-0" aria-hidden="true">info</span>
                <p class="text-sm text-ink mb-0 leading-relaxed">{{ $rapport->caveat }}</p>
            </div>
        @endif

        <div class="max-w-md mx-auto">
            <div class="flex items-center justify-between text-sm font-bold mb-2">
                <span class="text-slate2 flex items-center gap-2">
                    <span class="material-symbols-rounded" aria-hidden="true">speed</span>
                    {{ __('cit_rapport.indice_de_fiabilite') }}
                </span>
                <span class="{{ $vedette->texteRapport() }} text-base font-black">{{ $rapport->confidence }} %</span>
            </div>
            <div class="vf-progress-track">
                <div class="vf-progress-bar"
                     style="width: {{ $rapport->confidence }}%; background-color: {{ $vedette->couleurBarre() }};"></div>
            </div>
        </div>

        <p class="text-xs text-slate2 mt-6 mb-0">
            {{ $verification->completed_at?->translatedFormat('d F Y à H:i') }} ·
            {{ $verification->type->libelle() }} ·
            {{ __('cit_rapport.ref') }} <span class="font-mono font-bold text-ink">{{ $verification->reference() }}</span>
        </p>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         CORPS
    ══════════════════════════════════════════════════════════════ --}}
    <div class="grid lg:grid-cols-3 gap-5 items-start">
        <div class="lg:col-span-2 space-y-5">

            {{-- Contenu soumis --}}
            <div class="vf-card bg-surface border border-line">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate2 mb-2 flex items-center gap-2">
                    <span class="material-symbols-rounded" aria-hidden="true">input</span>
                    {{ __('cit_rapport.votre_contenu_soumis') }}
                </h2>
                <blockquote class="vf-citation rounded-xl bg-canvas border border-line p-4 text-sm text-ink mb-0 leading-relaxed">
                    « {{ $verification->claim }} »
                </blockquote>

                {{--
                  | LE CONTEXTE N'EST JAMAIS PUBLIÉ.
                  |
                  | « Où avez-vous vu cette information ? » est un champ libre.
                  | Les gens y écrivent « reçu par ma tante sur WhatsApp », un
                  | numéro, parfois le nom d'un tiers. Rien de cela n'appartient
                  | au démenti, et le publier reviendrait à exposer l'entourage
                  | de quelqu'un qui a rendu service en signalant une rumeur.
                --}}
                @if(! $public && $verification->context)
                    <p class="text-xs text-slate2 mt-3 mb-0">
                        <strong>{{ __('cit_rapport.contexte_indique') }}</strong> {{ $verification->context }}
                    </p>
                @endif
            </div>

            {{-- Sources et preuves --}}
            <div class="vf-card bg-surface border border-line">
                <h2 class="text-sm font-display font-bold text-ink mb-3 flex items-center gap-2">
                    <span class="material-symbols-rounded text-primary" aria-hidden="true">verified</span>
                    {{ __('cit_rapport.sources_et_preuves_consultees') }}
                </h2>

                @if($sources->isEmpty())
                    <p class="text-sm text-slate2 mb-0">{{ __('cit_rapport.aucune_source_n_a_pu_etre_consultee') }}</p>
                @else
                    <ul class="vf-liste-nue space-y-3 list-none m-0">
                        @foreach($sources as $source)
                            <li class="rounded-xl border border-line p-4 bg-canvas" wire:key="source-{{ $source->id }}">
                                <div class="flex items-start justify-between gap-2 mb-2">
                                    {{--
                                      | `vf-print-url` : sur PAPIER, l'adresse est
                                      | écrite en toutes lettres après le nom. Un lien
                                      | cliquable ne sert à rien sur une feuille, et
                                      | une source qu'on ne peut pas retrouver ne
                                      | prouve rien.
                                    --}}
                                    <a href="{{ $source->source_url }}" target="_blank" rel="noopener nofollow"
                                       class="vf-print-url text-sm font-bold text-ink hover:text-primary transition-colors flex items-center gap-1">
                                        <span>{{ $source->source_name }}</span>
                                        <span class="material-symbols-rounded no-print" aria-hidden="true">open_in_new</span>
                                    </a>
                                    <span class="{{ $source->stance->badge() }} shrink-0">{{ $source->stance->libelle() }}</span>
                                </div>
                                <p class="text-xs text-slate2 mb-1">
                                    {{ $source->domaineAffiche() }}
                                    {{-- Phase 5/11 : la date de publication, SEULEMENT
                                         quand la page l'a réellement déclarée — jamais
                                         devinée. Absente pour la plupart des lignes
                                         antérieures à la phase 5. --}}
                                    @if($source->published_at)
                                        · {{ $source->published_at->translatedFormat('d M Y') }}
                                    @endif
                                    · <strong class="text-ink">{{ __('cit_rapport.fiabilite') }} {{ $source->reliability }} %</strong>
                                </p>

                                @if($source->snippet)
                                    <p class="vf-clamp-3 text-xs text-slate2 mb-0 leading-relaxed">{{ $source->snippet }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>

            {{-- Hate Speech Indicator --}}
            <div class="vf-card border {{ $haine->alerte() ? 'vf-hsi--alerte' : 'border-line bg-surface' }}">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <h2 class="text-sm font-display font-bold mb-0 flex items-center gap-2 {{ $haine->alerte() ? 'text-vfalse' : 'text-ink' }}">
                        <span class="material-symbols-rounded {{ $haine->alerte() ? 'text-vfalse' : 'text-primary' }}" aria-hidden="true">shield</span>
                        Hate Speech Indicator
                    </h2>
                    <span class="{{ $haine->badge() }}">{{ mb_strtoupper($haine->libelle()) }}</span>
                </div>

                @if($haine->mesure())
                    <p class="text-sm text-slate2 mb-2">
                        {{ __('cit_rapport.hate_speech_index_hsi') }}
                        <strong class="font-black {{ $haine->alerte() ? 'text-vfalse' : 'text-vtrue' }}">{{ $haine->score }} %</strong>
                        — {{ $haine->note ?? __('cit_rapport.analyse_de_la_rhetorique_du_contenu_soumis') }}
                    </p>

                    @if($haine->tags !== [])
                        <div class="flex flex-wrap gap-2 mb-3">
                            @foreach($haine->tags as $tag)
                                <span class="rounded-full bg-canvas border border-line text-xs font-semibold text-ink px-3 py-1">{{ $tag }}</span>
                            @endforeach
                        </div>
                    @endif
                @else
                    <p class="text-sm text-slate2 mb-2">{{ __('cit_rapport.la_rhetorique_de_ce_contenu_n_a') }}</p>
                @endif

                <p class="text-xs text-slate2 mb-0 pt-2 border-t border-line">
                    {{ __('cit_rapport.l_analyse_porte_uniquement_sur_le_contenu') }}
                </p>
            </div>

            {{-- Pourquoi ce résultat ? --}}
            <div class="vf-card bg-surface border border-line">
                <h2 class="text-base font-display font-bold text-ink mb-3 flex items-center gap-2">
                    <span class="material-symbols-rounded text-primary" aria-hidden="true">psychology</span>
                    {{ __('cit_rapport.pourquoi_ce_resultat') }}
                </h2>

                <div class="space-y-4">
                    @if($synthese !== null)
                        <div class="bg-canvas border border-line rounded-xl p-4 text-sm text-ink leading-relaxed">
                            <p class="text-xs font-bold uppercase tracking-wider text-primary mb-3 flex items-center gap-2">
                                <span class="material-symbols-rounded" aria-hidden="true">stars</span>
                                {{ __('cit_rapport.synthese_de_l_analyse') }}
                            </p>
                            {{--
                              | `{!! !!}` sur du HTML ENGENDRÉ ICI, jamais recopié.
                              | `RenduDeSynthese` retire le HTML d'entrée puis purifie
                              | la sortie : le texte vient d'un modèle de langage dont
                              | l'entrée est l'affirmation du citoyen, donc il est
                              | traité comme une donnée hostile.
                            --}}
                            <div class="vf-markdown-summary">{!! $synthese !!}</div>
                        </div>
                    @endif

                    <p class="text-sm text-slate2 mb-0 pt-1">{{ $rapport->summary }}</p>
                </div>
            </div>

            {{-- Moteurs mobilisés --}}
            <div class="vf-card bg-surface border border-line">
                <h2 class="text-sm font-display font-bold text-ink mb-3 flex items-center gap-2">
                    <span class="material-symbols-rounded text-primary" aria-hidden="true">hub</span>
                    {{ __('cit_rapport.moteurs_d_analyse_mobilises') }}
                </h2>

                <div class="table-wrap overflow-x-auto">
                    <table class="vf-table-rapport">
                        <thead>
                            <tr class="text-xs font-bold uppercase text-slate2">
                                <th scope="col">{{ __('cit_rapport.moteur') }}</th>
                                <th scope="col">{{ __('cit_rapport.avis') }}</th>
                                <th scope="col">{{ __('cit_rapport.confiance') }}</th>
                                <th scope="col">{{ __('cit_rapport.temps') }}</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm">
                            @foreach($moteurs as $moteur)
                                <tr wire:key="moteur-{{ $moteur->id }}" data-row-el>
                                    <td class="font-medium text-ink">{{ $moteur->engine->libelle() }}</td>
                                    <td>
                                        @if($moteur->failed)
                                            <span class="vbadge vbadge-false">{{ __('cit_rapport.indisponible') }}</span>
                                        @elseif($moteur->verdict === null)
                                            <span class="vbadge vbadge-progress">{{ __('cit_rapport.sources_apportees') }}</span>
                                        @else
                                            <span class="{{ $moteur->verdict->badge() }}">{{ $moteur->verdict->libelle() }}</span>
                                        @endif
                                    </td>
                                    <td class="text-slate2">{{ $moteur->failed || $moteur->verdict === null ? '—' : $moteur->confidence.' %' }}</td>
                                    <td class="text-slate2 font-mono text-xs">{{ $moteur->latency_ms }} ms</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if($dissidents > 0)
                    <p class="text-xs text-slate2 mt-3 mb-0">
                        {{ trans_choice('cit_rapport.moteurs_ont_conclu_autrement', $dissidents) }}
                        {{ __('cit_rapport.le_verdict_retenu_est_celui_que_soutient') }}
                    </p>
                @endif
            </div>

        </div>

        {{-- ══════════════════════════════════════════════════════════════
             COLONNE LATÉRALE
        ══════════════════════════════════════════════════════════════ --}}
        <aside class="space-y-5">

            {{-- Avertissement éthique --}}
            <div class="vf-card bg-canvas border border-line">
                <h2 class="text-xs font-bold uppercase tracking-wider text-slate2 mb-2 flex items-center gap-2">
                    <span class="material-symbols-rounded" aria-hidden="true">info</span>
                    {{ __('cit_rapport.i_avertissement_sur_l_usage_de_l') }}
                </h2>
                <p class="text-xs text-slate2 mb-0 leading-relaxed">{{ __('cit_rapport.ce_rapport_est_produit_par_des_moteurs') }}</p>
            </div>

            {{-- Ce que chaque gabarit ajoute de son côté. --}}
            {{ $slot }}
        </aside>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         PIED DE CERTIFICATION — PAPIER UNIQUEMENT
    ══════════════════════════════════════════════════════════════ --}}
    @if($qrCode !== null)
        <div class="vf-print-only vf-print-pied">
            <div class="vf-print-pied-texte">
                <p><strong>{{ __('cit_rapport.certificat_d_analyse') }}</strong></p>
                <p>{{ __('cit_rapport.mention_de_certification') }}</p>
            </div>
            <div class="vf-print-qr">
                {!! $qrCode !!}
                <span class="vf-print-qr-ref">{{ $verification->reference() }}</span>
            </div>
        </div>
    @endif
</div>
