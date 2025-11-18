# Test ZKTeco connection using Docker (without docker-compose)

Write-Host "=======================================" -ForegroundColor Cyan
Write-Host "Testing ZKTeco Connection" -ForegroundColor Cyan
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

Write-Host "Testing connection..." -ForegroundColor Yellow
Write-Host ""

docker run --rm --network host -v ${PWD}/.env:/app/.env:ro zkteco-attendance-sync:latest php artisan attendance:sync --test

Write-Host ""
if ($LASTEXITCODE -eq 0) {
    Write-Host "=======================================" -ForegroundColor Green
    Write-Host "Connection test successful!" -ForegroundColor Green
    Write-Host "=======================================" -ForegroundColor Green
} else {
    Write-Host "=======================================" -ForegroundColor Red
    Write-Host "Connection test failed!" -ForegroundColor Red
    Write-Host "=======================================" -ForegroundColor Red
}

Write-Host ""
Write-Host "Press any key to continue..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
