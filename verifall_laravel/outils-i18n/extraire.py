"""Extrait les chaines traduisibles d'une vue Blade.

── POURQUOI CET OUTIL EXISTE ────────────────────────────────────────────────

Le francais des vues est la copie du prototype, et la regle du chantier
interdit d'y toucher. Le retaper a la main dans un fichier de langue
introduirait des ecarts invisibles : une espace insecable perdue, une
apostrophe droite au lieu d'une courbe, un accent oublie. On l'extrait donc
mecaniquement.

── CE QU'IL PREND, ET CE QU'IL LAISSE ───────────────────────────────────────

Il prend le contenu ENTIER des elements porteurs de texte, balisage en ligne
compris. Couper un paragraphe sur un <strong> produirait des fragments
qu'aucune langue ne se traduit correctement : l'ordre des mots change d'une
langue a l'autre, et l'emphase ne tombe pas au meme endroit.

Il laisse les commentaires, les scripts et les SVG. Il laisse aussi tout
element qui contient deja une expression Blade : y toucher casserait la
logique de la page.

── L'ENVELOPPE DE STYLE EST RETIREE ─────────────────────────────────────────

`<span class="...">Jeunes</span>` donne « Jeunes », pas le span. Sans cela, le
fichier de langue recopierait les classes CSS, et une retouche de style
casserait la traduction sans que rien ne le signale.
"""

import io
import json
import re
import sys
import unicodedata

CHEMIN, SORTIE = sys.argv[1], sys.argv[2]

s = io.open(CHEMIN, encoding='utf-8').read()

zones = []
for motif in [r'\{\{--.*?--\}\}', r'<script.*?</script>', r'<svg.*?</svg>', r'<!--.*?-->']:
    for m in re.finditer(motif, s, re.S):
        zones.append((m.start(), m.end()))


def exclu(debut, fin):
    return any(a <= debut and fin <= b for a, b in zones)


BALISES = r'h1|h2|h3|h4|h5|h6|p|li|button|figcaption|summary|label|td|th'
motif = re.compile(r'<(' + BALISES + r')(\s[^>]*)?>(.*?)</\1>', re.S)


def cle(texte):
    t = unicodedata.normalize('NFKD', texte).encode('ascii', 'ignore').decode()
    t = re.sub(r'<[^>]+>', ' ', t)
    t = re.sub(r'[^a-zA-Z0-9]+', '_', t).strip('_').lower()
    return '_'.join([x for x in t.split('_') if x][:7])[:60] or 'texte'


vus, pris = {}, set()

for m in motif.finditer(s):
    if exclu(m.start(), m.end()):
        continue

    attributs = m.group(2) or ''

    # LA BALISE OUVRANTE NE DOIT CONTENIR NI DIRECTIVE NI EXPRESSION.
    #
    # `<button @if($x < $cours->cost_units) class="...">` piege l'expression
    # reguliere : le `>` de la fleche `->` est pris pour la fin de la balise,
    # et « cost_units) class="..." » devient du « texte » a traduire.
    #
    # Le symptome est brutal et lointain : un `@endif` sort non compile, et
    # Blade echoue sur un fichier qui parait intact a la lecture.
    if attributs and ('@' in attributs or '{{' in attributs or '->' in attributs):
        continue

    contenu = m.group(3)

    # `<x-...>` est un COMPOSANT BLADE. Place dans un fichier de langue, il
    # n'est jamais compile : son code source s'affiche en clair sur la page,
    # et ce qu'il devait rendre disparait. Rien ne le signale.
    if '{{' in contenu or '@' in contenu or '<svg' in contenu or '<x-' in contenu:
        continue

    nu = re.sub(r'<[^>]+>', '', contenu).strip()

    if len(nu) < 3 or not re.search(r'[A-Za-zÀ-ÿ]{3,}', nu):
        continue

    propre = ' '.join(contenu.split())

    enveloppe = re.fullmatch(r'<(span|strong|em|b)(\s[^>]*)?>([^<>]+)</\1>', propre)
    if enveloppe:
        propre = enveloppe.group(3).strip()

    if propre in vus:
        continue

    base = cle(propre)
    k, i = base, 2
    while k in pris:
        k, i = base + '_' + str(i), i + 1

    pris.add(k)
    vus[propre] = k

io.open(SORTIE, 'w', encoding='utf-8').write(json.dumps(vus, ensure_ascii=False, indent=2))

print(len(vus))
