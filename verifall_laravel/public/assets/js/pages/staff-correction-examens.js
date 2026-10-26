/*
 | Écran « Correction des examens » — la description de la file, et rien d'autre.
 |
 | Migration DataTables du 14 septembre 2026 (contrat : docs/DATATABLES_CONTRAT.md).
 | Le JSON ne porte que des métadonnées de copie : aucune réponse de candidat.
 | La copie s'ouvre par `ouvrirLaCopie` (Livewire), qui MASQUE la file sans la
 | retirer ; à son retour, on recalcule les largeurs.
 */
(function (window, document) {
    'use strict';

    function monter() {
        var racine = document.querySelector('[data-vdt="correction-examens"]');
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
            return '<span class="pastille ' + classe + '">' + e(texte) + '</span>';
        }

        function sousTexte(texte) {
            return texte ? '<span class="vdt-sous-texte">' + e(texte) + '</span>' : '';
        }

        var BADGES_STATUT = { in_progress: 'p-info', pending: 'p-alerte', grading: 'p-info', graded: 'p-bon', abandoned: 'p-neutre' };

        function boutonAction(methode, uuid, icone, titre) {
            return '<button type="button" class="btn-icone" data-vdt-action="' + e(methode) + '" data-vdt-uuid="' + e(uuid) + '"'
                + ' title="' + e(titre) + '" aria-label="' + e(titre) + '">'
                + '<span class="material-symbols-rounded" aria-hidden="true">' + e(icone) + '</span></button>';
        }

        V.monter(racine, {
            // La plus ancienne copie d'abord : c'est ce tri qui fait remonter
            // les copies oubliées (même ordre que la file Livewire).
            ordre: [[3, 'asc']],
            actions: ['prendreLaCopie', 'ouvrirLaCopie'],
            langue: {
                emptyTable: "Rien à corriger. La file se remplit dès qu'un candidat rend une copie.",
                zeroRecords: 'Aucune copie ne correspond à vos critères.',
                processing: 'Chargement de la file…',
            },
            colonnes: [
                {
                    data: 'reference', name: 'reference', type: 'string', responsivePriority: 1,
                    render: rendu(function (v) { return '<span class="vdt-titre">' + e(v) + '</span>'; }, function (v) { return v; }),
                },
                {
                    data: 'candidat', name: 'candidat', type: 'string', responsivePriority: 4,
                    render: rendu(function (v) { return v ? e(v) : sousTexte('Compte supprimé'); }, function (v) { return v || 'Compte supprimé'; }),
                },
                {
                    data: 'epreuve', name: 'epreuve', type: 'string', responsivePriority: 6,
                    render: rendu(function (v) { return v ? e(v) : sousTexte('Épreuve archivée'); }, function (v) { return v || ''; }),
                },
                {
                    data: 'soumiseLe', name: 'soumiseLe', type: 'date', responsivePriority: 5,
                    render: rendu(
                        function (v, l) {
                            var attente = '';

                            if (l.attenteHeures !== null) {
                                attente = l.urgente
                                    ? '<span class="vdt-sous-texte"><strong>' + e(l.attenteHeures) + ' h d\'attente</strong></span>'
                                    : sousTexte(l.attenteHeures + ' h d\'attente');
                            }

                            return e(l.soumiseLeTexte || '—') + attente;
                        },
                        function (v, l) { return l.soumiseLeTexte || ''; }
                    ),
                },
                {
                    data: 'note', name: 'note', type: 'num', responsivePriority: 8,
                    render: rendu(
                        function (v, l) { return v === null ? sousTexte('—') : e(v + ' %') + sousTexte(l.admis ? 'Admis' : 'Non admis'); },
                        function (v) { return v === null ? '' : v + ' %'; }
                    ),
                },
                {
                    data: 'correcteur', name: 'correcteur', orderable: false, responsivePriority: 7,
                    render: rendu(function (v) { return v ? e(v) : sousTexte('Non assignée'); }, function (v) { return v || 'Non assignée'; }),
                },
                {
                    data: 'statut', name: 'statut', responsivePriority: 3,
                    render: rendu(
                        function (v, l) { return badge(BADGES_STATUT[v] || 'p-info', l.statutLibelle); },
                        function (v, l) { return l.statutLibelle; }
                    ),
                },
                {
                    data: null, name: 'actions', orderable: false, searchable: false, responsivePriority: 2,
                    className: 'vdt-sans-export vdt-actions',
                    render: function (donnee, type, l) {
                        if (type !== 'display') { return ''; }

                        var html = '';

                        if (l.actions.prendre) {
                            html += boutonAction('prendreLaCopie', l.uuid, 'how_to_reg', 'Prendre en charge');
                        }
                        if (l.actions.ouvrir) {
                            html += boutonAction('ouvrirLaCopie', l.uuid, 'grading', 'Ouvrir la copie');
                        }

                        return html || sousTexte('—');
                    },
                },
            ],
        }).then(function (table) {
            /*
             | La file est masquée (`hidden` sur l'enveloppe) pendant qu'une copie
             | est ouverte. À son retour, les largeurs calculées à l'aveugle sont
             | fausses : on les recalcule. Les données, elles, sont rechargées par
             | l'événement Livewire après chaque écriture.
             */
            var enveloppe = racine.parentElement;

            if (!enveloppe || !window.MutationObserver) { return; }

            new window.MutationObserver(function () {
                if (!enveloppe.hidden) {
                    table.columns.adjust();
                    if (table.responsive) { table.responsive.recalc(); }
                }
            }).observe(enveloppe, { attributes: true, attributeFilter: ['hidden'] });
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
