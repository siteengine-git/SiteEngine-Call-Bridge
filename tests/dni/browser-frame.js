/* Real browser DOM/MutationObserver; fetch, storage and lease timers are fixtures. */
(function(){
'use strict';
var scenario=new URLSearchParams(location.search).get('case');
var nativeTimer=window.setTimeout.bind(window),timers=new Map(),next=1,requests=[],memory={};
var track=function(n){return {tracking_number:'+120255501'+n,display_phone:'(202) 555-01'+n,lease_minutes:30};};
var replies=[track('01')];
if(scenario==='renewal-failure')replies.push({networkFailure:true});
if(scenario==='replacement-renewal')replies.push(track('02'));
if(scenario==='allocation-network-failure')replies=[{networkFailure:true}];
if(scenario==='stalled-network')replies=[{never:true}];
if(scenario==='storage-malformed')memory.secb_attribution='{broken';
Object.defineProperty(window,'localStorage',{configurable:true,value:{getItem:function(k){if(scenario==='storage-unavailable')throw Error('synthetic storage failure');return memory[k]||null;},setItem:function(k,v){if(scenario==='storage-unavailable')throw Error('synthetic storage failure');memory[k]=v;},removeItem:function(k){delete memory[k];}}});
window.setTimeout=function(fn,ms){var id=next++;timers.set(id,{fn:fn,ms:ms});return id;};window.clearTimeout=function(id){timers.delete(id);};
window.fetch=function(url,init){requests.push({url:url,init:init});var r=replies.shift();if(!r||r.networkFailure)return Promise.reject(Error('synthetic network failure'));if(r.never)return new Promise(function(){});return Promise.resolve({ok:true,json:function(){return Promise.resolve(r);}});};
// The test elements must never launch a telephone handler, even if clicked manually.
document.addEventListener('click',function(e){if(e.target.closest&&e.target.closest('a[href^="tel:"]')){e.preventDefault();e.stopImmediatePropagation();}},true);
var wait=function(){return new Promise(function(r){nativeTimer(r,15);});};
var assert=function(ok,message){if(!ok)throw Error(message);};
window.runDniBrowserCase=async function(){
 var result={scenario:scenario,ok:false};
 try{
  await wait();var business=document.getElementById('business'),explicit=document.getElementById('explicit'),other=document.getElementById('other');
  if(scenario==='no-attribution')assert(requests.length===0&&business.getAttribute('href')==='tel:+12025550190','no-attribution visit mutated or requested allocation');
  else if(scenario==='allocation-network-failure')assert(business.getAttribute('href')==='tel:+12025550190'&&!business.classList.contains('secb-dni-pending'),'failed allocation did not restore');
  else if(scenario==='stalled-network'){
   assert(business.classList.contains('secb-dni-pending'),'missing initial pending state');for(var [id,t]of timers)if(t.ms===2500){timers.delete(id);t.fn();}await wait();assert(!business.classList.contains('secb-dni-pending'),'anti-flicker did not restore stalled request');
  }else{
   assert(business.getAttribute('href')==='tel:+12025550101','initial insertion failed');assert(other.getAttribute('href')==='tel:+12025550199','unrelated link changed');assert(document.getElementById('label').textContent==='Call us','non-phone label changed');
   if(scenario==='late-nodes'){var late=document.createElement('a');late.setAttribute('href','tel:+12025550190');late.textContent='(202) 555-0190';document.getElementById('nondial').append(late);await wait();assert(late.getAttribute('href')==='tel:+12025550101','real MutationObserver missed late node');}
   if(scenario==='renewal-failure'||scenario==='replacement-renewal'){
    for(var [id,t]of timers)if(t.ms===900000){timers.delete(id);t.fn();}await wait();
    if(scenario==='renewal-failure')assert(business.getAttribute('href')==='tel:+12025550190'&&explicit.textContent==='Original explicit','renewal failure did not restore originals');
    else assert(business.getAttribute('href')==='tel:+12025550102'&&explicit.textContent==='(202) 555-0102','DEFECT D1: ordinary business link retained old number after replacement renewal');
   }
  }
  result.ok=true;
 }catch(e){result.error=e.message;}
 result.requests=requests.length;parent.postMessage({cbDniResult:result},location.origin);
};
})();
