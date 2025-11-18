# Run manual sync using Docker (without docker-compose)

Write-Host "=======================================" -ForegroundColor Cyan
Write-Host "ZKTeco Manual Sync" -ForegroundColor Cyan
Write-Host "=======================================" -ForegroundColor Cyan
Write-Host ""

Set-Location $PSScriptRoot

# Check if .env exists
if (-not (Test-Path ".env")) {
    Write-Host "ERROR: .env file not found!" -ForegroundColor Red
    Write-Host "Please create .env file from .env.example" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "Press any key to continue..."
    $null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
    exit 1
}

# Create database and logs directories if they don't exist
if (-not (Test-Path "database")) {
    New-Item -ItemType Directory -Path "database" | Out-Null
}
if (-not (Test-Path "storage/logs")) {
    New-Item -ItemType Directory -Path "storage/logs" -Force | Out-Null
}
if (-not (Test-Path "database/database.sqlite")) {
    New-Item -ItemType File -Path "database/database.sqlite" | Out-Null
}

Write-Host "Running sync..." -ForegroundColor Yellow
Write-Host ""

docker run --rm --network host `
    -v ${PWD}/.env:/app/.env:ro `
    -v ${PWD}/database/database.sqlite:/app/database/database.sqlite `
    -v ${PWD}/storage/logs:/app/storage/logs `
    zkteco-attendance-sync:latest php artisan attendance:sync --clear

Write-Host ""
if ($LASTEXITCODE -eq 0) {
    Write-Host "=======================================" -ForegroundColor Green
    Write-Host "Sync completed successfully!" -ForegroundColor Green
    Write-Host "=======================================" -ForegroundColor Green
} else {
    Write-Host "=======================================" -ForegroundColor Red
    Write-Host "Sync failed!" -ForegroundColor Red
    Write-Host "=======================================" -ForegroundColor Red
}

Write-Host ""
Write-Host "Press any key to continue..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
