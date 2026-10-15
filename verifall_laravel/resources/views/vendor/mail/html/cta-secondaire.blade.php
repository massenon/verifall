@props([
    'url' => null,
    'align' => 'center',
    'compact' => false,
])
@php
    /*
     | Bouton d'action SECONDAIRE : même squelette de tableaux que `button`,
     | mais en contour vert sur fond blanc, texte forest — jamais aussi dominant
     | que le bouton principal plein.
     |
     | Le contour, le rayon et le remplissage sont portés par la CELLULE, pas
     | par le lien : Outlook ignore le `padding` d'un `<a>` mais respecte celui
     | d'un `<td>`. Hauteur : 20 px de ligne + 2 × 12 px + 2 × 2 px = 48 px.
     |
     | `compact` retire la marge verticale — pour l'emploi à l'intérieur d'une
     | carte, où c'est la cellule parente qui espace.
     */
    $url = trim((string) ($url ?? ''));
    $align = in_array($align, ['left', 'center', 'right'], true) ? $align : 'center';
    $marge = $compact ? '0' : '24px auto';
    $police = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
@endphp
@if ($url !== '')
<table class="action" align="{{ $align }}" width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="width: 100%; margin: {{ $marge }}; padding: 0; text-align: {{ $align }};">
<tr>
<td align="{{ $align }}" style="padding: 0;">
<table border="0" cellpadding="0" cellspacing="0" role="presentation" style="border-collapse: separate;">
<tr>
<td class="vf-cta-secondaire" align="center" bgcolor="#ffffff" style="background-color: #ffffff; border: 2px solid #09B850; border-radius: 10px; padding: 12px 24px; font-family: {{ $police }}; font-size: 15px; line-height: 20px; font-weight: 700; color: #063B2A;">
<a href="{{ $url }}" target="_blank" rel="noopener" style="display: block; color: #063B2A; text-decoration: none; font-family: {{ $police }}; font-size: 15px; line-height: 20px; font-weight: 700;">{{ $slot->isEmpty() ? 'En savoir plus' : $slot }}</a>
</td>
</tr>
</table>
</td>
</tr>
</table>
@endif
