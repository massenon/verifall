# -*- coding: utf-8 -*-
"""Compare une page rendue par Laravel à la page du prototype dont elle sort.

Trois lectures, du plus structurant au plus fin :

1. **Squelette** — la suite des balises porteuses de mise en page, avec leurs
   classes, dans l'ordre du document. C'est ce qui répond à « même structure,
   mêmes conteneurs, mêmes espacements » : une classe Tailwind absente est un
   espacement perdu.
2. **Repères** — titres, libellés de boutons, entrées de menu, onglets, en-têtes
   de tableau. C'est ce qui répond à « même copywriting, mêmes items de menu ».
3. **Ressources** — feuilles de style, scripts, images. Une référence qui ne
   résout pas est un fond qui ne s'affiche pas.

Ce que le comparateur NE compte PAS comme écart : le contenu qui vient
légitimement de la base (une carte d'article, une ligne de tableau), les
attributs Livewire/Alpine, les jetons CSRF. On compare des gabarits, pas des
données.
"""
import difflib
import io
import json
import os
import re
import sys
from collections import Counter

from bs4 import BeautifulSoup, Comment

# Balises dont la structure interne ne nous intéresse pas.
OPAQUES = {'script', 'style', 'svg', 'path', 'noscript', 'template'}
# Balises qui portent la mise en page.
PORTEUSES = {'header', 'footer', 'nav', 'main', 'section', 'aside', 'article',
             'div', 'form', 'table', 'thead', 'tbody', 'tr', 'th', 'td', 'ul',
             'ol', 'li', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'button', 'a',
             'input', 'select', 'textarea', 'label', 'img', 'span', 'p'}
# Attributs ajoutés par le moteur, jamais présents dans le prototype.
ATTRS_MOTEUR = re.compile(r'^(wire:|x-|@|data-wire|_)')


def soupe(html):
    s = BeautifulSoup(html, 'html.parser')
    for c in s.find_all(string=lambda t: isinstance(t, Comment)):
        c.extract()
    return s


def classes(el):
    c = el.get('class') or []
    if isinstance(c, str):
        c = c.split()
    # Les classes d'état posées par le JS ne comptent pas.
    return [x for x in c if not x.startswith(('is-', 'js-'))]


def squelette(racine):
    """Suite « balise.classes » des éléments porteurs, en profondeur d'abord."""
    sortie = []

    def descendre(el, profondeur):
        for enfant in el.children:
            if getattr(enfant, 'name', None) is None:
                continue
            if enfant.name in OPAQUES:
                continue
            if enfant.name in PORTEUSES:
                cl = classes(enfant)
                sortie.append(f"{'  ' * min(profondeur, 12)}{enfant.name}.{'.'.join(cl)}")
            descendre(enfant, profondeur + 1)

    descendre(racine, 0)
    return sortie


def reperes(racine):
    """Le texte qui doit se retrouver à l'identique : titres, boutons, menus."""
    out = []
    for el in racine.find_all(['h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'button',
                               'label', 'th', 'legend', 'summary']):
        t = re.sub(r'\s+', ' ', el.get_text(' ', strip=True))
        if t:
            out.append(f'{el.name}: {t}')
    for el in racine.find_all('a'):
        t = re.sub(r'\s+', ' ', el.get_text(' ', strip=True))
        if t:
            out.append(f'a: {t}')
    for el in racine.find_all(['input', 'textarea', 'select']):
        p = el.get('placeholder') or el.get('aria-label') or ''
        if p:
            out.append(f'{el.name}[{p}]')
    return out


def ressources(racine, html):
    out = set()
    for el in racine.find_all(['link', 'script', 'img', 'source']):
        u = el.get('href') or el.get('src') or ''
        if u and not u.startswith(('data:', 'javascript:', '#')):
            out.add(u)
    for m in re.finditer(r'url\((["\']?)([^)"\']+)\1\)', html):
        out.add(m.group(2))
    return out


def rapport(nom, proto_html, rendu_html):
    sp, sr = soupe(proto_html), soupe(rendu_html)
    bp = sp.body or sp
    br = sr.body or sr

    kp, kr = squelette(bp), squelette(br)
    # Comparaison à plat : un conteneur supplémentaire décale la profondeur de
    # tout ce qu'il contient, ce qui écraserait le score sans rien signaler de
    # réel. La profondeur est examinée à part, sur les écarts eux-mêmes.
    pp, pr = [l.strip() for l in kp], [l.strip() for l in kr]
    ratio = difflib.SequenceMatcher(None, pp, pr).ratio()

    """
    Deuxième mesure : les MOTIFS.

    Le prototype construit plusieurs de ses listes en JavaScript ; sa page
    statique contient donc un conteneur vide là où le rendu contient dix
    cartes. Comparer les suites brutes pénalise alors la page qui affiche
    vraiment quelque chose — l'inverse de ce qu'on veut mesurer.

    On compare donc aussi l'ENSEMBLE des signatures distinctes : « les mêmes
    sortes de conteneurs, avec les mêmes classes, sont-elles présentes ? »,
    indépendamment du nombre de répétitions.
    """
    mp, mr = sorted(set(pp)), sorted(set(pr))
    motifs = difflib.SequenceMatcher(None, mp, mr).ratio()

    ecarts = []
    for tag, i1, i2, j1, j2 in difflib.SequenceMatcher(None, pp, pr).get_opcodes():
        if tag == 'equal':
            continue
        ecarts.append({'type': tag,
                       'prototype': pp[i1:i2][:40],
                       'rendu': pr[j1:j2][:40]})

    rp, rr = reperes(bp), reperes(br)
    cp, cr = Counter(rp), Counter(rr)
    manquants = sorted((cp - cr).elements())
    ajoutes = sorted((cr - cp).elements())

    return {
        'page': nom,
        'similitude_structure': round(ratio, 3),
        'similitude_motifs': round(motifs, 3),
        'motifs_absents': sorted(set(pp) - set(pr)),
        'motifs_ajoutes': sorted(set(pr) - set(pp)),
        'noeuds_prototype': len(kp),
        'noeuds_rendu': len(kr),
        'ecarts_structure': ecarts,
        'reperes_manquants': manquants,
        'reperes_ajoutes': ajoutes,
        'ressources_prototype': sorted(ressources(bp, proto_html)),
        'ressources_rendu': sorted(ressources(br, rendu_html)),
        'squelette_prototype': kp,
        'squelette_rendu': kr,
    }


if __name__ == '__main__':
    proto, rendu, sortie = sys.argv[1], sys.argv[2], sys.argv[3]
    r = rapport(os.path.basename(proto),
                io.open(proto, encoding='utf-8', errors='replace').read(),
                io.open(rendu, encoding='utf-8', errors='replace').read())
    io.open(sortie, 'w', encoding='utf-8').write(json.dumps(r, ensure_ascii=False, indent=1))
    print(f"{r['page']}: structure {r['similitude_structure']:.0%} "
          f"({r['noeuds_prototype']} vs {r['noeuds_rendu']} nœuds), "
          f"{len(r['reperes_manquants'])} repères manquants")
