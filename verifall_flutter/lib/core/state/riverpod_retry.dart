/// La politique de réessai **des providers**, pour les cinq parcours.
///
/// # Ce que Riverpod 3 fait par défaut, et pourquoi on le refuse
///
/// Riverpod 3 rejoue de lui-même la construction d'un provider en échec, avec un
/// recul exponentiel — jusqu'à `maxRetries` tentatives. Ce n'est pas anodin : la
/// politique de reprise de l'application est **déjà écrite, et elle est
/// ailleurs**. Elle vit dans la couche réseau du socle :
///
/// - l'intercepteur de réessai — trois tentatives, sur les seuls échecs de
///   transport et les `502/503/504`, et seulement si la requête est rejouable ;
/// - l'intercepteur de débit, qui applique la règle du contrat : « attendre
///   `meta.retry_after` secondes. **Un seul essai ensuite.** »
///
/// Une seconde couche de reprise, invisible depuis le code de l'écran,
/// multiplierait les appels et contredirait cette phrase-là. Le défaut a été
/// découvert par un test de M5 qui n'en finissait pas : **cinq appels à
/// `/me/wallet` là où on en attendait un** (arbitrage n° 115).
///
/// # Pourquoi c'est le socle qui tranche
///
/// M5 l'avait désactivé chez lui, M2 aussi — deux parcours écrivant la même ligne
/// pour la même raison (arbitrages n° 115 et 127.4). M1, M3 et M4, eux, ne
/// l'avaient pas désactivé : trois parcours rejouaient donc en silence pendant
/// que deux s'en gardaient. C'était l'incohérence, pas la ligne, qui posait
/// problème. Arbitrage n° 132.
///
/// **Conséquence assumée** : un écran en erreur **le reste** jusqu'à ce qu'on
/// tire pour rafraîchir ou qu'on touche « Réessayer ». C'est plus vrai encore sur
/// `GET /me/wallet` : un écran qui repart tout seul en boucle sur un réseau coupé
/// consomme le forfait de quelqu'un sans rien lui apprendre.
///
/// # Comment elle s'applique
///
/// Elle est posée **une fois**, sur le conteneur de providers
/// (`ProviderContainer(retry: sansReessaiAutomatique)`), dans la racine de
/// composition. Les providers qui la déclarent aussi individuellement le font
/// pour rester lisibles à l'endroit où le comportement compte ; ils citent cette
/// fonction, ils n'en réécrivent pas une.
///
/// POURQUOI le type `Retry` n'est pas nommé : il n'est exporté ni par
/// `flutter_riverpod`, ni par `package:riverpod/misc.dart`. La signature écrite
/// en clair lui est structurellement identique.
Duration? sansReessaiAutomatique(int tentative, Object echec) => null;
