<?php
/**
 * Tests for the pure-PHP parts of the plugin (no WordPress needed).
 *
 * Run:  "E:\SDKs\php\8.3.7\php.exe" tests/run.php
 * WordPress-level behaviour (sanitising, storage, REST, block) is tested in tests/playground/.
 */

require __DIR__ . '/../features/ordsifjar/includes/Csv.php';
require __DIR__ . '/../features/ordsifjar/includes/Format.php';
require __DIR__ . '/../features/ordsifjar/includes/Nofn.php';

use Vasaskrimsli\Ordsifjar\Csv;
use Vasaskrimsli\Ordsifjar\Format;
use Vasaskrimsli\Ordsifjar\Nofn;

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

// --- 2026 names (Nofn): fill placeholders, change gen 3 only while unchanged, never overwrite the owner
$d       = "\u{2013}";
$stored  = array(
	10 => entry( array( 'id' => 10, 'numer' => '400', 'kynslod' => 4, 'nafn' => $d, 'uppruni' => $d, 'thyding' => $d ) ),
	11 => entry( array( 'id' => 11, 'numer' => '401', 'kynslod' => 4, 'nafn' => '<strong>Mitt nafn</strong>', 'uppruni' => $d, 'thyding' => 'Minn texti' ) ),
	12 => entry( array( 'id' => 12, 'numer' => '298', 'kynslod' => 3, 'nafn' => 'Vatnakríli', 'uppruni' => "<strong>Vatna</strong>{$em}kríli", 'thyding' => 'Gamalt.' ) ),
	13 => entry( array( 'id' => 13, 'numer' => '299', 'kynslod' => 3, 'nafn' => 'Eigið nafn', 'uppruni' => 'Eigið', 'thyding' => 'Eigið.' ) ),
	14 => entry( array( 'id' => 14, 'numer' => '351a', 'kynslod' => 3, 'nafn' => 'Bjartviðri' ) ),
);
$decided = array(
	array( 'numer' => 400, 'kynslod' => 4, 'stada' => 'nýtt', 'nafn' => 'Nýnefni', 'uppruni' => "Ný{$em}nefni", 'thyding' => '<strong>Ný:</strong> x.', 'drog' => true ),
	array( 'numer' => 401, 'kynslod' => 4, 'stada' => 'nýtt', 'nafn' => 'Annað', 'uppruni' => "An{$em}nað", 'thyding' => 'Ekki þetta.', 'drog' => false ),
	array( 'numer' => 298, 'kynslod' => 3, 'stada' => 'breytt', 'nafn' => 'Agnarögn', 'uppruni' => "<strong>Agnar</strong>{$em}ögn", 'thyding' => 'Nýtt.', 'fyrra_nafn' => 'Vatnakríli', 'drog' => false ),
	array( 'numer' => 299, 'kynslod' => 3, 'stada' => 'breytt', 'nafn' => 'Áttavitringur', 'uppruni' => 'x', 'thyding' => 'y', 'fyrra_nafn' => 'Áttviti', 'drog' => false ),
	array( 'numer' => 351, 'kynslod' => 3, 'stada' => 'breytt', 'nafn' => 'Veðrabrigði', 'uppruni' => 'x', 'thyding' => 'y', 'fyrra_nafn' => 'Bjartviðri', 'drog' => false ),
	array( 'numer' => 402, 'kynslod' => 4, 'stada' => 'nýtt', 'nafn' => 'Enginn', 'uppruni' => 'x', 'thyding' => 'y', 'drog' => false ),
);
$plan    = Nofn::plan( $stored, $decided );
$by_id   = array_column( $plan['changes'], null, 'id' );
check( 'nofn: placeholders filled, all three fields', $by_id[10]['fields'] ?? null, array( 'nafn' => 'Nýnefni', 'uppruni' => "Ný{$em}nefni", 'thyding' => '<strong>Ný:</strong> x.' ) );
check( 'nofn: drafted row is flagged', $by_id[10]['drog'] ?? null, true );
check( 'nofn: owner text kept, only the placeholder field filled', $by_id[11]['fields'] ?? null, array( 'uppruni' => "An{$em}nað" ) );
check( 'nofn: gen 3 name unchanged since publication is replaced', $by_id[12]['fields'] ?? null, array( 'nafn' => 'Agnarögn', 'uppruni' => "<strong>Agnar</strong>{$em}ögn", 'thyding' => 'Nýtt.' ) );
check( 'nofn: gen 3 name the owner changed is skipped and reported', array( isset( $by_id[13] ), $plan['skipped'] ), array( false, array( '299 Eigið nafn' ) ) );
check( 'nofn: Númer matched exactly (351 is not 351a), missing numbers ignored', array_keys( $by_id ), array( 10, 11, 12 ) );
check( 'nofn: only placeholder dashes count as empty', array( Nofn::is_placeholder( " {$d} " ), Nofn::is_placeholder( '-' ), Nofn::is_placeholder( '' ), Nofn::is_placeholder( "A{$d}B" ) ), array( true, true, false, false ) );

$after = $stored;
foreach ( $plan['changes'] as $c ) {
	$after[ $c['id'] ] = array_merge( $after[ $c['id'] ], $c['fields'] );
}
$again = Nofn::plan( $after, $decided );
check( 'nofn: second run changes nothing (idempotent)', $again['changes'], array() );
check( 'nofn: second run still reports only the owner-changed gen 3 name', $again['skipped'], array( '299 Eigið nafn' ) );

$typed             = $stored;
$typed[12]['nafn'] = 'Agnarögn';
check( 'nofn: gen 3 entry where the owner already typed the new name is left alone', array_column( Nofn::plan( $typed, $decided )['changes'], 'id' ), array( 10, 11 ) );

// --- generations 3-9 "í vinnslu": notes only where empty, gen 3 heading once, gen 4 kept as it is
$kyn = json_decode( file_get_contents( __DIR__ . '/../features/ordsifjar/data/kynslodir.json' ), true );
foreach ( array( 5 => 'Fimmta', 6 => 'Sjötta', 7 => 'Sjöunda', 8 => 'Áttunda', 9 => 'Níunda' ) as $n => $o ) {
	$kyn[] = array( 'kynslod' => $n, 'akkeri' => '', 'heiti_html' => "<em>{$o} kynslóð</em> (óstaðfest)", 'skyring_html' => '' );
}
$kyn[7]['skyring_html'] = 'Eigin skýring.'; // gen 8: the owner wrote a note
$m   = Nofn::in_progress( $kyn );
$byk = array_column( $m['kynslodir'], null, 'kynslod' );
check( 'vinnsla: notes set for 3, 5, 6, 7, 9 (gen 8 has its own note)', $m['notes'], 5 );
check( 'vinnsla: gen 3 note and heading', array( $byk[3]['skyring_html'], $byk[3]['heiti_html'] ), array( Nofn::NOTE, '<em>Þriðja kynslóð</em> (í vinnslu)' ) );
check( 'vinnsla: gen 4 heading and note kept', array( $byk[4]['heiti_html'], $byk[4]['skyring_html'] ), array( $kyn[3]['heiti_html'], $kyn[3]['skyring_html'] ) );
check( 'vinnsla: owner note kept, óstaðfest heading not changed', array( $byk[8]['skyring_html'], $byk[8]['heiti_html'] ), array( 'Eigin skýring.', '<em>Áttunda kynslóð</em> (óstaðfest)' ) );
check( 'vinnsla: gens 1 and 2 untouched', array( $byk[1], $byk[2] ), array( $kyn[0], $kyn[1] ) );
$m2 = Nofn::in_progress( $m['kynslodir'] );
check( 'vinnsla: second run changes nothing', array( $m2['notes'], $m2['headings'], $m2['kynslodir'] ), array( 0, 0, $m['kynslodir'] ) );

// --- the bundled decisions file against the real data: what a new install gets
$rows     = json_decode( file_get_contents( __DIR__ . '/../features/ordsifjar/data/nofn-2026.json' ), true );
$national = array_column( json_decode( file_get_contents( __DIR__ . '/../features/ordsifjar/data/national.json' ), true ), null, 'numer' );
$bad      = array();
foreach ( $rows as $r ) {
	if ( ! in_array( $r['stada'], array( 'nýtt', 'breytt' ), true ) || ( $national[ $r['numer'] ]['kynslod'] ?? 0 ) !== $r['kynslod']
		|| ( 'breytt' === $r['stada'] ) !== ( 3 === $r['kynslod'] ) || ( 'breytt' === $r['stada'] && '' === (string) ( $r['fyrra_nafn'] ?? '' ) )
		|| '' === Csv::plain( $r['nafn'] ) || preg_match( '#</?(?!(?:strong|em|br|sup|sub|a)[\s>/])\w#i', $r['nafn'] . $r['uppruni'] . $r['thyding'] ) ) {
		$bad[] = $r['numer'];
	}
}
check( 'nofn-2026.json: valid rows (stada, generation, fyrra_nafn, allowed tags only)', $bad, array() );

$install = array();
foreach ( $seed_rows as $i => $r ) {
	$install[ $i + 1 ] = $r + array( 'id' => $i + 1 );
}
$present = array_flip( array_column( $seed_rows, 'numer' ) );
foreach ( $national as $n => $p ) {
	if ( $p['kynslod'] >= 4 && ! isset( $present[ (string) $n ] ) ) {
		$id             = count( $install ) + 1;
		$install[ $id ] = array( 'id' => $id, 'numer' => (string) $n, 'kynslod' => $p['kynslod'], 'nafn' => $d, 'uppruni' => $d, 'thyding' => $d );
	}
}
$plan = Nofn::plan( $install, $rows );
$want = array( 'nýtt' => 0, 'breytt' => 0 );
foreach ( $rows as $r ) {
	++$want[ $r['stada'] ];
}
$got = array_count_values( array_column( $plan['changes'], 'stada' ) ) + array( 'nýtt' => 0, 'breytt' => 0 );
check( 'new install: every decided name applies, none skipped', array( $got['nýtt'], $got['breytt'], $plan['skipped'] ), array( $want['nýtt'], $want['breytt'], array() ) );

echo ( $failures ? "{$failures} of {$count} checks FAILED\n" : "OK: {$count} checks passed\n" );
exit( $failures ? 1 : 0 );
