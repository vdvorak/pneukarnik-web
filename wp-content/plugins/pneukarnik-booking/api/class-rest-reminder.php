<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Připomínka přezutí (Pneukarnik_Reminder) a nastavení e‑mailů (Pneukarnik_Subscriptions).
 *
 * GET  /email-settings?key=…         nastavení e‑mailů podle klíče z odkazu: {email, reminder, promotions}
 * POST /email-settings {key, reminder, promotions}  uloží nastavení, vrátí nové
 * POST /email-settings {key, nothing: true}         „Neposílat nic“
 * POST /unsubscribe {token}          odkaz z Připomínky odeslané před stránkou nastavení, odvolá ji
 * POST /unsubscribe {email}          starý odkaz /cancel-subscription?email=…, jen starý odběr
 * GET  /admin/reminder               náhled: nejbližší Sezóna, od kdy se posílá, počet příjemců
 * POST /admin/reminder/test          zkušební Připomínka, jen na e‑mail Provozovatele z Nastavení
 *
 * Kódy: unsubscribe.done, unsubscribe.invalid_token (404), reminder.no_provozovatel_email (409),
 * reminder.send_failed (500).
 * Administrace: Pneukarnik_Access::can_manage().
 */
class Pneukarnik_Rest_Reminder {

	public function register_routes(): void {
		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/email-settings',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'email_settings' ],
					'permission_callback' => '__return_true',
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'save_email_settings' ],
					'permission_callback' => '__return_true',
				],
			]
		);
		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/unsubscribe',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'unsubscribe' ],
				'permission_callback' => '__return_true',
			]
		);
		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/admin/reminder',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'preview' ],
				'permission_callback' => [ Pneukarnik_Access::class, 'can_manage' ],
			]
		);
		register_rest_route(
			PNEUKARNIK_REST_NAMESPACE,
			'/admin/reminder/test',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'send_test' ],
				'permission_callback' => [ Pneukarnik_Access::class, 'can_manage' ],
			]
		);
	}

	public function email_settings( WP_REST_Request $request ): WP_REST_Response {
		return self::settings_response( $request->get_param( 'key' ) );
	}

	public function save_email_settings( WP_REST_Request $request ): WP_REST_Response {
		/** @var mixed $body Tělo může být i jiná JSON hodnota než objekt. */
		$body = $request->get_json_params();
		$body = is_array( $body ) ? $body : [];
		$key  = $body['key'] ?? null;
		if ( true === ( $body['nothing'] ?? null ) ) {
			Pneukarnik_Subscriptions::withdraw_everything( $key, 'nastaveni' );
		} else {
			Pneukarnik_Subscriptions::save_settings( $key, true === ( $body['reminder'] ?? null ), true === ( $body['promotions'] ?? null ) );
		}
		return self::settings_response( $key );
	}

	public function unsubscribe( WP_REST_Request $request ): WP_REST_Response {
		/** @var mixed $body Tělo může být i jiná JSON hodnota než objekt. */
		$body = $request->get_json_params();
		$body = is_array( $body ) ? $body : [];
		$code = array_key_exists( 'token', $body )
			? Pneukarnik_Subscriptions::withdraw_by_token( $body['token'] )
			: Pneukarnik_Subscriptions::withdraw_legacy( $body['email'] ?? null );
		return Pneukarnik_Subscriptions::DONE === $code
			? self::no_store( new WP_REST_Response( [ 'code' => $code ], 200 ) )
			: self::refusal( $code, 404 );
	}

	public function preview(): WP_REST_Response {
		return self::no_store( new WP_REST_Response( Pneukarnik_Reminder::preview(), 200 ) );
	}

	public function send_test(): WP_REST_Response {
		if ( '' === Pneukarnik_Contact::email() ) {
			return self::refusal( Pneukarnik_Reminder::NO_PROVOZOVATEL_EMAIL, 409 );
		}
		$sent_to = Pneukarnik_Reminder::send_test();
		if ( null === $sent_to ) {
			return self::refusal( Pneukarnik_Reminder::SEND_FAILED, 500 );
		}
		return self::no_store( new WP_REST_Response( [ 'sent_to' => $sent_to ], 200 ) );
	}

	private static function settings_response( mixed $key ): WP_REST_Response {
		$settings = Pneukarnik_Subscriptions::settings( $key );
		if ( null === $settings ) {
			return self::refusal( Pneukarnik_Subscriptions::INVALID_TOKEN, 404 );
		}
		unset( $settings['url'] );
		return self::no_store( new WP_REST_Response( $settings, 200 ) );
	}

	private static function refusal( string $code, int $status ): WP_REST_Response {
		return self::no_store(
			new WP_REST_Response(
				[
					'code'    => $code,
					'message' => $code,
					'data'    => [ 'status' => $status ],
				],
				$status
			)
		);
	}

	private static function no_store( WP_REST_Response $response ): WP_REST_Response {
		$response->header( 'Cache-Control', 'no-store' );
		return $response;
	}
}
