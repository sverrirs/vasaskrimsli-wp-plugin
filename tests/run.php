<?php
/**
 * Tests for the pure-PHP parts of the plugin (no WordPress needed).
 *
 * Run:  "E:\SDKs\php\8.3.7\php.exe" tests/run.php
 * WordPress-level behaviour (sanitising, storage, REST, block) is tested in tests/playground/.
 */

require __DIR__ . '/../features/ordsifjar/includes/Csv.php';
require __DIR__ . '/../features/ordsifjar/includes/Format.php';

use Vasaskrimsli\Ordsifjar\Csv;
use Vasaskrimsli\Ordsifjar\Format;

$failures = 0;
$count    = 0;
function check( string $name, $actual, $expected ): void {
	global $failures, $count;
	++$count;
	if ( $actual !== $expected ) {
		++$failures;
		echo "FAIL {$name}\n  expected: " . var_export( $expected, true ) . "\n  actual:   " . var_export( $actual, true ) . "\n";
	}
}
$noop = static function ( $s ) {
	return $s;
};

function entry( array $over = array() ): array {
	return array_merge(
		array(
			'id' => 7, 'numer' => '1', 'kynslod' => 1, 'nafn' => 'Laukeðla',
			'uppruni' => '<strong><a href="https://ordsifjabok.arnastofnun.is/faersla/10705">Lauk</a></strong>–eðla',
			'thyding' => '<strong>Laukur:</strong> Norrænt orð.<br><em>Eðla:</em> Skriðdýr &amp; „hali“.',
			'enska' => '<a href="https://pokemondb.net/pokedex/bulbasaur">Bulbasaur</a>',
			'flokkar' => array( 'Jurt', 'Dýr' ), 'athugasemdir' => '',
		),
		$over
	);
}

// --- plain text conversion
check( 'plain strips tags, br becomes newline, entities decoded',
	Csv::plain( '<strong>Laukur:</strong> orð.<br><em>Eðla:</em> &amp; „hali“' ), "Laukur: orð.\nEðla: & „hali“" );
check( 'plain_to_html escapes and turns newlines into br', Csv::plain_to_html( "a < b & c\nnæsta lína" ), 'a &lt; b &amp; c<br>næsta lína' );

// --- export
$html_csv  = Csv::export( array( entry() ), Csv::MODE_HTML );
$plain_csv = Csv::export( array( entry() ), Csv::MODE_PLAIN );
check( 'export starts with UTF-8 BOM', substr( $html_csv, 0, 3 ), "\xEF\xBB\xBF" );
check( 'formatted header', strtok( substr( $html_csv, 3 ), "\r" ), 'id,numer,kynslod,nafn_html,uppruni_html,thyding_html,enska_html,flokkar,athugasemdir_html' );
check( 'plain header', strtok( substr( $plain_csv, 3 ), "\r" ), 'id,numer,kynslod,nafn,uppruni,thyding,enska,flokkar,athugasemdir' );
check( 'plain export has no tags', strpos( $plain_csv, '<' ), false );

// --- round trips
$p = Csv::parse( $html_csv );
check( 'formatted parse detects mode', $p['mode'], Csv::MODE_HTML );
check( 'formatted round trip keeps html exactly', $p['rows'][0]['thyding'], entry()['thyding'] );
check( 'formatted round trip: re-import is a no-op', Csv::diff( array( 7 => entry() ), $p, $noop )['unchanged'], 1 );

$p = Csv::parse( $plain_csv );
check( 'plain parse detects mode', $p['mode'], Csv::MODE_PLAIN );
$plan = Csv::diff( array( 7 => entry() ), $p, $noop );
check( 'plain re-import of untouched export changes nothing (formatting kept)', array( $plan['unchanged'], $plan['update'] ), array( 1, array() ) );

// --- plain import: only edited cells change, and lost formatting is reported
$edited = str_replace( 'Norrænt orð.', 'Norrænt orð, breytt.', $plain_csv );
$plan   = Csv::diff( array( 7 => entry() ), Csv::parse( $edited ), $noop );
check( 'plain edit changes only that field', array_keys( $plan['update'][0]['changes'] ), array( 'thyding' ) );
check( 'plain edit becomes escaped html with br', $plan['update'][0]['changes']['thyding']['to'], 'Laukur: Norrænt orð, breytt.<br>Eðla: Skriðdýr &amp; „hali“.' );
check( 'plain edit reports formatting loss', $plan['update'][0]['formatting_lost'], array( 'thyding' ) );

// --- semicolon CSV from Icelandic Excel, no BOM
$semi = "id;numer;kynslod;nafn;flokkar\r\n7;1;1;Laukeðla;Dýr; Jurt\r\n";
$semi = "id;numer;kynslod;nafn;flokkar\r\n7;1;1;Laukeðla;\"Dýr; Jurt\"\r\n";
$plan = Csv::diff( array( 7 => entry() ), Csv::parse( $semi ), $noop );
check( 'semicolon file, missing columns untouched, flokkar compared as a set', $plan['unchanged'], 1 );

// --- new rows, errors, entries not in file
$csv  = "id,numer,kynslod,nafn\r\n,500,5,Nýtt skrímsli\r\n,501,x,Villa\r\n,502,5,\r\n9999,503,5,Óþekkt id\r\n";
$plan = Csv::diff( array( 7 => entry() ), Csv::parse( $csv ), $noop );
check( 'new row without id is created', $plan['create'][0]['fields']['nafn'], 'Nýtt skrímsli' );
check( 'unknown id becomes a new row and is flagged', $plan['create'][1]['missing_id'], 9999 );
check( 'bad kynslod and missing name are errors', count( $plan['errors'] ), 2 );
check( 'existing entry not in file is counted, not deleted', $plan['not_in_file'], 1 );

$plan = Csv::diff( array(), Csv::parse( "id,nafn\r\n1,a\r\n1,b\r\n" ), $noop );
check( 'duplicate id is an error', count( $plan['errors'] ), 2 ); // duplicate + missing kynslóð on first row
check( 'unknown column is an error', count( Csv::parse( "nafn,litur\r\na,b\r\n" )['errors'] ), 1 );
check( 'file without a name column is rejected', Csv::parse( "x,y\r\n1,2\r\n" )['mode'], '' );
check( 'non UTF-8 file is rejected', Csv::parse( "nafn\r\n\xE6\xF0\r\n" )['errors'] !== array(), true );

// --- multi-line cells survive (Þýðing has line breaks in the plain export)
$plan = Csv::diff( array( 7 => entry() ), Csv::parse( $plain_csv ), $noop );
check( 'multi-line quoted cell parsed as one row', count( Csv::parse( $plain_csv )['rows'] ), 1 );

// --- the real seed file
$seed = Csv::parse( file_get_contents( __DIR__ . '/../features/ordsifjar/data/seed.csv' ) );
check( 'seed: formatted, 390 rows, no errors', array( $seed['mode'], count( $seed['rows'] ), $seed['errors'] ), array( Csv::MODE_HTML, 390, array() ) );
$plan = Csv::diff( array(), $seed, $noop );
check( 'seed: all 390 rows would be created without errors', array( count( $plan['create'] ), $plan['errors'] ), array( 390, array() ) );

// --- Upprunaorð separators become em dashes; tags, links and placeholders are left alone
$em = "\u{2014}";
check( 'hyphens become em dashes', Format::separators( '<strong>Kam</strong>-eld-ljón' ), "<strong>Kam</strong>{$em}eld{$em}ljón" );
check( 'en dash between tags becomes em dash, href hyphens untouched',
	Format::separators( '<strong><a href="https://x.is/a-b-c" rel="noreferrer noopener">Lauk</a></strong>–<a href="https://x.is/2965">eðla</a>' ),
	"<strong><a href=\"https://x.is/a-b-c\" rel=\"noreferrer noopener\">Lauk</a></strong>{$em}<a href=\"https://x.is/2965\">eðla</a>" );
check( 'entity dashes and spaces around them', Format::separators( 'Lauk &ndash; eðla &#8211;x' ), "Lauk{$em}eðla{$em}x" );
check( 'a lone placeholder dash stays as it is', Format::separators( '–' ), '–' );
check( 'already normalised text is unchanged', Format::separators( "Lauk{$em}eðla" ), "Lauk{$em}eðla" );
check( 'only Upprunaorð is normalised', Format::field( 'thyding', 'Kam-eld' ), 'Kam-eld' );

$seed_rows = Csv::parse( file_get_contents( __DIR__ . '/../features/ordsifjar/data/seed.csv' ) )['rows'];
$left      = 0;
foreach ( $seed_rows as $r ) {
	$text = Csv::plain( Format::separators( $r['uppruni'] ) );
	if ( '–' !== $text && preg_match( '/[\x{002D}\x{2013}]/u', $text ) ) {
		++$left;
	}
}
check( 'seed: no hyphen or en dash separators left after normalising', $left, 0 );

$field   = static function ( $html, $f ) {
	return Format::field( $f, $html );
};
$stored  = array( 9 => entry( array( 'id' => 9, 'uppruni' => "<strong>Kam</strong>{$em}eld{$em}ljón" ) ) );
$plan    = Csv::diff( $stored, Csv::parse( "id,nafn,uppruni\r\n9,Laukeðla,Kam-eld-ljón\r\n" ), $field );
check( 'plain import with hyphens counts as unchanged (formatting kept)', array( $plan['unchanged'], $plan['update'] ), array( 1, array() ) );
$plan    = Csv::diff( $stored, Csv::parse( "id,nafn_html,uppruni_html\r\n9,Laukeðla,<strong>Kam</strong>-eld-ljón\r\n" ), $field );
check( 'formatted import with hyphens counts as unchanged', $plan['unchanged'], 1 );

echo ( $failures ? "{$failures} of {$count} checks FAILED\n" : "OK: {$count} checks passed\n" );
exit( $failures ? 1 : 0 );
