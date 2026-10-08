// @ts-check
/**
 * Rezervační formulář: kalendář dnů s volným Termínem, volné Termíny pro vybrané Služby a den
 * a odeslání Rezervace na REST API pluginu.
 * Pravidla (mřížka, obsazenost, Sezóny, leasing, validace) jsou na serveru, tady se jen zobrazují
 * jejich výsledky.
 */

/**
 * @typedef {{ enabled: boolean, disabled_message: string, services: {id: number, slug: string, name: string, duration: number, ask_stored_wheels: boolean, guide: {url: string, title: string} | null}[], max_services: number, selected: number, min_date: string, max_date: string, api: string, nonce: string, phone: string, privacy_url: string }} Config
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

/**
 * Odkud Zákazník na rezervaci přišel: nepodepsaný parametr `zdroj` odkazů z e‑mailů, jinak přímo.
 * @type {Record<string, string>}
 */
const SOURCES = { 'objednat-znovu': 'Objednat znovu', 'pripominka-prezuti': 'Připomínka přezutí', rozesilka: 'Rozesílka' };

/** Jak dlouho nejvýš čekat na Matomo, než odchod na potvrzení uřízne poslední událost. */
const TRACK_TIMEOUT = 500;

/**
 * Kroky rezervace jako události Matoma (kategorie Rezervace, název = zdroj příchodu). Jen když web
 * Matomo nastavil (fronta `_paq` z inc/seo.php), jinak nic. Bez tokenů a osobních údajů: akce je
 * krok, nebo u neúspěšného odeslání kód chyby. Vybrané Služby jdou zvlášť jako akce „Služba“
 * s názvem Služby místo zdroje.
 *
 * @param {string} source
 */
function tracker(source) {
	/**
	 * @param {string} action
	 * @param {{ name?: string, done?: () => void }} [options] name místo zdroje; done se zavolá po odeslání události (bez Matoma hned).
	 */
	return (action, { name = source, done } = {}) => {
		const paq = /** @type {{ _paq?: { push: (command: unknown[]) => void } }} */ (/** @type {unknown} */ (window))._paq;
		if (!paq) {
			done?.();
			return;
		}
		if (!done) {
			paq.push(['trackEvent', 'Rezervace', action, name]);
			return;
		}
		let finished = false;
		const finish = () => {
			if (finished) return;
			finished = true;
			done();
		};
		// Zablokovaný skript Matoma callback nezavolá, formulář proto dlouho nečeká.
		paq.push(['trackEvent', 'Rezervace', action, name, undefined, undefined, finish]);
		window.setTimeout(finish, TRACK_TIMEOUT);
	};
}

/** @param {string} hhmm */
const humanTime = (hhmm) => hhmm.replace(/^0/, '');

const dayMonth = new Intl.DateTimeFormat('cs', { day: 'numeric', month: 'numeric', timeZone: 'UTC' });
const shortWeekday = new Intl.DateTimeFormat('cs', { weekday: 'short', timeZone: 'UTC' });
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
const weekday = new Intl.DateTimeFormat('cs', { weekday: 'long', timeZone: 'UTC' });
const shortDate = new Intl.DateTimeFormat('cs', { day: 'numeric', month: 'numeric', year: 'numeric', timeZone: 'UTC' });

/** Den pro lidi, např. „pondělí 1. 3. 2027“. @param {string} ymd */
const humanDay = (ymd) => `${weekday.format(utcDate(ymd))} ${shortDate.format(utcDate(ymd))}`;

/** Den krátce, např. „út 14. 10.“. @param {string} ymd */
const shortDay = (ymd) => `${shortWeekday.format(utcDate(ymd))} ${dayMonth.format(utcDate(ymd))}`;

/** Během načítání místo Termínů zašedlé prázdné pilulky. */
const LOADING_SLOTS = 6;

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

/**
 * Upozornění (Notice) s textem: výchozí akcentové, info nebo danger.
 * @param {string} text
 * @param {'' | 'info' | 'danger'} tone
 */
function notice(text, tone = '') {
	return Object.assign(document.createElement('p'), { className: tone ? `notice notice--${tone}` : 'notice', textContent: text });
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
	const omezeni = /** @type {HTMLElement} */ (document.getElementById('kalendar-omezeni'));
	const nejblizsi = /** @type {HTMLElement} */ (document.getElementById('kalendar-nejblizsi'));
	const time = /** @type {HTMLInputElement} */ (form.elements.namedItem('time'));
	const terminy = /** @type {HTMLElement} */ (document.getElementById('terminy'));
	const terminyDen = /** @type {HTMLElement} */ (document.getElementById('terminy-den'));
	const terminyStav = /** @type {HTMLElement} */ (document.getElementById('terminy-stav'));
	const zprava = /** @type {HTMLElement} */ (document.getElementById('rezervace-zprava'));
	const submit = /** @type {HTMLButtonElement} */ (form.querySelector('button[type="submit"]'));
	const submitText = submit.textContent ?? '';
	const odesilam = /** @type {HTMLElement} */ (document.getElementById('rez-odesilam'));
	const uskladnena = /** @type {HTMLElement} */ (document.getElementById('rez-uskladnena'));
	const leasing = /** @type {HTMLInputElement} */ (document.getElementById('rez-leasing'));
	const leasingSpolecnost = /** @type {HTMLElement} */ (document.getElementById('rez-leasing-spolecnost'));
	const zapamatovat = /** @type {HTMLInputElement} */ (document.getElementById('rez-zapamatovat'));
	const zapomenout = /** @type {HTMLButtonElement} */ (document.getElementById('rez-zapomenout'));
	const zapomenuto = /** @type {HTMLElement} */ (document.getElementById('rez-zapomenuto'));
	const coPosilame = /** @type {HTMLButtonElement} */ (document.getElementById('rez-co-posilame'));
	const askStoredWheels = new Set(config.services.filter((service) => service.ask_stored_wheels).map((service) => String(service.id)));
	const servicesById = new Map(config.services.map((service) => [String(service.id), service]));
	const maxServices = Math.min(config.max_services, config.services.length);
	const track = tracker(SOURCES[new URL(window.location.href).searchParams.get('zdroj') ?? ''] ?? 'Přímo');
	let request = 0;
	let daysRequest = 0;
	let rows = 1;
	let month = config.min_date.slice(0, 7);
	/** @type {Set<string>} */
	let availableDays = new Set();

	/**
	 * Krok „Vybral službu“ a ke každé nově vybrané Službě událost „Služba“ s jejím názvem.
	 * @param {string[]} ids
	 */
	function trackServices(ids) {
		track('Vybral službu');
		for (const id of ids) {
			const service = servicesById.get(id);
			if (service) track('Služba', { name: service.name });
		}
	}

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

	/**
	 * Služby a uskladněná kola z „Objednat znovu“: jen Služby, které jde objednat online,
	 * nejvýš kolik jich formulář dovolí. Termín vybírá Zákazník znovu.
	 *
	 * @param {unknown[]} ids
	 * @param {boolean} storedWheels
	 */
	function fillServices(ids, storedWheels) {
		const known = [...new Set(ids.map(String))].filter((id) => servicesById.has(id)).slice(0, maxServices);
		if (known.length === 0) return false;
		for (const select of serviceSelects().slice(1)) select.closest('.rezervace__sluzba')?.remove();
		serviceSelects()[0].value = known[0];
		for (const id of known.slice(1)) addService(id);
		/** @type {HTMLInputElement} */ (form.elements.namedItem('stored_wheels')).checked = storedWheels;
		syncServices();
		syncStoredWheels();
		syncGuides();
		trackServices(known);
		loadDays({ jump: true });
		loadTerminy();
		return true;
	}

	/** „Objednat znovu“: odkaz z e‑mailu předvyplní kontaktní údaje, případně i Služby dané Rezervace. */
	async function prefillFromLink() {
		const url = new URL(window.location.href);
		const token = url.searchParams.get('znovu');
		if (!token) return;
		// Token z adresy hned zmizí, aby nezůstal v historii ani v odkazech dál.
		url.searchParams.delete('znovu');
		window.history.replaceState(null, '', url);
		try {
			const response = await fetch(`${config.api}/prefill?${new URLSearchParams({ token })}`, { headers: { Accept: 'application/json' } });
			if (!response.ok) return;
			const details = await response.json();
			fillContact(details);
			if (!Array.isArray(details.service_ids)) return;
			const text = fillServices(details.service_ids, details.stored_wheels === true)
				? 'Služby a údaje jsme předvyplnili podle vaší předchozí rezervace. Vyberte prosím nový termín.'
				: 'Údaje jsme předvyplnili podle vaší předchozí rezervace. Vyberte prosím službu a nový termín.';
			form.before(notice(text, 'info'));
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

	/** Pod každou vybranou Službou s Průvodcem odkaz „Přečtěte si“, jinak nic. */
	function syncGuides() {
		for (const select of serviceSelects()) {
			const box = /** @type {HTMLElement} */ (select.closest('.rezervace__sluzba')?.querySelector('.rezervace__pruvodce'));
			const guide = servicesById.get(select.value)?.guide;
			box.hidden = !guide;
			if (!guide) continue;
			/** @type {HTMLAnchorElement} */ (box.querySelector('a')).href = guide.url;
			setText(/** @type {HTMLElement} */ (box.querySelector('[data-pruvodce-nazev]')), guide.title);
		}
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

	/**
	 * Další řádek Služby. Bez hodnoty (tlačítko „+ přidat další službu“) dostane fokus.
	 *
	 * @param {string} [value]
	 */
	function addService(value = '') {
		const row = /** @type {HTMLElement} */ (sablona.content.firstElementChild?.cloneNode(true));
		const select = /** @type {HTMLSelectElement} */ (row.querySelector('select'));
		select.id = `rez-sluzba-${++rows}`;
		select.value = value;
		row.querySelector('label')?.setAttribute('for', select.id);
		row.querySelector('.rezervace__odebrat')?.addEventListener('click', () => {
			row.remove();
			syncServices();
			syncStoredWheels();
			pridat.focus();
			loadDays({ jump: true });
			loadTerminy();
			renderSummary();
		});
		sluzby.append(row);
		syncServices();
		if (!value) select.focus();
	}

	/** Souhrn vpravo (na mobilu nad tlačítkem): Služby s Délkou, celková Délka a Termín. */
	function renderSummary() {
		const selected = serviceIds().map((id) => servicesById.get(id)).filter((service) => service !== undefined);
		const total = selected.reduce((sum, service) => sum + service.duration, 0);
		const termin = date.value ? (time.value ? `${humanDay(date.value)} v ${humanTime(time.value)}` : shortDate.format(utcDate(date.value))) : '—';
		for (const souhrn of document.querySelectorAll('[data-souhrn]')) {
			const list = /** @type {HTMLElement} */ (souhrn.querySelector('[data-souhrn-sluzby]'));
			const items = selected.map((service) => {
				const item = document.createElement('li');
				item.append(
					Object.assign(document.createElement('span'), { textContent: service.name }),
					Object.assign(document.createElement('span'), { className: 'souhrn__minuty', textContent: `${service.duration} min` }),
				);
				return item;
			});
			list.replaceChildren(...(items.length ? items : [Object.assign(document.createElement('li'), { className: 'souhrn__prazdne', textContent: 'Zatím není vybraná žádná služba.' })]));
			setText(/** @type {HTMLElement} */ (souhrn.querySelector('[data-souhrn-delka]')), total ? `${total} min` : '—');
			setText(/** @type {HTMLElement} */ (souhrn.querySelector('[data-souhrn-termin]')), termin);
		}
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

	/**
	 * Nejbližší volný den nad kalendářem, bez něj v horizontu výzva zavolat.
	 * @param {string | null} firstDay
	 */
	function showFirstDay(firstDay) {
		const call = config.phone ? `, zavolejte nám prosím na ${config.phone}` : '';
		setText(nejblizsi, firstDay ? `Nejbližší volný den: ${shortDay(firstDay)}` : `Online teď volný termín nemáme${call}.`);
	}

	/**
	 * Volné dny zobrazeného měsíce. S `jump` (změna Služeb nebo leasingu) kalendář z měsíce bez
	 * volného dne sám přejde na měsíc nejbližšího volného dne. Ruční přepnutí měsíce nepřeskakuje.
	 *
	 * @param {{ jump?: boolean }} [options]
	 */
	async function loadDays({ jump = false } = {}) {
		const current = ++daysRequest;
		const ids = serviceIds();
		availableDays = new Set();
		omezeni.replaceChildren();
		dny.classList.remove('kalendar__dny--nacitani');
		if (jump || ids.length === 0) setText(nejblizsi, '');
		if (ids.length === 0) {
			setText(stav, 'Nejdřív vyberte službu, pak uvidíte volné dny.');
			renderCalendar();
			return;
		}
		setText(stav, 'Načítám volné dny…');
		dny.classList.add('kalendar__dny--nacitani');
		renderCalendar();
		try {
			const query = availabilityQuery();
			query.set('month', month);
			const response = await fetch(`${config.api}/available-days?${query}`, { headers: { Accept: 'application/json' } });
			if (current !== daysRequest) return;
			const data = await response.json();
			dny.classList.remove('kalendar__dny--nacitani');
			if (!response.ok) {
				setText(stav, explain(data) ?? 'Volné dny se nepodařilo načíst.');
				return;
			}
			/** @type {{ days: string[], restrictions: Restriction[], first_day: string | null }} */
			const { days, restrictions, first_day: firstDay } = data;
			if (jump && days.length === 0 && firstDay && firstDay.slice(0, 7) !== month) {
				month = firstDay.slice(0, 7);
				await loadDays();
				return;
			}
			showFirstDay(firstDay);
			availableDays = new Set(days);
			setText(stav, availableDays.size ? 'Zašedlé dny nemají volný termín.' : 'V tomto měsíci nejsou volné termíny.');
			// Proč Sezóna nedovolí některé dny: leasing jako informace, jen sezónní Služby jako upozornění.
			omezeni.replaceChildren(
				...restrictions.flatMap((restriction) => {
					const text = seasonText(restriction.code, restriction.season, config.phone);
					return text ? [notice(text, restriction.code === 'booking.leasing_date' ? 'info' : '')] : [];
				}),
			);
			renderCalendar();
		} catch {
			if (current === daysRequest) {
				dny.classList.remove('kalendar__dny--nacitani');
				setText(stav, messages.network);
			}
		}
	}

	/** @param {number} delta */
	function moveMonth(delta) {
		const first = utcDate(month);
		first.setUTCMonth(first.getUTCMonth() + delta);
		month = toYmd(first).slice(0, 7);
		loadDays();
	}

	/** Místo Termínů jen text (žádné volné, chyba). @param {string} text */
	const showTerminyText = (text) => {
		terminy.replaceChildren();
		setText(terminyStav, text);
	};

	/** Zašedlé prázdné pilulky, dokud se Termíny načítají. */
	function showTerminyLoading() {
		terminy.replaceChildren(...Array.from({ length: LOADING_SLOTS }, () => Object.assign(document.createElement('span'), { className: 'termin termin--nacitani' })));
		setText(terminyStav, 'Načítám volné termíny…');
	}

	/** @param {HTMLButtonElement} button */
	function pickTermin(button) {
		time.value = button.dataset.time ?? '';
		terminy.querySelectorAll('[aria-pressed="true"]').forEach((pressed) => pressed.setAttribute('aria-pressed', 'false'));
		button.setAttribute('aria-pressed', 'true');
		setText(/** @type {HTMLElement} */ (document.getElementById('chyba-time')), '');
		renderSummary();
		track('Vybral termín');
	}

	async function loadTerminy() {
		const current = ++request;
		const ids = serviceIds();
		time.value = '';
		renderSummary();
		setText(terminyDen, date.value ? `· ${humanDay(date.value)}` : '');
		if (ids.length === 0 || !date.value) {
			showTerminyText('Termíny se ukážou po výběru dne.');
			return;
		}
		showTerminyLoading();
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
			setText(terminyStav, '');
			terminy.replaceChildren(
				...data.slots.map((slot) => {
					const button = Object.assign(document.createElement('button'), {
						type: 'button',
						className: 'termin',
						textContent: `${humanTime(slot.time_start)}–${humanTime(slot.time_end)}`,
					});
					button.dataset.time = slot.time_start;
					button.setAttribute('aria-pressed', 'false');
					return button;
				}),
			);
		} catch {
			if (current === request) showTerminyText(messages.network);
		}
	}

	/** Celková chyba nad tlačítkem, prázdný text ji schová. @param {string} text */
	const showMessage = (text) => {
		zprava.replaceChildren(...(text ? [notice(text, 'danger')] : []));
	};

	function clearErrors() {
		showMessage('');
		form.querySelectorAll('.pole__chyba').forEach((element) => setText(/** @type {HTMLElement} */ (element), ''));
		form.querySelectorAll('[aria-invalid]').forEach((element) => element.removeAttribute('aria-invalid'));
	}

	/** @param {Record<string, string>} errors */
	function showFieldErrors(errors) {
		let first = /** @type {HTMLElement | null} */ (null);
		for (const [field, code] of Object.entries(errors)) {
			const target = document.getElementById(`chyba-${field}`);
			if (target) setText(target, FIELD_MESSAGES[field]?.[code] ?? 'Zkontrolujte toto pole.');
			const input = /** @type {HTMLInputElement | null} */ (form.querySelector(`[name="${field}"]`));
			input?.setAttribute('aria-invalid', 'true');
			// Den a Termín jsou skrytá pole, kurzor jde až na první vyplnitelné.
			if (input && input.type !== 'hidden') first ??= input;
		}
		showMessage('Rezervaci jsme neodeslali. Zkontrolujte prosím zvýrazněná pole.');
		first?.focus();
	}

	/** Stav Odesílání: tlačítko vypnuté s textem „Odesílám rezervaci…“. @param {boolean} sending */
	function setSending(sending) {
		submit.disabled = sending;
		submit.setAttribute('aria-busy', String(sending));
		setText(submit, sending ? 'Odesílám rezervaci…' : submitText);
		odesilam.hidden = !sending;
	}

	/** @param {SubmitEvent} event */
	async function send(event) {
		event.preventDefault();
		clearErrors();
		const values = new FormData(form);
		const body = {
			service_ids: serviceIds(),
			date: date.value,
			time: time.value,
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
			refuse_offers: values.get('refuse_offers') === '1',
		};
		setSending(true);
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
				track('Odeslal rezervaci', { done: () => window.location.assign(data.confirmation_url) });
				return;
			}
			/** @type {ApiError} */
			const error = data;
			track(error.code ?? 'unknown');
			if (error.code === 'booking.invalid_fields' && error.data?.errors) {
				showFieldErrors(error.data.errors);
			} else {
				showMessage(explain(error) ?? messages.network);
				if (error.code === 'booking.slot_taken' || error.code === 'booking.slot_unavailable') {
					await Promise.all([loadTerminy(), loadDays()]);
				}
			}
		} catch {
			showMessage(messages.network);
			track('network');
		} finally {
			if (!leaving) setSending(false);
		}
	}

	sluzby.addEventListener('change', (event) => {
		syncServices();
		syncStoredWheels();
		syncGuides();
		const { value } = /** @type {HTMLSelectElement} */ (event.target);
		if (value) trackServices([value]);
		loadDays({ jump: true });
		loadTerminy();
	});
	terminy.addEventListener('click', (event) => {
		const button = /** @type {HTMLElement} */ (event.target).closest('button');
		if (button?.dataset.time) pickTermin(/** @type {HTMLButtonElement} */ (button));
	});
	const syncLeasing = () => {
		leasingSpolecnost.hidden = !leasing.checked;
	};
	leasing.addEventListener('change', () => {
		syncLeasing();
		loadDays({ jump: true });
		loadTerminy();
	});
	pridat.addEventListener('click', () => addService());
	dny.addEventListener('click', (event) => {
		const button = /** @type {HTMLElement} */ (event.target).closest('button');
		if (!button?.dataset.date) return;
		date.value = button.dataset.date;
		dny.querySelectorAll('[aria-pressed="true"]').forEach((pressed) => pressed.setAttribute('aria-pressed', 'false'));
		button.setAttribute('aria-pressed', 'true');
		setText(/** @type {HTMLElement} */ (document.getElementById('chyba-date')), '');
		track('Vybral den');
		loadTerminy();
	});
	predchozi.addEventListener('click', () => moveMonth(-1));
	dalsi.addEventListener('click', () => moveMonth(1));
	/** @type {HTMLElement} */ (document.getElementById('rezervace')).hidden = false;
	track('Otevřel formulář');
	const remembered = storedContact();
	if (remembered) {
		fillContact(remembered);
		zapamatovat.checked = true;
		zapomenout.hidden = false;
	}
	zapomenout.addEventListener('click', forgetContact);
	// „i“ u Nabídek a připomínek rozbalí a sbalí, co chodí.
	coPosilame.addEventListener('click', () => {
		const open = coPosilame.getAttribute('aria-expanded') !== 'true';
		coPosilame.setAttribute('aria-expanded', String(open));
		/** @type {HTMLElement} */ (document.getElementById('napoveda-refuse_offers')).hidden = !open;
	});
	prefillFromLink();
	syncServices();
	syncStoredWheels();
	syncGuides();
	syncLeasing();
	// Služba předvybraná z detailu Služby („Rezervovat“).
	if (serviceIds().length) trackServices(serviceIds());
	loadDays({ jump: true });
	form.addEventListener('submit', send);
	loadTerminy();
}

init();
