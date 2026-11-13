<?php
/**
 * Niðurhal feature: short download addresses for the ZIP files (námsefni), https://vasaskrimsli.is/s/1, that count each
 * download and send the browser on to the file; the block "Námsefni: Niðurhalsteljari" also counts people's clicks on
 * them. Both in wp-admin → Niðurhal and on the Dashboard.
 */

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/includes/Nidurhal.php';
require __DIR__ . '/includes/Store.php';
require __DIR__ . '/includes/Saekja.php';
require __DIR__ . '/includes/Rest.php';
require __DIR__ . '/includes/Block.php';
require __DIR__ . '/includes/Admin.php';
require __DIR__ . '/includes/Fjoldi.php';

use Vasaskrimsli\Nidurhal;

add_action( 'init', array( Nidurhal\Block::class, 'register' ) );
add_action( 'init', array( Nidurhal\Fjoldi::class, 'register' ) );
add_action( 'rest_api_init', array( Nidurhal\Rest::class, 'register' ) );
add_action( 'admin_init', array( Nidurhal\Store::class, 'install' ) );
Nidurhal\Saekja::register();
Nidurhal\Admin::register();
