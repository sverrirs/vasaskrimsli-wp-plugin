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
require __DIR__ . '/../features/baekur/includes/Verslun.php';
require __DIR__ . '/../features/baekur/includes/Block.php';
require __DIR__ . '/../features/pontun/includes/Pontun.php';

use Vasaskrimsli\Ordsifjar\Csv;
use Vasaskrimsli\Ordsifjar\Format;
use Vasaskrimsli\Ordsifjar\Nofn;
use Vasaskrimsli\Pontun\Pontun;

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
			'flokkar' => array( 'Jurt', 'Dýr' ), 'athugasemdir' => '', 'lysing' => '',
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
check( 'formatted header', strtok( substr( $html_csv, 3 ), "\r" ), 'id,numer,kynslod,nafn_html,uppruni_html,thyding_html,lysing_html,enska_html,flokkar,athugasemdir_html' );
check( 'plain header', strtok( substr( $plain_csv, 3 ), "\r" ), 'id,numer,kynslod,nafn,uppruni,thyding,lysing,enska,flokkar,athugasemdir' );
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
$fam_names = array( 906 => 'Laufmjási', 907 => 'Blómkisi', 908 => 'Blómblöff', 280 => 'Árari', 281 => 'Spádans', 282 => 'Hjartaborg', 475 => 'Bjargvættur', 133 => 'Evi', 134 => 'A', 135 => 'B', 136 => 'C' );
check( 'family line added for the first member, the monster itself without a number',
	Format::with_family( '<strong>Lauf:</strong> blað.', 906, array( array( 906 ), array( 907 ), array( 908 ) ), $fam_names ),
	'<strong>Lauf:</strong> blað.<br><strong>Fjölskylda:</strong> Laufmjási → Blómkisi (#907) → Blómblöff (#908).' );
check( 'family line: branches read "eða", three or more with commas',
	array(
		Format::with_family( 'x', 281, array( array( 280 ), array( 281 ), array( 282, 475 ) ), $fam_names ),
		Format::with_family( 'x', 134, array( array( 133 ), array( 134, 135, 136 ) ), $fam_names ),
	),
	array(
		'x<br><strong>Fjölskylda:</strong> Árari (#280) → Spádans → Hjartaborg (#282) eða Bjargvættur (#475).',
		'x<br><strong>Fjölskylda:</strong> Evi (#133) → A, B (#135) eða C (#136).',
	) );
check( 'family line: a written one is replaced where it stands, kept for a lone monster; an empty text gets only the line',
	array(
		Format::with_family( 'x<br><strong>Fjölskylda:</strong> Laufmjási → Blómkisi (#907).<br>y', 907, array( array( 906 ), array( 907 ) ), $fam_names ),
		Format::with_family( 'x<br><strong>Fjölskylda:</strong> Systurveran.', 906, array( array( 906 ) ), $fam_names ),
		Format::with_family( '', 907, array( array( 906 ), array( 907 ) ), $fam_names ),
	),
	array( 'x<br><strong>Fjölskylda:</strong> Laufmjási (#906) → Blómkisi.<br>y', 'x<br><strong>Fjölskylda:</strong> Systurveran.', '<strong>Fjölskylda:</strong> Laufmjási (#906) → Blómkisi.' ) );
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

// --- 2026 names (Nofn): fill placeholders, change gen 2-3 names only while unchanged, never overwrite the owner
$d       = "\u{2013}";
$stored  = array(
	10 => entry( array( 'id' => 10, 'numer' => '400', 'kynslod' => 4, 'nafn' => $d, 'uppruni' => $d, 'thyding' => $d ) ),
	11 => entry( array( 'id' => 11, 'numer' => '401', 'kynslod' => 4, 'nafn' => '<strong>Mitt nafn</strong>', 'uppruni' => $d, 'thyding' => 'Minn texti' ) ),
	12 => entry( array( 'id' => 12, 'numer' => '298', 'kynslod' => 3, 'nafn' => 'Vatnakríli', 'uppruni' => "<strong>Vatna</strong>{$em}kríli", 'thyding' => 'Gamalt.' ) ),
	13 => entry( array( 'id' => 13, 'numer' => '299', 'kynslod' => 3, 'nafn' => 'Eigið nafn', 'uppruni' => 'Eigið', 'thyding' => 'Eigið.' ) ),
	14 => entry( array( 'id' => 14, 'numer' => '351a', 'kynslod' => 3, 'nafn' => 'Bjartviðri' ) ),
	15 => entry( array( 'id' => 15, 'numer' => '233', 'kynslod' => 2, 'nafn' => 'Grafíkja<sup>2</sup>', 'uppruni' => "<strong>Grafík</strong>{$em}ja{$em}tvö", 'thyding' => 'Birt.' ) ),
);
$decided = array(
	array( 'numer' => 400, 'kynslod' => 4, 'stada' => 'nýtt', 'nafn' => 'Nýnefni', 'uppruni' => "Ný{$em}nefni", 'thyding' => '<strong>Ný:</strong> x.', 'drog' => true ),
	array( 'numer' => 401, 'kynslod' => 4, 'stada' => 'nýtt', 'nafn' => 'Annað', 'uppruni' => "An{$em}nað", 'thyding' => 'Ekki þetta.', 'drog' => false ),
	array( 'numer' => 298, 'kynslod' => 3, 'stada' => 'breytt', 'nafn' => 'Agnarögn', 'uppruni' => "<strong>Agnar</strong>{$em}ögn", 'thyding' => 'Nýtt.', 'fyrra_nafn' => 'Vatnakríli', 'drog' => false ),
	array( 'numer' => 299, 'kynslod' => 3, 'stada' => 'breytt', 'nafn' => 'Áttavitringur', 'uppruni' => 'x', 'thyding' => 'y', 'fyrra_nafn' => 'Áttviti', 'drog' => false ),
	array( 'numer' => 351, 'kynslod' => 3, 'stada' => 'breytt', 'nafn' => 'Veðrabrigði', 'uppruni' => 'x', 'thyding' => 'y', 'fyrra_nafn' => 'Bjartviðri', 'drog' => false ),
	array( 'numer' => 402, 'kynslod' => 4, 'stada' => 'nýtt', 'nafn' => 'Enginn', 'uppruni' => 'x', 'thyding' => 'y', 'drog' => false ),
	array( 'numer' => 233, 'kynslod' => 2, 'stada' => 'breytt', 'nafn' => 'Grafíkja²', 'uppruni' => "<strong>Grafík</strong>{$em}ja{$em}tvö", 'thyding' => 'Birt.', 'fyrra_nafn' => 'Grafíkja2', 'drog' => false ),
);
$plan    = Nofn::plan( $stored, $decided );
$by_id   = array_column( $plan['changes'], null, 'id' );
check( 'nofn: placeholders filled, all three fields', $by_id[10]['fields'] ?? null, array( 'nafn' => 'Nýnefni', 'uppruni' => "Ný{$em}nefni", 'thyding' => '<strong>Ný:</strong> x.' ) );
check( 'nofn: drafted row is flagged', $by_id[10]['drog'] ?? null, true );
check( 'nofn: owner text kept, only the placeholder field filled', $by_id[11]['fields'] ?? null, array( 'uppruni' => "An{$em}nað" ) );
check( 'nofn: gen 3 name unchanged since publication is replaced', $by_id[12]['fields'] ?? null, array( 'nafn' => 'Agnarögn', 'uppruni' => "<strong>Agnar</strong>{$em}ögn", 'thyding' => 'Nýtt.' ) );
check( 'nofn: gen 3 name the owner changed is skipped and reported', array( isset( $by_id[13] ), $plan['skipped'] ), array( false, array( '299 Eigið nafn' ) ) );
check( 'nofn: Númer matched exactly (351 is not 351a), missing numbers ignored', array_keys( $by_id ), array( 10, 11, 12, 15 ) );
check( 'nofn: gen 2 #233 Grafíkja2 becomes Grafíkja² while unchanged (only the name differs)', $by_id[15]['fields'] ?? null, array( 'nafn' => 'Grafíkja²' ) );
$owner_233             = $stored;
$owner_233[15]['nafn'] = 'Grafíkja tvö';
$p233                  = Nofn::plan( $owner_233, $decided );
check( 'nofn: gen 2 #233 the owner renamed is skipped and reported', array( in_array( 15, array_column( $p233['changes'], 'id' ), true ), $p233['skipped'] ), array( false, array( '299 Eigið nafn', '233 Grafíkja tvö' ) ) );
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
check( 'nofn: gen 3 entry where the owner already typed the new name is left alone', array_column( Nofn::plan( $typed, $decided )['changes'], 'id' ), array( 10, 11, 15 ) );
$typed_233             = $stored;
$typed_233[15]['nafn'] = 'Grafíkja²';
check( 'nofn: gen 2 #233 where the owner already typed Grafíkja² is left alone', array_column( Nofn::plan( $typed_233, $decided )['changes'], 'id' ), array( 10, 11, 12 ) );

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
		|| ( 'breytt' === $r['stada'] ) !== in_array( $r['kynslod'], array( 2, 3 ), true ) || ( 'breytt' === $r['stada'] && '' === (string) ( $r['fyrra_nafn'] ?? '' ) )
		|| '' === Csv::plain( $r['nafn'] ) || preg_match( '#</?(?!(?:strong|em|br|sup|sub|a)[\s>/])\w#i', $r['nafn'] . $r['uppruni'] . $r['thyding'] ) ) {
		$bad[] = $r['numer'];
	}
}
check( 'nofn-2026.json: valid rows (stada, generation, fyrra_nafn, allowed tags only)', $bad, array() );
$r233 = array_values( array_filter( $rows, static function ( $r ) { return 233 === $r['numer']; } ) );
check( 'nofn-2026.json: #233 is the gen 2 change Grafíkja2 -> Grafíkja²', array( $r233[0]['kynslod'] ?? null, $r233[0]['stada'] ?? null, $r233[0]['nafn'] ?? null, $r233[0]['fyrra_nafn'] ?? null ), array( 2, 'breytt', 'Grafíkja²', 'Grafíkja2' ) );

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

// --- data version 4: rewritten Þýðing texts replace only what version 3 wrote
$v3    = array(
	1 => entry( array( 'id' => 1, 'numer' => '999', 'nafn' => 'Gemmérgull', 'thyding' => '<strong>Gemmér:</strong> gamalt.' ) ),
	2 => entry( array( 'id' => 2, 'numer' => '986', 'nafn' => 'Berserkur', 'thyding' => 'Eigandinn skrifaði þetta sjálfur.' ) ),
	3 => entry( array( 'id' => 3, 'numer' => '987', 'nafn' => 'Annað nafn', 'thyding' => 'gamalt 987' ) ),
	4 => entry( array( 'id' => 4, 'numer' => '988', 'nafn' => 'Nýtt', 'thyding' => '<em>nýtt</em> 988' ) ),
);
$trows = array(
	array( 'numer' => 999, 'nafn' => 'Gemmérgull', 'fyrri' => '<strong>Gemmér:</strong>  gamalt.', 'thyding' => '<strong>Gemmér:</strong> nýtt.' ),
	array( 'numer' => 986, 'nafn' => 'Berserkur', 'fyrri' => 'gamalt 986', 'thyding' => 'nýtt 986' ),
	array( 'numer' => 987, 'nafn' => 'Rétt nafn', 'fyrri' => 'gamalt 987', 'thyding' => 'nýtt 987' ),
	array( 'numer' => 988, 'nafn' => 'Nýtt', 'fyrri' => 'gamalt 988', 'thyding' => 'nýtt 988' ),
);
$tp    = Nofn::plan_texts( $v3, $trows );
check( 'texts: replaced only where the site still shows the version 3 text (whitespace and tags ignored)', array_column( $tp['changes'], 'id' ), array( 1 ) );
check( 'texts: the new text is what is saved, nothing else', $tp['changes'][0]['fields'] ?? null, array( 'thyding' => '<strong>Gemmér:</strong> nýtt.' ) );
check( 'texts: owner-edited text and renamed entries are reported, already-new text is silent', $tp['skipped'], array( '986 Berserkur', '987 Annað nafn' ) );
$v3[1]['thyding'] = $tp['changes'][0]['fields']['thyding'];
check( 'texts: running again changes nothing', Nofn::plan_texts( $v3, $trows ), array( 'changes' => array(), 'skipped' => array( '986 Berserkur', '987 Annað nafn' ) ) );

$tfile = __DIR__ . '/../features/ordsifjar/data/thyding-2026.json';
if ( file_exists( $tfile ) ) {
	foreach ( $plan['changes'] as $c ) {
		$install[ $c['id'] ] = $c['fields'] + $install[ $c['id'] ];
	}
	$texts = json_decode( file_get_contents( $tfile ), true );
	$tp    = Nofn::plan_texts( $install, $texts );
	check( 'thyding-2026.json on a new install after version 3: every text applies, none skipped', array( count( $tp['changes'] ), $tp['skipped'] ), array( count( $texts ), array() ) );
	$bad = array();
	foreach ( $texts as $r ) {
		if ( preg_match( '#</?(?!(?:strong|em|br|sup|sub|a)[\s>/])\w#i', $r['thyding'] ) || '' === Csv::plain( $r['thyding'] ) ) {
			$bad[] = $r['numer'];
		}
	}
	check( 'thyding-2026.json: allowed tags only, no empty text', $bad, array() );
}

// --- special kinds: Tímaflækingar are the Paradox Pokémon
$tima = array( 'Tímaflækingur · Fortíð' => array(), 'Tímaflækingur · Framtíð' => array() );
for ( $n = 1; $n <= 1025; $n++ ) {
	$k = Format::serstakt( (string) $n );
	if ( '' !== $k ) {
		$tima[ $k ][] = $n;
	}
}
check( 'Tímaflækingar: the 22 Paradox Pokémon, 11 from the past and 11 from the future', $tima, array(
	'Tímaflækingur · Fortíð'  => array( 984, 985, 986, 987, 988, 989, 1005, 1007, 1009, 1020, 1021 ),
	'Tímaflækingur · Framtíð' => array( 990, 991, 992, 993, 994, 995, 1006, 1008, 1010, 1022, 1023 ),
) );
check( 'serstakt: a form number counts as its base number',
	array( Format::serstakt( '1007a' ), Format::serstakt( '1008a' ) ),
	array( 'Tímaflækingur · Fortíð', 'Tímaflækingur · Framtíð' ) );
check( 'serstakt: ordinary entry has none', Format::serstakt( '1' ), '' );
check( 'serstakt: placeholder number has none', Format::serstakt( '–' ), '' );

// --- special and regional forms
$formir = array( '26' => array( array( 's' => 'raichu-alolan', 'h' => 'Alolan Raichu', 't' => array( 'Electric', 'Psychic' ) ), array( 's' => 'raichu-mega-x', 'h' => 'Mega Raichu X' ) ) );
$have   = array( 'raichu-alolan' => 0 );
check( 'formir: forms in order, slug only where there is a picture', Format::formir( $formir, $have, '26' ), array(
	array( 'id' => 'raichu-alolan', 'heiti' => 'Alolan Raichu', 'enska' => 'Alolan Raichu', 'slug' => 'raichu-alolan', 'tegundir' => array( 'Electric', 'Psychic' ) ),
	array( 'id' => 'raichu-mega-x', 'heiti' => 'Mega Raichu X', 'enska' => 'Mega Raichu X', 'slug' => '', 'tegundir' => array() ),
) );
check( 'formir: "26a" counts as 26', count( Format::formir( $formir, $have, '26a' ) ), 2 );
check( 'formir: none for others and placeholders', array( Format::formir( $formir, $have, '25' ), Format::formir( $formir, $have, '–' ) ), array( array(), array() ) );
$formir['26'][0]['i'] = '{n} Alóla';
$formir['26'][1]['i'] = 'Mega {n} X';
check( 'formir: Icelandic names from the Icelandic name of the monster', array_column( Format::formir( $formir, $have, '26', 'Þrumumús' ), 'heiti' ), array( 'Þrumumús Alóla', 'Mega Þrumumús X' ) );
check( 'formir: English names without a name to fill in', array_column( Format::formir( $formir, $have, '26' ), 'heiti' ), array( 'Alolan Raichu', 'Mega Raichu X' ) );
$real = json_decode( (string) file_get_contents( dirname( __DIR__ ) . '/features/ordsifjar/data/formir.json' ), true );
check( 'formir.json: Raichu has its Alolan form, Bulbasaur has none', array( $real['26'][0]['h'] ?? null, isset( $real['1'] ) ), array( 'Alolan Raichu', false ) );
check( 'formir.json: every form has an Icelandic name', array_filter( array_merge( ...array_values( $real ) ), static function ( $f ) {
	return empty( $f['i'] ) || false === strpos( $f['i'], '{n}' );
} ), array() );
check( 'formir.json: named as the books do', array_column( Format::formir( $real, array(), '6', 'Bálormur' ), 'heiti' ), array( 'Mega Bálormur X', 'Mega Bálormur Y', 'Gígamax Bálormur' ) );

// --- book catalogue: the web shop's products
use Vasaskrimsli\Baekur\Verslun;
$shopify = array( 'product' => array( 'id' => 7202611363943, 'title' => 'Vasaskrímsli - Bækur 1 - 10', 'variants' => array(
	array( 'title' => '1 pakki', 'price' => '12800.00' ),
	array( 'title' => '2 pakkar', 'price' => '22400.00' ),
) ) );
check( 'Verslun::parse: name, address and every option with its price', Verslun::parse( $shopify, 'https://www.haskolaprent.is', 'pakki' ), array(
	'id'       => '7202611363943',
	'heiti'    => 'Vasaskrímsli - Bækur 1 - 10',
	'slod'     => 'https://www.haskolaprent.is/products/pakki',
	'afbrigdi' => array( array( 'heiti' => '1 pakki', 'verd' => 12800 ), array( 'heiti' => '2 pakkar', 'verd' => 22400 ) ),
) );
check( 'Verslun::parse: a product with one option has no option name', Verslun::parse( array( 'product' => array( 'title' => 'X', 'variants' => array( array( 'title' => 'Default Title', 'price' => '6900.00' ) ) ) ), 'https://s.is', 'x' )['afbrigdi'], array( array( 'heiti' => '', 'verd' => 6900 ) ) );
check( 'Verslun::parse: not a product (an error page, nothing) is null', array( Verslun::parse( null, 's', 'x' ), Verslun::parse( array( 'errors' => 'Not Found' ), 's', 'x' ), Verslun::parse( array( 'product' => array( 'variants' => array() ) ), 's', 'x' ) ), array( null, null, null ) );
$rod = array( 'baekur' => array( array( 'id' => 'b1', 'kapa' => 'k1.webp' ), array( 'id' => 'b2', 'kapa' => 'k2.webp' ) ), 'pakkar' => array( array( 'id' => 'p1', 'baekur' => array( 'b2', 'b1' ) ) ) );
check( "Block::items: a series sold in lots lists its lots, each with its first book's cover", \Vasaskrimsli\Baekur\Block::items( $rod ), array( array( 'id' => 'p1', 'baekur' => array( 'b2', 'b1' ), 'kapa' => 'k2.webp' ) ) );
check( 'Block::items: without lots, the books', \Vasaskrimsli\Baekur\Block::items( array( 'baekur' => $rod['baekur'] ) ), $rod['baekur'] );
check( 'Block::settings: the defaults, and unknown values fall back to them', array( \Vasaskrimsli\Baekur\Block::settings( array() ), \Vasaskrimsli\Baekur\Block::settings( array( 'jofnun' => 'justify', 'stefna' => 'x', 'bil' => 999, 'staerd' => 'risa' ) ) ), array(
	array( 'jofnun' => 'left', 'stefna' => 'larett', 'bil' => 24, 'staerd' => 'litil' ),
	array( 'jofnun' => 'left', 'stefna' => 'larett', 'bil' => 120, 'staerd' => 'litil' ),
) );
check( 'Block::settings: what the editor sets is kept', \Vasaskrimsli\Baekur\Block::settings( array( 'jofnun' => 'center', 'stefna' => 'lodrett', 'bil' => 40, 'staerd' => 'stor' ) ), array( 'jofnun' => 'center', 'stefna' => 'lodrett', 'bil' => 40, 'staerd' => 'stor' ) );
check( 'Block::ORD: books, books sold in lots and posters agree with what they are', array_map( static fn( $k ) => \Vasaskrimsli\Baekur\Block::ORD[ $k ]['faanleg'], array( 'kvk', 'ft', 'hk' ) ), array( 'Fáanleg', 'Fáanlegar', 'Fáanlegt' ) );
check( 'Verslun::verd: one price, "frá" the lowest, nothing without prices', array( Verslun::verd( array( 'afbrigdi' => array( array( 'verd' => 6900 ) ) ) ), Verslun::verd( array( 'afbrigdi' => array( array( 'verd' => 3900 ), array( 'verd' => 2900 ) ) ) ), Verslun::verd( null ) ), array( '6.900 kr.', 'frá 2.900 kr.', '' ) );

// --- data version 5 and up: later decisions change a field only where the site still shows the released text
$v4    = array(
	1 => entry( array( 'id' => 1, 'numer' => '752', 'nafn' => 'Kafló', 'uppruni' => '<strong>Kaf</strong>—ló', 'thyding' => 'gamalt 752' ) ),
	2 => entry( array( 'id' => 2, 'numer' => '751', 'nafn' => 'Bóluló', 'thyding' => 'Eigandinn skrifaði þetta sjálfur.' ) ),
	3 => entry( array( 'id' => 3, 'numer' => '750', 'nafn' => 'Mitt nafn', 'thyding' => 'gamalt 750' ) ),
);
$crows = array(
	array( 'numer' => 752, 'fyrri' => array( 'nafn' => 'Kafló', 'uppruni' => '<strong>Kaf</strong>—ló', 'thyding' => 'gamalt  752' ), 'nytt' => array( 'nafn' => 'Kafekkja', 'uppruni' => '<strong>Kaf</strong>—ekkja', 'thyding' => 'nýtt 752' ) ),
	array( 'numer' => 751, 'fyrri' => array( 'thyding' => 'gamalt 751' ), 'nytt' => array( 'thyding' => 'nýtt 751' ) ),
	array( 'numer' => 750, 'fyrri' => array( 'nafn' => 'Gamla', 'thyding' => 'gamalt 750' ), 'nytt' => array( 'nafn' => 'Nýja', 'thyding' => 'nýtt 750' ) ),
);
$cp    = Nofn::plan_changes( $v4, $crows );
check( 'changes: every field still as released is replaced (whitespace ignored)', $cp['changes'][0] ?? null, array( 'id' => 1, 'numer' => '752', 'fields' => array( 'nafn' => 'Kafekkja', 'uppruni' => '<strong>Kaf</strong>—ekkja', 'thyding' => 'nýtt 752' ) ) );
check( 'changes: each field on its own; an owner-edited field is kept and reported', array( array_column( $cp['changes'], 'fields', 'numer' )['750'] ?? null, $cp['skipped'] ), array( array( 'thyding' => 'nýtt 750' ), array( '751 Bóluló (Þýðing)', '750 Mitt nafn (Nafn)' ) ) );
foreach ( $cp['changes'] as $c ) {
	$v4[ $c['id'] ] = $c['fields'] + $v4[ $c['id'] ];
}
check( 'changes: running again changes nothing', Nofn::plan_changes( $v4, $crows ), array( 'changes' => array(), 'skipped' => array( '751 Bóluló (Þýðing)', '750 Mitt nafn (Nafn)' ) ) );

// Lýsing (data version 7): filled only where the entry has none; text the owner wrote is kept.
$lrows = array(
	array( 'numer' => 752, 'fyrri' => array( 'lysing' => '' ), 'nytt' => array( 'lysing' => 'Hann kafar.' ) ),
	array( 'numer' => 751, 'fyrri' => array( 'lysing' => '' ), 'nytt' => array( 'lysing' => 'Hann flýtur.' ) ),
);
$v4[2]['lysing'] = 'Eigandinn skrifaði.';
$lp = Nofn::plan_changes( $v4, $lrows );
check( 'lysing: filled where empty, the text the owner wrote is kept', array( array_column( $lp['changes'], 'fields', 'numer' ), $lp['skipped'] ), array( array( '752' => array( 'lysing' => 'Hann kafar.' ) ), array( '751 Bóluló (Lýsing)' ) ) );

// Every released breytingar-N.json applies in full to a site that has versions 3 and 4 (and earlier N).
if ( file_exists( $tfile ) ) {
	foreach ( $tp['changes'] as $c ) {
		$install[ $c['id'] ] = $c['fields'] + $install[ $c['id'] ];
	}
	for ( $v = 5; file_exists( $cfile = __DIR__ . '/../features/ordsifjar/data/breytingar-' . $v . '.json' ); $v++ ) {
		$crows = json_decode( file_get_contents( $cfile ), true );
		$cp    = Nofn::plan_changes( $install, $crows );
		$want  = 0;
		foreach ( $crows as $r ) {
			$want += count( $r['nytt'] );
		}
		check( "breytingar-{$v}.json after the earlier versions: every field applies, none skipped", array( array_sum( array_map( 'count', array_column( $cp['changes'], 'fields' ) ) ), $cp['skipped'] ), array( $want, array() ) );
		foreach ( $cp['changes'] as $c ) {
			$install[ $c['id'] ] = $c['fields'] + $install[ $c['id'] ];
		}
	}
	check( 'breytingar-5.json exists and #752 is Kafekkja after all versions', Csv::plain( current( array_filter( $install, static fn( $e ) => '752' === (string) $e['numer'] ) )['nafn'] ?? '' ), 'Kafekkja' );
}

// A malformed active line must block the entire order, even with a good line present.
// If these checks fail, inspect the boundary between zero-quantity rows and lines that would be saved or emailed.
$pontun_config = json_decode( file_get_contents( __DIR__ . '/../features/pontun/data/pontun.json' ), true );
$pontun_input = array(
	'reitir' => array(
		'nafn' => '  Sigríður   Jónsdóttir ',
		'netfang' => 'pantandi@example.com',
		'simi' => '555 1234',
		'vidtakandi' => 'Bókasafnið',
		'heimilisfang' => 'Bókagata 3',
		'stadur' => 'Reykjavík',
		'postnumer' => '101',
		'greidandi' => 'Bókasafnið',
		'kennitala' => '1234567890',
		'netfang_greid' => 'reikningar@example.com',
		'aths' => "Fyrsta lína\r\nÖnnur lína",
	),
	'linur' => array(
		array( 'flokkur' => 'veggspjold', 'val' => array( 'tegund' => 'Kynslóð 1', 'staerd' => 'A3', 'fragangur' => 'Plasthúðað' ), 'fjoldi' => 3 ),
		array( 'flokkur' => 'storubaekur', 'val' => array(), 'fjoldi' => 0 ),
	),
	'dropp' => true,
);
$pontun_checked = Pontun::check( $pontun_input, $pontun_config );
check( 'Pöntun: a valid order has no errors', $pontun_checked['villur'], array() );
check( 'Pöntun: zero-quantity rows are omitted', count( $pontun_checked['pontun']['linur'] ), 1 );
check( 'Pöntun: the active row retains its category, choices and quantity', Pontun::lina( $pontun_checked['pontun']['linur'][0] ), '3 × Veggspjöld: Kynslóð 1, A3, Plasthúðað' );
check( 'Pöntun: kennitala is normalized', $pontun_checked['pontun']['reitir']['kennitala'], '123456-7890' );
check( 'Pöntun: spaces in single-line fields are normalized', $pontun_checked['pontun']['reitir']['nafn'], 'Sigríður Jónsdóttir' );
$test_order = $pontun_input;
$test_order['linur'] = array( array( 'flokkur' => 'lestrarbaekur', 'val' => array( 'pakki' => 'Lestrarbækur 1 til 10 (ath pakki af tíu bókum)' ), 'fjoldi' => 3 ) );
$pakkar = Pontun::check( $test_order, $pontun_config )['pontun']['linur'][0];
check( 'Pöntun: a pack line carries the books in each pack', $pakkar['pakki'] ?? null, 10 );
check( 'Pöntun: a pack line says packs and adds up the books', Pontun::lina( $pakkar ), '3 pakkar × Lestrarbækur: Lestrarbækur 1 til 10 (ath pakki af tíu bókum), 10 bækur í pakka, 30 bækur alls' );
$test_order['linur'][0]['val']['pakki'] = 'Lestrarbækur 6 til 10 (ath pakki af fimm bókum)';
$test_order['linur'][0]['fjoldi'] = 21;
check( 'Pöntun: a five-book pack counts five, and 21 takes the singular', Pontun::lina( Pontun::check( $test_order, $pontun_config )['pontun']['linur'][0] ), '21 pakki × Lestrarbækur: Lestrarbækur 6 til 10 (ath pakki af fimm bókum), 5 bækur í pakka, 105 bækur alls' );
check( 'Pöntun: 11 takes the plural', Pontun::fjoldi( 11, 'pakki', 'pakkar' ), '11 pakkar' );
check( 'Pöntun: a line saved before packs keeps its old text', Pontun::lina( array( 'fjoldi' => 2, 'heiti' => 'Lestrarbækur', 'val' => array( array( 'Tegund bókapakka', 'Lestrarbækur 1 til 10' ) ) ) ), '2 × Lestrarbækur: Lestrarbækur 1 til 10' );
$test_order = $pontun_input;
$test_order['reitir']['aths'] = str_repeat( 'á', 2001 );
check( 'Pöntun: long Icelandic notes are truncated without splitting UTF-8 characters', Pontun::check( $test_order, $pontun_config )['pontun']['reitir']['aths'], str_repeat( 'á', 2000 ) );

foreach ( Pontun::REITIR as $key => [ $label, $required ] ) {
	$test_order = $pontun_input;
	unset( $test_order['reitir'][ $key ] );
	check( 'Pöntun: missing field ' . $key, isset( Pontun::check( $test_order, $pontun_config )['villur'][ $key ] ), $required );
}
foreach ( array(
	'netfang' => 'not-an-email',
	'netfang_greid' => 'not-an-email',
	'simi' => '555',
	'postnumer' => '10A',
	'kennitala' => '1234',
) as $key => $value ) {
	$test_order = $pontun_input;
	$test_order['reitir'][ $key ] = $value;
	check( 'Pöntun: invalid ' . $key, isset( Pontun::check( $test_order, $pontun_config )['villur'][ $key ] ), true );
}
$test_order = $pontun_input;
$test_order['reitir']['kennitala'] = '123456AB7890';
check( 'Pöntun: kennitala rejects letters even with ten digits', isset( Pontun::check( $test_order, $pontun_config )['villur']['kennitala'] ), true );
$test_order['reitir']['kennitala'] = '123456 7890';
check( 'Pöntun: kennitala accepts a space', Pontun::check( $test_order, $pontun_config )['pontun']['reitir']['kennitala'], '123456-7890' );

$test_order = $pontun_input;
$test_order['linur'][0]['fjoldi'] = 0;
check( 'Pöntun: at least one active line is required', isset( Pontun::check( $test_order, $pontun_config )['villur']['linur'] ), true );
$test_order = $pontun_input;
$test_order['dropp'] = false;
check( 'Pöntun: Dropp acknowledgement is required', isset( Pontun::check( $test_order, $pontun_config )['villur']['dropp'] ), true );
$test_order['dropp'] = 'false';
check( 'Pöntun: a string cannot acknowledge Dropp', isset( Pontun::check( $test_order, $pontun_config )['villur']['dropp'] ), true );

$test_order = $pontun_input;
$test_order['linur'][1] = array( 'flokkur' => 'storubaekur', 'val' => array( 'tegund' => 'Kynslóð 99' ), 'fjoldi' => 2 );
check( 'Pöntun: a forged choice is refused even alongside a valid line', isset( Pontun::check( $test_order, $pontun_config )['villur']['linur'] ), true );
$test_order['linur'][1]['flokkur'] = 'unknown';
check( 'Pöntun: an unknown category with positive quantity is refused', isset( Pontun::check( $test_order, $pontun_config )['villur']['linur'] ), true );
$test_order['linur'][1] = $pontun_input['linur'][0];
foreach ( array( -1, 1.5, Pontun::MAX_FJOLDI + 1 ) as $fjoldi ) {
	$test_order['linur'][1]['fjoldi'] = $fjoldi;
	check( 'Pöntun: quantity outside the allowed range is refused (' . $fjoldi . ')', isset( Pontun::check( $test_order, $pontun_config )['villur']['linur'] ), true );
}
$test_order['linur'] = array_fill( 0, Pontun::MAX_LINUR + 1, $pontun_input['linur'][0] );
check( 'Pöntun: too many lines cannot be silently truncated', isset( Pontun::check( $test_order, $pontun_config )['villur']['linur'] ), true );

// The email and admin view must identify the same order and preserve line breaks in instructions.
$pontun_text = Pontun::texti( 270, '11.11.2026 10:30', $pontun_checked['pontun'] );
check( 'Pöntun text: order number and date', str_starts_with( $pontun_text, "Pöntun nr. 270\nDagsetning: 11.11.2026 10:30\n" ), true );
check( 'Pöntun text: quantity and choices', str_contains( $pontun_text, '3 × Veggspjöld: Kynslóð 1, A3, Plasthúðað' ), true );
check( 'Pöntun text: invoice information', str_contains( $pontun_text, 'Kennitala á reikning: 123456-7890' ), true );
check( 'Pöntun text: comments keep both lines', str_contains( $pontun_text, "Fyrsta lína\n    Önnur lína" ), true );
check( 'Pöntun text: empty optional fields are omitted', str_contains( $pontun_text, 'Inngangur' ), false );

echo ( $failures ? "{$failures} of {$count} checks FAILED\n" : "OK: {$count} checks passed\n" );
exit( $failures ? 1 : 0 );
