import 'package:flutter/material.dart';
import 'package:flutter_html/flutter_html.dart';

import '../../core/theme/vf_colors.dart';
import '../../core/theme/vf_typography.dart';

/// `.prose` — le rendu du contenu long.
///
/// # Pourquoi une liste blanche et jamais du HTML brut
///
/// Les champs `corps`, `contenu` et `description` peuvent contenir du HTML issu
/// de Quill, **purifié avant persistance** côté serveur (§8 du contrat). Le
/// client le rend malgré tout sur une **liste blanche de balises** :
///
/// > « la purification à l'écriture protège de ce qui est en base, pas de ce qui
/// > y entrerait un jour par une autre porte. »
///
/// C'est la transposition mobile de la règle « jamais `{!! !!}` sur données
/// utilisateur ».
///
/// # Ce que la liste blanche exclut, et pourquoi
///
/// `script`, `iframe`, `object`, `embed`, `form`, `style` et les attributs
/// d'événement. Un `iframe` autorisé dans un article du Hub, c'est une page
/// tierce qui s'exécute dans le contexte de l'application.
class VfProseBody extends StatelessWidget {
  const VfProseBody({required this.html, this.onLinkTap, super.key});

  final String html;

  /// L'ouverture d'un lien externe. **En onglet système** (Custom Tabs /
  /// SFSafariViewController), jamais en WebView maison : « moins de surface
  /// d'attaque, session isolée, et le citoyen voit le vrai domaine dans la barre
  /// d'adresse avant de saisir quoi que ce soit » (§10.5 du contrat).
  final void Function(String url)? onLinkTap;

  /// Les balises rendues. Tout le reste est ignoré.
  static const Set<String> allowedTags = <String>{
    'p',
    'br',
    'strong',
    'b',
    'em',
    'i',
    'u',
    's',
    'ul',
    'ol',
    'li',
    'h1',
    'h2',
    'h3',
    'h4',
    'blockquote',
    'code',
    'pre',
    'a',
    'body',
    'html',
  };

  @override
  Widget build(BuildContext context) {
    return Html(
      data: html,
      onlyRenderTheseTags: allowedTags,
      onLinkTap:
          (String? url, Map<String, String> attributes, dynamic element) {
            if (url != null && onLinkTap != null) {
              onLinkTap!(url);
            }
          },
      style: <String, Style>{
        'body': Style(
          margin: Margins.zero,
          padding: HtmlPaddings.zero,
          fontFamily: VfFonts.body,
          fontSize: FontSize(14.5),
          lineHeight: LineHeight(1.65),
          color: VfColors.proseInk,
        ),
        'h1': _heading(20),
        'h2': _heading(18),
        'h3': _heading(16),
        'h4': _heading(15),
        'a': Style(
          color: VfColors.primaryDark,
          textDecoration: TextDecoration.underline,
        ),
        'blockquote': Style(
          border: const Border(
            left: BorderSide(color: VfColors.line, width: 3),
          ),
          padding: HtmlPaddings.only(left: 12),
          margin: Margins.symmetric(vertical: 12),
          color: VfColors.slate2,
        ),
        'code': Style(
          fontFamily: VfFonts.mono,
          fontSize: FontSize(13),
          backgroundColor: VfColors.canvas,
        ),
      },
    );
  }

  static Style _heading(double size) => Style(
    fontFamily: VfFonts.display,
    fontSize: FontSize(size),
    fontWeight: FontWeight.w700,
    color: VfColors.ink,
    margin: Margins.only(top: 16, bottom: 8),
  );
}
