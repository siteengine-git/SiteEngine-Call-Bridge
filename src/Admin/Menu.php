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
        add_action('admin_init', [self::class, 'redirectLegacy'], 0);
        add_filter('parent_file', [self::class, 'parentFile']);
        add_filter('submenu_file', [self::class, 'submenuFile']);
    }
    public function addMenu(): void
    {
        $base = BaseIntegration::available();
        $parent = $base ? 'siteengine-options' : self::SLUG_SETTINGS;
        if (!$base) {
            add_menu_page('SiteEngine Call Bridge','Call Bridge',self::CAP,self::SLUG_SETTINGS,[self::class,'render'],'dashicons-phone',58);
        }
        // Register the original routes/capabilities, then hide only their menu rows.
        foreach ([self::SLUG_NUMBERS=>'Tracking Numbers', self::SLUG_CALLS=>'Call Log'] as $slug=>$title) {
            add_submenu_page($parent, 'Call Bridge — '.$title, $title, self::CAP, $slug, [self::class,'render']);
            remove_submenu_page($parent, $slug);
        }
        if (!$base) remove_submenu_page($parent, self::SLUG_SETTINGS);
    }
    public static function selectedTab(): string
    {
        $key = static fn(string $name): string => isset($_GET[$name]) && is_string($_GET[$name])
            ? sanitize_key((string)wp_unslash($_GET[$name])) : '';
        $page = $key('page');
        if ($page === self::SLUG_NUMBERS) return 'numbers';
        if ($page === self::SLUG_CALLS) return 'calls';
        if ($key('view') === 'help') return 'help';
        $tab = $key('tab');
        return in_array($tab, ['setup','numbers','calls','help'], true) ? $tab : 'setup';
    }
    public static function url(string $tab = 'setup', array $args = []): string
    {
        if (!in_array($tab, ['setup','numbers','calls','help'], true)) $tab = 'setup';
        return add_query_arg(array_merge($args, ['page'=>self::SLUG_SETTINGS, 'tab'=>$tab]), admin_url('admin.php'));
    }
    public static function redirectLegacy(): void
    {
        $page = $_GET['page'] ?? null;
        if (!is_string($page) || !in_array($page, [self::SLUG_NUMBERS,self::SLUG_CALLS], true)) return;
        if (!current_user_can(self::CAP)) wp_die('Insufficient permissions.', '', ['response'=>403]);
        $args = [];
        foreach (['edit','paged'] as $name) {
            if (isset($_GET[$name]) && is_scalar($_GET[$name])) $args[$name] = max(0, (int)$_GET[$name]);
        }
        if (isset($_GET['notice']) && is_string($_GET['notice'])) $args['notice'] = sanitize_key((string)wp_unslash($_GET['notice']));
        wp_safe_redirect(self::url($page === self::SLUG_NUMBERS ? 'numbers' : 'calls', $args));
        exit;
    }
    private static function isOwnPage(): bool
    {
        return isset($_GET['page']) && is_string($_GET['page'])
            && in_array($_GET['page'], [self::SLUG_SETTINGS,self::SLUG_NUMBERS,self::SLUG_CALLS], true);
    }
    public static function parentFile($parent)
    {
        return self::isOwnPage() ? (BaseIntegration::available() ? 'siteengine-options' : self::SLUG_SETTINGS) : $parent;
    }
    public static function submenuFile($submenu)
    {
        return self::isOwnPage() ? self::SLUG_SETTINGS : $submenu;
    }
    public static function render(): void
    {
        if (!current_user_can(self::CAP)) wp_die('Insufficient permissions.', '', ['response'=>403]);
        $selected = self::selectedTab();
        echo '<div class="wrap secb-admin"><h1>Call Bridge</h1><nav class="nav-tab-wrapper" aria-label="Call Bridge sections">';
        foreach (['setup'=>'Setup & Status','numbers'=>'Tracking Numbers','calls'=>'Call Log','help'=>'Help & Reference'] as $tab=>$label) {
            echo '<a class="nav-tab'.($selected === $tab ? ' nav-tab-active' : '').'" href="'.esc_url(self::url($tab)).'"'
                .($selected === $tab ? ' aria-current="page"' : '').'>'.esc_html($label).'</a>';
        }
        echo '</nav>';
        switch ($selected) {
            case 'numbers': TrackingNumbersPage::render(true); break;
            case 'calls': CallLogPage::render(true); break;
            case 'help': include SECB_PATH.'readme-tabs/call-bridge.php'; break;
            default: SettingsPage::render(true);
        }
        echo '</div>';
    }
}
