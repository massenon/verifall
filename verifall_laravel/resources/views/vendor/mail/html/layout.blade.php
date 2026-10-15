<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
<title>{{ config('app.name') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
{{--
  | « light dark » et non « light » seul, depuis le 9 septembre 2026.
  |
  | La déclaration précédente annonçait un courriel clair UNIQUEMENT. C'est une
  | stratégie défendable — elle évite qu'Apple Mail inverse les surfaces tout
  | seul — mais elle a un effet qu'on ne voulait pas : le client n'applique
  | alors AUCUNE de nos règles `prefers-color-scheme`, et les destinataires en
  | thème sombre reçoivent soit un fond blanc éclatant, soit une inversion
  | automatique qui décide à notre place. Gmail et Outlook.com inversent de
  | toute façon, meta ou pas.
  |
  | On préfère donc décider : le bloc `prefers-color-scheme: dark` plus bas
  | pose des surfaces sombres CHOISIES, en gardant le vert de marque, qui
  | passe la barre de contraste sur fond foncé.
--}}
<meta name="color-scheme" content="light dark">
<meta name="supported-color-schemes" content="light dark">
<style>
@media only screen and (max-width: 600px) {
.inner-body {
width: 100% !important;
}

.footer {
width: 100% !important;
}
}

@media only screen and (max-width: 500px) {
.button {
width: 100% !important;
}
}

/*
 | Mode sombre.
 |
 | `!important` sur CHAQUE déclaration, et ce n'est pas de la superstition :
 | Laravel inline `themes/verifall.css` dans des attributs `style`, qui
 | l'emportent sur toute règle de feuille. Sans `!important`, ce bloc entier
 | serait mort — et il le serait SILENCIEUSEMENT, puisque aucun test ne
 | regarde un rendu de client de messagerie.
 |
 | On ne redéfinit que les SURFACES et le TEXTE. Le vert de marque, les
 | couleurs de verdict et les boutons gardent leur teinte : ce sont eux qui
 | rendent le courriel reconnaissable, et les inverser reviendrait à envoyer
 | un autre courriel.
 */
@media (prefers-color-scheme: dark) {
body,
.wrapper,
.content {
background-color: #1F2124 !important;
}

.inner-body,
.vf-card {
background-color: #26292c !important;
border-color: #3a3e42 !important;
}

.vf-card--source {
background-color: #2e3235 !important;
}

.vf-card--alerte {
background-color: #33262a !important;
}

h1, h2, h3, .vf-card-titre, .vf-progress-libelle {
color: #f4f6f5 !important;
}

p, .vf-card-texte, .table td {
color: #c8ccc9 !important;
}

.vf-meta, .subcopy p, .subcopy a, .footer p, .footer a {
color: #9a9d9b !important;
}

.vf-progress {
background-color: #3a3e42 !important;
}

.vf-cta-secondaire {
background-color: transparent !important;
}
}
</style>
{!! $head ?? '' !!}
</head>
<body>

{{--
  | Le texte de prévisualisation de la boîte de réception (§38 de la directive).
  |
  | Il complète l'objet au lieu de le répéter : « Votre vérification est
  | terminée » / « Consultez le verdict, les sources et l'explication complète. »
  | Un preheader qui dit « Cliquez ici pour ouvrir » gaspille la seule ligne
  | que le destinataire lit avant de décider.
  |
  | Il DOIT être le premier nœud du corps — sinon le client ramasse le texte
  | qui le précède. Les entités invisibles qui le suivent poussent hors de
  | l'aperçu ce qui viendrait ensuite ; sans elles, Gmail complète la ligne
  | avec le début du courriel.
--}}
@isset($preheader)
<div class="vf-preheader" style="display:none;visibility:hidden;opacity:0;color:transparent;height:0;width:0;max-height:0;max-width:0;overflow:hidden;mso-hide:all;font-size:1px;line-height:1px;">
{{ $preheader }}
<span>&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;&#847;&zwnj;&nbsp;</span>
</div>
@endisset

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation">
<tr>
<td align="center">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation">
{!! $header ?? '' !!}

<!-- Email Body -->
<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0" style="border: hidden !important;">
<table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation">
<!-- Body content -->
<tr>
<td class="content-cell">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
