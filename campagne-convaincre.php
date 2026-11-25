<?php
/**
 * Plugin Name: Campagne Convaincre
 * Description: Créez et gérez un site de campagne politique professionnel, avec des formulaires et un agenda connectés à Convaincre.
 * Version: 1.0.5
 * Requires at least: 6.4
 * Requires PHP: 8.1
 * Author: Convaincre
 * Author URI: https://convainc.re/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: campagne-convaincre
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

if (defined('CONVAINCRE_CONNECT_VERSION')) {
    return;
}

define('CONVAINCRE_CONNECT_VERSION', '1.0.5');
define('CONVAINCRE_CONNECT_FILE', __FILE__);
define('CONVAINCRE_CONNECT_DIR', plugin_dir_path(__FILE__));
define('CONVAINCRE_CONNECT_URL', plugin_dir_url(__FILE__));

require_once CONVAINCRE_CONNECT_DIR . 'includes/class-crypto.php';
require_once CONVAINCRE_CONNECT_DIR . 'includes/class-signer.php';
require_once CONVAINCRE_CONNECT_DIR . 'includes/class-form-token.php';
require_once CONVAINCRE_CONNECT_DIR . 'includes/class-payload.php';
require_once CONVAINCRE_CONNECT_DIR . 'includes/class-settings.php';
require_once CONVAINCRE_CONNECT_DIR . 'includes/class-appearance.php';
require_once CONVAINCRE_CONNECT_DIR . 'includes/class-api-client.php';
require_once CONVAINCRE_CONNECT_DIR . 'includes/class-manifest.php';
require_once CONVAINCRE_CONNECT_DIR . 'includes/class-queue.php';
require_once CONVAINCRE_CONNECT_DIR . 'includes/class-shortcodes.php';
require_once CONVAINCRE_CONNECT_DIR . 'includes/class-form.php';
require_once CONVAINCRE_CONNECT_DIR . 'includes/class-agenda.php';
require_once CONVAINCRE_CONNECT_DIR . 'includes/class-blocks.php';
require_once CONVAINCRE_CONNECT_DIR . 'includes/class-admin.php';
require_once CONVAINCRE_CONNECT_DIR . 'includes/class-sync-receiver.php';
require_once CONVAINCRE_CONNECT_DIR . 'includes/class-site-legal.php';
require_once CONVAINCRE_CONNECT_DIR . 'includes/class-site-appearance.php';
require_once CONVAINCRE_CONNECT_DIR . 'includes/class-site-mode.php';
require_once CONVAINCRE_CONNECT_DIR . 'includes/class-site-dashboard.php';
require_once CONVAINCRE_CONNECT_DIR . 'includes/class-site-seo.php';
require_once CONVAINCRE_CONNECT_DIR . 'includes/class-site-news.php';
require_once CONVAINCRE_CONNECT_DIR . 'includes/class-plugin.php';

register_activation_hook(__FILE__, ['Convaincre\\Connect\\Plugin', 'activate']);
register_activation_hook(__FILE__, ['Convaincre\\Connect\\SiteMode', 'activation']);
register_deactivation_hook(__FILE__, ['Convaincre\\Connect\\Plugin', 'deactivate']);

add_action('plugins_loaded', static function (): void {
    Convaincre\Connect\Plugin::boot();
});
