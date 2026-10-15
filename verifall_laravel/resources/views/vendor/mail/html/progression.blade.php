@props([
    'pourcentage' => 0,
    'libelle' => 'Progression',
])
@php
    /*
     | Barre de progression pour courriel.
     |
     | Deux cellules de tableau dont la largeur est un pourcentage : c'est la
     | seule barre qu'Outlook (moteur Word) dessine. Le pourcentage est TOUJOURS
     | écrit en chiffres à côté — la couleur n'est jamais le seul porteur.
     |
     | Bornes : toute valeur non numérique vaut 0, tout est ramené dans [0, 100].
     | À 0 la cellule verte est omise, à 100 la cellule grise l'est : une cellule
     | de largeur 0 % garde une largeur minimale chez certains clients.
     */
    $pourcentage = is_numeric($pourcentage) ? (int) round((float) $pourcentage) : 0;
    $pourcentage = max(0, min(100, $pourcentage));
    $reste = 100 - $pourcentage;
    $libelle = trim((string) ($libelle ?? ''));
    $police = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
@endphp
<table class="vf-progress" width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="width: 100%; margin: 0; padding: 0; border-collapse: separate; table-layout: fixed;">
<tr>
<td class="vf-progress-libelle" align="left" style="font-family: {{ $police }}; font-size: 13px; line-height: 18px; color: #606261; padding: 0 8px 6px 0; word-break: break-word;">{{ $libelle !== '' ? $libelle : 'Progression' }}</td>
<td class="vf-progress-libelle" align="right" width="120" style="font-family: {{ $police }}; font-size: 13px; line-height: 18px; font-weight: 700; color: #202124; padding: 0 0 6px 0; white-space: nowrap;">{{ $pourcentage }}&nbsp;% complété</td>
</tr>
<tr>
<td colspan="2" style="padding: 0;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" bgcolor="#e9ecef" style="width: 100%; border-collapse: separate; table-layout: fixed; background-color: #e9ecef; border-radius: 5px;">
<tr>
@if ($pourcentage > 0)
<td class="vf-progress-remplissage" width="{{ $pourcentage }}%" height="10" bgcolor="#09B850" style="width: {{ $pourcentage }}%; height: 10px; line-height: 10px; font-size: 1px; background-color: #09B850; border-radius: 5px;">&nbsp;</td>
@endif
@if ($reste > 0)
<td width="{{ $reste }}%" height="10" bgcolor="#e9ecef" style="width: {{ $reste }}%; height: 10px; line-height: 10px; font-size: 1px; background-color: #e9ecef; border-radius: 5px;">&nbsp;</td>
@endif
</tr>
</table>
</td>
</tr>
</table>
