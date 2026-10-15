import 'package:flutter/material.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../core/theme/vf_typography.dart';
import '../../../../domain/entities/gamification_recompenses.dart';
import '../../../../domain/entities/stats_statistiques.dart';
import '../../../shared/shared.dart';

/// La carte de niveau : sur-titre, palier, pastille d'XP, barre.
class VfLevelCard extends StatelessWidget {
  const VfLevelCard({required this.niveau, super.key});

  final NiveauCitoyen niveau;

  @override
  Widget build(BuildContext context) {
    return VfCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          const VfEyebrow('Votre progression'),
          const SizedBox(height: VfSpacing.x8),
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  'Niveau ${niveau.rang} — ${niveau.nom}',
                  style: VfTextStyles.hMd,
                ),
              ),
              VfTag(label: '${niveau.xp} XP', variant: VfTagVariant.xp),
            ],
          ),
          const SizedBox(height: VfSpacing.x10),
          // L'avancement vient du serveur (`pourcentage`). Le recalculer a
          // partir de l'XP appliquerait un bareme cote client, et l'ecran
          // afficherait un jour un pourcentage different de celui du back.
          VfProgressBar(
            value: niveau.avancement,
            semanticsLabel: 'Progression vers le niveau ${niveau.rang + 1}',
          ),
          const SizedBox(height: VfSpacing.x6),
          Text(
            '${niveau.xpDansLePalier} / ${niveau.xpDuPalier} XP · encore '
            '${niveau.xpRestant} XP pour le niveau ${niveau.rang + 1}',
            style: VfTextStyles.tXs,
          ),
        ],
      ),
    );
  }
}

/// Le suivi jour par jour de la série : sept pastilles de 28.
///
/// # Ce que le record de série fait ici, et à quelle condition
///
/// L'arbitrage n° 67 a ajouté `streak_best` côté back parce que le prototype web
/// affichait un « record personnel » que rien ne soutenait. Le simulateur mobile
/// ne l'affiche pas et le contrat ne le nomme pas (réserve D.3). La règle
/// appliquée est celle des champs inconnus : **si l'agrégat le renvoie, la
/// donnée est réelle et s'affiche ; sinon, on ne l'invente pas.** La mention
/// n'apparaît donc que lorsque la valeur arrive réellement.
class VfStreakTracker extends StatelessWidget {
  const VfStreakTracker({
    required this.serie,
    required this.semaine,
    super.key,
  });

  final SerieDeVigilance serie;

  /// Les sept jours, **rendus par le serveur**. Les déduire de l'horloge du
  /// téléphone ferait « casser » une série à minuit pour qui voyage.
  final List<JourDeSemaine> semaine;

  @override
  Widget build(BuildContext context) {
    return VfCard(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          const VfEyebrow('Série'),
          const SizedBox(height: VfSpacing.x8),
          Row(
            children: <Widget>[
              Expanded(
                child: Text(
                  '${serie.jours} jours d affilee',
                  style: VfTextStyles.hSm,
                ),
              ),
              if (serie.record != null)
                VfTag(label: 'Record : ${serie.record}'),
            ],
          ),
          if (semaine.isNotEmpty) ...<Widget>[
            const SizedBox(height: VfSpacing.x12),
            Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: <Widget>[
                for (final JourDeSemaine jour in semaine) _Pastille(jour: jour),
              ],
            ),
          ],
          const SizedBox(height: VfSpacing.x10),
          Text(
            'Verifiez ou apprenez aujourd hui pour ne pas casser la série.',
            style: VfTextStyles.tXs,
          ),
        ],
      ),
    );
  }
}

class _Pastille extends StatelessWidget {
  const _Pastille({required this.jour});

  final JourDeSemaine jour;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      // Le suivi est lu comme sept jours NOMMES avec leur etat, pas comme sept
      // points : « lundi, tenu » plutot qu'un cercle vert.
      label:
          '${jour.lettre}, ${jour.actif
              ? 'tenu'
              : jour.futur
              ? 'a venir'
              : 'non tenu'}'
          '${jour.aujourdhui ? ", aujourd hui" : ''}',
      excludeSemantics: true,
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: <Widget>[
          Container(
            width: 28,
            height: 28,
            alignment: Alignment.center,
            decoration: BoxDecoration(
              color: jour.actif ? VfColors.primary : VfColors.canvas,
              shape: BoxShape.circle,
              border: jour.aujourdhui
                  ? Border.all(color: VfColors.primaryDark, width: 2)
                  : Border.all(color: VfColors.line),
            ),
            child: jour.actif
                ? const Icon(
                    Icons.check_rounded,
                    size: 16,
                    color: VfColors.surface,
                  )
                : null,
          ),
          const SizedBox(height: VfSpacing.x4),
          Text(jour.lettre, style: VfTextStyles.tXs),
        ],
      ),
    );
  }
}

/// La carte « Prochaine étape ».
///
/// # Pourquoi il n'y a plus de barre de progression
///
/// Le simulateur affiche « `<n>` / 10 vérifications terminées » avec une barre.
/// `GET /me/gamification` rend la **condition en toutes lettres**, jamais un
/// couple fait/objectif : la barre serait alimentée par un compteur local, donc
/// par un chiffre fabriqué (règle §2 du chantier, arbitrage n° 46).
///
/// La carte reste, à sa place, et montre la destination — c'est tout son but :
/// « montrer la destination motive davantage que la masquer ».
class VfNextBadgeCard extends StatelessWidget {
  const VfNextBadgeCard({required this.badge, super.key});

  final BadgeCitoyen badge;

  @override
  Widget build(BuildContext context) {
    return VfCard(
      border: VfColors.primary.withValues(alpha: 0.4),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.stretch,
        children: <Widget>[
          const VfEyebrow('Prochaine étape'),
          const SizedBox(height: VfSpacing.x6),
          Text('Badge « ${badge.nom} »', style: VfTextStyles.hSm),
          if (badge.condition != null &&
              badge.condition!.isNotEmpty) ...<Widget>[
            const SizedBox(height: VfSpacing.x4),
            Text(badge.condition!, style: VfTextStyles.tXs),
          ],
        ],
      ),
    );
  }
}

/// Une tuile de badge.
///
/// **Les badges verrouillés restent visibles à 55 % d'opacité** — pas masqués.
/// « Montrer la destination motive davantage que la masquer », et chaque tuile
/// porte sa condition d'obtention : « un cadenas sans explication n'est qu'une
/// frustration gratuite ».
class VfBadgeTile extends StatelessWidget {
  const VfBadgeTile({required this.badge, super.key});

  final BadgeCitoyen badge;

  /// L'opacité du simulateur, reprise telle quelle.
  static const double opaciteVerrouillee = 0.55;

  @override
  Widget build(BuildContext context) {
    final bool obtenu = badge.debloque;
    return Semantics(
      label: <String>[
        badge.nom,
        obtenu ? 'obtenu' : 'verrouille',
        if (!obtenu && badge.condition != null) badge.condition!,
      ].join(', '),
      excludeSemantics: true,
      child: Opacity(
        opacity: obtenu ? 1 : opaciteVerrouillee,
        child: VfCard(
          tight: true,
          padding: const EdgeInsets.symmetric(
            horizontal: VfSpacing.x6,
            vertical: VfSpacing.x14,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: <Widget>[
              Container(
                width: VfAvatarSize.moyen.diameter,
                height: VfAvatarSize.moyen.diameter,
                alignment: Alignment.center,
                decoration: BoxDecoration(
                  color: obtenu ? VfColors.primarySoft : VfColors.canvas,
                  shape: BoxShape.circle,
                ),
                child: Icon(
                  // Le nom d'icone est rendu par le serveur mais Flutter ne
                  // resout pas une icone par son nom sans table de
                  // correspondance ecrite a la main — c'est-a-dire sans figer
                  // cote client une liste que le back fait evoluer. On affiche
                  // donc la MEME icone pour tous les badges obtenus, et le
                  // cadenas pour les autres : le nom du badge, lui, est exact.
                  obtenu ? Icons.military_tech_rounded : Icons.lock_rounded,
                  size: 22,
                  color: obtenu ? VfColors.primaryDark : VfColors.slate2,
                ),
              ),
              const SizedBox(height: VfSpacing.x6),
              // Les deux textes sont BORNES : une cellule de grille a une
              // hauteur fixe, et un nom de badge long y deborderait. La
              // condition complete reste annoncee par le lecteur d'ecran, dans
              // le libelle semantique ci-dessus — elle n'est donc jamais
              // perdue, seulement raccourcie a l'oeil.
              Text(
                badge.nom,
                textAlign: TextAlign.center,
                maxLines: 2,
                overflow: TextOverflow.ellipsis,
                style: VfTextStyles.tXs.copyWith(
                  color: VfColors.ink,
                  fontWeight: FontWeight.w700,
                  height: 1.25,
                ),
              ),
              if (badge.condition != null &&
                  badge.condition!.isNotEmpty) ...<Widget>[
                const SizedBox(height: 3),
                Flexible(
                  child: Text(
                    badge.condition!,
                    textAlign: TextAlign.center,
                    maxLines: 3,
                    overflow: TextOverflow.ellipsis,
                    style: VfTextStyles.tXs.copyWith(fontSize: 10),
                  ),
                ),
              ],
            ],
          ),
        ),
      ),
    );
  }
}

/// Une ligne de certificat.
///
/// # Ce qu'il n'y a pas, et pourquoi ce n'est pas un oubli
///
/// **Aucun bouton « Télécharger le PDF ».** `GET /me/certificates/{code}/pdf`
/// n'existe pas en v1 : aucune bibliothèque de génération n'est installée côté
/// serveur, et « en ajouter une est une décision de dépendance qui appartient au
/// porteur du projet » (§11 du contrat). Un bouton qui échoue à chaque pression
/// est pire que son absence.
///
/// La ligne mène à la fiche du certificat — écran de M3 — qui porte le code
/// public et le renvoi vers la page publique de vérification.
class VfCertificateTile extends StatelessWidget {
  const VfCertificateTile({
    required this.certificat,
    required this.onTap,
    super.key,
  });

  final Certificat certificat;
  final VoidCallback onTap;

  @override
  Widget build(BuildContext context) {
    return VfCard(
      tight: true,
      onTap: onTap,
      child: Row(
        children: <Widget>[
          Container(
            width: VfAvatarSize.moyen.diameter,
            height: VfAvatarSize.moyen.diameter,
            alignment: Alignment.center,
            decoration: const BoxDecoration(
              color: VfColors.forest,
              shape: BoxShape.circle,
            ),
            child: const Icon(
              Icons.workspace_premium_rounded,
              size: 20,
              color: VfColors.surface,
            ),
          ),
          const SizedBox(width: VfSpacing.x12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: <Widget>[
                Text(
                  certificat.coursTitre,
                  style: VfTextStyles.tSm.copyWith(
                    color: VfColors.ink,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                Text(_detail(), style: VfTextStyles.tXs),
              ],
            ),
          ),
          const Icon(
            Icons.chevron_right_rounded,
            size: 20,
            color: VfColors.slate2,
          ),
        ],
      ),
    );
  }

  String _detail() {
    final DateTime? date = certificat.delivreLe?.toLocal();
    final String quand = date == null
        ? ''
        : ' · ${date.day.toString().padLeft(2, '0')}/'
              '${date.month.toString().padLeft(2, '0')}/${date.year}';
    // Un certificat revoque le DIT : le taire laisserait quelqu'un le presenter
    // a un recruteur qui, lui, verrait la revocation sur la page publique.
    final String etat = certificat.revoque ? ' · Revoque' : '';
    return '${certificat.code}$quand$etat';
  }
}
