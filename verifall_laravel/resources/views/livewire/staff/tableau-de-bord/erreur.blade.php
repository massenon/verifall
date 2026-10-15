{{--
  | L'échec d'un bloc (§51) — contextualisé, et limité à ce bloc.
  |
  | L'exception est déjà rapportée au journal par `CentreDePilotage::tenter()`.
  | L'écran ne l'affiche pas : un message technique n'aide pas la personne qui
  | regarde, et pourrait exposer un chemin ou une requête.
  |
  | Paramètre : $bloc (string, nom lisible du bloc).
--}}
<div class="tdb-erreur" role="alert">
    <span class="material-symbols-rounded" aria-hidden="true">error</span>
    <p>
        <strong>« {{ $bloc }} » n'a pas pu être chargé.</strong>
        Les autres indicateurs restent fiables. L'erreur est consignée au journal ;
        réessayez avec « Actualiser ».
    </p>
</div>
