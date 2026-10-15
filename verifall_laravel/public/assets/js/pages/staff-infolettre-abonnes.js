/*
 | Infolettre, onglet « Abonnés » — la description de la table, et QUAND la monter.
 |
 | Passé sous DataTables le 14 septembre 2026, sur le contrat du pilote
 | (docs/DATATABLES_CONTRAT.md, §9 bis pour le montage par onglet). Le
 | comportement commun vit dans verifall-datatables.js.
 |
 | Les onglets de cet écran sont rendus par LIVEWIRE (`changerOnglet`, `?vue=`),
 | pas par ce fichier. Le panneau des abonnés est toujours dans le DOM et
 | seulement `hidden` hors de son onglet ; ce fichier le surveille après chaque
 | rendu Livewire et :
 |
 |   · ne monte RIEN tant que le panneau est masqué — arrivée directe sur
 |     « Diffuser » ou « Historique » : aucune requête, aucune mesure faussée ;
 |   · monte la table à la première ouverture ;
 |   · réajuste colonnes et Responsive à chaque retour sur l'onglet.
 |
 | Il ne touche NI à l'éditeur Quill, NI à la modale d'envoi : ils ont leur
 | propre pont dans la vue.
 */
(function (window, document) {
    'use strict';

    function demarrer() {
        var V = window.VerifallDataTables;

        if (!V || !window.DataTable) {
            return;
        }

        var e = V.echapper;
        var table = null;
        var etaitVisible = false;

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

        var ETATS = {
            confirme: ['st-badge-pub', 'Confirmé'],
            en_attente: ['st-badge-draft', 'En attente'],
            desabonne: ['st-badge-arch', 'Désabonné'],
        };

        function configuration() {
            return {
                // Les inscriptions les plus récentes d'abord, comme la liste d'avant.
                ordre: [[1, 'desc']],
                actions: ['retirer'],
                langue: {
                    emptyTable: "Aucun abonné pour l'instant. L'abonnement se fait depuis le site public, et rien n'y est ajouté à la main.",
                    zeroRecords: 'Aucune adresse ne correspond à vos critères.',
                    processing: 'Chargement des abonnés…',
                },
                colonnes: [
                    {
                        data: 'email', name: 'email', type: 'string', responsivePriority: 1,
                        render: rendu(
                            function (email, l) { return '<span class="vdt-titre ifl-email">' + e(email) + '</span>' + sousTexte(l.nom); },
                            function (email) { return email; }
                        ),
                    },
                    {
                        data: 'inscritLe', name: 'inscritLe', type: 'date', responsivePriority: 5,
                        render: rendu(function (v, l) { return e(l.inscritLeTexte || '—'); }, function (v, l) { return l.inscritLeTexte || ''; }),
                    },
                    {
                        // La date de confirmation EST le consentement : un tiret
                        // veut dire « rien ne doit partir vers cette adresse ».
                        data: 'confirmeLe', name: 'confirmeLe', type: 'date', responsivePriority: 4,
                        render: rendu(function (v, l) { return e(l.confirmeLeTexte || '—'); }, function (v, l) { return l.confirmeLeTexte || ''; }),
                    },
                    {
                        data: 'etat', name: 'etat', orderable: false, responsivePriority: 3,
                        render: rendu(
                            function (v, l) {
                                var etat = ETATS[v] || ['st-badge-info', v];

                                return '<span class="st-badge ' + etat[0] + '">' + e(etat[1]) + '</span>'
                                    + (l.desabonneLeTexte ? sousTexte('le ' + l.desabonneLeTexte) : '');
                            },
                            function (v) { return (ETATS[v] || [null, v])[1]; }
                        ),
                    },
                    {
                        data: null, name: 'actions', orderable: false, searchable: false, responsivePriority: 2,
                        className: 'vdt-sans-export vdt-actions',
                        render: function (donnee, type, l) {
                            if (type !== 'display' || !l.actions.desabonner) { return ''; }

                            return '<button type="button" class="st-icnbtn" data-vdt-action="retirer" data-vdt-uuid="' + e(l.uuid) + '"'
                                + ' title="Désabonner cette adresse" aria-label="Désabonner cette adresse">'
                                + '<span class="material-symbols-rounded" aria-hidden="true">unsubscribe</span></button>';
                        },
                    },
                ],
            };
        }

        /** Après le chargement et après chaque rendu Livewire. */
        function verifier() {
            var racine = document.querySelector('[data-vdt="abonnes"]');
            var panneau = racine ? racine.closest('[data-ifl-panneau]') : null;
            var visible = !!panneau && !panneau.hidden;

            if (!visible) {
                etaitVisible = false;

                return;
            }

            if (etaitVisible) {
                return; // déjà ouvert : un rendu Livewire quelconque (toast…), rien à refaire
            }

            etaitVisible = true;

            if (table === null) {
                table = V.monter(racine, configuration());
                table.catch(function (erreur) {
                    table = null;
                    if (window.console) { window.console.error(erreur); }
                });

                return;
            }

            // Retour sur l'onglet : le panneau était masqué, les largeurs sont à refaire.
            table.then(function (t) {
                t.columns.adjust();
                if (t.responsive) { t.responsive.recalc(); }
            });
        }

        verifier();

        var brancher = function () {
            window.Livewire.hook('morphed', function () { verifier(); });
        };

        if (window.Livewire) { brancher(); } else { document.addEventListener('livewire:init', brancher); }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', demarrer);
    } else {
        demarrer();
    }
})(window, document);
