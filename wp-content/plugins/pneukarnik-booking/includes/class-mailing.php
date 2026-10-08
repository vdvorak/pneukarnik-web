<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Rozesílka (viz CONTEXT.md): e‑mail o jedné nebo více Akcích (platných teď nebo začínajících do
 * DAYS_AHEAD dní) s úvodní větou od Provozovatele, který odejde každému e‑mailu, jenž smí dostávat
 * Akce (Nabídky a připomínky druhu Pneukarnik_Subscriptions::PROMOTIONS, i starý souhlas LEGACY),
 * nebo jen Zákazníkům jedné Kategorie (mají nezrušenou Rezervaci Služby té Kategorie).
 *
 * Provozovatel ji na stránce E‑maily Zákazníkům složí (rozepsaná), pošle si zkušební e‑mail a pak
 * ji odešle hned, nebo naplánuje na datum a čas (naplánovaná). Bez zkušebního e‑mailu po poslední
 * změně obsahu odeslat ani naplánovat nejde. Naplánovanou jde do odeslání upravit (změna obsahu ji
 * vrátí mezi rozepsané, potřebuje nový zkušební e‑mail) nebo zrušit (zrušená).
 *
 * Plánovaná úloha běží každou hodinu, hned po odeslání a v naplánovaný čas, a posílá po dávkách.
 * Akce se vyhodnotí při každé dávce: skončená nebo smazaná v e‑mailu není, a nezbude‑li žádná dřív,
 * než Rozesílka komukoli odešla, neodejde vůbec (neodeslaná). Příjemci se vybírají při každé dávce,
 * takže kdo mezitím Akce odhlásí, už ji nedostane. E‑mail se před odesláním zapíše k Rozesílce,
 * takže opakované nebo souběžné spuštění nic nepošle dvakrát. Když odeslání selže, zápis se smaže
 * a zkusí se to příště. Rozesílka je odeslaná, když už nezbývá nikdo, komu by šla. Výmaz osobních
 * údajů zápisy e‑mailu smaže.
 *
 * @phpstan-type Mailing array{id:int,intro:string,promotion_ids:list<int>,category:string,status:string,test_sent_at:string|null,scheduled_at:string|null,created_at:string,started_at:string|null,finished_at:string|null,sent_count:int}
 */
final class Pneukarnik_Mailing {

	public const CRON_HOOK = 'pneukarnik_mailing_send';

	public const DRAFT     = 'draft';
	public const SCHEDULED = 'scheduled';
	public const SENDING   = 'sending';
	public const SENT      = 'sent';
	/** Do odeslání nezbyla žádná platná Akce. */
	public const NOT_SENT  = 'not_sent';
	public const CANCELLED = 'cancelled';

	public const DONE          = 'mailing.done';
	public const NOT_FOUND     = 'mailing.not_found';
	public const NOT_DRAFT     = 'mailing.not_draft';
	public const NO_PROMOTIONS = 'mailing.no_promotions';
	public const NOT_TESTED    = 'mailing.not_tested';
	public const NOT_FUTURE    = 'mailing.not_future';

	/** Akce začínající nejpozději za tolik dní jde do Rozesílky vybrat. */
	public const DAYS_AHEAD = 14;

	public const DEFAULT_INTRO = "Dobrý den,\nmáme pro vás akci, která by se vám mohla hodit.";

	/** Kolik Rozesílek nejvýš odejde za jedno spuštění (limity SMTP hostingu). */
	private const BATCH_SIZE = 50;

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

	/**
	 * Akce, které jde do Rozesílky vybrat.
	 *
	 * @return list<Pneukarnik_Promotion>
	 */
	public static function promotions(): array {
		return Pneukarnik_Promotion::upcoming( self::DAYS_AHEAD );
	}

	/**
	 * Uloží rozepsanou nebo naplánovanou Rozesílku, bez $id založí novou. Změna obsahu (úvod, Akce)
	 * zruší dřívější zkušební e‑mail a naplánovanou vrátí mezi rozepsané. Změna Kategorie ne.
	 *
	 * @param list<int> $promotion_ids Akce z promotions().
	 * @param string    $category      Kategorie příjemců, prázdná (i neznámá) = všichni.
	 * @return int|string ID Rozesílky, nebo NOT_FOUND, NOT_DRAFT, NO_PROMOTIONS.
	 */
	public static function save( ?int $id, string $intro, array $promotion_ids, string $category = '' ): int|string {
		$allowed = array_map( static fn( Pneukarnik_Promotion $promotion ): int => $promotion->id, self::promotions() );
		$ids     = array_values( array_intersect( $allowed, array_map( 'intval', $promotion_ids ) ) );
		if ( ! $ids ) {
			return self::NO_PROMOTIONS;
		}
		$intro    = sanitize_textarea_field( $intro );
		$ids      = implode( ',', $ids );
		$category = isset( Pneukarnik_Service::categories()[ $category ] ) ? $category : '';

		global $wpdb;
		if ( null === $id ) {
			$wpdb->insert(
				Pneukarnik_DB::mailings_table(),
				[
					'intro'         => $intro,
					'promotion_ids' => $ids,
					'category'      => $category,
					'status'        => self::DRAFT,
					'created_at'    => Pneukarnik_Clock::now()->format( 'Y-m-d H:i:s' ),
				]
			);
			return (int) $wpdb->insert_id;
		}

		$mailing = self::find( $id );
		if ( null === $mailing ) {
			return self::NOT_FOUND;
		}
		if ( ! self::editable( $mailing ) ) {
			return self::NOT_DRAFT;
		}
		$data = [ 'category' => $category ];
		if ( $mailing['intro'] !== $intro || implode( ',', $mailing['promotion_ids'] ) !== $ids ) {
			$data += [
				'intro'         => $intro,
				'promotion_ids' => $ids,
				'test_sent_at'  => null,
				'status'        => self::DRAFT,
			];
		}
		$wpdb->update(
			Pneukarnik_DB::mailings_table(),
			$data,
			[
				'id'     => $id,
				'status' => $mailing['status'],
			]
		);
		if ( isset( $data['status'] ) ) {
			self::unschedule( $id );
		}
		return $id;
	}

	/**
	 * Rozepsanou nebo naplánovanou jde upravit, otestovat, odeslat a naplánovat.
	 *
	 * @param array{status:string} $mailing
	 */
	public static function editable( array $mailing ): bool {
		return in_array( $mailing['status'], [ self::DRAFT, self::SCHEDULED ], true );
	}

	/**
	 * @return Mailing|null
	 */
	public static function find( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', Pneukarnik_DB::mailings_table(), $id ), ARRAY_A );
		return $row ? self::from_row( $row ) : null;
	}

	/**
	 * Všechny Rozesílky, nejnovější první.
	 *
	 * @return list<Mailing>
	 */
	public static function all(): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC', Pneukarnik_DB::mailings_table() ), ARRAY_A );
		return array_map( [ self::class, 'from_row' ], $rows ?: [] );
	}

	/**
	 * Smaže rozepsanou Rozesílku. Odeslanou nebo odesílanou ne.
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		return 1 === (int) $wpdb->delete(
			Pneukarnik_DB::mailings_table(),
			[
				'id'     => $id,
				'status' => self::DRAFT,
			]
		);
	}

	/**
	 * Akce Rozesílky, které ještě existují a jsou zveřejněné, v pořadí výběru.
	 *
	 * @param array{promotion_ids:list<int>} $mailing
	 * @return list<Pneukarnik_Promotion>
	 */
	public static function mailing_promotions( array $mailing ): array {
		$promotions = [];
		foreach ( $mailing['promotion_ids'] as $id ) {
			$promotion = Pneukarnik_Promotion::find( $id );
			if ( $promotion && 'publish' === $promotion->status && $promotion->service() ) {
				$promotions[] = $promotion;
			}
		}
		return $promotions;
	}

	/**
	 * Akce, se kterými by Rozesílka odešla teď: z mailing_promotions() ty, které ještě neskončily.
	 *
	 * @param array{promotion_ids:list<int>} $mailing
	 * @return list<Pneukarnik_Promotion>
	 */
	public static function valid_promotions( array $mailing ): array {
		$today = Pneukarnik_Clock::today()->format( 'Y-m-d' );
		return array_values( array_filter( self::mailing_promotions( $mailing ), static fn( Pneukarnik_Promotion $promotion ): bool => $promotion->valid_to >= $today ) );
	}

	/**
	 * Kolika e‑mailům by Rozesílka teď odešla, všem (prázdná Kategorie), nebo Zákazníkům Kategorie.
	 */
	public static function audience( string $category = '' ): int {
		global $wpdb;
		[ $where, $args ] = self::audience_where( $category );
		// audience_where() je pevný fragment s placeholdery.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(DISTINCT s.email) FROM %i s WHERE ' . $where, Pneukarnik_DB::subscriptions_table(), ...$args )
		);
		// phpcs:enable
	}

	/**
	 * Kdy začala odcházet poslední Rozesílka, null = ještě žádná.
	 */
	public static function last_sent(): ?\DateTimeImmutable {
		global $wpdb;
		$started = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT MAX(started_at) FROM %i WHERE status IN (%s, %s)',
				Pneukarnik_DB::mailings_table(),
				self::SENDING,
				self::SENT
			)
		);
		return null === $started ? null : Pneukarnik_Clock::at( (string) $started );
	}

	/**
	 * Zkušební Rozesílka na e‑mail Provozovatele z Nastavení, nikam jinam. Zapamatuje si ji, aby šla
	 * Rozesílka odeslat.
	 *
	 * @return string|null Adresa, kam odešla, null = Rozesílka není rozepsaná nebo nemá Akce,
	 *                     Provozovatel nemá e‑mail nebo odeslání selhalo.
	 */
	public static function send_test( int $id ): ?string {
		$to      = Pneukarnik_Contact::email();
		$mailing = self::find( $id );
		if ( '' === $to || null === $mailing || ! self::editable( $mailing ) ) {
			return null;
		}
		$promotions = self::valid_promotions( $mailing );
		if ( ! $promotions ) {
			return null;
		}
		$email = self::email( $mailing, $promotions, home_url( '/odhlaseni/' ), false, __( '[Zkouška] ', 'pneukarnik-booking' ) )
			->paragraph( __( 'Toto je zkušební Rozesílka pro Provozovatele. Zákazníci dostanou vlastní odkaz na nastavení e‑mailů, ten tady nic nenastaví.', 'pneukarnik-booking' ) );
		if ( ! $email->send( $to ) ) {
			return null;
		}
		global $wpdb;
		$wpdb->update( Pneukarnik_DB::mailings_table(), [ 'test_sent_at' => Pneukarnik_Clock::now()->format( 'Y-m-d H:i:s' ) ], [ 'id' => $id ] );
		return $to;
	}

	/**
	 * Odešle Rozesílku hned (i naplánovanou): první dávka odejde v nejbližším běhu plánovaných úloh,
	 * další po hodinách.
	 *
	 * @return string DONE, NOT_FOUND, NOT_DRAFT, NO_PROMOTIONS nebo NOT_TESTED.
	 */
	public static function send( int $id ): string {
		$mailing = self::find( $id );
		$ready   = self::ready( $mailing );
		if ( self::DONE !== $ready ) {
			return $ready;
		}
		global $wpdb;
		$started = (int) $wpdb->update(
			Pneukarnik_DB::mailings_table(),
			[
				'status'     => self::SENDING,
				'started_at' => Pneukarnik_Clock::now()->format( 'Y-m-d H:i:s' ),
			],
			[
				'id'     => $id,
				'status' => $mailing['status'] ?? '',
			]
		);
		if ( 1 !== $started ) {
			return self::NOT_DRAFT;
		}
		self::unschedule( $id );
		wp_schedule_single_event( Pneukarnik_Clock::now()->getTimestamp(), self::CRON_HOOK );
		return self::DONE;
	}

	/**
	 * Naplánuje Rozesílku (i už naplánovanou na jiný čas). Odejde v $at, nejpozději v nejbližším
	 * hodinovém běhu po něm.
	 *
	 * @return string DONE, NOT_FOUND, NOT_DRAFT, NO_PROMOTIONS, NOT_TESTED nebo NOT_FUTURE.
	 */
	public static function schedule_send( int $id, \DateTimeImmutable $at ): string {
		$mailing = self::find( $id );
		$ready   = self::ready( $mailing );
		if ( self::DONE !== $ready ) {
			return $ready;
		}
		if ( $at <= Pneukarnik_Clock::now() ) {
			return self::NOT_FUTURE;
		}
		global $wpdb;
		$wpdb->update(
			Pneukarnik_DB::mailings_table(),
			[
				'status'       => self::SCHEDULED,
				'scheduled_at' => $at->setTimezone( Pneukarnik_Clock::timezone() )->format( 'Y-m-d H:i:s' ),
			],
			[
				'id'     => $id,
				'status' => $mailing['status'] ?? '',
			]
		);
		if ( self::SCHEDULED !== ( self::find( $id )['status'] ?? null ) ) {
			return self::NOT_DRAFT; // Mezitím ji někdo odeslal.
		}
		self::unschedule( $id );
		wp_schedule_single_event( $at->getTimestamp(), self::CRON_HOOK, [ $id ] );
		return self::DONE;
	}

	/**
	 * Zruší naplánovanou Rozesílku, neodejde. Rozepsanou, odesílanou ani odeslanou ne.
	 */
	public static function cancel( int $id ): bool {
		global $wpdb;
		$cancelled = 1 === (int) $wpdb->update(
			Pneukarnik_DB::mailings_table(),
			[
				'status'      => self::CANCELLED,
				'finished_at' => Pneukarnik_Clock::now()->format( 'Y-m-d H:i:s' ),
			],
			[
				'id'     => $id,
				'status' => self::SCHEDULED,
			]
		);
		if ( $cancelled ) {
			self::unschedule( $id );
		}
		return $cancelled;
	}

	/**
	 * Spustí naplánované Rozesílky, jejichž čas nastal, a pošle další dávku odesílaných (plánovaná úloha).
	 */
	public static function send_due(): void {
		self::start_scheduled();
		$budget = self::BATCH_SIZE;
		foreach ( self::with_status( self::SENDING ) as $mailing ) {
			if ( $budget <= 0 ) {
				return;
			}
			$promotions = self::valid_promotions( $mailing );
			if ( ! $promotions ) {
				// Všechny Akce mezitím skončily nebo zmizely.
				self::finish( $mailing['id'], $mailing['sent_count'] > 0 ? self::SENT : self::NOT_SENT );
				continue;
			}
			$recipients = self::recipients( $mailing['id'], $mailing['category'], $budget );
			$failed     = false;
			foreach ( $recipients as $recipient ) {
				if ( ! self::claim( $mailing['id'], $recipient['email'] ) ) {
					continue; // Mezitím ji poslalo souběžné spuštění.
				}
				$settings = Pneukarnik_Subscriptions::settings_url( $recipient['email'] );
				$email    = self::email( $mailing, $promotions, $settings, $recipient['consented'] );
				if ( $email->send( $recipient['email'], Pneukarnik_Contact::email(), Pneukarnik_Subscriptions::unsubscribe_headers( $settings ) ) ) {
					self::count_sent( $mailing['id'] );
				} else {
					self::release( $mailing['id'], $recipient['email'] );
					$failed = true;
				}
			}
			if ( count( $recipients ) < $budget && ! $failed ) {
				self::finish( $mailing['id'], self::SENT );
			}
			$budget -= count( $recipients );
		}
	}

	/**
	 * Náhled e‑mailu Rozesílky (HTML), jak ho dostane Zákazník s nárokem po návštěvě. U rozepsané
	 * a naplánované jen s Akcemi, které ještě platí.
	 *
	 * @param array{intro:string,promotion_ids:list<int>,status:string} $mailing
	 */
	public static function preview( array $mailing ): string {
		$promotions = self::editable( $mailing ) ? self::valid_promotions( $mailing ) : self::mailing_promotions( $mailing );
		return self::email( $mailing, $promotions, home_url( '/odhlaseni/' ), false )->html();
	}

	/**
	 * Smaže, komu Rozesílky odešly (výmaz osobních údajů v nástrojích WordPressu).
	 */
	public static function erase( string $email ): int {
		global $wpdb;
		return (int) $wpdb->delete( Pneukarnik_DB::mailing_recipients_table(), [ 'email' => Pneukarnik_Subscriptions::normalize( $email ) ] );
	}

	/**
	 * @param array{intro:string}        $mailing
	 * @param list<Pneukarnik_Promotion> $promotions Aspoň jedna, každá se Službou.
	 * @param bool                       $consented  Výslovný souhlas, jinak nárok po návštěvě.
	 */
	private static function email( array $mailing, array $promotions, string $settings_url, bool $consented, string $subject_prefix = '' ): Pneukarnik_Email {
		$services = [];
		foreach ( $promotions as $promotion ) {
			$services[ $promotion->service_id ] = (string) $promotion->service()?->title;
		}
		$phone = Pneukarnik_Contact::phone();

		/* translators: %s: názvy Služeb s Akcí oddělené čárkou */
		$email = ( new Pneukarnik_Email( $subject_prefix . sprintf( __( 'Akce: %s', 'pneukarnik-booking' ), implode( ', ', $services ) ) ) )
			->heading( __( 'Akce pro vás', 'pneukarnik-booking' ) )
			->paragraph( $mailing['intro'] );
		foreach ( $promotions as $promotion ) {
			$service = $promotion->service();
			if ( null === $service ) {
				continue;
			}
			$email->details(
				[
					__( 'Služba', 'pneukarnik-booking' ) => $service->title,
					__( 'Akce', 'pneukarnik-booking' )   => $promotion->title,
					__( 'Akční cena', 'pneukarnik-booking' ) => self::price( $promotion, $service ),
					__( 'Platí', 'pneukarnik-booking' )  => $promotion->validity_label(),
				]
			)
				->paragraph( $promotion->description );
			if ( $service->bookable ) {
				$email->button( __( 'Rezervovat', 'pneukarnik-booking' ), $service->booking_url() );
			} elseif ( '' !== $phone ) {
				/* translators: %s: telefon Provozovatele */
				$email->paragraph( sprintf( __( 'Tuto službu objednáváme jen telefonicky: %s.', 'pneukarnik-booking' ), $phone ) );
			}
		}
		return $email
			->signature( Pneukarnik_Notifications::text( 'signature' ) )
			->footer(
				$consented
					? __( 'Tento e‑mail dostáváte, protože jste souhlasili se zasíláním nabídek.', 'pneukarnik-booking' )
					: __( 'Tento e‑mail dostáváte, protože jste u nás byli a při online rezervaci jste e‑maily s nabídkami a připomínkami neodmítli.', 'pneukarnik-booking' ),
				__( 'Nastavit, co vám posíláme', 'pneukarnik-booking' ),
				$settings_url
			);
	}

	/**
	 * Akční cena, s běžnou cenou Služby, když nějakou má („990 Kč, běžně od 1 500 Kč“).
	 * Null u Akce bez ceny (výhodu nese její název).
	 */
	private static function price( Pneukarnik_Promotion $promotion, Pneukarnik_Service $service ): ?string {
		if ( null === $promotion->price ) {
			return null;
		}
		$price = self::amount( $promotion->price );
		if ( $service->price_by_vehicle || null === $service->price ) {
			return $price;
		}
		$regular = self::amount( $service->price );
		/* translators: %s: částka */
		$regular = $service->price_from ? sprintf( __( 'od %s', 'pneukarnik-booking' ), $regular ) : $regular;
		/* translators: 1: akční cena, 2: běžná cena */
		return sprintf( __( '%1$s, běžně %2$s', 'pneukarnik-booking' ), $price, $regular );
	}

	/** Částka v Kč, např. „1 200 Kč“ (s nezlomitelnými mezerami). */
	private static function amount( int $amount ): string {
		return number_format( $amount, 0, ',', "\u{00A0}" ) . "\u{00A0}Kč";
	}

	/**
	 * Proč Rozesílka teď nejde odeslat ani naplánovat, DONE = jde.
	 *
	 * @param Mailing|null $mailing
	 */
	private static function ready( ?array $mailing ): string {
		return match ( true ) {
			null === $mailing                    => self::NOT_FOUND,
			! self::editable( $mailing )         => self::NOT_DRAFT,
			! self::valid_promotions( $mailing ) => self::NO_PROMOTIONS,
			null === $mailing['test_sent_at']    => self::NOT_TESTED,
			default                              => self::DONE,
		};
	}

	/**
	 * Naplánované Rozesílky, jejichž čas nastal, začnou odcházet.
	 */
	private static function start_scheduled(): void {
		global $wpdb;
		$now = Pneukarnik_Clock::now();
		foreach ( self::with_status( self::SCHEDULED ) as $mailing ) {
			if ( Pneukarnik_Clock::at( (string) $mailing['scheduled_at'] ) > $now ) {
				continue;
			}
			$wpdb->update(
				Pneukarnik_DB::mailings_table(),
				[
					'status'     => self::SENDING,
					'started_at' => $now->format( 'Y-m-d H:i:s' ),
				],
				[
					'id'     => $mailing['id'],
					'status' => self::SCHEDULED,
				]
			);
		}
	}

	/**
	 * Zruší naplánované spuštění Rozesílky (hodinová úloha zůstane).
	 */
	private static function unschedule( int $id ): void {
		wp_clear_scheduled_hook( self::CRON_HOOK, [ $id ] );
	}

	/**
	 * Rozesílky ve stavu $status, nejstarší první.
	 *
	 * @return list<Mailing>
	 */
	private static function with_status( string $status ): array {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE status = %s ORDER BY id', Pneukarnik_DB::mailings_table(), $status ), ARRAY_A );
		return array_map( [ self::class, 'from_row' ], $rows ?: [] );
	}

	/**
	 * Podmínka nad Nabídkami a připomínkami (alias s) pro e‑maily, které smějí dostávat Akce (i ze
	 * starého souhlasu), u Kategorie jen pro Zákazníky s nezrušenou Rezervací Služby té Kategorie.
	 *
	 * @return array{0:string,1:list<string>} SQL s placeholdery a jejich hodnoty.
	 */
	private static function audience_where( string $category ): array {
		global $wpdb;
		$where = 's.purpose IN (%s, %s) AND ' . Pneukarnik_Subscriptions::RECEIVES_SQL;
		$args  = [ Pneukarnik_Subscriptions::PROMOTIONS, Pneukarnik_Subscriptions::LEGACY, ...Pneukarnik_Subscriptions::receives_args() ];
		if ( '' !== $category ) {
			$where .= ' AND EXISTS (SELECT 1 FROM %i cb JOIN %i cs ON cs.booking_id = cb.id JOIN %i cm ON cm.post_id = cs.service_id AND cm.meta_key = %s
				WHERE cb.customer_email = s.email AND cb.status = %s AND cm.meta_value = %s)';
			array_push( $args, Pneukarnik_DB::bookings_table(), Pneukarnik_DB::booking_services_table(), $wpdb->postmeta, '_service_category', Pneukarnik_Booking::STATUS_CONFIRMED, $category );
		}
		return [ $where, $args ];
	}

	/**
	 * E‑maily, kterým Rozesílka jde (audience_where()) a ještě jim neodešla, každý jednou.
	 *
	 * @return list<array{email:string,consented:bool}>
	 */
	private static function recipients( int $mailing_id, string $category, int $limit ): array {
		global $wpdb;
		[ $where, $args ] = self::audience_where( $category );
		// audience_where() je pevný fragment s placeholdery.
		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT s.email, MAX(s.consented_at IS NOT NULL) AS consented FROM %i s
				 WHERE ' . $where . '
				   AND NOT EXISTS (SELECT 1 FROM %i r WHERE r.mailing_id = %d AND r.email = s.email)
				 GROUP BY s.email ORDER BY MIN(s.id) LIMIT %d',
				Pneukarnik_DB::subscriptions_table(),
				...[
					...$args,
					Pneukarnik_DB::mailing_recipients_table(),
					$mailing_id,
					$limit,
				]
			),
			ARRAY_A
		);
		// phpcs:enable
		return array_map(
			static fn( array $row ): array => [
				'email'     => (string) $row['email'],
				'consented' => (bool) $row['consented'],
			],
			$rows ?: []
		);
	}

	/**
	 * Zapíše e‑mail k Rozesílce. False, když už ho zapsalo jiné spuštění.
	 */
	private static function claim( int $mailing_id, string $email ): bool {
		global $wpdb;
		return 1 === (int) $wpdb->query(
			$wpdb->prepare(
				'INSERT IGNORE INTO %i (mailing_id, email, sent_at) VALUES (%d, %s, %s)',
				Pneukarnik_DB::mailing_recipients_table(),
				$mailing_id,
				$email,
				Pneukarnik_Clock::now()->format( 'Y-m-d H:i:s' )
			)
		);
	}

	private static function release( int $mailing_id, string $email ): void {
		global $wpdb;
		$wpdb->delete(
			Pneukarnik_DB::mailing_recipients_table(),
			[
				'mailing_id' => $mailing_id,
				'email'      => $email,
			]
		);
	}

	private static function count_sent( int $mailing_id ): void {
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET sent_count = sent_count + 1 WHERE id = %d', Pneukarnik_DB::mailings_table(), $mailing_id ) );
	}

	/**
	 * Odesílaná Rozesílka skončí jako odeslaná (SENT), nebo neodeslaná (NOT_SENT).
	 */
	private static function finish( int $mailing_id, string $status ): void {
		global $wpdb;
		$wpdb->update(
			Pneukarnik_DB::mailings_table(),
			[
				'status'      => $status,
				'finished_at' => Pneukarnik_Clock::now()->format( 'Y-m-d H:i:s' ),
			],
			[
				'id'     => $mailing_id,
				'status' => self::SENDING,
			]
		);
	}

	/**
	 * @param array<string, mixed> $row
	 * @return Mailing
	 */
	private static function from_row( array $row ): array {
		$nullable = static fn( string $key ): ?string => null === $row[ $key ] ? null : (string) $row[ $key ];
		return [
			'id'            => (int) $row['id'],
			'intro'         => (string) $row['intro'],
			'promotion_ids' => array_values( array_filter( array_map( 'intval', explode( ',', (string) $row['promotion_ids'] ) ) ) ),
			'category'      => (string) $row['category'],
			'status'        => (string) $row['status'],
			'test_sent_at'  => $nullable( 'test_sent_at' ),
			'scheduled_at'  => $nullable( 'scheduled_at' ),
			'created_at'    => (string) $row['created_at'],
			'started_at'    => $nullable( 'started_at' ),
			'finished_at'   => $nullable( 'finished_at' ),
			'sent_count'    => (int) $row['sent_count'],
		];
	}
}
