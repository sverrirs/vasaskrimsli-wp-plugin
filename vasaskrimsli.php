<?php
/**
 * Plugin Name:       Vasaskrímsli
 * Plugin URI:        https://github.com/sverrirs/vasaskrimsli-wp-plugin
 * Description:       Gagnvirkir hlutar vasaskrimsli.is. Inniheldur Orðsifjafræði: leitanlega töflu yfir uppruna nafna, með ritli og CSV útflutningi.
 * Version:           0.2.0
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Sverrir Sigmundarson
 * License:           MIT
 * Text Domain:       vasaskrimsli
 * Update URI:        https://github.com/sverrirs/vasaskrimsli-wp-plugin
 */

defined( 'ABSPATH' ) || exit;

define( 'VASASKRIMSLI_VERSION', '0.2.0' );
define( 'VASASKRIMSLI_FILE', __FILE__ );
define( 'VASASKRIMSLI_DIR', plugin_dir_path( __FILE__ ) );
define( 'VASASKRIMSLI_URL', plugin_dir_url( __FILE__ ) );

require VASASKRIMSLI_DIR . 'includes/Assets.php';
require VASASKRIMSLI_DIR . 'includes/Updater.php';
require VASASKRIMSLI_DIR . 'features/ordsifjar/ordsifjar.php';

Vasaskrimsli\Updater::register();
