<?php
/**
 * Jen lokální vývoj: e‑maily z WordPressu jdou do Mailpitu (http://localhost:8025), nikam ven.
 * Připojeno jen do kontejneru `wordpress` (docker-compose.yml), do testů ani na hosting se nedostane.
 */

add_action(
	'phpmailer_init',
	static function ( PHPMailer\PHPMailer\PHPMailer $mailer ): void {
		$mailer->isSMTP();
		$mailer->Host        = 'mailpit';
		$mailer->Port        = 1025;
		$mailer->SMTPAuth    = false;
		$mailer->SMTPAutoTLS = false;
	}
);

// Lokální web běží na localhost a PHPMailer adresu odesílatele „…@localhost“ odmítne.
add_filter(
	'wp_mail_from',
	static fn( string $from ): string => str_ends_with( $from, '@localhost' ) ? substr( $from, 0, -strlen( 'localhost' ) ) . 'pneukarnik.test' : $from
);
