import 'package:flutter/material.dart';
import 'package:qr_flutter/qr_flutter.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/referral_parrainage.dart';
import '../../../shared/shared.dart';

/// La carte du code de parrainage : code, QR natif, deux boutons.
///
/// # Pourquoi le QR est généré ici et pas téléchargé
///
/// Le contrat le dit mot pour mot : **généré nativement**, jamais une image
/// serveur, « il doit marcher hors ligne, en présentiel ». Montrer un code à
/// quelqu'un dans une cour d'école ne doit pas dépendre du réseau. La donnée
/// encodée est le **lien rendu par l'API**, jamais une adresse composée côté
/// client.
///
/// # L'accessibilité d'un QR
///
/// Un QR n'est pas accessible : le **code en texte reste la voie principale**,
/// et le lecteur d'écran l'épelle caractère par caractère. Le QR, lui, porte un
/// libellé et rien d'autre.
class VfReferralCard extends StatelessWidget {
  const VfReferralCard({
    required this.parrainage,
    required this.onCopier,
    required this.onWhatsApp,
    this.partageEnCours = false,
    super.key,
  });

  final Parrainage parrainage;
  final VoidCallback onCopier;
  final VoidCallback onWhatsApp;
  final bool partageEnCours;

  /// La taille du simulateur, reprise telle quelle.
  static const double tailleQr = 128;

  @override
  Widget build(BuildContext context) {
    return VfCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Text(
            'Votre code de parrainage',
            style: VfTextStyles.hSm,
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: VfSpacing.x12),
          Semantics(
            // Epele caractere par caractere : un code se dicte au telephone, et
            // « ADDERLY26 » lu d'un trait ne se recopie pas.
            label: 'Votre code : ${parrainage.code.split('').join(' ')}',
            excludeSemantics: true,
            child: Text(
              parrainage.code,
              textAlign: TextAlign.center,
              style: VfTextStyles.mono.copyWith(
                fontSize: 26,
                fontWeight: FontWeight.w600,
                color: VfColors.primaryDark,
                letterSpacing: 2.6,
              ),
            ),
          ),
          const SizedBox(height: VfSpacing.x12),
          Center(
            child: Container(
              padding: const EdgeInsets.all(VfSpacing.x9),
              decoration: BoxDecoration(
                color: VfColors.surface,
                border: Border.all(color: VfColors.line),
                borderRadius: VfRadii.mdAll,
              ),
              child: Semantics(
                label: 'QR code de parrainage',
                excludeSemantics: true,
                child: QrImageView(
                  data: parrainage.lien,
                  size: tailleQr - VfSpacing.x9 * 2,
                  backgroundColor: VfColors.surface,
                  eyeStyle: const QrEyeStyle(
                    eyeShape: QrEyeShape.square,
                    color: VfColors.forest,
                  ),
                  dataModuleStyle: const QrDataModuleStyle(
                    dataModuleShape: QrDataModuleShape.square,
                    color: VfColors.forest,
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(height: VfSpacing.x14),
          VfPrimaryButton(
            label: 'Copier le lien',
            icon: Icons.content_copy_rounded,
            expanded: true,
            onPressed: onCopier,
          ),
          const SizedBox(height: VfSpacing.stackSm),
          _BoutonWhatsApp(onPressed: onWhatsApp, busy: partageEnCours),
        ],
      ),
    );
  }
}

/// Le bouton vert WhatsApp du simulateur.
///
/// Il n'est plus dessiné ici : M4 avait écrit le même, au bas d'un article, avec
/// le même jeton `VfBrandColors.whatsapp`. `VfWhatsAppButton` est au socle depuis
/// l'arbitrage n° 139 ; ne reste ici que ce qui est propre au parrainage — le
/// libellé, et l'état « Preparation... » pendant que le lien se compose.
class _BoutonWhatsApp extends StatelessWidget {
  const _BoutonWhatsApp({required this.onPressed, required this.busy});

  final VoidCallback onPressed;
  final bool busy;

  @override
  Widget build(BuildContext context) => VfWhatsAppButton(
    label: busy ? 'Preparation...' : 'Inviter sur WhatsApp',
    semanticsLabel: 'Inviter sur WhatsApp',
    onPressed: busy ? null : onPressed,
  );
}

/// Une ligne de filleul.
///
/// # Ce qui n'est pas montré, et pourquoi
///
/// **Le prénom seul.** Le serveur ne rend rien d'autre : ni adresse — « il n'a
/// pas consenti à ce qu'elle soit rendue à qui l'a invité » (arbitrage n° 74) —
/// ni nom de famille. Afficher le nom complet, comme le fait la maquette,
/// livrerait au parrain des données que son filleul ne lui a pas confiées.
///
/// **Aucun statut « Invité ».** Une ligne de parrainage naît à l'**inscription**
/// du filleul ; l'état antérieur n'existe pas (arbitrage n° 46). Le libellé de
/// statut est celui du serveur, affiché tel quel.
class VfInviteeTile extends StatelessWidget {
  const VfInviteeTile({required this.filleul, super.key});

  final Filleul filleul;

  @override
  Widget build(BuildContext context) {
    final String nom = filleul.prenom ?? 'Filleul';
    return VfNavTile(
      label: nom,
      description: _date(),
      onTap: null,
      trailing: VfTag(
        label: filleul.statutLibelle,
        variant: filleul.statut.estActif
            ? VfTagVariant.prim
            : VfTagVariant.neutre,
      ),
    );
  }

  String? _date() {
    final DateTime? date = filleul.inscritLe?.toLocal();
    if (date == null) {
      return null;
    }
    return 'Inscrit le ${date.day.toString().padLeft(2, '0')}/'
        '${date.month.toString().padLeft(2, '0')}/${date.year}';
  }
}

/// La chronologie « Comment ça marche ».
///
/// # La phrase qui évite un faux bogue
///
/// Le premier indicateur compte les **ouvertures du lien**, et la récompense
/// arrive à la **première vérification** du filleul, pas à son inscription
/// (arbitrage n° 48). Sans le dire à l'écran, quelqu'un qui voit « 8 invitations
/// ouvertes » et « 0 unité gagnée » conclut à une panne. La chronologie du
/// simulateur l'écrit déjà correctement à sa troisième étape ; la mention finale
/// la rend explicite.
class VfReferralHowTo extends StatelessWidget {
  const VfReferralHowTo({super.key});

  @override
  Widget build(BuildContext context) {
    return VfCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          Text('Comment ca marche', style: VfTextStyles.hSm),
          const SizedBox(height: VfSpacing.x12),
          const VfActivityTimeline(
            entries: <({String title, String description})>[
              (
                title: 'Vous partagez votre code',
                description: 'Par WhatsApp, SMS ou QR en presentiel.',
              ),
              (
                title: 'Votre filleul cree son compte',
                description:
                    'Une ligne de parrainage apparaît ici a son inscription.',
              ),
              (
                title: 'Il fait sa premiere vérification',
                description:
                    'C est a ce moment-la que vos unités sont creditees, '
                    'automatiquement.',
              ),
            ],
          ),
          Text(
            'Le compteur « Invitations ouvertes » compte les personnes qui ont '
            'ouvert votre lien, pas celles a qui vous l avez envoye : un '
            'partage WhatsApp ne se constate pas depuis un serveur.',
            style: VfTextStyles.tXs,
          ),
        ],
      ),
    );
  }
}
