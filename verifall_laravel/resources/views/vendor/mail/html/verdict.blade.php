@props([
    'verdict',
    'confiance' => null,
])
@php
    /*
     | Badge de verdict, pour les courriels.
     |
     | Le MOT porte le verdict, la couleur ne fait que l'accompagner : un
     | destinataire daltonien, un client qui refuse le CSS ou un texte
     | recopié à la main doivent lire « FAUX » sans rien deviner.
     |
     | Couleur et libellé viennent de l'enum — `couleurBarre()` est l'échelle
     | déjà employée par la barre de fiabilité et la carte de démenti : un
     | même verdict a la même teinte partout. Aucune correspondance locale.
     |
     | PAS de classe modificatrice par verdict, et c'est délibéré. Une
     | `vf-badge--faux` qui ne correspond à rien dans le thème invite le
     | prochain lecteur à « réparer » l'oubli en y déclarant les couleurs —
     | recréant la seconde source que tout ce dispositif écarte. La teinte
     | vient de l'enum, en ligne, et de nulle part ailleurs.
     | `SocleDesCourrielsTest` garde cette règle.
     |
     | Les styles sont doublés en ligne, aux valeurs du thème : `CssToInlineStyles`
     | respecte un attribut `style` déjà présent, et un client qui n'applique pas
     | le thème garde ainsi un badge lisible. Le badge est une CELLULE et non un
     | `span` : Outlook ignore le rembourrage des éléments en ligne.
     */
    $verdict = $verdict instanceof \App\Enums\VerdictFactCheck
        ? $verdict
        : \App\Enums\VerdictFactCheck::from((string) $verdict);

    $couleur = $verdict->couleurBarre();

    // Un `unsignedTinyInteger` en base ; on borne quand même, un badge
    // à « 140 % » serait un mensonge typographié.
    $confiance = $confiance === null ? null : max(0, min(100, (int) $confiance));

    $police = 'font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif;';
@endphp
<table class="vf-verdict" cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse: collapse; margin: 0 0 20px 0;">
<tr>
<td class="vf-badge" bgcolor="{{ $couleur }}" valign="middle" style="background-color: {{ $couleur }}; border-radius: 999px; color: #ffffff; {{ $police }} font-size: 13px; font-weight: bold; letter-spacing: 0.6px; line-height: 18px; mso-line-height-rule: exactly; padding: 7px 18px; text-transform: uppercase; white-space: nowrap;">{{ $verdict->libelle() }}</td>
@if ($confiance !== null)<td class="vf-meta" valign="middle" style="color: #8a8d8b; {{ $police }} font-size: 12px; line-height: 18px; margin: 0; padding: 0 0 0 12px; white-space: nowrap;">{{ __('cit_rapport.confiance') }}&nbsp;: {{ $confiance }}&nbsp;%</td>@endif
</tr>
</table>
