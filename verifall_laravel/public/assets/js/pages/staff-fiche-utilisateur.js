/*
 | Fiche d'un compte — les onglets, et la description de chaque table.
 |
 | Tout le comportement commun (recherche, filtres, états, pont Livewire) vit
 | dans verifall-datatables.js. Ce fichier ne dit que ce qui est propre à la
 | fiche : quels onglets existent, leurs colonnes, leur rendu, et QUAND monter
 | chaque table — à la première ouverture de son onglet, pas avant.
 |
 | Chaque valeur reçue du serveur est un TEXTE et passe par `e()` avant
 | d'entrer dans le HTML. Le serveur n'envoie jamais de HTML.
 */
(function (window, document) {
    'use strict';

    function demarrer() {
        var zone = document.querySelector('[data-vfu-onglets]');
        var V = window.VerifallDataTables;

        if (!zone || !V || !window.DataTable) {
            return;
        }

        var e = V.echapper;

        /* ----------------------------------------------------------- rendus */

        function rendu(affichage, texte) {
            return function (donnee, type, ligne) {
                if (type === 'display') { return affichage(donnee, ligne); }
                if (type === 'export') { return texte ? texte(donnee, ligne) : donnee; }

                return donnee;
            };
        }

        function sousTexte(texte) {
            return texte !== null && texte !== undefined && texte !== ''
                ? '<span class="vdt-sous-texte">' + e(texte) + '</span>'
                : '';
        }

        function tiret(v) {
            return v === null || v === undefined || v === '' ? '<span class="vdt-sous-texte">—</span>' : e(v);
        }

        var TONS = { ok: 'p-bon', attente: 'p-alerte', alerte: 'p-critique', neutre: 'p-neutre' };

        /** Un badge dont la couleur suit `ton`, fourni par le serveur. */
        function badge(v, l) {
            return v ? '<span class="pastille ' + (TONS[l.ton] || 'p-info') + '">' + e(v) + '</span>' : tiret(v);
        }

        function titre(cle, sousCle) {
            return function (v, l) {
                return '<span class="vdt-titre">' + e(v) + '</span>' + (sousCle ? sousTexte(l[sousCle]) : '');
            };
        }

        function suffixe(texte) {
            return function (v) { return v === null || v === undefined ? tiret(v) : e(v) + ' ' + e(texte); };
        }

        function mouvement(v, l) {
            return '<span class="' + (l.ton === 'ok' ? 'vfu-ton-ok' : (l.ton === 'alerte' ? 'vfu-ton-alerte' : '')) + '">' + e(v) + '</span>';
        }

        /** Colonne : `data` et `name` = la clé publique (liste blanche serveur). */
        function col(cle, intitule, options) {
            var c = { data: cle, name: cle, title: intitule, defaultContent: '', render: rendu(tiret) };
            var cles = options ? Object.keys(options) : [];

            for (var i = 0; i < cles.length; i++) { c[cles[i]] = options[cles[i]]; }

            return c;
        }

        var fixe = { orderable: false };

        /* ---------------------------------------------------------- onglets */

        var ONGLETS = {
            verifications: {
                vide: "Ce compte n'a soumis aucune vérification.",
                ordre: [[5, 'desc']],
                colonnes: [
                    col('affirmation', 'Affirmation', { responsivePriority: 1, render: rendu(titre('affirmation', 'theme')) }),
                    col('type', 'Type', { responsivePriority: 6 }),
                    col('statut', 'Statut', { responsivePriority: 2, render: rendu(badge) }),
                    col('verdict', 'Verdict', { orderable: false, responsivePriority: 3 }),
                    col('cout', 'Coût (unités)', { responsivePriority: 7, render: rendu(function (v, l) { return e(v) + (l.rembourse ? sousTexte('Remboursé') : ''); }) }),
                    col('creeLe', 'Soumise le', { responsivePriority: 4 }),
                ],
            },
            cours: {
                vide: "Ce compte n'est inscrit à aucun cours.",
                ordre: [[4, 'desc']],
                colonnes: [
                    col('cours', 'Cours', { orderable: false, responsivePriority: 1, render: rendu(titre('cours')) }),
                    col('statut', 'Statut', { responsivePriority: 2, render: rendu(badge) }),
                    col('progression', 'Progression', { responsivePriority: 3, render: rendu(suffixe('%')) }),
                    col('unites', 'Unités payées', { responsivePriority: 7 }),
                    col('inscritLe', 'Inscrit le', { responsivePriority: 5 }),
                    col('derniereActivite', 'Dernière activité', { responsivePriority: 4 }),
                    col('termineLe', 'Terminé le', { responsivePriority: 6 }),
                ],
            },
            quiz: {
                vide: "Ce compte n'a passé aucun quiz.",
                ordre: [[4, 'desc']],
                colonnes: [
                    col('quiz', 'Quiz', { orderable: false, responsivePriority: 1, render: rendu(titre('quiz', 'cours')) }),
                    col('score', 'Score', { responsivePriority: 3, render: rendu(function (v, l) { return e(v) + (l.seuil !== null && l.seuil !== undefined ? sousTexte('Seuil : ' + l.seuil) : ''); }) }),
                    col('reussi', 'Résultat', { responsivePriority: 2, render: rendu(badge) }),
                    col('duree', 'Durée', { responsivePriority: 5 }),
                    col('passeLe', 'Passé le', { responsivePriority: 4 }),
                ],
            },
            devoirs: {
                vide: "Ce compte n'a remis aucun devoir.",
                ordre: [[4, 'desc']],
                colonnes: [
                    col('devoir', 'Devoir', { orderable: false, responsivePriority: 1, render: rendu(titre('devoir', 'cours')) }),
                    col('statut', 'Statut', { responsivePriority: 2, render: rendu(badge) }),
                    col('note', 'Note', { responsivePriority: 3 }),
                    col('correcteur', 'Correcteur', fixe),
                    col('remisLe', 'Remis le', { responsivePriority: 4 }),
                    col('corrigeLe', 'Corrigé le', { responsivePriority: 5 }),
                ],
            },
            examens: {
                vide: "Ce compte n'a composé aucun examen.",
                ordre: [[6, 'desc']],
                colonnes: [
                    col('examen', 'Examen', { orderable: false, responsivePriority: 1, render: rendu(titre('examen')) }),
                    col('tentative', 'Tentative', { responsivePriority: 6 }),
                    col('statut', 'Statut', { responsivePriority: 2, render: rendu(badge) }),
                    col('score', 'Score', { responsivePriority: 3, render: rendu(suffixe('%')) }),
                    col('reussi', 'Résultat', { responsivePriority: 4 }),
                    col('correcteur', 'Correcteur', { orderable: false, responsivePriority: 7 }),
                    col('soumisLe', 'Soumis le', { responsivePriority: 5 }),
                ],
            },
            certificats: {
                vide: "Aucun certificat n'a été délivré à ce compte.",
                ordre: [[3, 'desc']],
                colonnes: [
                    col('code', 'Code', { responsivePriority: 3 }),
                    col('cours', 'Cours', { responsivePriority: 1, render: rendu(titre('cours')) }),
                    col('statut', 'Statut', { orderable: false, responsivePriority: 2, render: rendu(badge) }),
                    col('delivreLe', 'Délivré le', { responsivePriority: 4 }),
                ],
            },
            unites: {
                vide: "Aucun mouvement d'unités sur ce compte.",
                ordre: [[0, 'desc']],
                colonnes: [
                    col('date', 'Date', { responsivePriority: 3 }),
                    col('mouvement', 'Mouvement', { responsivePriority: 1, render: rendu(mouvement) }),
                    col('solde', 'Solde après', { responsivePriority: 4 }),
                    col('motif', 'Motif', { orderable: false, responsivePriority: 2 }),
                    col('libelle', 'Libellé', { responsivePriority: 5 }),
                ],
            },
            paiements: {
                vide: "Ce compte n'a effectué aucun paiement.",
                ordre: [[0, 'desc']],
                colonnes: [
                    col('date', 'Date', { responsivePriority: 3 }),
                    col('operateur', 'Opérateur', { orderable: false, responsivePriority: 5 }),
                    col('pack', 'Pack', { responsivePriority: 1 }),
                    col('montant', 'Montant', { responsivePriority: 4, render: rendu(suffixe('FCFA')) }),
                    col('unites', 'Unités', { responsivePriority: 6 }),
                    col('statut', 'Statut', { responsivePriority: 2, render: rendu(badge) }),
                    col('regleLe', 'Réglé le', { orderable: false, responsivePriority: 7 }),
                ],
            },
            forum: {
                vide: "Ce compte n'a ouvert aucun sujet sur le forum.",
                ordre: [[3, 'desc']],
                colonnes: [
                    col('sujet', 'Sujet', { responsivePriority: 1, render: rendu(function (v, l) { return titre('sujet', 'categorie')(v, l) + (l.verrouille ? sousTexte('Verrouillé') : ''); }) }),
                    col('verdict', 'Verdict officiel', { orderable: false, responsivePriority: 3 }),
                    col('reponses', 'Réponses', { responsivePriority: 4 }),
                    col('creeLe', 'Ouvert le', { responsivePriority: 2 }),
                ],
            },
            journal: {
                vide: "Aucune trace d'audit ne concerne ce compte.",
                ordre: [[0, 'desc']],
                colonnes: [
                    col('date', 'Date', { responsivePriority: 2 }),
                    col('action', 'Action', { responsivePriority: 1, render: rendu(titre('action', 'module')) }),
                    col('module', 'Module', { responsivePriority: 6 }),
                    col('acteur', 'Acteur', { orderable: false, responsivePriority: 3 }),
                    col('cible', 'Cible', { orderable: false, responsivePriority: 4 }),
                    col('role', 'Ce compte est', { orderable: false, responsivePriority: 5 }),
                ],
            },
            'verifications-assignees': {
                vide: 'Aucune vérification ne lui est assignée.',
                ordre: [[3, 'desc']],
                colonnes: [
                    col('affirmation', 'Affirmation', { responsivePriority: 1, render: rendu(function (v, l) { return titre('affirmation')(v, l) + sousTexte('Soumise par ' + l.auteur); }) }),
                    col('statut', 'Statut', { responsivePriority: 2, render: rendu(badge) }),
                    col('verdict', 'Verdict', { orderable: false, responsivePriority: 4 }),
                    col('assigneeLe', 'Assignée le', { responsivePriority: 3 }),
                    col('creeLe', 'Soumise le', { responsivePriority: 5 }),
                ],
            },
            'cours-encadres': {
                vide: "Ce compte n'encadre aucun cours.",
                ordre: [[3, 'desc']],
                colonnes: [
                    col('titre', 'Cours', { responsivePriority: 1, render: rendu(titre('titre', 'niveau')) }),
                    col('publie', 'Publication', { responsivePriority: 2, render: rendu(badge) }),
                    col('inscrits', 'Inscrits', { responsivePriority: 3 }),
                    col('creeLe', 'Créé le', { responsivePriority: 4 }),
                ],
            },
            'corrections-devoirs': {
                vide: "Ce compte n'a corrigé aucun devoir.",
                ordre: [[4, 'desc']],
                colonnes: [
                    col('apprenant', 'Apprenant', { orderable: false, responsivePriority: 1 }),
                    col('devoir', 'Devoir', { orderable: false, responsivePriority: 3, render: rendu(titre('devoir', 'cours')) }),
                    col('statut', 'Statut', { responsivePriority: 2, render: rendu(badge) }),
                    col('note', 'Note', { responsivePriority: 4 }),
                    col('corrigeLe', 'Corrigé le', { responsivePriority: 5 }),
                ],
            },
            'corrections-examens': {
                vide: "Aucune copie d'examen ne lui est assignée.",
                ordre: [[4, 'desc']],
                colonnes: [
                    col('candidat', 'Candidat', { orderable: false, responsivePriority: 1 }),
                    col('examen', 'Examen', { orderable: false, responsivePriority: 3, render: rendu(titre('examen')) }),
                    col('statut', 'Statut', { responsivePriority: 2, render: rendu(badge) }),
                    col('score', 'Score', { responsivePriority: 5, render: rendu(suffixe('%')) }),
                    col('assigneeLe', 'Assignée le', { responsivePriority: 4 }),
                    col('corrigeLe', 'Corrigée le', { responsivePriority: 6 }),
                ],
            },
            'faits-rediges': {
                vide: "Ce compte n'a rédigé aucun fait vérifié.",
                ordre: [[4, 'desc']],
                colonnes: [
                    col('titre', 'Fait', { responsivePriority: 1, render: rendu(titre('titre')) }),
                    col('verdict', 'Verdict', { responsivePriority: 3 }),
                    col('publie', 'Publication', { responsivePriority: 2, render: rendu(badge) }),
                    col('reutilisations', 'Réutilisations', { responsivePriority: 5 }),
                    col('creeLe', 'Créé le', { responsivePriority: 4 }),
                ],
            },
        };

        // Les points ont exactement la forme des unités.
        ONGLETS.points = { vide: "Aucun mouvement de points sur ce compte.", ordre: ONGLETS.unites.ordre, colonnes: ONGLETS.unites.colonnes };

        /* ---------------------------------------------------- navigation */

        var boutons = Array.prototype.slice.call(zone.querySelectorAll('[data-vfu-onglet]'));
        var tables = {};

        function panneau(cle) {
            return document.getElementById('vfu-panneau-' + cle);
        }

        function monterSiBesoin(cle) {
            var racine = panneau(cle);
            var config = ONGLETS[cle];

            if (!racine || !config) { return; }

            if (tables[cle]) {
                // Déjà montée : le panneau était masqué, les largeurs sont à refaire.
                tables[cle].then(function (t) { t.columns.adjust(); if (t.responsive) { t.responsive.recalc(); } });

                return;
            }

            tables[cle] = V.monter(racine, {
                ordre: config.ordre,
                langue: {
                    emptyTable: config.vide,
                    zeroRecords: 'Aucune ligne ne correspond à vos critères.',
                    processing: 'Chargement…',
                },
                colonnes: config.colonnes,
            });

            tables[cle].catch(function (erreur) {
                if (window.console) { window.console.error(erreur); }
            });
        }

        function activer(cle, focus, ecrireAdresse) {
            boutons.forEach(function (b) {
                var actif = b.getAttribute('data-vfu-onglet') === cle;
                b.classList.toggle('is-active', actif);
                b.setAttribute('aria-selected', actif ? 'true' : 'false');
                b.setAttribute('tabindex', actif ? '0' : '-1');

                var p = panneau(b.getAttribute('data-vfu-onglet'));
                if (p) { p.hidden = !actif; }

                if (actif) {
                    if (focus) { b.focus(); }
                    if (b.scrollIntoView) { b.scrollIntoView({ block: 'nearest', inline: 'nearest' }); }
                }
            });

            // L'onglet ouvert survit à un rechargement et se partage par lien.
            if (ecrireAdresse) {
                var url = new URL(window.location.href);
                url.searchParams.set('onglet', cle);
                window.history.replaceState(window.history.state, '', url.toString());
            }

            monterSiBesoin(cle);
        }

        zone.addEventListener('click', function (ev) {
            var bouton = ev.target instanceof Element ? ev.target.closest('[data-vfu-onglet]') : null;

            if (bouton) { activer(bouton.getAttribute('data-vfu-onglet'), false, true); }
        });

        // Clavier : flèches, Début, Fin — le motif ARIA des onglets.
        zone.addEventListener('keydown', function (ev) {
            var courant = ev.target instanceof Element ? ev.target.closest('[data-vfu-onglet]') : null;

            if (!courant) { return; }

            var i = boutons.indexOf(courant);
            var cible = null;

            if (ev.key === 'ArrowRight') { cible = boutons[(i + 1) % boutons.length]; }
            if (ev.key === 'ArrowLeft') { cible = boutons[(i - 1 + boutons.length) % boutons.length]; }
            if (ev.key === 'Home') { cible = boutons[0]; }
            if (ev.key === 'End') { cible = boutons[boutons.length - 1]; }

            if (cible) {
                ev.preventDefault();
                activer(cible.getAttribute('data-vfu-onglet'), true, true);
            }
        });

        if (boutons.length === 0) { return; }

        var demande = new URLSearchParams(window.location.search).get('onglet');
        var existe = boutons.some(function (b) { return b.getAttribute('data-vfu-onglet') === demande; });

        activer(existe ? demande : boutons[0].getAttribute('data-vfu-onglet'), false, false);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', demarrer);
    } else {
        demarrer();
    }
})(window, document);
