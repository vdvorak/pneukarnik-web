<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Email notifications via wp_mail().
 * Failure does NOT roll back the booking — email is fire-and-forget after commit.
 * Errors are logged via error_log / WP error log.
 */
class Pneukarnik_Notifications {

	public static function on_booking_created( array $booking, string $plain_token ): void {
		self::send_customer_confirmation( $booking, $plain_token );
		self::send_owner_notification( $booking );
	}

	public static function on_booking_cancelled( array $booking ): void {
		self::send_customer_cancellation( $booking );
	}

	// 1. Potvrzení zákazníkovi
	private static function send_customer_confirmation( array $booking, string $plain_token ): void {
		$cancel_url = add_query_arg(
			[
				'id'    => $booking['id'],
				'token' => $plain_token,
			],
			home_url( '/zrusit-rezervaci' )
		);

		$cancellation_days = Pneukarnik_Working_Hours::get_cancellation_days();
		$phone             = get_option( 'pneukarnik_phone', '' );

		$subject = sprintf(
			/* translators: %d: číslo rezervace */
			__( 'Potvrzení rezervace #%d — Jan Kárník Autoservis', 'pneukarnik-booking' ),
			$booking['id']
		);

		$body = Pneukarnik_Template::render(
			'booking-confirmation',
			[
				'booking'           => $booking,
				'cancel_url'        => $cancel_url,
				'cancellation_days' => $cancellation_days,
				'phone'             => $phone,
			]
		);

		$sent = wp_mail(
			$booking['customer_email'],
			$subject,
			$body,
			self::html_headers()
		);

		if ( ! $sent ) {
			error_log( sprintf( '[pneukarnik] Failed to send booking confirmation for booking #%d', $booking['id'] ) );
		}
	}

	// 2. Notifikace provozovateli
	private static function send_owner_notification( array $booking ): void {
		$owner_email = get_option( 'pneukarnik_email', get_option( 'admin_email' ) );
		$admin_url   = admin_url( 'admin.php?page=pneukarnik-booking&booking_id=' . $booking['id'] );

		$subject = sprintf(
			/* translators: 1: číslo rezervace, 2: datum, 3: čas, 4: jméno zákazníka */
			__( 'Nová rezervace #%1$d — %2$s %3$s — %4$s', 'pneukarnik-booking' ),
			$booking['id'],
			$booking['booking_date'],
			$booking['time_start'],
			$booking['customer_name']
		);

		$body = Pneukarnik_Template::render(
			'booking-notification-owner',
			[
				'booking'   => $booking,
				'admin_url' => $admin_url,
			]
		);

		$sent = wp_mail(
			$owner_email,
			$subject,
			$body,
			self::html_headers()
		);

		if ( ! $sent ) {
			error_log( sprintf( '[pneukarnik] Failed to send owner notification for booking #%d', $booking['id'] ) );
		}
	}

	// 3. Potvrzení zrušení zákazníkovi
	private static function send_customer_cancellation( array $booking ): void {
		$subject = sprintf(
			/* translators: %d: číslo rezervace */
			__( 'Zrušení rezervace #%d — Jan Kárník Autoservis', 'pneukarnik-booking' ),
			$booking['id']
		);

		$body = Pneukarnik_Template::render(
			'booking-cancellation',
			[
				'booking' => $booking,
			]
		);

		$sent = wp_mail(
			$booking['customer_email'],
			$subject,
			$body,
			self::html_headers()
		);

		if ( ! $sent ) {
			error_log( sprintf( '[pneukarnik] Failed to send cancellation email for booking #%d', $booking['id'] ) );
		}
	}

	private static function html_headers(): array {
		$host    = wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'localhost';
		$noreply = get_option( 'pneukarnik_noreply_email', 'noreply@' . $host );
		return [
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: Jan Kárník Autoservis <%s>', $noreply ),
		];
	}
}
