param([Parameter(Mandatory=$true)][string]$Php)
$ErrorActionPreference='Stop'
$root=Split-Path $PSScriptRoot -Parent
$evidence=Join-Path $root '.secrets\lease-dni'
New-Item -ItemType Directory -Path $evidence -Force | Out-Null
$node=(Get-Command node -ErrorAction Stop).Source
$lint=@('tests/lease-repository.php','tools/acceptance/lease-sql.php','tools/acceptance/browser-fixtures.php','tools/acceptance/dni-page.php')
foreach($file in $lint){& $Php -n -l (Join-Path $root $file);if($LASTEXITCODE -ne 0){exit 2}}
& $Php -n (Join-Path $PSScriptRoot 'lease-repository.php') (Join-Path $evidence 'lease-results.json') | Tee-Object -FilePath (Join-Path $evidence 'lease-results.txt')
$leaseExit=$LASTEXITCODE
& $node (Join-Path $PSScriptRoot 'dni/unit.cjs') (Join-Path $evidence 'dni-unit-results.json') | Tee-Object -FilePath (Join-Path $evidence 'dni-unit-results.txt')
$dniExit=$LASTEXITCODE
Write-Output "LEASE_EXIT=$leaseExit DNI_VM_EXIT=$dniExit; red contracts are preserved, not accepted failures."
if($leaseExit -or $dniExit){exit 1}
exit 0
