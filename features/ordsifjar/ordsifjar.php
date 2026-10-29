<?php
/**
 * Orðsifjafræði feature: data store, admin grid, CSV import/export and the public block.
 */

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/includes/Csv.php';
require __DIR__ . '/includes/Format.php';
require __DIR__ . '/includes/Sanitize.php';
require __DIR__ . '/includes/Store.php';
require __DIR__ . '/includes/Import.php';
require __DIR__ . '/includes/Nofn.php';
require __DIR__ . '/includes/Upgrade.php';
require __DIR__ . '/includes/Rest.php';
require __DIR__ . '/includes/Admin.php';
require __DIR__ . '/includes/Block.php';

use Vasaskrimsli\Ordsifjar;

add_action( 'init', array( Ordsifjar\Store::class, 'register' ) );
add_action( 'init', array( Ordsifjar\Block::class, 'register' ) );
add_action( 'rest_api_init', array( Ordsifjar\Rest::class, 'register' ) );
Ordsifjar\Admin::register();
Ordsifjar\Upgrade::register();

// First activation on an empty site loads the 390 entries from the original page,
// then the data updates (gen 4-9 entries, em-dash separators, 2026 names) run straight away.
// Re-activating an up-to-date site runs no data update.
register_activation_hook( VASASKRIMSLI_FILE, static function () {
	Ordsifjar\Store::register();
	Ordsifjar\Import::seed();
	$report = Ordsifjar\Upgrade::run( (int) get_option( Ordsifjar\Upgrade::OPTION, 1 ) );
	if ( empty( $report['missing'] ) ) {
		update_option( Ordsifjar\Upgrade::OPTION, Ordsifjar\Upgrade::VERSION, false );
	}
} );
