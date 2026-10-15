{{--
  | Gabarit d'ESSAI, hors application.
  |
  | Il n'est expédié à personne : il sert à `ComposantsDeCourrielTest` à rendre
  | les huit composants par le vrai moteur Markdown, avec des données de bord —
  | valeur nulle, 0 %, 100 %, chaîne vide, nom très long. La directive l'exige
  | (§51, §52) et le projet a déjà payé quatre fois le prix d'un composant que
  | rien n'appelait : il répondait juste, et il ne s'affichait nulle part.
--}}
<x-mail::message>
<x-slot:preheader>{{ $preheader }}</x-slot:preheader>

# Essai des composants

<x-mail::verdict :verdict="$verdict" :confiance="$confiance" />

<x-mail::carte-preuve :titre="$titre">{{ $texte }}</x-mail::carte-preuve>

<x-mail::carte-source :nom="$titre" domaine="exemple.org" url="https://exemple.org" />

<x-mail::carte-alerte :titre="$titre">{{ $texte }}</x-mail::carte-alerte>

<x-mail::progression :pourcentage="$pourcentage" :libelle="$titre" />

<x-mail::carte-cours :titre="$titre" :pourcentage="$pourcentage" url="https://exemple.org/cours" />

<x-mail::carte-certificat :apprenant="$titre" :cours="$titre" :date="$date" code="ABC-123" url="https://exemple.org/c" />

<x-mail::cta-secondaire url="https://exemple.org">En savoir plus</x-mail::cta-secondaire>
</x-mail::message>
