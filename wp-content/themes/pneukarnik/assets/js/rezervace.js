// @ts-check
/**
 * Rezervační formulář: kalendář dnů s volným Termínem, volné Termíny pro vybrané Služby a den
 * a odeslání Rezervace na REST API pluginu.
 * Pravidla (mřížka, obsazenost, Sezóny, leasing, validace) jsou na serveru, tady se jen zobrazují
 * jejich výsledky.
 */

/**
 * @typedef {{ enabled: boolean, disabled_message: string, services: {id: number, slug: string, name: string, ask_stored_wheels: boolean}[], max_services: number, selected: number, min_date: string, max_date: string, api: string, nonce: string, phone: string, privacy_url: string }} Config
 * @typedef {{ time_start: string, time_end: string }} Termin
 * @typedef {{ name: 'spring' | 'autumn', from: string, to: string, leasing_from: string | null }} Season
 * @typedef {{ code: string, data?: { status: number, errors?: Record<string, string>, season?: Season, disabled_message?: string } }} ApiError
 * @typedef {{ code: string, season: Season }} Restriction
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
	leasing_company: { required: 'Vyplňte leasingovou společnost.', too_long: 'Název je příliš dlouhý.' },
	note: { too_long: 'Poznámka je příliš dlouhá.' },
	consent_gdpr: { required: 'Bez souhlasu nemůžeme rezervaci přijmout.' },
};

/** @param {string} phone */
const callText = (phone) => (phone ? ` Zavolejte nám prosím na ${phone}.` : '');

/**
 * @param {string} phone
 * @returns {Record<string, string>}
 */
function errorMessages(phone) {
	const call = callText(phone);
	return {
		'booking.slot_taken': 'Vybraný termín si mezitím rezervoval někdo jiný. Vyberte prosím jiný.',
		'booking.slot_unavailable': 'Vybraný termín už není v nabídce. Vyberte prosím jiný.',
		'booking.service_not_bookable': `Tuto službu teď online objednat nejde.${call}`,
		'booking.seasonal_only': `V sezóně přezouvání jde online objednat jen přezutí a související služby.${call}`,
		'booking.leasing_date': 'Vozidla na leasing v sezóně přezouvání objednáváme až od data, které určují leasingové společnosti.',
		'booking.service_not_found': 'Vybraná služba už není v nabídce.',
		'booking.rate_limited': `Odeslali jste příliš mnoho rezervací.${call}`,
		'booking.disabled': `Online rezervace jsou teď vypnuté.${call}`,
		'booking.busy': 'Systém je právě vytížený, zkuste to prosím za chvíli znovu.',
		network: `Rezervaci se nepodařilo odeslat. Zkuste to znovu.${call}`,
	};
}

/** Kontaktní údaje, které jde zapamatovat v prohlížeči nebo předvyplnit z „Objednat znovu“. */
const CONTACT_FIELDS = /** @type {const} */ (['name', 'phone', 'email', 'plate', 'vehicle']);
const STORAGE_KEY = 'pneukarnik.udaje';

/**
 * Údaje zapamatované v tomto prohlížeči. Úložiště může být zakázané, pak nic.
 * @returns {Record<string, string> | null}
 */
function storedContact() {
	try {
		const raw = window.localStorage.getItem(STORAGE_KEY);
		return raw ? JSON.parse(raw) : null;
	} catch {
		return null;
	}
}

/** @param {Record<string, string> | null} contact null = smazat */
function storeContact(contact) {
	try {
		if (contact) window.localStorage.setItem(STORAGE_KEY, JSON.stringify(contact));
		else window.localStorage.removeItem(STORAGE_KEY);
	} catch {
		// Bez úložiště se jen nic nezapamatuje.
	}
}

/** @param {string} hhmm */
const humanTime = (hhmm) => hhmm.replace(/^0/, '');

const dayMonth = new Intl.DateTimeFormat('cs', { day: 'numeric', month: 'numeric', timeZone: 'UTC' });
const SEASON_NAMES = { spring: 'jarní', autumn: 'podzimní' };

/**
 * Proč Sezóna nedovolí online Rezervaci, s daty Sezóny.
 * @param {string} code
 * @param {Season} season
 * @param {string} phone
 * @returns {string | undefined}
 */
function seasonText(code, season, phone) {
	/** @param {string} ymd */
	const day = (ymd) => dayMonth.format(utcDate(ymd));
	if (code === 'booking.seasonal_only') {
		const call = phone ? ` na ${phone}` : '';
		return `Od ${day(season.from)} do ${day(season.to)} je sezóna přezouvání a online jde objednat jen přezutí a související služby. Ostatní služby v tu dobu objednáváme telefonicky${call}.`;
	}
	if (code === 'booking.leasing_date' && season.leasing_from) {
		return `Vozidla na leasing objednáváme v ${SEASON_NAMES[season.name]} sezóně až od ${day(season.leasing_from)}, tak to určují leasingové společnosti.`;
	}
	return undefined;
}

const monthTitle = new Intl.DateTimeFormat('cs', { month: 'long', year: 'numeric', timeZone: 'UTC' });
const dayLabel = new Intl.DateTimeFormat('cs', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', timeZone: 'UTC' });

/**
 * Dny kalendáře jako UTC půlnoci, aby výpočty nezávisely na časové zóně prohlížeče.
 * @param {string} ymd YYYY-MM-DD nebo YYYY-MM
 */
function utcDate(ymd) {
	const [year, month, day = 1] = ymd.split('-').map(Number);
	return new Date(Date.UTC(year, month - 1, day));
}

/** @param {Date} date */
const toYmd = (date) => date.toISOString().slice(0, 10);

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
	const dny = /** @type {HTMLElement} */ (document.getElementById('kalendar-dny'));
	const mesic = /** @type {HTMLElement} */ (document.getElementById('kalendar-mesic'));
	const stav = /** @type {HTMLElement} */ (document.getElementById('kalendar-stav'));
	const predchozi = /** @type {HTMLButtonElement} */ (document.getElementById('kalendar-predchozi'));
	const dalsi = /** @type {HTMLButtonElement} */ (document.getElementById('kalendar-dalsi'));
	const terminy = /** @type {HTMLElement} */ (document.getElementById('terminy'));
	const zprava = /** @type {HTMLElement} */ (document.getElementById('rezervace-zprava'));
	const submit = /** @type {HTMLButtonElement} */ (form.querySelector('button[type="submit"]'));
	const uskladnena = /** @type {HTMLElement} */ (document.getElementById('rez-uskladnena'));
	const leasing = /** @type {HTMLInputElement} */ (document.getElementById('rez-leasing'));
	const leasingSpolecnost = /** @type {HTMLElement} */ (document.getElementById('rez-leasing-spolecnost'));
	const zapamatovat = /** @type {HTMLInputElement} */ (document.getElementById('rez-zapamatovat'));
	const zapomenout = /** @type {HTMLButtonElement} */ (document.getElementById('rez-zapomenout'));
	const zapomenuto = /** @type {HTMLElement} */ (document.getElementById('rez-zapomenuto'));
	const askStoredWheels = new Set(config.services.filter((service) => service.ask_stored_wheels).map((service) => String(service.id)));
	const maxServices = Math.min(config.max_services, config.services.length);
	let request = 0;
	let daysRequest = 0;
	let rows = 1;
	let month = config.min_date.slice(0, 7);
	/** @type {Set<string>} */
	let availableDays = new Set();

	const serviceSelects = () => /** @type {HTMLSelectElement[]} */ ([...sluzby.querySelectorAll('select')]);
	const serviceIds = () => serviceSelects().map((select) => select.value).filter(Boolean);

	/** @param {Record<string, unknown>} contact */
	function fillContact(contact) {
		for (const field of CONTACT_FIELDS) {
			const input = /** @type {HTMLInputElement} */ (form.elements.namedItem(field));
			if (typeof contact[field] === 'string') input.value = contact[field];
		}
	}

	/** Údaje z formuláře k zapamatování. */
	function currentContact() {
		const values = new FormData(form);
		return Object.fromEntries(CONTACT_FIELDS.map((field) => [field, String(values.get(field) ?? '')]));
	}

	function forgetContact() {
		storeContact(null);
		fillContact(Object.fromEntries(CONTACT_FIELDS.map((field) => [field, ''])));
		zapamatovat.checked = false;
		zapomenout.hidden = true;
		setText(zapomenuto, 'Uložené údaje jsme z tohoto prohlížeče smazali.');
	}

	/** „Objednat znovu“: odkaz z e‑mailu předvyplní kontaktní údaje dané Rezervace. */
	async function prefillFromLink() {
		const url = new URL(window.location.href);
		const token = url.searchParams.get('znovu');
		if (!token) return;
		// Token z adresy hned zmizí, aby nezůstal v historii ani v odkazech dál.
		url.searchParams.delete('znovu');
		window.history.replaceState(null, '', url);
		try {
			const response = await fetch(`${config.api}/prefill?${new URLSearchParams({ token })}`, { headers: { Accept: 'application/json' } });
			if (response.ok) fillContact(await response.json());
		} catch {
			// Formulář jde vyplnit i ručně.
		}
	}

	/**
	 * Text chyby. Online rezervace vypnuté až po načtení stránky: zpráva, kterou Provozovatel zadal.
	 *
	 * @param {ApiError} error
	 */
	const explain = (error) =>
		(error.code === 'booking.disabled' && error.data?.disabled_message
			? `${error.data.disabled_message}${callText(config.phone)}`
			: (error.data?.season && seasonText(error.code, error.data.season, config.phone)) ?? messages[error.code]);

	/** Dotaz na dostupnost: vybrané Služby a typ Zákazníka. */
	function availabilityQuery() {
		const query = new URLSearchParams(serviceIds().map((id) => ['service_ids[]', id]));
		if (leasing.checked) query.set('leasing', '1');
		return query;
	}

	/** „Kola mám uskladněná u vás“ jen u Služeb, kde se na to Provozovatel ptá. */
	function syncStoredWheels() {
		uskladnena.hidden = !serviceIds().some((id) => askStoredWheels.has(id));
	}

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
			syncStoredWheels();
			pridat.focus();
			loadDays();
			loadTerminy();
		});
		sluzby.append(row);
		syncServices();
		select.focus();
	}

	/** Mřížka měsíce: dny bez volného Termínu jsou vypnuté (zašedlé). */
	function renderCalendar() {
		const first = utcDate(month);
		mesic.textContent = monthTitle.format(first);
		predchozi.disabled = month <= config.min_date.slice(0, 7);
		dalsi.disabled = month >= config.max_date.slice(0, 7);

		const cells = /** @type {HTMLElement[]} */ ([]);
		for (let i = 0; i < (first.getUTCDay() + 6) % 7; i++) cells.push(document.createElement('td'));
		for (const day = new Date(first); day.getUTCMonth() === first.getUTCMonth(); day.setUTCDate(day.getUTCDate() + 1)) {
			const ymd = toYmd(day);
			const button = Object.assign(document.createElement('button'), {
				type: 'button',
				textContent: String(day.getUTCDate()),
				disabled: !availableDays.has(ymd),
			});
			button.dataset.date = ymd;
			button.setAttribute('aria-label', dayLabel.format(day));
			button.setAttribute('aria-pressed', String(ymd === date.value));
			const cell = document.createElement('td');
			cell.append(button);
			cells.push(cell);
		}
		const weeks = [];
		for (let i = 0; i < cells.length; i += 7) {
			const row = document.createElement('tr');
			row.append(...cells.slice(i, i + 7));
			weeks.push(row);
		}
		dny.replaceChildren(...weeks);
	}

	async function loadDays() {
		const current = ++daysRequest;
		const ids = serviceIds();
		availableDays = new Set();
		if (ids.length === 0) {
			setText(stav, 'Nejdřív vyberte službu, pak uvidíte volné dny.');
			renderCalendar();
			return;
		}
		setText(stav, 'Načítám volné dny…');
		renderCalendar();
		try {
			const query = availabilityQuery();
			query.set('month', month);
			const response = await fetch(`${config.api}/available-days?${query}`, { headers: { Accept: 'application/json' } });
			if (current !== daysRequest) return;
			const data = await response.json();
			if (!response.ok) {
				setText(stav, explain(data) ?? 'Volné dny se nepodařilo načíst.');
				return;
			}
			/** @type {{ days: string[], restrictions: Restriction[] }} */
			const { days, restrictions } = data;
			availableDays = new Set(days);
			const reasons = restrictions.map((restriction) => seasonText(restriction.code, restriction.season, config.phone)).filter(Boolean);
			setText(stav, [availableDays.size ? 'Zašedlé dny nemají volný termín.' : 'V tomto měsíci nejsou volné termíny. Zkuste další měsíc.', ...reasons].join(' '));
			renderCalendar();
		} catch {
			if (current === daysRequest) setText(stav, messages.network);
		}
	}

	/** @param {number} delta */
	function moveMonth(delta) {
		const first = utcDate(month);
		first.setUTCMonth(first.getUTCMonth() + delta);
		month = toYmd(first).slice(0, 7);
		loadDays();
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
			const query = availabilityQuery();
			query.set('date', date.value);
			const response = await fetch(`${config.api}/slots?${query}`, { headers: { Accept: 'application/json' } });
			if (current !== request) return;
			if (!response.ok) {
				/** @type {ApiError} */
				const error = await response.json();
				showTerminyText(explain(error) ?? 'Termíny se nepodařilo načíst.');
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
			leasing: leasing.checked,
			leasing_company: leasing.checked ? values.get('leasing_company') : '',
			stored_wheels: !uskladnena.hidden && values.get('stored_wheels') === '1',
			consent_gdpr: values.get('consent_gdpr') === '1',
			consent_reminder: values.get('consent_reminder') === '1',
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
				storeContact(zapamatovat.checked ? currentContact() : null);
				leaving = true; // Tlačítko zůstane vypnuté, aby druhé kliknutí neposlalo Rezervaci znovu.
				window.location.assign(data.confirmation_url);
				return;
			}
			/** @type {ApiError} */
			const error = data;
			if (error.code === 'booking.invalid_fields' && error.data?.errors) {
				showFieldErrors(error.data.errors);
			} else {
				setText(zprava, explain(error) ?? messages.network);
				if (error.code === 'booking.slot_taken' || error.code === 'booking.slot_unavailable') {
					await Promise.all([loadTerminy(), loadDays()]);
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
		syncStoredWheels();
		loadDays();
		loadTerminy();
	});
	const syncLeasing = () => {
		leasingSpolecnost.hidden = !leasing.checked;
	};
	leasing.addEventListener('change', () => {
		syncLeasing();
		loadDays();
		loadTerminy();
	});
	pridat.addEventListener('click', addService);
	dny.addEventListener('click', (event) => {
		const button = /** @type {HTMLElement} */ (event.target).closest('button');
		if (!button?.dataset.date) return;
		date.value = button.dataset.date;
		dny.querySelectorAll('[aria-pressed="true"]').forEach((pressed) => pressed.setAttribute('aria-pressed', 'false'));
		button.setAttribute('aria-pressed', 'true');
		loadTerminy();
	});
	predchozi.addEventListener('click', () => moveMonth(-1));
	dalsi.addEventListener('click', () => moveMonth(1));
	const remembered = storedContact();
	if (remembered) {
		fillContact(remembered);
		zapamatovat.checked = true;
		zapomenout.hidden = false;
	}
	zapomenout.addEventListener('click', forgetContact);
	prefillFromLink();
	syncServices();
	syncStoredWheels();
	syncLeasing();
	loadDays();
	form.addEventListener('submit', send);
	loadTerminy();
}

init();
