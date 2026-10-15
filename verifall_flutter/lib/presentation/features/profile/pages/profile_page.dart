import 'package:flutter/material.dart';
import 'package:flutter_riverpod/flutter_riverpod.dart';
import 'package:go_router/go_router.dart';

import '../../../../core/errors/failure.dart';
import '../../../../core/router/vf_routes.dart';
import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_metrics.dart';
import '../../../../domain/entities/profile_moi.dart';
import '../../../../domain/usecases/profile/enregistrer_le_profil.dart';
import '../../../shared/shared.dart';
import '../providers/profile_provider.dart';
import '../widgets/profile_widgets.dart';
import '../../../../core/session/solde_unites.dart';
import '../../../../core/session/deconnexion.dart';

/// Écran 25 · Mon profil.
///
/// # Le tiret n'est pas une panne
///
/// Chaque KPI affiche `—` quand la valeur n'a pas été lue. C'est la même règle
/// que la pastille de points du socle : « mieux vaut un tiret honnête qu'un zéro
/// qui ferait croire à un compte vide ». Le solde, en particulier, ne vient
/// **jamais** du cache : hors ligne, il est absent, et il le dit.
class ProfilePage extends ConsumerStatefulWidget {
  const ProfilePage({super.key});

  @override
  ConsumerState<ProfilePage> createState() => _ProfilePageState();
}

class _ProfilePageState extends ConsumerState<ProfilePage> {
  bool _enregistre = false;
  Map<String, String> _erreurs = const <String, String>{};

  @override
  Widget build(BuildContext context) {
    final AsyncValue<EtatProfil> etat = ref.watch(profileProvider);

    return Scaffold(
      backgroundColor: VfColors.canvas,
      appBar: VfAppBar(
        title: 'Mon profil',
        // Le bouton du tiroir, que le simulateur pose ici (§3, ecran 25). Le
        // socle en porte l'hote depuis l'arbitrage n° 137.
        leading: VfShellScope.isInsideShell(context)
            ? VfAppBarLeading.tiroir
            : VfAppBarLeading.aucun,
        pointsPill: VfPointsPill(
          // LA source du socle, alimentee par `GET /me` et `GET /me/wallet`
          // (arbitrage n° 135). `null` tant qu'aucune lecture reseau n'a eu
          // lieu : la pastille affiche alors un tiret, et le socle a ete ecrit
          // pour cela.
          solde: ref.watch(soldeUnitesProvider),
          onTap: () => context.push(VfRoutes.wallet),
        ),
        actions: <Widget>[
          VfIconButton(
            icon: Icons.settings_outlined,
            tooltip: 'Réglages',
            onPressed: () => context.push(VfRoutes.settings),
          ),
        ],
      ),
      body: etat.when(
        loading: () => const _SqueletteProfil(),
        error: (Object erreur, StackTrace _) => VfEmptyState(
          kind: VfEmptyKind.rienEncore,
          icon: Icons.person_outline_rounded,
          title: 'Profil indisponible',
          message: erreur is Failure
              ? (erreur.serverMessage ??
                    'Votre profil n a pas pu être lu pour le moment.')
              : 'Votre profil n a pas pu être lu pour le moment.',
          action: VfGhostButton(
            label: 'Réessayer',
            onPressed: () => ref.read(profileProvider.notifier).rafraichir(),
          ),
        ),
        data: _corps,
      ),
    );
  }

  Widget _corps(EtatProfil etat) {
    return RefreshIndicator(
      onRefresh: () => ref.read(profileProvider.notifier).rafraichir(),
      child: ListView(
        padding: const EdgeInsets.fromLTRB(
          VfSpacing.screenInline,
          VfSpacing.screenTopExtra,
          VfSpacing.screenInline,
          VfSpacing.screenBottomExtra,
        ),
        children: <Widget>[
          VfProfileHeader(moi: etat.moi, niveau: etat.niveau),
          const SizedBox(height: VfSpacing.stack),
          VfKpiGrid(
            children: <Widget>[
              VfKpiCard(
                value: _valeur(etat.verifications),
                label: 'Vérifications',
              ),
              VfKpiCard(value: _valeur(etat.badges), label: 'Badges'),
              VfKpiCard(
                value: _valeur(etat.moi.serieJours),
                label: 'Jours de série',
              ),
              VfKpiCard(
                value: _valeur(etat.solde),
                label: 'Unités',
                accent: VfColors.primaryDark,
              ),
            ],
          ),
          if (etat.vientDuCache) ...<Widget>[
            const SizedBox(height: VfSpacing.stackSm),
            const _BandeauHorsLigne(),
          ],
          const SizedBox(height: VfSpacing.stack),
          VfMenuList(
            children: <Widget>[
              VfNavTile(
                label: 'Mes unités',
                icon: Icons.bolt,
                trailing: Text(_valeur(etat.solde)),
                onTap: () => context.push(VfRoutes.wallet),
              ),
              VfNavTile(
                label: 'Récompenses & certificats',
                icon: Icons.military_tech_outlined,
                trailing: const Icon(Icons.chevron_right_rounded, size: 20),
                onTap: () => context.push(VfRoutes.rewards),
              ),
              VfNavTile(
                label: 'Mes statistiques',
                icon: Icons.insights_outlined,
                trailing: const Icon(Icons.chevron_right_rounded, size: 20),
                onTap: () => context.push(VfRoutes.stats),
              ),
              VfNavTile(
                label: 'Mon parrainage',
                icon: Icons.group_add_outlined,
                trailing: const Icon(Icons.chevron_right_rounded, size: 20),
                onTap: () => context.push(VfRoutes.referrals),
              ),
              // Ecran de M2 : on y va PAR LA NAVIGATION, jamais par un import
              // croise entre deux dossiers `features/`.
              VfNavTile(
                label: 'Mes vérifications',
                icon: Icons.fact_check_outlined,
                trailing: const Icon(Icons.chevron_right_rounded, size: 20),
                onTap: () => context.push(VfRoutes.myVerifications),
              ),
              VfNavTile(
                label: 'Réglages',
                icon: Icons.settings_outlined,
                trailing: const Icon(Icons.chevron_right_rounded, size: 20),
                onTap: () => context.push(VfRoutes.settings),
              ),
            ],
          ),
          const SizedBox(height: VfSpacing.stack),
          VfProfileForm(
            key: ValueKey<String>(etat.moi.uuid),
            moi: etat.moi,
            enCours: _enregistre,
            erreurs: _erreurs,
            onEnregistrer: _enregistrer,
          ),
          const SizedBox(height: VfSpacing.stack),
          VfGhostButton(
            label: 'Se déconnecter',
            icon: Icons.logout_rounded,
            expanded: true,
            onPressed: _deconnecter,
          ),
        ],
      ),
    );
  }

  /// `—` plutôt que `0` : un zéro fabriqué se lit comme une donnée mesurée.
  String _valeur(int? nombre) => nombre?.toString() ?? '—';

  Future<void> _enregistrer(ModificationProfil modification) async {
    if (modification.estVide) {
      VfToast.show(context, message: 'Aucune modification a enregistrer.');
      return;
    }

    setState(() {
      _enregistre = true;
      _erreurs = const <String, String>{};
    });

    try {
      final ResultatEnregistrement resultat = await ref
          .read(profileProvider.notifier)
          .enregistrer(modification);
      if (!mounted) {
        return;
      }
      VfToast.show(
        context,
        message: resultat == ResultatEnregistrement.enregistre
            ? 'Profil enregistre.'
            // « Ne jamais faire croire qu'une ecriture a abouti » : la file
            // n'est pas un succes, c'est une promesse d'envoi.
            : 'Pas de réseau : vos modifications partiront des le retour de '
                  'la connexion.',
      );
    } on ValidationFailure catch (echec) {
      if (!mounted) {
        return;
      }
      // Les valeurs saisies RESTENT a l'ecran : « rien de plus decourageant
      // qu'un formulaire vide par un echec ».
      setState(() {
        _erreurs = <String, String>{
          for (final MapEntry<String, List<String>> e
              in echec.fieldErrors.entries)
            if (e.value.isNotEmpty) e.key: e.value.first,
        };
      });
    } on Failure catch (echec) {
      if (mounted) {
        VfToast.error(
          context,
          echec.serverMessage ?? 'L enregistrement n a pas abouti.',
        );
      }
    } finally {
      if (mounted) {
        setState(() => _enregistre = false);
      }
    }
  }

  /// La déconnexion **appartient à M1** : M5 l'appelle, il ne la réécrit pas.
  Future<void> _deconnecter() async {
    await ref.read(deconnexionProvider)();
    if (mounted) {
      // La garde du routeur ramene deja vers `/connexion` quand la session se
      // ferme ; l'appel explicite evite d'attendre un cycle de rafraichissement
      // sur un ecran qui n'a plus de donnees a montrer.
      context.go(VfRoutes.login);
    }
  }
}

class _BandeauHorsLigne extends StatelessWidget {
  const _BandeauHorsLigne();

  @override
  Widget build(BuildContext context) {
    return VfCard(
      tight: true,
      background: VfColors.canvas,
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          const Icon(Icons.cloud_off_rounded, size: 18, color: VfColors.slate2),
          const SizedBox(width: VfSpacing.x8),
          const Expanded(
            child: Text(
              'Profil affiche depuis votre telephone. Votre solde d unités '
              'n est pas montre : il ne se lit jamais hors ligne, pour ne '
              'jamais afficher un chiffre perime.',
            ),
          ),
        ],
      ),
    );
  }
}

class _SqueletteProfil extends StatelessWidget {
  const _SqueletteProfil();

  @override
  Widget build(BuildContext context) {
    return ListView(
      padding: const EdgeInsets.all(VfSpacing.screenInline),
      children: const <Widget>[
        VfSkeleton(height: 168, borderRadius: VfRadii.lgAll),
        SizedBox(height: VfSpacing.stack),
        VfSkeleton(height: 76, borderRadius: VfRadii.lgAll),
        SizedBox(height: VfSpacing.stack),
        VfSkeleton(height: 312, borderRadius: VfRadii.lgAll),
      ],
    );
  }
}
