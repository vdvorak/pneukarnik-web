<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * E‑mail poskládaný z bloků. Z týchž bloků vznikne HTML i textová alternativa,
 * všechny texty se v HTML escapují (mohou pocházet od Zákazníka nebo z Nastavení).
 *
 * HTML podle šablony e‑mailů z návrhu: šířka 600 px, rozvržení tabulkami a inline styly,
 * protože poštovní klienti styly v hlavičce ani vlastní písma spolehlivě neumí.
 */
final class Pneukarnik_Email {

	public const BUTTON_ACCENT = 'accent';
	public const BUTTON_DARK   = 'dark';
	public const BUTTON_DANGER = 'danger';

	private const FONT_HEADING = 'Montserrat,Arial,Helvetica,sans-serif';
	private const FONT_BODY    = "'Nunito Sans',Arial,Helvetica,sans-serif";
	private const COLOR_TEXT   = '#232323';
	private const COLOR_MUTED  = '#555555';
	private const COLOR_LINK   = '#1F2933';
	private const COLOR_ACCENT = '#F5A400';
	private const COLOR_BORDER = '#E3E7EB';
	private const COLOR_PANEL  = '#F5F7F9';
	private const COLOR_DANGER = '#C0392B';

	/** @var list<array{type:string,text?:string,rows?:array<string,string|list<string>>,items?:list<string>,url?:string,label?:string,variant?:string}> */
	private array $blocks = [];

	/** @var list<array{filename:string,content:string,type:string}> */
	private array $attachments = [];

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

	/** Hlavní tlačítko. Odvolání akce (Zrušit rezervaci) jako BUTTON_DANGER, pro Provozovatele BUTTON_DARK. */
	public function button( string $label, string $url, string $variant = self::BUTTON_ACCENT ): self {
		$this->blocks[] = [
			'type'    => 'button',
			'text'    => $label,
			'url'     => $url,
			'variant' => $variant,
		];
		return $this;
	}

	/** Podpis s kontakty na konci e‑mailu, konce řádků zůstanou. */
	public function signature( string $text ): self {
		if ( '' !== trim( $text ) ) {
			$this->blocks[] = [
				'type' => 'signature',
				'text' => trim( $text ),
			];
		}
		return $this;
	}

	/** Patička pod podpisem: proč e‑mail chodí a odkaz, jak ho přestat dostávat. */
	public function footer( string $text, string $label, string $url ): self {
		$this->blocks[] = [
			'type'  => 'footer',
			'text'  => $text,
			'label' => $label,
			'url'   => $url,
		];
		return $this;
	}

	/** Příloha vytvořená z obsahu (např. rezervace.ics). HTML ani text e‑mailu nemění. */
	public function attach( string $filename, string $content, string $type ): self {
		$this->attachments[] = [
			'filename' => $filename,
			'content'  => $content,
			'type'     => $type,
		];
		return $this;
	}

	public function subject(): string {
		return $this->subject;
	}

	public function html(): string {
		$rows = [ self::html_header() ];
		foreach ( $this->blocks as $block ) {
			$rows[] = match ( $block['type'] ) {
				'heading'   => self::row( '8px 32px 16px', self::FONT_HEADING, 'font-size:26px;line-height:32px;font-weight:900;text-transform:uppercase;color:' . self::COLOR_TEXT, esc_html( $block['text'] ?? '' ) ),
				'paragraph' => self::row( '0 32px 14px', self::FONT_BODY, 'font-size:16px;line-height:24px;color:' . self::COLOR_TEXT, nl2br( esc_html( $block['text'] ?? '' ) ) ),
				'details'   => self::row( '4px 32px 18px', '', '', self::html_details( $block['rows'] ?? [] ) ),
				'items'     => self::row( '10px 32px 8px', self::FONT_HEADING, 'font-size:15px;line-height:20px;font-weight:900;text-transform:uppercase;color:' . self::COLOR_TEXT, esc_html( $block['text'] ?? '' ) )
					. self::row( '0 32px 14px', '', '', self::html_checklist( $block['items'] ?? [] ) ),
				'link'      => self::row( '0 32px 14px', self::FONT_BODY, 'font-size:15px;line-height:22px', self::html_link( $block['text'] ?? '', $block['url'] ?? '' ) ),
				'button'    => self::row( '10px 32px 18px', '', '', self::html_button( $block['text'] ?? '', $block['url'] ?? '', $block['variant'] ?? self::BUTTON_ACCENT ) ),
				'signature' => self::row( '12px 32px 28px', self::FONT_BODY, 'font-size:15px;line-height:22px;color:' . self::COLOR_MUTED, nl2br( esc_html( $block['text'] ?? '' ) ) ),
				'footer'    => self::row( '16px 32px', self::FONT_BODY, 'font-size:13px;line-height:19px;color:' . self::COLOR_MUTED . ';background:' . self::COLOR_PANEL, esc_html( $block['text'] ?? '' ) . ' ' . self::html_link( $block['label'] ?? '', $block['url'] ?? '' ) ),
				default     => '',
			};
		}
		$last = end( $this->blocks );
		if ( ! $last || ! in_array( $last['type'], [ 'signature', 'footer' ], true ) ) {
			$rows[] = '<tr><td style="padding:0 0 12px"></td></tr>';
		}

		return '<!DOCTYPE html><html lang="cs"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
			. '<title>' . esc_html( $this->subject ) . '</title></head>'
			. '<body style="margin:0;padding:0;background:' . self::COLOR_BORDER . '">'
			. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . self::COLOR_BORDER . '"><tr><td align="center" style="padding:24px 8px">'
			. '<table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background:#ffffff;border-collapse:collapse">'
			. implode( "\n", array_filter( $rows ) )
			. '</table></td></tr></table></body></html>';
	}

	public function text(): string {
		$parts = [];
		foreach ( $this->blocks as $block ) {
			$parts[] = match ( $block['type'] ) {
				'heading', 'paragraph', 'signature' => $block['text'] ?? '',
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
				'footer'               => ( $block['text'] ?? '' ) . "\n" . ( $block['label'] ?? '' ) . ': ' . ( $block['url'] ?? '' ),
				default                => '',
			};
		}
		return implode( "\n\n", $parts ) . "\n";
	}

	/**
	 * Odešle e‑mail jako HTML s textovou alternativou a přílohami.
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

		$text        = $this->text();
		$attachments = $this->attachments;
		// wp_mail neumí textovou alternativu ani přílohy bez souboru na disku, PHPMailer ano.
		// AltBody i přílohy wp_mail před každým e‑mailem vymaže.
		$alt_body = static function ( PHPMailer\PHPMailer\PHPMailer $mailer ) use ( $text, $attachments ): void {
			$mailer->AltBody = $text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- vlastnost PHPMaileru.
			foreach ( $attachments as $attachment ) {
				$mailer->addStringAttachment( $attachment['content'], $attachment['filename'], PHPMailer\PHPMailer\PHPMailer::ENCODING_BASE64, $attachment['type'] );
			}
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

	/** Hlavička s logem a názvem webu. Logo z vlastní domény, bez obrázků zůstane alt text a název. */
	private static function html_header(): string {
		$name = (string) get_bloginfo( 'name' );
		$name = '' !== $name ? $name : Pneukarnik_Contact::company();
		return '<tr><td style="padding:24px 32px 12px;border-top:4px solid ' . self::COLOR_ACCENT . '">'
			. '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
			. '<td style="padding-right:12px"><img src="' . esc_url( PNEUKARNIK_PLUGIN_URL . 'assets/email-logo.png' ) . '" width="48" height="48" alt="' . esc_attr( $name ) . '" style="display:block;border:0"></td>'
			. '<td style="font-family:' . self::FONT_HEADING . ';font-size:17px;line-height:20px;font-weight:900;text-transform:uppercase;color:' . self::COLOR_TEXT . '">' . esc_html( $name ) . '</td>'
			. '</tr></table></td></tr>';
	}

	/** Řádek e‑mailu: buňka s odsazením, písmem a styly, obsah už escapovaný. */
	private static function row( string $padding, string $font, string $style, string $html ): string {
		$css = 'padding:' . $padding . ( '' !== $font ? ';font-family:' . $font : '' ) . ( '' !== $style ? ';' . $style : '' );
		return '<tr><td style="' . $css . '">' . $html . '</td></tr>';
	}

	private static function html_link( string $label, string $url ): string {
		return '<a href="' . esc_url( $url ) . '" style="color:' . self::COLOR_LINK . ';font-weight:700;text-decoration:underline">' . esc_html( $label ) . '</a>';
	}

	/** Tlačítko jako tabulka s barvou pozadí, aby mělo plochu i v Outlooku. */
	private static function html_button( string $label, string $url, string $variant ): string {
		[ $cell, $color ] = match ( $variant ) {
			self::BUTTON_DARK   => [ 'background:' . self::COLOR_LINK, '#ffffff' ],
			self::BUTTON_DANGER => [ 'background:#ffffff;border:2px solid ' . self::COLOR_DANGER, self::COLOR_DANGER ],
			default             => [ 'background:' . self::COLOR_ACCENT, self::COLOR_TEXT ],
		};
		return '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr><td style="' . $cell . ';border-radius:999px">'
			. '<a href="' . esc_url( $url ) . '" style="display:inline-block;padding:14px 28px;font-family:' . self::FONT_BODY . ';font-size:16px;line-height:20px;font-weight:800;color:' . $color . ';text-decoration:none">'
			. esc_html( $label ) . '</a></td></tr></table>';
	}

	/**
	 * @param list<string> $items
	 */
	private static function html_checklist( array $items ): string {
		$cell = 'font-family:' . self::FONT_BODY . ';font-size:16px;line-height:24px;';
		$html = '<table role="presentation" cellpadding="0" cellspacing="0" border="0">';
		foreach ( $items as $item ) {
			$html .= '<tr><td style="padding:2px 10px 2px 0;' . $cell . 'font-weight:900;color:' . self::COLOR_ACCENT . ';vertical-align:top">✓</td>'
				. '<td style="padding:2px 0;' . $cell . 'color:' . self::COLOR_TEXT . '">' . esc_html( $item ) . '</td></tr>';
		}
		return $html . '</table>';
	}

	/**
	 * @param array<string, string|list<string>> $rows
	 */
	private static function html_details( array $rows ): string {
		$cell  = 'padding:10px 16px;font-family:' . self::FONT_BODY . ';font-size:15px;line-height:22px;';
		$html  = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="border-collapse:collapse;background:' . self::COLOR_PANEL . ';border-radius:12px">';
		$first = true;
		foreach ( $rows as $label => $value ) {
			$value  = is_array( $value ) ? implode( '<br>', array_map( 'esc_html', $value ) ) : nl2br( esc_html( $value ) );
			$border = $first ? '' : 'border-top:1px solid ' . self::COLOR_BORDER . ';';
			$html  .= '<tr><td style="' . $cell . $border . 'color:' . self::COLOR_MUTED . ';width:150px;vertical-align:top">' . esc_html( (string) $label ) . '</td>'
				. '<td style="' . $cell . $border . 'font-weight:700;color:' . self::COLOR_TEXT . ';vertical-align:top">' . $value . '</td></tr>';
			$first  = false;
		}
		return $html . '</table>';
	}
}
