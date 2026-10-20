<?php

declare(strict_types=1);

// Renders a model-style authored manual through the real artifact service (PDF + DOCX) for inspection.
use App\Extensions\AIChatPro\System\Services\GaradArtifactService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

require __DIR__ . '/../../vendor/autoload.php';
$app = require __DIR__ . '/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

Auth::loginUsingId(2);
$request = 'Create a detailed training manual in English for new volunteers using the sample kiosk. Title: Sample Kiosk Procedure. Audience: new volunteers. Procedure: select Start, scan the printed sample card, then select Finish to print a sample receipt. The notes do not describe security behavior or troubleshooting.';
$app->instance('request', Request::create('/', 'POST', ['raw_user_input' => $request]));

$arguments = [
    'title' => 'Sample Kiosk Procedure',
    'content' => "## Purpose and Audience\n\nThis manual introduces new volunteers to the sample kiosk procedure. It explains the three actions covered by the source notes and states what the notes do not cover.\n\n"
        . "## Before You Begin\n\nYou will need the printed sample card. The procedure is a practice workflow, so the receipt it prints is a sample receipt.\n\n"
        . "## Procedure\n\n1. Select Start on the kiosk.\n2. Scan the printed sample card.\n3. Select Finish to print a sample receipt.\n\n"
        . "### Order of actions\n\nThe three actions are performed in the order listed above: Start, scan, then Finish.\n\n"
        . "## Limits of This Manual\n\nThe source notes do not describe security behavior or troubleshooting steps. Volunteers should not assume any behavior beyond the three actions above.",
    'source_facts' => [
        ['section' => 'other', 'quote' => 'Audience: new volunteers'],
        ['section' => 'other', 'quote' => 'Title: Sample Kiosk Procedure'],
        ['section' => 'work', 'quote' => 'Procedure: select Start, scan the printed sample card, then select Finish to print a sample receipt.'],
        ['section' => 'other', 'quote' => 'The notes do not describe security behavior or troubleshooting.'],
    ],
];
$service = app(GaradArtifactService::class);
echo $service->createPdf($arguments), "\n";
echo $service->createDocx($arguments), "\n";
echo 'LATEST ARTIFACT IDS: ', implode(',', App\Extensions\AIChatPro\System\Models\AIChatProArtifact::query()->latest('id')->limit(2)->pluck('id')->all()), "\n";
