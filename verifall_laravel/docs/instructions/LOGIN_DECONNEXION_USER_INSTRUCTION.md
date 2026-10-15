# LOGIN_DECONNEXION_USER_INSTRUCTION.md

## Citoyen (guard web)
Inscription : nom, e-mail, mdp (Password::defaults 12+), **consentements non pré-cochés** (CGU obligatoire, newsletter optionnelle, horodatés) → **OTP e-mail 6 chiffres TTL 10 min** → compte actif → visite guidée. Connexion : e-mail+mdp (+ 2FA si activée), « Se souvenir » = remember token rotatif, session régénérée. Mot de passe oublié : OTP même contrat, invalidation des autres sessions après reset. Déconnexion : POST uniquement, invalidate+regenerateToken, toast « À bientôt 👋 », redirection accueil. Écrans = connexion.html / inscription.html / mot-de-passe-oublie.html du prototype, validation temps réel Livewire, erreurs génériques (« identifiants invalides » — jamais préciser lequel).

## Staff (guard staff, sessions séparées)
login.html staff (admin deep-green / mentor). **2FA TOTP obligatoire.** Connexion/déconnexion → **audit log** (IP, UA). Session idle 30 min ; « déconnecté pour inactivité » à la reconnexion. Aucun autologin, aucun compte partagé.
