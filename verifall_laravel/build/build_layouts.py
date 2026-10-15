# -*- coding: utf-8 -*-
"""
build_layouts.py — Transpose les 3 shells du prototype en layouts Blade.

Principe : on n'ecrit pas le HTML a la main, on l'EXTRAIT du prototype et on
remplace uniquement (a) les chemins d'assets, (b) les href .html par des routes
nommees, (c) la zone de contenu par le slot Blade. Toute autre difference
serait une derive par rapport au contrat visuel.
"""
import io
import os
import re
import sys

PROTO = 'resources/prototype'
OUT = "resources/views/components/layouts"

# ---------------------------------------------------------------- routes
# Correspondance fichier prototype -> route nommee Laravel.
ROUTES = {
    # citoyen
    'dashboard.html': 'citizen.dashboard',
    'verifier.html': 'citizen.verify',
    'mes-verifications.html': 'citizen.verifications',
    'outils.html': 'citizen.tools',
    'mes-tutoriels.html': 'citizen.tutorials',
    'catalogue.html': 'citizen.catalogue',
    'recompenses.html': 'citizen.rewards',
    'forum.html': 'citizen.forum',
    'hub-infos.html': 'citizen.hub',
    'parrainage.html': 'citizen.referral',
    'profil.html': 'citizen.profile',
    'reglages.html': 'citizen.settings',
    'statistiques.html': 'citizen.stats',
    'evaluations.html': 'citizen.evaluations',
    'live-classes.html': 'citizen.live-classes',
    'exercices.html': 'citizen.exercises',
    'quiz.html': 'citizen.quiz',
    'unites.html': 'citizen.units',
    # public
    'index.html': 'public.home',
    'pourquoi-verifall.html': 'public.why',
    'fonctionnalites.html': 'public.features',
    'comment-ca-marche.html': 'public.how',
    'notre-equipe.html': 'public.team',
    'tutoriels.html': 'public.tutorials',
    'facts.html': 'public.facts',
    'connexion.html': 'login',
    'inscription.html': 'register',
    'mot-de-passe-oublie.html': 'password.request',
    'contact.html': 'public.contact',
    'faq.html': 'public.faq',
}

CSS_BY_SHELL = {
    'public': ['fonts/fonts.css', 'verifall.css', 'tailwind.css'],
    'citizen': ['fonts/fonts.css', 'citizen.css', 'tailwind.css'],
    'staff': ['fonts/fonts.css', 'citizen.css', 'staff.css', 'vendor/quill.snow.css', 'tailwind.css'],
    'auth': ['fonts/fonts.css', 'verifall.css', 'tailwind.css'],
}


# Surcharges par shell : plusieurs fichiers portent le MEME nom dans des
# espaces differents (compte-user/dashboard.html et compte-admin/dashboard.html).
# Sans cette table, le « Tableau de Bord » du staff renvoyait vers l'espace
# citoyen — un administrateur se retrouvait hors de son back-office.
ROUTES_BY_SHELL = {
    'staff': {
        'dashboard.html': 'staff.dashboard',
        'login.html': 'staff.login',
    },
}


def rewrite_links(html: str, shell: str = '') -> str:
    """href="x.html" -> href="{{ route('...') }}" quand la route existe."""
    overrides = ROUTES_BY_SHELL.get(shell, {})

    def sub(m):
        prefix, target = m.group(1), m.group(2)
        base = os.path.basename(target)
        route = overrides.get(base, ROUTES.get(base))
        if route:
            return f'{prefix}="{{{{ route(\'{route}\') }}}}"'
        # Page pas encore portee : ancre inerte plutot qu'un lien casse.
        return f'{prefix}="#" data-todo="{base}"'

    return re.sub(r'(href)="([^"]*\.html)"', sub, html)


ANCHOR_RE = re.compile(r'<a\b[^>]*?>', re.S)
ROUTE_IN_HREF = re.compile(r"href=\"\{\{ route\('([^']+)'\) \}\}\"")


def dynamise_active_state(html: str) -> str:
    """Rend l'etat actif de la navigation dependant de la route courante.

    Le prototype fige `is-active` et `aria-current="page"` sur la page dont il
    est extrait : sans cette passe, « Tableau de bord » resterait surligne sur
    toutes les pages du shell. On remplace donc ces marqueurs statiques par une
    condition serveur, ce qui preserve exactement les memes classes CSS.
    """
    def fix(m):
        tag = m.group(0)
        route = ROUTE_IN_HREF.search(tag)
        if not route:
            return tag
        name = route.group(1)

        # Marqueurs statiques retires.
        tag = tag.replace(' aria-current="page"', '')

        def cls(cm):
            value = cm.group(1).replace('is-active', '').strip()
            value = ' '.join(value.split())
            return (f'class="{value}'
                    f" {{{{ request()->routeIs('{name}') ? 'is-active' : '' }}}}\"")

        tag = re.sub(r'class="([^"]*)"', cls, tag, count=1)

        # aria-current pose uniquement sur l'entree courante (accessibilite).
        return (tag[:-1]
                + f" @if(request()->routeIs('{name}')) aria-current=\"page\" @endif>")

    return ANCHOR_RE.sub(fix, html)


def _asset(kind):
    """Fabrique un remplacement lambda pour re.sub.

    On passe par une fonction plutot que par une chaine de remplacement :
    les chaines melant antislashs, guillemets et accolades Blade sont une
    source d'erreurs silencieuses (une reference \\1 devient un echappement
    octal en chaine non brute, et l'attribut disparait sans bruit).
    """
    def repl(m):
        attr, path = m.group(1), m.group(2)
        return attr + '="{{ asset(\'assets/' + kind + '/' + path + '\') }}"'
    return repl


def rewrite_assets(html: str) -> str:
    """Chemins relatifs du prototype -> /assets servis par Laravel."""
    html = re.sub(r'(href|src)="(?:\.\./)*(?:compte-user/)?css/([^"]+)"',
                  _asset('css'), html)
    html = re.sub(r'(href|src)="(?:\.\./)*(?:compte-user/)?js/([^"]+)"',
                  _asset('js'), html)
    html = re.sub(r'(src)="(?:\.\./)*images/([^"]+)"',
                  _asset('images'), html)
    # Le CDN Tailwind est proscrit (SEO_PERFORMANCE : aucun CDN tiers).
    html = re.sub(r'\s*<script src="https://cdn\.tailwindcss\.com"></script>', '', html)
    return html


# ---------------------------------------------------------------- visite guidée
# Ancres de la visite d'onboarding (UX_TRANSVERSE : 6 étapes citoyen, 5 staff).
#
# guided-tour.js vise des sélecteurs `[data-tour="…"]`. Le prototype ne porte
# évidemment pas ces attributs : ils sont posés ici, sur des éléments du SHELL
# repérés par un marqueur textuel unique. Les poser à la main dans le Blade les
# ferait disparaître à la première régénération — ce qui s'est déjà produit deux
# fois sur d'autres injections.
#
# L'unicité du marqueur est vérifiée à la génération : si le prototype évolue et
# que le repère devient ambigu — ou disparaît — la construction échoue
# bruyamment, plutôt que de produire une visite qui pointe dans le vide.
ANCRES_TOUR = {
    'citizen': [
        ('bg-brandred text-white text-sm font-semibold px-5 py-3', 'verifier'),
        ('title="Mes unités de vérification"', 'unites'),
        ("class=\"sidebar-link {{ request()->routeIs('citizen.catalogue')", 'academie'),
        ("class=\"sidebar-link {{ request()->routeIs('citizen.forum')", 'forum'),
        ("class=\"user-menu-link {{ request()->routeIs('citizen.stats')", 'statistiques'),
        ("class=\"user-menu-link {{ request()->routeIs('citizen.profile')", 'profil'),
        # Bascule du menu utilisateur : deux étapes visent des entrées qui n'y
        # sont visibles qu'une fois le menu déployé.
        ('aria-label="Menu utilisateur"', 'menu-utilisateur'),
    ],
    'staff': [
        ('aria-label="Navigation Admin"', 'sidebar'),
        ('bg-[#059669] text-white text-sm font-semibold px-4 py-2.5', 'creer'),
        ('w-11 h-11 rounded-lg hover:bg-canvas text-ink" data-dropdown-toggle',
         'notifications'),
        ('title="Journal d&#39;audit"', 'audit'),
        # « a-traiter » n'a pas d'ancre : la file prioritaire est un bloc du
        # tableau de bord, livré au Sprint 3, pas un élément du shell. L'étape
        # existe dans guided-tour.js et s'activera d'elle-même le jour où la
        # cible apparaîtra.
    ],
}


def ancre_visite_guidee(html: str, shell: str) -> str:
    """Pose les attributs `data-tour` attendus par guided-tour.js.

    L'attribut est inséré juste après le NOM de la balise, jamais à l'endroit du
    marqueur : plusieurs repères sont des fragments de `class="…"`, et une
    insertion en place les couperait en deux — la classe devenait
    `rounded-xl data-tour="` et le bouton perdait tout son style.
    """
    for marqueur, nom in ANCRES_TOUR.get(shell, []):
        vus = html.count(marqueur)
        if vus != 1:
            raise ValueError(
                f'ancre « {nom} » : {vus} occurrence(s) du marqueur, 1 attendue'
            )

        # Remontée jusqu'à l'ouverture de la balise qui porte le marqueur, puis
        # saut par-dessus le nom d'élément (<a, <button, <aside…).
        ouverture = html.rindex('<', 0, html.index(marqueur))
        apres_nom = ouverture + 1
        while html[apres_nom].isalnum():
            apres_nom += 1

        html = html[:apres_nom] + f' data-tour="{nom}"' + html[apres_nom:]

    return html


def declenche_visite_guidee(html: str, shell: str) -> str:
    """Marque le <body> quand la visite reste à faire.

    Le déclenchement appartient au SERVEUR : `users.tour_done_at` en est la
    seule source de vérité. Le confier au navigateur — localStorage — la
    reproposerait sur chaque nouvel appareil, et jamais après un changement de
    poste.

    Le garde est explicite par shell : `auth()` interroge le guard par défaut
    (`web`), et renverrait null pour un administrateur connecté sur `staff`.
    """
    if shell not in ('citizen', 'staff'):
        return html

    garde = "auth('staff')" if shell == 'staff' else 'auth()'
    condition = (
        f'@if({garde}->check() && {garde}->user()->tour_done_at === null)'
        f' data-tour-auto="{shell}"@endif'
    )

    ouverture = html.index('<body')
    fin = html.index('>', ouverture)

    return html[:fin] + ' ' + condition + html[fin:]


def build(source: str, shell: str, content_marker: str) -> str:
    raw = io.open(os.path.join(PROTO, source), encoding='utf-8').read()

    body_start = raw.index('<body')
    head = raw[:body_start]
    body = raw[body_start:]

    # Bornes de la zone de contenu.
    open_at = body.index(content_marker)
    open_end = body.index('>', open_at) + 1
    close_at = body.rindex('</main>')

    shell_top = body[:open_end]
    shell_bottom = body[close_at:]

    # <head> reconstruit : on garde la structure, on reecrit les CSS.
    css = '\n'.join(
        f"    <link rel=\"stylesheet\" href=\"{{{{ asset('assets/css/{c}') }}}}\">"
        for c in CSS_BY_SHELL[shell]
    )
    new_head = f"""<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{{{ csrf_token() }}}}">
    <title>{{{{ $title ?? "Vérif'All" }}}}</title>
    <meta name="description" content="{{{{ $description ?? "Vérif'All — la boussole contre la désinformation numérique." }}}}">
    <link rel="icon" href="{{{{ asset('assets/images/favicon.png') }}}}">

    {{{{-- Ordre de cascade IMPOSÉ (DESIGN_SYSTEM §Tokens) : tailwind.css EN DERNIER.
         Toute inversion casse la spécificité et donc le rendu du prototype. --}}}}
{css}
    {{{{-- Couche applicative : additive, jamais correctrice du design system. --}}}}
    <link rel="stylesheet" href="{{{{ asset('assets/css/verifall-app.css') }}}}">
    @stack('styles')
</head>
"""

    shell_top = dynamise_active_state(rewrite_links(rewrite_assets(shell_top), shell))
    shell_bottom = dynamise_active_state(rewrite_links(rewrite_assets(shell_bottom), shell))

    # L'hote des toasts et le JS applicatif appartiennent au shell : les
    # injecter a la main apres coup les ferait disparaitre a la premiere
    # regeneration — ce qui s'est produit une fois.
    pied = (
        f'<x-toast variant="{shell if shell in ("citizen", "staff") else "citizen"}" />\n'
        "<script src=\"{{ asset('assets/js/verifall-app.js') }}\" defer></script>\n"
    )

    # La visite guidee ne concerne que les espaces authentifies.
    if shell in ('citizen', 'staff'):
        pied += "<script src=\"{{ asset('assets/js/guided-tour.js') }}\" defer></script>\n"

    pied += "@stack('scripts')\n</body>"

    assemble = (
        new_head
        + shell_top
        + "\n\n{{ $slot }}\n\n"
        + shell_bottom.replace('</body>', pied, 1)
    )

    return declenche_visite_guidee(ancre_visite_guidee(assemble, shell), shell)


def build_auth() -> str:
    """Shell des ecrans d'authentification (ecran scinde).

    Extracteur dedie : ici la zone de contenu n'est pas delimitee par <main>
    mais par la colonne de droite. Le slot commence apres la barre de
    progression mobile (CLAIM/SOURCE/EVIDENCE/VERDICT), qui appartient au shell
    et doit apparaitre sur les trois ecrans d'auth.
    """
    raw = io.open(os.path.join(PROTO, 'inscription.html'), encoding='utf-8').read()

    body = raw[raw.index('<body'):]

    # « VERDICT » apparait DEUX fois : dans la trace desktop de la colonne
    # gauche, puis dans la barre de progression mobile de la colonne droite.
    # Ancrer sur la premiere coupait au milieu de la colonne gauche et faisait
    # se chevaucher les deux colonnes. On part donc du debut de la colonne
    # formulaire pour ne trouver que la seconde.
    colonne_formulaire = body.index('lg:col-span-5')
    ancre = body.index('VERDICT', colonne_formulaire)
    fin_barre = body.index('</div>', body.index('</div>', ancre) + 6) + len('</div>')

    shell_top = body[:fin_barre]

    # </main> seul laisserait ouverts le conteneur interne et la colonne
    # formulaire : on les referme explicitement.
    shell_bottom = '\n    </div>\n  </div>\n' + body[body.rindex('</main>'):]

    css = '\n'.join(
        f"    <link rel=\"stylesheet\" href=\"{{{{ asset('assets/css/{c}') }}}}\">"
        for c in CSS_BY_SHELL['auth']
    )
    head = f"""<!DOCTYPE html>
<html lang="fr" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{{{ csrf_token() }}}}">
    <title>{{{{ $title ?? "Vérif'All" }}}}</title>
    {{{{-- Les écrans d'authentification ne sont jamais indexés. --}}}}
    <meta name="robots" content="noindex, nofollow">
    <link rel="icon" href="{{{{ asset('assets/images/favicon.png') }}}}">
{css}
    <link rel="stylesheet" href="{{{{ asset('assets/css/verifall-app.css') }}}}">
    @stack('styles')
</head>
"""

    shell_top = dynamise_active_state(rewrite_links(rewrite_assets(shell_top), 'auth'))
    shell_bottom = dynamise_active_state(rewrite_links(rewrite_assets(shell_bottom), 'auth'))

    return (head + shell_top + "\n\n{{ $slot }}\n\n"
            + shell_bottom.replace(
                '</body>',
                "<x-toast variant=\"citizen\" />\n"
                "<script src=\"{{ asset('assets/js/verifall-app.js') }}\" defer></script>\n"
                "@stack('scripts')\n</body>", 1))


def main():
    os.makedirs(OUT, exist_ok=True)
    jobs = [
        ('compte-user/dashboard.html', 'citizen', '<main id="contenu"'),
        ('compte-admin/dashboard.html', 'staff', '<main id="contenu"'),
        ('pourquoi-verifall.html', 'public', '<main id="contenu"'),
    ]
    for source, shell, marker in jobs:
        try:
            out = build(source, shell, marker)
        except ValueError as e:
            print(f'  !! {shell}: {e} (source {source})')
            continue
        path = os.path.join(OUT, f'{shell}.blade.php')
        io.open(path, 'w', encoding='utf-8', newline='\n').write(out)
        todo = out.count('data-todo=')
        print(f'  {shell:<8} {len(out):>7} octets  ({todo} liens non encore portés)')

    auth = build_auth()
    io.open(os.path.join(OUT, 'auth.blade.php'), 'w', encoding='utf-8', newline='\n').write(auth)
    print(f"  {'auth':<8} {len(auth):>7} octets  ({auth.count('data-todo=')} liens non encore portés)")


if __name__ == '__main__':
    main()
