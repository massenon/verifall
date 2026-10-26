{{--
  | « Infolettre » — transposition de compte-admin/newsletters.html.
  |
  | Gabarit CRUD du prototype : fil d'Ariane, en-tête, accordéon d'indicateurs,
  | carte de recherche, compteur de résultats, table sur grand écran et cartes
  | sur téléphone, pagination serveur.
  |
  | Deux écarts au prototype, tous deux consignés aux arbitrages 155 et 156 :
  |   · les colonnes « Ouvertures » et « Clics » sont REMPLACÉES par les trois
  |     compteurs réels — rien ne mesure les ouvertures ici, et une colonne
  |     « 51 % » sans traceur est un chiffre inventé ;
  |   · les actions de masse (publier, archiver, dupliquer) sont retirées :
  |     aucune ne s'applique à une édition automatique déjà partie.
  |
  | Onglet « Abonnés » : DataTables, contrat complet dans
  | `docs/DATATABLES_CONTRAT.md`.
--}}
@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.dataTables-2.3.8.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/buttons.dataTables-3.2.6.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/dataTables.dateTime-1.6.3.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/vendor/datatables/css/responsive.dataTables-3.0.8.min.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/css/pages/datatables-staff.css') }}">
@endpush

<div>

<x-premium.entete titre="Infolettre" :fil="['Infolettre']">
        Le récapitulatif du lundi part tout seul, par tâche planifiée. Édition en cours : <span class="font-mono text-encre dark:text-white">{{ $editionEnCours }}</span>.
    </x-premium.entete>

    {{-- ══════════════════════════════════════ le rendez-vous est-il tenu ? --}}
    @if ($derniereEdition === null)
        <div class="carte p-4 mb-4 !border-amber-500/40">
            <p class="text-sm font-bold text-encre dark:text-white mb-1">Aucune édition n'est encore partie.</p>
            <p class="text-sm text-encre-2 dark:text-nuit-2 mb-0">
                C'est normal avant le premier lundi. Passé ce délai, vérifiez que
                <code class="font-mono text-xs">php artisan schedule:run</code> tourne bien toutes les minutes
                sur le serveur : sans lui, la commande ne produit aucune erreur — elle ne se produit pas.
            </p>
        </div>
    @elseif ($joursDepuisEnvoi !== null && $joursDepuisEnvoi > \App\Livewire\Staff\Infolettre::JOURS_AVANT_ALERTE)
        <div class="carte p-4 mb-4 !border-critique/40">
            <p class="text-sm font-bold text-encre dark:text-white mb-1">
                Dernier envoi il y a {{ $joursDepuisEnvoi }} jours — le rendez-vous est manqué.
            </p>
            <p class="text-sm text-encre-2 dark:text-nuit-2 mb-0">
                Deux lundis sans infolettre suffisent à perdre l'habitude des lecteurs.
                Vérifiez le planificateur, puis la file d'attente.
            </p>
        </div>
    @endif

    <x-kpi-grid variant="staff" :cols="4">
        <x-kpi variant="staff" :value="number_format($indicateurs['confirmes'], 0, ',', ' ')"
               label="Abonnés confirmés"
               :trend="'+'.number_format($indicateurs['nouveaux_30j'], 0, ',', ' ').' (30 j)'" />

        {{-- « En attente » n'est pas un détail technique : une file qui gonfle
             signale des courriels de confirmation qui n'arrivent pas. --}}
        <x-kpi variant="staff" :value="number_format($indicateurs['en_attente'], 0, ',', ' ')"
               label="En attente de confirmation" trend="double opt-in non validé" />

        <x-kpi variant="staff" :value="number_format($indicateurs['desabonnes'], 0, ',', ' ')"
               label="Désabonnés" trend="consentement retiré" />

        <x-kpi variant="staff"
               :value="$derniereEdition?->sent_at?->format('d/m/Y') ?? '—'"
               label="Dernier envoi"
               :trend="$joursDepuisEnvoi !== null ? 'il y a '.$joursDepuisEnvoi.' jour(s)' : 'jamais envoyée'" />
    </x-kpi-grid>

    {{-- ═════════════════════════════════════════════════════════ onglets --}}
    <div class="onglets mb-4" role="tablist">
        <button type="button" role="tab" wire:click="changerOnglet('abonnes')"
                class="onglet {{ $onglet === 'abonnes' ? 'is-active' : '' }}"
                aria-selected="{{ $onglet === 'abonnes' ? 'true' : 'false' }}">
            Abonnés
        </button>
        <button type="button" role="tab" wire:click="changerOnglet('historique')"
                class="onglet {{ $onglet === 'historique' ? 'is-active' : '' }}"
                aria-selected="{{ $onglet === 'historique' ? 'true' : 'false' }}">
            Historique des envois
        </button>
        @if ($peutDiffuser)
            {{-- L'onglet n'apparaît qu'à qui détient `newsletter.send`. Consulter
                 la liste et écrire à trois mille personnes ne sont pas le même
                 geste ; la matrice les séparait déjà, l'écran le reflète. --}}
            <button type="button" role="tab" wire:click="changerOnglet('diffuser')"
                    class="onglet {{ $onglet === 'diffuser' ? 'is-active' : '' }}"
                    aria-selected="{{ $onglet === 'diffuser' ? 'true' : 'false' }}">
                Diffuser
            </button>
        @endif
    </div>

    {{--
      | L'onglet « Abonnés » est TOUJOURS dans le DOM, et seulement `hidden`
      | hors de son onglet — jamais `@if ($onglet === 'abonnes')` : la zone
      | DataTables qu'il contient est `wire:ignore`, et une table mesurée dans
      | un panneau retiré du DOM (ou jamais monté) calcule mal ses colonnes à
      | la réouverture. `staff-infolettre-abonnes.js` monte au premier
      | affichage et réajuste ensuite à chaque retour sur l'onglet — voir
      | `docs/DATATABLES_CONTRAT.md` §9 bis.
    --}}
    <div wire:key="infolettre-abonnes" data-ifl-panneau="abonnes" @if ($onglet !== 'abonnes') hidden @endif>

        <div wire:ignore
             data-vdt="abonnes"
             data-vdt-source="{{ route('staff.newsletter.data') }}"
             data-vdt-langue="{{ asset('assets/vendor/datatables/i18n/fr-FR-2.3.8.json') }}"
             data-vdt-vendor="{{ asset('assets/vendor/datatables/js') }}"
             data-vdt-evenement="{{ \App\Livewire\Staff\Infolettre::EVENEMENT_TABLE_A_RECHARGER }}">

        <section class="mb-4 flex flex-wrap items-center gap-3">
            <div class="flex-1 mb-0">
                <label for="abonnes-q" class="etiquette">Rechercher une adresse, un nom</label>
<input id="abonnes-q" type="search" data-vdt-recherche data-vdt-url="q" value="{{ $recherche }}" class="champ">
            </div>

            <select data-vdt-filtre="etat" data-vdt-url="etat" class="champ w-auto" aria-label="Filtrer par état">
                <option value="">État : tous</option>
                <option value="confirmes" @selected($filtreEtat === 'confirmes')>Confirmés</option>
                <option value="en-attente" @selected($filtreEtat === 'en-attente')>En attente</option>
                <option value="desabonnes" @selected($filtreEtat === 'desabonnes')>Désabonnés</option>
            </select>

            <div class="mb-0">
                <label class="etiquette">Inscrit depuis le</label>
<input type="text" data-vdt-filtre="inscrit_du" data-vdt-url="du" data-vdt-date class="champ">
            </div>
            <div class="mb-0">
                <label class="etiquette">jusqu'au</label>
<input type="text" data-vdt-filtre="inscrit_au" data-vdt-url="au" data-vdt-date class="champ">
            </div>

            <button type="button" data-vdt-reinitialiser class="btn-second">
                Réinitialiser
            </button>
        </section>

            <div role="alert" hidden data-vdt-erreur class="rounded-lg border border-red-300 dark:border-red-900 bg-red-50 dark:bg-red-950/40 text-red-800 dark:text-red-300 text-sm px-4 py-3 mb-4">
                <p data-vdt-erreur-message class="mb-2">Impossible de charger les abonnés.</p>
                <button type="button" data-vdt-reessayer class="btn-second">Réessayer</button>
            </div>

            <table id="table-abonnes" style="width:100%" data-vdt-table>
                <thead>
                    <tr>
                        <th scope="col">Adresse</th>
                        <th scope="col">Inscription</th>
                        <th scope="col">Confirmation</th>
                        <th scope="col">État</th>
                        <th scope="col" class="text-right">Actions</th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    @if ($onglet === 'historique')

        {{-- ══════════════════════════════════════════ historique des envois --}}
        @if ($envois === null || $envois->isEmpty())
            <x-empty-state variant="staff" icon="outgoing_mail"
                           title="Aucune édition envoyée"
                           description="La première partira au prochain passage de la tâche planifiée, s'il y a des fact-checks publiés dans les sept jours écoulés.">
            </x-empty-state>
        @else
            <x-data-table variant="staff" :count="$envois->total()" :selectable="false">
                <x-slot:head>
                    <th scope="col">Édition</th>
                    <th scope="col">Envoi</th>
                    <th scope="col">Destinataires</th>
                    <th scope="col">Envoyés</th>
                    <th scope="col">Échoués</th>
                    <th scope="col">Statut</th>
                </x-slot:head>

                @foreach ($envois as $envoi)
                    @php $statut = \App\Core\Marketing\Enums\StatutCampagne::depuis($envoi->status); @endphp
                    <tr data-row-el wire:key="envoi-{{ $envoi->id }}">
                        <td>
                            <span class="font-bold text-encre dark:text-white">{{ $envoi->subject }}</span>
                            <span class="block text-xs text-encre-3 dark:text-nuit-3 font-mono">{{ $envoi->edition_key }}</span>
                        </td>
                        <td class="text-encre-3 dark:text-nuit-3">{{ $envoi->sent_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td>{{ number_format((int) $envoi->recipients_count, 0, ',', ' ') }}</td>
                        <td class="font-bold text-encre dark:text-white">{{ number_format((int) $envoi->sent_count, 0, ',', ' ') }}</td>
                        {{-- Les échecs ne sont jamais masqués, même à zéro : une
                             colonne qui disparaît quand tout va bien ne
                             s'apprend pas, et on ne la cherche pas le jour où
                             elle compterait. --}}
                        <td class="{{ (int) $envoi->failed_count > 0 ? 'font-bold text-critique' : 'text-encre-3 dark:text-nuit-3' }}">
                            {{ number_format((int) $envoi->failed_count, 0, ',', ' ') }}
                        </td>
                        <td><x-badge-statut variant="staff" :statut="$statut->pastille()" :label="$statut->libelle()" /></td>
                    </tr>
                @endforeach
            </x-data-table>

            <x-card-list variant="staff">
                @foreach ($envois as $envoi)
                    @php $statut = \App\Core\Marketing\Enums\StatutCampagne::depuis($envoi->status); @endphp
                    <div class="carte p-4" wire:key="envoi-carte-{{ $envoi->id }}">
                        <div class="flex items-start justify-between gap-3">
                            <p class="font-semibold text-encre dark:text-white mb-0 flex-1">{{ $envoi->subject }}</p>
                            <x-badge-statut variant="staff" :statut="$statut->pastille()" :label="$statut->libelle()" />
                        </div>
                        <p class="mt-2 text-sm text-encre-2 dark:text-nuit-2 mb-0">
                            {{ number_format((int) $envoi->recipients_count, 0, ',', ' ') }} destinataire(s) ·
                            {{ number_format((int) $envoi->sent_count, 0, ',', ' ') }} envoyé(s) ·
                            <span class="{{ (int) $envoi->failed_count > 0 ? 'font-bold text-critique' : '' }}">{{ number_format((int) $envoi->failed_count, 0, ',', ' ') }} échec(s)</span>
                        </p>
                        <p class="mt-1 text-xs text-encre-3 dark:text-nuit-3 mb-0">{{ $envoi->sent_at?->format('d/m/Y H:i') ?? 'Pas encore partie' }}</p>
                    </div>
                @endforeach
            </x-card-list>

            <div class="mt-6">{{ $envois->links() }}</div>
        @endif

    @endif

    {{-- ═══════════════════════════════════════════ diffuser une infolettre --}}
    @if ($onglet === 'diffuser' && $peutDiffuser)

        <form wire:submit="confirmerLaDiffusion" class="carte p-5 max-w-3xl">
            <h2 class="text-lg font-bold text-encre dark:text-white mb-1">Diffuser une Newsletter</h2>

            {{-- Le nombre, AVANT le formulaire et de nouveau dans la modale.
                 C'est lui qui fait hésiter au bon moment, pas un avertissement. --}}
            <p class="text-sm text-encre-3 dark:text-nuit-3 mb-5">
                Votre message sera envoyé à
                <strong class="text-encre dark:text-white">{{ number_format($destinataires, 0, ',', ' ') }}</strong>
                abonné{{ $destinataires > 1 ? 's' : '' }}.
            </p>

            <div class="mb-4">
                <div class="mb-1">
                    <label for="diff-objet" class="etiquette">Sujet de l'email (Objet) *</label>
<input id="diff-objet" type="text" wire:model.blur="objet"
                           maxlength="{{ $objetMaximum }}" class="champ">
                </div>
                @error('objet') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="mb-5">
                <label class="block text-sm font-medium text-encre dark:text-white mb-1">Contenu du message *</label>
                <div class="editeur-riche" wire:ignore>
                    <div data-quill-diffusion></div>
                </div>
                @error('corps') <p class="text-sm text-critique mt-1 mb-0">{{ $message }}</p> @enderror
                <p class="text-xs text-encre-3 dark:text-nuit-3 mt-1 mb-0">
                    Le HTML est purifié avant enregistrement. Un lien de désabonnement est
                    ajouté automatiquement : un envoi de masse qui n'en porte pas est une infraction.
                </p>
            </div>

            <button type="submit" class="btn-primaire" @disabled($destinataires === 0)>
                Envoyer
            </button>

            @if ($destinataires === 0)
                <p class="text-sm text-encre-3 dark:text-nuit-3 mt-2 mb-0">
                    Aucun abonné confirmé pour l'instant : il n'y a personne à qui écrire.
                </p>
            @endif
        </form>

        {{--
          | La confirmation.
          |
          | Les classes sont celles de `<x-delete-modal>` — `st-oc-backdrop`,
          | `st-card`, et le `z-index:90` EN LIGNE. Ce dernier n'est pas un
          | détail recopié machinalement : le build Tailwind est figé et ne
          | contient aucune valeur arbitraire, si bien qu'un `z-50` laisserait
          | la modale SOUS le fond du tiroir staff (z-79), grisée et
          | inutilisable. Le composant existant porte déjà ce commentaire ;
          | il a été suivi plutôt que redécouvert.
          |
          | Pourquoi pas `<x-delete-modal>` directement : il annonce une
          | suppression, du rouge, et un « Supprimer ». Diffuser n'est pas
          | détruire — c'est irréversible autrement.
        --}}
        @if ($confirmation)
            <div class="voile !z-[79]" wire:click="annulerLaDiffusion" aria-hidden="true"></div>

            <div class="carte fixed left-4 right-4 top-1/2 -translate-y-1/2 mx-auto max-w-md p-6"
                 style="z-index:90"
                 role="alertdialog" aria-modal="true"
                 aria-labelledby="diff-titre" aria-describedby="diff-desc">

                <h2 id="diff-titre" class="text-lg font-titre font-bold text-encre dark:text-white mb-2">
                    Diffuser maintenant ?
                </h2>

                <p class="text-sm text-encre-2 dark:text-nuit-2 mb-2">
                    <strong>{{ number_format($destinataires, 0, ',', ' ') }}</strong>
                    abonné{{ $destinataires > 1 ? 's' : '' }} recevront
                    «&nbsp;{{ $objet }}&nbsp;».
                </p>

                {{-- L'irréversibilité ne se corrige pas : elle s'annonce. --}}
                <p id="diff-desc" class="text-sm text-encre-3 dark:text-nuit-3 mb-5">
                    Un courriel parti ne se rappelle pas. Relisez l'objet et le contenu :
                    c'est le dernier moment où c'est possible.
                </p>

                <div class="flex gap-2 justify-end">
                    <button type="button" wire:click="annulerLaDiffusion" class="btn-fantome">
                        Annuler
                    </button>
                    <button type="button" wire:click="diffuser" wire:loading.attr="disabled"
                            class="btn-primaire">
                        Oui, diffuser
                    </button>
                </div>
            </div>
        @endif

    @endif
</div>

@push('scripts')
  <script src="{{ asset('assets/vendor/datatables/js/jquery-3.7.1.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables-2.3.8.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.buttons-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/buttons.html5-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/buttons.print-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/buttons.colVis-3.2.6.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.dateTime-1.6.3.min.js') }}"></script>
  <script src="{{ asset('assets/vendor/datatables/js/dataTables.responsive-3.0.8.min.js') }}"></script>
  <script src="{{ asset('assets/js/verifall-datatables.js') }}"></script>
  <!--
    Ce fichier de page est INLINÉ, et pas chargé en externe comme les autres
    écrans : son Livewire.hook('morphed', …) doit être enregistré dès le
    premier rendu, y compris une arrivée directe sur un onglet où le panneau
    Abonnés est hidden — une requête réseau supplémentaire avant
    l'enregistrement du crochet laisserait une fenêtre où un premier
    changement d'onglet ne serait pas vu. Source : assets/js/pages/staff-infolettre-abonnes.js
  -->
  <script>{!! file_get_contents(public_path('assets/js/pages/staff-infolettre-abonnes.js')) !!}</script>
@endpush

{{-- ------------------------------------------------------ pont vers Quill --}}
@push('scripts')
    <script src="{{ asset('assets/js/vendor/quill.js') }}"></script>
    <script>
        document.addEventListener('livewire:initialized', function () {
            var composant = @this;
            var hote = document.querySelector('[data-quill-diffusion]');

            if (!hote || typeof Quill === 'undefined') { return; }

            var editeur = new Quill(hote, {
                theme: 'snow',
                modules: {
                    toolbar: [
                        [{ header: [2, 3, false] }],
                        ['bold', 'italic', 'underline'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['blockquote', 'link'],
                        ['clean'],
                    ],
                },
                placeholder: 'Rédigez le message…',
            });

            var enChargement = false;

            editeur.on('text-change', function () {
                if (enChargement) { return; }

                var html = editeur.root.innerHTML;

                // Quill n'est jamais « vide » : un éditeur intact vaut
                // `<p><br></p>`, qui passerait la règle `required`.
                composant.set('corps', html === '<p><br></p>' ? '' : html, false);
            });

            composant.on('corps-diffusion-charge', function (charge) {
                var detail = Array.isArray(charge) ? charge[0] : charge;

                enChargement = true;
                editeur.root.innerHTML = (detail && detail.html) ? detail.html : '';
                enChargement = false;
            });
        });
    </script>
@endpush
