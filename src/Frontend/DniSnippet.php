<?php
/**
 * Dynamic number insertion with short-lived exclusive number leases.
 */

declare(strict_types=1);
namespace SiteEngine\CallBridge\Frontend;
use SiteEngine\CallBridge\DB\TrackingNumberRepository;
use SiteEngine\CallBridge\Support\Config;
defined('ABSPATH') || exit;
final class DniSnippet
{
    public function register(): void
    {
        if (!is_admin()) add_action('wp_head', [$this, 'output'], 1);
    }

    public function output(): void
    {
        if ((defined('REST_REQUEST') && REST_REQUEST) || (defined('DOING_AJAX') && DOING_AJAX) || is_feed()) return;
        $api = wp_json_encode(esc_url_raw(rest_url(SECB_REST_NAMESPACE . '/')));
        $business = wp_json_encode((new TrackingNumberRepository())->activeForwardNumbers());
        ?>
<style id="secb-dni-style">.secb-dni-pending{opacity:0!important;transition:opacity 120ms ease-out}</style>
<script id="secb-dni-script">
(function(){
'use strict';
var API=<?php echo $api; ?>;
var BUSINESS=<?php echo $business; ?>||[];
var STORE='secb_attribution', PENDING='secb-dni-pending';
var SESSION_TTL_DAYS=<?php echo (int) Config::sessionTtlDays(); ?>;
var EXPLICIT='[data-se-phone],.se-tracking-number,[data-se-call-tracking]';
var pendingObserver=null, lateObserver=null, leaseTimer=null;

function parseQuery(){var o={},s=location.search;if(!s||s.length<2)return o;s.substring(1).split('&').forEach(function(p){if(!p)return;var i=p.indexOf('='),k=(i<0?p:p.substring(0,i)).toLowerCase(),v=i<0?'':decodeURIComponent(p.substring(i+1).replace(/\+/g,' '));if(k)o[k]=v;});return o;}
function read(){try{var r=localStorage.getItem(STORE);if(!r)return null;var x=JSON.parse(r);if(!x||!x.session_id)return null;if(x.expires_at&&Date.now()>x.expires_at){localStorage.removeItem(STORE);return null;}return x;}catch(e){return null;}}
function write(x){try{localStorage.setItem(STORE,JSON.stringify(x));}catch(e){}}
function newSid(){try{if(window.crypto&&crypto.randomUUID)return crypto.randomUUID();}catch(e){}return 'sid-'+Date.now().toString(36)+'-'+Math.random().toString(36).slice(2,14);}
function digits(v){return String(v||'').replace(/\D+/g,'');}
function samePhone(a,b){var x=digits(a),y=digits(b);if(x.length===11&&x.charAt(0)==='1')x=x.slice(1);if(y.length===11&&y.charAt(0)==='1')y=y.slice(1);return x.length>=10&&x===y;}
function isBusinessTel(el){if(!el||!el.matches||!el.matches('a[href^="tel:"]'))return false;var h=el.getAttribute('href')||'';for(var i=0;i<BUSINESS.length;i++)if(samePhone(h,BUSINESS[i]))return true;return false;}
function eligible(el){return !!(el&&el.nodeType===1&&(isBusinessTel(el)||(el.matches&&el.matches(EXPLICIT))));}
function scan(root){var out=[];if(eligible(root))out.push(root);if(root&&root.querySelectorAll)root.querySelectorAll('a[href^="tel:"],'+EXPLICIT).forEach(function(el){if(eligible(el))out.push(el);});return out;}
function markPending(root){scan(root).forEach(function(el){if(el.getAttribute('data-secb-swapped')!=='1')el.classList.add(PENDING);});}
function showPending(){document.querySelectorAll('.'+PENDING).forEach(function(el){el.classList.remove(PENDING);});}
function stopPending(){if(pendingObserver){pendingObserver.disconnect();pendingObserver=null;}showPending();}
function remember(el){if(!el.hasAttribute('data-secb-original-text'))el.setAttribute('data-secb-original-text',el.textContent||'');if(el.matches('a[href^="tel:"]')&&!el.hasAttribute('data-secb-original-href'))el.setAttribute('data-secb-original-href',el.getAttribute('href')||'');}
function swapElement(el,e164,display){remember(el);if(el.matches('a[href^="tel:"]')){el.setAttribute('href','tel:'+e164);var original=el.getAttribute('data-secb-original-text')||'';if(/^[\d\s\-\(\)\.\+x]{7,}$/i.test(original.trim()))el.textContent=display;}else{el.textContent=display;}el.classList.remove(PENDING);el.setAttribute('data-secb-swapped','1');}
function swapAll(e164,display){if(!e164){restoreAll();return;}stopPending();scan(document).forEach(function(el){swapElement(el,e164,display);});if(lateObserver)lateObserver.disconnect();lateObserver=new MutationObserver(function(muts){muts.forEach(function(m){Array.prototype.forEach.call(m.addedNodes||[],function(n){scan(n).forEach(function(el){if(el.getAttribute('data-secb-swapped')!=='1')swapElement(el,e164,display);});});});});try{lateObserver.observe(document.documentElement,{childList:true,subtree:true});}catch(e){}}
function restoreAll(){if(lateObserver){lateObserver.disconnect();lateObserver=null;}document.querySelectorAll('[data-secb-swapped="1"]').forEach(function(el){var h=el.getAttribute('data-secb-original-href'),t=el.getAttribute('data-secb-original-text');if(h!==null&&el.matches('a[href^="tel:"]'))el.setAttribute('href',h);if(t!==null)el.textContent=t;el.removeAttribute('data-secb-swapped');el.classList.remove(PENDING);});stopPending();}

var query=parseQuery(), stored=read();
var fresh=!!(query.gclid||query.gbraid||query.wbraid||query.fbclid||query.msclkid||query.utm_source);
if(!fresh&&!stored)return;
var sessionId=(stored&&stored.session_id)||newSid();
var payload={session_id:sessionId,gclid:query.gclid||(stored&&stored.gclid)||null,gbraid:query.gbraid||(stored&&stored.gbraid)||null,wbraid:query.wbraid||(stored&&stored.wbraid)||null,fbclid:query.fbclid||(stored&&stored.fbclid)||null,msclkid:query.msclkid||(stored&&stored.msclkid)||null,utm_source:query.utm_source||(stored&&stored.utm_source)||null,utm_medium:query.utm_medium||(stored&&stored.utm_medium)||null,utm_campaign:query.utm_campaign||(stored&&stored.utm_campaign)||null,utm_term:query.utm_term||(stored&&stored.utm_term)||null,utm_content:query.utm_content||(stored&&stored.utm_content)||null,landing_page:(stored&&stored.landing_page)||location.href,referrer:(stored&&stored.referrer)||document.referrer||null};

markPending(document);try{pendingObserver=new MutationObserver(function(muts){muts.forEach(function(m){Array.prototype.forEach.call(m.addedNodes||[],markPending);});});pendingObserver.observe(document.documentElement,{childList:true,subtree:true});}catch(e){}
var antiFlicker=setTimeout(function(){stopPending();},2500);

function persist(data){write({session_id:sessionId,tracking_number:data.tracking_number||null,display_phone:data.display_phone||null,gclid:payload.gclid,gbraid:payload.gbraid,wbraid:payload.wbraid,fbclid:payload.fbclid,msclkid:payload.msclkid,utm_source:payload.utm_source,utm_medium:payload.utm_medium,utm_campaign:payload.utm_campaign,utm_term:payload.utm_term,utm_content:payload.utm_content,landing_page:payload.landing_page,referrer:payload.referrer,expires_at:Date.now()+((data.ttl_days||SESSION_TTL_DAYS)*86400000)});}
function scheduleRenew(minutes){if(leaseTimer)clearTimeout(leaseTimer);var ms=Math.max(60000,Math.floor((Number(minutes)||30)*60000/2));leaseTimer=setTimeout(function(){refresh(true);},ms);}
function refresh(isRenewal){fetch(API+'track/session',{method:'POST',headers:{'Content-Type':'application/json','Accept':'application/json'},body:JSON.stringify(payload),credentials:'omit',keepalive:true}).then(function(r){if(!r.ok)throw new Error('http_'+r.status);return r.json();}).then(function(data){clearTimeout(antiFlicker);persist(data);if(data.tracking_number){swapAll(data.tracking_number,data.display_phone||data.tracking_number);scheduleRenew(data.lease_minutes||30);}else{restoreAll();}}).catch(function(){clearTimeout(antiFlicker);restoreAll();});}
refresh(false);
})();
</script>
        <?php
    }
}
