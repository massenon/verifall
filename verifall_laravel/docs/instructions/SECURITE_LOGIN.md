# SECURITE_LOGIN.md — Durcissement authentification

- Rate limiting : login 5/min par IP **et** par identité (clé composite) → 429 générique + security log ; OTP envoi 3/10 min, vérification 5 essais puis code invalidé ; verrouillage progressif (5 échecs = 1 min, 10 = 15 min, 20 = notification admin).
- Hash Argon2id ; comparaison timing-safe ; `Password::defaults()->uncompromised()`.
- Anti-énumération : mêmes messages/latence que le compte existe ou non (reset : « Si un compte existe, un code a été envoyé »).
- Cookies session : Secure+HttpOnly+SameSite=Strict, domaine strict ; regénération d'ID à chaque élévation (login, 2FA, changement mdp).
- 2FA : TOTP (RFC 6238), secrets chiffrés, 8 codes de récupération à usage unique hashés ; désactivation = mdp + code.
- Détection : logins depuis 2 pays <1 h → e-mail d'alerte + security log (page Alertes Intrusion) ; nouveaux appareils notifiés.
- Interdits : mdp en clair dans les logs, tokens en localStorage, GET de déconnexion, autocomplete off cassant les gestionnaires (utiliser `autocomplete="current-password"`), question secrète.
- Tests obligatoires : brute force bloqué, OTP expiré refusé, OTP rejoué refusé, fixation de session impossible, CSRF sur logout.
