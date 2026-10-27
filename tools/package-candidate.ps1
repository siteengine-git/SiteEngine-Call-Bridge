param([string]$ZipPath)
$ErrorActionPreference='Stop'
$root=Split-Path $PSScriptRoot -Parent
if(!$ZipPath){$ZipPath=Join-Path $root 'siteengine-call-bridge-0.1.1-candidate.zip'}
if(Test-Path -LiteralPath $ZipPath){throw 'Preserve existing archive; choose a new output path'}
$baseline=Join-Path $root 'siteengine-call-bridge-0.1.0.zip'
if((Get-FileHash -LiteralPath $baseline).Hash.ToLower() -ne '5a1b28ac80dfcd2b6e09cd34bdc9b2d126774b588a501d852fe01f4bdd563fb8'){throw 'Retained baseline hash mismatch'}
Add-Type -AssemblyName System.IO.Compression.FileSystem
$old=[IO.Compression.ZipFile]::OpenRead($baseline)
try{$reader=[IO.StreamReader]::new($old.GetEntry('siteengine-call-bridge/RELEASE-MANIFEST.sha256').Open());try{$lines=$reader.ReadToEnd() -split "\r?\n"}finally{$reader.Dispose()}}finally{$old.Dispose()}
$paths=@($lines|Where-Object {$_ -match '^([a-f0-9]{64})\s+\./(.+)$'}|ForEach-Object {$_ -replace '^[a-f0-9]{64}\s+\./',''})
$paths+=@('src/Integration/BaseIntegration.php','src/Support/Readiness.php','src/Support/SharedBusinessContext.php','templates/admin/setup-status.php','readme-tabs/call-bridge.php')
$paths=@($paths|Sort-Object -Unique)
$manifest=@()
foreach($path in $paths){
 $full=[IO.Path]::GetFullPath((Join-Path $root $path))
 if(!$full.StartsWith($root+[IO.Path]::DirectorySeparatorChar,[StringComparison]::OrdinalIgnoreCase)){throw 'Unsafe path'}
 if(!(Test-Path -LiteralPath $full -PathType Leaf)){throw "Missing package file $path"}
 $manifest+=((Get-FileHash -LiteralPath $full).Hash.ToLower()+'  ./'+$path)
}
[IO.File]::WriteAllText((Join-Path $root 'RELEASE-MANIFEST.sha256'),($manifest -join "`n")+"`n",[Text.UTF8Encoding]::new($false))
$archive=[IO.Compression.ZipFile]::Open($ZipPath,[IO.Compression.ZipArchiveMode]::Create)
try{
 foreach($path in @($paths)+@('RELEASE-MANIFEST.sha256')){
  $entry=$archive.CreateEntry('siteengine-call-bridge/'+$path,[IO.Compression.CompressionLevel]::Optimal)
  $entry.LastWriteTime=[DateTimeOffset]::new(2026,10,27,0,0,0,[TimeSpan]::Zero)
  $input=[IO.File]::OpenRead((Join-Path $root $path));$output=$entry.Open()
  try{$input.CopyTo($output)}finally{$input.Dispose();$output.Dispose()}
 }
}finally{$archive.Dispose()}
$hash=(Get-FileHash -LiteralPath $ZipPath).Hash.ToLower()
[IO.File]::WriteAllText($ZipPath+'.sha256',$hash+'  '+[IO.Path]::GetFileName($ZipPath)+"`n",[Text.UTF8Encoding]::new($false))
"ZIP=$ZipPath SHA256=$hash FILES=$($paths.Count+1)"
