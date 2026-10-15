/*
 | Écran « Enrôlement aux examens » — la description de la table, et un pont
 | de plus que les autres écrans.
 |
 | Migration DataTables du 14 septembre 2026 (contrat : docs/DATATABLES_CONTRAT.md).
 |
 | Le pont en plus : le sélecteur de SESSION filtre la table (couche commune)
 | ET commande les indicateurs et le bouton « Inscrire un candidat », que
 | Livewire rend hors de la zone ignorée. La couche commune pose la propriété
 | `sessionUuid` sans requête (`$set(…, false)`) ; on demande ici le
 | rafraîchissement qui fait suivre le reste de l'écran.
 */
(function (window, document) {
    'use strict';

    function monter() {
        var racine = document.querySelector('[data-vdt="enrolement-aux-examens"]');
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

        var BADGES_STATUT = { registered: 'st-badge-info', confirmed: 'st-badge-pub', deferral_requested: 'st-badge-warn', cancelled: 'st-badge-arch' };

        function boutonAction(methode, uuid, icone, titre) {
            return '<button type="button" class="st-icnbtn" data-vdt-action="' + e(methode) + '" data-vdt-uuid="' + e(uuid) + '"'
                + ' title="' + e(titre) + '" aria-label="' + e(titre) + '">'
                + '<span class="material-symbols-rounded" aria-hidden="true">' + e(icone) + '</span></button>';
        }

        V.monter(racine, {
            ordre: [[5, 'desc']],
            actions: ['convoquer', 'emarger', 'ouvrirLArbitrage', 'demanderLAnnulation'],
            langue: {
                emptyTable: "Personne n'est encore inscrit à une session d'examen.",
                zeroRecords: 'Aucune inscription ne correspond à vos critères.',
                processing: 'Chargement des inscriptions…',
            },
            colonnes: [
                {
                    data: 'candidat', name: 'candidat', type: 'string', responsivePriority: 1,
                    render: rendu(
                        function (v, l) { return '<span class="vdt-titre">' + e(v || 'Compte supprimé') + '</span>' + sousTexte(l.email); },
                        function (v, l) { return (v || 'Compte supprimé') + (l.email ? ' <' + l.email + '>' : ''); }
                    ),
                },
                {
                    data: 'session', name: 'session', type: 'date', responsivePriority: 4,
                    render: rendu(
                        function (v, l) { return e(l.epreuve || 'Épreuve') + sousTexte((l.sessionTexte || '') + (l.lieu ? ' · ' + l.lieu : '')); },
                        function (v, l) { return (l.epreuve || '') + ' — ' + (l.sessionTexte || ''); }
                    ),
                },
                {
                    data: 'convocation', name: 'convocation', type: 'date', responsivePriority: 5,
                    render: rendu(
                        function (v, l) { return v ? e(l.convocationTexte) + sousTexte(l.codeConvocation) : sousTexte('Non envoyée'); },
                        function (v, l) { return v ? l.convocationTexte : 'Non envoyée'; }
                    ),
                },
                {
                    data: 'presence', name: 'presence', type: 'date', responsivePriority: 6,
                    render: rendu(function (v) { return v ? e('Émargé') : sousTexte('—'); }, function (v) { return v ? 'Émargé' : ''; }),
                },
                {
                    data: 'statut', name: 'statut', responsivePriority: 3,
                    render: rendu(
                        function (v, l) { return badge(BADGES_STATUT[v] || 'st-badge-info', l.statutLibelle) + sousTexte(l.motifReport); },
                        function (v, l) { return l.statutLibelle; }
                    ),
                },
                {
                    data: 'inscritLe', name: 'inscritLe', type: 'date', responsivePriority: 7,
                    render: rendu(function (v, l) { return e(l.inscritLeTexte || '—'); }, function (v, l) { return l.inscritLeTexte || ''; }),
                },
                {
                    data: null, name: 'actions', orderable: false, searchable: false, responsivePriority: 2,
                    className: 'vdt-sans-export vdt-actions',
                    render: function (donnee, type, l) {
                        if (type !== 'display') { return ''; }

                        var html = '';

                        if (l.actions.convoquer) {
                            html += boutonAction('convoquer', l.uuid, 'mail', 'Envoyer la convocation');
                        }
                        if (l.actions.emarger) {
                            html += boutonAction('emarger', l.uuid, 'fact_check', 'Enregistrer la présence');
                        }
                        if (l.actions.arbitrer) {
                            html += boutonAction('ouvrirLArbitrage', l.uuid, 'event_repeat', 'Arbitrer le report');
                        }
                        if (l.actions.annuler) {
                            html += boutonAction('demanderLAnnulation', l.uuid, 'person_remove', "Annuler l'inscription");
                        }

                        return html || sousTexte('—');
                    },
                },
            ],
        }).then(function () {
            /*
             | Enregistrés APRÈS ceux de la couche commune (qui a fini de monter) :
             | au moment où ils s'exécutent, `sessionUuid` a déjà reçu la nouvelle
             | valeur par `$set(…, false)`. `$refresh()` l'envoie et fait rendre
             | indicateurs et bouton d'inscription pour cette session.
             */
            var selecteur = racine.querySelector('[data-vdt-filtre="session"]');
            var rafraichir = function () {
                var hote = racine.closest('[wire\\:id]');
                var composant = hote && window.Livewire ? window.Livewire.find(hote.getAttribute('wire:id')) : null;

                if (composant && typeof composant.$refresh === 'function') {
                    composant.$refresh();
                }
            };

            if (selecteur) {
                selecteur.addEventListener('change', rafraichir);
            }

            racine.addEventListener('click', function (evenement) {
                var cible = evenement.target instanceof Element ? evenement.target : null;

                if (cible && cible.closest('[data-vdt-reinitialiser]')) {
                    rafraichir();
                }
            });
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
