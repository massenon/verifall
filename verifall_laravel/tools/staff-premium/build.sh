#!/bin/sh
# Construit public/assets/css/staff-premium.css (console admin et mentor).
#
#   sh tools/staff-premium/build.sh           construit la feuille
#   sh tools/staff-premium/build.sh --check   échoue si la feuille commitée
#                                             n'est plus à jour avec les vues
#
# Tailwind 3.4.17, la version du prototype premium : une autre version
# produirait un CSS différent pour les mêmes classes.
set -eu
cd "$(dirname "$0")/../.."
SORTIE=public/assets/css/staff-premium.css
CIBLE=$SORTIE
if [ "${1:-}" = "--check" ]; then CIBLE=$(mktemp); fi
npx --yes tailwindcss@3.4.17 -c tools/staff-premium/tailwind.config.cjs -i tools/staff-premium/input.css -o "$CIBLE" --minify
if [ "${1:-}" = "--check" ]; then
  if cmp -s "$CIBLE" "$SORTIE"; then echo "staff-premium.css est à jour."; rm -f "$CIBLE"; else rm -f "$CIBLE"; echo "staff-premium.css n'est plus à jour : relancez sh tools/staff-premium/build.sh" >&2; exit 1; fi
fi
