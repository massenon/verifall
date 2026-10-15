/* ------------------------------------------------------------------------
   Émoticônes — sélecteur autonome.

   ── POURQUOI PAS UNE BIBLIOTHÈQUE ────────────────────────────────────────

   Les sélecteurs d'émoticônes du marché pèsent de 200 Ko à plus d'un
   mégaoctet, parce qu'ils embarquent le jeu Unicode complet, ses noms dans
   plusieurs langues, et souvent une feuille d'images. Sur une connexion 3G au
   Bénin, c'est un temps d'attente réel pour un accessoire.

   S'y ajoutent deux contraintes du dépôt : aucune chaîne de compilation n'est
   active (Vite est dormant, `node_modules` est vide), et rien ne doit être
   chargé depuis un CDN. Une bibliothèque tierce demanderait donc de ranimer
   l'une ou d'ouvrir l'autre, pour un bouton.

   Ce fichier pèse quelques kilooctets et couvre ce qu'on emploie réellement
   dans une discussion. Le champ accepte de toute façon n'importe quelle
   émoticône collée ou saisie au clavier du téléphone : ce sélecteur est un
   raccourci, pas une porte.

   ── CE QU'IL GARANTIT ────────────────────────────────────────────────────

   1. L'INSERTION SE FAIT AU CURSEUR, jamais à la fin. Quelqu'un qui revient
      corriger le début de sa phrase ne doit pas voir son émoticône atterrir
      trois lignes plus bas.

   2. LIVEWIRE EST PRÉVENU. Écrire dans la valeur d'un champ lié par
      `wire:model` sans émettre d'événement `input` fait que le serveur ne voit
      jamais le changement : le texte s'affiche, et disparaît au premier
      rafraîchissement. C'est le piège classique de ce genre de composant.

   3. ÉCHAP FERME, ET LE FOCUS REVIENT dans le champ. Un panneau qu'on ne peut
      pas quitter au clavier est un piège.
   ------------------------------------------------------------------------ */
(function () {
  'use strict';

  /* Un jeu court, ordonné par usage réel dans une discussion : réagir,
     approuver, douter, signaler. Les drapeaux et les métiers en sont absents
     à dessein — ils gonflent la liste sans servir ici. */
  var GROUPES = [
    {
      nom: 'Réactions',
      liste: ['👍', '👎', '👏', '🙏', '🙌', '💪', '❤️', '🔥', '✨', '🎉', '😊', '😂',
              '😉', '😍', '🤔', '😐', '😕', '😢', '😡', '😱', '🤯', '😴']
    },
    {
      nom: 'Vérification',
      liste: ['✅', '❌', '⚠️', '❓', '❗', '🔍', '📌', '📎', '🔗', '📰', '📷', '🎥',
              '🎙️', '📢', '🚨', '🧾', '📊', '🗳️', '⚖️', '🛡️']
    },
    {
      nom: 'Échange',
      liste: ['💬', '🗨️', '💡', '📝', '✏️', '👀', '🤝', '🫱', '📱', '💻', '🌍', '🇧🇯',
              '⏰', '📅', '➡️', '⬅️', '🔁', '⭐']
    }
  ];

  function $(selecteur, racine) {
    return (racine || document).querySelector(selecteur);
  }

  function $$(selecteur, racine) {
    return Array.prototype.slice.call((racine || document).querySelectorAll(selecteur));
  }

  /* --- insertion ------------------------------------------------------- */

  /**
   * Insère au curseur et prévient Livewire.
   *
   * `setRangeText` place le texte à la sélection courante et déplace le
   * curseur : c'est le comportement qu'on attend d'un clavier. Sur les rares
   * navigateurs qui ne le connaissent pas, on retombe sur une concaténation.
   */
  function inserer(champ, caractere) {
    if (typeof champ.setRangeText === 'function') {
      var debut = champ.selectionStart;
      champ.setRangeText(caractere, debut, champ.selectionEnd, 'end');
    } else {
      champ.value += caractere;
    }

    /* SANS CETTE LIGNE, LIVEWIRE NE VOIT RIEN. Le texte apparaît à l'écran,
       le serveur garde l'ancienne valeur, et tout disparaît au premier
       rafraîchissement du composant. */
    champ.dispatchEvent(new Event('input', { bubbles: true }));
    champ.focus();
  }

  /* --- panneau --------------------------------------------------------- */

  function construirePanneau(champ) {
    var panneau = document.createElement('div');
    panneau.className = 'vf-emoji-panneau';
    panneau.hidden = true;
    panneau.setAttribute('role', 'dialog');
    panneau.setAttribute('aria-label', 'Choisir une émoticône');

    GROUPES.forEach(function (groupe) {
      var titre = document.createElement('p');
      titre.className = 'vf-emoji-groupe';
      titre.textContent = groupe.nom;
      panneau.appendChild(titre);

      var grille = document.createElement('div');
      grille.className = 'vf-emoji-grille';

      groupe.liste.forEach(function (caractere) {
        var bouton = document.createElement('button');
        bouton.type = 'button';
        bouton.className = 'vf-emoji';
        bouton.textContent = caractere;
        /* Le nom lu par un lecteur d'écran. Sans lui, la grille est une suite
           de boutons sans intitulé. */
        bouton.setAttribute('aria-label', 'Insérer ' + caractere);
        bouton.addEventListener('click', function () {
          inserer(champ, caractere);
        });
        grille.appendChild(bouton);
      });

      panneau.appendChild(grille);
    });

    return panneau;
  }

  function equiper(champ) {
    if (champ.dataset.vfEmojiPret === '1') return;
    champ.dataset.vfEmojiPret = '1';

    var boite = document.createElement('div');
    boite.className = 'vf-emoji-boite';

    var declencheur = document.createElement('button');
    declencheur.type = 'button';
    declencheur.className = 'vf-emoji-declencheur';
    declencheur.setAttribute('aria-expanded', 'false');
    declencheur.setAttribute('aria-label', 'Ajouter une émoticône');
    declencheur.textContent = '🙂';

    var panneau = construirePanneau(champ);

    boite.appendChild(declencheur);
    boite.appendChild(panneau);

    /* Inséré APRÈS le champ, dans son parent : le composant ne suppose donc
       aucune structure particulière autour de lui, et se pose sur n'importe
       quel champ marqué. */
    if (champ.parentNode) {
      champ.parentNode.insertBefore(boite, champ.nextSibling);
    }

    function fermer() {
      panneau.hidden = true;
      declencheur.setAttribute('aria-expanded', 'false');
    }

    declencheur.addEventListener('click', function () {
      var ouvert = !panneau.hidden;
      panneau.hidden = ouvert;
      declencheur.setAttribute('aria-expanded', ouvert ? 'false' : 'true');
    });

    document.addEventListener('click', function (evenement) {
      if (!boite.contains(evenement.target)) fermer();
    });

    document.addEventListener('keydown', function (evenement) {
      if (evenement.key !== 'Escape' || panneau.hidden) return;
      fermer();
      // Le focus revient dans le champ : on quittait le panneau pour écrire.
      champ.focus();
    });
  }

  function equiperTout() {
    $$('[data-emoticones]').forEach(equiper);
  }

  document.addEventListener('DOMContentLoaded', equiperTout);

  /* Livewire remplace des morceaux du document : les champs réapparus doivent
     être ré-équipés, sinon le bouton disparaît après la première publication.
     Le drapeau `data-vf-emoji-pret` empêche d'en poser deux. */
  document.addEventListener('livewire:navigated', equiperTout);
  document.addEventListener('livewire:update', equiperTout);
})();
