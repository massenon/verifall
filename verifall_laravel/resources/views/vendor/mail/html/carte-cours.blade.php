@props([
    'titre' => null,
    'pourcentage' => null,
    'url' => null,
    'libelleAction' => null,
])
@php
    /*
     | Carte d'un cours : titre, progression, reprise.
     |
     | `pourcentage` à `null` = aucune progression connue : la carte le dit en
     | toutes lettres au lieu d'afficher une barre vide. `0` et `100` sont des
     | valeurs légitimes, bornées par le composant `progression`. Le libellé du
     | bouton suit l'état (commencer / reprendre / revoir) sauf si l'appelant
     | l'impose.
     */
    $titre = trim((string) ($titre ?? ''));
    $titre = $titre !== '' ? $titre : 'Cours sans titre';
    $url = trim((string) ($url ?? ''));
    $pct = is_numeric($pourcentage) ? max(0, min(100, (int) round((float) $pourcentage))) : null;
    $termine = $pct !== null && $pct >= 100;
    $action = trim((string) ($libelleAction ?? ''));
    if ($action === '') {
        $action = match (true) {
            $pct === null || $pct === 0 => 'Commencer le cours',
            $termine => 'Revoir le cours',
            default => 'Reprendre le cours',
        };
    }
    $police = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
@endphp
<table class="vf-card vf-card--cours" width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" bgcolor="#ffffff" style="width: 100%; margin: 21px 0; padding: 0; border-collapse: separate; table-layout: fixed; background-color: #ffffff; border: 1px solid #e9ecef; border-radius: 12px;">
<tr>
<td style="padding: 20px 24px;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="width: 100%; border-collapse: collapse; table-layout: fixed;">
<tr>
<td class="vf-meta" style="font-family: {{ $police }}; font-size: 12px; line-height: 16px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #606261; padding: 0 0 6px 0;">{{ $termine ? 'Cours terminé' : 'Cours' }}</td>
</tr>
<tr>
<td class="vf-card-titre" style="font-family: {{ $police }}; font-size: 17px; line-height: 24px; font-weight: 700; color: #063B2A; padding: 0 0 14px 0; word-break: break-word;">{{ $titre }}</td>
</tr>
<tr>
<td style="padding: 0;">
@if ($pct !== null)
<x-mail::progression :pourcentage="$pct" :libelle="$termine ? 'Parcours achevé' : 'Votre progression'" />
@else
<p class="vf-card-texte" style="margin: 0; font-family: {{ $police }}; font-size: 15px; line-height: 22px; color: #606261;">Vous n'avez pas encore commencé ce cours.</p>
@endif
</td>
</tr>
@if ($url !== '')
<tr>
<td style="padding: 18px 0 0 0;">
<x-mail::cta-secondaire :url="$url" align="left" :compact="true">{{ $action }}</x-mail::cta-secondaire>
</td>
</tr>
@endif
</table>
</td>
</tr>
</table>
