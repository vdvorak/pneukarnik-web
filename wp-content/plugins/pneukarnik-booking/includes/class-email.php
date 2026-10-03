<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * E‑mail poskládaný z bloků. Z týchž bloků vznikne HTML i textová alternativa,
 * všechny texty se v HTML escapují (mohou pocházet od Zákazníka nebo z Nastavení).
 */
final class Pneukarnik_Email {

	/** @var list<array{type:string,text?:string,rows?:array<string,string|list<string>>,items?:list<string>,url?:string}> */
	private array $blocks = [];

	public function __construct( private readonly string $subject ) {}

	public function heading( string $text ): self {
		$this->blocks[] = [
			'type' => 'heading',
			'text' => $text,
		];
		return $this;
	}

	/** Odstavec, konce řádků zůstanou. Prázdný text se vynechá. */
	public function paragraph( string $text ): self {
		if ( '' !== trim( $text ) ) {
			$this->blocks[] = [
				'type' => 'paragraph',
				'text' => trim( $text ),
			];
		}
		return $this;
	}

	/**
	 * Tabulka údajů. Prázdné hodnoty se vynechají, seznam se vypíše po řádcích.
	 *
	 * @param array<string, string|list<string>|null> $rows Popisek => hodnota.
	 */
	public function details( array $rows ): self {
		$this->blocks[] = [
			'type' => 'details',
			'rows' => array_filter( $rows, static fn( $value ): bool => null !== $value && '' !== $value && [] !== $value ),
		];
		return $this;
	}

	/**
	 * Seznam s nadpisem. Prázdný seznam se vynechá.
	 *
	 * @param list<string> $items
	 */
	public function items( string $title, array $items ): self {
		if ( $items ) {
			$this->blocks[] = [
				'type'  => 'items',
				'text'  => $title,
				'items' => $items,
			];
		}
		return $this;
	}

	/** Méně výrazný odkaz než tlačítko. */
	public function link( string $label, string $url ): self {
		$this->blocks[] = [
			'type' => 'link',
			'text' => $label,
			'url'  => $url,
		];
		return $this;
	}

	public function button( string $label, string $url ): self {
		$this->blocks[] = [
			'type' => 'button',
			'text' => $label,
			'url'  => $url,
		];
		return $this;
	}

	public function subject(): string {
		return $this->subject;
	}

	public function html(): string {
		$parts = [];
		foreach ( $this->blocks as $block ) {
			$parts[] = match ( $block['type'] ) {
				'heading'   => '<h1 style="font-size:20px;margin:0 0 16px">' . esc_html( $block['text'] ?? '' ) . '</h1>',
				'paragraph' => '<p style="margin:0 0 16px">' . nl2br( esc_html( $block['text'] ?? '' ) ) . '</p>',
				'details'   => self::html_details( $block['rows'] ?? [] ),
				'items'     => '<p style="margin:0 0 4px"><strong>' . esc_html( $block['text'] ?? '' ) . '</strong></p><ul style="margin:0 0 16px;padding-left:20px">'
					. implode( '', array_map( static fn( string $item ): string => '<li>' . esc_html( $item ) . '</li>', $block['items'] ?? [] ) ) . '</ul>',
				'link'      => '<p style="margin:0 0 16px"><a href="' . esc_url( $block['url'] ?? '' ) . '">' . esc_html( $block['text'] ?? '' ) . '</a></p>',
				'button'    => '<p style="margin:24px 0"><a href="' . esc_url( $block['url'] ?? '' ) . '" style="background:#1a1a1a;color:#ffffff;padding:10px 20px;text-decoration:none;border-radius:4px;display:inline-block">'
					. esc_html( $block['text'] ?? '' ) . '</a></p>',
				default     => '',
			};
		}
		return '<!DOCTYPE html><html lang="cs"><head><meta charset="UTF-8"><title>' . esc_html( $this->subject ) . '</title></head>'
			. '<body style="font-family:Arial,sans-serif;font-size:15px;line-height:1.5;color:#1a1a1a;max-width:600px;margin:0 auto;padding:24px">'
			. implode( "\n", $parts )
			. '</body></html>';
	}

	public function text(): string {
		$parts = [];
		foreach ( $this->blocks as $block ) {
			$parts[] = match ( $block['type'] ) {
				'heading', 'paragraph' => $block['text'] ?? '',
				'details'              => implode(
					"\n",
					array_map(
						static fn( string $label, string|array $value ): string => $label . ': ' . ( is_array( $value ) ? implode( ', ', $value ) : $value ),
						array_keys( $block['rows'] ?? [] ),
						$block['rows'] ?? []
					)
				),
				'items'                => ( $block['text'] ?? '' ) . ":\n" . implode( "\n", array_map( static fn( string $item ): string => '- ' . $item, $block['items'] ?? [] ) ),
				'button', 'link'       => ( $block['text'] ?? '' ) . ': ' . ( $block['url'] ?? '' ),
				default                => '',
			};
		}
		return implode( "\n\n", $parts ) . "\n";
	}

	/**
	 * Odešle e‑mail jako HTML s textovou alternativou.
	 *
	 * @param string       $reply_to Adresa pro odpověď, prázdná = bez Reply-To.
	 * @param list<string> $extra_headers Další hlavičky „Název: hodnota“ (např. List-Unsubscribe).
	 */
	public function send( string $to, string $reply_to = '', array $extra_headers = [] ): bool {
		$host    = wp_parse_url( home_url(), PHP_URL_HOST ) ?: 'localhost';
		$from    = (string) get_option( 'pneukarnik_noreply_email', 'noreply@' . $host );
		$name    = str_replace( [ '"', '<', '>', "\r", "\n" ], '', (string) get_bloginfo( 'name' ) );
		$headers = [
			'Content-Type: text/html; charset=UTF-8',
			sprintf( 'From: %s <%s>', $name, $from ),
		];
		if ( is_email( $reply_to ) ) {
			$headers[] = 'Reply-To: ' . $reply_to;
		}
		$headers = [ ...$headers, ...$extra_headers ];

		$text = $this->text();
		// wp_mail neumí textovou alternativu, PHPMailer ano. AltBody wp_mail před každým e‑mailem vymaže.
		$alt_body = static function ( PHPMailer\PHPMailer\PHPMailer $mailer ) use ( $text ): void {
			$mailer->AltBody = $text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- vlastnost PHPMaileru.
		};
		add_action( 'phpmailer_init', $alt_body );
		try {
			$sent = wp_mail( $to, $this->subject, $this->html(), $headers );
		} finally {
			remove_action( 'phpmailer_init', $alt_body );
		}

		if ( ! $sent ) {
			error_log( sprintf( '[pneukarnik] E‑mail „%s“ se nepodařilo odeslat.', $this->subject ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		}
		return $sent;
	}

	/**
	 * @param array<string, string|list<string>> $rows
	 */
	private static function html_details( array $rows ): string {
		$html = '<table style="border-collapse:collapse;margin:0 0 16px;width:100%">';
		foreach ( $rows as $label => $value ) {
			$value = is_array( $value ) ? implode( '<br>', array_map( 'esc_html', $value ) ) : nl2br( esc_html( $value ) );
			$html .= '<tr><td style="padding:6px 12px 6px 0;color:#555;vertical-align:top;white-space:nowrap">' . esc_html( (string) $label ) . '</td>'
				. '<td style="padding:6px 0;vertical-align:top">' . $value . '</td></tr>';
		}
		return $html . '</table>';
	}
}
