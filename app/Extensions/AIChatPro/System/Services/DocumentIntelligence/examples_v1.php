<?php

declare(strict_types=1);

return [
    'version' => '1.0.0',
    'synthetic_label' => 'SYNTHETIC — NOT AN APPROVED GOLD STANDARD.',
    'examples' => [
        'weekly_report' => [
            'synthetic_label' => 'SYNTHETIC — NOT AN APPROVED GOLD STANDARD.',
            'language' => 'English',
            'original_request' => '[SYNTHETIC] Prepare a weekly report from the supplied work notes. Keep activities and meetings distinct and do not add dates or outcomes.',
            'source_information' => [
                'Fictional employee Lina Noor reviewed the Orchard website copy and updated its visitor checklist.',
                'The design group discussed page readability.',
                'The support group reviewed the checklist.',
            ],
            'outline' => ['Weekly Work Report', 'Employee', 'Executive Summary', 'Work', 'Meetings', 'Closing Summary'],
            'finished_reference' => <<<'DOCUMENT'
# Weekly Work Report

**Employee:** Lina Noor, fictional project coordinator

## Executive Summary
This week’s recorded work covered a review of the Orchard website copy and an update to the visitor checklist. Two meetings addressed page readability and the checklist.

## Work
- Reviewed the Orchard website copy.
- Updated the visitor checklist.

## Meetings
1. **Design group:** Discussed page readability.
2. **Support group:** Reviewed the visitor checklist.

## Closing Summary
The report records two work activities and the topics covered in the two meetings. It does not state dates, decisions, or completion status that were not supplied.
DOCUMENT,
            'localized_examples' => [
                'Somali' => <<<'DOCUMENT'
# Warbixinta Shaqada Toddobaadlaha ah

**Shaqaale:** Lina Noor, iskuduwaha mashruuca ee mala-awaalka ah

## Soo Koobid
Shaqadii la diiwaangeliyey toddobaadkan waxay ahayd dib-u-eegista qoraalka mareegta Orchard iyo cusboonaysiinta liiska hubinta booqdayaasha. Laba kulan ayaa ka hadlay akhris-fudaydka bogga iyo liiska hubinta.

## Shaqada
- Dib ayaa loo eegay qoraalka mareegta Orchard.
- Waa la cusboonaysiiyey liiska hubinta booqdayaasha.

## Kulamada
1. **Kooxda naqshadaynta:** Waxay ka hadashay akhris-fudaydka bogga.
2. **Kooxda taageerada:** Waxay dib u eegtay liiska hubinta.

## Gunaanad
Warbixintu waxay diiwaangelinaysaa laba hawlood iyo mawduucyada labada kulan.
DOCUMENT,
            ],
            'style_notes' => ['Use an informative summary, not a second activity log.', 'Give each meeting one distinct entry.'],
            'quality_criteria' => ['Both work facts appear once in the work section.', 'Both meetings have separate, accurate descriptions.', 'No dates, decisions, or completion claims are introduced.'],
            'known_limitations' => ['A synthetic structural reference; it is not evidence of an approved organizational style.'],
        ],
        'annual_report' => [
            'synthetic_label' => 'SYNTHETIC — NOT AN APPROVED GOLD STANDARD.',
            'language' => 'English',
            'original_request' => '[SYNTHETIC] Create an annual report from the supplied fictional source notes, preserving the stated period and metrics.',
            'source_information' => [
                'Fictional Orchard Cooperative recorded 120 workshop visits during its fictional reporting period.',
                'The cooperative maintained three community gardens.',
                'The supplied notes do not include a prior-year comparison or financial statement.',
            ],
            'outline' => ['Annual Report', 'Reporting Period', 'Executive Summary', 'Activities and Reach', 'Limitations', 'Closing Summary'],
            'finished_reference' => <<<'DOCUMENT'
# Orchard Cooperative Annual Report

**Reporting period:** Fictional reporting period supplied in the source notes

## Executive Summary
The supplied notes record 120 workshop visits and the maintenance of three community gardens during the stated period.

## Activities and Reach
The cooperative recorded 120 visits to its workshops. It also maintained three community gardens.

## Limitations
The source notes do not provide a prior-year comparison or financial statement, so this report makes no claims about year-over-year growth or financial performance.

## Closing Summary
This report summarizes the activity and reach information available in the supplied notes.
DOCUMENT,
            'style_notes' => ['Separate activities from outcomes.', 'State reporting-period scope and source limitations plainly.'],
            'quality_criteria' => ['The supplied counts are reproduced exactly.', 'No unsupported growth, impact, or financial claim is added.'],
            'known_limitations' => ['The fictional notes are deliberately narrow and cannot support a complete organizational annual report.'],
        ],
        'proposal' => [
            'synthetic_label' => 'SYNTHETIC — NOT AN APPROVED GOLD STANDARD.',
            'language' => 'English',
            'original_request' => '[SYNTHETIC] Draft a business proposal using only this fictional scope, delivery window, and budget.',
            'source_information' => [
                'Fictional client: Pine Desk Studio.',
                'Scope: reorganize a supplied help-center outline and prepare a navigation mock-up.',
                'Delivery window: four weeks. Budget ceiling: 2,400 fictional currency units.',
            ],
            'outline' => ['Proposal', 'Purpose', 'Proposed Scope', 'Deliverables', 'Schedule and Budget', 'Assumptions'],
            'finished_reference' => <<<'DOCUMENT'
# Pine Desk Studio Help-Center Proposal

## Purpose
This proposal presents the requested fictional scope for organizing the supplied help-center outline.

## Proposed Scope
The work would reorganize the supplied outline and prepare a navigation mock-up. No implementation or launch is included in the supplied scope.

## Deliverables
- A reorganized help-center outline.
- A navigation mock-up.

## Schedule and Budget
The supplied delivery window is four weeks. The stated budget ceiling is 2,400 fictional currency units.

## Assumptions
The source does not specify approval, implementation requirements, or additional costs. These remain open items rather than commitments.
DOCUMENT,
            'style_notes' => ['Label the work as proposed.', 'Keep commitments and exclusions tied to the stated scope.'],
            'quality_criteria' => ['Scope, deliverables, window, and ceiling match the source.', 'No approval, launch, or additional functionality is claimed.'],
            'known_limitations' => ['A proposal structure sample, not legal or procurement advice.'],
        ],
        'business_plan' => [
            'synthetic_label' => 'SYNTHETIC — NOT AN APPROVED GOLD STANDARD.',
            'language' => 'English',
            'original_request' => '[SYNTHETIC] Organize these fictional business-plan notes and identify assumptions separately from supplied facts.',
            'source_information' => [
                'Fictional business: Northwind Repair Desk.',
                'Service: repair appointments for small household appliances.',
                'The notes contain no market research, price list, staffing plan, or revenue forecast.',
            ],
            'outline' => ['Business Plan', 'Executive Summary', 'Business Description', 'Service', 'Evidence Gaps and Assumptions'],
            'finished_reference' => <<<'DOCUMENT'
# Northwind Repair Desk Business Plan

## Executive Summary
The supplied notes describe a fictional repair service for small household appliances. They do not provide the market or financial evidence needed for a complete operating plan.

## Business Description
Northwind Repair Desk is the fictional business name in the notes.

## Service
The proposed service is repair appointments for small household appliances.

## Evidence Gaps and Assumptions
The source includes no market research, price list, staffing plan, or revenue forecast. No customer demand, profitability, hiring, or growth claim is made here.
DOCUMENT,
            'style_notes' => ['Do not fill missing plan sections with invented projections.', 'Separate supplied business facts from open evidence gaps.'],
            'quality_criteria' => ['The service description is grounded.', 'Missing market and financial information is identified, not fabricated.'],
            'known_limitations' => ['The fictional input is intentionally insufficient for a complete business plan.'],
        ],
        'research' => [
            'synthetic_label' => 'SYNTHETIC — NOT AN APPROVED GOLD STANDARD.',
            'language' => 'English',
            'original_request' => '[SYNTHETIC] Write a short research summary from the fictional method and results provided; do not generalize beyond them.',
            'source_information' => [
                'Fictional study question: how participants used a sample library kiosk.',
                'Method: 12 fictional participants completed a short observation session.',
                'Supplied result: 8 of the 12 participants located the sample guide without assistance.',
                'No control group or population sampling was supplied.',
            ],
            'outline' => ['Research Summary', 'Question', 'Method', 'Observed Result', 'Limitations'],
            'finished_reference' => <<<'DOCUMENT'
# Sample Library Kiosk: Research Summary

## Question
The fictional study asked how participants used a sample library kiosk.

## Method
Twelve fictional participants completed a short observation session.

## Observed Result
Eight of the 12 participants located the sample guide without assistance.

## Limitations
The supplied notes do not describe a control group or population sampling. The observation therefore does not establish how all library users would perform.
DOCUMENT,
            'style_notes' => ['Distinguish method from finding.', 'Keep limitations proportional to the source.'],
            'quality_criteria' => ['The denominator and result remain exact.', 'No causal or population-wide conclusion is added.'],
            'known_limitations' => ['A tiny fictional example, not a research finding or citation.'],
        ],
        'financial_report' => [
            'synthetic_label' => 'SYNTHETIC — NOT AN APPROVED GOLD STANDARD.',
            'language' => 'English',
            'original_request' => '[SYNTHETIC] Prepare a financial summary using the supplied fictional ledger values and preserve the currency unit.',
            'source_information' => [
                'Fictional ledger: service receipts 1,200 units; supply costs 450 units.',
                'The source does not state taxes, other expenses, or whether the figures are audited.',
            ],
            'outline' => ['Financial Summary', 'Supplied Values', 'Calculation Notes', 'Limitations'],
            'finished_reference' => <<<'DOCUMENT'
# Fictional Financial Summary

## Supplied Values

| Item | Amount |
| --- | ---: |
| Service receipts | 1,200 units |
| Supply costs | 450 units |

## Calculation Notes
The difference between the two supplied values is 750 units. This arithmetic difference is not described as profit because the source does not include all expenses or taxes.

## Limitations
The notes do not state whether the figures are audited. No conclusion about net income or financial position is made.
DOCUMENT,
            'style_notes' => ['Preserve the unit.', 'Label calculations and avoid presenting partial data as profit.'],
            'quality_criteria' => ['Both values and the arithmetic difference are correct.', 'Missing costs, taxes, and audit status are not assumed.'],
            'known_limitations' => ['The example is not accounting, tax, or investment advice.'],
        ],
        'cv' => [
            'synthetic_label' => 'SYNTHETIC — NOT AN APPROVED GOLD STANDARD.',
            'language' => 'English',
            'original_request' => '[SYNTHETIC] Create a concise CV from the fictional candidate details; do not add dates or credentials.',
            'source_information' => [
                'Fictional candidate: Samira Bell.',
                'Role: records assistant at fictional Cedar Archive.',
                'Supplied responsibilities: catalogued records and answered visitor questions.',
                'No dates, education, contact information, or certifications were supplied.',
            ],
            'outline' => ['Candidate Name', 'Professional Summary', 'Experience', 'Education and Credentials'],
            'finished_reference' => <<<'DOCUMENT'
# Samira Bell

## Professional Summary
Fictional records assistant with experience cataloguing records and answering visitor questions.

## Experience
**Records Assistant — Cedar Archive (fictional)**
- Catalogued records.
- Answered visitor questions.

## Education and Credentials
Not provided in the source information.
DOCUMENT,
            'style_notes' => ['Use concise action-oriented phrasing without inflating responsibility.', 'State missing credentials only if useful.'],
            'quality_criteria' => ['Candidate, role, organization, and duties match the source.', 'No dates, qualifications, or contact details are invented.'],
            'known_limitations' => ['The fictional profile is not a real person or a complete CV.'],
        ],
        'technical_manual' => [
            'synthetic_label' => 'SYNTHETIC — NOT AN APPROVED GOLD STANDARD.',
            'language' => 'English',
            'original_request' => '[SYNTHETIC] Turn these fictional documented steps into a short manual; do not add system behavior.',
            'source_information' => [
                'Fictional kiosk guide: select “Start”; scan the printed sample card; choose “Finish” to print a sample receipt.',
                'The source does not describe errors, security, or troubleshooting.',
            ],
            'outline' => ['Purpose', 'Procedure', 'Expected Output', 'Limitations'],
            'finished_reference' => <<<'DOCUMENT'
# Sample Kiosk Quick Guide

## Purpose
This guide describes the documented steps for the fictional sample kiosk.

## Procedure
1. Select **Start**.
2. Scan the printed sample card.
3. Select **Finish**.

## Expected Output
The supplied guide states that selecting **Finish** prints a sample receipt.

## Limitations
The source does not describe error handling, security behavior, or troubleshooting.
DOCUMENT,
            'style_notes' => ['Use ordered steps for a documented sequence.', 'Do not add safety or error-handling claims absent from the source.'],
            'quality_criteria' => ['The sequence and stated output match the supplied guide.', 'No unsupported technical behavior is added.'],
            'known_limitations' => ['A fictional user-guide example, not instructions for a real product.'],
        ],
        'training' => [
            'synthetic_label' => 'SYNTHETIC — NOT AN APPROVED GOLD STANDARD.',
            'language' => 'English',
            'original_request' => '[SYNTHETIC] Create a short training handout from these fictional learning objectives and exercise.',
            'source_information' => [
                'Audience: fictional new library volunteers.',
                'Objective: identify the three labeled shelves on a sample map.',
                'Exercise: mark the shelves on a blank copy of the same map.',
                'No assessment threshold or official policy was supplied.',
            ],
            'outline' => ['Training Handout', 'Audience and Objective', 'Practice Activity', 'Assessment Limitation'],
            'finished_reference' => <<<'DOCUMENT'
# Sample Map-Reading Handout

## Audience and Objective
This fictional handout is for new library volunteers. The stated objective is to identify the three labeled shelves on a sample map.

## Practice Activity
Use a blank copy of the same map and mark the three shelves identified in the example.

## Assessment Limitation
The source does not provide a scoring threshold or official policy. This activity is practice, not a certification assessment.
DOCUMENT,
            'style_notes' => ['Make the objective observable.', 'Distinguish a suggested practice activity from formal assessment.'],
            'quality_criteria' => ['Audience, objective, and exercise match the source.', 'No certification or policy is invented.'],
            'known_limitations' => ['The fictional exercise is not a validated curriculum.'],
        ],
        'long_form_report' => [
            'synthetic_label' => 'SYNTHETIC — NOT AN APPROVED GOLD STANDARD.',
            'language' => 'English',
            'original_request' => '[SYNTHETIC] Develop a long-form report from these fictional observations, keeping evidence and interpretation separate.',
            'source_information' => [
                'A fictional reading room recorded 18 seat reservations during a sample observation period.',
                'The observation notes say 11 reservations were for the east room and 7 for the west room.',
                'No dates, capacity, attendance, or user feedback were supplied.',
            ],
            'outline' => ['Purpose and Scope', 'Executive Summary', 'Recorded Observations', 'Interpretation Limits', 'Conclusion'],
            'finished_reference' => <<<'DOCUMENT'
# Reading-Room Reservation Observation

## Purpose and Scope
This fictional report organizes the reservation counts recorded in the supplied observation notes. The notes describe reservations, not attendance or room occupancy.

## Executive Summary
The source records 18 seat reservations: 11 for the east room and 7 for the west room.

## Recorded Observations
The east room accounts for 11 of the recorded reservations, while the west room accounts for 7. Together, the two values equal the supplied total of 18.

## Interpretation Limits
The notes provide no dates, room capacities, attendance records, or user feedback. They do not establish a preference trend, utilization rate, or reason for the difference between rooms.

## Conclusion
The available evidence supports a summary of the recorded reservation counts only.
DOCUMENT,
            'style_notes' => ['Develop sections by explaining evidence scope, not by repeating the counts.', 'Separate observation from interpretation.'],
            'quality_criteria' => ['All three values are preserved and reconcile.', 'No trend, utilization, or preference conclusion is invented.'],
            'known_limitations' => ['The example is a short synthetic reference and does not demonstrate a complete long-form report.'],
        ],
    ],
];
