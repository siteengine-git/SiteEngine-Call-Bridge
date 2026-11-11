<?php
declare(strict_types=1);
define('ABSPATH', __DIR__.'/fixture/');
define('ARRAY_A','ARRAY_A');
function current_user_can($cap) { return true; }
function esc_html($s) { return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8'); }
function esc_url($s) { return esc_html($s); }
function rest_url($s) { return 'https://fixture.invalid/'.$s; }
final class TimeDb {
    public string $prefix='fixture_';
    public array $rows=[];
    public function prepare($sql,...$args) { return $sql; }
    public function get_results(...$args) { return $this->rows; }
    public function get_var(...$args) { return count($this->rows); }
}
$wpdb=new TimeDb();
$row=['created_at'=>'2026-11-11 08:43:53','caller_number'=>'+10000000082','tracking_number'=>'+10000000081','call_status'=>'completed','call_duration_sec'=>70,'qualification_status'=>'qualified','source'=>'synthetic','is_repeat_caller'=>0];
$wpdb->rows=[$row,array_replace($row,['created_at'=>'2026-07-01 08:43:53'])];
require dirname(__DIR__).'/src/DB/CallLogRepository.php';
require dirname(__DIR__).'/src/Admin/Menu.php';
require dirname(__DIR__).'/src/Admin/CallLogPage.php';
ob_start();SiteEngine\CallBridge\Admin\CallLogPage::render();$html=ob_get_clean();
$checks=[
    'neutral stored-time label'=>str_contains($html,'<th>Time (stored)</th>')&&!str_contains($html,'Time (UTC)'),
    'explicit no-conversion explanation'=>str_contains($html,'Timestamps are shown as stored; no timezone conversion is applied.'),
    'winter and summer values remain raw'=>str_contains($html,'2026-11-11 08:43:53')&&str_contains($html,'2026-07-01 08:43:53'),
    'repository results untouched'=>$wpdb->rows===[$row,array_replace($row,['created_at'=>'2026-07-01 08:43:53'])],
    'duration and qualification unchanged'=>substr_count($html,'70s')===2&&substr_count($html,'qualified')===2,
];
foreach($checks as $name=>$ok){echo ($ok?'PASS ':'FAIL ').$name."\n";if(!$ok)exit(1);}
echo count($checks)." PASS / 0 FAIL; isolated actual renderer; no installed UI claim\n";
