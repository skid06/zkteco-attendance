# Build Docker image directly without docker-compose

Write-Host "=======================================" -ForegroundColor Cyan
Write-Host "Building ZKTeco Docker Image (Direct)" -ForegroundColor Cyan
Write-Host "=======================================" -ForegroundColor Cyan
Write-Host ""

Set-Location $PSScriptRoot

Write-Host "Building image..." -ForegroundColor Yellow
docker build -t zkteco-attendance-sync:latest .

if ($LASTEXITCODE -eq 0) {
    Write-Host ""
    Write-Host "=======================================" -ForegroundColor Green
    Write-Host "Build completed successfully!" -ForegroundColor Green
    Write-Host "=======================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "Image built: zkteco-attendance-sync:latest" -ForegroundColor Yellow
    Write-Host ""
    Write-Host "To test connection, run:" -ForegroundColor Yellow
    Write-Host "docker run --rm --network host -v `${PWD}/.env:/app/.env:ro zkteco-attendance-sync:latest php artisan attendance:sync --test" -ForegroundColor White
} else {
    Write-Host ""
    Write-Host "=======================================" -ForegroundColor Red
    Write-Host "Build failed!" -ForegroundColor Red
    Write-Host "=======================================" -ForegroundColor Red
}

Write-Host ""
Write-Host "Press any key to continue..."
$null = $Host.UI.RawUI.ReadKey("NoEcho,IncludeKeyDown")
