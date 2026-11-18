param(
 [string]$Php,
 [Parameter(Mandatory=$true)][string]$BaseArchive
)
$ErrorActionPreference='Stop'
if(!$Php){$Php=(Get-Command php -ErrorAction Stop).Source}
if((Get-FileHash -LiteralPath $BaseArchive).Hash.ToLower() -ne '331a9d0e0693edf270f2d95ad6b8014c146283976a27ef707f0e91d0048abefe'){throw 'Expected original Base 2.2.2 archive'}
Add-Type -AssemblyName System.IO.Compression.FileSystem
$temp=Join-Path ([IO.Path]::GetTempPath()) ('secb-base-fixture-'+[guid]::NewGuid().ToString('N'))
$names=@('includes/class-admin.php','includes/class-module-registry.php','includes/class-readme-page.php','includes/class-shared-config.php')
$created=@()
try {
 [IO.Directory]::CreateDirectory((Join-Path $temp 'includes'))|Out-Null
 $zip=[IO.Compression.ZipFile]::OpenRead((Resolve-Path -LiteralPath $BaseArchive))
 try {foreach($name in $names){$target=Join-Path $temp $name;[IO.Compression.ZipFileExtensions]::ExtractToFile($zip.GetEntry('siteengine-base/'+$name),$target,$false);$created+=$target}}finally{$zip.Dispose()}
 foreach($mode in @('base','absent','unsafe-url')){
  & $Php -n (Join-Path $PSScriptRoot 'base-integration.php') $temp $mode
  if($LASTEXITCODE -ne 0){throw "Integration fixture failed: $mode"}
 }
} finally {
 foreach($file in $created){Remove-Item -LiteralPath $file}
 if(Test-Path -LiteralPath (Join-Path $temp 'includes')){Remove-Item -LiteralPath (Join-Path $temp 'includes')}
 if(Test-Path -LiteralPath $temp){Remove-Item -LiteralPath $temp}
}
