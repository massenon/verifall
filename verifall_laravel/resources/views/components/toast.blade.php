@props(['variant' => 'citizen'])

{{--
  | Hôte des toasts — monté une fois par layout (TOAST_INSTRUCTION).
  |
  | Émission depuis un composant Livewire :
  |     $this->dispatch('toast', message: '✓ Cours enregistré.', type: 'success');
  |     $this->dispatch('toast', message: 'Supprimé', type: 'info',
  |                     undoEvent: 'restaurerCours', undoId: $id);
  |
  | Le conteneur est vide au chargement : les toasts sont créés à la volée,
  | ce qui évite d'imposer un état initial aux 3 shells.
--}}
<div id="vf-toast-host"
     class="vf-toast-host"
     data-toast-variant="{{ $variant }}"
     aria-live="polite"
     aria-atomic="false"></div>

@once
    @push('scripts')
        <script>
        (function () {
            'use strict';

            var HOST = document.getElementById('vf-toast-host');
            if (!HOST) { return; }

            var STAFF   = HOST.dataset.toastVariant === 'staff';
            var MAX     = 3;      // pile plafonnée (FIFO) — TOAST_INSTRUCTION
            var DUREE   = 3000;   // 5 s pour les erreurs
            var UNDO_MS = 6000;   // fenêtre d'annulation avant commit du Job

            var ICONES = {
                success: '✓', error: '⚠', warning: '⚠', info: 'ℹ'
            };

            function retirer(el) {
                if (!el || !el.isConnected) { return; }
                el.classList.remove('is-on');
                var parti = false;
                var go = function () { if (!parti) { parti = true; el.remove(); } };
                el.addEventListener('transitionend', go, { once: true });
                // Repli : transitionend ne se déclenche pas sous
                // prefers-reduced-motion, le toast resterait à l'écran.
                setTimeout(go, 400);
            }

            function creer(detail) {
                var type    = detail.type || 'info';
                var message = detail.message || '';

                var el = document.createElement('div');
                el.className = (STAFF ? 'st-toast' : 'vf-toast')
                    + (type === 'error' ? ' is-danger' : '')
                    + ' vf-toast--' + type;
                // Une erreur interrompt la lecture ; un succès ne le mérite pas.
                el.setAttribute('role', type === 'error' ? 'alert' : 'status');
                el.setAttribute('aria-live', type === 'error' ? 'assertive' : 'polite');

                var icone = document.createElement('span');
                icone.setAttribute('aria-hidden', 'true');
                icone.textContent = ICONES[type] || ICONES.info;

                var texte = document.createElement('span');
                // textContent et jamais innerHTML : un message de toast peut
                // reprendre un nom saisi par l'utilisateur.
                texte.textContent = message;

                el.appendChild(icone);
                el.appendChild(texte);

                if (detail.undoEvent) {
                    var undo = document.createElement('button');
                    undo.type = 'button';
                    undo.className = 'vf-toast-undo';
                    undo.textContent = 'Annuler';
                    undo.addEventListener('click', function () {
                        window.Livewire && window.Livewire.dispatch(
                            detail.undoEvent, { id: detail.undoId || null }
                        );
                        retirer(el);
                    });
                    el.appendChild(undo);
                }

                return el;
            }

            function afficher(detail) {
                // Pile FIFO : le plus ancien cède la place au nouveau.
                while (HOST.children.length >= MAX) {
                    retirer(HOST.firstElementChild);
                    if (HOST.children.length >= MAX) { HOST.firstElementChild.remove(); }
                }

                var el = creer(detail);
                HOST.appendChild(el);

                /*
                 | Entrée en scène.
                 |
                 | requestAnimationFrame donne la transition la plus propre,
                 | MAIS il est suspendu dans un onglet en arrière-plan : le
                 | toast restait alors à opacité 0 puis disparaissait sans
                 | jamais avoir été vu. Un utilisateur qui change d'onglet
                 | pendant un enregistrement perdait tout retour.
                 |
                 | Le setTimeout, lui, s'exécute toujours. Les deux voies posent
                 | la même classe : la première qui arrive gagne, l'autre est
                 | sans effet (classList.add est idempotent).
                 */
                var montrer = function () { el.classList.add('is-on'); };
                requestAnimationFrame(function () { requestAnimationFrame(montrer); });
                setTimeout(montrer, 30);

                var delai = detail.undoEvent
                    ? UNDO_MS
                    : (detail.type === 'error' ? 5000 : DUREE);

                var minuteur = setTimeout(function () { retirer(el); }, delai);

                // Pause au survol : un message long doit pouvoir être lu.
                el.addEventListener('mouseenter', function () { clearTimeout(minuteur); });
                el.addEventListener('mouseleave', function () {
                    minuteur = setTimeout(function () { retirer(el); }, 1500);
                });
            }

            // Livewire 3 : les événements dispatchés côté serveur remontent ici.
            document.addEventListener('livewire:init', function () {
                window.Livewire.on('toast', function (payload) {
                    afficher(Array.isArray(payload) ? (payload[0] || {}) : (payload || {}));
                });
            });

            /*
             | Reprise de VF.toast().
             |
             | Les scripts du prototype (citizen.js, verifall.js) sont chargés en
             | `defer` : ils s'exécutent APRÈS ce script inline et redéfinissent
             | window.VF.toast avec leur version minimale — deux types, aucun
             | empilement, aucune annulation. Sans réinstallation, notre
             | implémentation était écrasée en silence.
             |
             | Les scripts différés s'exécutent avant DOMContentLoaded : poser
             | la fonction une seconde fois dans ce gestionnaire garantit que
             | c'est la nôtre qui reste. Le piège vaudra pour tout VF.* que nous
             | réimplémenterons (confetti, visite guidée).
             */
            function installer() {
                window.VF = window.VF || {};
                window.VF.toast = function (message, type) {
                    afficher({
                        message: message,
                        type: type === 'danger' ? 'error' : (type || 'info')
                    });
                };
            }

            installer();
            document.addEventListener('DOMContentLoaded', installer);
        })();
        </script>
    @endpush
@endonce
