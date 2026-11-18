<?php
/** SQLite is real local persistence; wpdb/HTTP are adapters. No website or provider. */
declare(strict_types=1);
$extension = $argv[1] ?? '';
if (!is_file($extension)) throw new RuntimeException('Pass existing php_sqlite3 extension path; do not install dependencies');
$root = sys_get_temp_dir().'/cb-evidence-'.bin2hex(random_bytes(8)); mkdir($root, 0700);
$db = $root.'/synthetic.sqlite'; $receipts = []; $count = 0; $pids = [];
function check(bool $ok, string $name): void { global $count; if (!$ok) throw new RuntimeException('FAIL '.$name); $count++; echo 'PASS '.$name."\n"; }
function runEvidence(string $mode): array {
    global $root, $db, $extension, $receipts, $pids;
    $path = $root.'/'.count($receipts).'.json'; $receipts[] = $path;
    $worker = base64_encode(__DIR__.'/call-evidence-worker.php');
    $code = 'require base64_decode("'.$worker.'");';
    // ASCII startup arguments avoid the Windows proc_open Unicode-path limitation.
    $proc = proc_open([PHP_BINARY,'-n','-d','extension=./'.basename($extension),'-r',$code,$db,$mode,$path], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes, dirname(realpath($extension)));
    fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
    if (proc_close($proc) !== 0 || !is_file($path)) throw new RuntimeException('worker_failed:'.$out.$err);
    $r = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR); $pids[] = $r['pid']; return $r;
}
try {
    $seed = runEvidence('seed'); $a = $seed['evidence'];
    check($a['status']==='found' && $seed['call_count']===1, 'one labelled synthetic call persisted through shipped repository');
    check($a['call']['identity']==='twilio:CA'.str_repeat('0',31).'1', 'canonical identity preserved without inquiry alias');
    check($a['call']['outcome']['duration_sec']===null && isset($a['unavailable']['outcome.duration_sec']), 'missing duration explicit, never fabricated zero');
    check($a['call']['outcome']['is_repeat_caller']===0 && !isset($a['unavailable']['outcome.is_repeat_caller']), 'persisted zero distinct from unavailable');
    $hash = hash_file('sha256', $db); $r = runEvidence('read');
    check($r['evidence']===$a && hash_file('sha256',$db)===$hash && $r['option_writes']===0, 'fresh read preserves database bytes and evidence without option writes');
    check(count($r['queries'])===1 && str_starts_with($r['queries'][0], 'SELECT id,') && !preg_match('/recording_|caller_number|notes|JOIN|leases|sessions/i', $r['queries'][0]), 'single allow-listed call SELECT, no media/raw caller or live attribution lookup');
    $r = runEvidence('callback-completed'); $accepted = $r['evidence'];
    check($r['http_status']===200 && $accepted['call']['outcome']['technical_qualification']==='qualified' && $accepted['call']['outcome']['duration_sec']===90, 'actual status controller persists terminal qualification in SQLite');
    check($accepted['call']['acquisition']===$a['call']['acquisition'] && $accepted['call']['number_assignment']===$a['call']['number_assignment'], 'callback leaves original acquisition and number assignment intact');
    foreach (['callback-completed','callback-ringing','callback-busy'] as $mode) {
        $r = runEvidence($mode);
        check($r['evidence']===$accepted && $r['call_count']===1, $mode.' preserves accepted evidence and unique identity');
    }
    $r = runEvidence('change-current-number');
    check($r['evidence']===$accepted, 'current source/pool/deactivation cannot reconstruct or relabel original evidence');
    $r = runEvidence('read');
    check($r['evidence']===$accepted && count(array_unique($pids))===count($pids), 'evidence survives fresh independent PHP processes');
    foreach (['lease_id','pool_group','leased_at','expires_at'] as $key) check($accepted['call']['number_assignment'][$key]===null && isset($accepted['unavailable']['number_assignment.'.$key]), 'unsnapshotted '.$key.' unavailable');
    foreach (['business_qualification','advertising_eligibility','inquiry_identity','authoritative_consent_linkage','durable_child_leg_mapping'] as $key) check($accepted['call'][$key]===null && isset($accepted['unavailable'][$key]), $key.' explicitly unavailable');
    check($accepted['call']['consent_receipt_id']===null && $accepted['call']['acquisition']['gclid']===null, 'no fabricated consent or advertising click identity');
    check(!str_contains(json_encode($accepted),'DO_NOT_EXPORT') && !str_contains(json_encode($accepted),'fixture-signing-only') && !str_contains(json_encode($accepted),'recording_url'), 'notes, fixture token and media interface absent');
    $r = runEvidence('invalid'); check($r['evidence']['status']==='invalid_identity' && $r['queries']===[], 'form identity rejected before SQL');
    check(runEvidence('missing')['evidence']['status']==='not_found', 'missing call distinguished from storage failure');
    $r = runEvidence('error'); check($r['evidence']['status']==='storage_error' && $r['evidence']['call']===null && !str_contains(json_encode($r['evidence']),'injected'), 'injected read failure fails closed without diagnostics disclosure');
    check(runEvidence('conflict')['evidence']['status']==='identity_conflict', 'persisted identity mismatch rejected, never repaired or aliased');
    echo json_encode(['demonstration'=>'SYNTHETIC LOCAL SQLITE ONLY','contract'=>$accepted['contract'],'identity'=>$accepted['call']['identity'],'acquisition'=>$accepted['call']['acquisition'],'number_assignment'=>$accepted['call']['number_assignment'],'outcome'=>$accepted['call']['outcome'],'business_qualification'=>$accepted['call']['business_qualification'],'advertising_eligibility'=>$accepted['call']['advertising_eligibility']], JSON_THROW_ON_ERROR|JSON_PRETTY_PRINT)."\n";
    echo "$count PASS / 0 FAIL; SQLite persistence + WP adapter; no MariaDB/installed HTTP/AB integration\n";
} finally {
    foreach ($receipts as $p) if (is_file($p)) unlink($p);
    foreach ([$db,$db.'-journal',$db.'-wal',$db.'-shm'] as $p) if (is_file($p)) unlink($p);
    rmdir($root);
}
