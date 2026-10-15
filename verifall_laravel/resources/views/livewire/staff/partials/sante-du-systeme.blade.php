{{--
  | Santé du système — MESURÉE, pas illustrée.
  |
  | La maquette demandait trois lignes qui ont été écartées le 1er septembre
  | 2026, et le raisonnement est consigné dans `SanteDuSysteme` :
  |
  |   · « Reverb (WebSockets) — Déconnecté » : Reverb n'est pas installé dans ce
  |     projet. La ligne aurait rapporté l'état d'un composant absent.
  |   · « Charge CPU 46 % » : en mutualisé, la charge est celle du NŒUD, pas la
  |     nôtre — exacte et sans rapport avec l'application.
  |   · « Espace disque 89 % · 300,6 Go libres » : c'est la partition du serveur,
  |     pas le quota cPanel du compte.
  |
  | Aucune classe nouvelle : uniquement celles de `tailwind.css` (build figé) et
  | de `staff.css`. Une classe absente du build n'a AUCUN effet, et rien ne le
  | signale avant la mise en ligne.
--}}
<div class="st-card">
    <div class="st-card-h flex items-center justify-between gap-3">
        <h2 class="font-display font-bold text-[#0F172A] mb-0">Santé du système</h2>

        {{-- L'ÂGE DE LA MESURE, à côté de la mesure. Une pastille verte sans
             date ne veut rien dire : elle peut dater d'une semaine. --}}
        <span class="text-xs text-[#64748B]">
            @if ($sante['moteurs']['mesure_le'] === null)
                Moteurs jamais sondés
            @else
                Sondé {{ $sante['moteurs']['mesure_le']->diffForHumans() }}
            @endif
        </span>
    </div>

    <div class="st-card-b">

        {{-- ── INFRASTRUCTURE ────────────────────────────────────────────────
             En mutualisé, aucun démon ne tourne. Ces quatre lignes sont
             exactement ce qui s'arrête en silence quand l'entrée cron manque. --}}
        <h3 class="text-xs font-bold uppercase tracking-wider text-[#64748B] mb-2">Infrastructure</h3>

        <dl class="mb-4">
            @foreach ($sante['infrastructure'] as $ligne)
                <div class="py-2 border-b border-line last:border-0">
                    <div class="flex items-baseline justify-between gap-3">
                        <dt class="text-sm text-[#64748B]">{{ $ligne['libelle'] }}</dt>
                        <dd class="font-display font-bold mb-0 {{ $ligne['alerte'] ? 'text-vfalse' : 'text-[#0F172A]' }}">
                            {{ $ligne['valeur'] }}
                        </dd>
                    </div>

                    @if ($ligne['detail'] !== null)
                        <p class="text-xs mb-0 mt-1 {{ $ligne['alerte'] ? 'text-vfalse' : 'text-[#64748B]' }}">
                            {{ $ligne['detail'] }}
                        </p>
                    @endif
                </div>
            @endforeach
        </dl>

        {{-- ── MOTEURS D'ANALYSE ─────────────────────────────────────────────
             Cinq états distincts, jamais un booléen. L'ancien `etat()` disait
             « disponible » dès qu'une CLASSE existait : il annonçait Perplexity
             opérationnel avec une clé vide, et Google Fact Check opérationnel
             avec une clé que Google refuse. --}}
        <div class="flex items-center justify-between gap-3 mb-2">
            <h3 class="text-xs font-bold uppercase tracking-wider text-[#64748B] mb-0">Moteurs d'analyse</h3>
            <button type="button" wire:click="sonderLesMoteurs" wire:loading.attr="disabled"
                    class="text-xs font-bold text-primary">
                <span wire:loading.remove wire:target="sonderLesMoteurs">Sonder maintenant</span>
                <span wire:loading wire:target="sonderLesMoteurs">Sonde en cours…</span>
            </button>
        </div>

        @if ($sante['moteurs']['simule'])
            {{-- Le dire en premier : sous FAKE_ADAPTERS, aucun état affiché
                 en dessous ne décrit un service réel. --}}
            <p class="text-xs text-vfalse mb-3">
                <strong>FAKE_ADAPTERS=true</strong> — les verdicts sont inventés.
                Aucun moteur réel n'est interrogé.
            </p>
        @endif

        <dl class="mb-0">
            @foreach ($sante['moteurs']['moteurs'] as $moteur)
                <div class="flex items-center justify-between gap-3 py-1.5 border-b border-line last:border-0">
                    <dt class="text-sm text-[#0F172A] min-w-0 truncate" title="{{ $moteur['etat']->explication() }}">
                        {{ $moteur['libelle'] }}
                        @if ($moteur['precision'] !== null)
                            <span class="text-xs text-[#64748B]">· {{ $moteur['precision'] }}</span>
                        @endif
                    </dt>
                    <dd class="mb-0 shrink-0">
                        <span class="{{ $moteur['etat']->pastille() }}">{{ $moteur['etat']->libelle() }}</span>
                    </dd>
                </div>
            @endforeach
        </dl>
    </div>
</div>
