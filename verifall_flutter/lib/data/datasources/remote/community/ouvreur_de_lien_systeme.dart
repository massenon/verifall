import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:share_plus/share_plus.dart';
import 'package:url_launcher/url_launcher.dart';

import '../../../../domain/usecases/community/ouvrir_une_candidature.dart';

/// L'ouverture d'un lien **en onglet système**.
///
/// # Pourquoi `inAppBrowserView` et pas `inAppWebView`
///
/// `LaunchMode.inAppBrowserView` est ce que le contrat appelle « onglet
/// système » : Chrome Custom Tabs sur Android, `SFSafariViewController` sur
/// iOS. Les deux affichent **le vrai domaine** dans une barre que la page ne
/// peut pas dessiner, et isolent la session.
///
/// `inAppWebView` est exactement ce qui est interdit : une WebView maison masque
/// le domaine. « Sur une page qui met en garde contre les fausses offres,
/// masquer le domaine serait une contradiction complète » (03-ECRANS-05 §3.23).
///
/// Le repli sur `externalApplication` couvre l'appareil sans Custom Tabs : le
/// navigateur système, avec sa barre d'adresse complète, respecte la même règle.
class OuvreurDeLienSysteme implements OuvreurDeLienExterne {
  const OuvreurDeLienSysteme();

  @override
  Future<bool> ouvrirEnOngletSysteme(Uri url) async {
    final bool ouvert = await launchUrl(url, mode: LaunchMode.inAppBrowserView);
    if (ouvert) {
      return true;
    }
    return launchUrl(url, mode: LaunchMode.externalApplication);
  }
}

/// Le partage d'un lien.
///
/// POURQUOI une interface : le partage est le seul geste de ces écrans qui
/// fonctionne **hors ligne** — « le lien est connu » — et un test doit pouvoir
/// le vérifier sans feuille de partage native.
abstract interface class PartageurDeLien {
  Future<void> partager({required String texte});
}

class PartageurSysteme implements PartageurDeLien {
  const PartageurSysteme();

  @override
  Future<void> partager({required String texte}) async {
    await SharePlus.instance.share(ShareParams(text: texte));
  }
}

final Provider<OuvreurDeLienExterne> ouvreurDeLienProvider =
    Provider<OuvreurDeLienExterne>((Ref ref) => const OuvreurDeLienSysteme());

final Provider<PartageurDeLien> partageurDeLienProvider =
    Provider<PartageurDeLien>((Ref ref) => const PartageurSysteme());
