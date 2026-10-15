<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Types
|--------------------------------------------------------------------------
|
| Keys = enum values. Those values are also URL filters and configuration
| keys (`factcheck.costs.image`): what is translated here is the LABEL, never
| the value.
|
*/

return [

    'contenu' => [
        'text' => 'Text',
        'url' => 'URL link',
        'image' => 'Image',
        'video' => 'Video',
        'audio' => 'Audio',
    ],

    'lecon' => [
        'text' => 'Reading',
        'video' => 'Video',
        'audio' => 'Audio',
    ],

    /*
     | CDI and CDD are Beninese labour-law contracts and job adverts use the
     | acronyms as they stand. They are kept, with the English meaning beside
     | them: dropping the acronym would make the listing unrecognisable to
     | someone job-hunting in Cotonou, dropping the gloss would leave an
     | English reader guessing.
     */
    'opportunite' => [
        'cdi' => 'Permanent (CDI)',
        'cdd' => 'Fixed-term (CDD)',
        'stage' => 'Internship',
        'alternance' => 'Apprenticeship',
        'volontariat' => 'Volunteering',
        'bourse' => 'Scholarship',
        'financement' => 'Funding',
        'conference' => 'Conference',
        'appel' => 'Call for applications',
        'autre' => 'Other',
    ],

    'portee_opportunite' => [
        'benin' => 'Benin opportunities',
        'international' => 'International opportunities',
    ],

    'campagne' => [
        'popup' => 'Pop-up',
        'banner' => 'Banner',
    ],

    'question' => [
        'qcm' => 'Multiple choice',
        'true_false' => 'True/False',
        'multi_select' => 'Multi-select',
        'short_answer' => 'Short answer',
        'matching' => 'Matching',
    ],
];
