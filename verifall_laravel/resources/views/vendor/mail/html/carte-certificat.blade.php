@props([
    'apprenant' => null,
    'cours' => null,
    'date' => null,
    'code' => null,
    'url' => null,
])
@php
    /*
     | Carte d'un certificat obtenu : apprenant, cours, date, code de vérification.
     |
     | Bandeau forest, corps blanc. Une donnée absente fait disparaître SA ligne,
     | jamais la carte : `date` et `code` peuvent valoir `null`, un nom très long
     | se replie (`word-break`), un code se coupe n'importe où (`break-all`).
     | `date` accepte une chaîne déjà formatée ou un DateTimeInterface.
     */
    $apprenant = trim((string) ($apprenant ?? ''));
    $apprenant = $apprenant !== '' ? $apprenant : 'Nom non renseigné';
    $cours = trim((string) ($cours ?? ''));
    $cours = $cours !== '' ? $cours : 'Cours non renseigné';
    $code = trim((string) ($code ?? ''));
    $url = trim((string) ($url ?? ''));
    if ($date instanceof \DateTimeInterface) {
        $date = \Illuminate\Support\Carbon::instance($date)->locale(app()->getLocale())->translatedFormat('j F Y');
    }
    $date = trim((string) ($date ?? ''));
    $police = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
    $meta = "font-family: {$police}; font-size: 13px; line-height: 18px; padding: 10px 0 0 0;";
@endphp
<table class="vf-card vf-card--certificat" width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" bgcolor="#ffffff" style="width: 100%; margin: 21px 0; padding: 0; border-collapse: separate; table-layout: fixed; background-color: #ffffff; border: 1px solid #e9ecef; border-radius: 12px;">
<tr>
<td bgcolor="#063B2A" style="background-color: #063B2A; border-radius: 11px 11px 0 0; padding: 14px 24px;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="width: 100%; border-collapse: collapse; table-layout: fixed;">
<tr>
<td class="vf-meta" align="left" style="font-family: {{ $police }}; font-size: 12px; line-height: 16px; font-weight: 700; letter-spacing: 1px; text-transform: uppercase; color: #ffffff;">Certificat de réussite</td>
<td class="vf-meta" align="right" style="font-family: {{ $police }}; font-size: 12px; line-height: 16px; color: #ffffff; white-space: nowrap;">Académie Vérif'All</td>
</tr>
</table>
</td>
</tr>
<tr>
<td style="padding: 22px 24px 20px 24px;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="width: 100%; border-collapse: collapse; table-layout: fixed;">
<tr>
<td class="vf-card-texte" style="font-family: {{ $police }}; font-size: 14px; line-height: 20px; color: #606261; padding: 0 0 4px 0;">Décerné à</td>
</tr>
<tr>
<td class="vf-card-titre" style="font-family: {{ $police }}; font-size: 20px; line-height: 26px; font-weight: 700; color: #063B2A; padding: 0 0 12px 0; word-break: break-word;">{{ $apprenant }}</td>
</tr>
<tr>
<td class="vf-card-texte" style="font-family: {{ $police }}; font-size: 15px; line-height: 22px; color: #202124; padding: 0 0 16px 0; word-break: break-word;">pour avoir achevé le cours <strong style="color: #202124;">{{ $cours }}</strong>.</td>
</tr>
@if ($date !== '' || $code !== '')
<tr>
<td style="padding: 0; border-top: 1px solid #e9ecef;">
<table width="100%" cellpadding="0" cellspacing="0" border="0" role="presentation" style="width: 100%; border-collapse: collapse; table-layout: fixed;">
@if ($date !== '')
<tr>
<td class="vf-meta" width="45%" align="left" style="{{ $meta }} color: #606261;">Date d'obtention</td>
<td class="vf-meta" align="right" style="{{ $meta }} color: #202124; font-weight: 700; word-break: break-word;">{{ $date }}</td>
</tr>
@endif
@if ($code !== '')
<tr>
<td class="vf-meta" width="45%" align="left" style="{{ $meta }} color: #606261;">Code de vérification</td>
<td class="vf-meta" align="right" style="{{ $meta }} color: #202124; font-weight: 700; font-family: 'Courier New', Courier, monospace; letter-spacing: 1px; word-break: break-all;">{{ $code }}</td>
</tr>
@endif
</table>
</td>
</tr>
@endif
@if ($url !== '')
<tr>
<td style="padding: 18px 0 0 0;">
<x-mail::cta-secondaire :url="$url" align="left" :compact="true">Vérifier l'authenticité du certificat</x-mail::cta-secondaire>
</td>
</tr>
@endif
</table>
</td>
</tr>
</table>
