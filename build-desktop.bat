@echo off
setlocal enabledelayedexpansion

echo.
echo =========================================================
echo  POS Enterprise - Desktop Installer Builder
echo =========================================================
echo.

REM ── Step 1: Build the Tauri desktop app ──────────────────────────────────
echo [1/3] Building Tauri desktop app...
cd /d "%~dp0desktop"
call npm run build
if errorlevel 1 (
    echo.
    echo ERROR: Build failed! Check the output above for details.
    pause
    exit /b 1
)

REM ── Step 2: Find the NSIS installer ──────────────────────────────────────
echo.
echo [2/3] Locating NSIS installer...
set "NSIS_DIR=%~dp0desktop\src-tauri\target\release\bundle\nsis"
set "INSTALLER="
for %%f in ("%NSIS_DIR%\*-setup.exe") do set "INSTALLER=%%f"

if not defined INSTALLER (
    echo ERROR: NSIS installer not found in:
    echo   %NSIS_DIR%
    echo.
    echo Make sure bundle.active is true in desktop\src-tauri\tauri.conf.json
    pause
    exit /b 1
)

echo Found: !INSTALLER!

REM ── Step 3: Copy to releases\ with the server-expected name ──────────────
echo.
echo [3/3] Copying to releases folder...
set "RELEASES_DIR=%~dp0releases"
if not exist "%RELEASES_DIR%" mkdir "%RELEASES_DIR%"

copy /Y "!INSTALLER!" "%RELEASES_DIR%\POS-Enterprise-Setup.exe"
if errorlevel 1 (
    echo ERROR: Failed to copy installer!
    pause
    exit /b 1
)

REM Show file size
for %%f in ("%RELEASES_DIR%\POS-Enterprise-Setup.exe") do set "SIZE=%%~zf"
set /a SIZE_MB=!SIZE! / 1048576

echo.
echo =========================================================
echo  BUILD COMPLETE
echo =========================================================
echo.
echo  Installer: releases\POS-Enterprise-Setup.exe  (!SIZE_MB! MB)
echo.
echo  Next step: Upload this file to the server at:
echo    public/downloads/POS-Enterprise-Setup.exe
echo.
echo  (via cPanel File Manager - biskumarket.life/pos/downloads/)
echo =========================================================
echo.
pause
