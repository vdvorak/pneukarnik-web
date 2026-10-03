/**
 * Kalendář Rezervací v administraci: den a týden, klik na volné místo = telefonická objednávka,
 * klik na Rezervaci = detail, úprava a Zrušení. Pravidla (překryv, Pracovní doba) hlídá REST
 * /admin/…, tady se jen zobrazují a ptáme se na vědomé zadání mimo Pracovní dobu.
 */

/**
 * @typedef {{ api: string, nonce: string, can_manage: boolean, grid_step: number, today: string, date: string, booking_id: number, services: Service[] }} Config
 * @typedef {{ id: number, name: string, duration: number, online: boolean }} Service
 * @typedef {{ from: string, to: string }} Block
 * @typedef {{ id: number, name: string, duration: number, price: number | null, price_from: boolean }} BookedService
 * @typedef {{ id: number, status: string, source: string, date: string, time_start: string, time_end: string, services: BookedService[], name: string, company: string, phone: string, email: string, plate: string, vehicle: string, note: string, leasing: boolean, leasing_company: string, stored_wheels: boolean, created_at: string, cancelled_at: string | null, cancel_reason: string }} Booking
 * @typedef {{ date: string, hours: Block[] | null, note: string, bookings: Booking[] }} Day
 * @typedef {{ code: string, data?: { status: number, errors?: Record<string, string> } }} ApiError
 * @typedef {{ ok: boolean, status: number, data: any }} ApiResult
 */

/** Výška jedné minuty v pixelech. */
const PX_PER_MINUTE = 1.2;
const DAY_NAMES = ['pondělí', 'úterý', 'středa', 'čtvrtek', 'pátek', 'sobota', 'neděle'];
const DAY_SHORT = ['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne'];

const MESSAGES = /** @type {Record<string, string>} */ ({
	'booking.slot_taken': 'V tomto čase už je jiná rezervace. Vyberte jiný čas.',
	'booking.no_duration': 'Vyberte alespoň jednu službu s délkou.',
	'booking.past_midnight': 'Rezervace musí skončit do půlnoci.',
	'booking.service_not_found': 'Některá z vybraných služeb už není v nabídce.',
	'booking.cancelled': 'Rezervace je mezitím zrušená.',
	'booking.not_found': 'Rezervace neexistuje.',
	'booking.busy': 'Systém je právě vytížený, zkuste to za chvíli znovu.',
	'booking.invalid_fields': 'Zkontrolujte vyznačená pole.',
	'cancellation.already_cancelled': 'Rezervace už je zrušená.',
	'cancellation.not_found': 'Rezervace neexistuje.',
	rest_forbidden: 'Na tuto akci nemáte oprávnění.',
	rest_cookie_invalid_nonce: 'Přihlášení vypršelo. Načtěte stránku znovu.',
	network: 'Nepodařilo se spojit se serverem. Zkuste to znovu.',
});

const FIELD_MESSAGES = /** @type {Record<string, Record<string, string>>} */ ({
	service_ids: { required: 'Vyberte alespoň jednu službu.', too_many: 'Vyberte méně služeb.' },
	phone: { invalid: 'Telefon zadejte jako 9–15 číslic, případně s předvolbou.' },
	email: { invalid: 'Zkontrolujte e‑mail.' },
	plate: { invalid: 'SPZ může obsahovat jen písmena a číslice.' },
});

/** @param {string} code */
const message = (code) => MESSAGES[code] ?? `Akce se nepodařila (${code}).`;

/**
 * @template {keyof HTMLElementTagNameMap} K
 * @param {K} tag
 * @param {Record<string, string | number | boolean | ((event: Event) => void) | undefined>} [attrs]
 * @param {...(Node | string | null | undefined | false)} children
 * @returns {HTMLElementTagNameMap[K]}
 */
function h(tag, attrs = {}, ...children) {
	const element = document.createElement(tag);
	for (const [key, value] of Object.entries(attrs)) {
		if (value === undefined || value === false) continue;
		if (typeof value === 'function') element.addEventListener(key.slice(2), value);
		else if (key === 'class') element.className = String(value);
		else if (value === true) element.setAttribute(key, '');
		else element.setAttribute(key, String(value));
	}
	for (const child of children) {
		if (child !== null && child !== undefined && child !== false) element.append(child);
	}
	return element;
}

/** @param {string} hhmm */
const toMinutes = (hhmm) => {
	const [hours, minutes] = hhmm.split(':').map(Number);
	return hours * 60 + minutes;
};

/** @param {number} minutes */
const toHhmm = (minutes) => `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(minutes % 60).padStart(2, '0')}`;

/** „09:00“ → „9:00“ @param {string} hhmm */
const shortTime = (hhmm) => hhmm.replace(/^0(\d)/, '$1');

/** @param {string} ymd @param {number} days */
function addDays(ymd, days) {
	const date = new Date(`${ymd}T00:00:00Z`);
	date.setUTCDate(date.getUTCDate() + days);
	return date.toISOString().slice(0, 10);
}

/** 0 = pondělí @param {string} ymd */
const weekday = (ymd) => (new Date(`${ymd}T00:00:00Z`).getUTCDay() + 6) % 7;

/** „6. 10.“ @param {string} ymd */
const dayMonth = (ymd) => `${Number(ymd.slice(8, 10))}. ${Number(ymd.slice(5, 7))}.`;

/** „pondělí 6. 10. 2026“ @param {string} ymd */
const longDay = (ymd) => `${DAY_NAMES[weekday(ymd)]} ${dayMonth(ymd)} ${ymd.slice(0, 4)}`;

function init() {
	const root = document.getElementById('pnk-cal');
	const configElement = document.getElementById('pnk-cal-config');
	if (!root || !configElement) return;
	/** @type {Config} */
	const config = JSON.parse(configElement.textContent ?? '{}');
	const params = new URLSearchParams(window.location.search);

	const state = {
		/** @type {'day' | 'week'} */
		view: params.get('view') === 'day' ? 'day' : 'week',
		date: config.date,
		/** @type {Day[]} */
		days: [],
		request: 0,
	};

	/**
	 * @param {string} method
	 * @param {string} path
	 * @param {unknown} [body]
	 * @returns {Promise<ApiResult>}
	 */
	async function api(method, path, body) {
		try {
			const response = await fetch(`${config.api}${path}`, {
				method,
				credentials: 'same-origin',
				headers: { Accept: 'application/json', 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
				body: body === undefined ? undefined : JSON.stringify(body),
			});
			return { ok: response.ok, status: response.status, data: await response.json() };
		} catch {
			return { ok: false, status: 0, data: { code: 'network' } };
		}
	}

	// Kostra stránky.
	const label = h('strong', { class: 'pnk-cal__label' });
	const status = h('p', { class: 'pnk-cal__status', role: 'status' });
	const grid = h('div', { class: 'pnk-cal__grid' });
	const dialog = h('dialog', { class: 'pnk-cal__dialog', 'aria-labelledby': 'pnk-cal-dialog-title' });
	const viewButtons = {
		day: h('button', { type: 'button', class: 'button', onclick: () => setView('day') }, 'Den'),
		week: h('button', { type: 'button', class: 'button', onclick: () => setView('week') }, 'Týden'),
	};
	root.replaceChildren(
		h(
			'div',
			{ class: 'pnk-cal__toolbar' },
			h('button', { type: 'button', class: 'button', 'aria-label': 'Předchozí', onclick: () => move(-1) }, '‹'),
			h('button', { type: 'button', class: 'button', onclick: () => go(config.today) }, 'Dnes'),
			h('button', { type: 'button', class: 'button', 'aria-label': 'Další', onclick: () => move(1) }, '›'),
			h('input', { type: 'date', class: 'pnk-cal__pick', 'aria-label': 'Přejít na den', value: state.date, onchange: (event) => go(/** @type {HTMLInputElement} */ (event.target).value) }),
			label,
			h('span', { class: 'pnk-cal__views', role: 'group', 'aria-label': 'Zobrazení' }, viewButtons.day, viewButtons.week),
			config.can_manage && h('button', { type: 'button', class: 'button button-primary', onclick: () => openForm(null, { date: state.date, time: '' }) }, '+ Nová rezervace'),
		),
		status,
		grid,
		dialog,
	);

	/** První den zobrazeného období. */
	const firstDay = () => (state.view === 'week' ? addDays(state.date, -weekday(state.date)) : state.date);

	/** @param {'day' | 'week'} view */
	function setView(view) {
		state.view = view;
		load();
	}

	/** @param {number} direction */
	function move(direction) {
		state.date = addDays(state.date, direction * (state.view === 'week' ? 7 : 1));
		load();
	}

	/** @param {string} date */
	function go(date) {
		if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return;
		state.date = date;
		load();
	}

	/** @param {string} text */
	function setStatus(text) {
		status.textContent = text;
	}

	async function load() {
		const from = firstDay();
		const to = state.view === 'week' ? addDays(from, 6) : from;
		const request = ++state.request;
		const url = new URL(window.location.href);
		url.searchParams.set('date', state.date);
		url.searchParams.set('view', state.view);
		url.searchParams.delete('booking_id');
		window.history.replaceState(null, '', url);
		for (const [view, button] of Object.entries(viewButtons)) button.setAttribute('aria-pressed', String(view === state.view));
		/** @type {HTMLInputElement} */ (root?.querySelector('.pnk-cal__pick')).value = state.date;
		label.textContent = from === to ? longDay(from) : `${dayMonth(from)} – ${dayMonth(to)} ${to.slice(0, 4)}`;

		const result = await api('GET', `/admin/calendar?from=${from}&to=${to}`);
		if (request !== state.request) return;
		if (!result.ok) {
			setStatus(message(result.data?.code ?? 'network'));
			return;
		}
		setStatus('');
		state.days = result.data.days;
		render();
	}

	/** Rozsah osy: od nejdřívějšího bloku nebo Rezervace po nejpozdější, na celé hodiny. */
	function range() {
		let start = Infinity;
		let end = -Infinity;
		for (const day of state.days) {
			for (const block of day.hours ?? []) {
				start = Math.min(start, toMinutes(block.from));
				end = Math.max(end, toMinutes(block.to));
			}
			for (const booking of day.bookings) {
				start = Math.min(start, toMinutes(booking.time_start));
				end = Math.max(end, toMinutes(booking.time_end));
			}
		}
		if (start === Infinity) [start, end] = [8 * 60, 17 * 60];
		return { start: Math.floor(start / 60) * 60, end: Math.min(24 * 60, Math.ceil(end / 60) * 60) };
	}

	function render() {
		const { start, end } = range();
		const height = (end - start) * PX_PER_MINUTE;
		/** @param {number} minutes */
		const top = (minutes) => `${(minutes - start) * PX_PER_MINUTE}px`;

		const axis = h('div', { class: 'pnk-cal__axis', style: `height:${height}px` });
		for (let minutes = start; minutes < end; minutes += 60) {
			axis.append(h('span', { class: 'pnk-cal__hour', style: `top:${top(minutes)}` }, shortTime(toHhmm(minutes))));
		}

		const heads = [];
		const bodies = [];
		for (const day of state.days) {
			const today = day.date === config.today ? ' pnk-cal__today' : '';
			heads.push(
				h(
					'div',
					{ class: `pnk-cal__head${today}` },
					h('button', { type: 'button', class: 'button-link', onclick: () => { state.view = 'day'; go(day.date); } }, `${DAY_SHORT[weekday(day.date)]} ${dayMonth(day.date)}`),
					day.note && h('small', {}, day.note),
					!day.hours && !day.note && h('small', {}, 'zavřeno'),
					h('a', { class: 'pnk-cal__pdf', href: `${config.api}/admin/day-sheet?date=${day.date}&_wpnonce=${config.nonce}`, target: '_blank', 'aria-label': `PDF přehled ${longDay(day.date)}` }, 'PDF'),
				),
			);
			const body = h('div', {
				class: `pnk-cal__body${today}${day.hours ? '' : ' pnk-cal__body--closed'}`,
				style: `height:${height}px`,
				'data-date': day.date,
			});
			for (let minutes = start + 60; minutes < end; minutes += 60) {
				body.append(h('span', { class: 'pnk-cal__line', style: `top:${top(minutes)}` }));
			}
			for (const block of day.hours ?? []) {
				body.append(
					h('span', {
						class: 'pnk-cal__open',
						style: `top:${top(toMinutes(block.from))};height:${(toMinutes(block.to) - toMinutes(block.from)) * PX_PER_MINUTE}px`,
					}),
				);
			}
			for (const booking of day.bookings) body.append(bookingButton(booking, top));
			if (config.can_manage) {
				body.title = 'Kliknutím do volného místa zadáte rezervaci';
				body.addEventListener('click', (event) => {
					if (event.target instanceof HTMLElement && event.target.closest('.pnk-cal__booking')) return;
					const y = event.clientY - body.getBoundingClientRect().top;
					openForm(null, { date: day.date, time: toHhmm(snap(day, start + Math.floor(y / PX_PER_MINUTE))) });
				});
			}
			bodies.push(body);
		}
		grid.style.setProperty('--pnk-days', String(state.days.length));
		grid.replaceChildren(h('div', { class: 'pnk-cal__corner' }), ...heads, axis, ...bodies);
	}

	/**
	 * Začátek po kliknutí: dolů na mřížku od začátku bloku Pracovní doby, mimo bloky po celé mřížce.
	 *
	 * @param {Day} day
	 * @param {number} minutes
	 */
	function snap(day, minutes) {
		const step = Math.max(5, config.grid_step);
		const block = (day.hours ?? []).find((b) => minutes >= toMinutes(b.from) && minutes < toMinutes(b.to));
		const base = block ? toMinutes(block.from) : 0;
		return base + Math.floor((minutes - base) / step) * step;
	}

	/**
	 * @param {Booking} booking
	 * @param {(minutes: number) => string} top
	 */
	function bookingButton(booking, top) {
		const startMinutes = toMinutes(booking.time_start);
		const length = toMinutes(booking.time_end) - startMinutes;
		const flags = [booking.leasing && 'leasing', booking.stored_wheels && 'kola uskladněná', booking.source === 'web' && 'web'].filter(Boolean).join(', ');
		const summary = `${shortTime(booking.time_start)}–${shortTime(booking.time_end)} ${booking.name}`;
		return h(
			'button',
			{
				type: 'button',
				class: `pnk-cal__booking${booking.source === 'web' ? ' pnk-cal__booking--web' : ''}`,
				style: `top:${top(startMinutes)};height:${Math.max(18, length * PX_PER_MINUTE - 2)}px`,
				title: [summary, booking.services.map((s) => s.name).join(', '), booking.plate, flags].filter(Boolean).join('\n'),
				onclick: () => openDetail(booking),
			},
			h('strong', {}, summary),
			h('span', {}, [booking.services.map((s) => s.name).join(', '), booking.plate].filter(Boolean).join(' · ')),
			flags && h('em', {}, flags),
		);
	}

	/** @param {...Node} content */
	function showDialog(...content) {
		dialog.replaceChildren(...content);
		if (!dialog.open) dialog.showModal();
	}

	const closeButton = () => h('button', { type: 'button', class: 'button', onclick: () => dialog.close() }, 'Zavřít');

	/** @param {Booking} booking */
	function openDetail(booking) {
		/** @type {[string, Node | string][]} */
		const rows = [
			['Služby', booking.services.map((s) => `${s.name} (${s.duration} min)`).join(', ')],
			['Zákazník', [booking.name, booking.company].filter(Boolean).join(', ')],
			['Telefon', booking.phone ? h('a', { href: `tel:${booking.phone.replace(/[^\d+]/g, '')}` }, booking.phone) : ''],
			['E‑mail', booking.email ? h('a', { href: `mailto:${booking.email}` }, booking.email) : ''],
			['SPZ', booking.plate],
			['Vozidlo', booking.vehicle],
			['Poznámka', booking.note],
			['Leasing', booking.leasing ? booking.leasing_company || 'ano' : ''],
			['Uskladněná kola', booking.stored_wheels ? 'ano, připravit' : ''],
			['Zdroj', booking.source === 'web' ? 'web' : 'Provozovatel'],
			['Zrušeno', booking.status === 'CANCELLED' ? [booking.cancelled_at, booking.cancel_reason].filter(Boolean).join(', ') : ''],
		];
		const editable = config.can_manage && booking.status === 'CONFIRMED';
		showDialog(
			h('h2', { id: 'pnk-cal-dialog-title' }, `${shortTime(booking.time_start)}–${shortTime(booking.time_end)}, ${longDay(booking.date)}`),
			h('dl', { class: 'pnk-cal__detail' }, ...rows.filter(([, value]) => value !== '').flatMap(([term, value]) => [h('dt', {}, term), h('dd', {}, value)])),
			h(
				'p',
				{ class: 'pnk-cal__actions' },
				editable && h('button', { type: 'button', class: 'button button-primary', onclick: () => openForm(booking, { date: booking.date, time: booking.time_start }) }, 'Upravit'),
				editable && h('button', { type: 'button', class: 'button button-link-delete', onclick: () => openCancel(booking) }, 'Zrušit rezervaci'),
				closeButton(),
			),
		);
	}

	/**
	 * Formulář telefonické objednávky nebo úpravy Rezervace.
	 *
	 * @param {Booking | null} booking null = nová Rezervace
	 * @param {{ date: string, time: string }} termin
	 */
	function openForm(booking, termin) {
		const chosen = booking ? booking.services.map((s) => s.id) : [];
		// Služby Rezervace, které už v nabídce nejsou, zůstanou na výběr, dokud je Provozovatel neodebere.
		const services = [
			...config.services,
			...(booking?.services ?? []).filter((s) => !config.services.some((c) => c.id === s.id)).map((s) => ({ id: s.id, name: s.name, duration: s.duration, online: false })),
		];
		/**
		 * @param {string} name
		 * @param {string} text
		 * @param {Record<string, string | boolean | undefined>} [attrs]
		 */
		const field = (name, text, attrs = {}) =>
			h(
				'p',
				{ class: 'pnk-cal__field' },
				h('label', { for: `pnk-f-${name}` }, text),
				name === 'note'
					? h('textarea', { id: `pnk-f-${name}`, name, rows: 2 }, booking?.note ?? '')
					: h('input', { id: `pnk-f-${name}`, name, type: 'text', value: booking ? String(booking[/** @type {keyof Booking} */ (name)] ?? '') : '', ...attrs }),
				h('span', { class: 'pnk-cal__error', 'data-error-for': name }),
			);

		const end = h('span', { class: 'pnk-cal__end' });
		const error = h('p', { class: 'pnk-cal__form-error', role: 'alert' });
		const leasingCompany = field('leasing_company', 'Leasingová společnost');
		leasingCompany.hidden = !booking?.leasing;
		const form = h(
			'form',
			{ class: 'pnk-cal__form', novalidate: true },
			h('h2', { id: 'pnk-cal-dialog-title' }, booking ? 'Upravit rezervaci' : 'Nová rezervace'),
			h(
				'fieldset',
				{ class: 'pnk-cal__services' },
				h('legend', {}, 'Služby'),
				...services.map((s) =>
					h('label', {}, h('input', { type: 'checkbox', name: 'service', value: s.id, checked: chosen.includes(s.id) }), ` ${s.name} (${s.duration} min)${s.online ? '' : ', jen telefon'}`),
				),
				h('span', { class: 'pnk-cal__error', 'data-error-for': 'service_ids' }),
			),
			h(
				'p',
				{ class: 'pnk-cal__termin' },
				h('label', {}, 'Datum ', h('input', { type: 'date', name: 'date', value: termin.date, required: true })),
				h('label', {}, 'Čas ', h('input', { type: 'time', name: 'time', value: termin.time, step: 300, required: true })),
				end,
				h('span', { class: 'pnk-cal__error', 'data-error-for': 'date' }),
				h('span', { class: 'pnk-cal__error', 'data-error-for': 'time' }),
			),
			field('name', 'Jméno *', { autocomplete: 'off' }),
			field('phone', 'Telefon *', { type: 'tel', autocomplete: 'off' }),
			field('email', 'E‑mail (pošleme potvrzení s odkazem na zrušení)', { type: 'email', autocomplete: 'off' }),
			field('plate', 'SPZ', { autocomplete: 'off' }),
			h(
				'details',
				{ open: Boolean(booking && (booking.company || booking.vehicle || booking.note || booking.leasing || booking.stored_wheels)) },
				h('summary', {}, 'Další údaje'),
				field('company', 'Firma'),
				field('vehicle', 'Značka a model'),
				field('note', 'Poznámka'),
				h('p', {}, h('label', {}, h('input', { type: 'checkbox', name: 'leasing', checked: Boolean(booking?.leasing) }), ' Vozidlo na leasing')),
				leasingCompany,
				h('p', {}, h('label', {}, h('input', { type: 'checkbox', name: 'stored_wheels', checked: Boolean(booking?.stored_wheels) }), ' Kola uskladněná u nás')),
			),
			error,
			h('p', { class: 'pnk-cal__actions' }, h('button', { type: 'submit', class: 'button button-primary' }, booking ? 'Uložit změny' : 'Zadat rezervaci'), closeButton()),
		);
		const input = (/** @type {string} */ name) => /** @type {HTMLInputElement} */ (form.elements.namedItem(name));

		/** Vybrané Služby: původní pořadí, nové na konec. */
		const serviceIds = () => {
			const checked = [...form.querySelectorAll('input[name="service"]:checked')].map((el) => Number(/** @type {HTMLInputElement} */ (el).value));
			return [...chosen.filter((id) => checked.includes(id)), ...checked.filter((id) => !chosen.includes(id))];
		};
		const updateEnd = () => {
			const minutes = serviceIds().reduce((sum, id) => sum + (services.find((s) => s.id === id)?.duration ?? 0), 0);
			const time = input('time').value;
			end.textContent = minutes && time ? `do ${shortTime(toHhmm(toMinutes(time) + minutes))} (${minutes} min)` : '';
		};
		form.addEventListener('change', (event) => {
			if (event.target === input('leasing')) leasingCompany.hidden = !input('leasing').checked;
			updateEnd();
		});
		form.addEventListener('input', updateEnd);
		updateEnd();

		form.addEventListener('submit', async (event) => {
			event.preventDefault();
			const body = {
				service_ids: serviceIds(),
				date: input('date').value,
				time: input('time').value,
				name: input('name').value,
				phone: input('phone').value,
				email: input('email').value,
				plate: input('plate').value,
				company: input('company').value,
				vehicle: input('vehicle').value,
				note: /** @type {HTMLTextAreaElement} */ (form.elements.namedItem('note')).value,
				leasing: input('leasing').checked,
				leasing_company: input('leasing').checked ? input('leasing_company').value : '',
				stored_wheels: input('stored_wheels').checked,
				outside_working_hours: false,
			};
			await submit(form, error, body, booking);
		});

		showDialog(form);
		(booking ? input('name') : /** @type {HTMLInputElement | null} */ (form.querySelector('input[name="service"]')))?.focus();
	}

	/**
	 * @param {HTMLFormElement} form
	 * @param {HTMLElement} error
	 * @param {Record<string, unknown> & { outside_working_hours: boolean }} body
	 * @param {Booking | null} booking
	 */
	async function submit(form, error, body, booking) {
		const button = /** @type {HTMLButtonElement} */ (form.querySelector('button[type="submit"]'));
		for (const el of form.querySelectorAll('.pnk-cal__error')) el.textContent = '';
		error.textContent = '';
		button.disabled = true;
		const result = booking ? await api('PATCH', `/admin/bookings/${booking.id}`, body) : await api('POST', '/admin/bookings', body);
		button.disabled = false;
		if (result.ok) {
			dialog.close();
			state.date = body.date === '' ? state.date : String(body.date);
			await load();
			setStatus(booking ? 'Rezervace upravena.' : 'Rezervace zadána.');
			return;
		}
		/** @type {ApiError} */
		const failure = result.data;
		if (failure.code === 'booking.outside_working_hours' && !body.outside_working_hours) {
			if (window.confirm('Termín je mimo Pracovní dobu (nebo je ten den zavřeno). Přesto rezervaci zadat?')) {
				await submit(form, error, { ...body, outside_working_hours: true }, booking);
			}
			return;
		}
		if (failure.code === 'booking.invalid_fields' && failure.data?.errors) {
			for (const [name, code] of Object.entries(failure.data.errors)) {
				const target = form.querySelector(`[data-error-for="${name}"]`);
				if (target) target.textContent = FIELD_MESSAGES[name]?.[code] ?? (code === 'required' ? 'Vyplňte toto pole.' : code === 'too_long' ? 'Text je příliš dlouhý.' : 'Zkontrolujte toto pole.');
			}
		}
		error.textContent = message(failure.code);
	}

	/** @param {Booking} booking */
	function openCancel(booking) {
		const error = h('p', { class: 'pnk-cal__form-error', role: 'alert' });
		const reason = h('input', { id: 'pnk-f-reason', type: 'text', maxlength: 255, class: 'regular-text' });
		const form = h(
			'form',
			{ class: 'pnk-cal__form' },
			h('h2', { id: 'pnk-cal-dialog-title' }, `Zrušit rezervaci ${shortTime(booking.time_start)}, ${longDay(booking.date)}`),
			h('p', {}, `${booking.name}${booking.plate ? `, ${booking.plate}` : ''}`),
			h('p', { class: 'pnk-cal__field' }, h('label', { for: 'pnk-f-reason' }, 'Důvod (nepovinný, Zákazník ho uvidí v e‑mailu)'), reason),
			h('p', {}, booking.email ? `Zákazník dostane e‑mail o zrušení na ${booking.email}.` : 'Zákazník nemá e‑mail, dejte mu vědět telefonicky.'),
			error,
			h(
				'p',
				{ class: 'pnk-cal__actions' },
				h('button', { type: 'submit', class: 'button button-primary pnk-cal__danger' }, 'Zrušit rezervaci'),
				h('button', { type: 'button', class: 'button', onclick: () => openDetail(booking) }, 'Zpět'),
			),
		);
		form.addEventListener('submit', async (event) => {
			event.preventDefault();
			const result = await api('POST', `/admin/bookings/${booking.id}/cancel`, { reason: reason.value });
			if (!result.ok) {
				error.textContent = message(result.data?.code ?? 'network');
				return;
			}
			dialog.close();
			await load();
			setStatus('Rezervace zrušena.');
		});
		showDialog(form);
		reason.focus();
	}

	async function openRequested() {
		if (!config.booking_id) return;
		const result = await api('GET', `/admin/bookings/${config.booking_id}`);
		if (result.ok) openDetail(result.data.booking);
		else setStatus(message(result.data?.code ?? 'network'));
	}

	load().then(openRequested);
}

if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', init);
} else {
	init();
}
