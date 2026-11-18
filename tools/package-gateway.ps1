param([string]$ZipPath)
$ErrorActionPreference='Stop'
$root=Split-Path $PSScriptRoot -Parent
if(!$ZipPath){$ZipPath=Join-Path $root 'siteengine-call-bridge-gateway-0.1.0-review-r2-20261116.zip'}
if(Test-Path -LiteralPath $ZipPath){throw 'Preserve prior archive'}
$paths=@('gateway/README.md','gateway/Router.php','gateway/entry.php','gateway/VERSION','src/Gateway/Protocol.php')
$manifest=@($paths|ForEach-Object {((Get-FileHash -LiteralPath (Join-Path $root $_)).Hash.ToLower())+'  ./'+$_})
[IO.File]::WriteAllText((Join-Path $root 'gateway/GATEWAY-MANIFEST.sha256'),($manifest -join "`n")+"`n",[Text.UTF8Encoding]::new($false))
Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip=[IO.Compression.ZipFile]::Open($ZipPath,[IO.Compression.ZipArchiveMode]::Create)
try{foreach($p in @($paths)+@('gateway/GATEWAY-MANIFEST.sha256')){$e=$zip.CreateEntry('siteengine-call-bridge-gateway/'+$p,[IO.Compression.CompressionLevel]::Optimal);$e.LastWriteTime=[DateTimeOffset]::new(2026,11,16,0,0,0,[TimeSpan]::Zero);$input=[IO.File]::OpenRead((Join-Path $root $p));$output=$e.Open();try{$input.CopyTo($output)}finally{$input.Dispose();$output.Dispose()}}}finally{$zip.Dispose()}
$hash=(Get-FileHash -LiteralPath $ZipPath).Hash.ToLower()
[IO.File]::WriteAllText($ZipPath+'.sha256',$hash+'  '+[IO.Path]::GetFileName($ZipPath)+"`n",[Text.UTF8Encoding]::new($false))
"ZIP=$ZipPath SHA256=$hash FILES=6"
