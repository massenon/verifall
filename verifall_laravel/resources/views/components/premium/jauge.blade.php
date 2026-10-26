@props(['pct' => 0])
{{-- Barre de progression : la valeur est bornée ici, jamais par l'appelant. --}}
<div {{ $attributes->class('jauge') }} role="img" aria-label="{{ max(0, min(100, (int) $pct)) }} %"><span style="width: {{ max(0, min(100, (int) $pct)) }}%"></span></div>
