$ErrorActionPreference = 'Stop'
Set-Location $PSScriptRoot
if (!(Test-Path .\compose.yaml)) { throw 'Run this script from the project folder containing compose.yaml.' }
$reportPath = Join-Path $PSScriptRoot 'shop-diagnostic.txt'
'PAPACHEAT diagnostic: schema only, no passwords or customer rows.' | Set-Content -Path $reportPath -Encoding UTF8

function Run-Diagnostic([string]$Label, [string[]]$DockerArguments) {
    Write-Host $Label
    Add-Content -Path $reportPath -Encoding UTF8 -Value ("`r`n--- " + $Label + " ---")
    $oldPreference = $ErrorActionPreference
    try {
        $ErrorActionPreference = 'Continue'
        $lines = & docker @DockerArguments 2>&1
        $resultCode = $LASTEXITCODE
    } finally {
        $ErrorActionPreference = $oldPreference
    }
    $lines | ForEach-Object {
        $line = $_.ToString()
        Write-Host $line
        Add-Content -Path $reportPath -Encoding UTF8 -Value $line
    }
    Add-Content -Path $reportPath -Encoding UTF8 -Value ("Exit code: " + $resultCode)
}

Run-Diagnostic -Label 'Containers' -DockerArguments @('compose','ps')
Run-Diagnostic -Label 'Repair Laravel writable directories' -DockerArguments @('compose','exec','-T','--user','root','app','sh','-c','mkdir -p storage/logs storage/framework/views storage/framework/sessions storage/framework/cache/data bootstrap/cache && chown -R www-data:www-data storage bootstrap/cache && chmod -R ug+rwX storage bootstrap/cache')
Run-Diagnostic -Label 'Clear configuration cache' -DockerArguments @('compose','exec','-T','app','php','artisan','config:clear')
Run-Diagnostic -Label 'Migration status' -DockerArguments @('compose','exec','-T','app','php','artisan','migrate:status','--no-interaction')
foreach ($table in @('users','categories','products','orders','messages','alerts','visitors')) {
    Run-Diagnostic -Label ("Table: " + $table) -DockerArguments @('compose','exec','-T','app','php','artisan','db:table',$table,'--no-interaction')
}
Write-Host ("Report saved: " + $reportPath)
Write-Host 'Attach shop-diagnostic.txt in the chat. No database tables or records were modified.'
