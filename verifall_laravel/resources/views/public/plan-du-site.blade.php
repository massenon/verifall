{!! '<' . '?xml version="1.0" encoding="UTF-8"?' . '>' !!}
{{-- Plan du site. Engendré depuis la base : voir DepotPlanDuSiteEloquent. --}}

{{--
    La déclaration ci-dessus est concaténée ('<' . '?xml ... ?' . '>') pour
    empêcher le parseur PHP de détecter une balise fermante '?>' ou ouvrante '<?'
    à la compilation Blade lorsque `short_open_tag` vaut On sur le serveur.
--}}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
@foreach ($entrees as $entree)
    <url>
        <loc>{{ $entree['url'] }}</loc>
@if ($entree['lastmod'] !== null)
        <lastmod>{{ $entree['lastmod'] }}</lastmod>
@endif
        <changefreq>{{ $entree['changefreq'] }}</changefreq>
        <priority>{{ $entree['priority'] }}</priority>
    </url>
@endforeach
</urlset>