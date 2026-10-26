/*
 | Vérif'All — couche commune des tables DataTables du back-office.
 |
 | Pilote du 14 septembre 2026 (écran Utilisateurs). Contrat complet :
 | docs/DATATABLES_CONTRAT.md. Ce fichier ne connaît AUCUN écran : il monte
 | une table serveur à partir d'une racine `[data-vdt]` et d'une description
 | de colonnes fournie par le fichier de page.
 |
 | Ce qu'il prend en charge, pour que les ~58 écrans suivants ne le réécrivent
 | pas chacun à sa façon :
 |
 |   · la langue (fichier vendorisé + surcharges de l'écran) ;
 |   · les filtres rapides, la recherche débouncée, la période DateTime,
 |     l'état dans l'adresse, la synchronisation des propriétés #[Url]
 |     Livewire existantes ;
 |   · les quatre états : chargement, vide, aucun résultat, erreur ;
 |   · l'export du JEU ENTIER (et non de la page affichée), sous permission ;
 |   · le pont Livewire : actions de ligne → méthodes existantes, événement
 |     → rechargement.
 |
 | Pas de module, pas de build : le déploiement est un cPanel mutualisé.
 */
(function (window, document) {
    'use strict';

    var UUID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;
    var DATE = /^\d{4}-\d{2}-\d{2}$/;

    /* ------------------------------------------------------------ outils */

    /** Échappe un texte pour l'insérer dans du HTML. Toute cellule y passe. */
    function echapper(valeur) {
        if (valeur === null || valeur === undefined) {
            return '';
        }

        return String(valeur)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function debounce(fn, delai) {
        var minuteur = null;

        return function () {
            var args = arguments;
            var contexte = this;
            window.clearTimeout(minuteur);
            minuteur = window.setTimeout(function () { fn.apply(contexte, args); }, delai);
        };
    }

    var scriptsCharges = {};

    /** Charge des scripts à la demande, dans l'ordre, une seule fois chacun. */
    function chargerScripts(urls) {
        return urls.reduce(function (chaine, url) {
            return chaine.then(function () {
                if (!scriptsCharges[url]) {
                    scriptsCharges[url] = new Promise(function (resoudre, rejeter) {
                        var balise = document.createElement('script');
                        balise.src = url;
                        balise.onload = resoudre;
                        balise.onerror = function () { rejeter(new Error('Script introuvable : ' + url)); };
                        document.head.appendChild(balise);
                    });
                }

                return scriptsCharges[url];
            });
        }, Promise.resolve());
    }

    /** Langue vendorisée, puis les surcharges de l'écran par-dessus. */
    function chargerLangue(url, surcharges) {
        var fusion = function (base) {
            return window.jQuery.extend(true, {}, base || {}, surcharges || {});
        };

        if (!url) {
            return Promise.resolve(fusion({}));
        }

        return window.fetch(url, { credentials: 'same-origin' })
            .then(function (reponse) { return reponse.ok ? reponse.json() : {}; })
            .catch(function () { return {}; })
            .then(fusion);
    }

    /** Le composant Livewire qui contient la racine, ou null. */
    function composantLivewire(racine) {
        var hote = racine.closest('[wire\\:id]');

        if (!hote || !window.Livewire) {
            return null;
        }

        return window.Livewire.find(hote.getAttribute('wire:id')) || null;
    }

    /* ------------------------------------------------------------ montage */

    /**
     * Monte une table DataTables côté serveur.
     *
     * @param {HTMLElement} racine  l'élément `[data-vdt]` (en wire:ignore)
     * @param {object} ecran
     *   colonnes            définitions DataTables (data, name, render…)
     *   ordre               tri initial, ex. [[8, 'desc']]
     *   searchBuilder       indices des colonnes proposées aux filtres avancés
     *   actions             noms des méthodes Livewire que les boutons de ligne
     *                       ont le droit d'appeler (liste blanche)
     *   titreExport         titre des fichiers exportés
     *   langue              surcharges de langue propres à l'écran
     * @returns {Promise<object>} l'API DataTables
     */
    function monter(racine, ecran) {
        var DataTable = window.DataTable;
        var tableEl = racine.querySelector('[data-vdt-table]');

        if (!DataTable || !tableEl || racine.getAttribute('data-vdt-pret') === '1') {
            return Promise.reject(new Error('DataTables absent, table absente, ou déjà montée.'));
        }

        racine.setAttribute('data-vdt-pret', '1');

        // Les erreurs sont affichées par l'écran (bandeau « Réessayer »), jamais
        // par une alerte native qui bloquerait la page.
        DataTable.ext.errMode = 'none';

        var etat = { export: false };
        var api = null;
        var champRecherche = racine.querySelector('[data-vdt-recherche]');
        var filtres = Array.prototype.slice.call(racine.querySelectorAll('[data-vdt-filtre]'));
        var bandeau = racine.querySelector('[data-vdt-erreur]');
        var messageBandeau = racine.querySelector('[data-vdt-erreur-message]');
        var vendor = (racine.getAttribute('data-vdt-vendor') || '').replace(/\/$/, '');
        var peutExporter = racine.getAttribute('data-vdt-export') === '1';

        /* — l'état initial : l'adresse d'abord, puis la valeur rendue par Blade */
        var params = new URLSearchParams(window.location.search);

        filtres.concat(champRecherche ? [champRecherche] : []).forEach(function (champ) {
            var cle = champ.getAttribute('data-vdt-url');

            if (!cle || !params.has(cle)) {
                return;
            }

            var valeur = params.get(cle) || '';

            if (champ.tagName === 'SELECT') {
                // Une valeur d'adresse absente des options est ignorée, pas
                // injectée : le serveur la refuserait de toute façon.
                var existe = Array.prototype.some.call(champ.options, function (o) { return o.value === valeur; });
                champ.value = existe ? valeur : '';
            } else if (!champ.hasAttribute('data-vdt-date') || DATE.test(valeur)) {
                champ.value = valeur;
            }
        });

        function lireFiltres() {
            var valeurs = {};

            filtres.forEach(function (champ) {
                var valeur = (champ.value || '').trim();

                if (valeur !== '' && (!champ.hasAttribute('data-vdt-date') || DATE.test(valeur))) {
                    valeurs[champ.getAttribute('data-vdt-filtre')] = valeur;
                }
            });

            return valeurs;
        }

        /* — l'adresse et les propriétés #[Url] Livewire suivent les filtres */
        function synchroniserAdresse() {
            var url = new URL(window.location.href);
            var composant = composantLivewire(racine);

            filtres.concat(champRecherche ? [champRecherche] : []).forEach(function (champ) {
                var cle = champ.getAttribute('data-vdt-url');
                var propriete = champ.getAttribute('data-vdt-livewire');
                var valeur = (champ.value || '').trim();

                if (cle) {
                    if (valeur === '') { url.searchParams.delete(cle); } else { url.searchParams.set(cle, valeur); }
                }

                /*
                 | Sans cela, le prochain aller-retour Livewire (ouvrir un tiroir)
                 | réécrirait l'adresse avec ses propres valeurs, restées vides, et
                 | effacerait `?q=`. `false` : aucune requête n'est envoyée.
                 */
                if (propriete && composant && typeof composant.$set === 'function') {
                    composant.$set(propriete, valeur, false);
                }
            });

            url.searchParams.delete('page');
            window.history.replaceState(window.history.state, '', url.toString());
        }

        function afficherErreur(message) {
            if (!bandeau) { return; }
            if (messageBandeau) { messageBandeau.textContent = message; }
            bandeau.hidden = false;
        }

        function masquerErreur() {
            if (bandeau) { bandeau.hidden = true; }
        }

        function messageDErreur(xhr) {
            var statut = xhr ? xhr.status : 0;

            if (statut === 401 || statut === 419) {
                return 'Votre session a expiré. Rechargez la page pour vous reconnecter.';
            }
            if (statut === 403) {
                return "Accès refusé : vous n'avez pas le droit de consulter ou d'exporter ces données.";
            }
            if (statut === 0) {
                return 'Impossible de joindre le serveur. Vérifiez la connexion, puis réessayez.';
            }

            return 'Impossible de charger les données. Réessayez dans un instant.';
        }

        /* — l'export du jeu ENTIER ------------------------------------------ */

        /*
         | En traitement côté serveur, les boutons d'export de DataTables ne
         | voient que la page affichée. On redemande donc le jeu entier
         | (`export=1`, que le serveur n'accorde qu'avec la permission d'export
         | et plafonne), on exporte AVANT que DataTables ne le dessine — le
         | dessin est annulé —, puis on recharge la page où l'on était.
         */
        function exporterLeJeuEntier(dt, exporter, terminer) {
            etat.export = true;

            dt.one('xhr', function (e, reglages, json, xhr) {
                etat.export = false;

                if (!json || !Array.isArray(json.data)) {
                    afficherErreur(messageDErreur(xhr));
                    terminer();
                    // La page d'avant, pas le jeu partiel.
                    window.setTimeout(function () { dt.ajax.reload(null, false); }, 0);

                    return;
                }

                // Enregistré ICI et non avant : DataTables déclenche aussi
                // `preDraw` avant d'envoyer la requête, et l'annuler là
                // annulerait la requête elle-même.
                dt.one('preDraw', function () {
                    try {
                        exporter();
                    } finally {
                        window.setTimeout(function () { dt.ajax.reload(null, false); }, 0);
                    }

                    return false;
                });

                if (json.exportTronque) {
                    afficherErreur('Export limité aux ' + json.data.length + ' premières lignes. Affinez les filtres pour obtenir le reste.');
                }
            });

            dt.ajax.reload(null, false);
        }

        function boutonExport(nom, texte, scripts, options) {
            var base = DataTable.ext.buttons[nom];

            return window.jQuery.extend(true, {
                extend: nom,
                text: texte,
                // Les bibliothèques lourdes (JSZip, pdfmake) arrivent au clic :
                // sans ce `available`, Buttons masquerait le bouton faute de les
                // trouver au chargement.
                available: function () { return true; },
                title: ecran.titreExport || document.title,
                filename: (ecran.fichierExport || 'export') + '-' + new Date().toISOString().slice(0, 10),
                exportOptions: {
                    orthogonal: 'export',
                    // Les colonnes que la personne a laissées visibles, moins
                    // les actions : on exporte ce qu'on voit, pas des boutons.
                    columns: function (index, donnees, cellule) {
                        if (cellule && cellule.classList.contains('vdt-sans-export')) { return false; }

                        return api === null || api.column(index).visible();
                    },
                },
                action: function (e, dt, noeud, config, fin) {
                    var bouton = this;
                    var terminer = typeof fin === 'function' ? fin : function () {};

                    masquerErreur();

                    chargerScripts(scripts.map(function (s) { return vendor + '/' + s; }))
                        .then(function () {
                            exporterLeJeuEntier(dt, function () {
                                base.action.call(bouton, e, dt, noeud, config, terminer);
                            }, terminer);
                        })
                        .catch(function () {
                            afficherErreur("La bibliothèque d'export n'a pas pu être chargée.");
                            terminer();
                        });
                },
            }, options || {});
        }

        var boutons = [];

        if (ecran.searchBuilder && ecran.searchBuilder.length && DataTable.SearchBuilder) {
            // Niveau 3 de la directive : derrière un bouton, jamais ouvert d'office.
            boutons.push({ extend: 'searchBuilder', config: { columns: ecran.searchBuilder, depthLimit: 2 } });
        }

        boutons.push({ extend: 'colvis', text: 'Colonnes', columns: ':not(.vdt-sans-export)' });

        if (peutExporter) {
            boutons.push({
                extend: 'collection',
                text: 'Exporter',
                buttons: [
                    boutonExport('copyHtml5', 'Copier', []),
                    boutonExport('csvHtml5', 'CSV', [], { bom: true, fieldSeparator: ';', extension: '.csv' }),
                    boutonExport('excelHtml5', 'Excel', ['jszip-3.10.1.min.js']),
                    boutonExport('pdfHtml5', 'PDF', ['pdfmake-0.2.12.min.js', 'vfs_fonts-0.2.12.min.js'], { orientation: 'landscape', pageSize: 'A4' }),
                    boutonExport('print', 'Imprimer', []),
                ],
            });
        }

        return chargerLangue(racine.getAttribute('data-vdt-langue'), window.jQuery.extend(true, {
            processing: 'Chargement…',
            loadingRecords: 'Chargement…',
            emptyTable: 'Aucun élément à afficher.',
            zeroRecords: 'Aucun résultat ne correspond à vos critères.',
            searchBuilder: { button: { 0: 'Filtres avancés', _: 'Filtres avancés (%d)' } },
        }, ecran.langue || {})).then(function (langue) {
            // `display` = rayures, survol, bordures : le style DataTables par
            // défaut. Posé ici et non dans la vue, où le contrôle des classes
            // Tailwind le refuserait (elle n'existe pas dans le build figé).
            tableEl.classList.add('display');

            var table = new DataTable(tableEl, {
                serverSide: true,
                processing: true,
                responsive: true,
                autoWidth: false,
                pageLength: 25,
                lengthMenu: [10, 25, 50, 100],
                order: ecran.ordre || [],
                search: { search: champRecherche ? champRecherche.value.trim() : '' },
                layout: {
                    topStart: 'pageLength',
                    topEnd: { buttons: boutons },
                    bottomStart: 'info',
                    bottomEnd: 'paging',
                },
                language: langue,
                columns: ecran.colonnes,
                ajax: {
                    url: racine.getAttribute('data-vdt-source'),
                    type: 'GET',
                    headers: { Accept: 'application/json' },
                    data: function (d) {
                        d.filtres = lireFiltres();

                        // Le protocole envoie aussi la recherche et le motif de
                        // CHAQUE colonne : inutiles ici, ils rallongent l'adresse.
                        d.columns = d.columns.map(function (c) { return { data: c.data, name: c.name }; });

                        if (etat.export) {
                            d.start = 0;
                            d.length = -1;
                            d.export = 1;
                        }
                    },
                },
            });

            api = table;

            /* — états */
            /*
             | L'erreur se lit sur `xhr`, pas sur `error` : avec `errMode = 'none'`,
             | DataTables 2.3 ne déclenche pas `error` pour un échec HTTP (vérifié
             | au navigateur sur un 404), mais passe toujours par `xhr` avec un
             | JSON nul et la requête, d'où l'on tire le statut.
             */
            table.on('xhr', function (e, reglages, json, xhr) {
                if (json && Array.isArray(json.data)) {
                    masquerErreur();
                } else {
                    afficherErreur(messageDErreur(xhr));
                }
            });

            table.on('error', function (e, reglages) {
                afficherErreur(messageDErreur(reglages && reglages.jqXHR));
            });

            table.on('draw', function () {
                var info = table.page.info();
                var vide = tableEl.querySelector('td.dt-empty');

                // « Aucun résultat » n'est pas « aucune donnée » : seul le
                // premier propose de réinitialiser, le second n'a rien à défaire.
                if (vide && info.recordsTotal > 0 && info.recordsDisplay === 0 && !vide.querySelector('button')) {
                    var bouton = document.createElement('button');
                    bouton.type = 'button';
                    bouton.className = 'btn-second';
                    bouton.textContent = 'Réinitialiser les filtres';
                    bouton.setAttribute('data-vdt-reinitialiser', '');
                    vide.appendChild(document.createElement('br'));
                    vide.appendChild(bouton);
                }
            });

            /* — recherche et filtres */
            var relancer = function () {
                synchroniserAdresse();
                table.draw();
            };

            if (champRecherche) {
                champRecherche.addEventListener('input', debounce(function () {
                    synchroniserAdresse();
                    table.search(champRecherche.value.trim()).draw();
                }, 350));
            }

            filtres.forEach(function (champ) {
                if (champ.hasAttribute('data-vdt-date')) {
                    if (DataTable.DateTime) {
                        new DataTable.DateTime(champ, {
                            format: 'YYYY-MM-DD',
                            i18n: langue.datetime,
                            onChange: relancer,
                        });
                    }

                    // Saisie au clavier : on ne relance que sur une date complète
                    // ou un champ vidé, pas à chaque chiffre.
                    champ.addEventListener('input', debounce(function () {
                        var v = champ.value.trim();
                        if (v === '' || DATE.test(v)) { relancer(); }
                    }, 350));

                    return;
                }

                champ.addEventListener('change', relancer);
            });

            var reessayer = racine.querySelector('[data-vdt-reessayer]');
            if (reessayer) {
                reessayer.addEventListener('click', function () {
                    masquerErreur();
                    table.ajax.reload(null, false);
                });
            }

            /* — délégation : réinitialiser, actions de ligne */
            var actions = ecran.actions || [];

            racine.addEventListener('click', function (e) {
                var cible = e.target instanceof Element ? e.target : null;

                if (!cible) { return; }

                if (cible.closest('[data-vdt-reinitialiser]')) {
                    filtres.forEach(function (champ) { champ.value = ''; });
                    if (champRecherche) { champRecherche.value = ''; }
                    if (table.searchBuilder) { table.searchBuilder.rebuild(); }
                    synchroniserAdresse();
                    table.search('').draw();

                    return;
                }

                var bouton = cible.closest('[data-vdt-action]');

                if (!bouton || !racine.contains(bouton)) { return; }

                var methode = bouton.getAttribute('data-vdt-action');
                var uuid = bouton.getAttribute('data-vdt-uuid') || '';

                // Liste blanche côté client ; le composant ré-autorise côté
                // serveur. Aucune des deux ne remplace l'autre.
                if (actions.indexOf(methode) === -1 || !UUID.test(uuid)) { return; }

                var composant = composantLivewire(racine);

                if (composant) {
                    composant.$call(methode, uuid);
                }
            });

            /* — Livewire → table : recharger après une écriture */
            var evenement = racine.getAttribute('data-vdt-evenement');
            var brancher = function () {
                if (evenement && window.Livewire) {
                    window.Livewire.on(evenement, function () { table.ajax.reload(null, false); });
                }
            };

            if (window.Livewire) { brancher(); } else { document.addEventListener('livewire:init', brancher); }

            return table;
        });
    }

    window.VerifallDataTables = {
        echapper: echapper,
        debounce: debounce,
        chargerScripts: chargerScripts,
        monter: monter,
    };
})(window, document);
