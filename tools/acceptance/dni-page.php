<?php
/** PREPARED ONLY: temporary SITE_ROOT/secb-acceptance-20261027-01/index.php. */
declare(strict_types=1);
if(PHP_SAPI==='cli'){fwrite(STDERR,"Prepared web fixture; do not run locally or bootstrap through CLI.\n");exit(2);}
require dirname(__DIR__).'/wp-load.php';
if(!is_user_logged_in()||!current_user_can('manage_options')){status_header(403);exit('Administrator fixture access required.');}
nocache_headers();header('X-Robots-Tag: noindex, nofollow');header('Content-Type: text/html; charset=utf-8');header("Content-Security-Policy: default-src 'none'; script-src 'unsafe-inline'; style-src 'unsafe-inline'; connect-src 'self'; base-uri 'none'; form-action 'none'");
$stateFile=dirname(rtrim(ABSPATH,'/')).'/.secb-acceptance/cb-lease-dni-20261027-01/browser-state.json';
if(!is_readable($stateFile)||is_link($stateFile)||(fileperms($stateFile)&0777)!==0600||fileowner($stateFile)!==posix_geteuid()){status_header(503);exit('Restricted fixture state unavailable.');}
$state=json_decode(file_get_contents($stateFile),true);$visitor=(string)($_GET['visitor']??'');$source=(string)($_GET['utm_source']??'');
if(($state['run']??'')!=='cb-lease-dni-20261027-01'||($state['phase']??'')!=='ready'||($state['expires']??0)<time()||!in_array($visitor,['a','b'],true)||!in_array($source,['cbaccept_20261027_browser','cbaccept_20261027_browser_alt'],true)){status_header(400);exit('Fixture run expired or parameters invalid.');}
foreach(['gclid','gbraid','wbraid','fbclid','msclkid'] as $key)if(isset($_GET[$key])){status_header(400);exit('No click-ID fixtures in this run.');}
global $wpdb;
foreach($state['ids'] as $i=>$id){$row=$wpdb->get_row($wpdb->prepare("SELECT phone_number,source,record_calls,notes FROM {$wpdb->prefix}se_cb_tracking_numbers WHERE id=%d",$id),ARRAY_A);if(!$row||$row['phone_number']!==$state['rows'][$i]['phone_number']||$row['source']!==$state['rows'][$i]['source']||(int)$row['record_calls']!==0||$row['notes']!=='cbaccept-20261027-01-browser'){status_header(503);exit('Fixture ownership/readiness conflict.');}}
?><!doctype html><html lang="en"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Isolated CB DNI acceptance</title>
<h1>Isolated CB DNI acceptance — no dialing</h1><p>Fixture links are inert. No other plugin head/DNI scripts are rendered. This does not prove coexistence.</p>
<div id="fixture-links" inert><a id="business" href="tel:+12025550190">(202) 555-0190</a> <a id="unrelated" href="tel:+12025550199">Unrelated</a> <span id="explicit" data-se-phone>Original explicit</span></div>
<button type="button" id="late">Append non-dialing late node</button><button type="button" id="storage-cleanup">Remove this fixture's browser storage</button><pre id="evidence"></pre>
<template id="fixture-script"><?php (new \SiteEngine\CallBridge\Frontend\DniSnippet())->output(); ?></template>
<script>
(function(){
'use strict';var sid=<?php echo wp_json_encode($state['sessions'][$visitor]); ?>,source=<?php echo wp_json_encode($source); ?>,evidence=document.querySelector('#evidence');
document.addEventListener('click',function(e){if(e.target.closest&&e.target.closest('a[href^="tel:"]')){e.preventDefault();e.stopImmediatePropagation();}},true);
if(!('inert' in HTMLElement.prototype)){evidence.textContent='STOP: inert support required; DNI not started.';return;}
try{var old=localStorage.getItem('secb_attribution'),context=old?JSON.parse(old):null;if(context&&context.session_id!==sid)throw Error('Existing context must be preserved; use an isolated browser session');if(context&&['gclid','gbraid','wbraid','fbclid','msclkid'].some(function(k){return !!context[k];}))throw Error('Click-ID context must be preserved; use an isolated browser session');if(!old)localStorage.setItem('secb_attribution',JSON.stringify({session_id:sid,utm_source:source,expires_at:Date.now()+3600000}));}catch(e){evidence.textContent='STOP: '+e.message;return;}
document.querySelector('#storage-cleanup').onclick=function(){var x=JSON.parse(localStorage.getItem('secb_attribution')||'null');if(x&&x.session_id===sid)localStorage.removeItem('secb_attribution');evidence.textContent='Own storage removed. Close this page now to stop lease renewal before server cleanup.';};
document.querySelector('#late').onclick=function(){var a=document.createElement('a');a.id='late-business';a.href='tel:+12025550190';a.textContent='(202) 555-0190';document.querySelector('#fixture-links').append(a);};
function report(){var rows=Array.from(document.querySelectorAll('#fixture-links > *'),function(e){return {id:e.id,text:e.textContent,href:e.getAttribute('href'),swapped:e.getAttribute('data-secb-swapped')};});evidence.textContent=JSON.stringify({synthetic_session:sid,source:source,elements:rows},null,2);}
new MutationObserver(report).observe(document.querySelector('#fixture-links'),{childList:true,subtree:true,attributes:true,characterData:true});
for(var n of document.querySelector('#fixture-script').content.children){var copy=document.createElement(n.tagName);for(var a of n.attributes)copy.setAttribute(a.name,a.value);copy.textContent=n.textContent;document.head.append(copy);}report();
})();
</script></html>
