$ErrorActionPreference = 'Stop'

$workspace = Split-Path -Parent $PSScriptRoot
$php = 'C:\xampp\php\php.exe'
$public = Join-Path $workspace 'public'

if (-not (Test-Path -LiteralPath $php)) {
    throw "PHP nao encontrado em $php"
}

if (-not (Test-Path -LiteralPath $public)) {
    throw "Diretorio public nao encontrado em $public"
}

Start-Process `
    -FilePath $php `
    -ArgumentList @(
        '-d', 'display_errors=0',
        '-d', 'display_startup_errors=0',
        '-d', 'log_errors=1',
        '-d', "error_log=$workspace\storage\php_errors.log",
        '-d', "upload_tmp_dir=$workspace\storage\tmp",
        '-S', 'localhost:8000',
        '-t', $public
    ) `
    -WorkingDirectory $workspace `
    -WindowStyle Hidden

Write-Host 'Servidor local iniciado em http://localhost:8000'
