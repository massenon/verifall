<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| The analysis: steps, engines, evidence, community opinion
|--------------------------------------------------------------------------
|
| The pipeline keys are the INTEGER values of `EtapePipeline` (1 to 6), the
| chronological order itself — renumbering here renumbers nothing elsewhere,
| it would only move a label under a different step.
|
| ENGINE NAMES are proper nouns: Gemini, DeepSeek, OpenAI and TinEye are
| spelled the same in every language. Only the job the engine does is
| translated.
|
*/

return [

    'pipeline' => [
        'libelle' => [
            1 => 'Content received',
            2 => 'Analysis preparation',
            3 => 'Source analysis',
            4 => 'AI analysis',
            5 => 'Evidence consolidation',
            6 => 'Verdict',
        ],

        'description' => [
            1 => 'Your submission is recorded and time-stamped.',
            2 => 'Extracting the text, the metadata and the fingerprints.',
            3 => 'Cross-checking against fact-checking databases and trusted sources.',
            4 => 'Our engines assess the wording, the provenance and any earlier appearance.',
            5 => 'Building the case file: sources, excerpts, timeline.',
            6 => 'Writing the report and computing the confidence score.',
        ],
    ],

    'moteur' => [
        'gemini' => 'Semantic analysis (Gemini)',
        'deepseek' => 'Counter-analysis (DeepSeek)',
        'openai' => 'Reasoning (OpenAI)',
        'perplexity_search' => 'Cited web search',
        'google_factcheck' => 'Earlier fact-checks',
        'google_custom_search' => 'Beninese press and official sources',
        'knowledge_graph' => 'Entity verification',
        'technical_analysis' => 'Technical analysis of the domain',
        'tineye' => 'Earlier appearances of the image',
        'vision' => 'Image reading',
        'ai_detector' => 'Generated-content detection',
        'transcription' => 'Transcription',
    ],

    'position_preuve' => [
        'supports' => 'Supports',
        'refutes' => 'Refutes',
        'neutral' => 'Neutral',
        'contested' => 'Contested',
    ],

    /*
     | What a sentence IS — `ActeDeLangage`, phase 2. Keys are the enum
     | values.
     */
    'acte_de_langage' => [
        'affirmation' => 'Affirmation',
        'question' => 'Question',
        'opinion' => 'Opinion',
        'prediction' => 'Prediction',
        'non_factuel' => 'Not factual',
    ],

    /*
     | What a report SAYS as a whole — `CompositionDuRapport`, an axis
     | distinct from the verdict (contract V-8). `MIXED` is never a verdict.
     */
    'composition' => [
        'homogene' => 'Single verdict',
        'mixte' => 'Contradictory claims',
        'insuffisante' => 'Nothing settled',
    ],

    'composition_explication' => [
        'homogene' => 'Every claim in this content carries the same verdict.',
        'mixte' => 'This content mixes accurate claims with false or misleading ones — the breakdown below tells them apart.',
        'insuffisante' => 'This content carries no claim the platform could settle.',
    ],

    /*
     | What a claim says about TIME — `PorteeTemporelle`, phase 3. `consigne`
     | is injected as-is under {{PORTEE_TEMPORELLE}} in `analyse.md`: it is
     | text addressed to the MODEL, not to a human reader.
     */
    'portee_temporelle' => [
        'libelle' => [
            'present' => 'Present state',
            'historique' => 'Historical fact',
            'date_explicite' => 'Explicit date',
            'relatif' => 'Relative date',
        ],
        'consigne' => [
            'present' => "This claim concerns a CURRENT STATE (who holds an office, a price in effect, a rule in force). Your memory is FORMALLY FORBIDDEN as a basis for judging it: it can look current without being so. If you have a search tool, you MUST use it, together with the contradictory search already required below. Without search, or if evidence is missing, the verdict is NON_VERIFIABLE.",
            'historique' => "This claim concerns a HISTORICAL fact considered stable (a dated, closed event, a constant). Your memory is admissible here, provided it stays consistent with any evidence you can check.",
            'date_explicite' => "This claim carries its OWN date or period. That date, not today's date ({{DATE}}), should guide your search and judgment: look for what concerns THAT specific period, not the current state.",
            'relatif' => "This claim is dated RELATIVE to the moment it was submitted (\"yesterday\", \"this week\", \"this year\"). Resolve that date against the reference date {{DATE}} — you cannot rely on your own notion of the present, which is not this verification's.",
        ],
    ],

    /*
     | The report Hero (`corps.blade.php`), for `MIXED` and `INSUFFICIENT`
     | only — `HOMOGENEOUS` stays the verdict's own business.
     */
    'composition_rapport' => [
        'libelle' => [
            'mixte' => 'Contradictory claims',
            'insuffisante' => 'Not settled',
        ],
        'phrase' => [
            'mixte' => 'This content mixes accurate claims with false or misleading ones.',
            'insuffisante' => 'No claim in this content could be submitted to factual verification.',
        ],
    ],

    /*
     | What the community THINKS, never what the platform ESTABLISHES — the
     | official verdicts live in `verdicts.php` and have no business here. The
     | capitals are the prototype's, kept as they are.
     */
    'avis_communaute' => [
        'true' => 'TRUE',
        'doubt' => 'DOUBTFUL',
        'false' => 'FALSE',
    ],
];
