<?php
/**
 * An order from the Pantanir form: the fields, checking what was sent, and the order as text for the emails and the
 * admin list. Pure PHP (no WordPress), tested in tests/run.php.
 */

namespace Vasaskrimsli\Pontun;

final class Pontun {

	/** The form's fields: id => [label, required]. In the order the form and the emails show them. */
	public const REITIR = array(
		'nafn'            => array( 'Nafn þess sem pantar', true ),
		'netfang'         => array( 'Tölvupóstur', true ),
		'simi'            => array( 'Sími', true ),
		'vidtakandi'      => array( 'Viðtakandi', true ),
		'heimilisfang'    => array( 'Heimilisfang', true ),
		'inngangur'       => array( 'Inngangur, hæð o.þ.h.', false ),
		'stadur'          => array( 'Staður/bær/borg', true ),
		'postnumer'       => array( 'Póstnúmer', true ),
		'greidandi'       => array( 'Reikningur stílaður á', true ),
		'kennitala'       => array( 'Kennitala á reikning', true ),
		'netfang_greid'   => array( 'Tölvupóstur greiðanda', true ),
		'aths_reikningur' => array( 'Aðrar athugasemdir varðandi reikning', false ),
		'aths'            => array( 'Aðrar athugasemdir varðandi pöntunina eða afhendingarmáta', false ),
	);

	public const MAX_FJOLDI = 10000;
	public const MAX_LINUR  = 100;

	/**
	 * Checks an order as sent by the form.
	 * @param array $input  { reitir: {id: text}, linur: [{flokkur, val: {id: choice}, fjoldi}], dropp: bool }
	 * @param array $config data/pontun.json.
	 * @return array{villur: array<string, string>, pontun: array} villur: field id (or 'linur', 'dropp') => message;
	 *         pontun: the clean order { reitir, linur: [{flokkur, heiti, val: [[label, choice]], fjoldi}] }.
	 */
	public static function check( array $input, array $config ): array {
		$villur = array();
		$reitir = array();
		$source = is_array( $input['reitir'] ?? null ) ? $input['reitir'] : array();
		foreach ( self::REITIR as $id => [ $label, $required ] ) {
			$v     = is_string( $source[ $id ] ?? null ) ? $source[ $id ] : '';
			$langt = in_array( $id, array( 'aths', 'aths_reikningur' ), true );
			$v     = $langt ? str_replace( array( "\r\n", "\r" ), "\n", $v ) : preg_replace( '/\s+/u', ' ', $v );
			// Count UTF-8 characters without depending on the optional mbstring extension.
			preg_match( '/\A.{0,' . ( $langt ? 2000 : 200 ) . '}/su', trim( $v ?? '' ), $first );
			$v = $first[0] ?? '';
			if ( $required && '' === $v ) {
				$villur[ $id ] = 'Fylltu út: ' . $label . '.';
			} elseif ( '' !== $v && in_array( $id, array( 'netfang', 'netfang_greid' ), true ) && ! filter_var( $v, FILTER_VALIDATE_EMAIL ) ) {
				$villur[ $id ] = 'Netfangið er ekki rétt skrifað.';
			} elseif ( '' !== $v && 'kennitala' === $id ) {
				$digits = str_replace( array( ' ', '-' ), '', $v );
				if ( ! preg_match( '/^[0-9]{10}$/D', $digits ) ) {
					$villur[ $id ] = 'Kennitala er tíu tölustafir.';
				} else {
					$v = substr( $digits, 0, 6 ) . '-' . substr( $digits, 6 );
				}
			} elseif ( '' !== $v && 'postnumer' === $id && ! preg_match( '/^\d{3}$/', $v ) ) {
				$villur[ $id ] = 'Póstnúmer er þrír tölustafir.';
			} elseif ( '' !== $v && 'simi' === $id && strlen( preg_replace( '/\D/', '', $v ) ) < 7 ) {
				$villur[ $id ] = 'Símanúmerið er of stutt.';
			}
			$reitir[ $id ] = $v;
		}

		$flokkar = array_column( $config['flokkar'] ?? array(), null, 'id' );
		$linur   = array();
		$raw     = $input['linur'] ?? array();
		if ( ! is_array( $raw ) || ! array_is_list( $raw ) || count( $raw ) > self::MAX_LINUR ) {
			$villur['linur'] = 'Ekki tókst að lesa vörulínurnar. Athugaðu fjölda lína.';
			$raw = array();
		}
		foreach ( $raw as $l ) {
			$q = $l['fjoldi'] ?? 0;
			if ( ! is_array( $l ) || ( ! is_int( $q ) && ! ( is_string( $q ) && ctype_digit( $q ) ) ) || (int) $q < 0 ) {
				$villur['linur'] = 'Fjöldi í vörulínu þarf að vera heil tala frá 0 og upp úr.';
				continue;
			}
			$fjoldi = (int) $q;
			if ( 0 === $fjoldi ) {
				continue; // Empty lines are left out.
			}
			$id = $l['flokkur'] ?? '';
			$f  = is_string( $id ) ? ( $flokkar[ $id ] ?? null ) : null;
			if ( ! $f ) {
				$villur['linur'] = 'Óþekktur vöruflokkur er í pöntuninni.';
				continue;
			}
			$choices = is_array( $l['val'] ?? null ) ? $l['val'] : array();
			$val   = array();
			$pakki = 0; // Books in each pack, when the category is sold in packs.
			foreach ( $f['val'] as $v ) {
				$choice = $choices[ $v['id'] ] ?? '';
				if ( ! in_array( $choice, $v['kostir'], true ) ) {
					$villur['linur'] = 'Athugaðu valið í vöruflokknum „' . $f['heiti'] . '“.';
					continue 2;
				}
				$val[] = array( $v['heiti'], $choice );
				if ( isset( $v['baekur'] ) ) {
					$pakki = (int) ( $v['baekur'][ array_search( $choice, $v['kostir'], true ) ] ?? 0 );
				}
			}
			if ( $fjoldi > self::MAX_FJOLDI ) {
				$villur['linur'] = 'Fjöldinn er of mikill. Hafðu samband ef pöntunin er svo stór.';
				continue;
			}
			$linur[] = array( 'flokkur' => $f['id'], 'heiti' => $f['heiti'], 'val' => $val, 'fjoldi' => $fjoldi ) + ( $pakki > 1 ? array( 'pakki' => $pakki ) : array() );
		}
		if ( ! $linur && ! isset( $villur['linur'] ) ) {
			$villur['linur'] = 'Veldu að minnsta kosti eina vöru og fjölda.';
		}
		if ( true !== ( $input['dropp'] ?? false ) ) {
			$villur['dropp'] = 'Hakaðu við að vörurnar séu sendar með Dropp.';
		}
		return array( 'villur' => $villur, 'pontun' => array( 'reitir' => $reitir, 'linur' => $linur ) );
	}

	/**
	 * One order line as text: "3 × Veggspjöld: Kynslóð 1, A3, Plasthúðað". A line of book packs says so and adds up the
	 * books: "2 pakkar × Lestrarbækur: Lestrarbækur 1 til 10 (ath pakki af tíu bókum), 10 bækur í pakka, 20 bækur alls".
	 */
	public static function lina( array $l ): string {
		$text  = implode( ', ', array_column( $l['val'], 1 ) );
		$pakki = (int) ( $l['pakki'] ?? 0 );
		if ( $pakki <= 1 ) {
			return $l['fjoldi'] . ' × ' . $l['heiti'] . ': ' . $text;
		}
		$n = (int) $l['fjoldi'];
		return self::fjoldi( $n, 'pakki', 'pakkar' ) . ' × ' . $l['heiti'] . ': ' . $text . ', '
			. self::fjoldi( $pakki, 'bók', 'bækur' ) . ' í pakka, ' . self::fjoldi( $n * $pakki, 'bók', 'bækur' ) . ' alls';
	}

	/** "1 pakki", "21 pakki", "11 pakkar", "3 pakkar": Icelandic takes the singular after numbers ending in 1, except 11. */
	public static function fjoldi( int $n, string $eintala, string $fleirtala ): string {
		return $n . ' ' . ( 1 === $n % 10 && 11 !== $n % 100 ? $eintala : $fleirtala );
	}

	/** The order as plain text for the emails: number, date, the lines, then every field that was filled in. */
	public static function texti( int $numer, string $dags, array $pontun ): string {
		$out = array( 'Pöntun nr. ' . $numer, 'Dagsetning: ' . $dags, '', 'Vörur:' );
		foreach ( $pontun['linur'] as $l ) {
			$out[] = '  ' . self::lina( $l );
		}
		$groups = array(
			'Hver pantar'        => array( 'nafn', 'netfang', 'simi' ),
			'Sendist til'        => array( 'vidtakandi', 'heimilisfang', 'inngangur', 'stadur', 'postnumer' ),
			'Reikningsupplýsingar' => array( 'greidandi', 'kennitala', 'netfang_greid', 'aths_reikningur' ),
			'Athugasemdir'       => array( 'aths' ),
		);
		foreach ( $groups as $title => $ids ) {
			$lines = array();
			foreach ( $ids as $id ) {
				$v = (string) ( $pontun['reitir'][ $id ] ?? '' );
				if ( '' !== $v ) {
					$lines[] = '  ' . self::REITIR[ $id ][0] . ': ' . str_replace( "\n", "\n    ", $v );
				}
			}
			if ( $lines ) {
				$out[] = '';
				$out[] = $title . ':';
				array_push( $out, ...$lines );
			}
		}
		return implode( "\n", $out ) . "\n";
	}
}
