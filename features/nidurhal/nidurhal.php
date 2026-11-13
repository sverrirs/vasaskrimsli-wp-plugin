<?php
/**
 * Niðurhal feature: counts downloads of the ZIP files (námsefni) linked from a page with the block "Námsefni:
 * Niðurhalsteljari", by person-made clicks, and shows them in wp-admin → Niðurhal and on the Dashboard.
 */

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/includes/Nidurhal.php';
require __DIR__ . '/includes/Store.php';
require __DIR__ . '/includes/Rest.php';
require __DIR__ . '/includes/Block.php';
require __DIR__ . '/includes/Admin.php';

use Vasaskrimsli\Nidurhal;

add_action( 'init', array( Nidurhal\Block::class, 'register' ) );
add_action( 'rest_api_init', array( Nidurhal\Rest::class, 'register' ) );
add_action( 'admin_init', array( Nidurhal\Store::class, 'install' ) );
Nidurhal\Admin::register();
