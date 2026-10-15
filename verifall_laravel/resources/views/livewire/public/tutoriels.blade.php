{{--
  | « Tutoriels » — transposition de tutoriels.html.
  |
  | Structure du prototype : volet de filtres à gauche (accordéon Catégories +
  | Niveau, cases à cocher), grille de cartes à droite sur trois colonnes.
  |
  | Les cours viennent de l'Académie. Tenir ici une seconde bibliothèque
  | pédagogique donnerait deux corpus à maintenir, et le jour où ils divergent
  | c'est la page publique qui ment — celle que voient les gens qui ne sont pas
  | encore inscrits.
--}}
<main id="contenu">
  <section class="pt-32 lg:pt-40 pb-24">
    <div class="max-w-page mx-auto px-4">
      <div class="grid xl:grid-cols-4 gap-10">
        <aside class="xl:col-span-1">
          <h6 class="xl:hidden font-display font-bold text-heading bg-[#f8f9fa] rounded-lg px-4 py-3 mb-4">{{ __('pub_tutoriels.filtres_avances') }}</h6>
          <div data-accordion="multi">
            <div class="accordion-item !bg-primary/10 mb-4 is-open">
              <h2 class="m-0 text-base"><button class="accordion-button" type="button" aria-expanded="true">{{ __('pub_tutoriels.categories') }}<span class="acc-icon" aria-hidden="true">▾</span></button></h2>
              <div class="accordion-body">
                @foreach($categories as $nom)
                  <label class="flex items-center gap-2 {{ $loop->last ? '' : 'mb-2' }} cursor-pointer"><input type="checkbox" class="form-check-input" wire:model.live="categoriesChoisies" value="{{ $nom }}"><span class="text-heading">{{ $nom }}</span></label>
                @endforeach
              </div>
            </div>
            <div class="accordion-item !bg-primary/10 is-open">
              <h2 class="m-0 text-base"><button class="accordion-button" type="button" aria-expanded="true">{{ __('pub_tutoriels.niveau') }}<span class="acc-icon" aria-hidden="true">▾</span></button></h2>
              <div class="accordion-body">
                @foreach($niveaux as $niveau)
                  <label class="flex items-center gap-2 {{ $loop->last ? '' : 'mb-2' }} cursor-pointer"><input type="checkbox" class="form-check-input !rounded-full" wire:model.live="niveauxChoisis" value="{{ $niveau->value }}"><span class="text-heading">{{ $niveau->libelle() }}</span></label>
                @endforeach
              </div>
            </div>
          </div>
        </aside>
        <div class="xl:col-span-3">
          <div class="grid md:grid-cols-3 gap-6">
            @foreach($cours as $formation)
              <div wire:key="cours-{{ $formation->id }}">
                <div class="bg-transparent border border-line rounded-xl overflow-hidden h-full flex flex-col hover:shadow-cardlg hover:-translate-y-1 transition-all">
                  <div class="bg-[#f8f9fa] rounded-lg m-2 relative overflow-hidden" style="aspect-ratio:16/9">
                    @if($formation->cost_units === 0)
                      <span class="absolute top-3 left-3 z-10 rounded-md bg-primary text-white text-xs font-semibold px-2.5 py-1 shadow-card">{{ __('pub_tutoriels.gratuit') }}</span>
                    @endif
                    @if($formation->cover_path)
                      <img src="{{ asset($formation->cover_path) }}" class="w-full h-full object-cover" alt="{{ $formation->title }}" loading="lazy">
                    @endif
                  </div>
                  <div class="px-4 pt-2 flex-1">
                    <span class="inline-block rounded-md bg-dark text-white text-xs px-2 py-0.5 mb-2">{{ $formation->level->libelle() }}</span>
                    <h6 class="font-display font-bold leading-snug mb-2"><a href="{{ route('public.tutorial', $formation) }}" class="card-title-link text-heading">{{ $formation->title }}</a></h6>
                    <p class="text-sm text-body/75 mb-0">{{ Str::limit(strip_tags($formation->description), 90) }}</p>
                    <ul class="flex gap-3 text-sm text-body/75 mt-3">
                      <li><svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor" class="inline -mt-0.5" aria-hidden="true"><path d="M1 2.828c.885-.37 2.154-.769 3.388-.893 1.33-.134 2.458.063 3.112.752v9.746c-.935-.53-2.12-.603-3.213-.493-1.18.12-2.37.461-3.287.811zm7.5-.141c.654-.689 1.782-.886 3.112-.752 1.234.124 2.503.523 3.388.893v9.923c-.918-.35-2.107-.692-3.287-.81-1.094-.111-2.278-.039-3.213.492zM8 1.783C7.015.936 5.587.81 4.287.94c-1.514.153-3.042.672-3.994 1.105A.5.5 0 0 0 0 2.5v11a.5.5 0 0 0 .707.455c.882-.4 2.303-.881 3.68-1.02 1.409-.142 2.59.087 3.223.877a.5.5 0 0 0 .78 0c.633-.79 1.814-1.019 3.222-.877 1.378.139 2.8.62 3.681 1.02A.5.5 0 0 0 16 13.5v-11a.5.5 0 0 0-.293-.455c-.952-.433-2.48-.952-3.994-1.105C10.413.809 8.985.936 8 1.783"/></svg> {{ $formation->lessons_count }} {{ trans_choice('pub_tutoriels.lecons', $formation->lessons_count) }}</li>
                      <li><svg width="13" height="13" viewBox="0 0 16 16" fill="currentColor" class="inline -mt-0.5" aria-hidden="true"><path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71z"/><path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16m7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0"/></svg> {{ $formation->duration_label }}</li>
                    </ul>
                  </div>
                  <div class="p-4 pt-3 mt-auto">
                    <div class="grid grid-cols-2 gap-2 items-center">
                      <span class="font-bold text-primary text-sm">{{ $formation->cost_units === 0 ? __('pub_tutoriels.acces_libre') : $formation->cost_units.' '.trans_choice('pub_tutoriels.unites', $formation->cost_units) }}</span>
                      <a href="{{ route('register') }}" class="inline-flex justify-center items-center rounded-lg bg-primary text-white text-sm font-semibold px-3 py-2 hover:bg-primary-dark transition-colors">{{ __('pub_tutoriels.s_inscrire') }}</a>
                    </div>
                  </div>
                </div>
              </div>
            @endforeach
          </div>
          @if($cours->isEmpty())
            <p class="text-center text-body/70 mt-10">{{ $this->filtresActifs() ? __('pub_tutoriels.aucun_cours_ne_correspond_a_vos_filtres') : __('pub_tutoriels.l_academie_ouvre_bientot_ses_premiers_cours') }}</p>
          @endif
        </div>
      </div>
    </div>
  </section>
</main>
