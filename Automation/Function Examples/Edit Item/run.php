<?php

declare(strict_types=1);

$itemId = '%ONLINE_ASSESSMENT_ID%';

$data = [
    // Title
    'title' => 'Quarterly vendor security assessment',

    // Description
    'description' => 'Security questionnaire sent to a third party before the quarterly supplier review.',

    // Questionnaire ID from Vendor Assessment Questionnaires
    'VendorAssessmentQuestionnaires' => 1,

    // Assessor users or groups
    'Auditors' => [
        'User-1',
        'Group-13',
    ],

    // Enable Non-Authenticated Submissions
    'public_access' => 1,

    // Recipient users or groups
    'Auditees' => [
        'User-1',
        'Group-13',
    ],

    // Portal Title
    'portal_title' => 'Vendor Security Questionnaire',

    // Allow report download?
    'questions_download' => 0,

    // Report ID. Use an integer report ID when questions_download is 1.
    'report_id' => null,

    // Incomplete Submissions
    'incomplete_submit' => 1,

    // Start Date
    'start_date' => '2027-01-01',

    // Stop Date
    'end_date' => '2027-01-31',

    // Auto Stop
    'auto_close' => 1,

    // Recurrence
    'recurrence' => 1,

    // Periodicity
    'recurrence_period' => 1,

    // Recurrence Period Type: 1 = Day, 2 = Week, 3 = Month, 4 = Year
    'recurrence_period_type' => 3,

    // Auto Load Answers
    'recurrence_auto_load' => 1,

    // Business Unit IDs
    'BusinessUnits' => [
        1,
    ],

    // Third Party IDs
    'ThirdParties' => [
        1,
    ],

    // Asset IDs
    'Assets' => [
        1,
    ],

    // Asset Risk IDs
    'Risks' => [
        1,
    ],

    // Third Party Risk IDs
    'ThirdPartyRisks' => [
        1,
    ],

    // Business Risk IDs
    'BusinessContinuities' => [
        1,
    ],

    // Data Flow IDs
    'DataAssets' => [
        1,
    ],
];

echo editObjectMacro($data, $itemId);
