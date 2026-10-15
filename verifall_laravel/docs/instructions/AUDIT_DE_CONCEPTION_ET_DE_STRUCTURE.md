# AUDIT_DE_CONCEPTION_ET_DE_STRUCTURE.md — Grille d'audit par module (avant merge)

1. **Architecture** : logique dans les Services ? Repositories seuls à toucher Eloquent ? Contracts bindés ? Aucune dépendance Core→framework ? Events/Listeners plutôt que couplage direct ?
2. **Données** : migrations conformes au DICTIONNAIRE_DONNEES (types, index, FK, uuid) ? casts Enums ? $fillable ? N+1 vérifié ?
3. **Sécurité** : Policy présente + testée ? scope mentor ? validation exhaustive ? purifier sur HTML riche ? audit log sur actions sensibles ? rate limit si endpoint public ?
4. **Conformité prototype** : diff visuel page par page ? ordre des sections ? copywriting du dictionnaire ? états empty/loading/error ?
5. **UX transverse** : KPI accordéon ? tables→cards ? toasts ? suppression avec avertissement+annulation ? import sample ? export réel ?
6. **Qualité** : tests Feature (happy + edge + interdits) ? Pint/PHPStan clean ? pas de code mort/dupliqué ? docblocks métier ?
Verdict d'audit : ✅ conforme / 🟡 réserves listées (mergeable avec tickets) / 🔴 refus motivé. L'audit est collé dans la PR.
