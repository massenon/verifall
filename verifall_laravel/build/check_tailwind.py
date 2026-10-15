# -*- coding: utf-8 -*-
"""Quelles classes Tailwind existent reellement dans le build statique ?

Recherche par sous-chaine et non par regex : dans le CSS compile, « : » et
« [ » sont precedes d'un ANTISLASH litteral, que le moteur de regex consomme
comme un echappement. Le test precedent concluait donc a tort a l'absence.
"""
import io
import sys

BS = chr(92)
css = io.open('public/assets/css/tailwind.css', encoding='utf-8').read()


def selecteur(cls: str) -> str:
    esc = cls
    for ch in ':[]./':
        esc = esc.replace(ch, BS + ch)
    return '.' + esc


def present(cls: str) -> bool:
    return selecteur(cls) in css


CLASSES = sys.argv[1:] or [
    'space-y-10', 'space-y-3', 'grid-cols-2', 'lg:grid-cols-4', 'sm:grid-cols-2',
    'lg:grid-cols-3', 'lg:grid-cols-2', 'max-w-md', 'gap-4', 'tracking-[.14em]',
    'tracking-[.12em]', 'inset-x-4', 'z-[90]', 'lg:hidden', 'lg:block',
    'lg:p-8', 'p-6', 'mt-6', 'text-2xl', '-translate-y-1/2', 'top-1/2',
]

manquantes = [c for c in CLASSES if not present(c)]
for c in CLASSES:
    print('  %-20s %s' % (c, 'ok' if present(c) else 'ABSENTE'))

print()
print('Regles CSS :', css.count('{'), '| manquantes :', len(manquantes))
if manquantes:
    print('->', ', '.join(manquantes))
