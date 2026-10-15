/* guided-tour.js — visite guidée d'onboarding (UX_TRANSVERSE).
 *
 * Spotlight maison : aucune bibliothèque. Le principe est d'assombrir la page
 * et de découper une fenêtre autour de l'élément visé, puis d'ancrer une bulle
 * à côté.
 *
 * 6 étapes citoyen, 5 étapes staff, relançable depuis les réglages.
 * Navigable au clavier, Échap quitte, progression « 2/6 » affichée.
 */
(function () {
    'use strict';

    var ETAPES = {
        citizen: [
            { cible: '[data-tour="verifier"]', titre: 'Vérifier un contenu',
              texte: "Collez un texte, un lien ou un média : nos experts l'analysent et vous rendent un verdict argumenté." },
            { cible: '[data-tour="unites"]', titre: 'Vos unités',
              texte: 'Chaque vérification consomme une unité. Votre solde est toujours visible ici.' },
            { cible: '[data-tour="academie"]', titre: "L'Académie",
              texte: 'Des cours pour développer vos réflexes de fact-checker, du débutant à l\'enquête en sources ouvertes.' },
            { cible: '[data-tour="forum"]', titre: 'Le forum',
              texte: 'Signalez une rumeur à la communauté et suivez les verdicts officiels.' },
            // Ces deux entrées vivent dans le menu utilisateur : la visite le
            // déplie elle-même, sans quoi elle désignerait un élément masqué.
            { cible: '[data-tour="statistiques"]', revele: '[data-tour="menu-utilisateur"]',
              titre: 'Vos statistiques',
              texte: 'Votre progression, vos badges et votre série de jours actifs.' },
            { cible: '[data-tour="profil"]', revele: '[data-tour="menu-utilisateur"]',
              titre: 'Votre profil',
              texte: 'Réglages, notifications et données personnelles. Vous pouvez relancer cette visite à tout moment.' }
        ],
        staff: [
            { cible: '[data-tour="sidebar"]', titre: 'Navigation par domaine',
              texte: 'Les modules sont regroupés par domaine. Le groupe actif reste ouvert.' },
            { cible: '[data-tour="creer"]', titre: 'Créer',
              texte: 'Le raccourci de création, disponible depuis n\'importe quelle page.' },
            { cible: '[data-tour="notifications"]', titre: 'Notifications',
              texte: 'Les demandes qui vous attendent, lues et non lues séparément.' },
            // L'ancre appartient au tableau de bord, pas au shell : l'étape
            // reste filtrée jusqu'à ce que le bloc « file prioritaire » existe.
            { cible: '[data-tour="a-traiter"]', titre: 'À traiter',
              texte: 'La file prioritaire : signalements urgents et copies à corriger.' },
            { cible: '[data-tour="audit"]', titre: "Journal d'audit",
              texte: 'Toute action sensible y est tracée, y compris les vôtres.' }
        ]
    };

    var etat = { etapes: [], index: 0, elements: null, revele: null };

    /* Rendu, et pas seulement présent : un élément `hidden sm:inline-flex` est
     * dans le DOM mais mesure 0×0, et le spotlight se découperait dans le vide. */
    function estRendu(el) {
        return !!el && el.getClientRects().length > 0;
    }

    /* Une étape n'est jouée que si sa cible est atteignable ICI.
     *
     * Le shell ne porte pas toutes les ancres — « À traiter » appartient au
     * tableau de bord staff, livré plus tard — et la barre latérale disparaît
     * sous `lg`. Filtrer vaut mieux qu'afficher une bulle centrée qui ne montre
     * rien : l'étape se réactivera d'elle-même quand la cible existera. */
    function etapeJouable(etape) {
        var cible = document.querySelector(etape.cible);
        if (!cible) { return false; }

        // Cible à révéler : c'est la bascule qui doit être visible, pas elle.
        return estRendu(etape.revele ? document.querySelector(etape.revele) : cible);
    }

    /* Referme ce que l'étape précédente avait déplié. */
    function refermer() {
        if (etat.revele && etat.revele.getAttribute('aria-expanded') === 'true') {
            etat.revele.click();
        }
        etat.revele = null;
    }

    function revelerPuis(etape, suite) {
        var bascule = etape.revele ? document.querySelector(etape.revele) : null;

        if (!bascule) { suite(); return; }

        /* Différé d'un tick : le clic sur « Suivant » qui nous amène ici remonte
         * encore jusqu'au document, où le gestionnaire global referme tous les
         * menus. Déplier dans le même tick reviendrait à ouvrir puis refermer. */
        window.setTimeout(function () {
            if (bascule.getAttribute('aria-expanded') !== 'true') { bascule.click(); }
            etat.revele = bascule;
            suite();
        }, 0);
    }

    function creerCalque() {
        var fond = document.createElement('div');
        fond.className = 'vf-tour-backdrop';
        fond.setAttribute('aria-hidden', 'true');

        var bulle = document.createElement('div');
        bulle.className = 'vf-tour-bubble';
        bulle.setAttribute('role', 'dialog');
        bulle.setAttribute('aria-modal', 'true');
        bulle.setAttribute('aria-labelledby', 'vf-tour-titre');

        document.body.appendChild(fond);
        document.body.appendChild(bulle);

        return { fond: fond, bulle: bulle };
    }

    function positionner(cible) {
        var el = document.querySelector(cible);
        var e = etat.elements;

        if (!estRendu(el)) {
            // Cible disparue depuis le filtrage — repli du navigateur, menu
            // refermé : la bulle se centre plutôt que de pointer dans le vide.
            e.fond.style.setProperty('--vf-tour-clip', 'none');
            e.bulle.style.top = '50%';
            e.bulle.style.left = '50%';
            e.bulle.style.transform = 'translate(-50%, -50%)';
            return;
        }

        /* prefers-reduced-motion ne coupe pas la visite — elle est informative,
         * pas décorative — mais retire l'animation du défilement. Le réglage est
         * lu ici, sur l'élément : remplacer Element.prototype.scrollIntoView
         * comme on le faisait le neutralisait pour TOUTE la page, bien après la
         * fin de la visite. */
        var anime = window.matchMedia('(prefers-reduced-motion: reduce)').matches
            ? 'auto' : 'smooth';

        el.scrollIntoView({ block: 'center', behavior: anime });

        var r = el.getBoundingClientRect();
        var marge = 8;

        // Découpe du spotlight autour de la cible.
        e.fond.style.setProperty(
            '--vf-tour-clip',
            'polygon(0 0, 100% 0, 100% 100%, 0 100%, 0 0, ' +
            (r.left - marge) + 'px ' + (r.top - marge) + 'px, ' +
            (r.left - marge) + 'px ' + (r.bottom + marge) + 'px, ' +
            (r.right + marge) + 'px ' + (r.bottom + marge) + 'px, ' +
            (r.right + marge) + 'px ' + (r.top - marge) + 'px, ' +
            (r.left - marge) + 'px ' + (r.top - marge) + 'px)'
        );

        // Sous la cible si la place le permet, au-dessus sinon.
        var dessous = r.bottom + 16;
        var place = window.innerHeight - dessous > 190;

        e.bulle.style.transform = 'none';
        e.bulle.style.top = (place ? dessous : Math.max(16, r.top - 200)) + 'px';
        e.bulle.style.left = Math.max(16, Math.min(r.left, window.innerWidth - 340)) + 'px';
    }

    function rendre() {
        var etape = etat.etapes[etat.index];
        var e = etat.elements;
        var dernier = etat.index === etat.etapes.length - 1;

        refermer();
        e.bulle.textContent = '';

        var progression = document.createElement('p');
        progression.className = 'vf-tour-step';
        progression.textContent = (etat.index + 1) + '/' + etat.etapes.length;

        var titre = document.createElement('h3');
        titre.id = 'vf-tour-titre';
        titre.textContent = etape.titre;

        var texte = document.createElement('p');
        texte.className = 'vf-tour-text';
        texte.textContent = etape.texte;

        var actions = document.createElement('div');
        actions.className = 'vf-tour-actions';

        var quitter = document.createElement('button');
        quitter.type = 'button';
        quitter.className = 'vf-tour-skip';
        quitter.textContent = 'Passer';
        quitter.addEventListener('click', terminer);

        var suivant = document.createElement('button');
        suivant.type = 'button';
        suivant.className = 'vf-tour-next';
        suivant.textContent = dernier ? 'Terminer' : 'Suivant';
        suivant.addEventListener('click', function () {
            if (dernier) { terminer(); return; }
            etat.index++;
            rendre();
        });

        if (etat.index > 0) {
            var precedent = document.createElement('button');
            precedent.type = 'button';
            precedent.className = 'vf-tour-prev';
            precedent.textContent = 'Précédent';
            precedent.addEventListener('click', function () { etat.index--; rendre(); });
            actions.appendChild(precedent);
        }

        actions.appendChild(quitter);
        actions.appendChild(suivant);
        e.bulle.append(progression, titre, texte, actions);

        revelerPuis(etape, function () {
            positionner(etape.cible);
            suivant.focus();
        });
    }

    function auClavier(ev) {
        if (ev.key === 'Escape') { terminer(); }
        else if (ev.key === 'ArrowRight' && etat.index < etat.etapes.length - 1) { etat.index++; rendre(); }
        else if (ev.key === 'ArrowLeft' && etat.index > 0) { etat.index--; rendre(); }
    }

    function terminer() {
        refermer();

        if (etat.elements) {
            etat.elements.fond.remove();
            etat.elements.bulle.remove();
            etat.elements = null;
        }
        document.removeEventListener('keydown', auClavier);
        cesserDeSuivre();

        /*
         | Marquage serveur : la visite ne doit pas se relancer à chaque page.
         |
         | Un POST direct plutôt qu'un événement Livewire : la visite peut se
         | dérouler sur n'importe quelle page, y compris une page sans
         | composant Livewire monté, où le dispatch serait perdu en silence.
         */
        var jeton = document.querySelector('meta[name="csrf-token"]');

        if (jeton) {
            fetch('/visite-guidee/terminee', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': jeton.getAttribute('content'),
                    'Accept': 'application/json'
                },
                credentials: 'same-origin'
            }).catch(function () {
                // Un échec réseau ne doit pas casser l'expérience : au pire
                // la visite se reproposera à la prochaine connexion.
            });
        }
    }

    function repositionner() {
        if (etat.elements && etat.etapes[etat.index]) {
            positionner(etat.etapes[etat.index].cible);
        }
    }

    /* La découpe est faite de coordonnées d'écran : toute chose qui bouge la
     * cible périme le spotlight. Le défilement en fait partie — sans cela, le
     * trou reste où il était et éclaire le vide. */
    function suivreLaCible() {
        window.addEventListener('resize', repositionner);
        window.addEventListener('scroll', repositionner, { passive: true });

        /* Le chargement des polices reflue tout l'en-tête : mesurer avant leur
         * arrivée décalait le trou d'une centaine de pixels. */
        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(repositionner);
        }
    }

    function cesserDeSuivre() {
        window.removeEventListener('resize', repositionner);
        window.removeEventListener('scroll', repositionner);
    }

    function demarrer(espace) {
        var etapes = (ETAPES[espace] || []).filter(etapeJouable);

        // Aucune ancre atteignable — page sans chrome, mobile étroit : mieux
        // vaut ne rien lancer qu'assombrir l'écran pour rien.
        if (!etapes.length) { return; }

        etat = { etapes: etapes, index: 0, elements: creerCalque(), revele: null };
        document.addEventListener('keydown', auClavier);
        suivreLaCible();
        rendre();
    }

    window.VF = window.VF || {};
    window.VF.tour = { demarrer: demarrer, terminer: terminer };

    /* Démarrage automatique au premier accès, piloté par le serveur.
     *
     * DEUX déclencheurs, et ce n'est pas de la ceinture-bretelles : la connexion
     * redirige avec `navigate: true`, c'est-à-dire un échange de <body> par
     * Livewire — sans rechargement, donc SANS DOMContentLoaded. La visite ne se
     * lançait alors jamais au moment précis où elle existe : le premier login.
     * `livewire:navigated` couvre ce cas, et se déclenche aussi au chargement
     * initial ; DOMContentLoaded reste pour les pages sans Livewire. */
    function demarrageAutomatique() {
        if (etat.elements) {
            // Toujours affichée sur CETTE page : rien à refaire.
            if (document.body.contains(etat.elements.fond)) { return; }

            /* Sinon la visite a été emportée par un échange de <body> : ses
             * noeuds sont détachés mais ses écouteurs, eux, vivent sur window et
             * survivent. Les laisser en place les accumulerait à chaque page. */
            document.removeEventListener('keydown', auClavier);
            cesserDeSuivre();
            etat = { etapes: [], index: 0, elements: null, revele: null };
        }

        var racine = document.querySelector('[data-tour-auto]');
        if (racine) { demarrer(racine.getAttribute('data-tour-auto')); }
    }

    document.addEventListener('DOMContentLoaded', demarrageAutomatique);
    document.addEventListener('livewire:navigated', demarrageAutomatique);
})();
