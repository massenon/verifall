{{-- Statut d'un quiz — quatre états, dans l'ordre où on les rencontre. --}}
@props(['ligne'])

@if($ligne['passed'])
  <span class="vbadge vbadge-true">{{ __('composants.badge_reussi') }}</span>
@elseif($ligne['score'] !== null)
  <span class="vbadge vbadge-doubt">{{ __('composants.badge_a_refaire') }}</span>
@elseif($ligne['inscrit'])
  <span class="vbadge vbadge-progress">{{ __('composants.badge_a_passer') }}</span>
@else
  <span class="vbadge" style="background:#F6F8F7;color:#64748B" title="{{ __('composants.badge_disponible_apres_inscription_au_cours') }}">{{ __('composants.badge_verrouille') }}</span>
@endif
