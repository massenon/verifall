"""Remplace le texte d'une vue Blade par des appels au fichier de langue.

La logique de reperage est RIGOUREUSEMENT celle de `extraire.py`. Toute
divergence laisserait des chaines derriere elle, et une chaine oubliee ne
produit aucune erreur : elle reste simplement en francais quand la page bascule
en anglais, ce que personne ne remarque avant qu'un lecteur anglophone ne le
signale.

Les valeurs qui portent du balisage en ligne sortent en syntaxe non echappee.
Ce n'est PAS une entorse a la regle de securite : ces chaines vivent dans
`lang/`, versionnees dans le depot, ecrites par l'equipe. La regle vise les
donnees d'utilisateur, et elle reste entiere. Le detecteur de la liste de
securite n'accepte d'ailleurs que les cles ECRITES EN TOUTES LETTRES.
"""

import io
import json
import re
import sys

VUE, CARTE, ESPACE = sys.argv[1], sys.argv[2], sys.argv[3]

s = io.open(VUE, encoding='utf-8').read()
d = json.load(io.open(CARTE, encoding='utf-8'))

zones = []
for motif in [r'\{\{--.*?--\}\}', r'<script.*?</script>', r'<svg.*?</svg>', r'<!--.*?-->']:
    for m in re.finditer(motif, s, re.S):
        zones.append((m.start(), m.end()))


def exclu(debut, fin):
    return any(a <= debut and fin <= b for a, b in zones)


BALISES = r'h1|h2|h3|h4|h5|h6|p|li|button|figcaption|summary|label|td|th'
motif = re.compile(r'<(' + BALISES + r')(\s[^>]*)?>(.*?)</\1>', re.S)

compteur = {'n': 0}


def substituer(m):
    if exclu(m.start(), m.end()):
        return m.group(0)

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
        return m.group(0)

    contenu = m.group(3)

    # `<x-...>` est un COMPOSANT BLADE. Place dans un fichier de langue, il
    # n'est jamais compile : son code source s'affiche en clair sur la page,
    # et ce qu'il devait rendre disparait. Rien ne le signale.
    if '{{' in contenu or '@' in contenu or '<svg' in contenu or '<x-' in contenu:
        return m.group(0)

    nu = re.sub(r'<[^>]+>', '', contenu).strip()

    if len(nu) < 3 or not re.search(r'[A-Za-zÀ-ÿ]{3,}', nu):
        return m.group(0)

    propre = ' '.join(contenu.split())

    enveloppe = re.fullmatch(r'<(span|strong|em|b)(\s[^>]*)?>([^<>]+)</\1>', propre)
    interieur = enveloppe.group(3).strip() if enveloppe else propre

    cle = d.get(interieur)

    if cle is None:
        return m.group(0)

    appel = ("{!! __('%s.%s') !!}" if '<' in interieur else "{{ __('%s.%s') }}") % (ESPACE, cle)

    compteur['n'] += 1

    if enveloppe:
        ouvrant = '<' + enveloppe.group(1) + (enveloppe.group(2) or '') + '>'
        fermant = '</' + enveloppe.group(1) + '>'
        appel = ouvrant + appel + fermant

    return '<%s%s>%s</%s>' % (m.group(1), m.group(2) or '', appel, m.group(1))


io.open(VUE, 'w', encoding='utf-8', newline='\n').write(motif.sub(substituer, s))

print(compteur['n'])
