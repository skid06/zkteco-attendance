@echo off
REM Build Docker image using simplified Dockerfile

echo =======================================
echo Building with Simplified Dockerfile
echo =======================================
echo.

cd /d "%~dp0"

echo Building image using Dockerfile.simple...
docker build -f Dockerfile.simple -t zkteco-attendance-sync:latest .

if %ERRORLEVEL% EQU 0 (
    echo.
    echo =======================================
    echo Build completed successfully!
    echo =======================================
    echo.
    echo Note: This uses the simplified Dockerfile.
    echo You can now use the normal docker scripts.
) else (
    echo.
    echo =======================================
    echo Build failed! Check the errors above.
    echo =======================================
)

echo.
pause
