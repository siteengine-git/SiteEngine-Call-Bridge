'use strict';
// Actual shipped JS in Node VM; deliberately small DOM/clock/fetch doubles.
const vm=require('node:vm'),fs=require('node:fs'),extract=require('./extract.cjs');
class Element {
 constructor(tag='span',text='',attrs={}){this.nodeType=1;this.tag=tag;this.textContent=text;this.attrs={...attrs};this.children=[];this.classes=new Set();this.classList={add:n=>this.classes.add(n),remove:n=>this.classes.delete(n)};}
 getAttribute(n){return Object.hasOwn(this.attrs,n)?String(this.attrs[n]):null;}
 hasAttribute(n){return Object.hasOwn(this.attrs,n);}
 setAttribute(n,v){this.attrs[n]=String(v);}
 removeAttribute(n){delete this.attrs[n];}
 matches(s){return s.split(',').some(s=>{if(s==='a[href^="tel:"]')return this.tag==='a'&&(this.attrs.href||'').startsWith('tel:');if(s[0]==='.')return this.classes.has(s.slice(1));const m=s.match(/^\[([^=\]]+)(?:="([^"]*)")?\]$/);return m&&this.hasAttribute(m[1])&&(m[2]===undefined||this.getAttribute(m[1])===m[2]);});}
 querySelectorAll(s){return this.children.flatMap(c=>[...(c.matches(s)?[c]:[]),...c.querySelectorAll(s)]);}
}
const flush=()=>new Promise(r=>setImmediate(r));
async function boot(options={}){
 let now=1767225600000,nextTimer=1;const timers=new Map(),observers=[],requests=[],storage=new Map();
 if(options.stored!==undefined)storage.set('secb_attribution',options.stored);
 const nodes={business:new Element('a','(202) 555-0190',{href:'tel:+12025550190'}),label:new Element('a','Call us',{href:'tel:+12025550190'}),unrelated:new Element('a','(202) 555-0199',{href:'tel:+12025550199'}),explicit:new Element('span','Original explicit',{'data-se-phone':''})};
 const root=new Element('html');root.children=Object.values(nodes);const document={nodeType:9,documentElement:root,referrer:'',querySelectorAll:s=>root.querySelectorAll(s)};
 const replies=[...(options.replies||[])];
 const context={document,location:{search:options.query??'?utm_source=fixture_alpha',href:'https://fixture.invalid/'},console,Promise,Number,JSON,Math,Array,String,decodeURIComponent,
  Date:class extends Date{static now(){return now;}},crypto:{randomUUID:()=> 'fixture-session-0001'},
  localStorage:{getItem(k){if(options.storageUnavailable)throw Error('storage unavailable');return storage.get(k)||null;},setItem(k,v){if(options.storageUnavailable)throw Error('storage unavailable');storage.set(k,v);},removeItem(k){storage.delete(k);}},
  setTimeout(fn,ms){const id=nextTimer++;timers.set(id,{fn,at:now+ms});return id;},clearTimeout(id){timers.delete(id);},
  MutationObserver:class{constructor(fn){this.fn=fn;this.active=false;observers.push(this);}observe(){this.active=true;}disconnect(){this.active=false;}},
  fetch(url,init){requests.push({url,init});const response=replies.shift();if(!response)return Promise.reject(Error('unexpected fixture request'));if(response.reject)return Promise.reject(Error('network'));if(response.never)return new Promise(()=>{});return Promise.resolve({ok:response.ok!==false,status:response.status||200,json:()=>response.badJson?Promise.reject(Error('bad json')):Promise.resolve(response.data)});}
 };
 context.window=context;let error=null;try{vm.runInNewContext(extract(),context,{timeout:1000});}catch(e){error=e;}
 await flush();await flush();
 return {nodes,requests,timers,storage,error,append(id,element){nodes[id]=element;root.children.push(element);for(const o of [...observers])if(o.active)o.fn([{addedNodes:[element]}]);},async advance(ms){now+=ms;for(const [id,t]of [...timers])if(t.at<=now){timers.delete(id);t.fn();await flush();await flush();}}};
}
const number=(n='01')=>({data:{tracking_number:'+120255501'+n,display_phone:'(202) 555-01'+n,lease_minutes:30,ttl_days:90}});
const results=[];function check(ok,name){results.push({ok:!!ok,name});console.log((ok?'PASS ':'FAIL ')+name);}
(async()=>{
 let r=await boot({query:''});check(!r.requests.length&&!r.nodes.business.hasAttribute('data-secb-swapped'),'no attribution: no request, insertion or hiding');
 r=await boot({query:'',stored:'{broken'});check(!r.requests.length&&!r.error,'malformed storage without attribution safely ignored');
 r=await boot({query:'',stored:JSON.stringify({session_id:'fixture-expired',expires_at:1})});check(!r.requests.length&&!r.storage.has('secb_attribution'),'expired stored context removed without allocation');
 r=await boot({storageUnavailable:true,replies:[number()]});check(r.nodes.business.getAttribute('href')==='tel:+12025550101','fresh attribution works when storage read/write unavailable');
 r=await boot({stored:'{broken',replies:[number()]});check(r.nodes.business.getAttribute('href')==='tel:+12025550101','malformed storage does not block fresh attribution');
 r=await boot({query:'',stored:JSON.stringify({session_id:'fixture-stored',utm_source:'fixture_alpha'}),replies:[number()]});check(JSON.parse(r.requests[0].init.body).session_id==='fixture-stored','stored attribution reuses synthetic session');
 r=await boot({replies:[number(),number()]});check(r.nodes.business.textContent==='(202) 555-0101'&&r.nodes.explicit.textContent==='(202) 555-0101','business and explicit insertion succeeds');
 check(r.nodes.label.textContent==='Call us'&&r.nodes.label.getAttribute('href')==='tel:+12025550101','non-phone business link label preserved');
 check(r.nodes.unrelated.getAttribute('href')==='tel:+12025550199'&&!r.nodes.unrelated.hasAttribute('data-secb-swapped'),'unrelated telephone link unchanged');
 check(r.requests[0].init.credentials==='omit'&&JSON.parse(r.requests[0].init.body).utm_source==='fixture_alpha','session payload and credential omission preserved');
 await r.advance(900000);check(r.requests.length===2&&r.nodes.business.getAttribute('href')==='tel:+12025550101','successful same-number renewal keeps insertion');
 for(const failure of [{data:{tracking_number:null,pool_exhausted:true}},{reject:true},{ok:false,status:503},{badJson:true}]){
  r=await boot({replies:[failure]});check(r.nodes.business.getAttribute('href')==='tel:+12025550190'&&!r.nodes.business.classes.size&&!r.nodes.explicit.classes.size,'allocation failure restores original and unhides: '+JSON.stringify(failure));
 }
 for(const failure of [{data:{tracking_number:null}},{reject:true},{ok:false,status:429}]){
  r=await boot({replies:[number(),failure]});await r.advance(900000);check(r.nodes.business.getAttribute('href')==='tel:+12025550190'&&r.nodes.business.textContent==='(202) 555-0190'&&r.nodes.explicit.textContent==='Original explicit','renewal failure restores all originals: '+JSON.stringify(failure));
  r.append('late',new Element('a','(202) 555-0190',{href:'tel:+12025550190'}));check(!r.nodes.late.hasAttribute('data-secb-swapped'),'failure disconnects stale late-node observer');
 }
 r=await boot({replies:[{never:true}]});check(r.nodes.business.classes.has('secb-dni-pending'),'in-flight allocation marks eligible nodes pending');await r.advance(2500);check(!r.nodes.business.classes.size&&r.nodes.business.getAttribute('href')==='tel:+12025550190','stalled network anti-flicker timeout unhides original');
 r=await boot({replies:[number()]});r.append('late',new Element('a','(202) 555-0190',{href:'tel:+12025550190'}));r.append('lateUnrelated',new Element('a','Other',{href:'tel:+12025550199'}));check(r.nodes.late.getAttribute('href')==='tel:+12025550101'&&r.nodes.lateUnrelated.getAttribute('href')==='tel:+12025550199','late business node inserted; unrelated late node preserved');
 r=await boot({replies:[number('01'),number('02')]});await r.advance(900000);
 check(r.nodes.business.getAttribute('href')==='tel:+12025550102','DEFECT D1: replacement number on renewal must update ordinary business links');
 check(r.nodes.explicit.textContent==='(202) 555-0102','replacement renewal does update explicit marker (contrasts D1)');
 const failed=results.filter(x=>!x.ok).length;console.log(`RESULT ${results.length-failed} PASS / ${failed} FAIL; Node VM with DOM/fetch/clock doubles, not browser or SQL acceptance.`);
 if(process.argv[2])fs.writeFileSync(process.argv[2],JSON.stringify(results,null,2)+'\n');process.exitCode=failed?1:0;
})().catch(e=>{console.error(e);process.exitCode=2;});
