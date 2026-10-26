@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/css/pages/staff-tableau-de-bord.css') }}">
@endpush
{{--
  | Le squelette d'une section différée (§50).
  |
  | Il occupe À PEU PRÈS la place de la section qui arrive : un squelette d'une
  | ligne remplacé par un bloc de 400 px ferait sauter la page sous le doigt.
--}}
<div role="status" aria-live="polite">
    <span class="sr-only">Chargement des indicateurs…</span>

    @if ($section === 'kpi')
        <div class="tdb-kpis" aria-hidden="true">
            @for ($i = 0; $i < 5; $i++)
                <div class="squelette tdb-skel-kpi"></div>
            @endfor
        </div>
    @elseif ($section === 'activite')
        <div class="carte tdb-skel-card" aria-hidden="true">
            @for ($i = 0; $i < 6; $i++)
                <div class="squelette tdb-skel-ligne" style="width: {{ 92 - $i * 7 }}%;"></div>
            @endfor
        </div>
    @else
        <div class="tdb-grid tdb-grid-21" aria-hidden="true">
            <div class="carte tdb-skel-card">
                <div class="squelette tdb-skel-ligne" style="width: 40%;"></div>
                <div class="squelette tdb-skel-bloc"></div>
            </div>
            <div class="carte tdb-skel-card">
                <div class="squelette tdb-skel-ligne" style="width: 55%;"></div>
                <div class="squelette tdb-skel-ligne"></div>
                <div class="squelette tdb-skel-ligne" style="width: 80%;"></div>
                <div class="squelette tdb-skel-ligne" style="width: 65%;"></div>
            </div>
        </div>
    @endif
</div>
