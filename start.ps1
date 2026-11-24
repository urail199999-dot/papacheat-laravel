$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
function Step([string[]]$DockerArguments) {
    # Windows PowerShell 5.1 treats redirected native stderr as ErrorRecord.
    # Docker writes progress there even when the command succeeds.
    $previousPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        & docker @DockerArguments
        $dockerExitCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $previousPreference
    }
    if ($dockerExitCode -ne 0) { throw "Docker command failed. Stop and inspect the error above." }
}
if (!(Test-Path .env)) { Copy-Item .env.example .env }
if (!(Select-String -Path .env -Pattern '^APP_KEY=')) { Add-Content .env "`nAPP_KEY=" }
Step -DockerArguments @('compose','build','app')
Step -DockerArguments @('compose','up','-d')
Step -DockerArguments @('compose','exec','-T','app','sh','-c','mkdir -p storage/framework/views storage/framework/sessions storage/framework/cache/data storage/logs bootstrap/cache && chmod -R a+rwX storage bootstrap/cache && rm -f bootstrap/cache/*.php')
Step -DockerArguments @('compose','exec','-T','app','composer','install','--no-interaction','--prefer-dist','--no-scripts')
Step -DockerArguments @('compose','exec','-T','app','php','artisan','config:clear')
if (!(Select-String -Path .env -Pattern '^APP_KEY=.+')) { Step -DockerArguments @('compose','exec','-T','app','php','artisan','key:generate','--force') }
Step -DockerArguments @('compose','exec','-T','app','php','artisan','migrate','--force')
Step -DockerArguments @('compose','exec','-T','app','php','artisan','shop:import')
Step -DockerArguments @('compose','exec','-T','app','php','artisan','storage:link','--force')
Step -DockerArguments @('compose','exec','-T','app','php','artisan','optimize:clear')
Step -DockerArguments @('compose','restart','app','web')
Write-Host 'Open http://localhost:8080. Create admin: docker compose exec app php artisan shop:admin'
