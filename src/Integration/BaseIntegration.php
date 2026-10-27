<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\Integration;
use SiteEngine\CallBridge\Admin\Menu;
use SiteEngine\CallBridge\Admin\SettingsPage;
use SiteEngine\CallBridge\Support\Readiness;
defined('ABSPATH') || exit;
final class BaseIntegration
{
    public static function available(): bool
    {
        return class_exists('SiteEngine_Base_Module_Registry') && class_exists('SiteEngine_Base_Admin') && class_exists('SiteEngine_Base_Readme_Page');
    }
    public static function register(): void
    {
        add_filter('siteengine_modules', [self::class, 'modules']);
        add_filter('siteengine_readme_tab_dirs', [self::class, 'helpDirectories']);
    }
    public static function modules($modules): array
    {
        $modules = is_array($modules) ? $modules : [];
        $modules['call-bridge'] = [
            'title'=>'Call Bridge', 'menu_title'=>'Call Bridge', 'page_slug'=>Menu::SLUG_SETTINGS,
            'capability'=>Menu::CAP, 'order'=>40, 'render'=>[SettingsPage::class,'render'],
            'status_callback'=>[self::class,'status'], 'requirements'=>[], 'notice'=>false,
            'description'=>'Twilio call tracking, setup guidance and configuration status.',
        ];
        return $modules;
    }
    public static function status(): array
    {
        if (!current_user_can(Menu::CAP)) {
            return ['status'=>'warning','label'=>'Administrator access required','message'=>'Open with an administrator account.'];
        }
        // Original Base routes setup_required to Business Data. Its supported
        // warning/Open path preserves the CB destination without a Base patch.
        return ['status'=>'warning','label'=>Readiness::current()['label'],
            'message'=>'Open Call Bridge to review setup. Live calling, attribution and playback acceptance remain pending.'];
    }
    public static function helpDirectories($directories): array
    {
        $directories = is_array($directories) ? $directories : [];
        $own = SECB_PATH . 'readme-tabs/';
        if (!in_array($own, $directories, true)) $directories[] = $own;
        return $directories;
    }
    public static function helpUrl(): string
    {
        return self::available() ? admin_url('admin.php?page=read-me-file&tab=call-bridge')
            : admin_url('admin.php?page='.Menu::SLUG_SETTINGS.'&view=help');
    }
}
