<?php
/**
 * Plugin Name:         WordClever - AI Content Writer
 * Plugin URI:          https://www.wpradiant.net/products/wordclever-pro
 * Description:         WordClever AI Content Writer generates SEO-friendly product descriptions, meta titles, and more for WooCommerce with just a few clicks.
 * Version:             1.1.0
 * Requires at least:   5.2
 * Requires PHP:        7.4
 * Author:              wpradiant
 * Author URI:          https://www.wpradiant.net
 * Text Domain:         wordclever-ai-content-writer
 * License:             GPL-2.0-or-later
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.html
 */

defined('ABSPATH') || exit;

// Autoload classes
spl_autoload_register(function ($class_name) {
    if (strpos($class_name, 'WordClever_') !== false) {
        $file_name = strtolower(str_replace('_', '-', $class_name)) . '.php';
        $file_path = plugin_dir_path(__FILE__) . 'includes/' . $file_name;
        if (file_exists($file_path)) {
            require_once $file_path;
        }
    }
});

define('WORDCLEVER_ENDPOINT', 'https://license.wpradiant.net/api/public/');
define('WORDCLEVER_MAIN_URL', 'https://www.wpradiant.net');
define('WORDCLEVER_PREVIEW_URL', 'https://preview.wpradiant.net');

// Initialize the plugin
class WordClever
{
    public function __construct()
    {
        $this->define_constants();
        $this->load_dependencies();
        $this->init_hooks();
    }

    private function define_constants()
    {
        define('WORDCLEVER_VERSION', '1.1.0');
        define('WORDCLEVER_PATH', plugin_dir_path(__FILE__));
        define('WORDCLEVER_URL', plugin_dir_url(__FILE__));
    }

    private function load_dependencies()
    {
        require_once WORDCLEVER_PATH . 'includes/class-wordclever-loader.php';
        require_once WORDCLEVER_PATH . 'global-functions.php';
    }

    private function init_hooks()
    {
        add_action('plugins_loaded', [$this, 'initialize_plugin']);
    }

    public function initialize_plugin()
    {
        WordClever_Loader::init();
    }
}

// Initialize the plugin
new WordClever();

add_action('admin_notices', 'wordclever_upsell_banner_func');
function wordclever_upsell_banner_func()
{ ?>
    <div class="notice is-dismissible wordclever-upsell-banner">
        <div id="wordclever-banner-main">
            <div class="wordclever-banner-main-wrap">
                <div class="wordclever-banner-img">
                    <img src="<?php echo esc_url(WORDCLEVER_URL . 'assets/images/bundle-banner.png'); ?>" alt="">
                </div>
                <div class="wordclever-banner-content">
                    <h2><?php echo esc_html('WordPress Theme Bundle'); ?></h2>
                    <p><?php echo esc_html('Get Access to 72+ Gutenberg WordPress Themes for almost all business Niche'); ?>
                    </p>
                </div>
                <div class="wordclever-banner-btn-content">
                    <div class="wordclever-disocunt-wrap">
                        <h6><?php echo esc_html('Get Instant Discount'); ?></h6>
                        <h4><?php echo esc_html('15%'); ?></h4>
                    </div>
                    <a href="<?php echo esc_attr(WORDCLEVER_MAIN_URL . '/products/wordpress-theme-bundle'); ?>"
                        target="_blank" class="wordclever-bundlle-btn"><?php echo esc_html('Buy Bundle at $79'); ?></a>
                </div>
            </div>
        </div>
    </div>
<?php }


// Admin Notice
add_action('admin_notices', 'wordclever_woocommerce_professional_notice');

function wordclever_woocommerce_professional_notice()
{
    if (!current_user_can('activate_plugins')) {
        return;
    }

    include_once(ABSPATH . 'wp-admin/includes/plugin.php');

    $woocommerce_active = class_exists('WooCommerce');

    if (!$woocommerce_active) {

        $button = '<button class="wordclever-install-btn wordclever-btn">
            Install & Activate WooCommerce
        </button>';
        ?>

        <div class="wordclever-notice notice notice-error is-dismissible">
            <div class="wordclever-notice-icon">
                <span class="dashicons dashicons-warning"></span>
            </div>
            <div class="wordclever-notice-content">
                <h2><?php echo esc_html__('WordClever AI Content Writer Requires WooCommerce', 'wordclever-ai-content-writer'); ?>
                </h2>
                <p><?php echo esc_html__('To use all features of WordClever, WooCommerce must be installed and active.', 'wordclever-ai-content-writer'); ?>
                </p>
                <?php echo $button; ?>
            </div>
        </div>

        <?php
    }
}

//Skip SetupWizard Wocommerce 
add_filter('woocommerce_prevent_automatic_wizard_redirect', '__return_true');

// install handler 
add_action('wp_ajax_wordclever_install_plugin', 'wordclever_install_plugin');

function wordclever_install_plugin()
{

    check_ajax_referer('wordclever_nonce', 'nonce');

    if (!current_user_can('install_plugins')) {
        wp_send_json_error('Permission denied');
    }

    include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
    include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
    include_once ABSPATH . 'wp-admin/includes/plugin.php';

    $plugin_slug = 'woocommerce';

    // Install plugin
    $api = plugins_api('plugin_information', array(
        'slug' => $plugin_slug,
        'fields' => array('sections' => false)
    ));

    if (is_wp_error($api)) {
        wp_send_json_error($api->get_error_message());
    }

    $upgrader = new Plugin_Upgrader(new WP_Ajax_Upgrader_Skin());

    $install = $upgrader->install($api->download_link);

    if (is_wp_error($install)) {
        wp_send_json_error($install->get_error_message());
    }

    // Activate plugin
    $activate = activate_plugin('woocommerce/woocommerce.php');

    if (is_wp_error($activate)) {
        wp_send_json_error($activate->get_error_message());
    }

    wp_send_json_success('Installed & Activated');
}