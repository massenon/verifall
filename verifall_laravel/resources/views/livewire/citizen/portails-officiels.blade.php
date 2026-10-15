{{--
  | Portails officiels — l'annuaire des sites de l'administration béninoise.
  |
  | La rubrique n'est atteignable que si le réglage `hub_portals_actif` est
  | ouvert : le composant rend 404 sinon. Le lien du menu du compte, lui, reste
  | visible mais désactivé — on annonce ce qui vient sans le faire cliquer.
  |
  | Chaque adresse part en `target="_blank"` avec `rel="noopener"`. Pas de
  | `nofollow` ici, contrairement aux sources qu'on dément : ces sites-là, on
  | recommande justement de les consulter.
--}}
<div class="py-8">
  <div class="mx-auto w-full max-w-5xl px-4">

    <h1 class="text-2xl font-display font-bold text-ink mb-1">{{ __('cit_portails.portails_officiels') }}</h1>
    <p class="text-slate2 mb-6">{{ __('cit_portails.les_adresses_verifiees_des_administrations') }}</p>

    @forelse($parRubrique as $rubrique => $portails)
      <h2 class="text-sm font-display font-bold text-ink mt-6 mb-3">{{ $rubrique }}</h2>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @foreach($portails as $portail)
          <div class="card p-5" wire:key="portail-{{ $portail->uuid }}">
            <div class="flex items-start justify-between gap-3 mb-1">
              <p class="text-sm font-display font-bold text-ink mb-0">{{ $portail->name }}</p>
              @if($portail->is_official)
                {{-- Le seul signal qui compte sur cette page : ce site est-il
                     bien celui de l'administration, ou un site qui lui
                     ressemble ? C'est exactement la confusion que la rubrique
                     existe pour dissiper. --}}
                <span class="inline-flex items-center gap-1 rounded-full bg-primary-soft text-primary-dark text-xs font-bold px-2.5 py-1">
                  <span class="material-symbols-rounded !text-[14px]" aria-hidden="true">verified</span>
                  {{ __('cit_portails.officiel') }}
                </span>
              @endif
            </div>

            <p class="text-sm text-slate2 mb-3">{{ $portail->description }}</p>

            <a href="{{ $portail->url }}" target="_blank" rel="noopener"
               class="inline-flex items-center gap-1.5 text-sm font-semibold text-primary break-all">
              {{ $portail->url }}
              <span class="material-symbols-rounded !text-[16px]" aria-hidden="true">open_in_new</span>
            </a>
          </div>
        @endforeach
      </div>
    @empty
      <div class="card p-6">
        <p class="text-sm text-slate2 mb-0">{{ __('cit_portails.aucun_portail_publie_pour_l_instant') }}</p>
      </div>
    @endforelse

  </div>
</div>
