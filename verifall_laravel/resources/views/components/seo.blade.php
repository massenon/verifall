@props(['seo'])
{{--
  | Les métadonnées de référencement d'une page publique.
  |
  | L'ORDRE et le CONTENU sont ceux du prototype, balise par balise. On ne
  | complète pas « pendant qu'on y est » : ce qu'il déclare, on le déclare ;
  | ce qu'il ne déclare pas, on s'en abstient.
  |
  | Deux points valent l'attention :
  |
  | 1. `og:image` et `twitter:image` sont ABSOLUES. Facebook et WhatsApp ne
  |    résolvent aucun chemin relatif : une URL relative ne donne pas un aperçu
  |    dégradé, elle n'en donne aucun — et on ne s'en aperçoit qu'au premier
  |    partage, quand il est trop tard pour la publication.
  |
  | 2. Le JSON-LD passe par `@json` et non par `{!! !!}`. Le titre d'un article
  |    peut contenir un guillemet ; recopié tel quel dans un bloc `<script>`,
  |    il casse le JSON — au mieux Google ignore le balisage, au pire la page
  |    devient un vecteur d'injection.
--}}
<title>{{ $seo->titreComplet() }}</title>
<meta name="description" content="{{ $seo->descriptionCourte() }}">
<link rel="canonical" href="{{ $seo->urlCanonique }}">
@unless ($seo->indexable)
<meta name="robots" content="noindex, follow">
@endunless

<meta property="og:title" content="{{ $seo->titreComplet() }}">
<meta property="og:description" content="{{ $seo->descriptionCourte() }}">
<meta property="og:type" content="{{ $seo->type }}">
<meta property="og:url" content="{{ $seo->urlCanonique }}">
<meta property="og:image" content="{{ $seo->image }}">
<meta property="og:site_name" content="Vérif'All">
{{-- Suit la langue rendue, au lieu d'affirmer le français en toutes
     circonstances. Une page servie en anglais qui se déclare `fr_FR` ment à
     Facebook et à WhatsApp au moment précis où quelqu'un la partage — et
     l'aperçu généré est la seule chose que verront la plupart des gens.
     Le gabarit du certificat faisait déjà ainsi ; c'est son motif qui est
     repris, pour que les deux ne divergent pas. --}}
<meta property="og:locale" content="{{ app()->getLocale() === 'en' ? 'en_GB' : 'fr_FR' }}">
@if ($seo->type === 'article' && $seo->publieLe !== null)
<meta property="article:published_time" content="{{ $seo->publieLe->toIso8601String() }}">
@if ($seo->modifieLe !== null)
<meta property="article:modified_time" content="{{ $seo->modifieLe->toIso8601String() }}">
@endif
@endif

<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seo->titreComplet() }}">
<meta name="twitter:description" content="{{ $seo->descriptionCourte() }}">
<meta name="twitter:image" content="{{ $seo->image }}">

@if ($fil = $seo->filArianeStructure())
<script type="application/ld+json">@json($fil, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
@endif
@foreach ($seo->donneesStructurees as $bloc)
<script type="application/ld+json">@json($bloc, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)</script>
@endforeach
