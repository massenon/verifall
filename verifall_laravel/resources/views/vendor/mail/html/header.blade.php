@props(['url'])
{{--
  | En-tête de marque.
  |
  | Le gabarit d'origine testait `trim($slot) === 'Laravel'` pour poser le logo
  | de Laravel. Ce n'est pas un détail cosmétique : un démenti reçu sous une
  | identité générique perd exactement ce qui le rend croyable.
  |
  | Le nom en TEXTE plutôt qu'une image : la plupart des clients de messagerie
  | bloquent les images tant qu'on ne clique pas « afficher », et un en-tête qui
  | commence par un rectangle vide ressemble à un message frauduleux.
--}}
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">{!! $slot !!}</a>
</td>
</tr>
<tr>
{{-- Le filet vert : la seule signature graphique qui traverse TOUS les clients
     de messagerie. Un tableau, pas une bordure — Outlook rend le HTML avec le
     moteur de Word. --}}
<td class="brand-rule" height="4">&nbsp;</td>
</tr>
