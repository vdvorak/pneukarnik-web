<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Připomínka Termínu (viz CONTEXT.md): den před Termínem v nastavenou hodinu e‑mail ke každé
 * potvrzené Rezervaci s e‑mailem, i zadané Provozovatelem. Provozní e‑mail, mezi Nabídky
 * a připomínky nepatří, odmítnout nejde. Provozovatel ji vypíná na stránce E‑maily Zákazníkům.
 *
 * Neposílá se k Rezervaci vytvořené méně než 24 hodin před plánovaným odesláním (Zákazník
 * na ni ještě nezapomněl). Plánovaná úloha běží každou hodinu. Rezervace se před odesláním
 * označí (reminder_sent), takže opakované nebo souběžné spuštění nic nepošle dvakrát. Když
 * odeslání selže, označení se vrátí a zkusí se to příště. Přesun na jiný den označení smaže.
 */
final class Pneukarnik_Termin_Reminder {

	public const CRON_HOOK = 'pneukarnik_termin_reminder_send';

	public const OPTION_ENABLED = 'pneukarnik_termin_reminder_enabled';
	public const OPTION_HOUR    = 'pneukarnik_termin_reminder_hour';

	private const DEFAULT_HOUR = 16;

	public static function init(): void {
		add_action( self::CRON_HOOK, [ self::class, 'send_due' ] );
		add_action( 'init', [ self::class, 'schedule' ] );
	}

	/**
	 * Každou hodinu, naplánuje se samo i u už aktivního pluginu.
	 */
	public static function schedule(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( Pneukarnik_Clock::now()->getTimestamp(), 'hourly', self::CRON_HOOK );
		}
	}

	public static function enabled(): bool {
		return '1' === (string) get_option( self::OPTION_ENABLED, '1' );
	}

	/** Hodina místního času den před Termínem, od které se Připomínky posílají (0–23). */
	public static function hour(): int {
		return max( 0, min( 23, (int) get_option( self::OPTION_HOUR, self::DEFAULT_HOUR ) ) );
	}

	public static function save( bool $enabled, int $hour ): void {
		update_option( self::OPTION_ENABLED, $enabled ? '1' : '0' );
		update_option( self::OPTION_HOUR, max( 0, min( 23, $hour ) ) );
	}

	/**
	 * Pošle Připomínky k zítřejším Termínům, když je čas (plánovaná úloha).
	 */
	public static function send_due(): void {
		$now = Pneukarnik_Clock::now();
		if ( ! self::enabled() || (int) $now->format( 'G' ) < self::hour() ) {
			return;
		}
		$date = Pneukarnik_Clock::today()->modify( '+1 day' )->format( 'Y-m-d' );
		foreach ( self::recipients( $date ) as $id ) {
			$booking = Pneukarnik_Booking::get_by_id( $id );
			if ( null === $booking || ! is_email( $booking['customer_email'] ) || ! self::claim( $id, $date ) ) {
				continue; // Nebo ji mezitím poslalo souběžné spuštění.
			}
			if ( ! Pneukarnik_Notifications::on_termin_reminder( $booking ) ) {
				self::release( $id );
			}
		}
	}

	/**
	 * Zkušební Připomínka s ukázkovou Rezervací na e‑mail Provozovatele z Nastavení, nikam jinam.
	 *
	 * @return string|null Adresa, kam odešla, null = Provozovatel nemá e‑mail nebo odeslání selhalo.
	 */
	public static function send_test(): ?string {
		$to = Pneukarnik_Contact::email();
		if ( '' === $to ) {
			return null;
		}
		return Pneukarnik_Notifications::termin_reminder_test( $to ) ? $to : null;
	}

	/**
	 * Potvrzené Rezervace na $date s e‑mailem, kterým Připomínka ještě neodešla a které vznikly
	 * aspoň 24 hodin před plánovaným odesláním.
	 *
	 * @return list<int>
	 */
	private static function recipients( string $date ): array {
		global $wpdb;
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM %i WHERE booking_date = %s AND status = %s AND reminder_sent = 0 AND customer_email <> '' AND created_at <= %s ORDER BY time_start, id",
				Pneukarnik_DB::bookings_table(),
				$date,
				Pneukarnik_Booking::STATUS_CONFIRMED,
				self::created_until( $date )
			)
		);
		return array_map( 'intval', $ids );
	}

	/**
	 * Nejpozdější vytvoření Rezervace na $date, ke které se Připomínka posílá: 24 skutečných
	 * hodin před plánovaným odesláním (přes přechod na letní čas to je o hodinu jiný místní čas).
	 * created_at je místní čas.
	 */
	private static function created_until( string $date ): string {
		$send_at = Pneukarnik_Clock::at( $date )->modify( '-1 day' )->setTime( self::hour(), 0 );
		return ( new DateTimeImmutable( '@' . ( $send_at->getTimestamp() - DAY_IN_SECONDS ) ) )
			->setTimezone( Pneukarnik_Clock::timezone() )
			->format( 'Y-m-d H:i:s' );
	}

	/**
	 * Označí Rezervaci jako připomenutou. False, když už ji označilo jiné spuštění nebo se mezitím
	 * zrušila či přesunula.
	 */
	private static function claim( int $id, string $date ): bool {
		global $wpdb;
		return 1 === (int) $wpdb->query(
			$wpdb->prepare(
				'UPDATE %i SET reminder_sent = 1 WHERE id = %d AND reminder_sent = 0 AND status = %s AND booking_date = %s',
				Pneukarnik_DB::bookings_table(),
				$id,
				Pneukarnik_Booking::STATUS_CONFIRMED,
				$date
			)
		);
	}

	private static function release( int $id ): void {
		global $wpdb;
		$wpdb->update( Pneukarnik_DB::bookings_table(), [ 'reminder_sent' => 0 ], [ 'id' => $id ] );
	}
}
