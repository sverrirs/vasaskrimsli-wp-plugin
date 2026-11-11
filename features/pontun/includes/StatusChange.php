<?php
/**
 * Customer notification and feedback shared by the two order status forms.
 */

namespace Vasaskrimsli\Pontun;

final class StatusChange {

	public const MESSAGES = array(
		'stada'               => 'Staðan var uppfærð.',
		'stada_obreytt'       => 'Staða pöntunarinnar er óbreytt.',
		'stada_sent'          => 'Staðan var uppfærð og tölvupóstur sendur til þess sem pantaði.',
		'stada_postvilla'     => 'Staðan var uppfærð en ekki tókst að senda tölvupóstinn.',
		'stada_netfangvantar' => 'Staðan var uppfærð en gilt netfang vantar hjá þeim sem pantaði.',
	);

	public static function checkbox( array $order ): string {
		if ( ! is_email( $order['netfang'] ?? '' ) ) {
			return '';
		}
		return '<p><label><input type="checkbox" name="senda_stodu" value="1"> Senda þeim sem pantaði tölvupóst um stöðubreytinguna</label></p>';
	}

	public static function finish( int $numer, string $status, int $changed, bool $notify ): string {
		if ( ! $changed ) {
			return 'stada_obreytt';
		}
		if ( ! $notify ) {
			return 'stada';
		}
		$order = Store::get( $numer );
		$email = $order['netfang'] ?? '';
		if ( ! is_email( $email ) ) {
			return 'stada_netfangvantar';
		}

		$label = Store::STADA[ $status ];
		$sent = wp_mail(
			$email,
			'Staða pöntunar nr. ' . $numer,
			"Staða pöntunar nr. {$numer} hefur breyst.\n\nNý staða: {$label}\n\nVasaskrímsli á íslensku\n"
		);
		return $sent ? 'stada_sent' : 'stada_postvilla';
	}
}
