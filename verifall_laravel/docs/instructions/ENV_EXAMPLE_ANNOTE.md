# ENV_EXAMPLE_ANNOTE.md — Variables d'environnement complètes

```env
# ─── Application ───
APP_NAME="Vérif'All"
APP_ENV=production            # local | staging | production
APP_DEBUG=false               # JAMAIS true en prod (SECURITE_RGPD §3)
APP_URL=https://verifall.com
APP_LOCALE=fr

# ─── Base de données / cache / files ───
DB_CONNECTION=mysql  DB_DATABASE=verifall  DB_USERNAME=…  DB_PASSWORD=…   # utilisateur dédié, pas root
CACHE_STORE=redis  QUEUE_CONNECTION=redis  SESSION_DRIVER=redis
REDIS_HOST=127.0.0.1  REDIS_PASSWORD=…
SESSION_SECURE_COOKIE=true  SESSION_SAME_SITE=strict

# ─── Mail (OTP, notifications, newsletters — via queue) ───
MAIL_MAILER=smtp  MAIL_HOST=…  MAIL_PORT=587  MAIL_USERNAME=…  MAIL_PASSWORD=…
MAIL_FROM_ADDRESS=bonjour@verifall.com  MAIL_FROM_NAME="Vérif'All"

# ─── Temps réel & push ───
REVERB_APP_ID=…  REVERB_APP_KEY=…  REVERB_APP_SECRET=…  REVERB_HOST=ws.verifall.com
FCM_CREDENTIALS=storage/keys/fcm-service-account.json   # hors git (storage/keys dans .gitignore)

# ─── Adaptateurs fact-checking (obtention : consoles développeur respectives) ───
FAKE_ADAPTERS=true            # true en dev : adaptateurs mockés, aucun coût (SPRINT S3)
GEMINI_API_KEY=…              # aistudio.google.com
GEMINI_MODEL=gemini-2.5-flash
DEEPSEEK_API_KEY=…            # platform.deepseek.com
OPENAI_API_KEY=…              # platform.openai.com (aussi Whisper)
PERPLEXITY_API_KEY=…          # perplexity.ai/settings/api
GOOGLE_FACTCHECK_KEY=…        # console.cloud.google.com — Fact Check Tools API
GOOGLE_CUSTOM_SEARCH_KEY=…  GOOGLE_CSE_ID=…   # Programmable Search Engine
GOOGLE_KG_KEY=…  GOOGLE_SB_KEY=…  GOOGLE_VISION_KEY=…  GOOGLE_STT_KEY=…
TINEYE_KEY=…  AIORNOT_KEY=…  GPTZERO_KEY=…  WINSTON_KEY=…
SEARCH_CONSOLE_CREDENTIALS=storage/keys/gsc-service-account.json

# ─── Paiement Mobile Money (voir PAIEMENT_MOBILE_MONEY.md) ───
MOMO_ENV=sandbox              # sandbox | production
MTN_MOMO_SUBSCRIPTION_KEY=…  MTN_MOMO_API_USER=…  MTN_MOMO_API_KEY=…
MOOV_MONEY_CLIENT_ID=…  MOOV_MONEY_CLIENT_SECRET=…
PAYMENT_WEBHOOK_SECRET=…      # signature HMAC des webhooks entrants

# ─── Zoom (live classes) ───
ZOOM_ACCOUNT_ID=…  ZOOM_CLIENT_ID=…  ZOOM_CLIENT_SECRET=…   # Server-to-Server OAuth

# ─── Sauvegardes ───
BACKUP_ARCHIVE_PASSWORD=…     # chiffrement des archives spatie/backup
```
Règles : `.env` jamais commité ; rotation des clés trimestrielle documentée ; en CI, secrets via le coffre du pipeline ; `php artisan config:cache` après tout changement.
