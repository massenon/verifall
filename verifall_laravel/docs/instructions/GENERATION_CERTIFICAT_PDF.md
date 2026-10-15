# GENERATION_CERTIFICAT_PDF.md

Déclencheur : événement CourseCompleted (100 % leçons + quiz requis réussis) → Job `GenerateCertificatePdf` (queue `pdf`).
Gabarit : Blade `pdf/certificate.blade.php` fidèle au visuel du prototype (cadre forest, logo, nom, cours, date, code) + **gabarits personnalisables** via la page admin Templates Certificats (fond image, positions). Rendu : Browsershot (Chromium headless) pour la fidélité CSS ; fallback dompdf si indisponible.
Code public : `VERIF-{lettre cours}-{6 alphanum}` (unique, ex. VERIF-D-H1ST-1926 du seeder) + **QR code** pointant vers `/verify-certificate?code=…` (page publique existante : valide/révoqué, nom, cours, date). Stockage : disque privé `certificates/{user_uuid}/{code}.pdf` ; téléchargement par route signée 15 min ; régénérable (bouton citoyen + admin).
Révocation : admin → revoked_at + motif (audit log) → la vérification publique affiche « Révoqué ». Métadonnées PDF : titre, auteur Vérif'All, pas de PII superflue. Tests : génération, unicité du code, vérification publique, révocation.
