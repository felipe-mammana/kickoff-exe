@echo off
title EXE Kickoff - Servidor Local + Tunel Cloudflare

echo.
echo ============================================
echo   EXE Kickoff - Iniciando ambiente de teste
echo ============================================
echo.

set PHP=C:\xampp\php\php.exe
set PROJECT=C:\Users\felip\OneDrive\Desktop\exe-kickoff
set PUBLIC=%PROJECT%\public
set PORT=8000

:: Verifica se o PHP existe
if not exist "%PHP%" (
    echo [ERRO] PHP nao encontrado em %PHP%
    echo Ajuste a variavel PHP no inicio deste arquivo.
    pause
    exit /b 1
)

:: Verifica se o cloudflared existe
where cloudflared >nul 2>nul

if errorlevel 1 (
    echo [ERRO] cloudflared nao encontrado no PATH.
    echo Instale o cloudflared pelo site oficial da Cloudflare.
    pause
    exit /b 1
)

:: Mata processos anteriores na porta 8000
echo [1/3] Verificando porta %PORT%...

for /f "tokens=5" %%a in ('netstat -ano ^| findstr ":%PORT% " ^| findstr "LISTENING"') do (
    echo       Encerrando processo antigo PID %%a na porta %PORT%...
    taskkill /PID %%a /F >nul 2>nul
)

:: Inicia o servidor PHP
echo [2/3] Iniciando servidor PHP em http://127.0.0.1:%PORT% ...

start /B "" "%PHP%" -S 127.0.0.1:%PORT% -t "%PUBLIC%"

:: Aguarda o servidor subir
timeout /t 2 /nobreak >nul

:: Verifica se o servidor subiu
powershell -Command "try { $r = Invoke-WebRequest -Uri 'http://127.0.0.1:%PORT%' -UseBasicParsing -TimeoutSec 3 -ErrorAction Stop; Write-Host '       Servidor PHP OK (HTTP' $r.StatusCode ')' } catch { Write-Host '       [AVISO] Servidor pode nao ter iniciado corretamente.' }"

:: Inicia o tunel Cloudflare
echo [3/3] Abrindo tunel Cloudflare...
echo.
echo ============================================
echo   Aguarde o link HTTPS aparecer abaixo
echo   (procure por "trycloudflare.com")
echo.
echo   Para encerrar: Ctrl+C
echo ============================================
echo.

cloudflared tunnel --url http://127.0.0.1:%PORT%

pause