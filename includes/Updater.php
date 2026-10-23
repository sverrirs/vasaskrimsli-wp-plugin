<?php
/**
 * Updates from GitHub Releases: WordPress shows "Update available" when a new release
 * of sverrirs/vasaskrimsli-wp-plugin has a vasaskrimsli.zip asset.
 *
 * Uses YahnisElsts/plugin-update-checker, which the release workflow puts in vendor/.
 */

namespace Vasaskrimsli;

final class Updater {

	const REPO = 'https://github.com/sverrirs/vasaskrimsli-wp-plugin/';

	public static function register(): void {
		$autoload = VASASKRIMSLI_DIR . 'vendor/plugin-update-checker/plugin-update-checker.php';
		if ( ! file_exists( $autoload ) ) {
			return; // Development checkout: updates come from git, not GitHub Releases.
		}
		require $autoload;
		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker( self::REPO, VASASKRIMSLI_FILE, 'vasaskrimsli' );
		$checker->getVcsApi()->enableReleaseAssets( '/vasaskrimsli\.zip/' );
	}
}
