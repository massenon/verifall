/*
 | Écran « Messages reçus » — la description de la table, et rien d'autre.
 |
 | Passé sous DataTables le 14 septembre 2026, sur le contrat du pilote
 | Utilisateurs (docs/DATATABLES_CONTRAT.md). Le comportement commun vit dans
 | verifall-datatables.js ; ce fichier ne dit que les colonnes, leur rendu, et
 | la méthode Livewire que le bouton de ligne a le droit d'appeler.
 |
 | Le nom, l'adresse et le message sont écrits par un INCONNU : chaque valeur
 | passe par `e()` avant d'entrer dans le HTML d'une cellule.
 */
(function (window, document) {
    'use strict';

    // Clés sémantiques de `<x-badge-statut>` → classes staff (même table que le composant).
    var BADGES = {
        'publie': 'p-bon',
        'brouillon': 'p-neutre',
        'archive': 'p-neutre',
        'danger': 'p-critique',
        'en-cours': 'p-info',
        'info': 'p-info',
    };

    function monter() {
        var racine = document.querySelector('[data-vdt="messages-contact"]');
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

        function badge(cle, libelle) {
            return '<span class="pastille ' + (BADGES[cle] || 'p-info') + '">' + e(libelle) + '</span>';
        }

        V.monter(racine, {
            // Le plus récent d'abord, comme la liste qu'elle remplace.
            ordre: [[4, 'desc']],
            actions: ['ouvrir'],
            langue: {
                emptyTable: "Aucun message n'a encore été reçu. Le formulaire de contact public alimente cette boîte.",
                zeroRecords: 'Aucun message ne correspond à vos critères.',
                processing: 'Chargement des messages…',
            },
            colonnes: [
                {
                    data: 'nature', name: 'nature', responsivePriority: 5,
                    render: rendu(function (v, l) { return badge(l.natureBadge, l.natureLibelle); }, function (v, l) { return l.natureLibelle; }),
                },
                {
                    data: 'expediteur', name: 'expediteur', type: 'string', responsivePriority: 1,
                    render: rendu(
                        function (nom, l) {
                            return '<span class="vdt-titre">' + e(nom) + '</span><span class="vdt-sous-texte">' + e(l.email) + '</span>';
                        },
                        function (nom, l) { return nom + ' <' + l.email + '>'; }
                    ),
                },
                {
                    data: 'extrait', name: 'extrait', orderable: false, responsivePriority: 4,
                    render: rendu(function (v) { return '<span class="vdt-sous-texte">' + e(v) + '</span>'; }, function (v) { return v; }),
                },
                {
                    data: 'statut', name: 'statut', responsivePriority: 3,
                    render: rendu(function (v, l) { return badge(l.statutBadge, l.statutLibelle); }, function (v, l) { return l.statutLibelle; }),
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

                        return '<button type="button" class="btn-icone" data-vdt-action="ouvrir" data-vdt-uuid="' + e(l.uuid) + '"'
                            + ' title="Lire ce message" aria-label="Lire ce message">'
                            + '<span class="material-symbols-rounded" aria-hidden="true">mark_email_read</span></button>';
                    },
                },
            ],
        }).catch(function (erreur) {
            if (window.console) { window.console.error(erreur); }
        });

        /*
         | Le panneau de lecture est rendu par Livewire AU-DESSUS de la table.
         | Ouvert depuis le bas de la liste, il apparaîtrait hors de vue : on
         | l'amène à l'écran, et le focus avec lui, une fois le rendu terminé.
         */
        var attendPanneau = false;

        racine.addEventListener('click', function (ev) {
            var cible = ev.target instanceof Element ? ev.target.closest('[data-vdt-action="ouvrir"]') : null;
            if (cible) { attendPanneau = true; }
        });

        var brancher = function () {
            window.Livewire.hook('morphed', function () {
                var panneau = document.getElementById('message-ouvert');

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
