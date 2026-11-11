<?php
/**
 * Pöntunarform feature: the order form block for the Pantanir page (instead of Forminator), the orders in their own
 * table with numbers in sequence, the emails to the owner and the printer, and wp-admin → Pantanir.
 */

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/includes/Pontun.php';
require __DIR__ . '/includes/Store.php';
require __DIR__ . '/includes/Rest.php';
require __DIR__ . '/includes/Block.php';
require __DIR__ . '/includes/Admin.php';
require __DIR__ . '/includes/Portal.php';

use Vasaskrimsli\Pontun;

add_action( 'init', array( Pontun\Block::class, 'register' ) );
add_action( 'rest_api_init', array( Pontun\Rest::class, 'register' ) );
add_action( 'admin_init', array( Pontun\Store::class, 'install' ) );
Pontun\Admin::register();
Pontun\Portal::register();
register_activation_hook( VASASKRIMSLI_FILE, array( Pontun\Store::class, 'install' ) );
register_activation_hook( VASASKRIMSLI_FILE, array( Pontun\Portal::class, 'install' ) );
