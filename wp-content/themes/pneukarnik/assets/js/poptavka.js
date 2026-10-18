// @ts-check
/**
 * Formulář Poptávky („Poptat pneumatiky“) v detailu Služby: odeslání na REST API pluginu a hlášky
 * u polí. Pravidla validace jsou na serveru, tady se jen zobrazují jejich výsledky.
 */

/**
 * @typedef {{ code: string, data?: { status: number, errors?: Record<string, string> } }} ApiError
 */

/** @type {Record<string, Record<string, string>>} */
const FIELD_MESSAGES = {
	size: { required: 'Vyplňte rozměr pneumatik.', invalid: 'Zadejte rozměr, například 205/55 R16.', too_long: 'Rozměr je příliš dlouhý.' },
	season: { required: 'Vyberte letní, nebo zimní.', invalid: 'Vyberte letní, nebo zimní.' },
	pieces: { required: 'Vyplňte počet kusů.', invalid: 'Zadejte počet kusů od 1 do 20.' },
	name: { required: 'Vyplňte jméno nebo firmu.', too_long: 'Jméno je příliš dlouhé.' },
	phone: { contact_required: 'Vyplňte telefon, nebo e‑mail.', invalid: 'Zadejte telefon, například 777 123 456.', too_long: 'Telefon je příliš dlouhý.' },
	email: { contact_required: 'Vyplňte telefon, nebo e‑mail.', invalid: 'Zkontrolujte e‑mail.', too_long: 'E‑mail je příliš dlouhý.' },
	note: { too_long: 'Poznámka je příliš dlouhá.' },
};

/** @param {string} text @param {string} [tone] */
function notice(text, tone = '') {
	return Object.assign(document.createElement('p'), { className: tone ? `notice notice--${tone}` : 'notice', textContent: text });
}

function init() {
	const form = /** @type {HTMLFormElement | null} */ (document.getElementById('poptavka-form'));
	if (!form) return;
	const zprava = /** @type {HTMLElement} */ (document.getElementById('poptavka-zprava'));
	const hotovo = /** @type {HTMLElement} */ (document.getElementById('poptavka-hotovo'));
	const submit = /** @type {HTMLButtonElement} */ (form.querySelector('button[type="submit"]'));
	const submitText = submit.textContent ?? '';
	const { api = '', nonce = '', phone = '' } = form.dataset;
	const call = phone ? ` Zavolejte nám prosím na ${phone}.` : '';
	/** @type {Record<string, string>} */
	const messages = {
		'inquiry.rate_limited': `Odeslali jste příliš mnoho poptávek.${call}`,
		'inquiry.disabled': `Poptávku teď online odeslat nejde.${call}`,
		network: `Poptávku se nepodařilo odeslat. Zkuste to znovu.${call}`,
	};

	/** Celková chyba nad tlačítkem, prázdný text ji schová. @param {string} text */
	const showMessage = (text) => {
		zprava.replaceChildren(...(text ? [notice(text, 'danger')] : []));
	};

	function clearErrors() {
		showMessage('');
		form?.querySelectorAll('.pole__chyba').forEach((element) => {
			element.textContent = '';
		});
		form?.querySelectorAll('[aria-invalid]').forEach((element) => element.removeAttribute('aria-invalid'));
	}

	/** @param {Record<string, string>} errors */
	function showFieldErrors(errors) {
		let first = /** @type {HTMLElement | null} */ (null);
		for (const [field, code] of Object.entries(errors)) {
			const target = document.getElementById(`chyba-pop-${field}`);
			if (target) target.textContent = FIELD_MESSAGES[field]?.[code] ?? 'Zkontrolujte toto pole.';
			const input = /** @type {HTMLInputElement | null} */ (form?.querySelector(`[name="${field}"]`) ?? null);
			input?.setAttribute('aria-invalid', 'true');
			first ??= input;
		}
		showMessage('Poptávku jsme neodeslali. Zkontrolujte prosím zvýrazněná pole.');
		first?.focus();
	}

	/** @param {boolean} sending */
	function setSending(sending) {
		submit.disabled = sending;
		submit.setAttribute('aria-busy', String(sending));
		submit.textContent = sending ? 'Odesílám poptávku…' : submitText;
	}

	/** @param {SubmitEvent} event */
	async function send(event) {
		event.preventDefault();
		if (!form) return;
		clearErrors();
		const values = new FormData(form);
		const body = Object.fromEntries(['size', 'season', 'pieces', 'name', 'phone', 'email', 'note', 'website'].map((field) => [field, String(values.get(field) ?? '')]));
		setSending(true);
		try {
			const response = await fetch(api, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-WP-Nonce': nonce },
				body: JSON.stringify(body),
			});
			if (response.status === 201) {
				form.hidden = true;
				hotovo.hidden = false;
				hotovo.focus();
				return;
			}
			/** @type {ApiError} */
			const error = await response.json();
			if (error.code === 'inquiry.invalid_fields' && error.data?.errors) {
				showFieldErrors(error.data.errors);
			} else {
				showMessage(messages[error.code] ?? messages.network);
			}
		} catch {
			showMessage(messages.network);
		} finally {
			setSending(false);
		}
	}

	form.addEventListener('submit', send);
	form.hidden = false;
}

init();
