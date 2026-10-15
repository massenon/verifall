/*
 | Écran « Liste des examens » — la description de la table, et rien d'autre.
 |
 | Migration DataTables du 14 septembre 2026 (contrat : docs/DATATABLES_CONTRAT.md).
 | Les sessions de l'épreuve dépliée ne sont PAS ici : Livewire les rend sous
 | la table, au clic sur « Voir les sessions » (`deplierLesSessions`).
 */
(function (window, document) {
    'use strict';

    function monter() {
        var racine = document.querySelector('[data-vdt="examens"]');
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

        function badge(classe, texte) {
            return '<span class="st-badge ' + classe + '">' + e(texte) + '</span>';
        }

        function sousTexte(texte) {
            return texte ? '<span class="vdt-sous-texte">' + e(texte) + '</span>' : '';
        }

        var BADGES_STATUT = { draft: 'st-badge-draft', published: 'st-badge-pub', archived: 'st-badge-arch' };

        function boutonAction(methode, uuid, icone, titre) {
            return '<button type="button" class="st-icnbtn" data-vdt-action="' + e(methode) + '" data-vdt-uuid="' + e(uuid) + '"'
                + ' title="' + e(titre) + '" aria-label="' + e(titre) + '">'
                + '<span class="material-symbols-rounded" aria-hidden="true">' + e(icone) + '</span></button>';
        }

        V.monter(racine, {
            ordre: [[5, 'desc']],
            actions: ['deplierLesSessions', 'publier', 'ouvrirEdition', 'archiver'],
            langue: {
                emptyTable: 'Aucun examen. Créez une épreuve, attachez-lui des questions depuis la banque, puis publiez-la.',
                zeroRecords: 'Aucune épreuve ne correspond à vos critères.',
                processing: 'Chargement des examens…',
            },
            colonnes: [
                {
                    data: 'titre', name: 'titre', type: 'string', responsivePriority: 1,
                    render: rendu(
                        function (titre, l) {
                            return '<span class="vdt-titre">' + e(titre) + '</span>'
                                + sousTexte(l.duree + ' min · ' + l.tentatives + ' tentative(s) · ' + l.sessions + ' session(s)');
                        },
                        function (titre) { return titre; }
                    ),
                },
                {
                    data: 'cours', name: 'cours', responsivePriority: 6,
                    render: rendu(function (v) { return v ? e(v) : sousTexte('Transverse'); }, function (v) { return v || 'Transverse'; }),
                },
                {
                    data: 'seuil', name: 'seuil', type: 'num', responsivePriority: 5,
                    render: rendu(function (v) { return e(v) + ' %'; }, function (v) { return v + ' %'; }),
                },
                {
                    data: 'candidats', name: 'candidats', type: 'num', responsivePriority: 7,
                    render: rendu(
                        function (v, l) { return e(v) + (l.tauxReussite !== null ? sousTexte(l.tauxReussite + ' % de réussite') : ''); },
                        function (v) { return v; }
                    ),
                },
                {
                    data: 'statut', name: 'statut', responsivePriority: 3,
                    render: rendu(
                        function (v, l) { return badge(BADGES_STATUT[v] || 'st-badge-draft', l.statutLibelle) + (l.publieLeTexte ? sousTexte('le ' + l.publieLeTexte) : ''); },
                        function (v, l) { return l.statutLibelle; }
                    ),
                },
                {
                    data: 'creeLe', name: 'creeLe', type: 'date', responsivePriority: 8,
                    render: rendu(function (v, l) { return e(l.creeLeTexte || '—'); }, function (v, l) { return l.creeLeTexte || ''; }),
                },
                {
                    data: null, name: 'actions', orderable: false, searchable: false, responsivePriority: 2,
                    className: 'vdt-sans-export vdt-actions',
                    render: function (donnee, type, l) {
                        if (type !== 'display') { return ''; }

                        var html = '';

                        if (l.actions.sessions) {
                            html += boutonAction('deplierLesSessions', l.uuid, 'event', 'Voir les sessions');
                        }
                        if (l.actions.publier) {
                            html += boutonAction('publier', l.uuid, 'publish', 'Publier');
                        }
                        if (l.actions.modifier) {
                            html += boutonAction('ouvrirEdition', l.uuid, 'edit', 'Modifier');
                        }
                        if (l.actions.archiver) {
                            html += boutonAction('archiver', l.uuid, 'inventory_2', 'Archiver');
                        }

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
