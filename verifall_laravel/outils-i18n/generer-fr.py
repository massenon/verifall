"""Engendre un fichier de langue francais depuis une carte extraite d'une vue.

Le francais est copie TEL QUEL, jamais retape : une recopie a la main
introduirait des ecarts invisibles dans un texte que la regle du chantier
interdit de modifier.
"""

import io
import json
import sys

CARTE, SORTIE, TITRE = sys.argv[1], sys.argv[2], sys.argv[3]

d = json.load(io.open(CARTE, encoding='utf-8'))

ENTETE = """<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| %s
|--------------------------------------------------------------------------
|
| ENGENDRE depuis la vue, puis relu. La copie du prototype reste ainsi
| identique au caractere pres.
|
| Les valeurs qui portent du balisage en ligne sont rendues en syntaxe non
| echappee. Ce n'est PAS une entorse a la regle de securite : ces chaines sont
| ecrites par l'equipe, jamais par un visiteur.
|
*/

return [
"""


def echapper(valeur):
    return valeur.replace('\\', '\\\\').replace("'", "\\'")


lignes = [ENTETE % TITRE]

for fr, cle in d.items():
    lignes.append("    '%s' => '%s',\n" % (cle, echapper(fr)))

lignes.append("];\n")

io.open(SORTIE, 'w', encoding='utf-8', newline='\n').write(''.join(lignes))

print(len(d))
