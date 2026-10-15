/*
 | Écran « Banque de questions » — la description de la table, et rien d'autre.
 |
 | Migration DataTables du 14 septembre 2026 (contrat : docs/DATATABLES_CONTRAT.md).
 | Le comportement commun vit dans verifall-datatables.js ; ce fichier ne dit
 | que les colonnes de CET écran, leur rendu, et les méthodes Livewire que ses
 | boutons ont le droit d'appeler.
 |
 | Chaque valeur reçue est un TEXTE, échappé par `e()` avant d'entrer dans le
 | HTML d'une cellule. Le serveur n'envoie ni HTML, ni proposition, ni corrigé.
 */
(function (window, document) {
    'use strict';

    function monter() {
        var racine = document.querySelector('[data-vdt="banque-de-questions"]');
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

        function brut(v) { return v === null || v === undefined ? '' : v; }

        function badge(classe, texte) {
            return '<span class="st-badge ' + classe + '">' + e(texte) + '</span>';
        }

        function sousTexte(texte) {
            return texte ? '<span class="vdt-sous-texte">' + e(texte) + '</span>' : '';
        }

        var BADGES_STATUT = { draft: 'st-badge-draft', published: 'st-badge-pub', to_review: 'st-badge-warn', archived: 'st-badge-arch' };

        function boutonAction(methode, uuid, icone, titre) {
            return '<button type="button" class="st-icnbtn" data-vdt-action="' + e(methode) + '" data-vdt-uuid="' + e(uuid) + '"'
                + ' title="' + e(titre) + '" aria-label="' + e(titre) + '">'
                + '<span class="material-symbols-rounded" aria-hidden="true">' + e(icone) + '</span></button>';
        }

        V.monter(racine, {
            // Tri initial : les plus récentes d'abord, comme la liste remplacée.
            ordre: [[6, 'desc']],
            // DepotBanqueQuestions::COLONNES_SEARCHBUILDER, et elles seules.
            searchBuilder: [0, 3, 5, 6],
            actions: ['publier', 'mettreEnRevision', 'ouvrirEdition', 'demanderLaSuppression'],
            langue: {
                emptyTable: "Le fonds est vide. Tant qu'aucune question n'est publiée, aucun examen ne peut tirer de sujet.",
                zeroRecords: 'Aucune question ne correspond à vos critères.',
                processing: 'Chargement des questions…',
            },
            colonnes: [
                {
                    data: 'enonce', name: 'enonce', type: 'string', responsivePriority: 1,
                    render: rendu(
                        function (enonce, l) {
                            // Sans explication, le candidat apprend seulement
                            // qu'il a eu tort : la ligne le signale.
                            return '<span class="vdt-titre">' + e(enonce) + '</span>'
                                + (l.sansExplication ? sousTexte('Sans explication') : '');
                        },
                        brut
                    ),
                },
                {
                    data: 'type', name: 'type', responsivePriority: 5,
                    render: rendu(function (v, l) { return badge('st-badge-info', l.typeLibelle); }, function (v, l) { return l.typeLibelle; }),
                },
                {
                    data: 'cours', name: 'cours', responsivePriority: 6,
                    render: rendu(function (v) { return v ? e(v) : sousTexte('Transverse'); }, function (v) { return v || 'Transverse'; }),
                },
                { data: 'points', name: 'points', type: 'num', responsivePriority: 7 },
                {
                    data: 'statut', name: 'statut', responsivePriority: 3,
                    render: rendu(
                        function (v, l) { return badge(BADGES_STATUT[v] || 'st-badge-draft', l.statutLibelle); },
                        function (v, l) { return l.statutLibelle; }
                    ),
                },
                {
                    data: 'reponses', name: 'reponses', type: 'num', responsivePriority: 8,
                    render: rendu(
                        function (v, l) { return e(v) + (l.tauxReussite !== null ? sousTexte(l.tauxReussite + ' % de réussite') : ''); },
                        function (v) { return v; }
                    ),
                },
                {
                    data: 'creeLe', name: 'creeLe', type: 'date', responsivePriority: 9,
                    render: rendu(function (v, l) { return e(l.creeLeTexte || '—'); }, function (v, l) { return l.creeLeTexte || ''; }),
                },
                {
                    data: null, name: 'actions', orderable: false, searchable: false, responsivePriority: 2,
                    className: 'vdt-sans-export vdt-actions',
                    render: function (donnee, type, l) {
                        if (type !== 'display') { return ''; }

                        var html = '';

                        if (l.actions.publier) {
                            html += boutonAction('publier', l.uuid, 'publish', 'Publier au fonds');
                        }
                        if (l.actions.retirer) {
                            html += boutonAction('mettreEnRevision', l.uuid, 'visibility_off', 'Retirer des tirages');
                        }
                        if (l.actions.modifier) {
                            html += boutonAction('ouvrirEdition', l.uuid, 'edit', 'Modifier');
                        }
                        if (l.actions.supprimer) {
                            html += boutonAction('demanderLaSuppression', l.uuid, 'delete', 'Supprimer');
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
