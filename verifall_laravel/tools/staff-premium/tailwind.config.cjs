/*
 | Configuration Tailwind de la console premium (administration et mentor).
 |
 | Mêmes jetons que resources/prototype-premium/tailwind.config.js : c'est la
 | référence visuelle (ARBITRAGES n° 176). Compilée en fichier STATIQUE
 | (public/assets/css/staff-premium.css), sans Vite, pour cPanel :
 |
 |   sh tools/staff-premium/build.sh          (depuis verifall_laravel/)
 |
 | La feuille citoyenne (public/assets/css/tailwind.css) n'est pas concernée :
 | elle reste figée (BuildTailwindFigeTest).
 */
const fs = require('fs');
const path = require('path');

/* Les briques du design system (couche `components` d'input.css) sont
   toujours émises, même avant qu'une vue ne les emploie : une brique doit
   exister le jour où un écran la réclame, sans dépendre du dernier build.
   La liste est LUE dans input.css, pour ne jamais diverger de lui. */
// Chemin relatif à verifall_laravel/, d'où build.sh lance Tailwind.
const source = fs.readFileSync(path.resolve('tools/staff-premium/input.css'), 'utf8');
const couche = source.slice(source.indexOf('@layer components'), source.indexOf('@layer utilities') > 0 ? source.indexOf('@layer utilities') : undefined);
const briques = [...new Set([...couche.matchAll(/^\s*\.([a-z][a-z0-9-]*)[\s,{:[]/gm)].map((m) => m[1]))]
  .filter((nom) => nom !== 'dark');

module.exports = {
  safelist: briques,
  /* Chemins relatifs à verifall_laravel/ (le script s'y place). Une vue
     ajoutée hors de ces dossiers n'aurait pas ses classes : l'y ranger. */
  content: [
    './resources/views/components/premium/**/*.blade.php',
    './resources/views/components/layouts/staff.blade.php',
    './resources/views/components/lucide.blade.php',
    './resources/views/livewire/staff/**/*.blade.php',
    './resources/views/livewire/auth/staff-*.blade.php',
    './resources/views/livewire/auth/verrou-*.blade.php',
    './resources/views/components/layouts/console-*.blade.php',
    './resources/views/staff/**/*.blade.php',
    './app/Support/Rbac/MenuDuBackOffice.php',
    './app/View/Components/Premium/**/*.php',
    './config/console.php',
    './public/assets/js/console/**/*.js',
  ],
  darkMode: 'class',
  theme: {
    extend: {
      colors: {
        /* La marque : réservée aux actions et à l'état actif. */
        brand: { DEFAULT: '#09B850', 600: '#079a43', 700: '#067a36', 50: '#e8f8ef', 100: '#c9efd9' },
        /* La colonne de navigation : un vert forêt, pas un noir neutre. */
        foret: { DEFAULT: '#0E2A1E', 800: '#123527', 700: '#18432f', 600: '#21573e' },
        papier: '#F6F8F5',
        encre: { DEFAULT: '#142019', 2: '#4A5A51', 3: '#7A8981' },
        trait: '#E3E8E4',
        /* Surfaces sombres, teintées du même vert. */
        nuit: { DEFAULT: '#0A1510', carte: '#13211A', trait: '#22352B', 2: '#B9C8BF', 3: '#7F9187' },
        /* Statuts (réservés : jamais une série de graphique). */
        bon: '#0ca30c', alerte: '#fab219', grave: '#ec835a', critique: '#d03b3b',
      },
      fontFamily: {
        titre: ['"Bricolage Grotesque"', 'system-ui', 'sans-serif'],
        sans: ['"Public Sans"', 'system-ui', '-apple-system', '"Segoe UI"', 'sans-serif'],
      },
      borderRadius: { carte: '14px', ctrl: '8px' },
      boxShadow: {
        flottant: '0 12px 32px -8px rgba(14,42,30,.22), 0 2px 6px rgba(14,42,30,.08)',
      },
    },
  },
  plugins: [],
};
