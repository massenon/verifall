/*
 | Écran « Sources vérifiées » — la description de la table, et rien d'autre.
 |
 | Migration DataTables du 14 septembre 2026 (docs/DATATABLES_CONTRAT.md).
 | Les boutons de ligne appellent les méthodes Livewire EXISTANTES du
 | composant ; le serveur dit, ligne par ligne, lesquelles la Policy accorde.
 |
 | Chaque valeur reçue est un TEXTE et passe par `e()`.
 */
(function (window, document) {
    'use strict';

    function monter() {
        var racine = document.querySelector('[data-vdt="sources-verifiees"]');
        var V = window.VerifallDataTables;

        if (!racine || !V || !window.DataTable) {
            return;
        }

        var e = V.echapper;

        function rendu(affichage, texte) {
            return function (donnee, type, ligne) {
                if (type === 'display') { return affichage(donnee, ligne); }
                if (type === 'export') { return texte(donnee, ligne); }

                return donnee;
            };
        }

        function sousTexte(texte) {
            return texte ? '<span class="vdt-sous-texte">' + e(texte) + '</span>' : '';
        }

        function boutonAction(methode, uuid, icone, titre) {
            return '<button type="button" class="btn-icone" data-vdt-action="' + e(methode) + '" data-vdt-uuid="' + e(uuid) + '"'
                + ' title="' + e(titre) + '" aria-label="' + e(titre) + '">'
                + '<span class="material-symbols-rounded" aria-hidden="true">' + e(icone) + '</span></button>';
        }

        V.monter(racine, {
            titreExport: "Sources vérifiées — Vérif'All",
            fichierExport: 'sources-verifiees-verifall',
            // L'ordre de l'ancienne liste : les actives d'abord, puis par domaine.
            ordre: [[4, 'desc'], [1, 'asc']],
            actions: ['basculerLActivation', 'ouvrirEdition', 'demanderLaSuppression'],
            langue: {
                emptyTable: 'Aucune source référencée. Tant que ce référentiel est vide, le moteur ne juge les domaines que par leur forme.',
                zeroRecords: 'Aucune source ne correspond à vos critères.',
                processing: 'Chargement du référentiel…',
            },
            colonnes: [
                {
                    data: 'nom', name: 'nom', type: 'string', responsivePriority: 1,
                    render: rendu(
                        function (nom) { return '<span class="vdt-titre">' + e(nom) + '</span>'; },
                        function (nom) { return nom; }
                    ),
                },
                {
                    data: 'domaine', name: 'domaine', type: 'string', responsivePriority: 4,
                    render: rendu(
                        function (domaine) { return '<span class="font-mono text-xs text-[#64748B]">' + e(domaine) + '</span>'; },
                        function (domaine) { return domaine; }
                    ),
                },
                {
                    data: 'famille', name: 'famille', type: 'string', responsivePriority: 5,
                    render: rendu(
                        // Une fiche orpheline se dit, elle ne se cache pas.
                        function (famille) { return famille ? '<span class="pastille p-info">' + e(famille) + '</span>' : sousTexte('Sans famille'); },
                        function (famille) { return famille || 'Sans famille'; }
                    ),
                },
                {
                    data: 'credibilite', name: 'credibilite', type: 'num', responsivePriority: 6,
                    render: rendu(function (v) { return e(v + ' / 100'); }, function (v) { return v; }),
                },
                {
                    data: 'effet', name: 'effet', responsivePriority: 3,
                    render: rendu(
                        function (effet) {
                            return effet === 'reconnue'
                                ? '<span class="pastille p-bon">Reconnue</span>' + sousTexte("Exemptée du soupçon d'usurpation")
                                : '<span class="pastille p-neutre">Ignorée</span>' + sousTexte('Le moteur la traite comme un domaine inconnu');
                        },
                        function (effet) { return effet === 'reconnue' ? 'Reconnue' : 'Ignorée'; }
                    ),
                },
                {
                    data: null, name: 'actions', orderable: false, searchable: false, responsivePriority: 2,
                    className: 'vdt-sans-export vdt-actions',
                    render: function (donnee, type, l) {
                        if (type !== 'display') { return ''; }

                        var a = l.actions;
                        var html = '';

                        if (a.desactiver) { html += boutonAction('basculerLActivation', l.uuid, 'visibility_off', 'Désactiver'); }
                        if (a.reactiver) { html += boutonAction('basculerLActivation', l.uuid, 'visibility', 'Réactiver'); }
                        if (a.modifier) { html += boutonAction('ouvrirEdition', l.uuid, 'edit', 'Modifier'); }
                        if (a.supprimer) { html += boutonAction('demanderLaSuppression', l.uuid, 'delete', 'Supprimer'); }

                        return html || sousTexte('—');
                    },
                },
            ],
        }).catch(function (erreur) {
            if (window.console) { window.console.error(erreur); }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', monter);
    } else {
        monter();
    }
})(window, document);
