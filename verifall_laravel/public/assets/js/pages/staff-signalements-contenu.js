/*
 | Écran « Contenus signalés » — la description de la table, et rien d'autre.
 |
 | Passé sous DataTables le 14 septembre 2026, sur le contrat du pilote
 | Utilisateurs (docs/DATATABLES_CONTRAT.md). Le comportement commun vit dans
 | verifall-datatables.js ; ce fichier ne dit que les colonnes, leur rendu, et
 | la méthode Livewire que le bouton de ligne a le droit d'appeler.
 |
 | Motif, détail et extrait du contenu visé sont du texte saisi par le PUBLIC :
 | chaque valeur passe par `e()` avant d'entrer dans le HTML d'une cellule.
 */
(function (window, document) {
    'use strict';

    /*
     | Clés sémantiques de `<x-badge-statut>` → classes staff. Même table que le
     | composant Blade : le serveur envoie la clé, jamais une classe.
     */
    var BADGES = {
        'publie': 'st-badge-pub',
        'brouillon': 'st-badge-draft',
        'archive': 'st-badge-arch',
        'danger': 'st-badge-danger',
        'en-cours': 'st-badge-info',
        'info': 'st-badge-info',
    };

    function monter() {
        var racine = document.querySelector('[data-vdt="signalements-contenu"]');
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

        V.monter(racine, {
            // La règle de la file : ce qui se répand vite passe devant. Le
            // serveur départage ensuite par ancienneté.
            ordre: [[1, 'desc']],
            actions: ['ouvrir'],
            langue: {
                emptyTable: "Aucun signalement n'a encore été reçu. Le bouton « signaler » du forum alimente cette file.",
                zeroRecords: 'Aucun signalement ne correspond à vos critères.',
                processing: 'Chargement des signalements…',
            },
            colonnes: [
                {
                    data: 'type', name: 'type', orderable: false, responsivePriority: 1,
                    render: rendu(
                        function (type, l) {
                            return '<span class="vdt-titre">' + e(type) + '</span>'
                                + (l.contenuSupprime ? sousTexte("Le contenu signalé n'existe plus.") : sousTexte(l.extrait))
                                + (l.detail ? sousTexte('« ' + l.detail + ' »') : '');
                        },
                        function (type, l) { return type + (l.extrait ? ' — ' + l.extrait : ''); }
                    ),
                },
                {
                    data: 'propagation', name: 'propagation', responsivePriority: 4,
                    render: rendu(
                        function (texte, l) {
                            // La mesure, jamais une conclusion.
                            return l.flambee ? '<span class="st-badge st-badge-danger">' + e(texte) + '</span>' : sousTexte(texte);
                        },
                        function (texte) { return texte; }
                    ),
                },
                { data: 'motif', name: 'motif', type: 'string', responsivePriority: 5, render: rendu(e, function (v) { return v; }) },
                {
                    data: 'signalePar', name: 'signalePar', orderable: false, responsivePriority: 7,
                    // Pas de nom : compte supprimé, ou détection automatique.
                    render: rendu(function (v) { return v ? e(v) : sousTexte('Compte supprimé'); }, function (v) { return v || 'Compte supprimé'; }),
                },
                {
                    data: 'statut', name: 'statut', responsivePriority: 3,
                    render: rendu(
                        function (v, l) { return '<span class="st-badge ' + (BADGES[l.statutBadge] || 'st-badge-info') + '">' + e(l.statutLibelle) + '</span>'; },
                        function (v, l) { return l.statutLibelle; }
                    ),
                },
                {
                    data: 'recuLe', name: 'recuLe', type: 'date', responsivePriority: 6,
                    render: rendu(function (v, l) { return e(l.recuLeTexte || '—'); }, function (v, l) { return l.recuLeTexte || ''; }),
                },
                {
                    data: null, name: 'actions', orderable: false, searchable: false, responsivePriority: 2,
                    className: 'vdt-sans-export vdt-actions',
                    render: function (donnee, type, l) {
                        if (type !== 'display' || !l.actions.ouvrir) { return ''; }

                        var titre = l.actions.trancher ? 'Ouvrir et trancher ce signalement' : 'Ouvrir ce signalement';

                        return '<button type="button" class="st-icnbtn" data-vdt-action="ouvrir" data-vdt-uuid="' + e(l.uuid) + '"'
                            + ' title="' + e(titre) + '" aria-label="' + e(titre) + '">'
                            + '<span class="material-symbols-rounded" aria-hidden="true">' + (l.actions.trancher ? 'gavel' : 'visibility') + '</span></button>';
                    },
                },
            ],
        }).catch(function (erreur) {
            if (window.console) { window.console.error(erreur); }
        });

        /*
         | Le panneau « Signalement ouvert » est rendu par Livewire AU-DESSUS de
         | la table. Ouvert depuis la vingtième ligne, il apparaîtrait hors de
         | vue : on l'amène à l'écran, et le focus avec lui (clavier, lecteur
         | d'écran), une fois le rendu Livewire terminé.
         */
        var attendPanneau = false;

        racine.addEventListener('click', function (ev) {
            var cible = ev.target instanceof Element ? ev.target.closest('[data-vdt-action="ouvrir"]') : null;
            if (cible) { attendPanneau = true; }
        });

        var brancher = function () {
            window.Livewire.hook('morphed', function () {
                var panneau = document.getElementById('signalement-ouvert');

                if (attendPanneau && panneau) {
                    attendPanneau = false;
                    panneau.scrollIntoView({ block: 'start', behavior: 'smooth' });
                    panneau.focus({ preventScroll: true });
                }
            });
        };

        if (window.Livewire) { brancher(); } else { document.addEventListener('livewire:init', brancher); }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', monter);
    } else {
        monter();
    }
})(window, document);
