{{-- Le geste offert dépend de l'état : on ne propose pas de passer un quiz
     dont le cours n'est pas suivi — le bouton mènerait à un refus.

     La même raison vaut pour les LEÇONS NON LUES, et ce cas manquait (B-05) :
     « inscrit » suffisait à afficher « Passer le quiz », alors qu'un quiz ne
     s'ouvre qu'une fois le cours parcouru. Le clic tombait sur un refus, sans
     un mot. `ouvert` porte désormais cette seconde condition. --}}
@props(['ligne'])

@if($ligne['passed'])
  <a href="{{ route('citizen.course', $ligne['quiz']->course) }}" class="text-sm font-semibold text-primary">{{ __('composants.quiz_voir_le_cours') }}</a>
@elseif($ligne['inscrit'] && ! ($ligne['ouvert'] ?? true))
  <a href="{{ route('citizen.course', $ligne['quiz']->course) }}" class="text-sm font-semibold text-slate2">{{ __('composants.quiz_terminez_les_lecons') }}</a>
@elseif($ligne['inscrit'])
  <a href="{{ route('citizen.quiz.take', ['course' => $ligne['quiz']->course, 'quiz' => $ligne['quiz']]) }}" class="inline-flex items-center rounded-xl bg-primary text-white text-xs font-semibold px-3.5 py-2 hover:bg-primary-dark min-h-[36px]">{{ $ligne['score'] !== null ? __('composants.quiz_reessayer') : __('composants.quiz_passer_le_quiz') }}</a>
@else
  <a href="{{ route('citizen.course', $ligne['quiz']->course) }}" class="text-sm font-semibold text-slate2">{{ __('composants.quiz_s_inscrire_au_cours') }}</a>
@endif
