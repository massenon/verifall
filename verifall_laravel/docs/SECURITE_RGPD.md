# SECURITE_RGPD.md — Checklist sécurité & conformité RGPD Vérif'All

## 1. Authentification & sessions
- **Login/Logout admin & mentor** : guards séparés (`web` citoyen, `staff`), écran conforme au prototype (login.html deep-green), session régénérée au login (`session()->regenerate()`), logout = invalidation + regénération token.
- **Rate limiting** (RateLimiter facade, dans `AppServiceProvider`) :
  - `login` : 5/min par IP **et** par e-mail (clé composite) → 423 avec message générique.
  - `otp` : 3 envois / 10 min / identité ; vérification 5 essais puis invalidation du code.
  - `fact-check-submit` : 10/h par utilisateur (anti-abus unités).
- **OTP inscription & mot de passe oublié** : code 6 chiffres, TTL 10 min, usage unique (invalidé après succès), stocké hashé, jamais loggé. Compte inactif tant que non vérifié.
- **Mots de passe** : `Password::defaults()` → min 12, mixte casse+chiffres+symboles, `uncompromised()`. Hash Argon2id (`config/hashing.php`).
- **2FA** (réglages citoyen + obligatoire staff) : TOTP, codes de récupération chiffrés.
- **Cookies** : `SESSION_SECURE_COOKIE=true`, `http_only=true`, `same_site=strict`. **JAMAIS de token dans localStorage/sessionStorage** (le vf-store du prototype migre côté serveur ; localStorage ne garde que des préférences UI non sensibles).

## 2. Injections, XSS, CSRF, uploads
- SQL : Eloquent/Query Builder uniquement ; `DB::raw()` interdit sauf paramètres bindés et revue explicite.
- XSS : `{{ }}` partout ; contenu Quill purifié (`mews/purifier`, whitelist h2 h3 p strong em ul ol li blockquote pre a[href] img[src]) avant persistance ET au rendu.
- CSRF : `@csrf` sur tout formulaire non-Livewire ; Livewire le gère nativement — ne jamais exclure de route du middleware.
- Uploads (KYC, devoirs, images) : `file|mimes:jpg,png,pdf,webp|max:10240`, ré-encodage des images (Intervention), nom `Str::uuid()`, disque `private`, distribution par **route signée temporaire** (15 min — cf. « téléchargement sécurisé » du prototype grading). Aucun fichier exécutable, aucune écriture dans `public/`.
- `UrlSandbox` : toute URL soumise à vérification est résolue côté serveur avec allowlist de schémas (http/https), blocage IP privées/localhost (anti-SSRF), timeout 8 s, taille max 5 Mo.

## 3. Exposition & erreurs
- `APP_ENV=production`, `APP_DEBUG=false` ; pages `errors/{403,404,419,429,500}.blade.php` au style du front (copywriting Vérif'All, lien retour).
- UUID/slug publics partout (`HasUuids`) : `/verifications/{uuid}`, `/cours/{slug}` — jamais `/signalements/1042`.
- Headers (middleware `SecureHeaders`) : HSTS, X-Content-Type-Options nosniff, X-Frame-Options DENY, Referrer-Policy strict-origin-when-cross-origin, CSP sans `unsafe-inline` JS (Livewire nonce).
- Logs : jamais de PII en clair (masquage e-mail/téléphone via processor Monolog).

## 4. RBAC & audit
- Spatie Permission : rôles Admin / Mentor / Modérateur / Citoyen ; **Policies = contrôle définitif** (masquer un bouton n'est PAS une sécurité). Mentor : scope strict `where mentor_id = auth()->id()` dans les Repositories.
- **Audit Logs** (table `audit_logs`) : acteur, action, module, cible (type+uuid), IP, user-agent, diff JSON avant/après, horodatage — écrits via `AuditLogService::record()` sur toute action sensible (CRUD staff, changement rôle, export de données, connexion admin, KYC). Alimenté la page « Journal d'Audit » du dashboard.
- **Security Logs** : tentatives login échouées, 429, uploads rejetés, patterns d'injection détectés → page « Alertes Intrusion » + notification admin si seuil.

## 5. RGPD (Identity\ConsentService + AccountErasureService)
- **Minimisation** : ne collecter que le nécessaire (validations strictes) ; téléphone optionnel.
- **Consentements** : cases explicites non pré-cochées à l'inscription (CGU + politique de confidentialité, newsletter séparée) ; table `consents` (user, type, version du texte, timestamp, IP). Bandeau cookies conforme au COOKIE_BANNER_AUDIT de la base de connaissances.
- **Droit d'accès/portabilité** : bouton « Exporter mes données » (réglages citoyen) → Job → archive JSON+CSV (profil, vérifications, progression LMS, forum) sur route signée 24 h + e-mail.
- **Droit à l'effacement** : « Supprimer mon compte » → confirmation par mot de passe + OTP → soft delete 30 j (délai de rétractation notifié) → Job d'anonymisation définitive (PII → null/hash, contenus forum attribués à « Citoyen supprimé », certificats conservés anonymisés pour la vérification publique — base légale : intérêt légitime documenté).
- **Rétention** : commande planifiée `CleanupEvidences` (preuves d'analyse > 90 j), logs sécurité 12 mois, comptes inactifs 3 ans → e-mail puis anonymisation.
- **Chiffrement** : PII sensibles (pièces KYC, téléphone) via casts `encrypted` ; HTTPS forcé (`URL::forceScheme('https')`).
- **Historiques rattachés au compte** (arbitrage n° 178, `docs/RGPD_HISTORIQUES.md`) : connexions par compte en intérêt légitime de sécurité, IP tronquée et hachée, 12 mois ; lectures du Hub uniquement avec consentement séparé, 90 jours, effacées au retrait.
- **Registre & DPO** : page confidentialité à jour (celle du prototype), mention du responsable de traitement (Vérif'All / René Fadonougbo), procédure de réclamation APDP Bénin.

## 6. Checklist pré-déploiement
`APP_DEBUG=false` ✓ clés API hors repo ✓ `php artisan config:cache route:cache view:cache` ✓ queues supervisées ✓ sauvegardes chiffrées testées (spatie/laravel-backup, page Maintenance) ✓ scan `composer audit` + `npm audit` ✓ test des limiteurs ✓ test parcours RGPD complet (export + suppression) ✓.
