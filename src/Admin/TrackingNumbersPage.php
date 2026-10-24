<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\Admin;
use SiteEngine\CallBridge\DB\TrackingNumberRepository;
use SiteEngine\CallBridge\Support\Phone;
defined('ABSPATH') || exit;
final class TrackingNumbersPage
{
    private const NS_SAVE='secb_save_number'; private const NS_DELETE='secb_delete_number';
    public static function render(): void { if(!current_user_can(Menu::CAP))wp_die('Insufficient permissions.');$repo=new TrackingNumberRepository();$edit=isset($_GET['edit'])?(int)$_GET['edit']:0;$row=$edit>0?$repo->findById($edit):null;$rows=$repo->listAll();include SECB_PATH.'templates/admin/tracking-numbers.php'; }
    public static function handleSave(): void
    {
        if(!current_user_can(Menu::CAP))wp_die('Insufficient permissions.');check_admin_referer(self::NS_SAVE);$repo=new TrackingNumberRepository();$id=(int)($_POST['id']??0);
        $phone=Phone::normalize((string)($_POST['phone_number']??''));$forward=Phone::normalize((string)($_POST['forward_to']??''));if($phone===null||$forward===null){self::redirect('invalid_phone',$id);}
        $data=['twilio_phone_sid'=>sanitize_text_field((string)($_POST['twilio_phone_sid']??'')),'phone_number'=>$phone,'display_label'=>sanitize_text_field((string)($_POST['display_label']??'')),'source'=>sanitize_key((string)($_POST['source']??'google_ads')),'pool_group'=>sanitize_text_field((string)($_POST['pool_group']??''))?:null,'forward_to'=>$forward,'min_duration_sec'=>max(0,(int)($_POST['min_duration_sec']??60)),'record_calls'=>empty($_POST['record_calls'])?0:1,'recording_notice'=>sanitize_text_field((string)($_POST['recording_notice']??''))?:null,'whisper_new'=>sanitize_text_field((string)($_POST['whisper_new']??''))?:null,'whisper_repeat'=>sanitize_text_field((string)($_POST['whisper_repeat']??''))?:null,'repeat_window_days'=>max(0,(int)($_POST['repeat_window_days']??30)),'is_active'=>empty($_POST['is_active'])?0:1,'notes'=>sanitize_textarea_field((string)($_POST['notes']??''))?:null];
        if($id>0){$repo->update($id,$data);self::redirect('updated',$id);} $new=$repo->insert($data);self::redirect($new===false?'insert_failed':'created',0);
    }
    public static function handleDelete(): void { if(!current_user_can(Menu::CAP))wp_die('Insufficient permissions.');check_admin_referer(self::NS_DELETE);$id=(int)($_POST['id']??0);if($id>0)(new TrackingNumberRepository())->delete($id);self::redirect('deleted',0); }
    private static function redirect(string $notice,int $edit): never { $url=add_query_arg(['page'=>Menu::SLUG_NUMBERS,'notice'=>$notice],admin_url('admin.php'));if($edit>0&&$notice==='updated')$url=add_query_arg('edit',$edit,$url);wp_safe_redirect($url);exit; }
    public static function nonceSave(): string{return self::NS_SAVE;} public static function nonceDelete(): string{return self::NS_DELETE;}
}
