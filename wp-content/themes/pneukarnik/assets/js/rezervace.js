// @ts-check
/**
 * Rezervační formulář: načte volné Termíny pro vybrané Služby a den a odešle Rezervaci na REST API pluginu.
 * Pravidla (mřížka, obsazenost, validace) jsou na serveru, tady se jen zobrazují jejich výsledky.
 */

/**
 * @typedef {{ enabled: boolean, disabled_message: string, services: {id: number, slug: string, name: string}[], max_services: number, selected: number, min_date: string, max_date: string, api: string, nonce: string, phone: string, privacy_url: string }} Config
 * @typedef {{ time_start: string, time_end: string }} Termin
 * @typedef {{ code: string, data?: { status: number, errors?: Record<string, string> } }} ApiError
 */

/** @type {Record<string, Record<string, string>>} */
const FIELD_MESSAGES = {
	service_ids: {
		required: 'Vyberte službu.',
		invalid: 'Vyberte službu.',
		duplicate: 'Každou službu vyberte jen jednou.',
		too_many: 'Vyberte méně služeb, ostatní napište do poznámky.',
	},
	date: { required: 'Vyberte den.', invalid: 'Vyberte den.' },
	time: { required: 'Vyberte termín.', invalid: 'Vyberte termín.' },
	name: { required: 'Vyplňte jméno nebo firmu.', too_long: 'Jméno je příliš dlouhé.' },
	phone: { required: 'Vyplňte telefon.', invalid: 'Zadejte telefon, například 777 123 456.' },
	email: { required: 'Vyplňte e‑mail.', invalid: 'Zkontrolujte e‑mail.', too_long: 'E‑mail je příliš dlouhý.' },
	plate: { required: 'Vyplňte SPZ.', invalid: 'SPZ může obsahovat jen písmena a číslice.' },
	vehicle: { too_long: 'Text je příliš dlouhý.' },
	note: { too_long: 'Poznámka je příliš dlouhá.' },
	consent_gdpr: { required: 'Bez souhlasu nemůžeme rezervaci přijmout.' },
};

/**
 * @param {string} phone
 * @returns {Record<string, string>}
 */
function errorMessages(phone) {
	const call = phone ? ` Zavolejte nám prosím na ${phone}.` : '';
	return {
		'booking.slot_taken': 'Vybraný termín si mezitím rezervoval někdo jiný. Vyberte prosím jiný.',
		'booking.slot_unavailable': 'Vybraný termín už není v nabídce. Vyberte prosím jiný.',
		'booking.service_not_bookable': `Tuto službu teď online objednat nejde.${call}`,
		'booking.seasonal_only': `V sezóně přezouvání jde online objednat jen přezutí a související služby.${call}`,
		'booking.service_not_found': 'Vybraná služba už není v nabídce.',
		'booking.rate_limited': `Odeslali jste příliš mnoho rezervací.${call}`,
		'booking.disabled': `Online rezervace jsou teď vypnuté.${call}`,
		'booking.busy': 'Systém je právě vytížený, zkuste to prosím za chvíli znovu.',
		network: `Rezervaci se nepodařilo odeslat. Zkuste to znovu.${call}`,
	};
}

/** @param {string} hhmm */
const humanTime = (hhmm) => hhmm.replace(/^0/, '');

/**
 * @param {HTMLElement} element
 * @param {string} text
 */
function setText(element, text) {
	element.textContent = text;
}

function init() {
	const configElement = document.getElementById('rezervace-config');
	const form = /** @type {HTMLFormElement} */ (document.getElementById('rezervace-form'));
	if (!configElement || !form) return;

	/** @type {Config} */
	const config = JSON.parse(configElement.textContent ?? '{}');
	const messages = errorMessages(config.phone);
	const sluzby = /** @type {HTMLElement} */ (document.getElementById('rez-sluzby'));
	const sablona = /** @type {HTMLTemplateElement} */ (document.getElementById('rez-sluzba-sablona'));
	const pridat = /** @type {HTMLButtonElement} */ (document.getElementById('rez-pridat-sluzbu'));
	const date = /** @type {HTMLInputElement} */ (form.elements.namedItem('date'));
	const terminy = /** @type {HTMLElement} */ (document.getElementById('terminy'));
	const zprava = /** @type {HTMLElement} */ (document.getElementById('rezervace-zprava'));
	const submit = /** @type {HTMLButtonElement} */ (form.querySelector('button[type="submit"]'));
	const maxServices = Math.min(config.max_services, config.services.length);
	let request = 0;
	let rows = 1;

	const serviceSelects = () => /** @type {HTMLSelectElement[]} */ ([...sluzby.querySelectorAll('select')]);
	const serviceIds = () => serviceSelects().map((select) => select.value).filter(Boolean);

	/** Službu vybranou v jednom řádku nejde vybrat v jiném. Přidat jde, dokud zbývá Služba. */
	function syncServices() {
		const selects = serviceSelects();
		for (const select of selects) {
			const others = new Set(selects.filter((other) => other !== select).map((other) => other.value));
			for (const option of select.options) {
				option.disabled = option.value !== '' && others.has(option.value);
			}
		}
		pridat.hidden = selects.length >= maxServices;
	}

	function addService() {
		const row = /** @type {HTMLElement} */ (sablona.content.firstElementChild?.cloneNode(true));
		const select = /** @type {HTMLSelectElement} */ (row.querySelector('select'));
		select.id = `rez-sluzba-${++rows}`;
		row.querySelector('label')?.setAttribute('for', select.id);
		row.querySelector('.sluzby__odebrat')?.addEventListener('click', () => {
			row.remove();
			syncServices();
			pridat.focus();
			loadTerminy();
		});
		sluzby.append(row);
		syncServices();
		select.focus();
	}

	/** @param {string} text */
	const showTerminyText = (text) => {
		terminy.replaceChildren(Object.assign(document.createElement('p'), { textContent: text }));
	};

	async function loadTerminy() {
		const current = ++request;
		const ids = serviceIds();
		if (ids.length === 0 || !date.value) {
			showTerminyText('Vyberte službu a den.');
			return;
		}
		showTerminyText('Načítám volné termíny…');
		try {
			const query = new URLSearchParams(ids.map((id) => ['service_ids[]', id]));
			query.set('date', date.value);
			const response = await fetch(`${config.api}/slots?${query}`, { headers: { Accept: 'application/json' } });
			if (current !== request) return;
			if (!response.ok) {
				/** @type {ApiError} */
				const error = await response.json();
				showTerminyText(messages[error.code] ?? 'Termíny se nepodařilo načíst.');
				return;
			}
			/** @type {{ slots: Termin[] }} */
			const data = await response.json();
			if (data.slots.length === 0) {
				showTerminyText('V tento den nejsou volné termíny. Zkuste prosím jiný den.');
				return;
			}
			terminy.replaceChildren(
				...data.slots.map((slot) => {
					const label = document.createElement('label');
					label.className = 'termin';
					const radio = Object.assign(document.createElement('input'), { type: 'radio', name: 'time', value: slot.time_start, required: true });
					label.append(radio, ` ${humanTime(slot.time_start)}–${humanTime(slot.time_end)}`);
					return label;
				}),
			);
		} catch {
			if (current === request) showTerminyText(messages.network);
		}
	}

	function clearErrors() {
		setText(zprava, '');
		form.querySelectorAll('.pole__chyba').forEach((element) => setText(/** @type {HTMLElement} */ (element), ''));
		form.querySelectorAll('[aria-invalid]').forEach((element) => element.removeAttribute('aria-invalid'));
	}

	/** @param {Record<string, string>} errors */
	function showFieldErrors(errors) {
		let first = /** @type {HTMLElement | null} */ (null);
		for (const [field, code] of Object.entries(errors)) {
			const target = document.getElementById(`chyba-${field}`);
			if (target) setText(target, FIELD_MESSAGES[field]?.[code] ?? 'Zkontrolujte toto pole.');
			const input = /** @type {HTMLElement | null} */ (form.querySelector(`[name="${field}"]`));
			input?.setAttribute('aria-invalid', 'true');
			first ??= input;
		}
		first?.focus();
	}

	/** @param {SubmitEvent} event */
	async function send(event) {
		event.preventDefault();
		clearErrors();
		const values = new FormData(form);
		const body = {
			service_ids: serviceIds(),
			date: date.value,
			time: values.get('time') ?? '',
			name: values.get('name'),
			phone: values.get('phone'),
			email: values.get('email'),
			plate: values.get('plate'),
			vehicle: values.get('vehicle'),
			note: values.get('note'),
			consent_gdpr: values.get('consent_gdpr') === '1',
		};
		submit.disabled = true;
		let leaving = false;
		try {
			const response = await fetch(`${config.api}/bookings`, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-WP-Nonce': config.nonce },
				body: JSON.stringify(body),
			});
			const data = await response.json();
			if (response.status === 201) {
				leaving = true; // Tlačítko zůstane vypnuté, aby druhé kliknutí neposlalo Rezervaci znovu.
				window.location.assign(data.confirmation_url);
				return;
			}
			/** @type {ApiError} */
			const error = data;
			if (error.code === 'booking.invalid_fields' && error.data?.errors) {
				showFieldErrors(error.data.errors);
			} else {
				setText(zprava, messages[error.code] ?? messages.network);
				if (error.code === 'booking.slot_taken' || error.code === 'booking.slot_unavailable') {
					await loadTerminy();
				}
			}
		} catch {
			setText(zprava, messages.network);
		} finally {
			if (!leaving) submit.disabled = false;
		}
	}

	sluzby.addEventListener('change', () => {
		syncServices();
		loadTerminy();
	});
	pridat.addEventListener('click', addService);
	date.addEventListener('change', loadTerminy);
	syncServices();
	form.addEventListener('submit', send);
	loadTerminy();
}

init();
