{{-- Vue d'attente du Sprint 1a : prouve que le shell « staff » est fidèle et
     navigable. Remplacée par un composant Livewire au sprint du module. --}}
<x-layouts.staff :title="$pageTitle">
    <div class="p-6 lg:p-8">
        <div class="rounded-2xl border border-dashed border-line bg-surface p-8 text-center">
            <p class="text-xs font-semibold tracking-widest text-primary">SPRINT 1A</p>
            <h1 class="mt-2 text-2xl font-display font-bold">{{ $pageTitle }}</h1>
            <p class="mt-2 text-slate2">Shell « staff » transposé du prototype. Le contenu de cette page arrive à son sprint.</p>
        </div>
    </div>
</x-layouts.staff>
