'use strict';
const fs=require('node:fs'),zlib=require('node:zlib'),crypto=require('node:crypto'),vm=require('node:vm');
const assert=(v,m)=>{if(!v)throw Error(m);};
const sha=b=>crypto.createHash('sha256').update(b).digest('hex');
function crc(b){let c=0xffffffff;for(const n of b){c^=n;for(let i=0;i<8;i++)c=(c>>>1)^((c&1)?0xedb88320:0);}return(c^0xffffffff)>>>0;}
function readZip(path){
 const b=fs.readFileSync(path);let e=b.length-22;
 while(e>=0&&b.readUInt32LE(e)!==0x06054b50)e--;assert(e>=0,'ZIP end record');
 let p=b.readUInt32LE(e+16);const out=new Map(),count=b.readUInt16LE(e+10);
 for(let i=0;i<count;i++){
  assert(b.readUInt32LE(p)===0x02014b50,'Central record');
  const method=b.readUInt16LE(p+10),expected=b.readUInt32LE(p+16),packed=b.readUInt32LE(p+20),size=b.readUInt32LE(p+24);
  const len=b.readUInt16LE(p+28),extra=b.readUInt16LE(p+30),comment=b.readUInt16LE(p+32),offset=b.readUInt32LE(p+42);
  const name=b.subarray(p+46,p+46+len).toString();assert(name.startsWith('siteengine-call-bridge/')&&!name.split('/').includes('..'),'Unsafe ZIP path');
  assert(b.readUInt32LE(offset)===0x04034b50,'Local record');
  const start=offset+30+b.readUInt16LE(offset+26)+b.readUInt16LE(offset+28);
  assert(method===0||method===8,'Compression method');
  const body=method===8?zlib.inflateRawSync(b.subarray(start,start+packed)):b.subarray(start,start+packed);
  assert(body.length===size&&crc(body)===expected,'CRC/size '+name);
  if(!name.endsWith('/')){const rel=name.slice('siteengine-call-bridge/'.length);assert(!out.has(rel),'Duplicate entry');out.set(rel,body);}
  p+=46+len+extra+comment;
 }
 return {bytes:b,files:out};
}
const old=readZip('siteengine-call-bridge-0.1.0.zip');
assert(old.bytes.length===52306&&sha(old.bytes)==='5a1b28ac80dfcd2b6e09cd34bdc9b2d126774b588a501d852fe01f4bdd563fb8','Original archive changed');
const path=process.argv[2]||'siteengine-call-bridge-0.1.2-lease-dni-review-20261028.zip',candidate=readZip(path),files=candidate.files;
const additions=['src/Integration/BaseIntegration.php','src/Support/Readiness.php','src/Support/SharedBusinessContext.php','templates/admin/setup-status.php','readme-tabs/call-bridge.php'];
assert(files.size===old.files.size+additions.length,'Unexpected artifact file count');
for(const n of additions)assert(files.has(n),'Missing addition');
const changed=new Set(['siteengine-call-bridge.php','src/Plugin.php','src/Admin/Menu.php','src/Admin/SettingsPage.php','README.md','CHANGELOG.md','RELEASE-MANIFEST.sha256','src/DB/NumberLeaseRepository.php','src/DB/TrackingNumberRepository.php','src/DB/AttributionSessionRepository.php','src/Webhook/TrackSessionController.php','src/Frontend/DniSnippet.php']);
let protectedCount=0;
for(const [name,body] of old.files){assert(files.has(name),'Missing baseline file');if(!changed.has(name)){assert(files.get(name).equals(body),'Protected baseline changed: '+name);protectedCount++;}}
for(const [name,body] of files){
 assert(fs.readFileSync(name).equals(body),'Packaged/source bytes differ: '+name);
 assert(!/(?:^|\/)(?:\.git|STATUS.md|tests|verification|tools|\.secrets)|\.(?:zip|sql|log|key|pem)$/.test(name),'Forbidden publication path');
 assert(!/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----|\bAKIA[A-Z0-9]{16}\b|\b(?:AC|SK)[a-f0-9]{32}\b/i.test(body.toString()),'Credential pattern '+name);
}
const manifest=files.get('RELEASE-MANIFEST.sha256').toString().trim().split(/\r?\n/);
assert(manifest.length===files.size-1,'Manifest count');
const seen=new Set();
for(const line of manifest){const m=line.match(/^([a-f0-9]{64})\s+\.\/(.+)$/);assert(m&&!seen.has(m[2]),'Manifest format/duplicate');seen.add(m[2]);assert(files.has(m[2])&&sha(files.get(m[2]))===m[1],'Manifest hash');}
const prior=readZip('siteengine-call-bridge-0.1.1-webhook-layout-review-20261027.zip');
assert(sha(prior.bytes)==='ad202e02748cab8dcd779790d60a1fb9f97d4401ac4909fd3b00896d9edeb993','Installed review archive changed');
const bounded=new Set(['siteengine-call-bridge.php','README.md','CHANGELOG.md','RELEASE-MANIFEST.sha256','src/DB/NumberLeaseRepository.php','src/DB/TrackingNumberRepository.php','src/DB/AttributionSessionRepository.php','src/Webhook/TrackSessionController.php','src/Frontend/DniSnippet.php']);
let priorProtected=0;for(const [name,body] of prior.files){if(!bounded.has(name)){assert(files.get(name).equals(body),'Out-of-scope installed file changed: '+name);priorProtected++;}}
assert(files.size===prior.files.size,'Unexpected package addition/removal');
console.log('PASS '+priorProtected+' files unchanged from installed f763b3c review; changes bounded to lease/session/DNI and release metadata.');
const main=files.get('siteengine-call-bridge.php').toString();
assert(main.includes("define('SECB_VERSION', '0.1.2')")&&main.includes('Version:           0.1.2')&&main.includes("define('SECB_DB_VERSION', '1.0.0')"),'Version coherence');
assert(files.get('CHANGELOG.md').toString().includes('## 0.1.2'),'Changelog version');
let js=files.get('src/Frontend/DniSnippet.php').toString().split('<script id="secb-dni-script">')[1].split('</script>')[0],n=0;
js=js.replace(/<\?php[\s\S]*?\?>/g,()=>['"https://fixture.invalid/"','[]','90'][n++]);assert(n===3,'JS placeholders');new vm.Script(js);
const php=[...files].filter(([name])=>name.endsWith('.php'));assert(php.length===47&&php.every(([,b])=>b.subarray(0,5).toString()==='<?php'),'PHP count/opening');
assert(fs.readFileSync(path+'.sha256','utf8').split(/\s+/)[0]===sha(candidate.bytes),'Published SHA sidecar');
console.log('PASS ZIP CRC/size, '+manifest.length+' manifest hashes, '+files.size+' source/artifact byte comparisons, '+protectedCount+' unchanged protected baseline files, 47 PHP openings, DNI JS syntax, version coherence, artifact path/credential scan, original archive preservation.');
console.log('CANDIDATE SHA256 '+sha(candidate.bytes)+' BYTES '+candidate.bytes.length+'; NOT DEPLOYED / NOT ACCEPTED');
