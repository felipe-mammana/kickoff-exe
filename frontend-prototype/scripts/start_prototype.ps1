$ErrorActionPreference = 'Stop'
$root = (Resolve-Path (Join-Path $PSScriptRoot '..')).Path
Write-Host "EXE Kickoff prototype: http://localhost:4173/"
if (Get-Command php -ErrorAction SilentlyContinue) {
    php -S localhost:4173 -t $root
} elseif (Get-Command python -ErrorAction SilentlyContinue) {
    python -m http.server 4173 --directory $root
} else {
    throw 'Instale PHP ou Python para iniciar um servidor HTTP local.'
}
