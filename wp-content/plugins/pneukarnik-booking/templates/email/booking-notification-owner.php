<?php
/** @var array $booking */
/** @var string $admin_url */
defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html lang="cs">
<head><meta charset="UTF-8"><title>Nová rezervace</title></head>
<body style="font-family:Arial,sans-serif;color:#1a1a1a;max-width:600px;margin:0 auto;padding:24px">
	<h2>Nová rezervace #<?php echo (int) $booking['id']; ?></h2>

	<table style="width:100%;border-collapse:collapse;margin:16px 0">
		<tr>
			<td style="padding:8px;font-weight:bold;width:40%">Datum</td>
			<td style="padding:8px"><?php echo esc_html( pneukarnik_format_date( $booking['booking_date'] ) ); ?></td>
		</tr>
		<tr style="background:#f9f9f9">
			<td style="padding:8px;font-weight:bold">Čas</td>
			<td style="padding:8px"><?php echo esc_html( $booking['time_start'] . '–' . $booking['time_end'] ); ?></td>
		</tr>
		<tr>
			<td style="padding:8px;font-weight:bold">Služba</td>
			<td style="padding:8px"><?php echo esc_html( $booking['service_name'] ); ?></td>
		</tr>
		<tr style="background:#f9f9f9">
			<td style="padding:8px;font-weight:bold">Zákazník</td>
			<td style="padding:8px"><?php echo esc_html( $booking['customer_name'] ); ?></td>
		</tr>
		<tr>
			<td style="padding:8px;font-weight:bold">SPZ</td>
			<td style="padding:8px"><?php echo esc_html( $booking['customer_plate'] ); ?></td>
		</tr>
		<tr style="background:#f9f9f9">
			<td style="padding:8px;font-weight:bold">Email</td>
			<td style="padding:8px"><?php echo esc_html( $booking['customer_email'] ); ?></td>
		</tr>
		<tr>
			<td style="padding:8px;font-weight:bold">Telefon</td>
			<td style="padding:8px"><?php echo esc_html( $booking['customer_phone'] ); ?></td>
		</tr>
		<?php if ( $booking['customer_company'] ) : ?>
		<tr style="background:#f9f9f9">
			<td style="padding:8px;font-weight:bold">Firma</td>
			<td style="padding:8px"><?php echo esc_html( $booking['customer_company'] ); ?></td>
		</tr>
		<?php endif; ?>
	</table>

	<p>
		<a href="<?php echo esc_url( $admin_url ); ?>" style="background:#F5A500;color:#fff;padding:10px 20px;text-decoration:none;border-radius:4px">
			Zobrazit v administraci
		</a>
	</p>
</body>
</html>
