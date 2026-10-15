@props([
    'titre' => null,
])
@php
    /*
     | Avertissement : même ossature que la carte de preuve, filet ROUGE et
     | fond rosé — les valeurs de `.vf-card--alerte` dans le thème.
     |
     | Une alerte n'est pas un verdict : le rouge est celui de la charte
     | courriel (`#D6293E`, déjà celui de `.button-error`), et non l'échelle
     | de `VerdictFactCheck::couleurBarre()`.
     |
     | Le titre est le porteur du sens — la couleur seule ne suffit pas. Un
     | appelant devrait toujours en fournir un ; sans titre la carte reste
     | valide, mais elle ne dit « attention » que par sa teinte.
     |
     | Slot : même traitement Markdown que `<x-mail::panel>`, voir la carte de
     | preuve pour le détail et la raison des marges posées en ligne.
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
<td class="vf-card vf-card--alerte" bgcolor="#fdf5f6" style="background-color: #fdf5f6; border: 1px solid #e9ecef; border-left: 4px solid #D6293E; border-radius: 10px; padding: 18px 20px;">
@if ($titre !== '')<p class="vf-card-titre" style="color: #202124; {{ $police }} font-size: 16px; font-weight: bold; line-height: 22px; margin: 0 0 {{ $corps === '' ? '0' : '6px' }} 0; text-align: left;">{{ $titre }}</p>@endif
{{ new \Illuminate\Support\HtmlString($corps) }}
</td>
</tr>
</table>
