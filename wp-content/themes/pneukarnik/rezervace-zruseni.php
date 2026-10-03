<?php
/**
 * Zrušení Rezervace odkazem z e‑mailu: detail Rezervace, potvrzení Zrušení, po Lhůtě telefon.
 * Stav a pravidla dodává plugin (Pneukarnik_Booking_Pages::cancellation), formulář zpracuje on.
 *
 * @package Pneukarnik
 */

$pneukarnik_cancellation = Pneukarnik_Booking_Pages::cancellation();
$pneukarnik_booking      = $pneukarnik_cancellation['booking'];
$pneukarnik_code         = $pneukarnik_cancellation['code'];
$pneukarnik_phone        = $pneukarnik_cancellation['phone'];
$pneukarnik_call         = '' !== $pneukarnik_phone ? sprintf( /* translators: %s: telefon */ __( 'Zavolejte nám prosím na %s.', 'pneukarnik' ), $pneukarnik_phone ) : '';
$pneukarnik_until        = '' !== $pneukarnik_cancellation['cancel_until'] ? Pneukarnik_Clock::at( $pneukarnik_cancellation['cancel_until'] )->format( 'j. n. Y \v G:i' ) : '';

// Text pro každý výsledek odkazu.
$pneukarnik_messages   = [
	/* translators: %s: den a čas konce Lhůty zrušení */
	Pneukarnik_Cancellation::ALLOWED           => sprintf( __( 'Rezervaci můžete zrušit nejpozději %s. Termín se tím uvolní pro ostatní.', 'pneukarnik' ), $pneukarnik_until ),
	Pneukarnik_Cancellation::CANCELLED         => __( 'Rezervace je zrušená a termín jsme uvolnili. Potvrzení jsme vám poslali e‑mailem.', 'pneukarnik' ),
	/* translators: 1: den a čas konce Lhůty zrušení, 2: výzva k zavolání s telefonem */
	Pneukarnik_Cancellation::TOO_LATE          => trim( sprintf( __( 'Online šlo rezervaci zrušit nejpozději %1$s. %2$s', 'pneukarnik' ), $pneukarnik_until, $pneukarnik_call ) ),
	Pneukarnik_Cancellation::ALREADY_CANCELLED => __( 'Tato rezervace už je zrušená.', 'pneukarnik' ),
	/* translators: %s: výzva k zavolání s telefonem */
	Pneukarnik_Cancellation::INVALID_TOKEN     => trim( sprintf( __( 'Odkaz pro zrušení je neplatný nebo už vypršel. %s', 'pneukarnik' ), $pneukarnik_call ) ),
];
$pneukarnik_offer_call = '' !== $pneukarnik_phone && in_array( $pneukarnik_code, [ Pneukarnik_Cancellation::TOO_LATE, Pneukarnik_Cancellation::INVALID_TOKEN ], true );

get_header();
?>
<main id="obsah" class="site-main rezervace-zruseni">
	<h1><?php esc_html_e( 'Zrušení rezervace', 'pneukarnik' ); ?></h1>

	<?php if ( null !== $pneukarnik_booking ) : ?>
		<dl class="rezervace-potvrzeni__udaje">
			<dt><?php esc_html_e( 'Termín', 'pneukarnik' ); ?></dt>
			<dd><?php echo esc_html( pneukarnik_format_day( $pneukarnik_booking['date'] ) . ' ' . Pneukarnik_Clock::at( $pneukarnik_booking['date'] . ' ' . $pneukarnik_booking['time_start'] )->format( 'G:i' ) ); ?></dd>
			<dt><?php echo esc_html( count( $pneukarnik_booking['services'] ) > 1 ? __( 'Služby', 'pneukarnik' ) : __( 'Služba', 'pneukarnik' ) ); ?></dt>
			<?php foreach ( $pneukarnik_booking['services'] as $pneukarnik_service ) : ?>
				<dd><?php echo esc_html( $pneukarnik_service ); ?></dd>
			<?php endforeach; ?>
			<dt><?php esc_html_e( 'SPZ', 'pneukarnik' ); ?></dt>
			<dd><?php echo esc_html( $pneukarnik_booking['plate'] ); ?></dd>
		</dl>
	<?php endif; ?>

	<?php if ( Pneukarnik_Cancellation::ALLOWED === $pneukarnik_code ) : ?>
		<p><?php echo esc_html( $pneukarnik_messages[ $pneukarnik_code ] ); ?></p>
		<form method="post" action="<?php echo esc_url( Pneukarnik_Cancellation::url( $pneukarnik_cancellation['token'] ) ); ?>">
			<input type="hidden" name="r" value="<?php echo esc_attr( $pneukarnik_cancellation['token'] ); ?>">
			<button type="submit"><?php esc_html_e( 'Zrušit rezervaci', 'pneukarnik' ); ?></button>
		</form>
	<?php else : ?>
		<p role="alert"><?php echo esc_html( $pneukarnik_messages[ $pneukarnik_code ] ); ?></p>
		<?php if ( $pneukarnik_offer_call ) : ?>
			<p><a href="<?php echo esc_url( pneukarnik_tel_href( $pneukarnik_phone ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s: telefon */ __( 'Zavolat %s', 'pneukarnik' ), $pneukarnik_phone ) ); ?></a></p>
		<?php endif; ?>
		<p><a href="<?php echo esc_url( home_url( '/rezervace/' ) ); ?>"><?php esc_html_e( 'Objednat nový termín', 'pneukarnik' ); ?></a></p>
	<?php endif; ?>
</main>
<?php
get_footer();
