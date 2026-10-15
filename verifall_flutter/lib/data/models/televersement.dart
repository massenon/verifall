import 'package:flutter/foundation.dart';

import 'json_reader.dart';

/// Le type d'un téléversement — `POST /uploads`, champ `type` (§10.3).
///
/// POURQUOI l'énumération porte ses plafonds : « valider format et poids **avant**
/// d'envoyer » (04-RESEAU §4.10). Envoyer 120 Mo pour se faire refuser après
/// trois minutes est le pire scénario possible sur un réseau mobile.
///
/// Les valeurs viennent du contrat, pas d'une estimation : elles sont
/// **identiques à l'écran web**.
enum TypeTeleversement {
  image(
    'image',
    maxBytes: 10 * 1024 * 1024,
    extensions: <String>{'jpg', 'jpeg', 'png', 'webp'},
  ),
  video(
    'video',
    maxBytes: 100 * 1024 * 1024,
    extensions: <String>{'mp4', 'webm', 'mov'},
  ),
  audio(
    'audio',
    maxBytes: 25 * 1024 * 1024,
    extensions: <String>{'mp3', 'wav', 'ogg', 'm4a'},
  );

  const TypeTeleversement(
    this.wireValue, {
    required this.maxBytes,
    required this.extensions,
  });

  final String wireValue;
  final int maxBytes;
  final Set<String> extensions;

  static TypeTeleversement? parse(String? raw) {
    for (final TypeTeleversement type in TypeTeleversement.values) {
      if (type.wireValue == raw) {
        return type;
      }
    }
    return null;
  }

  bool acceptsExtension(String extension) =>
      extensions.contains(extension.toLowerCase().replaceFirst('.', ''));

  bool acceptsSize(int bytes) => bytes > 0 && bytes <= maxBytes;
}

/// La réponse de `POST /uploads` : `201 { data: { televersement, nom, octets } }`.
///
/// POURQUOI l'identifiant ne suffit pas à la file d'écriture : le fichier
/// n'attend que **deux heures** sur le disque privé. Une soumission mise en file
/// et rejouée au-delà doit re-téléverser — la file garde donc la copie locale du
/// fichier, pas seulement cet identifiant (04-RESEAU §4.10).
@immutable
class Televersement {
  const Televersement({
    required this.uuid,
    required this.nom,
    required this.octets,
  });

  factory Televersement.fromJson(Map<String, Object?> json) {
    final JsonReader reader = JsonReader(json);
    return Televersement(
      uuid: reader.string('televersement'),
      nom: reader.string('nom'),
      octets: reader.intValue('octets'),
    );
  }

  final String uuid;
  final String nom;
  final int octets;

  /// Le délai de rétention côté serveur, fixé par le contrat.
  static const Duration retention = Duration(hours: 2);

  @override
  String toString() => 'Televersement($uuid)';
}
