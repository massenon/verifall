{{--
  | « Constructeur de cours » (lot 6).
  |
  | Un écran PAR COURS, atteint par son slug : on y revient, on le partage à un
  | relecteur, il survit à un rechargement. Un tiroir ouvert depuis une liste ne
  | permet rien de tout cela.
  |
  | L'éditeur de questions REMPLACE le plan à l'écran plutôt que de s'y
  | superposer : une question porte deux à six propositions, et un tiroir dans
  | un tiroir ne se referme jamais dans le bon ordre.
--}}
<div class="p-6 lg:p-8">

    <nav class="text-xs text-[#64748B] mb-2" aria-label="Fil d'Ariane">
        <a href="{{ route('staff.dashboard') }}" class="hover:text-[#065F46]">Administration</a>
        <span aria-hidden="true">/</span>
        <a href="{{ route('staff.courses') }}" class="hover:text-[#065F46]">Tous les cours</a>
        <span aria-hidden="true">/</span>
        <span class="text-[#0F172A] font-medium">{{ Str::limit($cours->title, 40) }}</span>
    </nav>

    <header class="mb-6">
        <h1 class="text-2xl md:text-3xl font-display font-bold text-[#0F172A] leading-tight mb-1">{{ $cours->title }}</h1>
        <p class="text-[#64748B] text-sm mb-0">
            {{ $cours->categorie?->name ?? 'Sans rayon' }} · {{ $cours->level?->libelle() }} ·
            {{ $cours->is_published ? 'publié au catalogue' : 'brouillon' }}
        </p>
    </header>

    @if ($quizCourant !== null)
        {{-- ─────────────────────────────── éditeur de questions d'un quiz --}}
        <section>
            <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-4">
                <div>
                    <button type="button" wire:click="refermerLesQuestions"
                            class="text-sm text-[#059669] font-semibold underline mb-1">
                        ← Revenir au plan du cours
                    </button>
                    <h2 class="text-xl font-display font-bold text-[#0F172A] mb-0">
                        Questions — {{ $quizCourant->title }}
                    </h2>
                    <p class="text-sm text-[#64748B] mb-0">
                        Seuil de réussite {{ $quizCourant->pass_score }} % · {{ $questions->count() }} question(s)
                    </p>
                </div>

                <button type="button" wire:click="ouvrirCreationQuestion" class="st-btn st-btn-primary">
                    <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">add</span>
                    Ajouter une question
                </button>
            </div>

            @if ($questions->isEmpty())
                <x-empty-state variant="staff" icon="quiz"
                               title="Ce quiz n'a aucune question"
                               description="Un quiz sans question se termine aussitôt et compte comme réussi. Tant qu'il est vide, ne publiez pas le cours." />
            @else
                <div class="space-y-3">
                    @foreach ($questions as $rang => $question)
                        <article class="st-card p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1">
                                    <p class="text-xs text-[#64748B] mb-1">Question {{ $rang + 1 }}</p>
                                    <p class="font-semibold text-[#0F172A] mb-2">{{ $question->prompt }}</p>

                                    <ul class="list-none pl-0 mb-0 space-y-1">
                                        @foreach ($question->answers->sortBy('position') as $reponse)
                                            <li class="text-sm flex items-start gap-2">
                                                <span class="material-symbols-rounded !text-[17px] {{ $reponse->is_correct ? 'text-[#059669]' : 'text-[#64748B]' }}"
                                                      aria-hidden="true">{{ $reponse->is_correct ? 'check_circle' : 'radio_button_unchecked' }}</span>
                                                <span class="{{ $reponse->is_correct ? 'text-[#065F46] font-medium' : 'text-[#334155]' }}">{{ $reponse->label }}</span>
                                            </li>
                                        @endforeach
                                    </ul>

                                    @if ($question->explanation !== null)
                                        {{-- L'explication est le cœur pédagogique du quiz :
                                             sans elle, un quiz note sans rien apprendre. --}}
                                        <p class="text-xs text-[#64748B] mt-2 mb-0">
                                            <span class="font-semibold">Explication :</span> {{ Str::limit($question->explanation, 160) }}
                                        </p>
                                    @endif
                                </div>

                                <div class="whitespace-nowrap">
                                    <button type="button" wire:click="ouvrirEditionQuestion('{{ $question->uuid }}')"
                                            class="st-icnbtn" title="Modifier">
                                        <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">edit</span>
                                    </button>
                                    <button type="button" wire:click="demanderLaSuppression('question', '{{ $question->uuid }}')"
                                            class="st-icnbtn text-[#DC2626]" title="Supprimer">
                                        <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">delete</span>
                                    </button>
                                </div>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    @else
        {{-- ──────────────────────────────────────────── le plan du cours --}}
        <div class="grid lg:grid-cols-2 gap-6">

            <section>
                <div class="flex items-end justify-between gap-3 mb-3">
                    <div>
                        <h2 class="text-lg font-display font-bold text-[#0F172A] mb-0">Leçons</h2>
                        <p class="text-xs text-[#64748B] mb-0">{{ $lecons->count() }} leçon(s), dans l'ordre du parcours</p>
                    </div>
                    @can('create', App\Models\Lesson::class)
                        <button type="button" wire:click="ouvrirCreationLecon" class="st-btn st-btn-secondary">
                            <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">add</span>
                            Leçon
                        </button>
                    @endcan
                </div>

                @if ($lecons->isEmpty())
                    <x-empty-state variant="staff" icon="menu_book"
                                   title="Aucune leçon"
                                   description="Un cours sans leçon ne peut pas être publié : le catalogue l'afficherait et les inscrits tomberaient sur une page vide." />
                @else
                    <div class="space-y-2">
                        @foreach ($lecons as $lecon)
                            <article class="st-card p-3 flex items-center gap-3">
                                <span class="text-xs font-bold text-[#64748B] w-8 text-center">{{ $lecon->position }}</span>

                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-[#0F172A] mb-0 truncate">{{ $lecon->title }}</p>
                                    <p class="text-xs text-[#64748B] mb-0">
                                        {{ $lecon->type?->libelle() ?? $lecon->type }} · {{ $lecon->minutes }} min
                                    </p>
                                </div>

                                <div class="whitespace-nowrap">
                                    @can('update', $lecon)
                                        {{-- Les flèches disparaissent en bout de liste. Le service
                                             ne lève rien si on l'appelle quand même : un bouton
                                             masqué n'est pas une garde. --}}
                                        @unless ($loop->first)
                                            <button type="button" wire:click="deplacerLaLecon('{{ $lecon->uuid }}', 'haut')"
                                                    class="st-icnbtn" title="Monter">
                                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">arrow_upward</span>
                                            </button>
                                        @endunless
                                        @unless ($loop->last)
                                            <button type="button" wire:click="deplacerLaLecon('{{ $lecon->uuid }}', 'bas')"
                                                    class="st-icnbtn" title="Descendre">
                                                <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">arrow_downward</span>
                                            </button>
                                        @endunless
                                        <button type="button" wire:click="ouvrirEditionLecon('{{ $lecon->uuid }}')"
                                                class="st-icnbtn" title="Modifier">
                                            <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">edit</span>
                                        </button>
                                    @endcan
                                    @can('delete', $lecon)
                                        <button type="button" wire:click="demanderLaSuppression('lecon', '{{ $lecon->uuid }}')"
                                                class="st-icnbtn text-[#DC2626]" title="Supprimer">
                                            <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">delete</span>
                                        </button>
                                    @endcan
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <section>
                <div class="flex items-end justify-between gap-3 mb-3">
                    <div>
                        <h2 class="text-lg font-display font-bold text-[#0F172A] mb-0">Quiz</h2>
                        <p class="text-xs text-[#64748B] mb-0">{{ $quiz->count() }} quiz, évalués sur leur seuil</p>
                    </div>
                    @can('create', App\Models\Quiz::class)
                        <button type="button" wire:click="ouvrirCreationQuiz" class="st-btn st-btn-secondary">
                            <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">add</span>
                            Quiz
                        </button>
                    @endcan
                </div>

                @if ($quiz->isEmpty())
                    <x-empty-state variant="staff" icon="quiz"
                                   title="Aucun quiz"
                                   description="Un cours peut se passer de quiz. S'il en a un, c'est lui qui décide de l'achèvement du parcours — et donc du certificat." />
                @else
                    <div class="space-y-2">
                        @foreach ($quiz as $unQuiz)
                            <article class="st-card p-3 flex items-center gap-3">
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-[#0F172A] mb-0 truncate">{{ $unQuiz->title }}</p>
                                    <p class="text-xs mb-0">
                                        @if ($unQuiz->questions_count === 0)
                                            {{-- Un quiz vide se termine aussitôt et compte comme
                                                 réussi : c'est un défaut, pas un état neutre. --}}
                                            <span class="text-[#DC2626] font-semibold">0 question</span>
                                        @else
                                            <span class="text-[#64748B]">{{ $unQuiz->questions_count }} question(s)</span>
                                        @endif
                                        <span class="text-[#64748B]">· seuil {{ $unQuiz->pass_score }} % · {{ $unQuiz->minutes }} min</span>
                                    </p>
                                </div>

                                <div class="whitespace-nowrap">
                                    @can('update', $unQuiz)
                                        <button type="button" wire:click="ouvrirLesQuestions('{{ $unQuiz->uuid }}')"
                                                class="st-icnbtn" title="Ouvrir les questions">
                                            <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">list_alt</span>
                                        </button>
                                        <button type="button" wire:click="ouvrirEditionQuiz('{{ $unQuiz->uuid }}')"
                                                class="st-icnbtn" title="Modifier">
                                            <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">edit</span>
                                        </button>
                                    @endcan
                                    @can('delete', $unQuiz)
                                        <button type="button" wire:click="demanderLaSuppression('quiz', '{{ $unQuiz->uuid }}')"
                                                class="st-icnbtn text-[#DC2626]" title="Supprimer">
                                            <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">delete</span>
                                        </button>
                                    @endcan
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    @endif

    {{-- ──────────────────────────────────────────────── tiroir : leçon --}}
    <x-offcanvas-form :open="$tiroirLecon"
                      :title="$leconEnEdition === null ? 'Nouvelle leçon' : 'Modifier la leçon'"
                      close="fermerLeTiroirLecon">
        <form wire:submit="enregistrerLaLecon" class="space-y-4">
            <div class="ffield">
                <input id="lec-titre" type="text" wire:model="leconTitre" placeholder=" ">
                <label for="lec-titre">Titre de la leçon</label>
                @error('leconTitre') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="grid sm:grid-cols-2 gap-4">
                <div>
                    <label for="lec-type" class="block text-sm font-medium text-[#0F172A] mb-1">Type</label>
                    <select id="lec-type" wire:model.live="leconType" class="st-input w-full">
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}">{{ $type->libelle() }}</option>
                        @endforeach
                    </select>
                    @error('leconType') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="ffield mb-0">
                    <input id="lec-min" type="number" min="1" max="600" wire:model="leconMinutes" placeholder=" ">
                    <label for="lec-min">Durée (minutes)</label>
                    @error('leconMinutes') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            </div>

            @if ($leconType === 'video')
                <div class="ffield">
                    <input id="lec-video" type="url" wire:model="leconVideo" placeholder=" ">
                    <label for="lec-video">Adresse de la vidéo</label>
                    @error('leconVideo') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            @elseif ($leconType === 'audio')
                {{-- L'ADRESSE, pas le fichier. Héberger l'audio du cours sur le
                     serveur ferait porter la bande passante de la lecture à un
                     hébergement mutualisé — celui-là même où aucun démon ne
                     tourne. Les documents joints, eux, sont déposés : on les
                     télécharge une fois, on ne les diffuse pas en continu. --}}
                <div class="ffield">
                    <input id="lec-audio" type="url" wire:model="leconAudio" placeholder=" ">
                    <label for="lec-audio">Adresse de l'audio</label>
                    <p class="text-xs text-[#64748B] mt-1 mb-0">
                        Lien direct vers le fichier, ou vers l'épisode hébergé.
                    </p>
                    @error('leconAudio') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            @endif

            <div>
                <label class="block text-sm font-medium text-[#0F172A] mb-1">
                    Contenu {{ $leconType === 'video' ? '(facultatif — sous la vidéo)' : '' }}
                </label>
                {{-- Quill écrit dans son propre DOM : sans wire:ignore, le premier
                     rendu de Livewire l'effacerait. --}}
                <div class="st-quill" wire:ignore>
                    <div data-quill-lecon></div>
                </div>
                @error('leconContenu') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                <p class="text-xs text-[#64748B] mt-1 mb-0">
                    Le HTML est purifié avant enregistrement : la leçon est rendue à des citoyens.
                </p>
            </div>

            {{-- ── DOCUMENTS JOINTS ──────────────────────────────────────────
                 EN ÉDITION SEULEMENT : une leçon qui n'existe pas encore n'a
                 pas d'identifiant, et rien ne peut lui être attaché. Mêler le
                 dépôt à l'enregistrement obligerait à garder les fichiers en
                 mémoire jusqu'à la création — et à les perdre si la validation
                 échoue.

                 Le dépôt est donc un geste à part, hors du `<form>` : il part
                 dès que le fichier est choisi. --}}
            @if ($leconEnEdition !== null)
                <div class="rounded-xl border border-line p-3">
                    <p class="text-sm font-medium text-[#0F172A] mb-2">Documents joints</p>

                    @php($leconOuverte = $lecons->firstWhere('uuid', $leconEnEdition))

                    @forelse ($leconOuverte?->pieces ?? [] as $document)
                        <div class="flex items-center justify-between gap-2 py-1.5 border-b border-line last:border-0"
                             wire:key="piece-{{ $document->uuid }}">
                            <span class="text-sm text-[#0F172A] min-w-0 truncate">
                                <span class="material-symbols-rounded !text-[17px]" aria-hidden="true">{{ $document->icone() }}</span>
                                {{ $document->original_name }}
                                <span class="text-xs text-[#64748B]">· {{ $document->poidsLisible() }}</span>
                            </span>
                            <button type="button" wire:click="detacherLaPiece('{{ $document->uuid }}')"
                                    class="st-icnbtn text-[#DC2626] shrink-0" title="Retirer">
                                <span class="material-symbols-rounded !text-[18px]" aria-hidden="true">delete</span>
                            </button>
                        </div>
                    @empty
                        <p class="text-xs text-[#64748B] mb-2">Aucun document pour l'instant.</p>
                    @endforelse

                    <input type="file" wire:model="piece" class="st-input w-full mt-2"
                           aria-label="Ajouter un document">
                    <div wire:loading wire:target="piece" class="text-xs text-[#64748B] mt-1">Envoi en cours…</div>
                    @error('piece') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror

                    <button type="button" wire:click="attacherLaPiece" wire:loading.attr="disabled"
                            class="st-btn st-btn-secondary mt-2">Joindre</button>

                    <p class="text-xs text-[#64748B] mt-2 mb-0">
                        PDF, Word, Excel, PowerPoint, images, ZIP — 20 Mo maximum. Les fichiers
                        vivent hors du site : seuls les inscrits au cours peuvent les ouvrir.
                    </p>
                </div>
            @endif

            <div class="flex gap-2 pt-2">
                <button type="submit" class="st-btn st-btn-primary flex-1">Enregistrer</button>
                <button type="button" wire:click="fermerLeTiroirLecon" class="st-btn st-btn-secondary">Annuler</button>
            </div>
        </form>
    </x-offcanvas-form>

    {{-- ───────────────────────────────────────────────── tiroir : quiz --}}
    <x-offcanvas-form :open="$tiroirQuiz"
                      :title="$quizEnEdition === null ? 'Nouveau quiz' : 'Modifier le quiz'"
                      close="fermerLeTiroirQuiz">
        <form wire:submit="enregistrerLeQuiz" class="space-y-4">
            <div class="ffield">
                <input id="qz-titre" type="text" wire:model="quizTitre" placeholder=" ">
                <label for="qz-titre">Titre du quiz</label>
                @error('quizTitre') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="grid sm:grid-cols-3 gap-4">
                <div class="ffield mb-0">
                    <input id="qz-min" type="number" min="1" max="180" wire:model="quizMinutes" placeholder=" ">
                    <label for="qz-min">Durée (min)</label>
                    @error('quizMinutes') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="ffield mb-0">
                    <input id="qz-seuil" type="number" min="50" max="100" wire:model="quizSeuil" placeholder=" ">
                    <label for="qz-seuil">Seuil (%)</label>
                    @error('quizSeuil') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>

                <div class="ffield mb-0">
                    <input id="qz-xp" type="number" min="0" max="1000" wire:model="quizXp" placeholder=" ">
                    <label for="qz-xp">Points XP</label>
                    @error('quizXp') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
                </div>
            </div>

            <p class="text-xs text-[#64748B] mb-0">
                Le seuil ne descend pas sous 50 % : en dessous, « réussir » voudrait dire
                se tromper plus souvent qu'avoir juste, et le certificat ne vaudrait plus rien.
            </p>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="st-btn st-btn-primary flex-1">Enregistrer</button>
                <button type="button" wire:click="fermerLeTiroirQuiz" class="st-btn st-btn-secondary">Annuler</button>
            </div>
        </form>
    </x-offcanvas-form>

    {{-- ───────────────────────────────────────────── tiroir : question --}}
    <x-offcanvas-form :open="$tiroirQuestion"
                      :title="$questionEnEdition === null ? 'Nouvelle question' : 'Modifier la question'"
                      close="fermerLeTiroirQuestion">
        <form wire:submit="enregistrerLaQuestion" class="space-y-4">
            <div class="ffield">
                <textarea id="qn-enonce" wire:model="questionEnonce" rows="3" placeholder=" "></textarea>
                <label for="qn-enonce">Énoncé</label>
                @error('questionEnonce') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div>
                <p class="block text-sm font-medium text-[#0F172A] mb-2">Propositions</p>

                @error('propositions') <p class="text-sm text-[#DC2626] mb-2">{{ $message }}</p> @enderror

                <div class="space-y-2">
                    @foreach ($propositions as $rang => $proposition)
                        <div class="flex items-start gap-2">
                            {{-- La case « correcte » est un vrai choix multiple : une
                                 question peut avoir plusieurs bonnes réponses. --}}
                            <input type="checkbox" wire:model="propositions.{{ $rang }}.correcte"
                                   class="mt-3" aria-label="Proposition correcte">

                            <div class="ffield flex-1 mb-0">
                                <input id="qn-prop-{{ $rang }}" type="text"
                                       wire:model="propositions.{{ $rang }}.label" placeholder=" ">
                                <label for="qn-prop-{{ $rang }}">Proposition {{ $rang + 1 }}</label>
                                @error('propositions.'.$rang.'.label')
                                    <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p>
                                @enderror
                            </div>

                            @if (count($propositions) > 2)
                                <button type="button" wire:click="retirerUneProposition({{ $rang }})"
                                        class="st-icnbtn text-[#DC2626] mt-1" title="Retirer">
                                    <span class="material-symbols-rounded !text-[19px]" aria-hidden="true">close</span>
                                </button>
                            @endif
                        </div>
                    @endforeach
                </div>

                @if (count($propositions) < 6)
                    <button type="button" wire:click="ajouterUneProposition"
                            class="text-sm text-[#059669] font-semibold underline mt-2">
                        + Ajouter une proposition
                    </button>
                @endif
            </div>

            <div class="ffield">
                <textarea id="qn-expl" wire:model="questionExplication" rows="3" placeholder=" "></textarea>
                <label for="qn-expl">Explication</label>
                <p class="text-xs text-[#64748B] mt-1 mb-0">
                    Affichée après la réponse. Sans elle, un quiz note sans rien apprendre.
                </p>
                @error('questionExplication') <p class="text-sm text-[#DC2626] mt-1 mb-0">{{ $message }}</p> @enderror
            </div>

            <div class="flex gap-2 pt-2">
                <button type="submit" class="st-btn st-btn-primary flex-1">Enregistrer</button>
                <button type="button" wire:click="fermerLeTiroirQuestion" class="st-btn st-btn-secondary">Annuler</button>
            </div>
        </form>
    </x-offcanvas-form>

    <x-delete-modal :open="$aSupprimer !== null"
                    title="Supprimer cet élément ?"
                    description="La suppression est définitive. Les leçons suivantes se renumérotent automatiquement ; supprimer un quiz emporte ses questions et leurs propositions."
                    confirm="supprimer"
                    cancel="annulerLaSuppression" />
</div>

{{-- ------------------------------------------------------ pont vers Quill --}}
@push('scripts')
    <script src="{{ asset('assets/js/vendor/quill.js') }}"></script>
    <script>
        /* `livewire:initialized` et non `livewire:init` : à `init` les composants
           ne sont pas encore enregistrés et `@this` vaut `undefined`. */
        document.addEventListener('livewire:initialized', function () {
            var composant = @this;
            var hote = document.querySelector('[data-quill-lecon]');

            if (!hote || typeof Quill === 'undefined') { return; }

            var editeur = new Quill(hote, {
                theme: 'snow',
                modules: {
                    toolbar: [
                        [{ header: [2, 3, false] }],
                        ['bold', 'italic', 'underline'],
                        [{ list: 'ordered' }, { list: 'bullet' }],
                        ['blockquote', 'code-block', 'link'],
                        ['clean'],
                    ],
                },
                placeholder: 'Rédigez la leçon…',
            });

            /* Le chargement programmatique déclenche « text-change » lui aussi :
               sans ce verrou, ouvrir une leçon la marquerait aussitôt modifiée. */
            var enChargement = false;

            editeur.on('text-change', function () {
                if (enChargement) { return; }

                var html = editeur.root.innerHTML;

                /* Troisième argument à false : rien ne part à chaque frappe. La
                   valeur accompagne le prochain « Enregistrer ». */
                composant.set('leconContenu', html === '<p><br></p>' ? '' : html, false);
            });

            /* L'hôte est en wire:ignore : sans cet événement, ouvrir une seconde
               leçon afficherait le contenu de la première — et l'enregistrer
               l'écraserait par elle. */
            composant.on('contenu-lecon-charge', function (charge) {
                var detail = Array.isArray(charge) ? charge[0] : charge;

                enChargement = true;
                editeur.root.innerHTML = (detail && detail.html) ? detail.html : '';
                enChargement = false;
            });
        });
    </script>
@endpush
