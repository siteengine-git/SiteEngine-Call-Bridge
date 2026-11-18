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
    $seed=runEvidence('seed-capture');check($seed['call_count']===0,'empty prospective fixture; no backfilled call');
    $r=runEvidence('voice-new');$original=$r['evidence'];$call=$original['call'];
    check($r['http_status']===200&&$r['call_count']===1&&$original['status']==='found','actual Voice insert captured one prospective call');
    check($call['capture']['version']===1&&preg_match('/Z$/',$call['capture']['captured_at_utc'])===1,'explicit first-insert UTC capture');
    check($call['number_assignment']['lease_id']===1&&$call['number_assignment']['pool_group']==='original-allocation-pool','original lease and allocation-time pool captured');
    check($call['number_assignment']['leased_at']!==null&&$call['number_assignment']['expires_at']!==null&&$call['capture']['lease_timestamp_timezone']==='UTC','observed reservation bounds have explicit UTC semantics');
    check($call['acquisition']['utm_source']==='synthetic-acquisition'&&$call['acquisition']['matched_session_id']==='synthetic-original-session','original acquisition/session copied');
    check($call['consent_receipt_id']===null&&$call['authoritative_consent_linkage']===null,'missing Consent stays unavailable');
    $r=runEvidence('change-capture-context');check($r['evidence']===$original,'changed current lease/session/number cannot rewrite original snapshot');
    $r=runEvidence('voice-new');check($r['evidence']===$original&&$r['call_count']===1,'duplicate Voice cannot recapture changed context');
    $r=runEvidence('callback-completed');$terminal=$r['evidence'];
    check($terminal['call']['outcome']['qualification_threshold_sec']===120&&$terminal['call']['outcome']['technical_qualification']==='unqualified_short','actual terminal threshold atomically captured with its outcome');
    check($terminal['call']['capture']===$call['capture']&&$terminal['call']['number_assignment']===$call['number_assignment']&&$terminal['call']['acquisition']===$call['acquisition'],'terminal write leaves original evidence immutable');
    check(runEvidence('change-terminal-threshold')['evidence']===$terminal,'later threshold change cannot rewrite applied threshold');
    foreach(['callback-completed','callback-ringing','callback-busy','read'] as $mode)check(runEvidence($mode)['evidence']===$terminal,$mode.' preserves terminal capture across fresh process');
    check(runEvidence('conflicting-capture')['evidence']['status']==='capture_invalid','cross-call capture identity rejected');
    check(runEvidence('corrupt-capture')['evidence']['status']==='capture_invalid','malformed capture fails closed');
    $prospective=$db;$db=$root.'/legacy.sqlite';runEvidence('seed');$legacy=runEvidence('legacy-terminal')['evidence'];
    check($legacy['call']['capture']['captured_at_utc']===null&&$legacy['call']['outcome']['qualification_threshold_sec']===null,'historical terminal fields remain unavailable');
    check(runEvidence('callback-completed')['evidence']===$legacy,'legacy duplicate does not backfill threshold or capture');
    unlink($db);$db=$root.'/legacy-lease.sqlite';runEvidence('seed-capture');runEvidence('clear-original-pool');$partial=runEvidence('voice-new')['evidence'];
    check($partial['call']['number_assignment']['pool_group']===null&&$partial['unavailable']['number_assignment.pool_group']==='not_snapshotted_on_lease','old lease pool never inferred from current number');
    check($partial['call']['timestamp_timezone']===null&&$partial['call']['capture']['captured_at_utc']!==null,'UTC observation never relabels naive historical timestamp');
    unlink($db);$db=$prospective;
    echo "$count PASS / 0 FAIL; real local SQLite persistence, WordPress adapter; no MariaDB/installed HTTP claim\n";
} finally {
    foreach($receipts as $p)if(is_file($p))unlink($p);
    foreach(glob($root.'/*')?:[] as $p)if(is_file($p))unlink($p);
    rmdir($root);
}
