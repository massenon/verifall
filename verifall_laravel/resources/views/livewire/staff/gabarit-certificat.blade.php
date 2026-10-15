@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/pages/user-certificat.css') }}">
@endpush

{{--
  | « Certificat Template » (lot 4) — l'aperçu qui manquait.
  |
  | PAS de formulaire : les six réglages se saisissent aux Paramètres, onglet
  | « Certificats ». Un second formulaire pour les mêmes clés, c'est deux
  | endroits où écrire la même valeur — donc un endroit où l'on oublie de la
  | changer.
  |
  | Ce qui manquait n'était pas la saisie mais le RENDU : on réglait une
  | couleur, on déposait une signature, et l'on découvrait le résultat en
  | délivrant un vrai diplôme à une vraie personne.
--}}
<div class="p-6 lg:p-8">

    <nav class="text-xs text-[#64748B] mb-2" aria-label="Fil d'Ariane">
        <a href="{{ route('staff.dashboard') }}" class="hover:text-[#065F46]">Administration</a>
        <span aria-hidden="true">/</span>
        <span class="text-[#0F172A] font-medium">Certificat Template</span>
    </nav>

    <header class="mb-6 flex flex-col lg:flex-row lg:items-end lg:justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-display font-bold text-[#0F172A] leading-tight mb-1">Certificat Template</h1>
            <p class="text-[#64748B] text-sm mb-0">
                Le document tel qu'il sortira, sur un exemple. Les valeurs se règlent aux
                <a href="{{ route('staff.settings') }}?onglet=certificats" class="text-[#059669] underline">Paramètres, onglet Certificats</a>.
            </p>
        </div>

        <a href="{{ route('staff.settings') }}?onglet=certificats" class="st-btn st-btn-primary">
            <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">tune</span>
            Régler le certificat
        </a>
    </header>

    <div class="st-card p-4 mb-6 border-l-4" style="border-left-color:#8A5A0B">
        <p class="text-sm text-[#334155] mb-2">
            <strong>Ceci est un exemple, il n'est pas enregistré.</strong>
            Son code <code>VERIF-EXEMPLE-0000</code> ne validera jamais rien sur la page
            publique de vérification, et aucune ligne n'est créée dans la table des diplômes.
        </p>
        <p class="text-sm text-[#334155] mb-0">
            <strong>Avant de vous fier à l'écran, imprimez-le une fois.</strong> Les navigateurs
            retirent par défaut les fonds et les dégradés à l'impression&nbsp;; la feuille de style
            l'empêche, mais ce comportement varie d'un moteur à l'autre et ne se vérifie que sur papier.
        </p>
    </div>

    {{-- Fond gris volontaire : il détache le document et donne l'échelle d'une
         feuille posée sur un bureau. Sur fond blanc, on ne verrait pas où
         s'arrête le certificat. --}}
    <div class="rounded-2xl p-6 sm:p-10" style="background:#EEF2F0">
        <div class="max-w-3xl mx-auto">
            <x-lms.certificat :certificat="$certificat" :habillage="$habillage" :qr-code="$qrCode" />
        </div>
    </div>

    <div class="flex flex-wrap gap-2 justify-center mt-6 no-print">
        <button type="button" onclick="window.print()" class="st-btn st-btn-secondary">
            <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">print</span>
            Épreuve d'impression
        </button>
    </div>

    <section class="mt-8 st-card p-5">
        <h2 class="font-display font-bold text-[#0F172A] mb-3">Ce qui se règle, et ce qui ne se règle pas</h2>
        <div class="grid sm:grid-cols-2 gap-5 text-sm text-[#334155]">
            <div>
                <p class="font-bold mb-1">Réglable</p>
                <ul class="list-disc pl-5 mb-0 space-y-1">
                    <li>La couleur du décor — teinte des vagues, en tête et en pied.</li>
                    <li>La signature numérisée, le nom et la qualité du signataire.</li>
                    <li>Un filigrane de fond, posé en très faible opacité.</li>
                    <li>La mention de bas de page.</li>
                </ul>
            </div>
            <div>
                <p class="font-bold mb-1">Non réglable, et pourquoi</p>
                <ul class="list-disc pl-5 mb-0 space-y-1">
                    <li><strong>La disposition.</strong> Le document est une page HTML que le
                        navigateur imprime : elle se recompose selon la largeur. Des positions
                        en points, comme sur un PDF, n'y voudraient rien dire.</li>
                    <li><strong>Le code de vérification.</strong> C'est lui qui donne sa valeur
                        au diplôme ; il reste au même endroit sur tous les certificats pour
                        qu'on sache où le chercher.</li>
                </ul>
            </div>
        </div>
    </section>
</div>
