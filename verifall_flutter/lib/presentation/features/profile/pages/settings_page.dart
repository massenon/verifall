import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/errors/failure.dart';
import '../../../../core/network/auth_controller.dart';
import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/settings_reglages.dart';
import '../../../../domain/usecases/profile/regler_une_preference.dart';
import '../../../shared/shared.dart';
import '../providers/profile_dependencies.dart';
import '../providers/settings_provider.dart';
import '../widgets/delete_account_sheet.dart';
import 'package:url_launcher/url_launcher.dart';
import '../../../../core/config/config_providers.dart';
import '../../../../core/config/vf_web_links.dart';

/// Écran 30 · Réglages.
///
/// # Ce que l'écran ne montre plus, et pourquoi rien n'est laissé inerte
///
/// | Élément du simulateur | Décision |
/// |---|---|
/// | Interrupteur **Double authentification** | **Retiré.** Aucun endpoint en v1 ; `PATCH /me/settings` refuse en `422` toute clé inconnue. « Un interrupteur qui échoue à chaque pression est pire que son absence ; un interrupteur de sécurité qui semblerait marcher sans rien activer serait un mensonge sur une garantie » (réserve C.8) |
/// | **Changer mon mot de passe** | **Conservé**, mais il ouvre le parcours de code de M1 (`/mot-de-passe-oublie`), le seul que l'API serve. Il n'existe pas de changement authentifié en v1 |
/// | **Appareils connectés · 2** | **Retiré.** Aucun endpoint de session, et le nombre comme la ligne « Cet appareil · Cotonou » figurent parmi les données de démonstration à ne jamais transposer (09-RESERVES §B). Afficher un compte inventé serait un indicateur non mesuré |
/// | **Politique de confidentialité** | **Rendue au menu — sous condition.** Elle n'y était pas faute d'adresse de site côté client ; `AppConfig.webBaseUrl` en porte une depuis l'arbitrage n° 138. La ligne apparaît **si et seulement si** cette adresse est configurée : une ligne inerte reste pire qu'une ligne absente |
/// | Sous-titres des interrupteurs | `GET /me/settings` rend les **libellés**, pas de description. On affiche ce qui est servi |
///
/// # L'adresse e-mail
///
/// Elle n'est pas ici : elle reste sur l'écran 25, affichée en lecture seule
/// avec la mention de l'endroit où la changer (arbitrage n° 6).
class SettingsPage extends ConsumerWidget {
  const SettingsPage({super.key});

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<Reglages> etat = ref.watch(settingsProvider);

    return Scaffold(
      backgroundColor: VfColors.canvas,
      appBar: const VfAppBar(title: 'Réglages'),
      body: etat.when(
        loading: () => const _Squelette(),
        error: (Object erreur, StackTrace _) => VfEmptyState(
          kind: VfEmptyKind.rienEncore,
          icon: Icons.settings_outlined,
          title: 'Réglages indisponibles',
          message: erreur is Failure
              ? (erreur.serverMessage ?? 'Vos réglages n ont pas pu être lus.')
              : 'Vos réglages n ont pas pu être lus.',
          action: VfGhostButton(
            label: 'Réessayer',
            onPressed: () => ref.read(settingsProvider.notifier).rafraichir(),
          ),
        ),
        data: (Reglages reglages) => _Corps(reglages: reglages),
      ),
    );
  }
}

class _Corps extends ConsumerWidget {
  const _Corps({required this.reglages});

  final Reglages reglages;

  @override
  Widget build(BuildContext context, WidgetRef ref) {
    final AsyncValue<String> version = ref.watch(versionInstalleeProvider);
    final Uri? confidentialite = VfWebLinks.confidentialite(
      ref.watch(appConfigProvider),
    );

    return ListView(
      padding: const EdgeInsets.fromLTRB(
        VfSpacing.screenInline,
        VfSpacing.screenTopExtra,
        VfSpacing.screenInline,
        VfSpacing.screenBottomExtra,
      ),
      children: <Widget>[
        Text('Sécurité', style: VfTextStyles.hSm),
        const SizedBox(height: VfSpacing.stackSm),
        VfMenuList(
          children: <Widget>[
            VfNavTile(
              label: 'Changer mon mot de passe',
              // Le parcours de code est le seul que l'API serve : un code part
              // par courriel, et il revoque toutes les sessions. Le dire evite
              // la surprise d'une deconnexion generale.
              description:
                  'Un code vous sera envoye par e-mail. Toutes vos sessions '
                  'seront fermees.',
              icon: Icons.key_outlined,
              trailing: const Icon(Icons.chevron_right_rounded, size: 20),
              onTap: () => context.push(VfRoutes.forgotPassword),
            ),
          ],
        ),
        const SizedBox(height: VfSpacing.x10),
        Text(
          'La double authentification et le changement d adresse e-mail se '
          'reglent sur le site Vérif All : l application ne les propose pas '
          'tant qu elle ne peut pas les appliquer reellement.',
          style: VfTextStyles.tXs,
        ),
        const SizedBox(height: VfSpacing.stack),
        Text('Notifications', style: VfTextStyles.hSm),
        const SizedBox(height: VfSpacing.stackSm),
        VfMenuList(
          children: <Widget>[
            // La liste vient du SERVEUR : un canal ajoute cote back apparait
            // tout seul, un canal retire disparait tout seul.
            for (final CanalNotification canal in reglages.notifications)
              VfSwitchTile(
                label: canal.libelle,
                value: canal.active,
                onChanged: (bool valeur) =>
                    _basculer(context, ref, cle: canal.cle, valeur: valeur),
              ),
            VfSwitchTile(
              label: 'Lettre d information',
              // Ce n'est pas une preference d'affichage : c'est un consentement
              // marketing, horodate et versionne (arbitrage n. 11). Le dire ici
              // est ce qui rend la case honnete.
              description:
                  'Consentement enregistre avec sa date. Vous pouvez le '
                  'retirer a tout moment.',
              value: reglages.newsletter,
              onChanged: (bool valeur) =>
                  _basculerNewsletter(context, ref, valeur),
            ),
          ],
        ),
        const SizedBox(height: VfSpacing.stack),
        Text('Donnees personnelles', style: VfTextStyles.hSm),
        const SizedBox(height: VfSpacing.stackSm),
        VfMenuList(
          children: <Widget>[
            VfNavTile(
              label: 'Exporter mes donnees',
              description:
                  'Archive de tout votre compte (RGPD art. 20), envoyee par '
                  'e-mail.',
              icon: Icons.download_outlined,
              onTap: () => _exporter(context, ref),
            ),
            // La ligne n'apparait QUE si l'adresse du site est configuree : une
            // ligne qui n'ouvre rien serait pire que son absence. Voir
            // `AppConfig.webBaseUrl` et l'arbitrage n° 138.
            if (confidentialite != null)
              VfNavTile(
                label: 'Politique de confidentialité',
                icon: Icons.privacy_tip_outlined,
                onTap: () => _ouvrir(context, confidentialite),
              ),
          ],
        ),
        const SizedBox(height: VfSpacing.stack),
        Text(
          'Zone de danger',
          style: VfTextStyles.hSm.copyWith(color: VfColors.brandRed),
        ),
        const SizedBox(height: VfSpacing.stackSm),
        VfMenuList(
          children: <Widget>[
            VfNavTile(
              label: 'Supprimer definitivement mon compte',
              icon: Icons.delete_forever_outlined,
              danger: true,
              onTap: () => _supprimer(context, ref),
            ),
          ],
        ),
        const SizedBox(height: VfSpacing.stack),
        Text(
          // Lue du paquet installe, jamais ecrite en dur : « une version figee
          // dans une chaine de caracteres finit toujours par mentir ».
          'Vérif All Mobile · ${version.value ?? '...'}',
          style: VfTextStyles.tXs,
          textAlign: TextAlign.center,
        ),
      ],
    );
  }

  Future<void> _basculer(
    BuildContext context,
    WidgetRef ref, {
    required String cle,
    required bool valeur,
  }) async {
    try {
      final ResultatReglage resultat = await ref
          .read(settingsProvider.notifier)
          .definirCanal(cle: cle, valeur: valeur);
      if (!context.mounted) {
        return;
      }
      VfToast.show(
        context,
        message: switch (resultat) {
          ResultatReglage.enregistre when valeur => 'Preference activee.',
          ResultatReglage.enregistre => 'Preference desactivee.',
          ResultatReglage.misEnFile =>
            'Pas de réseau : ce reglage partira des le retour de la connexion.',
        },
      );
    } on Failure catch (echec) {
      if (context.mounted) {
        VfToast.error(
          context,
          echec.serverMessage ?? 'Ce reglage n a pas pu être enregistre.',
        );
      }
    }
  }

  Future<void> _basculerNewsletter(
    BuildContext context,
    WidgetRef ref,
    bool valeur,
  ) async {
    try {
      final ResultatReglage resultat = await ref
          .read(settingsProvider.notifier)
          .definirNewsletter(valeur: valeur);
      if (!context.mounted) {
        return;
      }
      VfToast.show(
        context,
        message: switch (resultat) {
          ResultatReglage.enregistre when valeur =>
            'Consentement enregistre, avec sa date.',
          ResultatReglage.enregistre => 'Consentement retire, avec sa date.',
          ResultatReglage.misEnFile =>
            'Pas de réseau : ce choix partira des le retour de la connexion.',
        },
      );
    } on Failure catch (echec) {
      if (context.mounted) {
        VfToast.error(
          context,
          echec.serverMessage ?? 'Ce choix n a pas pu être enregistre.',
        );
      }
    }
  }

  /// L'export **n'attend rien** : `202`, et l'archive arrive par courriel.
  ///
  /// Un `409 conflit` affiche **le message du serveur, sans relancer** : chaque
  /// archive est une copie complète de données personnelles, et en produire à la
  /// chaîne multiplie les objets à protéger.
  /// Ouvre une page du site public **en onglet systeme**, jamais en WebView.
  ///
  /// « Une WebView maison ne montre pas l'adresse, ne partage pas les cookies de
  /// session du navigateur, et se fait passer pour l'application. »
  Future<void> _ouvrir(BuildContext context, Uri adresse) async {
    final bool ouvert = await launchUrl(
      adresse,
      mode: LaunchMode.externalApplication,
    );
    if (!ouvert && context.mounted) {
      // Un echec silencieux laisserait croire que la page s'est ouverte
      // derriere l'application.
      VfToast.error(context, 'La page n a pas pu être ouverte.');
    }
  }

  Future<void> _exporter(BuildContext context, WidgetRef ref) async {
    try {
      final String message = await ref.read(exporterMesDonneesProvider)();
      if (context.mounted) {
        VfToast.show(
          context,
          message: message.isEmpty
              ? 'Export lance — vous le recevrez par e-mail.'
              : message,
        );
      }
    } on Failure catch (echec) {
      if (context.mounted) {
        VfToast.error(
          context,
          echec.serverMessage ?? 'L export n a pas pu être lance.',
        );
      }
    }
  }

  /// La suppression, **en deux temps**.
  Future<void> _supprimer(BuildContext context, WidgetRef ref) async {
    final SuppressionCompte?
    resultat = await VfBottomSheet.show<SuppressionCompte>(
      context: context,
      title: 'Supprimer definitivement le compte ?',
      builder: (BuildContext sheetContext) => VfDeleteAccountSheet(
        onDemanderLeCode: (String confirmation, String motDePasse) => ref
            .read(supprimerMonCompteProvider)
            .demanderLeCode(confirmation: confirmation, motDePasse: motDePasse),
        onConfirmer: (String confirmation, String motDePasse, String code) =>
            ref
                .read(supprimerMonCompteProvider)
                .confirmer(
                  confirmation: confirmation,
                  motDePasse: motDePasse,
                  code: code,
                ),
      ),
    );

    if (resultat == null ||
        resultat.etape != EtapeSuppression.suppressionProgrammee) {
      return;
    }

    // Le second temps revoque TOUS les jetons, y compris celui qui vient de
    // servir. La session locale doit donc suivre : l'ecran d'etat prend le
    // relais, alimente par `GET /me`, qui reste servi.
    ref
        .read(authControllerProvider.notifier)
        .setDeletionRequestedAt(resultat.demandeeLe ?? DateTime.now());

    if (context.mounted) {
      context.go(VfRoutes.accountBeingDeleted);
    }
  }
}

class _Squelette extends StatelessWidget {
  const _Squelette();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(VfSpacing.screenInline),
      children: const <Widget>[
        VfSkeleton(height: 24, width: 120),
        SizedBox(height: VfSpacing.stackSm),
        VfSkeleton(height: 108, borderRadius: VfRadii.lgAll),
        SizedBox(height: VfSpacing.stack),
        VfSkeleton(height: 24, width: 140),
        SizedBox(height: VfSpacing.stackSm),
        VfSkeleton(height: 216, borderRadius: VfRadii.lgAll),
      ],
    );
  }
}
