# Polices

**Ressources locales, jamais réseau.** Le pied du panneau de transposition du
simulateur l'écrit : *« Polices Inter + Instrument Sans (assets locaux, jamais
réseau) »*. Un premier lancement qui attend une police distante affiche un écran
nu sur une connexion lente — c'est-à-dire dans le cas d'usage principal du
produit.

## Les fichiers attendus

| Fichier | Famille | Graisse | Où elle sert |
|---|---|---|---|
| `Inter-Regular.ttf` | Inter | 400 | Corps, légendes, libellés |
| `Inter-Medium.ttf` | Inter | 500 | |
| `Inter-SemiBold.ttf` | Inter | 600 | Boutons, liens de section |
| `Inter-Bold.ttf` | Inter | 700 | Sur-titres |
| `InstrumentSans-Bold.ttf` | Instrument Sans | 700 | Titres `h1..h4` |
| `InstrumentSans-ExtraBold.ttf` | Instrument Sans | 800 | Valeurs de KPI |
| `IBMPlexMono-Regular.ttf` | IBM Plex Mono | 400 | Référence de vérification, identifiant de certificat, code de parrainage |
| `IBMPlexMono-SemiBold.ttf` | IBM Plex Mono | 600 | Solde de la pastille de points |

`IBM Plex Mono` n'apparaît que sur trois éléments — **partout où un humain devra
recopier des caractères sans se tromper**.

## Comment les activer

1. Déposer les huit fichiers dans ce dossier.
2. Décommenter le bloc `fonts:` de `pubspec.yaml` (il est déjà écrit, avec les
   bons chemins et les bonnes graisses).

## Tant qu'elles manquent

Flutter retombe sur la police système. L'application fonctionne, mais :

- **les captures de référence ne sont pas exploitables** (06-TESTS §6.5 : « un
  test de capture qui charge une police depuis le réseau rend des rectangles ») ;
- les hauteurs de ligne diffèrent légèrement du simulateur, donc toute mesure de
  fidélité faite maintenant serait fausse.

C'est une réserve consignée, pas un oubli : les fichiers sont sous licence
(SIL Open Font License pour les trois familles) et leur dépôt relève du porteur
du projet.
