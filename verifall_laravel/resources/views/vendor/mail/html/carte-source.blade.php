@props([
    'nom' => null,
    'domaine' => null,
    'url' => null,
])
@php
    /*
     | Une source citée : nom, domaine, lien.
     |
     | L'adresse est ÉCRITE en clair sous le nom, pas seulement cachée derrière
     | lui : un client qui neutralise les liens la garde lisible, et un lecteur
     | méfiant — c'est le public d'un service de fact-checking — voit où il va
     | avant de cliquer.
     |
     | Seul un lien `http(s)` devient cliquable. Le domaine, s'il manque, est
     | déduit de l'adresse ; le nom, s'il manque, se replie sur le domaine.
     | Styles doublés en ligne aux valeurs du thème (`.vf-card--source`,
     | `.vf-card-titre`, `.vf-meta`), classes posées sur le `td` — voir la
     | carte de preuve.
     */
    $url = trim((string) $url);
    $cliquable = preg_match('#^https?://#i', $url) === 1;

    $domaine = trim((string) $domaine);
    if ($domaine === '' && $cliquable) {
        $domaine = (string) (parse_url($url, PHP_URL_HOST) ?: '');
    }
    $domaine = (string) preg_replace('/^www\./i', '', $domaine);

    $nom = trim((string) $nom);
    if ($nom === '') {
        $nom = $domaine !== '' ? $domaine : $url;
    }

    $meta = $domaine !== '' || $url !== '';
    $police = 'font-family: -apple-system, BlinkMacSystemFont, Segoe UI, Roboto, Helvetica, Arial, sans-serif;';
@endphp
<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="border-collapse: separate; margin: 0 0 16px 0; width: 100%;">
<tr>
<td class="vf-card vf-card--source" bgcolor="#f4f6f5" style="background-color: #f4f6f5; border: 1px solid #e9ecef; border-radius: 10px; padding: 18px 20px;">
<p class="vf-card-titre" style="color: #202124; {{ $police }} font-size: 16px; font-weight: bold; line-height: 22px; margin: 0 0 {{ $meta ? '6px' : '0' }} 0; text-align: left;">@if ($cliquable)<a href="{{ $url }}" target="_blank" rel="noopener" style="color: #202124; font-weight: bold; text-decoration: underline;">{{ $nom }}</a>@else{{ $nom }}@endif</p>
@if ($meta)<p class="vf-meta" style="color: #8a8d8b; {{ $police }} font-size: 12px; line-height: 18px; margin: 0; text-align: left; word-break: break-all;">{{ $domaine }}@if ($domaine !== '' && $url !== '') &middot; @endif{{ $url }}</p>@endif
</td>
</tr>
</table>
