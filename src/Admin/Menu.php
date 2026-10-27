<?php
declare(strict_types=1);
namespace SiteEngine\CallBridge\Admin;
use SiteEngine\CallBridge\Integration\BaseIntegration;
defined('ABSPATH') || exit;
final class Menu
{
    public const CAP='manage_options';
    public const SLUG_NUMBERS='secb-numbers';
    public const SLUG_CALLS='secb-calls';
    public const SLUG_SETTINGS='secb-settings';
    public function register(): void
    {
        add_action('admin_menu',[$this,'addMenu'],120);
        add_action('admin_post_secb_save_number',[TrackingNumbersPage::class,'handleSave']);
        add_action('admin_post_secb_delete_number',[TrackingNumbersPage::class,'handleDelete']);
    }
    public function addMenu(): void
    {
        if(BaseIntegration::available()){
            add_submenu_page('siteengine-options','Call Bridge Tracking Numbers','Call Bridge — Numbers',self::CAP,self::SLUG_NUMBERS,[TrackingNumbersPage::class,'render']);
            add_submenu_page('siteengine-options','Call Bridge Call Log','Call Bridge — Call Log',self::CAP,self::SLUG_CALLS,[CallLogPage::class,'render']);
            // Base alone registers secb-settings through the module filter.
            return;
        }
        add_menu_page('SiteEngine Call Bridge','Call Bridge',self::CAP,self::SLUG_NUMBERS,[TrackingNumbersPage::class,'render'],'dashicons-phone',58);
        add_submenu_page(self::SLUG_NUMBERS,'Tracking Numbers','Tracking Numbers',self::CAP,self::SLUG_NUMBERS,[TrackingNumbersPage::class,'render']);
        add_submenu_page(self::SLUG_NUMBERS,'Call Log','Call Log',self::CAP,self::SLUG_CALLS,[CallLogPage::class,'render']);
        add_submenu_page(self::SLUG_NUMBERS,'Setup & Status','Setup & Status',self::CAP,self::SLUG_SETTINGS,[SettingsPage::class,'render']);
    }
}
