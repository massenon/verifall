import '../../../domain/entities/wallet_portefeuille.dart';
import '../json_reader.dart';

/// La lecture du portefeuille, des packs, du relevé et de la demande de
/// recharge.
///
/// **Aucune fonction d'écriture en cache ici, et c'est le sujet.** Le solde et
/// la grille tarifaire ne se persistent pas : « on relit cet écran précisément
/// parce qu'on attend un changement ; une valeur d'il y a cinq minutes fait
/// croire à un paiement perdu » (contrat §7.1).
abstract final class PortefeuilleModel {
  static Portefeuille depuisJson(
    Map<String, Object?> json, {
    required DateTime luLe,
  }) {
    final JsonReader lecteur = JsonReader(json);
    return Portefeuille(
      solde: lecteur.intValue('solde'),
      packs: <PackUnites>[
        for (final Map<String, Object?> pack in lecteur.objectListOrEmpty(
          'packs',
        ))
          packDepuisJson(pack),
      ],
      luLe: luLe,
    );
  }

  static PackUnites packDepuisJson(Map<String, Object?> json) {
    final JsonReader lecteur = JsonReader(json);
    return PackUnites(
      code: lecteur.string('code'),
      libelle: lecteur.string('libelle'),
      unites: lecteur.intValue('unites'),
      prixFcfa: lecteur.intValue('prix_fcfa'),
      misEnAvant: lecteur.boolValue('mis_en_avant'),
    );
  }

  static MouvementUnite mouvementDepuisJson(Map<String, Object?> json) {
    final JsonReader lecteur = JsonReader(json);
    return MouvementUnite(
      uuid: lecteur.string('uuid'),
      delta: lecteur.intValue('delta'),
      libelle: lecteur.string('libelle'),
      soldeApres: lecteur.intOrNull('solde_apres'),
      motif: lecteur.stringOrNull('motif'),
      motifLibelle: lecteur.stringOrNull('motif_libelle'),
      date: lecteur.dateTimeOrNull('date'),
    );
  }

  static DemandeRecharge demandeDepuisJson(Map<String, Object?> json) {
    final JsonReader lecteur = JsonReader(json);
    return DemandeRecharge(
      uuid: lecteur.string('uuid'),
      statut: lecteur.string('statut'),
      statutLibelle: lecteur.string('statut_libelle'),
      operateur: lecteur.stringOrNull('operateur'),
      montantFcfa: lecteur.intOrNull('montant_fcfa'),
      unites: lecteur.intOrNull('unites'),
    );
  }
}
