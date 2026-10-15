# -*- coding: utf-8 -*-
"""Rend leurs accents aux libellés d'interface de l'application Flutter.

Le socle a écrit ses chaînes sans accents — « Verifier », « Deconnexion »,
« Communaute » — et les cinq parcours s'y sont alignés plutôt que de mélanger
deux styles à l'écran. Le simulateur, lui, les porte toutes : treize
« Vérifier », dix « Communauté », sept « Réglages ». C'est un écart de
transposition, pas une convention.

Il est visible par n'importe qui : sur un produit béninois francophone dont le
métier est la rigueur, « Recompenses » ressemble à un bug.

## Pourquoi une liste écrite à la main plutôt qu'une règle

J'ai essayé de dériver les corrections des maquettes, en rapprochant chaque mot
du code de sa forme accentuée. La méthode trouve les bons mots — et quelques
pièges : « affiche » deviendrait « affiché », « copie » deviendrait « copié »,
« base » deviendrait « basé ». Ces mots existent en français dans les deux
formes, et seule la phrase tranche.

La liste ci-dessous ne contient donc QUE des mots dont la forme sans accent
n'est pas un mot français autonome. Le reste est signalé en fin d'exécution,
à traiter à la lecture — ce qui est le seul traitement honnête.

## Ce qu'une première version cassait

Une chaîne Dart n'est pas que de la prose : elle contient des interpolations.
`'${texte.detail}'` porte un IDENTIFIANT, pas un mot. La première version l'a
accentué — `texte.détail` — et le dépôt a cessé de compiler dans vingt-et-un
fichiers. On masque donc `${...}` et `$nom` avant de substituer, et on les
remet ensuite. Les fichiers engendrés (`*.g.dart`) sont écartés : les corriger
serait perdu au prochain `build_runner`.
"""
import io
import os
import re
import sys

DEPOT = r'C:\Projects\verifall\verifall_app_flutter'
# Les tests citent les mêmes libellés — `find.text('Verifier')`. Les corriger
# d'un côté seulement, c'est fabriquer soixante échecs.
RACINES = [os.path.join(DEPOT, d) for d in sys.argv[1:] if not d.startswith('--')] \
    or [os.path.join(DEPOT, 'lib'), os.path.join(DEPOT, 'test')]

# Forme sans accent → forme accentuée. Aucune de ces formes courtes n'est un
# mot français autonome : la substitution est sûre quelle que soit la phrase.
MOTS = {
    # — vocabulaire du produit
    'Verif': 'Vérif', 'VERIF': 'VÉRIF',
    'Verifier': 'Vérifier', 'verifier': 'vérifier',
    'Verification': 'Vérification', 'verification': 'vérification',
    'Verifications': 'Vérifications', 'verifications': 'vérifications',
    'Verifiee': 'Vérifiée', 'verifiee': 'vérifiée',
    'Verifiees': 'Vérifiées', 'verifiees': 'vérifiées',
    'Academie': 'Académie', 'academie': 'académie', 'ACADEMIE': 'ACADÉMIE',
    'Communaute': 'Communauté', 'communaute': 'communauté',
    'COMMUNAUTE': 'COMMUNAUTÉ',
    'Deconnexion': 'Déconnexion', 'deconnexion': 'déconnexion',
    'Deconnecter': 'Déconnecter', 'deconnecter': 'déconnecter',
    'Reglages': 'Réglages', 'reglages': 'réglages',
    'Recompenses': 'Récompenses', 'recompenses': 'récompenses',
    'Recompense': 'Récompense', 'recompense': 'récompense',
    'Securite': 'Sécurité', 'securite': 'sécurité',
    'Unites': 'Unités', 'unites': 'unités',
    'Actualites': 'Actualités', 'actualites': 'actualités',
    'Lecon': 'Leçon', 'Lecons': 'Leçons', 'lecon': 'leçon', 'lecons': 'leçons',
    'Evaluations': 'Évaluations', 'Evaluation': 'Évaluation',
    'Opportunites': 'Opportunités', 'opportunites': 'opportunités',

    # — mots courants sans homographe
    'Reessayer': 'Réessayer', 'reessayer': 'réessayer',
    'serie': 'série', 'Serie': 'Série', 'series': 'séries', 'Series': 'Séries',
    'etat': 'état', 'Etat': 'État', 'ETAT': 'ÉTAT',
    'etape': 'étape', 'Etape': 'Étape', 'etapes': 'étapes', 'Etapes': 'Étapes',
    'categorie': 'catégorie', 'Categorie': 'Catégorie',
    'categories': 'catégories', 'Categories': 'Catégories',
    'activite': 'activité', 'Activite': 'Activité',
    'activites': 'activités', 'Activites': 'Activités',
    'video': 'vidéo', 'Video': 'Vidéo', 'videos': 'vidéos', 'Videos': 'Vidéos',
    'duree': 'durée', 'Duree': 'Durée', 'durees': 'durées',
    'resultat': 'résultat', 'Resultat': 'Résultat',
    'resultats': 'résultats', 'Resultats': 'Résultats',
    'repertoire': 'répertoire', 'Repertoire': 'Répertoire',
    'operateur': 'opérateur', 'Operateur': 'Opérateur',
    'operateurs': 'opérateurs', 'Operateurs': 'Opérateurs',
    'echec': 'échec', 'Echec': 'Échec', 'echecs': 'échecs', 'Echecs': 'Échecs',
    'reel': 'réel', 'Reel': 'Réel', 'reelle': 'réelle', 'reels': 'réels',
    'apres': 'après', 'Apres': 'Après',
    'etre': 'être', 'Etre': 'Être',
    'plutot': 'plutôt', 'Plutot': 'Plutôt',
    'deja': 'déjà', 'Deja': 'Déjà',
    'tres': 'très', 'Tres': 'Très',
    'Decrivez': 'Décrivez', 'decrivez': 'décrivez',
    'Detail': 'Détail', 'detail': 'détail',
    'Details': 'Détails', 'details': 'détails',
    'Detaillez': 'Détaillez', 'detaillez': 'détaillez',
    'caractere': 'caractère', 'caracteres': 'caractères',
    'Caractere': 'Caractère', 'Caracteres': 'Caractères',
    'numero': 'numéro', 'Numero': 'Numéro',
    'Precedent': 'Précédent', 'precedent': 'précédent',
    'Selectionner': 'Sélectionner', 'selectionner': 'sélectionner',
    'Telecharger': 'Télécharger', 'telecharger': 'télécharger',
    'Telechargement': 'Téléchargement', 'telechargement': 'téléchargement',
    'Repondre': 'Répondre', 'repondre': 'répondre',
    'Reponse': 'Réponse', 'reponse': 'réponse',
    'Reponses': 'Réponses', 'reponses': 'réponses',
    'Cloture': 'Clôture', 'cloture': 'clôture',
    'Cloturee': 'Clôturée', 'cloturee': 'clôturée',
    'CLOTUREE': 'CLÔTURÉE', 'Cloturees': 'Clôturées',
    'Reseau': 'Réseau', 'reseau': 'réseau',
    'Echeance': 'Échéance', 'echeance': 'échéance',
    'Elements': 'Éléments', 'Element': 'Élément',
    'Experience': 'Expérience', 'experience': 'expérience',
    'Problemes': 'Problèmes', 'Probleme': 'Problème',
    'Regles': 'Règles', 'regles': 'règles',
    'Terminee': 'Terminée', 'terminee': 'terminée',
    'Terminees': 'Terminées', 'terminees': 'terminées',
    'apparait': 'apparaît', 'Apparait': 'Apparaît',
    'reussi': 'réussi', 'Reussi': 'Réussi',
    'reussie': 'réussie', 'reussite': 'réussite', 'Reussite': 'Réussite',
}

# Ces mots existent en français SANS accent, avec un autre sens. Une machine ne
# peut pas trancher : on les compte, on les signale, on n'y touche pas.
AMBIGUS = {
    'affiche', 'copie', 'porte', 'base', 'recherche', 'pousse', 'exige',
    'oublie', 'revoque', 'debloque', 'verifie', 'termine', 'cree', 'publie',
    'expire', 'archive', 'enregistre', 'reste', 'note', 'cote', 'entree',
}

MOTIF_CHAINE = re.compile(r"'([^'\\\n]*)'|\"([^\"\\\n]*)\"")
# `${expression}` et `$nom` sont du code logé dans une chaîne : intouchables.
MOTIF_INTERPOLATION = re.compile(r'\$\{[^{}]*\}|\$[A-Za-z_][A-Za-z0-9_]*')
MOTIFS = {m: re.compile(r'\b' + m + r'\b') for m in MOTS}
MOTIFS_AMBIGUS = {m: re.compile(r'\b' + m + r'\b', re.I) for m in AMBIGUS}

essai = '--dry-run' in sys.argv
touches = remplacements = 0
restes = {}


def technique(texte: str) -> bool:
    """Une chaîne technique ne se traduit pas.

    La règle décisive tient en une phrase : **un libellé montré à quelqu'un
    contient un espace ou commence par une majuscule.** Tout le reste — un seul
    mot, tout en minuscules — est un jeton : un nom de route (`'unites'`), un
    segment d'URL (`'opportunites'`), une valeur d'énumération envoyée au
    serveur (`'vrai'`, `'en_cours'`), une clé de cache (`'forum:threads:'`).

    Ce garde-fou n'est pas une précaution théorique. Sans lui, cette passe a
    réellement transformé `name: 'unites'` en `name: 'unités'` et le lien
    profond `['hub', 'opportunites', slug]` en `'opportunités'` : la navigation
    casse, et **`flutter analyze` ne voit rien** — ce sont des chaînes.

    Le prix : un libellé d'un seul mot tout en minuscules (« echec » seul) ne
    sera pas corrigé. C'est le bon sens de l'échange — on rate une correction
    plutôt que d'en risquer une fausse.
    """
    if '_' in texte or texte.startswith('http'):
        return True
    # ATTENTION A L'ORDRE : ce test se fait sur le texte DEJA MASQUE. Sinon les
    # majuscules logees dans l'interpolation le mettent en defaut —
    # `'${VfRoutes.hub}/opportunites/:slug'` n'est pas « tout en minuscules »
    # a cause de `VfRoutes`, et le chemin de route se fait accentuer. C'est
    # arrive, et seul un test de lien profond l'a vu.
    if not any(c.isspace() for c in texte) and texte == texte.lower():
        return True
    # Une interpolation coupée en deux par le découpage naïf des chaînes :
    # `'forum:threads:${categorie ?? ''}'` se lit comme la chaîne
    # `forum:threads:${categorie ?? ` suivie d'autre chose. L'accolade n'est
    # pas fermée, le masque ne s'applique pas, et `categorie` — un identifiant —
    # se ferait accentuer. On s'abstient.
    if texte.count('${') != len(re.findall(r'\$\{[^{}]*\}', texte)):
        return True

    # Un `toString()` de débogage : « EtatInscription(<masquée>) ».
    return bool(re.match(r'^[A-Z][A-Za-z]*\(', texte))


for racine in RACINES:
  for base, _, fichiers in os.walk(racine):
      for nom in sorted(fichiers):
          if not nom.endswith('.dart'):
              continue
          # Engendré par build_runner : toute correction serait écrasée.
          if nom.endswith('.g.dart') or nom.endswith('.freezed.dart'):
              continue

          chemin = os.path.join(base, nom)
          source = io.open(chemin, encoding='utf-8').read()
          compteur = [0]

          def corriger(m):
              texte = m.group(1) if m.group(1) is not None else m.group(2)
              guillemet = "'" if m.group(1) is not None else '"'

              # On met les interpolations de cote AVANT tout jugement : elles
              # portent du code, qui ne doit ni etre corrige ni peser dans la
              # decision.
              gardees = []

              def garder(i):
                  gardees.append(i.group(0))
                  return '[[%d]]' % (len(gardees) - 1)

              masque = MOTIF_INTERPOLATION.sub(garder, texte)

              if technique(masque):
                  return m.group(0)

              for mot, motif in MOTIFS_AMBIGUS.items():
                  if motif.search(texte):
                      restes.setdefault(mot, []).append(
                          os.path.relpath(chemin, racine))

              corrige = masque
              for mot, accentue in MOTS.items():
                  corrige = MOTIFS[mot].sub(accentue, corrige)
              for i, gardee in enumerate(gardees):
                  corrige = corrige.replace('[[%d]]' % i, gardee)

              if corrige != texte:
                  compteur[0] += 1

              return guillemet + corrige + guillemet

          nouveau = MOTIF_CHAINE.sub(corriger, source)

          if compteur[0] > 0:
              touches += 1
              remplacements += compteur[0]
              if not essai:
                  io.open(chemin, 'w', encoding='utf-8', newline='\n').write(nouveau)

print(f"{touches} fichiers · {remplacements} chaînes"
      + (' (essai, rien écrit)' if essai else ' corrigées'))

if restes:
    print(f"\n{len(restes)} mots AMBIGUS laissés en l'état — seule la phrase")
    print("peut trancher, une machine ne le peut pas :")
    for mot in sorted(restes, key=lambda m: -len(restes[m])):
        print(f"  {len(restes[mot]):3d}  {mot}")
