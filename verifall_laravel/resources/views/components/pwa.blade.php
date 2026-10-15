{{--
  | La déclaration d'application web progressive, commune aux gabarits.
  |
  | Un partiel plutôt que six copies : ces six lignes doivent rester identiques
  | entre le public, l'espace citoyen et le back-office. Recopiées, elles
  | divergent — et une `theme_color` qui diffère d'un écran à l'autre fait
  | clignoter la barre d'état du téléphone à chaque navigation.
--}}
<link rel="manifest" href="{{ route('public.manifest') }}">

{{-- La teinte de la barre d'état une fois l'application posée sur l'écran
     d'accueil. C'est `--vf-primary`, la même que celle du manifeste. --}}
<meta name="theme-color" content="#09B850">

{{-- iOS ignore le manifeste : il lui faut ses propres balises. --}}
<link rel="apple-touch-icon" href="{{ asset('assets/images/apple-touch-icon.png') }}">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="default">
<meta name="apple-mobile-web-app-title" content="Vérif'All">

@once
@push('scripts')
<script>
/*
 | Enregistrement du service worker.
 |
 | Trois précautions, chacune pour une raison vécue :
 |
 | 1. `'serviceWorker' in navigator` — l'API n'existe pas en navigation privée
 |    sur certains navigateurs, ni en HTTP non chiffré. Sans le test, une
 |    exception non capturée casse tout le JavaScript de la page.
 |
 | 2. Après `load` — l'enregistrement met le réseau en concurrence avec
 |    l'affichage. Le faire pendant le chargement retarde ce que la personne
 |    attend, pour préparer une visite qui n'aura peut-être pas lieu.
 |
 | 3. `.catch()` — un service worker qui refuse de s'installer ne doit jamais
 |    faire échouer la page. La PWA est un supplément, pas une dépendance.
 */
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function () {
    navigator.serviceWorker.register('{{ route('public.service-worker') }}', { scope: '/' })
      .catch(function () { /* Le site fonctionne sans. */ });
  });
}

/*
 | À la déconnexion, on efface les pages mises en cache.
 |
 | Sans cela, se déconnecter d'un téléphone partagé laisserait les pages déjà
 | vues consultables hors ligne par la personne suivante. Le formulaire de
 | déconnexion est en POST ; on écoute son envoi.
 */
document.addEventListener('submit', function (evenement) {
  var formulaire = evenement.target;
  if (!(formulaire instanceof HTMLFormElement)) return;
  if (formulaire.getAttribute('action') !== '{{ route('logout') }}') return;

  if (navigator.serviceWorker && navigator.serviceWorker.controller) {
    navigator.serviceWorker.controller.postMessage('purge');
  }
});
</script>
@endpush
@endonce
