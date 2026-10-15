<x-mail::message>
{{--
  | Le gabarit générique.
  |
  | Le corps vient de `email_templates.body`, en Markdown, marqueurs déjà
  | remplacés par `RenduDeGabarit`. Il est rendu tel quel — la garde est à
  | l'enregistrement, pas ici : un corps nettoyé à chaque lecture finit par
  | sortir par la lecture qu'on avait oubliée, et un courriel part hors de
  | l'application, là où plus rien ne le rattrape.
--}}
# {{ $titre }}

{{-- Purifié ICI, au dernier moment.
  |
  | Le corps est rédigé depuis l'administration, par quelqu'un qui détient
  | `system.email_template` — ce n'est pas une saisie publique. Mais un courriel
  | part HORS de l'application, là où plus aucun filtre ne le rattrapera : c'est
  | le seul endroit du produit où « en amont, c'est déjà propre » ne suffit pas.
  | Le garde XSS du dépôt ne fait d'ailleurs aucune exception, et il a raison. --}}
{!! clean($corps) !!}

@if ($libelleDuBouton && $lienDuBouton)
<x-mail::button :url="$lienDuBouton">
{{ $libelleDuBouton }}
</x-mail::button>
@endif
</x-mail::message>
