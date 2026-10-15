/// Le rythme du sondage de l'écran « Analyse en cours ».
///
/// # Pourquoi trois secondes, et pas une valeur choisie au hasard
///
/// Le contrat le dit : *« le plafond de lecture — 120 par minute — a été
/// dimensionné exprès pour un sondage toutes les trois secondes : se faire
/// éconduire à ce moment-là ferait croire à une panne »* (§5.1). Trois secondes
/// n'est donc pas une préférence : c'est la cadence pour laquelle le serveur a
/// été réglé.
///
/// # Pourquoi le client s'espace tout seul
///
/// « Un plafond sans en-têtes est un mur invisible » (§5.3). Sous 10 % du quota
/// restant, l'intercepteur du socle lève déjà `shouldThrottle` ; l'écran de
/// suivi allonge alors sa cadence **avant** de se faire éconduire, plutôt que de
/// prendre un 429 et d'afficher une erreur au pire moment.
///
/// # Ce que ce fichier ne fait surtout pas
///
/// Il ne fabrique **aucune** progression. La barre affiche `data.avancement`,
/// point. « Le prototype fait défiler ses étapes sur des minuteurs aléatoires.
/// Ici elles sont posées par l'orchestrateur au moment où il les franchit : si
/// l'analyse cale sur les moteurs, l'affichage cale avec elle » (arbitrage
/// n° 28). Ce rythme décide **quand relire**, jamais **quoi afficher**.
abstract final class RythmeDuSondage {
  /// La cadence nominale.
  static const Duration nominal = Duration(seconds: 3);

  /// La cadence ralentie, quand le quota restant passe sous le seuil.
  static const Duration espace = Duration(seconds: 10);

  /// Au-delà, on cesse de sonder et on propose de revenir plus tard.
  ///
  /// POURQUOI une borne : l'arbitrage n° 28 demande qu'un blocage prolongé « se
  /// dise » plutôt que de laisser tourner un écran indéfiniment. Un téléphone
  /// qui sonde une heure en arrière-plan vide une batterie et un forfait.
  static const Duration avantDeProposerDeRevenir = Duration(minutes: 5);

  static Duration prochain({required bool quotaFaible}) =>
      quotaFaible ? espace : nominal;
}
