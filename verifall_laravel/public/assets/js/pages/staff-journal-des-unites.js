/*
 | Écran « Historiques des Unités IA » — la description de la table.
 |
 | Migration DataTables du 14 septembre 2026 (docs/DATATABLES_CONTRAT.md).
 | Aucune action de ligne : un mouvement ne se modifie ni ne se supprime, il
 | se corrige par un mouvement inverse (bouton « Ajuster un compte »).
 |
 | Chaque valeur reçue est un TEXTE et passe par `e()`.
 */
(function (window, document) {
    'use strict';

    function monter() {
        var racine = document.querySelector('[data-vdt="journal-des-unites"]');
        var V = window.VerifallDataTables;

        if (!racine || !V || !window.DataTable) {
            return;
        }

        var e = V.echapper;
        var nombres = new Intl.NumberFormat('fr-FR');

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

        function signe(n) {
            return (n > 0 ? '+' : '') + nombres.format(n);
        }

        V.monter(racine, {
            titreExport: "Historiques des Unités IA — Vérif'All",
            fichierExport: 'journal-des-unites-verifall',
            // Aucun tri initial : les derniers mouvements d'abord (`id desc`).
            ordre: [],
            // UnitLogRepositoryInterface::COLONNES_SEARCHBUILDER, et elles seules.
            searchBuilder: [0, 3, 4, 5],
            actions: [],
            langue: {
                emptyTable: "Aucun mouvement : le journal est vide tant qu'aucune unité n'a circulé.",
                zeroRecords: 'Aucun mouvement ne correspond à vos critères.',
                processing: 'Chargement du journal…',
            },
            colonnes: [
                {
                    data: 'date', name: 'date', type: 'date', responsivePriority: 3,
                    render: rendu(function (v, l) { return e(l.dateTexte || '—'); }, function (v, l) { return l.dateTexte || ''; }),
                },
                {
                    data: 'compte', name: 'compte', type: 'string', responsivePriority: 1,
                    render: rendu(
                        function (compte) {
                            // Un compte supprimé laisse ses mouvements : on le dit.
                            return compte
                                ? '<span class="vdt-titre">' + e(compte.nom) + '</span>' + sousTexte(compte.email)
                                : sousTexte('Compte supprimé');
                        },
                        function (compte) { return compte ? compte.nom + ' <' + compte.email + '>' : 'Compte supprimé'; }
                    ),
                },
                {
                    data: 'motif', name: 'motif', responsivePriority: 4,
                    render: rendu(
                        function (motif, l) {
                            var html = '<span class="st-badge st-badge-info">' + e(l.motifLibelle) + '</span>';

                            // Le libellé sous le motif seulement s'il dit autre chose :
                            // c'est la justification d'un ajustement, typiquement.
                            return l.libelle && l.libelle !== l.motifLibelle ? html + sousTexte(l.libelle) : html;
                        },
                        function (motif, l) { return l.motifLibelle; }
                    ),
                },
                {
                    // Masquée d'office : SearchBuilder l'interroge, « Colonnes »
                    // l'affiche à la demande.
                    data: 'libelle', name: 'libelle', type: 'string', orderable: false, visible: false, responsivePriority: 8,
                    render: rendu(function (v) { return e(v || '—'); }, function (v) { return v; }),
                },
                {
                    data: 'mouvement', name: 'mouvement', type: 'num', responsivePriority: 2,
                    render: rendu(
                        function (n) { return '<span class="font-semibold ' + (n > 0 ? 'text-[#059669]' : 'text-[#DC2626]') + '">' + e(signe(n)) + '</span>'; },
                        function (n) { return n; }
                    ),
                },
                {
                    data: 'soldeApres', name: 'soldeApres', type: 'num', responsivePriority: 5,
                    render: rendu(function (n) { return e(nombres.format(n)); }, function (n) { return n; }),
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
