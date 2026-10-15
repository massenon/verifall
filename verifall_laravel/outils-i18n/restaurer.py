"""Rend a une vue son texte francais d'origine, depuis la carte d'extraction.

Sert de marche arriere : si le remplacement a casse quelque chose, on revient a
l'etat connu plutot que de reparer a l'aveugle sur du code deja abime.
"""
import io, json, re, sys

VUE, CARTE, ESPACE = sys.argv[1], sys.argv[2], sys.argv[3]

s = io.open(VUE, encoding='utf-8').read()
d = json.load(io.open(CARTE, encoding='utf-8'))
inverse = {cle: fr for fr, cle in d.items()}

n = [0]

def rendre(m):
    cle = m.group(1)
    if cle in inverse:
        n[0] += 1
        return inverse[cle]
    return m.group(0)

s = re.sub(r"\{!! __\('" + ESPACE + r"\.([a-z0-9_]+)'\) !!\}", rendre, s)
s = re.sub(r"\{\{ __\('" + ESPACE + r"\.([a-z0-9_]+)'\) \}\}", rendre, s)

io.open(VUE, 'w', encoding='utf-8', newline='\n').write(s)
print(n[0])
