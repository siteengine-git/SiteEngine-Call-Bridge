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

const path=process.argv[2]||'siteengine-call-bridge-0.1.3-denial-review-20261103.zip';
const prior=readZip('siteengine-call-bridge-0.1.2-lease-dni-review-20261028.zip');
assert(sha(prior.bytes)==='6b240e1b7633cac208ad08834df76dc9f7d741168884560b8e0aae5ce161c5de','Preserve original 0.1.2 ZIP');
const candidate=readZip(path), files=candidate.files;
assert(files.size===51&&files.size===prior.files.size,'Payload count');
const allowed=new Set(['siteengine-call-bridge.php','README.md','CHANGELOG.md','RELEASE-MANIFEST.sha256','src/Admin/TrackingNumbersPage.php']);
let protectedCount=0;
for(const [n,b] of prior.files){assert(files.has(n),'Missing prior payload');if(!allowed.has(n)){assert(files.get(n).equals(b),'Unapproved runtime change '+n);protectedCount++;}}
const page=prior.files.get('src/Admin/TrackingNumbersPage.php').toString();
const from="wp_die('Insufficient permissions.')",to="wp_die('Insufficient permissions.','',['response'=>403])";
assert(page.split(from).length===4,'Three existing denial sites');
assert(files.get('src/Admin/TrackingNumbersPage.php').equals(Buffer.from(page.split(from).join(to))),'Only approved denial response changes');
assert(files.get('siteengine-call-bridge.php').equals(Buffer.from(prior.files.get('siteengine-call-bridge.php').toString().replaceAll('0.1.2','0.1.3'))),'Header/constants only version change');
const main=files.get('siteengine-call-bridge.php').toString();
assert(main.includes("define('SECB_VERSION', '0.1.3')")&&main.includes('Version:           0.1.3')&&main.includes("define('SECB_DB_VERSION', '1.0.0')"),'Version/DB metadata');
assert(files.get('README.md').toString().includes('Call Bridge 0.1.3')&&files.get('CHANGELOG.md').toString().includes('## 0.1.3'),'Release documents');
const manifest=files.get('RELEASE-MANIFEST.sha256').toString().trim().split(/\r?\n/),seen=new Set();assert(manifest.length===50,'Manifest count');
for(const line of manifest){const m=line.match(/^([a-f0-9]{64})  \.\/(.+)$/);assert(m&&!seen.has(m[2])&&files.has(m[2]),'Manifest shape/ownership');seen.add(m[2]);assert(sha(files.get(m[2]))===m[1],'Manifest payload hash');}
for(const [n,b] of files){assert(fs.readFileSync(n).equals(b),'Source/package equality '+n);assert(!/(?:^|\/)(?:\.git|STATUS.md|tests|tools|\.secrets)|\.(?:zip|sql|log|key|pem)$/.test(n),'Forbidden publication path');assert(!/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----|\bAKIA[A-Z0-9]{16}\b|\b(?:AC|SK)[a-f0-9]{32}\b/i.test(b.toString()),'Credential pattern');}
assert([...files].filter(([n])=>n.endsWith('.php')).length===47,'PHP count');
assert(fs.readFileSync(path+'.sha256','utf8').split(/\s+/)[0]===sha(candidate.bytes),'ZIP sidecar');
console.log(JSON.stringify({pass:true,files:files.size,manifest_entries:manifest.length,unchanged_prior_files:protectedCount,php_files:47,zip_sha256:sha(candidate.bytes),manifest_sha256:sha(files.get('RELEASE-MANIFEST.sha256')),bytes:candidate.bytes.length,version:'0.1.3',db:'1.0.0',scope:'three denial responses plus release metadata; no stable promotion'}));
