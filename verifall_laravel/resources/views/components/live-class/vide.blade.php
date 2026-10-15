{{-- État vide d'un onglet de Live Classes — repris du prototype. --}}
@props(['message'])

<div class="card p-8 text-center">
  <span class="material-symbols-rounded text-slate2 !text-[36px] block mb-2" aria-hidden="true">videocam_off</span>
  <p class="text-sm text-slate2 mb-0">{{ $message }}</p>
</div>
