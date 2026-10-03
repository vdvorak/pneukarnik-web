<?php
/** @var array $booking */
/** @var string $cancel_url */
/** @var int $cancellation_days */
/** @var string $phone */
defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html lang="cs">
<head><meta charset="UTF-8"><title>Potvrzení rezervace</title></head>
<body style="font-family:Arial,sans-serif;color:#1a1a1a;max-width:600px;margin:0 auto;padding:24px">
	<h2 style="color:#F5A500">Jan Kárník Autoservis</h2>
	<h3>Potvrzení rezervace #<?php echo (int) $booking['id']; ?></h3>

	<p>Vážený zákazníku <?php echo esc_html( $booking['customer_name'] ); ?>,</p>
	<p>děkujeme za rezervaci. Níže jsou detaily vašeho termínu:</p>

	<table style="width:100%;border-collapse:collapse;margin:16px 0">
		<tr>
			<td style="padding:8px;font-weight:bold;width:40%">Služba</td>
			<td style="padding:8px"><?php echo esc_html( $booking['service_name'] ); ?></td>
		</tr>
		<tr style="background:#f9f9f9">
			<td style="padding:8px;font-weight:bold">Datum</td>
			<td style="padding:8px"><?php echo esc_html( pneukarnik_format_date( $booking['booking_date'] ) ); ?></td>
		</tr>
		<tr>
			<td style="padding:8px;font-weight:bold">Čas</td>
			<td style="padding:8px"><?php echo esc_html( $booking['time_start'] . '–' . $booking['time_end'] ); ?></td>
		</tr>
		<tr style="background:#f9f9f9">
			<td style="padding:8px;font-weight:bold">SPZ</td>
			<td style="padding:8px"><?php echo esc_html( $booking['customer_plate'] ); ?></td>
		</tr>
	</table>

	<?php if ( $cancellation_days > 0 ) : ?>
		<p><strong>Zrušení:</strong> Rezervaci lze zrušit nejpozději <?php echo (int) $cancellation_days; ?> <?php echo $cancellation_days === 1 ? 'den' : 'dny'; ?> před termínem.</p>
	<?php endif; ?>

	<p style="margin:24px 0">
		<a href="<?php echo esc_url( $cancel_url ); ?>" style="background:#e53e3e;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px">
			Zrušit rezervaci
		</a>
	</p>

	<?php if ( $phone ) : ?>
		<p>V případě dotazů nás kontaktujte na: <strong><?php echo esc_html( $phone ); ?></strong></p>
	<?php endif; ?>

	<hr style="border:none;border-top:1px solid #eee;margin:24px 0">
	<p style="color:#888;font-size:12px">Jan Kárník Autoservis &amp; Pneuservis | pneukarnik.cz</p>
</body>
</html>
