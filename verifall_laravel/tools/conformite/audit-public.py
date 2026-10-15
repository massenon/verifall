# -*- coding: utf-8 -*-
"""Contrôle de conformité des 22 pages publiques : prototype contre rendu."""
import io
import json
import os
import urllib.request

from comparer import rapport

BASE = 'http://127.0.0.1:8000'
PROTO = r'C:\Projects\verifall\verifall_laravel\resources\prototype'
SORTIE = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'conformite')
os.makedirs(SORTIE, exist_ok=True)

# page du prototype  ->  chemin servi par Laravel (None = aucune route)
PAIRES = {
    'index.html': '/',
    'blog.html': '/blog',
    'blog-details.html': '/blog/verifall-remporte-le-prix-wsis-2026',
    'comment-ca-marche.html': '/comment-ca-marche',
    'confidentialite.html': '/confidentialite',
    'connexion.html': '/connexion',
    'contact.html': '/contact',
    'cookies.html': '/cookies',
    'facts.html': '/actualites',
    'facts-details.html': '/actualites/nouveau-service-e-services-pour-les-actes-de-naissance',
    'faq.html': '/faq',
    'fonctionnalites.html': '/fonctionnalites',
    'inscription.html': '/inscription',
    'medias.html': '/medias',
    'mentions-legales.html': '/mentions-legales',
    'mot-de-passe-oublie.html': '/mot-de-passe-oublie',
    'notre-equipe.html': '/notre-equipe',
    'pourquoi-verifall.html': '/pourquoi-verifall',
    'tutoriels.html': '/tutoriels',
    'tutoriels-details.html': '/tutoriels/introduction-fact-checking',
    'verifier-une-information.html': '/verifier-une-information',
    'verify-certificate.html': '/verifier-un-certificat',
}


def recuperer(chemin):
    req = urllib.request.Request(BASE + chemin, headers={'User-Agent': 'audit-conformite'})
    with urllib.request.urlopen(req, timeout=30) as r:
        return r.status, r.read().decode('utf-8', 'replace')


resultats = []
for page, chemin in sorted(PAIRES.items()):
    if chemin is None:
        resultats.append({'page': page, 'etat': 'non apparié', 'similitude_structure': None})
        continue
    try:
        code, html = recuperer(chemin)
    except Exception as e:  # noqa: BLE001
        resultats.append({'page': page, 'etat': f'ERREUR {e}', 'similitude_structure': None})
        continue

    proto_html = io.open(os.path.join(PROTO, page), encoding='utf-8', errors='replace').read()
    r = rapport(page, proto_html, html)
    r['etat'] = f'HTTP {code}'
    r['url'] = chemin
    io.open(os.path.join(SORTIE, page.replace('.html', '.json')), 'w', encoding='utf-8').write(
        json.dumps(r, ensure_ascii=False, indent=1))
    resultats.append({k: r[k] for k in ('page', 'etat', 'similitude_structure',
                                        'noeuds_prototype', 'noeuds_rendu')}
                     | {'similitude_motifs': r['similitude_motifs'],
                        'manquants': len(r['reperes_manquants']),
                        'ajoutes': len(r['reperes_ajoutes'])})

io.open(os.path.join(SORTIE, '_resume-public.json'), 'w', encoding='utf-8').write(
    json.dumps(resultats, ensure_ascii=False, indent=1))

print(f"{'page':32s} {'état':9s} {'motifs':>7s} {'suite':>7s} {'proto/rendu':>13s} {'rep -':>6s} {'+':>5s}")
print('-' * 88)
for r in sorted(resultats, key=lambda x: (x.get('similitude_motifs') is None,
                                          x.get('similitude_motifs') or 0)):
    s = r.get('similitude_motifs')
    s = f'{s:.0%}' if s is not None else '—'
    s2 = r['similitude_structure']
    s2 = f'{s2:.0%}' if s2 is not None else '—'
    n = (f"{r.get('noeuds_prototype','—')}/{r.get('noeuds_rendu','—')}"
         if r.get('noeuds_prototype') else '—')
    print(f"{r['page']:32s} {r['etat']:9s} {s:>7s} {s2:>7s} {n:>13s} "
          f"{r.get('manquants','—'):>6} {r.get('ajoutes','—'):>5}")
