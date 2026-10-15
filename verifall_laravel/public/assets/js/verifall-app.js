/* verifall-app.js — comportements transverses de l'application.
   Complète les scripts du prototype ; ne les remplace pas. */
(function () {
    'use strict';

    /* Accordéon KPI — présent sur toutes les pages (PAGES_KPIS_UX_MOBILE_DESKTOP).
       L'état n'est piloté qu'en mobile : dès 768 px, la CSS force l'affichage
       de la grille, le bouton étant masqué.

       UN SEUL ÉCOUTEUR, DÉLÉGUÉ SUR LE DOCUMENT (14/09/2026). Auparavant chaque
       bouton était lié ici ET dans `citizen.js` : le clic basculait `is-open`
       puis le rebasculait aussitôt, et l'accordéon restait fermé. La garde
       `data-vf-bound` ne pouvait rien y faire, `citizen.js` l'ignorant.

       La délégation règle aussi le cas Livewire : un bouton réinjecté par un
       rendu n'a rien à « relier ». La sentinelle protège contre un second
       chargement du script (navigation `wire:navigate`). */
    if (!document.vfAccordeonKpiLie) {
        document.vfAccordeonKpiLie = true;

        document.addEventListener('click', function (evenement) {
            var bouton = evenement.target.closest ? evenement.target.closest('[data-kpi-accordion] .kpi-toggle') : null;
            if (!bouton) { return; }

            var bloc = bouton.closest('[data-kpi-accordion]');
            var ouvert = bloc.classList.toggle('is-open');
            bouton.setAttribute('aria-expanded', ouvert ? 'true' : 'false');
        });
    }

    /* Un rendu Livewire recopie les attributs du serveur, qui ne connaît pas
       `is-open` : l'accordéon ouvert se refermait à la moindre frappe dans une
       recherche. On reporte l'état ouvert sur l'élément entrant. */
    function garderAccordeonOuvert() {
        if (document.vfAccordeonKpiMorph || !window.Livewire || typeof window.Livewire.hook !== 'function') { return; }
        document.vfAccordeonKpiMorph = true;

        window.Livewire.hook('morph.updating', function (morph) {
            var el = morph && morph.el;
            var cible = morph && morph.toEl;
            if (!el || !cible || el.nodeType !== 1 || cible.nodeType !== 1) { return; }

            if (el.hasAttribute('data-kpi-accordion') && el.classList.contains('is-open')) {
                cible.classList.add('is-open');
            } else if (el.classList.contains('kpi-toggle') && el.closest('[data-kpi-accordion].is-open')) {
                cible.setAttribute('aria-expanded', 'true');
            }
        });
    }

    garderAccordeonOuvert();
    document.addEventListener('livewire:init', garderAccordeonOuvert);

    /* ------------------------------------------------------------ le solde
     *
     * Le solde d'unités est affiché à DEUX endroits : dans l'en-tête, qui
     * appartient au layout, et dans la page, qui appartient au composant
     * Livewire. Seul le second se rafraîchit — l'en-tête gardait donc l'ancien
     * chiffre après une dépense, et l'écran montrait deux soldes différents
     * pour le même compte. Sur le nombre auquel les gens se fient le plus, ce
     * n'est pas un détail.
     *
     * Le serveur annonce le nouveau solde, tous les porteurs se mettent à jour.
     */
    function majSolde(solde) {
        var affichage = new Intl.NumberFormat('fr-FR').format(solde);

        document.querySelectorAll('[data-units-badge]').forEach(function (el) {
            el.textContent = affichage;
        });
    }

    document.addEventListener('livewire:init', function () {
        if (!window.Livewire) { return; }

        window.Livewire.on('solde-change', function (charge) {
            var detail = Array.isArray(charge) ? charge[0] : charge;

            if (detail && typeof detail.solde !== 'undefined') { majSolde(detail.solde); }
        });
    });

    window.VF = window.VF || {};
    window.VF.majSolde = majSolde;
})();
