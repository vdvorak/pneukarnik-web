<?php
/**
 * Zrušení Rezervace odkazem z e‑mailu: detail Rezervace, potvrzení Zrušení, po Lhůtě telefon.
 * Stav a pravidla dodává plugin (Pneukarnik_Booking_Pages::cancellation), formulář zpracuje on.
 * Bílá karta na přechodu; každý stav má svůj text nebo Notice a svoje tlačítka.
 *
 * @package Pneukarnik
 */

$pneukarnik_cancellation = Pneukarnik_Booking_Pages::cancellation();
$pneukarnik_booking      = $pneukarnik_cancellation['booking'];
$pneukarnik_code         = $pneukarnik_cancellation['code'];
$pneukarnik_phone        = $pneukarnik_cancellation['phone'];
$pneukarnik_call         = '' !== $pneukarnik_phone ? sprintf( /* translators: %s: telefon */ __( 'Zavolejte nám prosím na %s.', 'pneukarnik' ), $pneukarnik_phone ) : '';
$pneukarnik_until        = '' !== $pneukarnik_cancellation['cancel_until'] ? Pneukarnik_Clock::at( $pneukarnik_cancellation['cancel_until'] )->format( 'j. n. Y \v G:i' ) : '';

/*
 * Každý výsledek odkazu: text, tón (null = prostý text, jinak Notice), ✓ u úspěchu,
 * tlačítko Zrušit, Zavolat a Objednat znovu jako hlavní (accent) nebo vedlejší (secondary).
 */
$pneukarnik_states = [
	Pneukarnik_Cancellation::ALLOWED           => [
		/* translators: %s: den a čas konce Lhůty zrušení */
		'text'   => sprintf( __( 'Rezervaci můžete zrušit nejpozději %s. Termín se tím uvolní pro ostatní.', 'pneukarnik' ), $pneukarnik_until ),
		'tone'   => null,
		'cancel' => true,
	],
	Pneukarnik_Cancellation::CANCELLED         => [
		'text'  => __( 'Rezervace je zrušená a termín jsme uvolnili. Potvrzení jsme vám poslali e‑mailem.', 'pneukarnik' ),
		'tone'  => null,
		'ok'    => true,
		'again' => 'accent',
	],
	Pneukarnik_Cancellation::TOO_LATE          => [
		/* translators: 1: den a čas konce Lhůty zrušení, 2: výzva k zavolání s telefonem */
		'text'  => trim( sprintf( __( 'Online šlo rezervaci zrušit nejpozději %1$s. %2$s', 'pneukarnik' ), $pneukarnik_until, $pneukarnik_call ) ),
		'tone'  => '',
		'call'  => true,
		'again' => 'secondary',
	],
	Pneukarnik_Cancellation::ALREADY_CANCELLED => [
		'text'  => __( 'Tato rezervace už je zrušená.', 'pneukarnik' ),
		'tone'  => 'info',
		'again' => 'accent',
	],
	Pneukarnik_Cancellation::RATE_LIMITED      => [
		'text'  => __( 'Z vašeho připojení přišlo příliš mnoho pokusů o zrušení. Rezervace zůstává, zkuste to prosím za hodinu.', 'pneukarnik' ),
		'tone'  => '',
		'call'  => true,
		'again' => 'secondary',
	],
	Pneukarnik_Cancellation::INVALID_TOKEN     => [
		'text'  => __( 'Odkaz pro zrušení je neplatný nebo už vypršel.', 'pneukarnik' ),
		'tone'  => 'danger',
		'call'  => true,
		'again' => 'secondary',
	],
];
$pneukarnik_state  = $pneukarnik_states[ $pneukarnik_code ] + [
	'ok'     => false,
	'cancel' => false,
	'call'   => false,
	'again'  => '',
];

get_header();
?>
<main id="obsah" class="site-main vysledek">
	<div class="vysledek__inner">
		<div class="card vysledek__karta">
			<?php if ( $pneukarnik_state['ok'] ) : ?>
				<span class="vysledek__ikona" aria-hidden="true">✓</span>
			<?php endif; ?>
			<h1><?php esc_html_e( 'Zrušení rezervace', 'pneukarnik' ); ?></h1>

			<?php
			if ( null !== $pneukarnik_booking ) {
				pneukarnik_booking_summary(
					[
						__( 'Termín', 'pneukarnik' ) => pneukarnik_format_day( $pneukarnik_booking['date'] ) . ' v ' . Pneukarnik_Clock::at( $pneukarnik_booking['date'] . ' ' . $pneukarnik_booking['time_start'] )->format( 'G:i' ),
						( count( $pneukarnik_booking['services'] ) > 1 ? __( 'Služby', 'pneukarnik' ) : __( 'Služba', 'pneukarnik' ) ) => implode( ', ', $pneukarnik_booking['services'] ),
						__( 'SPZ', 'pneukarnik' )    => $pneukarnik_booking['plate'],
					]
				);
			}
			?>

			<?php if ( null === $pneukarnik_state['tone'] ) : ?>
				<p class="vysledek__text"<?php echo $pneukarnik_state['ok'] ? ' role="status"' : ''; ?>><?php echo esc_html( $pneukarnik_state['text'] ); ?></p>
			<?php else : ?>
				<p class="notice<?php echo '' !== $pneukarnik_state['tone'] ? ' notice--' . esc_attr( $pneukarnik_state['tone'] ) : ''; ?>" role="<?php echo 'info' === $pneukarnik_state['tone'] ? 'status' : 'alert'; ?>"><?php echo esc_html( $pneukarnik_state['text'] ); ?></p>
			<?php endif; ?>

			<div class="vysledek__tlacitka">
				<?php if ( $pneukarnik_state['cancel'] ) : ?>
					<form method="post" action="<?php echo esc_url( Pneukarnik_Cancellation::url( $pneukarnik_cancellation['token'] ) ); ?>">
						<input type="hidden" name="r" value="<?php echo esc_attr( $pneukarnik_cancellation['token'] ); ?>">
						<button type="submit" class="button button--danger"><?php esc_html_e( 'Zrušit rezervaci', 'pneukarnik' ); ?></button>
					</form>
				<?php endif; ?>
				<?php if ( $pneukarnik_state['call'] && '' !== $pneukarnik_phone ) : ?>
					<a class="button button--dark" href="<?php echo esc_url( pneukarnik_tel_href( $pneukarnik_phone ) ); ?>"><?php echo esc_html( sprintf( /* translators: %s: telefon */ __( 'Zavolat %s', 'pneukarnik' ), $pneukarnik_phone ) ); ?></a>
				<?php endif; ?>
				<?php if ( '' !== $pneukarnik_state['again'] ) : ?>
					<a class="button<?php echo 'secondary' === $pneukarnik_state['again'] ? ' button--secondary' : ''; ?>" href="<?php echo esc_url( $pneukarnik_cancellation['prefill_url'] ); ?>"><?php esc_html_e( 'Objednat znovu', 'pneukarnik' ); ?></a>
				<?php endif; ?>
			</div>
		</div>
	</div>
</main>
<?php
get_footer();
