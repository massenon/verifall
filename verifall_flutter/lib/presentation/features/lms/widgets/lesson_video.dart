import 'package:chewie/chewie.dart';
import 'package:flutter/material.dart';
import 'package:video_player/video_player.dart';

import '../../../../core/theme/vf_colors.dart';
import '../../../../core/theme/vf_typography.dart';
import 'course_card.dart';

/// Le lecteur vidéo d'une leçon.
///
/// # Deux états, et le second n'existe pas toujours
///
/// Le simulateur montre un bloc en dégradé avec `play_circle` et la ligne
/// « Vidéo de la leçon · `<n>` min ». En production, « c'est `chewie` ».
///
/// Les deux se conjuguent ici : le bloc du simulateur est **l'affiche**, et le
/// lecteur ne s'initialise qu'au toucher. POURQUOI cette paresse plutôt qu'un
/// lecteur monté d'emblée : initialiser un `VideoPlayerController` ouvre une
/// connexion et commence à télécharger. Sur un forfait facturé au mégaoctet,
/// une vidéo qui se charge parce qu'on a ouvert la page est une facture que
/// personne n'a demandée.
///
/// # Hors ligne
///
/// **La vidéo n'est pas téléchargée** avec le cours : le contrat ne rend qu'une
/// `video_url`, sans poids ni durée de validité, et on ne peut donc pas en
/// annoncer la taille avant de la rapatrier. Le bloc dit alors franchement
/// qu'elle demande une connexion — plutôt qu'un lecteur qui tourne dans le vide.
class LessonVideo extends StatefulWidget {
  const LessonVideo({
    required this.videoUrl,
    required this.minutes,
    required this.cleDeDegrade,
    super.key,
  });

  /// `null` quand la leçon n'a pas de vidéo : le bloc ne s'affiche alors pas du
  /// tout, plutôt qu'un lecteur vide.
  final String? videoUrl;

  final int minutes;
  final String cleDeDegrade;

  @override
  State<LessonVideo> createState() => _LessonVideoState();
}

class _LessonVideoState extends State<LessonVideo> {
  VideoPlayerController? _controleur;
  ChewieController? _chewie;
  bool _demarrage = false;

  @override
  void dispose() {
    _chewie?.dispose();
    _controleur?.dispose();
    super.dispose();
  }

  Future<void> _demarrer() async {
    final String? url = widget.videoUrl;
    if (url == null || url.isEmpty || _demarrage) {
      return;
    }
    setState(() => _demarrage = true);
    final VideoPlayerController controleur = VideoPlayerController.networkUrl(
      Uri.parse(url),
    );
    try {
      await controleur.initialize();
    } on Object {
      // Un echec de lecture ne casse pas la lecon : le corps du texte reste
      // lisible, et c'est lui qui porte le contenu.
      await controleur.dispose();
      if (mounted) {
        setState(() => _demarrage = false);
      }
      return;
    }
    if (!mounted) {
      await controleur.dispose();
      return;
    }
    setState(() {
      _controleur = controleur;
      _chewie = ChewieController(
        videoPlayerController: controleur,
        autoPlay: true,
        looping: false,
      );
    });
  }

  @override
  Widget build(BuildContext context) {
    final String? url = widget.videoUrl;
    if (url == null || url.isEmpty) {
      return const SizedBox.shrink();
    }

    final ChewieController? chewie = _chewie;
    if (chewie != null) {
      return AspectRatio(
        aspectRatio: chewie.videoPlayerController.value.aspectRatio,
        child: Chewie(controller: chewie),
      );
    }

    return Semantics(
      button: true,
      label: 'Lire la vidéo de la leçon, ${widget.minutes} minutes',
      excludeSemantics: true,
      child: InkWell(
        onTap: _demarrage ? null : _demarrer,
        child: CoverAvecLibelle(
          cleDeDegrade: widget.cleDeDegrade,
          hauteurSurchargee: 150,
          enfant: AfficheDeVideo(minutes: widget.minutes),
        ),
      ),
    );
  }
}

/// L'affiche du simulateur : icône `play_circle` et la ligne de durée.
///
/// POURQUOI elle est séparée : le test de widget de la leçon doit pouvoir la
/// vérifier sans jamais instancier un lecteur vidéo.
class AfficheDeVideo extends StatelessWidget {
  const AfficheDeVideo({required this.minutes, super.key});

  final int minutes;

  @override
  Widget build(BuildContext context) {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: <Widget>[
        const Icon(
          Icons.play_circle_rounded,
          size: 38,
          color: VfColors.surface,
        ),
        const SizedBox(height: 6),
        Text(
          'Vidéo de la leçon · $minutes min',
          style: VfTextStyles.tXs.copyWith(color: VfColors.surface),
        ),
      ],
    );
  }
}
