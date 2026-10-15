import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

/// **Le coût n'est jamais envoyé, et il n'est jamais écrit non plus.**
///
/// La définition de fini de M2 le demande noir sur blanc :
///
/// > « Le coût n'est jamais envoyé : **aucun barème d'unités dans le code
/// > client**. Un test le vérifie **par l'absence de constante**. Le solde
/// > affiché vient de `meta.solde`, jamais d'une soustraction locale. »
///
/// Ce test lit les sources du parcours et cherche ce qui ne doit pas s'y
/// trouver. C'est un test inhabituel, et c'est assumé : le barème du simulateur
/// (1/1/2/5/5) est **exactement** le genre de valeur qu'on recopie « juste pour
/// afficher quelque chose », et qui reste ensuite trois ans. Une plateforme qui
/// annonce 2 unités puis en débite 5 a menti, même sans le vouloir.
void main() {
  late List<({String chemin, String contenu})> sources;

  setUpAll(() {
    sources = <({String chemin, String contenu})>[
      for (final String dossier in <String>[
        'lib/domain/entities',
        'lib/domain/usecases/verify',
        'lib/domain/usecases/home',
        'lib/data/datasources/remote/verify',
        'lib/data/datasources/local/verify',
        'lib/data/repositories/verify',
        'lib/presentation/features/verify',
        'lib/presentation/features/home',
      ])
        for (final FileSystemEntity f in Directory(
          dossier,
        ).listSync(recursive: true))
          if (f is File && f.path.endsWith('.dart'))
            (chemin: f.path, contenu: f.readAsStringSync()),
    ];
    expect(sources, isNotEmpty, reason: 'les sources doivent être lisibles');
  });

  test('aucun bareme de cout par type de contenu', () {
    // Le bareme du simulateur, sous les formes ou on le recopierait : une carte
    // type -> cout, ou une constante nommee.
    final List<RegExp> interdits = <RegExp>[
      RegExp(r'const\s+\w*[Cc]out\w*\s*=\s*\d'),
      RegExp(r'\bCOUTS?\b\s*=\s*<'),
      RegExp('bareme', caseSensitive: false),
      RegExp(r"'\d+ pts?'"),
    ];

    for (final ({String chemin, String contenu}) source in sources) {
      for (final RegExp interdit in interdits) {
        // Les commentaires expliquent POURQUOI le bareme est absent : ils ont le
        // droit de le nommer. On ne regarde donc que le code.
        final String code = _sansCommentaires(source.contenu);
        expect(
          interdit.hasMatch(code),
          isFalse,
          reason:
              'Un bareme d unités semble ecrit dans ${source.chemin} : le cout '
              'est lu du type PAR LE SERVEUR, et aucun endpoint ne le publie.',
        );
      }
    }
  });

  test('aucune soustraction locale du solde', () {
    // « Le client ne debite ni ne credite jamais d'unite. Le solde est un fait
    // serveur. » Une soustraction sur un solde serait le debit local interdit.
    final RegExp soustraction = RegExp(r'solde\w*\s*-\s*\w');
    for (final ({String chemin, String contenu}) source in sources) {
      expect(
        soustraction.hasMatch(_sansCommentaires(source.contenu)),
        isFalse,
        reason:
            'Une soustraction sur le solde apparaît dans ${source.chemin} : le '
            'solde vient de meta.solde, recalcule par le serveur.',
      );
    }
  });

  test('aucun appel aux cinq endpoints que l API ne sert pas', () {
    // Les ecrire « en attendant » produirait un client qui appelle du vide.
    // C'est pourquoi ils ne figurent pas non plus dans `ApiEndpoints`.
    final List<String> absents = <String>[
      '/me/notifications',
      '/feedback',
      '/pdf',
    ];
    for (final ({String chemin, String contenu}) source in sources) {
      final String code = _sansCommentaires(source.contenu);
      for (final String absent in absents) {
        expect(
          code.contains("'$absent"),
          isFalse,
          reason:
              '${source.chemin} semble appeler $absent, qui n est servi par '
              'aucun endpoint (contrat, section 11).',
        );
      }
    }
  });
}

/// Retire les commentaires : ils **doivent** pouvoir nommer ce qu'ils
/// interdisent, sinon on ne peut plus expliquer pourquoi une chose est absente.
String _sansCommentaires(String source) {
  final StringBuffer sortie = StringBuffer();
  for (final String ligne in source.split('\n')) {
    final String nette = ligne.trimLeft();
    if (nette.startsWith('//') || nette.startsWith('///')) {
      continue;
    }
    sortie.writeln(ligne);
  }
  return sortie.toString()
  // Les blocs /* ... */ ne sont pas employes dans ce parcours ; on les
  // retire quand meme, pour que le test reste vrai si quelqu'un en ajoute.
  .replaceAll(RegExp(r'/\*.*?\*/', dotAll: true), '');
}
