# Polices serveur — pourquoi trois `.ttf` dans `resources/`

*Écrit le 26 août 2026, avec le lot « carte de démenti ».*

---

## Le problème, en une phrase

GD ne sait pas lire le `.woff2`.

Le site charge ses polices depuis `public/assets/css/fonts/files/` — huit
fichiers `.woff2`, exactement ce qu'un navigateur veut. FreeType, que GD emploie
pour `imagettftext()`, n'ouvre que du `.ttf` ou de l'`.otf`. Sans police
lisible, GD retombe sur sa fonte bitmap intégrée : cinq pixels de haut, sans
accents, illisible sur une image de 1200 pixels de large. Une carte de démenti
dessinée dans cette fonte-là ne serait pas une carte dégradée, elle serait une
carte inutile.

Il n'existait **aucun** `.ttf` dans le dépôt — ni dans `public/`, ni dans
`vendor/`. Vérifié.

## Ce qui a été fait

Les trois graisses d'Inter réellement employées par la carte ont été
**converties depuis les `.woff2` déjà présents dans le dépôt**, sans changer un
seul contour : le `.woff2` n'est qu'un `.ttf` compressé, et la conversion ne
fait que le décompresser.

| Fichier | Source | Emploi sur la carte |
|---|---|---|
| `Inter-Bold.ttf` | `inter-latin-700-normal.woff2` | le verdict, le titre |
| `Inter-SemiBold.ttf` | `inter-latin-600-normal.woff2` | les sur-titres, la date |
| `Inter-Regular.ttf` | `inter-latin-400-normal.woff2` | la phrase de raison, l'adresse |

Environ 67 Ko chacun. Ils ne sont **jamais servis par HTTP** — d'où
`resources/` et non `public/` : un fichier que seul le serveur lit n'a rien à
faire dans un dossier public.

Le sous-ensemble « latin » couvre l'intégralité de ce que le produit écrit :
`É È À Ç Ù Ô Î Ï Œ œ « » ’ — …` sont tous présents. Vérifié caractère par
caractère sur la table `cmap`.

## Licence

Inter est publiée sous **SIL Open Font License 1.1**.

> Copyright 2016 The Inter Project Authors (https://github.com/rsms/inter)
> Texte de la licence : https://openfontlicense.org

Ces deux lignes sont celles que porte la table `name` du fichier lui-même
(identifiants 0 et 14) ; elles n'ont pas été recopiées d'ailleurs. La licence
autorise expressément la redistribution et la modification, la conversion de
format en faisant partie. Le nom de la fonte est inchangé, comme la licence
l'exige lorsqu'on ne modifie pas les dessins.

## Si un jour il faut refaire la conversion

Elle ne demande aucune dépendance PHP ni Composer — c'est un outillage de
poste, joué une fois :

```bash
python -c "
from fontTools.ttLib import TTFont
for a, b in {'400':'Regular', '600':'SemiBold', '700':'Bold'}.items():
    f = TTFont(f'public/assets/css/fonts/files/inter-latin-{a}-normal.woff2')
    f.flavor = None
    f.save(f'resources/fonts/Inter-{b}.ttf')
"
```

`fontTools` et `brotli` suffisent. Rien de tout cela ne tourne en production :
les `.ttf` sont versionnés, la production ne fait que les lire.
