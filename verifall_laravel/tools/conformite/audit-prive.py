# -*- coding: utf-8 -*-
"""Contrôle de conformité des écrans authentifiés (citoyen + back-office)."""
import io
import json
import os

from comparer import rapport

PROTO = r'C:\Projects\verifall\verifall_laravel\resources\prototype'
ICI = os.path.dirname(os.path.abspath(__file__))
RENDUS = os.path.join(ICI, 'rendus')
SORTIE = os.path.join(ICI, 'conformite')
os.makedirs(SORTIE, exist_ok=True)

PAIRES = {
    'citizen_dashboard': 'compte-user/dashboard.html',
    'citizen_verify': 'compte-user/verifier.html',
    'citizen_verifications': 'compte-user/mes-verifications.html',
    'citizen_catalogue': 'compte-user/catalogue.html',
    'citizen_evaluations': 'compte-user/evaluations.html',
    'citizen_exercises': 'compte-user/exercices.html',
    'citizen_forum': 'compte-user/forum.html',
    'citizen_hub': 'compte-user/hub-infos.html',
    'citizen_live-classes': 'compte-user/live-classes.html',
    'citizen_profile': 'compte-user/profil.html',
    'citizen_quiz': 'compte-user/quiz.html',
    'citizen_referral': 'compte-user/parrainage.html',
    'citizen_rewards': 'compte-user/recompenses.html',
    'citizen_settings': 'compte-user/reglages.html',
    'citizen_stats': 'compte-user/statistiques.html',
    'citizen_tools': 'compte-user/outils.html',
    'citizen_tutorials': 'compte-user/mes-tutoriels.html',
    'citizen_units': 'compte-user/unites.html',
    'staff_dashboard': 'compte-admin/dashboard.html',
    'staff_alerts': 'compte-admin/alertes.html',
    'staff_audit': 'compte-admin/securite.html',
    'staff_knowledge': 'compte-admin/base-connaissances.html',
}

resultats = []
for rendu, page in sorted(PAIRES.items()):
    fr = os.path.join(RENDUS, rendu + '.html')
    fp = os.path.join(PROTO, page.replace('/', os.sep))
    if not os.path.exists(fr):
        resultats.append({'page': page, 'etat': 'non capturé'})
        continue
    if not os.path.exists(fp):
        resultats.append({'page': page, 'etat': 'PROTOTYPE INTROUVABLE'})
        continue

    r = rapport(page,
                io.open(fp, encoding='utf-8', errors='replace').read(),
                io.open(fr, encoding='utf-8', errors='replace').read())
    r['etat'] = 'ok'
    io.open(os.path.join(SORTIE, rendu + '.json'), 'w', encoding='utf-8').write(
        json.dumps(r, ensure_ascii=False, indent=1))
    resultats.append({'page': page, 'rendu': rendu, 'etat': 'ok',
                      'similitude_structure': r['similitude_structure'],
                      'similitude_motifs': r['similitude_motifs'],
                      'noeuds_prototype': r['noeuds_prototype'],
                      'noeuds_rendu': r['noeuds_rendu'],
                      'manquants': len(r['reperes_manquants']),
                      'ajoutes': len(r['reperes_ajoutes'])})

io.open(os.path.join(SORTIE, '_resume-prive.json'), 'w', encoding='utf-8').write(
    json.dumps(resultats, ensure_ascii=False, indent=1))

print(f"{'page':38s} {'motifs':>7s} {'suite':>7s} {'proto/rendu':>13s} {'rep -':>6s} {'rep +':>6s}")
print('-' * 84)
for r in sorted(resultats, key=lambda x: x.get('similitude_motifs', -1)):
    if r['etat'] != 'ok':
        print(f"{r['page']:38s} {r['etat']}")
        continue
    print(f"{r['page']:38s} {r['similitude_motifs']:>6.0%} {r['similitude_structure']:>6.0%} "
          f"{r['noeuds_prototype']:>5}/{r['noeuds_rendu']:<7} "
          f"{r['manquants']:>6} {r['ajoutes']:>6}")
