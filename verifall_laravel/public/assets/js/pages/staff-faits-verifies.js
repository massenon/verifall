/*
 | Écran « Fact checks » — la description de la table, et rien d'autre.
 |
 | Migration DataTables du 14 septembre 2026, sur le contrat du pilote
 | (docs/DATATABLES_CONTRAT.md). Le comportement commun vit dans
 | verifall-datatables.js ; ce fichier ne dit que les colonnes, leur rendu et
 | les méthodes Livewire EXISTANTES que les boutons de ligne ont le droit
 | d'appeler.
 |
 | Chaque valeur reçue est un TEXTE et passe par `e()` avant d'entrer dans le
 | HTML d'une cellule. Le serveur n'envoie jamais de HTML.
 */
(function (window, document) {
    'use strict';

    function monter() {
        var racine = document.querySelector('[data-vdt="faits-verifies"]');
        var V = window.VerifallDataTables;

        if (!racine || !V || !window.DataTable) {
            return;
        }

        var e = V.echapper;

        /** Rendu à deux visages : HTML échappé à l'écran, texte brut ailleurs. */
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

        // Mêmes teintes que <x-badge-statut variant="staff"> pour les verdicts.
        var CLASSES_VERDICT = { 'true': 'p-bon', 'false': 'p-critique', misleading: 'p-alerte', unverifiable: '' };
        var STATUTS = { publie: 'Publié', brouillon: 'Brouillon' };

        function boutonAction(methode, uuid, icone, titre) {
            return '<button type="button" class="btn-icone" data-vdt-action="' + e(methode) + '" data-vdt-uuid="' + e(uuid) + '"'
                + ' title="' + e(titre) + '" aria-label="' + e(titre) + '">'
                + '<span class="material-symbols-rounded" aria-hidden="true">' + e(icone) + '</span></button>';
        }

        V.monter(racine, {
            titreExport: "Fact checks — Vérif'All",
            fichierExport: 'fact-checks-verifall',
            // Aucun tri initial : le serveur retombe sur l'ordre de l'ancienne
            // liste, les derniers créés d'abord (départage `id desc`).
            ordre: [],
            // DepotFaitsVerifies::COLONNES_SEARCHBUILDER, et elles seules.
            searchBuilder: [0, 3, 4, 6],
            actions: ['ouvrirApercu', 'ouvrirEdition', 'dupliquer', 'basculerLaPublication', 'confirmerSuppression'],
            langue: {
                emptyTable: 'La base ne contient encore aucun fact check.',
                zeroRecords: 'Aucun fact check ne correspond à vos critères.',
                processing: 'Chargement des fact checks…',
            },
            colonnes: [
                {
                    data: 'affirmation', name: 'affirmation', type: 'string', responsivePriority: 1,
                    render: rendu(
                        function (affirmation) { return '<span class="vdt-titre">' + e(affirmation) + '</span>'; },
                        function (affirmation) { return affirmation; }
                    ),
                },
                {
                    data: 'verdict', name: 'verdict', responsivePriority: 3,
                    render: rendu(
                        function (verdict, l) { return badge(CLASSES_VERDICT[verdict] || '', l.verdictLibelle); },
                        function (verdict, l) { return l.verdictLibelle; }
                    ),
                },
                {
                    data: 'sources', name: 'sources', type: 'num', orderable: false, responsivePriority: 6,
                    render: rendu(
                        function (n) { return e(n + ' source' + (n > 1 ? 's' : '')); },
                        function (n) { return n; }
                    ),
                },
                { data: 'reutilisations', name: 'reutilisations', type: 'num', responsivePriority: 7 },
                {
                    data: 'misAJour', name: 'misAJour', type: 'date', responsivePriority: 5,
                    render: rendu(function (v, l) { return e(l.misAJourTexte || '—'); }, function (v, l) { return l.misAJourTexte || ''; }),
                },
                {
                    data: 'statut', name: 'statut', responsivePriority: 4,
                    render: rendu(
                        function (statut) { return badge(statut === 'publie' ? 'p-bon' : 'p-neutre', STATUTS[statut] || statut); },
                        function (statut) { return STATUTS[statut] || statut; }
                    ),
                },
                {
                    // Masquée d'office : c'est la colonne que SearchBuilder
                    // interroge, et « Colonnes » l'affiche à la demande.
                    data: 'motsCles', name: 'motsCles', type: 'string', orderable: false, visible: false, responsivePriority: 9,
                    render: rendu(function (v) { return sousTexte(v || '—'); }, function (v) { return v; }),
                },
                {
                    data: null, name: 'actions', orderable: false, searchable: false, responsivePriority: 2,
                    className: 'vdt-sans-export vdt-actions',
                    render: function (donnee, type, l) {
                        if (type !== 'display') { return ''; }

                        var a = l.actions;
                        var html = '';

                        if (a.apercu) { html += boutonAction('ouvrirApercu', l.uuid, 'visibility', 'Voir le fact check'); }
                        if (a.modifier) { html += boutonAction('ouvrirEdition', l.uuid, 'edit', 'Modifier'); }
                        if (a.dupliquer) { html += boutonAction('dupliquer', l.uuid, 'content_copy', 'Dupliquer'); }
                        if (a.publier) { html += boutonAction('basculerLaPublication', l.uuid, 'public', 'Publier'); }
                        if (a.depublier) { html += boutonAction('basculerLaPublication', l.uuid, 'unpublished', 'Dépublier'); }
                        if (a.supprimer) { html += boutonAction('confirmerSuppression', l.uuid, 'delete', 'Supprimer'); }

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
