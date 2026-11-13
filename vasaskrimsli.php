<?php
/**
 * Plugin Name:       Vasaskrímsli
 * Plugin URI:        https://github.com/sverrirs/vasaskrimsli-wp-plugin
 * Description:       Gagnvirkir hlutar vasaskrimsli.is. Inniheldur Orðsifjafræði (leitanlega töflu yfir uppruna nafna, með ritli og CSV útflutningi), Skrímslaskrá og bókaskrá Verslunarinnar.
 * Version:           1.6.6
 * Requires at least: 6.5
 * Requires PHP:      8.1
 * Author:            Sverrir Sigmundarson
 * License:           MIT
 * Text Domain:       vasaskrimsli
 * Update URI:        https://github.com/sverrirs/vasaskrimsli-wp-plugin
 */

defined( 'ABSPATH' ) || exit;

define( 'VASASKRIMSLI_VERSION', '1.6.6' );
define( 'VASASKRIMSLI_FILE', __FILE__ );
define( 'VASASKRIMSLI_DIR', plugin_dir_path( __FILE__ ) );
define( 'VASASKRIMSLI_URL', plugin_dir_url( __FILE__ ) );

require VASASKRIMSLI_DIR . 'includes/Assets.php';
require VASASKRIMSLI_DIR . 'includes/Updater.php';
require VASASKRIMSLI_DIR . 'features/ordsifjar/ordsifjar.php';
require VASASKRIMSLI_DIR . 'features/baekur/baekur.php';
require VASASKRIMSLI_DIR . 'features/nidurhal/nidurhal.php';
// The order form (Pantanir) is not ready for the site yet: off unless wp-config.php says
// define( 'VASASKRIMSLI_PONTUN', true ); A later release turns it on for everyone.
if ( defined( 'VASASKRIMSLI_PONTUN' ) && VASASKRIMSLI_PONTUN ) {
	require VASASKRIMSLI_DIR . 'features/pontun/pontun.php';
}

Vasaskrimsli\Updater::register();
