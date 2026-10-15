# PAIEMENT_MOBILE_MONEY.md — Recharge d'unités (MTN MoMo & Moov Money Bénin)

## Flux (page unites.html du prototype)
1. Citoyen choisit un pack (500 F → 25 u, 1000 F → 55 u, 2000 F → 120 u — config `config/economy.php`).
2. `PaymentService::initiate()` crée `payments(uuid, user, provider, pack, amount_fcfa, units, status=PENDING, provider_ref)` puis appelle l'API opérateur (RequestToPay MTN / init Moov) avec le numéro saisi (validé regex opérateur, jamais stocké en clair — cast encrypted).
3. UI : écran d'attente Livewire (polling 3 s) « Validez la demande sur votre téléphone (*880#) ».
4. **Webhook** `/webhooks/momo/{provider}` : signature HMAC vérifiée (PAYMENT_WEBHOOK_SECRET) + IP allowlist → transition PENDING→SUCCESS|FAILED (machine à états, transaction DB) → SUCCESS : crédit unités via EconomyService + unit_log(kind=recharge) + notification + toast temps réel (Reverb). Idempotence : provider_ref unique — un webhook rejoué ne crédite jamais deux fois.
5. Échec/timeout 5 min : statut FAILED + message actionnable + bouton Réessayer. Reconciliation : commande `verifall:reconcile-payments` (hourly) interroge les statuts des PENDING >10 min.
Sandbox : MOMO_ENV=sandbox + `FakeMomoProvider` (bouton « Simuler le paiement » visible en local uniquement). Sécurité : montants serveur uniquement (jamais du client), audit log de tout crédit manuel admin, page admin Paiements (CRUD lecture + remboursement motivé).
