param([Parameter(Mandatory=$true)][string]$Php,[string]$EvidenceName='lease-dni-0.1.2')
$ErrorActionPreference='Stop'
$root=Split-Path $PSScriptRoot -Parent
$evidence=Join-Path $root ('.secrets\'+$EvidenceName)
New-Item -ItemType Directory -Path $evidence -Force | Out-Null
$node=(Get-Command node -ErrorAction Stop).Source
$lint=@('tests/lease-repository.php','tests/controller-session.php','tools/acceptance/lease-sql.php','tools/acceptance/browser-fixtures.php','tools/acceptance/dni-page.php')
foreach($file in $lint){& $Php -n -l (Join-Path $root $file);if($LASTEXITCODE -ne 0){exit 2}}
& $Php -n (Join-Path $PSScriptRoot 'lease-repository.php') (Join-Path $evidence 'lease-results.json') | Tee-Object -FilePath (Join-Path $evidence 'lease-results.txt')
$leaseExit=$LASTEXITCODE
& $node (Join-Path $PSScriptRoot 'dni/unit.cjs') (Join-Path $evidence 'dni-unit-results.json') | Tee-Object -FilePath (Join-Path $evidence 'dni-unit-results.txt')
$dniExit=$LASTEXITCODE
& $Php -n (Join-Path $PSScriptRoot 'controller-session.php') (Join-Path $evidence 'controller-results.json') | Tee-Object -FilePath (Join-Path $evidence 'controller-results.txt')
$controllerExit=$LASTEXITCODE
Write-Output "LEASE_EXIT=$leaseExit DNI_VM_EXIT=$dniExit; CONTROLLER_EXIT=$controllerExit; local doubles only; native SQL pending."
if($leaseExit -or $dniExit -or $controllerExit){exit 1}
exit 0
