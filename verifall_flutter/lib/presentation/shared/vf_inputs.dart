import 'dart:async';

import 'package:flutter/material.dart';
import 'package:flutter/services.dart';

import '../../core/theme/vf_colors.dart';
import '../../core/theme/vf_metrics.dart';
import '../../core/theme/vf_typography.dart';

/// `.field` — le champ de saisie.
///
/// # L'étiquette flottante
///
/// Posée **dans** le champ, elle remonte et rétrécit (0.76) au focus ou dès
/// qu'il y a du texte. Bordure primaire + halo 3 px à 12 % au focus (§2.7).
///
/// POURQUOI le halo est dessiné à la main : `InputDecoration` n'a pas de notion
/// de halo extérieur. Un `BoxShadow` sans flou de 3 px reproduit exactement le
/// `box-shadow: 0 0 0 3px rgba(9,184,80,.12)` du CSS.
///
/// # Les erreurs
///
/// [errorText] reçoit **le message du serveur**, pris dans
/// `ValidationFailure.details[champ]`. Le contrat garantit qu'il est déjà rédigé
/// en français et affichable tel quel : « le client ne traduit pas, il montre ce
/// qu'on lui donne » (§2.1).
class VfTextField extends StatefulWidget {
  const VfTextField({
    required this.label,
    this.controller,
    this.initialValue,
    this.onChanged,
    this.errorText,
    this.helperText,
    this.keyboardType,
    this.obscureText = false,
    this.autofillHints,
    this.textInputAction,
    this.enabled = true,
    this.maxLength,
    this.prefixIcon,
    this.suffix,
    super.key,
  });

  final String label;
  final TextEditingController? controller;
  final String? initialValue;
  final ValueChanged<String>? onChanged;
  final String? errorText;
  final String? helperText;
  final TextInputType? keyboardType;
  final bool obscureText;
  final Iterable<String>? autofillHints;
  final TextInputAction? textInputAction;
  final bool enabled;
  final int? maxLength;
  final IconData? prefixIcon;
  final Widget? suffix;

  @override
  State<VfTextField> createState() => _VfTextFieldState();
}

class _VfTextFieldState extends State<VfTextField> {
  final FocusNode _focusNode = FocusNode();
  bool _focused = false;

  @override
  void initState() {
    super.initState();
    _focusNode.addListener(() {
      if (_focused != _focusNode.hasFocus) {
        setState(() => _focused = _focusNode.hasFocus);
      }
    });
  }

  @override
  void dispose() {
    _focusNode.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final bool hasError =
        widget.errorText != null && widget.errorText!.isNotEmpty;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: <Widget>[
        DecoratedBox(
          decoration: BoxDecoration(
            borderRadius: VfRadii.mdAll,
            boxShadow: _focused && !hasError
                ? <BoxShadow>[
                    BoxShadow(
                      color: VfColors.primary.withValues(alpha: 0.12),
                      spreadRadius: 3,
                    ),
                  ]
                : null,
          ),
          child: TextFormField(
            focusNode: _focusNode,
            controller: widget.controller,
            initialValue: widget.controller == null
                ? widget.initialValue
                : null,
            onChanged: widget.onChanged,
            keyboardType: widget.keyboardType,
            obscureText: widget.obscureText,
            autofillHints: widget.autofillHints,
            textInputAction: widget.textInputAction,
            enabled: widget.enabled,
            maxLength: widget.maxLength,
            style: VfTextStyles.body,
            decoration: InputDecoration(
              labelText: widget.label,
              prefixIcon: widget.prefixIcon == null
                  ? null
                  : Icon(widget.prefixIcon),
              suffixIcon: widget.suffix,
              // Le compteur natif de Flutter est masque : le simulateur en pose
              // un aligne a droite sous le champ (voir VfTextArea).
              counterText: '',
              errorText: hasError ? widget.errorText : null,
              helperText: widget.helperText,
            ),
          ),
        ),
      ],
    );
  }
}

/// `.field textarea` — hauteur minimale 120, non redimensionnable, compteur de
/// caractères aligné à droite.
class VfTextArea extends StatelessWidget {
  const VfTextArea({
    required this.label,
    required this.value,
    required this.onChanged,
    this.controller,
    this.maxLength,
    this.errorText,
    this.minLines = 5,
    super.key,
  });

  final String label;
  final String value;
  final ValueChanged<String> onChanged;
  final TextEditingController? controller;
  final int? maxLength;
  final String? errorText;
  final int minLines;

  @override
  Widget build(BuildContext context) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.stretch,
      children: <Widget>[
        ConstrainedBox(
          constraints: const BoxConstraints(minHeight: 120),
          child: TextFormField(
            controller: controller,
            initialValue: controller == null ? value : null,
            onChanged: onChanged,
            minLines: minLines,
            maxLines: null,
            maxLength: maxLength,
            style: VfTextStyles.body,
            decoration: InputDecoration(
              labelText: label,
              alignLabelWithHint: true,
              counterText: '',
              errorText: errorText,
            ),
          ),
        ),
        if (maxLength != null)
          Padding(
            padding: const EdgeInsets.only(top: VfSpacing.x4),
            child: Text(
              '${value.characters.length} / $maxLength',
              textAlign: TextAlign.right,
              style: VfTextStyles.tXs,
              // Le compteur est une information de progression, pas un element
              // que le lecteur d'ecran doit annoncer a chaque frappe.
              semanticsLabel: '',
            ),
          ),
      ],
    );
  }
}

/// `.field input[type=search]` — débounce **350 ms**, valeur identique au web.
///
/// POURQUOI la même valeur que le web : une recherche qui réagit plus vite sur
/// mobile déclenche plus d'appels, et le plafond de lecture est partagé.
class VfSearchField extends StatefulWidget {
  const VfSearchField({
    required this.onSearch,
    this.hint = 'Rechercher',
    this.initialValue,
    this.debounce = const Duration(milliseconds: 350),
    super.key,
  });

  final ValueChanged<String> onSearch;
  final String hint;
  final String? initialValue;
  final Duration debounce;

  @override
  State<VfSearchField> createState() => _VfSearchFieldState();
}

class _VfSearchFieldState extends State<VfSearchField> {
  Timer? _timer;

  @override
  void dispose() {
    _timer?.cancel();
    super.dispose();
  }

  void _onChanged(String value) {
    _timer?.cancel();
    _timer = Timer(widget.debounce, () => widget.onSearch(value));
  }

  @override
  Widget build(BuildContext context) {
    return TextFormField(
      initialValue: widget.initialValue,
      onChanged: _onChanged,
      textInputAction: TextInputAction.search,
      onFieldSubmitted: (String value) {
        _timer?.cancel();
        widget.onSearch(value);
      },
      style: VfTextStyles.body,
      decoration: InputDecoration(
        hintText: widget.hint,
        prefixIcon: const Icon(Icons.search_rounded, color: VfColors.slate2),
      ),
    );
  }
}

/// `.otp-box` — six cases de 46 x 56.
///
/// # Trois comportements qui font toute la différence
///
/// - passage automatique à la case suivante ;
/// - retour arrière vers la précédente ;
/// - **collage d'un code complet réparti sur les six** — c'est le geste réel :
///   on copie le code depuis l'application de messagerie.
///
/// `autofillHints: oneTimeCode` laisse le système proposer le code reçu par SMS
/// ou par courriel.
class VfOtpField extends StatefulWidget {
  const VfOtpField({
    required this.onCompleted,
    this.length = 6,
    this.onChanged,
    this.hasError = false,
    super.key,
  });

  final int length;
  final ValueChanged<String> onCompleted;
  final ValueChanged<String>? onChanged;
  final bool hasError;

  @override
  State<VfOtpField> createState() => _VfOtpFieldState();
}

class _VfOtpFieldState extends State<VfOtpField> {
  late final List<TextEditingController> _controllers;
  late final List<FocusNode> _nodes;

  @override
  void initState() {
    super.initState();
    _controllers = List<TextEditingController>.generate(
      widget.length,
      (_) => TextEditingController(),
    );
    _nodes = List<FocusNode>.generate(widget.length, (_) => FocusNode());
  }

  @override
  void dispose() {
    for (final TextEditingController c in _controllers) {
      c.dispose();
    }
    for (final FocusNode n in _nodes) {
      n.dispose();
    }
    super.dispose();
  }

  String get _code =>
      _controllers.map((TextEditingController c) => c.text).join();

  void _handle(int index, String value) {
    // Collage d'un code complet : on le repartit sur les cases plutot que de
    // n'en garder qu'un caractere.
    if (value.length > 1) {
      final String digits = value.replaceAll(RegExp(r'\D'), '');
      for (int i = 0; i < widget.length; i++) {
        _controllers[i].text = i < digits.length ? digits[i] : '';
      }
      _nodes[(digits.length - 1).clamp(0, widget.length - 1)].requestFocus();
    } else if (value.isNotEmpty && index < widget.length - 1) {
      _nodes[index + 1].requestFocus();
    } else if (value.isEmpty && index > 0) {
      _nodes[index - 1].requestFocus();
    }

    final String code = _code;
    widget.onChanged?.call(code);
    if (code.length == widget.length) {
      widget.onCompleted(code);
    }
    setState(() {});
  }

  @override
  Widget build(BuildContext context) {
    return Semantics(
      label: 'Code de vérification a ${widget.length} chiffres',
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: <Widget>[
          for (int i = 0; i < widget.length; i++)
            SizedBox(
              width: VfTouch.otpBoxWidth,
              height: VfTouch.otpBoxHeight,
              child: TextField(
                controller: _controllers[i],
                focusNode: _nodes[i],
                onChanged: (String value) => _handle(i, value),
                keyboardType: TextInputType.number,
                textAlign: TextAlign.center,
                autofillHints: const <String>[AutofillHints.oneTimeCode],
                inputFormatters: <TextInputFormatter>[
                  FilteringTextInputFormatter.digitsOnly,
                ],
                style: VfTextStyles.hMd,
                decoration: InputDecoration(
                  counterText: '',
                  contentPadding: EdgeInsets.zero,
                  enabledBorder: OutlineInputBorder(
                    borderRadius: VfRadii.mdAll,
                    borderSide: BorderSide(
                      color: widget.hasError
                          ? VfColors.brandRed
                          : VfColors.line,
                    ),
                  ),
                ),
              ),
            ),
        ],
      ),
    );
  }
}

/// `.switch` + `.mrow` — une ligne de réglage avec son interrupteur.
///
/// POURQUOI le rôle d'accessibilité `switch` est explicite : un `Row` avec un
/// `Switch` dedans est annoncé comme deux éléments distincts ; l'étiquette et
/// l'état doivent arriver ensemble.
///
/// POURQUOI la valeur envoyée est une **valeur voulue** et non une bascule :
/// « une bascule perd sa cible quand la requête est rejouée après une coupure, et
/// l'utilisateur retrouve le réglage inverse de ce qu'il a demandé » (§10.2 du
/// contrat). D'où [onChanged] qui reçoit la valeur, jamais un `onToggle()`.
class VfSwitchTile extends StatelessWidget {
  const VfSwitchTile({
    required this.label,
    required this.value,
    required this.onChanged,
    this.description,
    super.key,
  });

  final String label;
  final String? description;
  final bool value;
  final ValueChanged<bool>? onChanged;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      toggled: value,
      label: label,
      excludeSemantics: true,
      child: InkWell(
        onTap: onChanged == null ? null : () => onChanged!(!value),
        child: Container(
          constraints: const BoxConstraints(
            minHeight: VfTouch.menuRowMinHeight,
          ),
          padding: const EdgeInsets.symmetric(
            horizontal: VfTouch.menuRowInlinePadding,
            vertical: VfTouch.menuRowVerticalPadding,
          ),
          child: Row(
            children: <Widget>[
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: <Widget>[
                    Text(label, style: VfTextStyles.body),
                    if (description != null)
                      Text(description!, style: VfTextStyles.tSm),
                  ],
                ),
              ),
              const SizedBox(width: VfSpacing.x12),
              Switch.adaptive(
                value: value,
                onChanged: onChanged,
                activeThumbColor: VfColors.surface,
                activeTrackColor: VfColors.primary,
              ),
            ],
          ),
        ),
      ),
    );
  }
}

/// `.consent` — case carrée de 22, rayon 6.
///
/// **Jamais pré-cochée.** Ce n'est pas une préférence d'interface : un
/// consentement RGPD pré-coché n'est pas un consentement. Le composant n'a donc
/// aucune valeur par défaut à `true`, et il n'y a pas de paramètre pour en
/// fabriquer une.
class VfConsentTile extends StatelessWidget {
  const VfConsentTile({
    required this.value,
    required this.onChanged,
    required this.child,
    this.required = false,
    this.errorText,
    super.key,
  });

  final bool value;
  final ValueChanged<bool> onChanged;

  /// Le libellé, sous forme de widget : il porte souvent un lien vers les CGU ou
  /// la politique de confidentialité.
  final Widget child;

  /// Les consentements obligatoires bloquent l'envoi. C'est l'écran qui applique
  /// la règle ; ce drapeau ne sert qu'à l'annoncer au lecteur d'écran.
  final bool required;

  final String? errorText;

  @override
  Widget build(BuildContext context) {
    return Semantics(
      checked: value,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: <Widget>[
          InkWell(
            onTap: () => onChanged(!value),
            child: Padding(
              padding: const EdgeInsets.symmetric(vertical: VfSpacing.x8),
              child: Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: <Widget>[
                  Container(
                    width: VfTouch.consentBox,
                    height: VfTouch.consentBox,
                    alignment: Alignment.center,
                    decoration: BoxDecoration(
                      color: value ? VfColors.primary : VfColors.surface,
                      borderRadius: BorderRadius.circular(
                        VfTouch.consentBoxRadius,
                      ),
                      border: Border.all(
                        color: errorText != null
                            ? VfColors.brandRed
                            : value
                            ? VfColors.primary
                            : VfColors.line,
                      ),
                    ),
                    child: value
                        ? const Icon(
                            Icons.check_rounded,
                            size: 16,
                            color: VfColors.surface,
                          )
                        : null,
                  ),
                  const SizedBox(width: VfSpacing.x12),
                  Expanded(child: child),
                ],
              ),
            ),
          ),
          if (errorText != null)
            Text(
              errorText!,
              style: VfTextStyles.tSm.copyWith(color: VfColors.brandRed),
            ),
        ],
      ),
    );
  }
}
