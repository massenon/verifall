/*
 | Écran « Utilisateurs » — la description de la table, et rien d'autre.
 |
 | Pilote DataTables du 14 septembre 2026. Tout le comportement commun
 | (filtres, états, export, pont Livewire) vit dans verifall-datatables.js ;
 | ce fichier ne dit que ce qui est propre à CET écran : ses colonnes, leur
 | rendu, et les méthodes Livewire que ses boutons ont le droit d'appeler.
 |
 | Chaque valeur reçue du serveur est un TEXTE, et passe par `e()` avant
 | d'entrer dans le HTML d'une cellule. Le serveur n'envoie jamais de HTML.
 */
(function (window, document) {
    'use strict';

    function monter() {
        var racine = document.querySelector('[data-vdt="utilisateurs"]');
        var V = window.VerifallDataTables;

        if (!racine || !V || !window.DataTable) {
            return;
        }

        var e = V.echapper;
        var UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
        var UUID_FICTIF = '00000000-0000-0000-0000-000000000000';
        var gabaritFiche = racine.getAttribute('data-vdt-fiche');

        /** Rendu à deux visages : HTML échappé à l'écran, texte brut à l'export. */
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

        var STATUTS = { actif: 'Actif', non_active: 'Non activé', suspendu: 'Suspendu' };
        var KYC = { verifiee: 'Vérifiée', en_attente: 'Dossier en attente', non_verifiee: 'Non vérifiée' };
        var DEUX_FACTEURS = { active: '2FA active', a_venir: 'Enrôlement à venir' };

        function boutonAction(methode, uuid, icone, titre) {
            return '<button type="button" class="st-icnbtn" data-vdt-action="' + e(methode) + '" data-vdt-uuid="' + e(uuid) + '"'
                + ' title="' + e(titre) + '" aria-label="' + e(titre) + '">'
                + '<span class="material-symbols-rounded" aria-hidden="true">' + e(icone) + '</span></button>';
        }

        V.monter(racine, {
            titreExport: "Utilisateurs — Vérif'All",
            fichierExport: 'utilisateurs-verifall',
            // Tri initial : les inscriptions les plus récentes d'abord, comme
            // la liste qu'elle remplace.
            ordre: [[8, 'desc']],
            // Filtres avancés : les colonnes que le serveur sait interroger
            // (DepotUtilisateurs::COLONNES_SEARCHBUILDER), et elles seules.
            searchBuilder: [0, 1, 6, 7, 8, 9],
            actions: ['ouvrirEdition', 'ouvrirLaSuspension', 'ouvrirLeRetablissement'],
            langue: {
                emptyTable: "Aucun compte n'existe encore.",
                zeroRecords: 'Aucun compte ne correspond à vos critères.',
                processing: 'Chargement des comptes…',
            },
            colonnes: [
                {
                    data: 'nom', name: 'nom', type: 'string', responsivePriority: 1,
                    render: rendu(
                        function (nom, l) {
                            // Le nom mène à la fiche du compte. L'adresse est un
                            // gabarit posé par le serveur ; l'uuid passe le motif.
                            var intitule = '<span class="vdt-titre">' + e(nom) + '</span>';

                            if (gabaritFiche && UUID.test(l.uuid)) {
                                intitule = '<a class="vdt-lien-fiche" href="' + e(gabaritFiche.replace(UUID_FICTIF, l.uuid)) + '">' + intitule + '</a>';
                            }

                            return intitule + sousTexte(l.profession);
                        },
                        function (nom) { return nom; }
                    ),
                },
                { data: 'email', name: 'email', type: 'string', responsivePriority: 6, render: rendu(e, function (v) { return v; }) },
                {
                    data: 'roles', name: 'roles', orderable: false, responsivePriority: 4,
                    render: rendu(
                        function (roles) {
                            return roles.length
                                ? roles.map(function (r) { return badge('st-badge-info', r); }).join(' ')
                                : sousTexte('Citoyen');
                        },
                        function (roles) { return roles.length ? roles.join(', ') : 'Citoyen'; }
                    ),
                },
                {
                    data: 'statut', name: 'statut', orderable: false, responsivePriority: 3,
                    render: rendu(
                        function (statut, l) {
                            var classe = statut === 'suspendu' ? 'st-badge-warn' : (statut === 'actif' ? 'st-badge-pub' : 'st-badge-draft');
                            var html = badge(classe, STATUTS[statut] || statut);

                            // Le motif et l'auteur sur la ligne : c'est là qu'on
                            // se pose la question, pas dans le journal d'audit.
                            if (l.suspension) {
                                html += sousTexte((l.suspension.depuis || '') + (l.suspension.par ? ' · par ' + l.suspension.par : ''));
                                html += sousTexte(l.suspension.motif);
                            }

                            return html;
                        },
                        function (statut, l) {
                            return (STATUTS[statut] || statut) + (l.suspension && l.suspension.motif ? ' — ' + l.suspension.motif : '');
                        }
                    ),
                },
                {
                    data: 'deuxFacteurs', name: 'deuxFacteurs', orderable: false, responsivePriority: 8,
                    render: rendu(
                        function (v) { return v ? badge(v === 'active' ? 'st-badge-pub' : 'st-badge-info', DEUX_FACTEURS[v]) : sousTexte('—'); },
                        function (v) { return v ? DEUX_FACTEURS[v] : ''; }
                    ),
                },
                {
                    data: 'kyc', name: 'kyc', orderable: false, responsivePriority: 7,
                    render: rendu(
                        function (v) { return v === 'non_verifiee' ? sousTexte(KYC[v]) : badge(v === 'verifiee' ? 'st-badge-pub' : 'st-badge-warn', KYC[v]); },
                        function (v) { return KYC[v] || v; }
                    ),
                },
                { data: 'unites', name: 'unites', type: 'num', responsivePriority: 9 },
                { data: 'points', name: 'points', type: 'num', responsivePriority: 10 },
                {
                    data: 'inscritLe', name: 'inscritLe', type: 'date', responsivePriority: 5,
                    render: rendu(function (v, l) { return e(l.inscritLeTexte || '—'); }, function (v, l) { return l.inscritLeTexte || ''; }),
                },
                {
                    data: 'derniereConnexion', name: 'derniereConnexion', type: 'date', responsivePriority: 11,
                    render: rendu(function (v, l) { return e(l.derniereConnexionTexte || 'Jamais'); }, function (v, l) { return l.derniereConnexionTexte || 'Jamais'; }),
                },
                {
                    data: null, name: 'actions', orderable: false, searchable: false, responsivePriority: 2,
                    className: 'vdt-sans-export vdt-actions',
                    render: function (donnee, type, l) {
                        if (type !== 'display') { return ''; }

                        var html = '';

                        if (l.actions.modifier) {
                            html += boutonAction('ouvrirEdition', l.uuid, 'edit', 'Modifier le compte et ses rôles');
                        }
                        if (l.actions.retablir) {
                            html += boutonAction('ouvrirLeRetablissement', l.uuid, 'lock_open', 'Rétablir ce compte');
                        }
                        if (l.actions.suspendre) {
                            html += boutonAction('ouvrirLaSuspension', l.uuid, 'block', 'Suspendre ce compte');
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
