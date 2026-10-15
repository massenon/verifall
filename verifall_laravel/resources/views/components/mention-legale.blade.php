@props(['valeur' => null, 'reglage' => null, 'libelle' => null])

{{--
  | Une donnée d'identité légale, ou le constat honnête qu'elle manque.
  |
  | JAMAIS de crochet publié. Le prototype affichait « [Votre Adresse
  | Complète] » sur la page même qu'ouvre celui qui veut savoir à qui il a
  | affaire. C'est le pire endroit possible pour un espace réservé.
  |
  | Quand la valeur manque, le visiteur lit « Non communiqué », ce qui est
  | exact, et le PERSONNEL CONNECTÉ voit en plus quel réglage renseigner.
  | L'avertissement s'adresse à qui peut agir, pas à qui subit.
--}}
@if ($valeur !== null)<strong>{{ $valeur }}</strong>@else<span class="text-body/50">{{ __('composants.mention_non_communique') }}</span>@auth('staff')
  <span class="ml-2 inline-block rounded bg-vfalse/10 text-vfalse text-xs font-semibold px-2 py-0.5">
    {{ __('composants.mention_a_renseigner') }} {{ $libelle ?? $reglage }} {{ __('composants.mention_reglages_onglet_general') }}
  </span>
@endauth
@endif
