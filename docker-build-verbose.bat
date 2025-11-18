@echo off
REM Build Docker image with verbose output for debugging

echo =======================================
echo Building with Verbose Output
echo =======================================
echo.

cd /d "%~dp0"

echo Building image with detailed output...
echo.

docker-compose build --progress=plain --no-cache

if %ERRORLEVEL% EQU 0 (
    echo.
    echo =======================================
    echo Build completed successfully!
    echo =======================================
) else (
    echo.
    echo =======================================
    echo Build failed! Review the output above for details.
    echo =======================================
)

echo.
pause
