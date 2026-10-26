// Régénère resources/icones/lucide.php depuis le Lucide vendorisé du prototype.
// Usage (depuis verifall_laravel/) : node tools/staff-premium/generer-icones.mjs
import { readFileSync, writeFileSync } from 'node:fs';
import vm from 'node:vm';

const source = 'resources/prototype-premium/assets/vendor/lucide/lucide.min.js';
const ctx = {}; ctx.globalThis = ctx; ctx.self = ctx; ctx.window = ctx;
vm.createContext(ctx);
vm.runInContext(readFileSync(source, 'utf8'), ctx);

const kebab = (s) => s.replace(/([a-z0-9])([A-Z])/g, '$1-$2').replace(/([a-zA-Z])([0-9])/g, '$1-$2').toLowerCase();
const attr = (v) => String(v).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
const php = (s) => s.replace(/\\/g, '\\\\').replace(/'/g, "\\'");

const icones = {};
for (const [nom, noeuds] of Object.entries(ctx.lucide.icons)) {
  // Selon la version, une icône est la liste de ses nœuds ou [balise, attributs, nœuds].
  const liste = Array.isArray(noeuds[2]) ? noeuds[2] : noeuds;
  icones[kebab(nom)] = liste.map(([balise, a]) => `<${balise} ${Object.entries(a).filter(([k]) => k !== 'key').map(([k, v]) => `${k}="${attr(v)}"`).join(' ')}/>`).join('');
}

const tete = `<?php

declare(strict_types=1);

/*
 | Icônes Lucide 0.468.0 (licence ISC, voir LICENSE-lucide.txt) : le contenu
 | de chaque <svg>, indexé par le nom kebab-case de lucide.dev.
 |
 | Généré depuis resources/prototype-premium/assets/vendor/lucide/lucide.min.js
 | par tools/staff-premium/generer-icones.mjs. Ne pas modifier à la main.
 | Un tableau PHP plutôt que du JSON : OPcache le garde en mémoire.
 */

return [
`;
const lignes = Object.keys(icones).sort().map((n) => `    '${n}' => '${php(icones[n])}',`);
writeFileSync('resources/icones/lucide.php', tete + lignes.join('\n') + '\n];\n');
console.log(`${lignes.length} icônes écrites dans resources/icones/lucide.php`);
