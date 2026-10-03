<?php
/** @var array $booking */
defined( 'ABSPATH' ) || exit;
?>
<!DOCTYPE html>
<html lang="cs">
<head><meta charset="UTF-8"><title>Zrušení rezervace</title></head>
<body style="font-family:Arial,sans-serif;color:#1a1a1a;max-width:600px;margin:0 auto;padding:24px">
	<h2 style="color:#F5A500">Jan Kárník Autoservis</h2>
	<h3>Zrušení rezervace #<?php echo (int) $booking['id']; ?></h3>

	<p>Vážený zákazníku <?php echo esc_html( $booking['customer_name'] ); ?>,</p>
	<p>potvrzujeme zrušení vaší rezervace:</p>

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
	</table>

	<p>Pokud si přejete objednat nový termín, navštivte <a href="https://pneukarnik.cz/rezervace">pneukarnik.cz/rezervace</a>.</p>

	<hr style="border:none;border-top:1px solid #eee;margin:24px 0">
	<p style="color:#888;font-size:12px">Jan Kárník Autoservis &amp; Pneuservis | pneukarnik.cz</p>
</body>
</html>
