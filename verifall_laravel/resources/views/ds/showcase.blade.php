<x-layouts.staff title="Composants partagés">
    <div class="p-6 lg:p-8 space-y-6">

        <header>
            <p class="text-xs font-bold tracking-[.14em] text-[#059669]">SPRINT 1A</p>
            <h1 class="mt-1 text-2xl font-display font-bold text-[#0F172A]">Composants partagés</h1>
            <p class="mt-1 text-sm text-[#475569]">
                Chaque composant existe en variante <code>citizen</code> et <code>staff</code>.
                Le balisage provient du prototype ; seule la mécanique est ajoutée.
            </p>
        </header>

        {{-- ----------------------------------------------------------- KPI --}}
        <section>
            <h2 class="mb-3 font-display font-bold text-[#0F172A]">KPI + accordéon mobile</h2>
            <p class="mb-4 text-sm text-[#475569]">
                Réduisez la fenêtre sous 1024 px : la grille se replie derrière un bouton.
            </p>

            <x-kpi-grid variant="staff">
                <x-kpi variant="staff" value="8" label="Badges actifs" trend="référentiel citoyen" />
                <x-kpi variant="staff" value="14 204" label="Attributions" trend="cumulées" />
                <x-kpi variant="staff" value="27" label="En file" trend="dont 4 prioritaires" />
                <x-kpi variant="staff" value="2" label="En conception" trend="roadmap T4" />
            </x-kpi-grid>

            <x-kpi-grid variant="citizen" title="Aperçu de votre activité">
                <x-kpi value="4" label="Vérifications" />
                <x-kpi value="0" label="Cours suivis" />
                <x-kpi value="1 280" label="Points d'expérience" />
                <x-kpi value="34" label="Unités disponibles" />
            </x-kpi-grid>
        </section>

        {{-- -------------------------------------------------------- badges --}}
        <section>
            <h2 class="mb-3 font-display font-bold text-[#0F172A]">Badges de statut</h2>
            <p class="mb-3 text-sm text-[#475569]">
                Libellés de verdict imposés par le dictionnaire de copywriting, pastille
                sémantique toujours présente — jamais la couleur seule.
            </p>

            <div class="flex flex-wrap gap-2">
                @foreach (['vrai', 'faux', 'trompeur', 'non-verifiable', 'en-cours'] as $s)
                    <x-badge-statut :statut="$s" />
                @endforeach
            </div>

            <div class="mt-3 flex flex-wrap gap-2">
                @foreach (['publie', 'brouillon', 'archive', 'premium', 'danger', 'info'] as $s)
                    <x-badge-statut variant="staff" :statut="$s" />
                @endforeach
            </div>
        </section>

        {{-- --------------------------------------------------------- toast --}}
        <section>
            <h2 class="mb-3 font-display font-bold text-[#0F172A]">Toasts</h2>
            <p class="mb-3 text-sm text-[#475569]">
                Pile plafonnée à 3, pause au survol, 5 s pour les erreurs,
                6 s avec bouton « Annuler ».
            </p>

            <div class="flex flex-wrap gap-2">
                <button type="button" class="st-btn st-btn-primary" data-demo-toast="success">Succès</button>
                <button type="button" class="st-btn st-btn-danger" data-demo-toast="error">Erreur</button>
                <button type="button" class="st-btn st-btn-secondary" data-demo-toast="warning">Avertissement</button>
                <button type="button" class="st-btn st-btn-ghost" data-demo-toast="info">Information</button>
                <button type="button" class="st-btn st-btn-ghost" data-demo-toast="undo">Avec annulation</button>
            </div>
        </section>

        {{-- ------------------------------------------------- table / cartes --}}
        <section>
            <h2 class="mb-3 font-display font-bold text-[#0F172A]">Table (desktop) et cartes (mobile)</h2>

            <x-data-table variant="staff" :count="3">
                <x-slot:head>
                    <th scope="col">Signalement</th>
                    <th scope="col">Type</th>
                    <th scope="col">Verdict</th>
                    <th class="text-right">Actions</th>
                </x-slot:head>

                @foreach ([['Taxe WhatsApp 50 F', 'Texte', 'faux'], ['Faux décret présidentiel', 'Image', 'faux'], ['Talon président', 'URL', 'vrai']] as [$titre, $type, $verdict])
                    <tr data-row-el>
                        <td>{{ $titre }}</td>
                        <td>{{ $type }}</td>
                        <td><x-badge-statut variant="staff" :statut="$verdict" /></td>
                        <td class="text-right">
                            <button type="button" class="st-icnbtn" aria-label="Actions sur {{ $titre }}">
                                <span class="material-symbols-rounded" aria-hidden="true">more_vert</span>
                            </button>
                        </td>
                    </tr>
                @endforeach
            </x-data-table>

            <x-card-list variant="staff">
                @foreach ([['Taxe WhatsApp 50 F', 'Texte', 'faux'], ['Faux décret présidentiel', 'Image', 'faux'], ['Talon président', 'URL', 'vrai']] as [$titre, $type, $verdict])
                    <div class="st-card p-4">
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-semibold text-[#0F172A]">{{ $titre }}</p>
                            <x-badge-statut variant="staff" :statut="$verdict" />
                        </div>
                        <p class="mt-1 text-sm text-[#475569]">{{ $type }}</p>
                    </div>
                @endforeach
            </x-card-list>
        </section>

        {{-- ------------------------------------------------- vide / charge --}}
        <section class="grid gap-4 lg:grid-cols-2">
            <div>
                <h2 class="mb-3 font-display font-bold text-[#0F172A]">État vide</h2>
                <x-empty-state variant="staff"
                               icon="search_off"
                               title="Aucun résultat"
                               description="Essayez un autre mot-clé ou réinitialisez les filtres.">
                    <x-slot:action>
                        <button type="button" class="st-btn st-btn-secondary">Réinitialiser les filtres</button>
                    </x-slot:action>
                </x-empty-state>
            </div>

            <div>
                <h2 class="mb-3 font-display font-bold text-[#0F172A]">Chargement</h2>
                <div class="st-card p-4">
                    <x-skeleton variant="staff" :lines="4" />
                </div>
            </div>
        </section>

        {{-- ------------------------------------------------------ overlays --}}
        <section>
            <h2 class="mb-3 font-display font-bold text-[#0F172A]">Suppression nominative</h2>
            <p class="mb-3 text-sm text-[#475569]">
                La modale nomme la cible et compte les suppressions en cascade.
            </p>
            <button type="button" class="st-btn st-btn-danger" data-demo-delete>Supprimer un cours</button>
        </section>
    </div>

    <x-delete-modal variant="staff"
                    cible="OSINT Fondamentaux"
                    :cascades="['3 sections et 8 leçons', '412 inscriptions d\'apprenants', '2 exercices et leurs copies rendues']" />

    @push('scripts')
        <script>
        window.addEventListener('load', function () {
            var MESSAGES = {
                success: '✓ Cours « OSINT Fondamentaux » enregistré.',
                error: "L'import a échoué : la colonne « e-mail » est absente du fichier.",
                warning: 'Trois apprenants n’ont pas encore validé leur adresse.',
                info: 'La prochaine synchronisation du Hub aura lieu à 18 h.'
            };

            document.querySelectorAll('[data-demo-toast]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var t = b.dataset.demoToast;
                    if (t === 'undo') {
                        window.VF.toast('Signalement supprimé.', 'info');
                        return;
                    }
                    window.VF.toast(MESSAGES[t], t);
                });
            });

            var modale = document.querySelector('[data-delete-modal]');
            var fond = document.querySelector('[data-delete-backdrop]');
            function ouvrir(o) {
                modale.classList.toggle('hidden', !o);
                if (fond) { fond.classList.toggle('is-on', o); fond.style.display = o ? 'block' : 'none'; }
            }
            document.querySelectorAll('[data-demo-delete]').forEach(function (b) {
                b.addEventListener('click', function () { ouvrir(true); });
            });
            document.querySelectorAll('[data-delete-cancel]').forEach(function (b) {
                b.addEventListener('click', function () { ouvrir(false); });
            });
            document.addEventListener('keydown', function (e) {
                if (e.key === 'Escape') { ouvrir(false); }
            });
        });
        </script>
    @endpush
</x-layouts.staff>
