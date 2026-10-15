{{--
  | Contact — transposition de contact.html.
  |
  | Contenu, structure et classes repris du prototype. Seuls les liens et les
  | chemins d'images sont réécrits : ce sont les seules choses qu'une
  | application doit calculer plutôt que figer.
--}}
<x-layouts.public :title="$title">
    <main id="contenu">
      <section class="pt-36 pb-24">
        <div class="max-w-page mx-auto px-4">
          <div class="grid lg:grid-cols-2 gap-12 lg:gap-16 items-start">
            <div class="flex flex-col h-full">
              <h1 class="text-4xl md:text-5xl font-display font-bold text-heading mb-4">{{ __('contact.contactez_lequipe_verifall') }}</h1>
              <p class="mb-2">{{ __('contact.votre_voix_est_importante_pour_nous_pour') }}</p>
              <ul class="mb-8 space-y-2">
                <li>{!! __('contact.pour_les_partenariats_amp_medias_utilisez_le') !!}</li>
                <li>{!! __('contact.pour_les_questions_amp_suggestions_rejoignez_la') !!}</li>
                <li><strong class="text-heading">{{ __('contact.pour_signaler_une_information') }}</strong> {{ __('contact.veuillez_utiliser_notre') }} <a href="{{ route('public.verify') }}" class="text-primary font-medium">{{ __('contact.outil_de_verification') }}</a>{{ __('contact.dedie') }}</li>
              </ul>
              <p class="mb-0">{!! __('contact.une_question_un_partenariat_ou_un_besoin') !!}</p>
              <div class="sm:flex gap-4 items-center mt-10">
                <h6 class="font-display font-bold text-heading mb-3 sm:mb-0">{{ __('contact.suivez_nous') }}</h6>
                <ul class="flex gap-2">
                  <li><a href="https://www.facebook.com/share/17nJws5pMX/" target="_blank" rel="noopener" aria-label="Facebook" class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-[#f5f6f7] text-[#5d82d1] hover:bg-[#eceef0]">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M16 8.049c0-4.446-3.582-8.05-8-8.05C3.58 0-.002 3.603-.002 8.05c0 4.017 2.926 7.347 6.75 7.951v-5.625h-2.03V8.05H6.75V6.275c0-2.017 1.195-3.131 3.022-3.131.876 0 1.791.157 1.791.157v1.98h-1.009c-.993 0-1.303.621-1.303 1.258v1.51h2.218l-.354 2.326H9.25V16c3.824-.604 6.75-3.934 6.75-7.951"/></svg></a></li>
                  <li><a href="https://www.instagram.com/verif_all" target="_blank" rel="noopener" aria-label="Instagram" class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-[#f5f6f7] text-[#c13584] hover:bg-[#eceef0]">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0C5.829 0 5.556.01 4.703.048 3.85.088 3.269.222 2.76.42a3.9 3.9 0 0 0-1.417.923A3.9 3.9 0 0 0 .42 2.76C.222 3.268.087 3.85.048 4.7.01 5.555 0 5.827 0 8.001c0 2.172.01 2.444.048 3.297.04.852.174 1.433.372 1.942.205.526.478.972.923 1.417.444.445.89.719 1.416.923.51.198 1.09.333 1.942.372C5.555 15.99 5.827 16 8 16s2.444-.01 3.298-.048c.851-.04 1.434-.174 1.943-.372a3.9 3.9 0 0 0 1.416-.923c.445-.445.718-.891.923-1.417.197-.509.332-1.09.372-1.942C15.99 10.445 16 10.173 16 8s-.01-2.445-.048-3.299c-.04-.851-.175-1.433-.372-1.941a3.9 3.9 0 0 0-.923-1.417A3.9 3.9 0 0 0 13.24.42c-.51-.198-1.092-.333-1.943-.372C10.443.01 10.172 0 7.998 0zm-.717 1.442h.718c2.136 0 2.389.007 3.232.046.78.035 1.204.166 1.486.275.373.145.64.319.92.599s.453.546.598.92c.11.281.24.705.275 1.485.039.843.047 1.096.047 3.231s-.008 2.389-.047 3.232c-.035.78-.166 1.203-.275 1.485a2.5 2.5 0 0 1-.599.919c-.28.28-.546.453-.92.598-.28.11-.704.24-1.485.276-.843.038-1.096.047-3.232.047s-2.39-.009-3.233-.047c-.78-.036-1.203-.166-1.485-.276a2.5 2.5 0 0 1-.92-.598 2.5 2.5 0 0 1-.6-.92c-.109-.281-.24-.705-.275-1.485-.038-.843-.046-1.096-.046-3.233s.008-2.388.046-3.231c.036-.78.166-1.204.276-1.486.145-.373.319-.64.599-.92s.546-.453.92-.598c.282-.11.705-.24 1.485-.276.738-.034 1.024-.044 2.515-.045zm4.988 1.328a.96.96 0 1 0 0 1.92.96.96 0 0 0 0-1.92m-4.27 1.122a4.109 4.109 0 1 0 0 8.217 4.109 4.109 0 0 0 0-8.217m0 1.441a2.667 2.667 0 1 1 0 5.334 2.667 2.667 0 0 1 0-5.334"/></svg></a></li>
                  <li><a href="https://www.linkedin.com/company/v-rif-all/" target="_blank" rel="noopener" aria-label="LinkedIn" class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-[#f5f6f7] text-[#238cc8] hover:bg-[#eceef0]">
                    <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M0 1.146C0 .513.526 0 1.175 0h13.65C15.474 0 16 .513 16 1.146v13.708c0 .633-.526 1.146-1.175 1.146H1.175C.526 16 0 15.487 0 14.854zm4.943 12.248V6.169H2.542v7.225zm-1.2-8.212c.837 0 1.358-.554 1.358-1.248-.015-.709-.52-1.248-1.342-1.248S2.4 3.226 2.4 3.934c0 .694.521 1.248 1.327 1.248zm4.908 8.212V9.359c0-.216.016-.432.08-.586.173-.431.568-.878 1.232-.878.869 0 1.216.662 1.216 1.634v3.865h2.401V9.25c0-2.22-1.184-3.252-2.764-3.252-1.274 0-1.845.7-2.165 1.193v.025h-.016l.016-.025V6.169h-2.4c.03.678 0 7.225 0 7.225z"/></svg></a></li>
                </ul>
              </div>
            </div>

            <div class="relative bg-white rounded-2xl shadow-cardlg border border-line p-6 sm:p-12">
              <figure class="absolute top-full left-0 -translate-x-1/2 -translate-y-1/2 -mt-10 pointer-events-none select-none" aria-hidden="true">
                <svg width="225.3px" height="168.1px" viewBox="0 0 225.3 168.1" style="enable-background:new 0 0 225.3 168.1;" xml:space="preserve">
                <path fill="#09B850" opacity=".08" d="M147,129.9c1.9-1,9.2-2.2,4.2,2.3c-1,0.9-3.4,0.9-4.7,1.2c-3,0.8-5.8,2-8.6,3.3c0.9,0.3,1.8,0.7,2.7,1 c-1.2-3.3-3.5-6.4-3.7-9.9c-0.1-1.3,0-5,1.2-5.8c3.2-2.1,5.9,3.8,4.8,4.6c0.9-0.3,1.9-0.5,2.8-0.8c-2.4-0.9-4.6-3.7-5-6.1 c-1.2,0.9-2.4,1.8-3.7,2.8c1,0.4,1.7,1,2.1,1.9c0.2,0.8,0.4,1.7,0.5,2.5c0.8-0.1,1.5-0.2,2.3-0.4c-1.3-0.4-2.5-1.7-3.3-2.7 c-1.3-1.5-4.3-0.3-4.4,1.7c-0.4,4.4,3.1,8.4,5.1,12c1.1,1.9,5-0.2,4.4-2.2c-0.6-2.2-1.8-4.7-1.9-7c-0.5,0.8-0.9,1.6-1.4,2.4 c-0.8,0.5,3,1.7,3.3,1.7c1.2,0.2,1.9,0.2,2.9-0.3c0.7-0.3,2.9-1.8,2.4-1.9c-0.8-0.3-5.4,1.1-6.6,1.1c-2.3,0-4.7,4-1.7,4 c3.5,0,7.5-0.4,10.6-2.2c2.1-1.2,4.4-4.4,2.5-6.7c-2-2.4-4.9,0-6.8,1c-2.1,1.1-3.1,0.1-5.4-0.3c-1.3-0.2-2-0.1-3.1,0.6 c-0.9,0.5-1.4,1.4-1.4,2.4c0.1,2.3,1.3,4.8,1.9,7c1.5-0.7,3-1.4,4.4-2.2c-1.5-2.6-5.1-6.9-4.8-10c-1.5,0.6-3,1.1-4.4,1.7 c2.2,2.6,6.1,6.1,8.8,2.1c2.5-3.6,0.2-8.3-3.4-9.9c-1.6-0.7-3.9,1.1-3.7,2.8c0.5,3.4,5.1,10.1,9.2,7.9c3.9-2.1,1.8-8.6-0.6-11 c-3.5-3.6-8.5-1.7-10.5,2.4c-1.3,2.6-2.2,5.7-2,8.6c0.2,3.7,2.5,6.7,3.8,10.2c0.4,1.1,1.6,1.5,2.7,1c4-1.9,8-2.9,12.1-4.2 c2.5-0.8,6-2.3,6.9-5.1c0.7-2.4-1.2-6.1-3.5-7c-3-1.1-6.5,1.3-9,2.6C142.3,128.2,144.3,131.3,147,129.9L147,129.9z"/>
                <path fill="#202124" d="M3,0.9C3,1,3,1.2,3.1,1.4c0.1,0.1,0.2,0.2,0.4,0.3c2.8,4.7,5.4,9.5,7.9,14.4c9.2,18,16.3,36.9,21.4,56.4 c0.1,0.3,0.4,0.4,0.7,0.3c0,0.3,0.4,0.6,0.7,0.4c2.7-1.8,5.1-4.2,7.7-6.2c2.5-1.9,5-3.7,7.6-5.5c0.8-0.5,1.6-1.1,2.3-1.6 c0,0.1,0,0.2,0.1,0.3c1.5,2.3,4,4.3,6.1,6.1c1.1,0.9,2.2,1.9,3.3,2.7c1,0.8,2,1.7,3.2,2.2c0.3,0.1,0.5-0.2,0.4-0.5c0,0,0,0,0,0 c2.4-8.3,4.5-16.8,6-25.4c0.2,0,0.3-0.2,0.3-0.4c7.6-3.5,14.4-9,20.4-14.8c0.2-0.2,0.2-0.4,0.1-0.6c0.1-0.2,0.1-0.6-0.2-0.7 c-6.4-2.4-13-4.3-19.5-6.5C49.3,15,26.6,7.5,3.8,0.2c0,0,0,0-0.1,0c0,0-0.1,0-0.1-0.1C3.2-0.3,2.7,0.5,3,0.9z M70.6,44.2 c-4.6-3.4-9.5-6.4-14.3-9.5C39.8,23.8,23.2,12.9,6.7,2.1c5.3,1.8,10.7,3.6,16,5.3l34,11.1c5.8,1.9,11.5,3.8,17.3,5.7 c5.6,1.8,11.1,3.9,16.8,5.4c-3,2.9-6.3,5.7-9.7,8.2c-3.3,2.4-6.7,4.4-10.2,6.5C70.8,44.2,70.7,44.2,70.6,44.2z M64.8,67.5 c-0.3-5.7-0.5-11.4-1-17.1c0-0.4-0.4-0.6-0.7-0.5c-0.1-0.2-0.4-0.4-0.6-0.4c-0.1,0-0.2-0.1-0.3-0.1c-0.7-0.8-1.6-1.5-2.4-2.2 c-1.1-1-2.3-1.9-3.4-2.9c-2.1-1.8-4.3-3.5-6.4-5.3c-7.8-6.5-15.5-12.9-23.3-19.4c-3.9-3.2-7.8-6.5-11.6-9.7C13.1,8.3,11.2,6.7,9.3,5 l8.8,5.8c8.8,5.8,17.5,11.5,26.3,17.3c4.4,2.9,8.8,5.8,13.2,8.7c4.2,2.8,8.4,5.7,12.8,8.3C68.6,52.6,66.9,60,64.8,67.5z M52.3,59.6 c1.6-1.1,3.2-2.3,4.7-3.5c1.2-1,2.4-1.9,3.5-2.9c0.7-0.6,1.8-1.3,2.2-2.2c0,6.2,0.6,12.5,1.1,18.6c-0.7-0.6-1.6-1.1-2.3-1.7 c-1-0.8-2.1-1.7-3.1-2.6c-1-0.9-2-1.8-2.9-2.7c-1-1-1.9-2.1-3-3C52.5,59.7,52.4,59.6,52.3,59.6z M6.1,4c3.3,2.9,6.9,5.6,10.3,8.4 c7.7,6.4,15.4,12.8,23.1,19.2c3.8,3.2,7.6,6.3,11.4,9.5c2,1.6,4,3.3,5.9,4.9c1.7,1.4,3.4,3.3,5.5,4.2c0,0,0,0,0,0 c0.1,0.1,0.1,0.1,0.2,0.1c-1.1,0.3-2,1.6-2.9,2.3c-1.1,0.9-2.2,1.9-3.4,2.8c-2.6,2-5.3,3.9-8,5.7c-2.6,1.8-5.1,3.6-7.6,5.6 c-2.3,1.8-4.9,3.5-6.9,5.7c0,0,0,0,0-0.1c-1.4-5.6-3-11.1-4.7-16.5C23.2,37.8,15.5,20.4,6.1,4z"/>
                <path fill="#202124" d="M67.3,70.8c2.2,6.3,5.5,12.1,9.9,17.3c2.2,2.6,4.7,5,7.4,7.2c1.8,1.5,3.8,2.9,6.1,3.7c0.3,1.1,0.9,2.2,1.4,3.2 c1.5,2.8,3.4,5.5,5.2,8.1c3.7,5.2,7.8,10,12.6,14.2c7.2,6.2,16.3,10.9,25.8,12.2c0.4,0.9,0.8,1.8,1.2,2.6 c5.7,10.5,18.1,16.3,29.6,11.9c5.3-2,10-6.1,12.2-11.4c0.3-0.7,0.5-1.4,0.7-2.2c0.8,0.3,1.5,0.5,2.3,0.7c6.6,2,13.7,1.9,20.3-0.2 c12.2-3.8,21.5-14.8,23.3-27.4c0.1-0.5-0.6-0.7-0.7-0.2c-2.1,11.7-10,22-21.2,26.2c-5.4,2-11.2,2.6-16.9,1.7 c-2.3-0.3-4.6-0.9-6.8-1.8c0.4-2.1,0.3-4.4-0.5-6.4c-0.8-2-2.8-3.7-4.8-2.1c-1.9,1.5-1.9,4.1-0.5,5.9c1.2,1.5,2.9,2.5,4.8,3.2 c-0.5,2-1.4,3.9-2.6,5.5c-2.8,4.1-7.2,6.9-11.9,8.2c-10,2.7-20.1-2.1-25.5-10.7c-0.7-1.1-1.3-2.3-1.9-3.5c2.6,0.3,5.2,0.3,7.9,0.1 c4.3-0.4,9.8-2.5,9.6-7.7c-0.1-1.8-0.9-3.6-2.5-4.4c-2-0.9-4-0.1-5.8,1c0.4-1.5,0-3.3-1.1-4.5c-1.8-2.1-5-2-7.2-0.5 c-4.9,3.2-4.2,9.8-2.3,15c-0.2,0-0.3,0-0.5-0.1c-10.4-1.7-19.8-7.3-27.3-14.6c-3.7-3.6-6.9-7.5-9.9-11.7c-1.5-2.1-2.9-4.2-4.2-6.4 c-0.7-1.2-1.5-2.6-2-4c0.5,0.1,1,0.2,1.5,0.2c2.2,0.1,4.2-0.7,5.5-2.6c1-1.4,1.4-3.4,0.5-5c-2.2-3.9-7.6-0.9-8.5,2.4 c-0.3,1.2-0.3,2.4,0,3.6c-2.1-0.8-4.2-2.3-5.7-3.4c-7.7-6.2-13.6-14.4-17-23.7C67.6,70.5,67.2,70.5,67.3,70.8z M178.7,136.3 C178.7,136.3,178.7,136.2,178.7,136.3c-2.6-1.1-6.6-3.5-4.6-6.7c1.3-2.1,3.3-0.9,4.1,0.9c0.6,1.4,0.7,2.9,0.6,4.4 C178.9,135.4,178.8,135.8,178.7,136.3z M136.3,135.8c-1.6-3.9-2.4-8.3-0.5-11.9c1.2-2.4,4.5-4.5,7.1-3.1c2.1,1.1,3.1,4.3,1.3,6 c-0.3,0.3,0.1,1,0.5,0.7c2.2-1.7,6.2-4.1,8.1-0.7c1.2,2.2,0.5,5-1.3,6.6c-2.1,1.9-5.1,2.4-7.9,2.6 C141.2,136.2,138.7,136.1,136.3,135.8z M91.2,98.1c-0.3-1.1-0.4-2.2-0.1-3.4c0.4-2.1,2.5-4.1,4.7-4.1c2.7,0,3.5,2.8,2.4,4.9 C96.6,98.7,93.9,98.9,91.2,98.1z"/>
            </svg>
              </figure>
              {{--
                | CE FORMULAIRE POSTE RÉELLEMENT — et c'était le contraire.
                |
                | Le prototype le portait avec `data-vf-form`, dont le
                | gestionnaire (`verifall.js`) appelle `ev.preventDefault()`,
                | attend 900 ms et affiche « Message envoyé ! Notre équipe vous
                | répondra très vite. » Aucune `action`, aucun `name` sur les
                | champs, aucune route POST côté serveur.
                |
                | **Quelqu'un écrivait, était remercié, et son message
                | n'arrivait nulle part** — sur un site où l'on signale des
                | intox. Les deux attributs sont retirés : les laisser ferait
                | ravaler la soumission par le script, et le message de succès
                | s'afficherait encore par-dessus le vrai envoi.
                |
                | Le balisage, les classes et les libellés sont inchangés. Seuls
                | s'ajoutent : `method`, `action`, `@csrf`, les `name`, le
                | rappel des erreurs, et les deux champs anti-robots.
              --}}
              @if (session('contact'))
                <div class="md:col-span-2 rounded-lg border border-primary/30 bg-primary/5 px-4 py-3 text-heading" role="status">
                  {{ session('contact') }}
                </div>
              @endif
              <form method="POST" action="{{ route('public.contact.send') }}" class="grid md:grid-cols-2 gap-5">
                @csrf
                {{-- Le piège. Masqué par le STYLE et non par `type="hidden"` :
                     les robots ignorent les champs cachés, mais remplissent
                     volontiers ce qui ressemble à un champ ordinaire. Il
                     s'appelle `website` — un robot qui reconnaît « honeypot »
                     l'évite. `aria-hidden` et `tabindex` le retirent aux
                     lecteurs d'écran et au parcours clavier. --}}
                <div class="sr-only" aria-hidden="true">
                  <label for="ct-website">{{ __('contact.ne_remplissez_pas_ce_champ') }}</label>
                  <input type="text" id="ct-website" name="website" tabindex="-1" autocomplete="off">
                </div>
                <input type="hidden" name="rendu_a" value="{{ time() }}">
                <div>
                  <label class="block text-sm font-medium text-heading mb-2" for="ct-nom">{{ __('contact.nom_complet') }}</label>
                  <input type="text" id="ct-nom" name="name" value="{{ old('name') }}" required autocomplete="name" class="w-full border border-line rounded-lg px-4 py-3 text-heading outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 plain" placeholder="{{ __('contact.ph_votre_nom') }}">
                  @error('name')<p class="text-sm text-vfalse mt-2" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                  <label class="block text-sm font-medium text-heading mb-2" for="ct-email">{{ __('contact.adresse_e_mail') }}</label>
                  <input type="email" id="ct-email" name="email" value="{{ old('email') }}" required autocomplete="email" class="w-full border border-line rounded-lg px-4 py-3 text-heading outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 plain" placeholder="{{ __('contact.ph_nom_exemple_com') }}">
                  @error('email')<p class="text-sm text-vfalse mt-2" role="alert">{{ $message }}</p>@enderror
                </div>
                <div>
                  <label class="block text-sm font-medium text-heading mb-2" for="ct-tel">{{ __('contact.telephone') }}</label>
                  <input type="tel" id="ct-tel" name="phone" value="{{ old('phone') }}" autocomplete="tel" class="w-full border border-line rounded-lg px-4 py-3 text-heading outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 plain" placeholder="+229 xx xx xx xx">
                </div>
                <div>
                  <label class="block text-sm font-medium text-heading mb-2" for="ct-org">{{ __('contact.organisation') }}</label>
                  <input type="text" id="ct-org" name="organisation" value="{{ old('organisation') }}" autocomplete="organization" class="w-full border border-line rounded-lg px-4 py-3 text-heading outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 plain" placeholder="{{ __('contact.ph_nom_de_l_organisation') }}">
                </div>
                <div class="md:col-span-2">
                  <label class="block text-sm font-medium text-heading mb-2" for="ct-msg">{{ __('contact.votre_message') }}</label>
                  <textarea id="ct-msg" name="message" required class="w-full h-[150px] border border-line rounded-lg px-4 py-3 text-heading outline-none focus:border-primary focus:ring-4 focus:ring-primary/15 plain">{{ old('message') }}</textarea>
                  @error('message')<p class="text-sm text-vfalse mt-2" role="alert">{{ $message }}</p>@enderror
                </div>
                <div class="md:col-span-2">
                  <button type="submit" class="w-full inline-flex justify-center items-center rounded-lg bg-primary text-white text-lg font-semibold px-4 py-3.5 hover:bg-primary-dark transition-colors">{{ __('contact.envoyer_le_message') }}</button>
                </div>
              </form>
            </div>
          </div>
        </div>
      </section>
    </main>
</x-layouts.public>
