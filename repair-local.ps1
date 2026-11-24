$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
if (!(Test-Path .\compose.yaml)) { throw 'Project folder not found.' }
$sourceRef = '012ab09791cb4dd5bd30b89dcf3f82db4937b502'
$files = @(
 'database/migrations/2026_11_24_000001_create_shop_tables.php',
 'app/Models/Product.php',
 'app/Http/Controllers/ShopAdminController.php',
 'app/Http/Controllers/ShopController.php',
 'resources/views/shop/admin.blade.php',
 'resources/views/shop/order.blade.php'
)
function Run-Docker([string[]]$DockerArguments) {
    $oldPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        & docker @DockerArguments
        $code = $LASTEXITCODE
    } finally { $ErrorActionPreference = $oldPreference }
    if ($code -ne 0) { throw "Docker failed (exit $code). Repair stopped; inspect the error above." }
}
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss-fff'
$backupDirectory = Join-Path $PSScriptRoot ("backups/repair-" + $stamp)
$stagingDirectory = Join-Path $PSScriptRoot '.repair-staging'
New-Item -ItemType Directory -Force -Path $backupDirectory | Out-Null
New-Item -ItemType Directory -Force -Path $stagingDirectory | Out-Null
'*' | Set-Content -Encoding ASCII -Path (Join-Path (Split-Path $backupDirectory -Parent) '.gitignore')
'*' | Set-Content -Encoding ASCII -Path (Join-Path $stagingDirectory '.gitignore')

# Download all files before changing the running application.
foreach ($file in $files) {
    $target = Join-Path $stagingDirectory $file
    New-Item -ItemType Directory -Force -Path (Split-Path $target -Parent) | Out-Null
    Invoke-WebRequest -UseBasicParsing -Uri ("https://raw.githubusercontent.com/urail199999-dot/papacheat-laravel/" + $sourceRef + "/" + $file) -OutFile $target
}
Run-Docker -DockerArguments @('compose','exec','-T','--user','root','app','sh','-c','mkdir -p storage/logs storage/framework/views storage/framework/sessions storage/framework/cache/data bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache')
$maintenance = $false
try {
    Run-Docker -DockerArguments @('compose','exec','-T','app','php','artisan','down')
    $maintenance = $true
    Write-Host 'Saving SQL backup before migration...'
    Run-Docker -DockerArguments @('compose','exec','-T','db','sh','-c','mariadb-dump --single-transaction --user="${MARIADB_USER:-root}" --password="${MARIADB_PASSWORD:-$MARIADB_ROOT_PASSWORD}" "$MARIADB_DATABASE" > /tmp/papacheat-repair.sql')
    $sqlFile = Join-Path $backupDirectory 'database.sql'
    Run-Docker -DockerArguments @('compose','cp','db:/tmp/papacheat-repair.sql',$sqlFile)
    if (!(Test-Path $sqlFile) -or (Get-Item $sqlFile).Length -lt 100) { throw 'SQL backup is empty; migration was not started.' }
    Write-Host ("Backup saved locally: " + $sqlFile)
    foreach ($file in $files) {
        $target = Join-Path $PSScriptRoot $file
        if (Test-Path $target) {
            $oldFile = Join-Path $backupDirectory $file
            New-Item -ItemType Directory -Force -Path (Split-Path $oldFile -Parent) | Out-Null
            Copy-Item -LiteralPath $target -Destination $oldFile
        }
        Copy-Item -LiteralPath (Join-Path $stagingDirectory $file) -Destination $target -Force
    }
    Run-Docker -DockerArguments @('compose','exec','-T','app','php','artisan','config:clear')
    Run-Docker -DockerArguments @('compose','exec','-T','app','php','artisan','migrate','--force')
    Run-Docker -DockerArguments @('compose','exec','-T','app','php','artisan','optimize:clear')
    Run-Docker -DockerArguments @('compose','exec','-T','--user','root','app','sh','-c','chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache')
} finally {
    if ($maintenance) { Run-Docker -DockerArguments @('compose','exec','-T','app','php','artisan','up') }
}
Run-Docker -DockerArguments @('compose','restart','app','web')
Write-Host 'Repair completed. Open http://localhost:8080/login then http://localhost:8080/manage'
Write-Host 'Administrator email and password were preserved. Legacy guest orders are visible only to the administrator.'
Write-Host 'Keep backups/database.sql private; do not upload it into the chat or GitHub.'
