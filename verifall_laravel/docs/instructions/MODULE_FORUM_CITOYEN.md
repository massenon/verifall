# MODULE_FORUM_CITOYEN.md — Community Desk (référence : forum.html / forum-sujet.html)

Modèles : ForumCategory(moderation_level) · Thread(uuid, title, body, category, author, votes vrai/doute/faux, official_verdict_id nullable, status publique|signalée|verrouillée) · Reply(is_mentor_certified) · ThreadVote(user, thread, choice — 1 vote modifiable) · Report.

1. **forum** : hero stats, composer (progressive disclosure mobile ; validation titre ≥10, corps ≥15), feed paginé, filtres catégorie, publication → insertion en tête + toast + **focus accessible** sur la nouvelle discussion.
2. **Votes communautaires** vrai/doute/faux : barres de répartition, 1 vote/user modifiable, aria-pressed.
3. **Mode Fake News** : catégorie prioritaire ; bouton « Transmettre au laboratoire » (≥N signalements ou modérateur) → crée un Signalement lié ; quand le pipeline publie → Listener `LinkOfficialVerdictToThread` pose le label **« ✓ VÉRIF'ALL CONFIRME — {VERDICT} » au-dessus du titre** (hiérarchie : le labo tranche, la communauté opine — jamais confondus).
4. **forum-sujet** : fil de réponses, réponse certifiée mentor épinglée (badge), signalement de contenu (motifs), verrouillage modérateur.
5. Modération (staff) : file signalements forum, niveaux par catégorie, actions auditées.
Sécurité : purge XSS des corps, rate limit 5 posts/h/user, anti-flood, mots interdits configurables. Notifications : réponse reçue, verdict officiel lié, transmission labo confirmée.
