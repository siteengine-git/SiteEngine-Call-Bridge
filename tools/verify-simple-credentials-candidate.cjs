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




const path=process.argv[2]||'siteengine-call-bridge-0.2.3-ordinary-credentials-review-20261117.zip';
const before=readZip('siteengine-call-bridge-0.2.2-base-credential-migration-review-20261117.zip');
assert(sha(before.bytes)==='a31e383a409cfa116bc7bf1005964e6558e98285604e4878753903fe4a9a5226','Retained 0.2.2 archive');
const candidate=readZip(path),files=candidate.files;
const allowed=new Set(['siteengine-call-bridge.php','README.md','CHANGELOG.md','RELEASE-MANIFEST.sha256','src/Integration/BaseCredentials.php','src/Support/Readiness.php','templates/admin/setup-status.php','readme-tabs/call-bridge.php','ONBOARDING.md']);
assert(files.size===60&&before.files.size===60,'Exact package shape');let unchanged=0;
for(const[n,b]of before.files){assert(files.has(n),'Missing prior file '+n);if(!allowed.has(n)){assert(files.get(n).equals(b),'Unexpected runtime delta '+n);unchanged++;}}
for(const[n,b]of files){assert(fs.readFileSync(n).equals(b),'Source equality '+n);assert(!/(?:^|\/)(?:\.git|STATUS.md|tests|tools|\.secrets)|\.(?:zip|sql|log|key|pem)$/.test(n),'Forbidden payload');assert(!/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----|\bAKIA[A-Z0-9]{16}\b|\b(?:AC|SK)[a-f0-9]{32}\b/i.test(b.toString()),'Credential pattern');}
const lines=files.get('RELEASE-MANIFEST.sha256').toString().trim().split(/\r?\n/),seen=new Set();assert(lines.length===59,'Manifest size');
for(const line of lines){const m=line.match(/^([a-f0-9]{64})  \.\/(.+)$/);assert(m&&!seen.has(m[2])&&files.has(m[2]),'Manifest entry');seen.add(m[2]);assert(sha(files.get(m[2]))===m[1],'Manifest digest');}
const main=files.get('siteengine-call-bridge.php').toString();assert(main.includes("define('SECB_VERSION', '0.2.3')")&&main.includes('Version:           0.2.3')&&main.includes("define('SECB_DB_VERSION', '1.1.0')"),'Metadata');assert([...files.keys()].filter(n=>n.endsWith('.php')).length===55,'PHP count');assert(fs.readFileSync(path+'.sha256','utf8').split(/\s+/)[0]===sha(candidate.bytes),'Checksum');
console.log(JSON.stringify({pass:true,files:60,manifest_entries:59,unchanged_from_022:unchanged,PHP:55,version:'0.2.3',DB:'1.1.0',zip_sha256:sha(candidate.bytes),manifest_sha256:sha(files.get('RELEASE-MANIFEST.sha256')),bytes:candidate.bytes.length}));
