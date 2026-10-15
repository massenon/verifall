@props([
    'titre' => null,
])
@php
    /*
     | « Evidence Card » : une information importante, encadrée.
     |
     | Un tableau et non un `div` bordé : Outlook rend le HTML avec le moteur
     | de Word, et le filet vert à gauche est une bordure de CELLULE, la seule
     | qui y tienne — les classes `.vf-card*` sont donc posées sur le `td`, où
     | rembourrage et bordure s'appliquent partout. Pas d'image : la carte est
     | complète en texte seul.
     |
     | Le slot passe par le même analyseur Markdown que `<x-mail::panel>`, pour
     | que « **gras** » et les listes y marchent comme dans le reste du message.
     | Les paragraphes engendrés n'ont pas de style et le thème ne peut pas
     | viser `:last-of-type` (non inliné) : on leur pose ici la classe et la
     | marge, le dernier à zéro. Le contenu est celui de l'auteur du gabarit ;
     | une donnée d'utilisateur y arrive déjà échappée par les doubles
     | accolades de l'appelant.
     */
    $titre = trim((string) $titre);
    $texte = trim((string) $slot);

    $police = 'font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif;';
    $paragraphe = '<p class="vf-card-texte" style="color: #606261; '.$police.' font-size: 14px; line-height: 21px; margin: 0 0 10px 0; text-align: left;">';

    $corps = $texte === ''
        ? ''
        : str_replace('<p>', $paragraphe, \Illuminate\Mail\Markdown::parse($texte)->toHtml());

    $dernier = strrpos($corps, 'margin: 0 0 10px 0;');
    if ($dernier !== false) {
        $corps = substr_replace($corps, 'margin: 0;', $dernier, strlen('margin: 0 0 10px 0;'));
    }
@endphp
<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse: separate; margin: 0 0 16px 0; width: 100%;">
<tr>
<td class="vf-card vf-card--preuve" bgcolor="#ffffff" style="background-color: #ffffff; border: 1px solid #e9ecef; border-left: 4px solid #09B850; border-radius: 10px; padding: 18px 20px;">
@if ($titre !== '')<p class="vf-card-titre" style="color: #202124; {{ $police }} font-size: 16px; font-weight: bold; line-height: 22px; margin: 0 0 {{ $corps === '' ? '0' : '6px' }} 0; text-align: left;">{{ $titre }}</p>@endif
{{ new \Illuminate\Support\HtmlString($corps) }}
</td>
</tr>
</table>
