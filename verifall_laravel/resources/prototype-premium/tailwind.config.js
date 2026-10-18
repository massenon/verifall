/*
 | Configuration Tailwind du prototype premium (admin + mentor).
 |
 | Compilée en fichier statique (assets/css/app.css) : le prototype s'ouvre
 | sans serveur et sans CDN, comme le reste du site sur cPanel.
 |
 |   npx tailwindcss@3.4.17 -c tailwind.config.js -i assets/css/input.css -o assets/css/app.css --minify
 */
module.exports = {
  content: ['./*.html', './assets/js/**/*.js'],
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
