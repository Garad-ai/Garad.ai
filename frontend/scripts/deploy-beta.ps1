$ErrorActionPreference = 'Stop'

$ProjectRoot = Split-Path -Parent $PSScriptRoot
$TargetDist = Join-Path $ProjectRoot '..\public\garad-ai-chat-pro\dist'

Push-Location $ProjectRoot
try {
    & npm run build
    if ($LASTEXITCODE -ne 0) {
        throw "Frontend build failed with exit code $LASTEXITCODE"
    }

    if (-not (Test-Path (Join-Path $TargetDist '.vite\manifest.json'))) {
        throw "Frontend manifest not found: $TargetDist"
    }

    Write-Output "Frontend built for Laravel: $TargetDist"
}
finally {
    Pop-Location
}
