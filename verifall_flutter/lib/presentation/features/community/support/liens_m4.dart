import 'package:flutter_riverpod/flutter_riverpod.dart';

import '../../../../data/datasources/remote/community/ouvreur_de_lien_systeme.dart';
import '../../../../domain/usecases/community/ouvrir_une_candidature.dart';

/// L'ouverture d'un lien contenu dans un corps de message.
///
/// # Pourquoi un lien de forum se traite comme une candidature
///
/// Le corps d'un sujet est du contenu **soumis par un citoyen**. Deux règles du
/// back s'y appliquent, et elles se transposent ici :
///
/// > « Les liens signalés par des utilisateurs sortent en `nofollow noopener
/// > ugc` — une plateforme qui combat la désinformation ne transmet pas son
/// > autorité de référencement à l'arnaque qu'on lui signale. »
///
/// Sur mobile, il n'y a ni `rel` ni moteur de référencement : l'équivalent de
/// `noopener` et de `ugc`, c'est **l'onglet système** — session isolée, moins de
/// surface d'attaque, et le vrai domaine visible avant toute saisie.
///
/// [OuvrirUneCandidature.normaliser] fait le reste du travail : elle **refuse**
/// `javascript:`, `data:` et `file:`. Un corps de message est exactement
/// l'endroit par où un lien de ce genre arriverait.
Future<void> ouvrirLienDeProse(WidgetRef ref, String url) async {
  final Uri? cible = OuvrirUneCandidature.normaliser(url);
  if (cible == null) {
    return;
  }
  await ref.read(ouvreurDeLienProvider).ouvrirEnOngletSysteme(cible);
}
