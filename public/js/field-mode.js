(() => {
    'use strict';

    const DB_NAME = 'c2me-field';
    const DB_VERSION = 2;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const state = {
        snapshot: null,
        activities: [],
        pending: 0,
        syncing: false,
        search: '',
        filters: { book: '', status: '', followUp: '', overdue: false },
        sort: 'priority',
        editAccountId: null,
        editContactId: null,
        newActivityFor: null,
    };
    let db = null;

    const ACTIVITY_TYPES = [
        ['appel', 'Appel'],
        ['email', 'E-mail'],
        ['rdv', 'Rendez-vous'],
        ['visite', 'Visite'],
        ['relance', 'Relance'],
        ['note', 'Note'],
        ['tache', 'Tâche'],
    ];

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const escapeHtml = (value) =>
        String(value ?? '').replace(/[&<>"']/g, (character) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        })[character]);

    const normalize = (value) =>
        String(value ?? '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();

    const uuid = () =>
        (crypto.randomUUID
            ? crypto.randomUUID()
            : `${Date.now()}-${Math.random().toString(16).slice(2)}`);

    const formatDate = (iso) => {
        if (!iso) {
            return '';
        }

        const date = new Date(iso);

        return Number.isNaN(date.getTime())
            ? ''
            : date.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
    };

    const toLocalInput = (iso) => {
        if (!iso) {
            return '';
        }

        const date = new Date(iso);

        if (Number.isNaN(date.getTime())) {
            return '';
        }

        const pad = (value) => String(value).padStart(2, '0');

        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`;
    };

    const startOfToday = () => {
        const date = new Date();
        date.setHours(0, 0, 0, 0);

        return date;
    };

    const sameDay = (iso, date = new Date()) => {
        if (!iso) {
            return false;
        }

        const value = new Date(iso);

        return !Number.isNaN(value.getTime())
            && value.getFullYear() === date.getFullYear()
            && value.getMonth() === date.getMonth()
            && value.getDate() === date.getDate();
    };

    function openDb() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onupgradeneeded = () => {
                const database = request.result;

                if (!database.objectStoreNames.contains('snapshot')) {
                    database.createObjectStore('snapshot', { keyPath: 'key' });
                }

                if (!database.objectStoreNames.contains('activities')) {
                    database.createObjectStore('activities', { keyPath: 'id' });
                }

                if (!database.objectStoreNames.contains('ops')) {
                    database.createObjectStore('ops', { keyPath: 'op_id' });
                }

                if (!database.objectStoreNames.contains('meta')) {
                    database.createObjectStore('meta', { keyPath: 'key' });
                }
            };

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    function idb(store, mode, action) {
        return new Promise((resolve, reject) => {
            const transaction = db.transaction(store, mode);
            const request = action(transaction.objectStore(store));

            transaction.oncomplete = () => resolve(request?.result);
            transaction.onerror = () => reject(transaction.error);
        });
    }

    const putSnapshot = (snapshot) =>
        idb('snapshot', 'readwrite', (store) => store.put({ key: 'data', value: snapshot, saved_at: Date.now() }));

    const getSnapshot = () => idb('snapshot', 'readonly', (store) => store.get('data'));
    const allActivities = () => idb('activities', 'readonly', (store) => store.getAll());
    const putActivity = (activity) => idb('activities', 'readwrite', (store) => store.put(activity));
    const clearActivities = () => idb('activities', 'readwrite', (store) => store.clear());
    const allOps = () => idb('ops', 'readonly', (store) => store.getAll());
    const putOp = (op) => idb('ops', 'readwrite', (store) => store.put(op));
    const deleteOp = (opId) => idb('ops', 'readwrite', (store) => store.delete(opId));

    async function migrateLegacyQueue() {
        if (!db.objectStoreNames.contains('queue')) {
            return;
        }

        const legacy = await idb('queue', 'readonly', (store) => store.getAll());

        if (!Array.isArray(legacy) || legacy.length === 0) {
            return;
        }

        for (const item of legacy) {
            if (!item.activity_id) {
                continue;
            }

            await putOp({
                op_id: uuid(),
                type: 'update_activity',
                entity_type: 'activity',
                entity_id: Number(item.activity_id),
                client_uuid: null,
                payload: {
                    body: item.body ?? null,
                    completed_at: item.completed_at ?? null,
                },
                base_updated_at: item.base_updated_at ?? null,
                queued_at: item.queued_at ?? Date.now(),
                attempts: 0,
                status: 'pending',
                last_error: null,
            });
        }

        await idb('queue', 'readwrite', (store) => store.clear());
    }

    function accounts() {
        return Array.isArray(state.snapshot?.accounts) ? state.snapshot.accounts : [];
    }

    function contacts() {
        return accounts().flatMap((account) => account.contacts ?? []);
    }

    function findAccount(accountId) {
        return accounts().find((account) => Number(account.id) === Number(accountId)) ?? null;
    }

    function findContact(contactId) {
        return contacts().find((contact) => Number(contact.id) === Number(contactId)) ?? null;
    }

    function findActivity(activityId) {
        return state.activities.find((activity) => Number(activity.id) === Number(activityId)) ?? null;
    }

    function activitiesFor(accountId) {
        return state.activities
            .filter((activity) => Number(activity.crm_account_id) === Number(accountId))
            .sort((a, b) => {
                if (Boolean(a.completed_at) !== Boolean(b.completed_at)) {
                    return a.completed_at ? 1 : -1;
                }

                return String(a.due_at ?? '').localeCompare(String(b.due_at ?? ''));
            });
    }

    function isOverdue(activity) {
        return !activity.completed_at && activity.due_at && new Date(activity.due_at) < startOfToday();
    }

    function isToday(activity) {
        return !activity.completed_at && sameDay(activity.due_at);
    }

    function activityCounts(accountId) {
        const list = activitiesFor(accountId);

        return {
            overdue: list.filter(isOverdue).length,
            today: list.filter(isToday).length,
            pending: list.filter((activity) => activity._pending).length,
        };
    }

    function formatAddress(account) {
        return [
            account.address_line1,
            account.address_line2,
            [account.postal_code, account.city].filter(Boolean).join(' '),
            account.country && account.country !== 'FR' ? account.country : null,
        ]
            .filter(Boolean)
            .join(', ');
    }

    function matches(account, term) {
        if (term === '') {
            return true;
        }

        const accountActivities = activitiesFor(account.id);
        const haystack = [
            account.name,
            account.address,
            account.address_line1,
            account.address_line2,
            account.postal_code,
            account.city,
            account.email,
            account.phone,
            account.mobile,
            account.notes,
            ...(account.contacts ?? []).map((contact) => [
                contact.name,
                contact.first_name,
                contact.last_name,
                contact.email,
                contact.phone,
                contact.mobile,
                contact.notes,
            ].join(' ')),
            ...accountActivities.map((activity) => [activity.subject, activity.body].join(' ')),
        ]
            .join(' ');

        return normalize(haystack).includes(normalize(term));
    }

    function matchesFilters(account) {
        if (state.filters.book !== '' && account.book !== state.filters.book) {
            return false;
        }

        if (state.filters.status !== '' && account.status !== state.filters.status) {
            return false;
        }

        const followUp = account.next_follow_up_at ? new Date(account.next_follow_up_at) : null;

        if (state.filters.followUp === 'overdue' && (!followUp || followUp >= startOfToday())) {
            return false;
        }

        if (state.filters.followUp === 'today' && (!followUp || !sameDay(account.next_follow_up_at))) {
            return false;
        }

        if (state.filters.followUp === 'week' && (!followUp || followUp > new Date(Date.now() + 7 * 86400000))) {
            return false;
        }

        if (state.filters.followUp === 'none' && followUp !== null) {
            return false;
        }

        if (state.filters.overdue) {
            const counts = activityCounts(account.id);

            if (counts.overdue === 0) {
                return false;
            }
        }

        return matches(account, state.search.trim());
    }

    function priorityScore(account) {
        const counts = activityCounts(account.id);

        return counts.overdue * 1000 + counts.today * 100 + counts.pending * 10;
    }

    function sortedAccounts(list) {
        return [...list].sort((a, b) => {
            if (state.sort === 'name') {
                return String(a.name).localeCompare(String(b.name), 'fr');
            }

            if (state.sort === 'status') {
                return String(a.status_label).localeCompare(String(b.status_label), 'fr')
                    || String(a.name).localeCompare(String(b.name), 'fr');
            }

            if (state.sort === 'follow_up') {
                return String(a.next_follow_up_at ?? '9999').localeCompare(String(b.next_follow_up_at ?? '9999'))
                    || String(a.name).localeCompare(String(b.name), 'fr');
            }

            return priorityScore(b) - priorityScore(a)
                || String(a.next_follow_up_at ?? '9999').localeCompare(String(b.next_follow_up_at ?? '9999'))
                || String(a.name).localeCompare(String(b.name), 'fr');
        });
    }

    function contactRow(contact) {
        if (state.editContactId === contact.id) {
            return contactForm(contact);
        }

        return `<div class="contact">
            <div class="contact__head">
                <p>
                    <strong>${escapeHtml(contact.name)}</strong>
                    ${contact.is_primary ? ' <span class="pill">Principal</span>' : ''}
                </p>
                <button type="button" class="small" data-edit-contact="${contact.id}">Modifier</button>
            </div>
            ${contact.job_title ? `<p class="muted">${escapeHtml(contact.job_title)}</p>` : ''}
            ${contact.phone ? `<p class="muted">Tél. ${escapeHtml(contact.phone)}</p>` : ''}
            ${contact.mobile ? `<p class="muted">Mobile ${escapeHtml(contact.mobile)}</p>` : ''}
            ${contact.email ? `<p class="muted">${escapeHtml(contact.email)}</p>` : ''}
        </div>`;
    }

    function contactForm(contact) {
        return `<form class="inline-form" data-contact-form="${contact.id}">
            <h3>Modifier le contact</h3>
            <div class="grid">
                <label>Prénom
                    <input id="contact-first-${contact.id}" value="${escapeHtml(contact.first_name ?? '')}" autocomplete="given-name">
                </label>
                <label>Nom
                    <input id="contact-last-${contact.id}" value="${escapeHtml(contact.last_name ?? '')}" autocomplete="family-name">
                </label>
                <label>Fonction
                    <input id="contact-job-${contact.id}" value="${escapeHtml(contact.job_title ?? '')}">
                </label>
                <label>Téléphone
                    <input id="contact-phone-${contact.id}" value="${escapeHtml(contact.phone ?? '')}" inputmode="tel">
                </label>
                <label>Mobile
                    <input id="contact-mobile-${contact.id}" value="${escapeHtml(contact.mobile ?? '')}" inputmode="tel">
                </label>
                <label>E-mail
                    <input id="contact-email-${contact.id}" value="${escapeHtml(contact.email ?? '')}" inputmode="email">
                </label>
            </div>
            <label>Notes
                <textarea id="contact-notes-${contact.id}" rows="2">${escapeHtml(contact.notes ?? '')}</textarea>
            </label>
            <div class="form-actions">
                <button type="button" class="primary" data-save-contact="${contact.id}">Enregistrer</button>
                <button type="button" data-cancel-contact="${contact.id}">Annuler</button>
            </div>
        </form>`;
    }

    function activityRow(activity) {
        const done = Boolean(activity.completed_at);
        const due = formatDate(activity.due_at);
        const pending = activity._pending ? ' <span class="pill pill--pending">En attente</span>' : '';

        return `<div class="activity ${done ? 'activity--done' : ''}">
            <div class="activity__head">
                <strong>${escapeHtml(activity.subject)}${pending}</strong>
                <span class="muted">
                    ${escapeHtml(activity.type_label)}
                    ${due ? ' · ' + escapeHtml(due) : ' · sans échéance'}
                    ${isOverdue(activity) ? ' · en retard' : ''}
                </span>
            </div>
            <textarea id="activity-body-${activity.id}" data-activity-body="${activity.id}" rows="3" placeholder="Compte rendu…">${escapeHtml(activity.body ?? '')}</textarea>
            <label class="check">
                <input type="checkbox" id="activity-done-${activity.id}" data-activity-done="${activity.id}" ${done ? 'checked' : ''}>
                Terminée
            </label>
            <button type="button" class="primary small" data-save-activity="${activity.id}">Enregistrer</button>
        </div>`;
    }

    function newActivityForm(account) {
        const contactOptions = (account.contacts ?? [])
            .map((contact) => `<option value="${contact.id}">${escapeHtml(contact.name)}</option>`)
            .join('');

        return `<form class="inline-form new-activity" data-new-activity-form="${account.id}">
            <h3>Nouvelle activité</h3>
            <div class="grid">
                <label>Type
                    <select id="new-activity-type-${account.id}">
                        ${ACTIVITY_TYPES.map(([value, label]) => `<option value="${value}">${label}</option>`).join('')}
                    </select>
                </label>
                <label>Contact
                    <select id="new-activity-contact-${account.id}">
                        <option value="">Aucun</option>
                        ${contactOptions}
                    </select>
                </label>
                <label>Échéance
                    <input type="datetime-local" id="new-activity-due-${account.id}">
                </label>
                <label>Objet
                    <input id="new-activity-subject-${account.id}" placeholder="Visite, relance, tâche…">
                </label>
            </div>
            <label>Compte rendu
                <textarea id="new-activity-body-${account.id}" rows="3" placeholder="Notes prises sur place…"></textarea>
            </label>
            <div class="form-actions">
                <button type="button" class="primary" data-save-new-activity="${account.id}">Ajouter</button>
                <button type="button" data-cancel-new-activity="${account.id}">Annuler</button>
            </div>
        </form>`;
    }

    function quickActions(account) {
        const address = formatAddress(account);
        const actions = [];

        if (account.phone) {
            actions.push(`<a class="button small" href="tel:${escapeHtml(account.phone)}">Appeler</a>`);
        }

        if (account.mobile) {
            actions.push(`<a class="button small" href="sms:${escapeHtml(account.mobile)}">SMS</a>`);
        }

        if (account.email) {
            actions.push(`<a class="button small" href="mailto:${escapeHtml(account.email)}">E-mail</a>`);
        }

        if (address) {
            actions.push(`<a class="button small" href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}" target="_blank" rel="noopener">Plans</a>`);
        }

        return actions.length ? `<div class="quick-actions">${actions.join('')}</div>` : '';
    }

    function accountForm(account) {
        return `<form class="inline-form" data-account-form="${account.id}">
            <h3>Modifier les coordonnées</h3>
            <div class="grid">
                <label>Téléphone
                    <input id="account-phone-${account.id}" value="${escapeHtml(account.phone ?? '')}" inputmode="tel">
                </label>
                <label>Mobile
                    <input id="account-mobile-${account.id}" value="${escapeHtml(account.mobile ?? '')}" inputmode="tel">
                </label>
                <label>E-mail
                    <input id="account-email-${account.id}" value="${escapeHtml(account.email ?? '')}" inputmode="email">
                </label>
                <label>Adresse
                    <input id="account-address1-${account.id}" value="${escapeHtml(account.address_line1 ?? '')}">
                </label>
                <label>Complément
                    <input id="account-address2-${account.id}" value="${escapeHtml(account.address_line2 ?? '')}">
                </label>
                <label>Code postal
                    <input id="account-postal-${account.id}" value="${escapeHtml(account.postal_code ?? '')}" inputmode="numeric">
                </label>
                <label>Commune
                    <input id="account-city-${account.id}" value="${escapeHtml(account.city ?? '')}">
                </label>
            </div>
            <label>Notes
                <textarea id="account-notes-${account.id}" rows="3">${escapeHtml(account.notes ?? '')}</textarea>
            </label>
            <div class="form-actions">
                <button type="button" class="primary" data-save-account="${account.id}">Enregistrer</button>
                <button type="button" data-cancel-account="${account.id}">Annuler</button>
            </div>
        </form>`;
    }

    function accountCard(account) {
        const counts = activityCounts(account.id);
        const contactsHtml = (account.contacts ?? []).map(contactRow).join('');
        const activitiesHtml = activitiesFor(account.id).map(activityRow).join('');
        const pendingBadge = account._pending ? ' <span class="pill pill--pending">Modifié localement</span>' : '';
        const overdueBadge = counts.overdue > 0 ? ` <span class="pill pill--danger">${counts.overdue} en retard</span>` : '';
        const todayBadge = counts.today > 0 ? ` <span class="pill pill--warning">${counts.today} aujourd’hui</span>` : '';
        const editingContact = state.editContactId !== null
            && (account.contacts ?? []).some((contact) => Number(contact.id) === Number(state.editContactId));
        const isOpen = state.editAccountId === account.id
            || state.newActivityFor === account.id
            || editingContact;

        return `<details class="account" ${isOpen ? 'open' : ''}>
            <summary>
                <span class="account__name">${escapeHtml(account.name)}${pendingBadge}</span>
                <span class="pill">${escapeHtml(account.status_label)}</span>
                ${overdueBadge}
                ${todayBadge}
                <span class="account__meta">${escapeHtml(account.book_label)} · ${escapeHtml(account.category_label)}</span>
            </summary>
            <div class="account__body">
                ${quickActions(account)}

                ${state.editAccountId === account.id ? accountForm(account) : `
                    <div class="grid">
                        <div>
                            <h3>Coordonnées</h3>
                            ${account.address ? `<p>${escapeHtml(account.address)}</p>` : ''}
                            ${account.phone ? `<p>Tél. ${escapeHtml(account.phone)}</p>` : ''}
                            ${account.mobile ? `<p>Mobile ${escapeHtml(account.mobile)}</p>` : ''}
                            ${account.email ? `<p>${escapeHtml(account.email)}</p>` : ''}
                        </div>
                        <div>
                            <h3>Relance</h3>
                            <p>${escapeHtml(formatDate(account.next_follow_up_at) || '—')}</p>
                            ${account.notes ? `<p class="muted">${escapeHtml(account.notes)}</p>` : ''}
                            <button type="button" class="small" data-edit-account="${account.id}">Modifier les coordonnées</button>
                        </div>
                    </div>
                `}

                <h3>Contacts</h3>
                ${contactsHtml || '<p class="muted">Aucun contact.</p>'}

                <h3>Activités</h3>
                <button type="button" class="small" data-new-activity="${account.id}">Nouvelle activité</button>
                ${state.newActivityFor === account.id ? newActivityForm(account) : ''}
                ${activitiesHtml || '<p class="muted">Aucune activité.</p>'}
            </div>
        </details>`;
    }

    function populateFilters() {
        const bookSelect = $('#filter-book');
        const statusSelect = $('#filter-status');

        if (!bookSelect || !statusSelect) {
            return;
        }

        const books = new Map();
        const statuses = new Map();

        for (const account of accounts()) {
            books.set(account.book, account.book_label);
            statuses.set(account.status, account.status_label);
        }

        const currentBook = state.filters.book;
        const currentStatus = state.filters.status;

        bookSelect.innerHTML = '<option value="">Tous les carnets</option>'
            + [...books.entries()].map(([value, label]) => `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`).join('');
        statusSelect.innerHTML = '<option value="">Tous les statuts</option>'
            + [...statuses.entries()].map(([value, label]) => `<option value="${escapeHtml(value)}">${escapeHtml(label)}</option>`).join('');

        bookSelect.value = currentBook;
        statusSelect.value = currentStatus;
    }

    function render() {
        const container = $('#accounts');
        const list = sortedAccounts(accounts().filter(matchesFilters));
        const allActivities = state.activities;
        const overdueCount = allActivities.filter(isOverdue).length;
        const todayCount = allActivities.filter(isToday).length;

        $('#count').textContent = `${accounts().length} compte(s) · ${list.length} affiché(s)`;
        $('#last-sync').textContent = state.snapshot?.generated_at
            ? `Dernière synchro : ${formatDate(state.snapshot.generated_at)}`
            : 'Jamais synchronisé';
        $('#priority-summary').textContent = `${overdueCount} en retard · ${todayCount} aujourd’hui`;

        container.innerHTML = list.length
            ? list.map(accountCard).join('')
            : '<p class="empty">Aucune donnée. Connectez-vous à Internet pour synchroniser.</p>';

        renderStatus();
    }

    function renderStatus() {
        const online = navigator.onLine;

        $('#connection').textContent = online ? 'En ligne' : 'Hors ligne';
        $('#status-dot').classList.toggle('dot--off', !online);
        $('#pending').textContent = state.syncing
            ? 'Synchronisation…'
            : (state.pending > 0 ? `${state.pending} en attente` : 'À jour');
        $('#sync-button').disabled = state.syncing || !online;
    }

    function flash(message) {
        const toast = $('#toast');
        toast.textContent = message;
        toast.classList.add('toast--visible');

        window.clearTimeout(flash.timeout);
        flash.timeout = window.setTimeout(() => toast.classList.remove('toast--visible'), 3500);
    }

    async function refreshPending() {
        state.pending = (await allOps()).length;
        renderStatus();
    }

    function localActivityFromOp(op) {
        const payload = op.payload ?? {};

        return {
            id: op.local_id ?? -Math.abs(Date.now() + Math.floor(Math.random() * 1000)),
            client_uuid: op.client_uuid,
            crm_account_id: payload.crm_account_id,
            crm_contact_id: payload.crm_contact_id ?? null,
            subject: payload.subject ?? 'Activité',
            type: payload.type ?? 'note',
            type_label: ACTIVITY_TYPES.find(([value]) => value === payload.type)?.[1] ?? 'Note',
            body: payload.body ?? '',
            due_at: payload.due_at ?? null,
            completed_at: payload.completed_at ?? null,
            is_overdue: false,
            updated_at: null,
            _pending: true,
        };
    }

    async function applyQueueToState() {
        for (const op of await allOps()) {
            if (op.type === 'update_account') {
                const account = findAccount(op.entity_id);

                if (account) {
                    Object.assign(account, op.payload ?? {});
                    account.address = formatAddress(account);
                    account._pending = true;
                }
            }

            if (op.type === 'update_contact') {
                const contact = findContact(op.entity_id);

                if (contact) {
                    Object.assign(contact, op.payload ?? {});
                    contact.name = [contact.first_name, contact.last_name].filter(Boolean).join(' ');
                    contact._pending = true;
                }
            }

            if (op.type === 'update_activity') {
                const activity = findActivity(op.entity_id);

                if (activity) {
                    Object.assign(activity, op.payload ?? {});
                    activity._pending = true;
                }
            }

            if (op.type === 'create_activity') {
                let activity = state.activities.find((item) => item.client_uuid === op.client_uuid);

                if (!activity) {
                    activity = localActivityFromOp(op);
                    state.activities.push(activity);
                }

                Object.assign(activity, op.payload ?? {}, {
                    client_uuid: op.client_uuid,
                    _pending: true,
                });

                if (Number(activity.id) < 0 && op.local_id) {
                    activity.id = op.local_id;
                }
            }
        }
    }

    async function enqueueOperation(operation) {
        const ops = await allOps();
        const existing = ops.find((op) => {
            if (operation.type === 'create_activity') {
                return op.type === 'create_activity' && op.client_uuid === operation.client_uuid;
            }

            return op.type === operation.type && Number(op.entity_id) === Number(operation.entity_id);
        });

        if (existing) {
            existing.payload = { ...(existing.payload ?? {}), ...operation.payload };
            existing.queued_at = Date.now();
            existing.status = 'pending';
            existing.last_error = null;
            await putOp(existing);

            return existing;
        }

        const op = {
            op_id: uuid(),
            type: operation.type,
            entity_type: operation.entity_type,
            entity_id: operation.entity_id ?? null,
            client_uuid: operation.client_uuid ?? null,
            local_id: operation.local_id ?? null,
            payload: operation.payload ?? {},
            base_updated_at: operation.base_updated_at ?? null,
            queued_at: Date.now(),
            attempts: 0,
            status: 'pending',
            last_error: null,
        };

        await putOp(op);

        return op;
    }

    async function saveAccount(accountId) {
        const account = findAccount(accountId);

        if (!account) {
            return;
        }

        const fields = {
            email: $(`#account-email-${accountId}`)?.value.trim() || null,
            phone: $(`#account-phone-${accountId}`)?.value.trim() || null,
            mobile: $(`#account-mobile-${accountId}`)?.value.trim() || null,
            address_line1: $(`#account-address1-${accountId}`)?.value.trim() || null,
            address_line2: $(`#account-address2-${accountId}`)?.value.trim() || null,
            postal_code: $(`#account-postal-${accountId}`)?.value.trim() || null,
            city: $(`#account-city-${accountId}`)?.value.trim() || null,
            notes: $(`#account-notes-${accountId}`)?.value || null,
        };

        Object.assign(account, fields, {
            address: formatAddress({ ...account, ...fields }),
            _pending: true,
        });

        await putSnapshot(state.snapshot);
        await enqueueOperation({
            type: 'update_account',
            entity_type: 'account',
            entity_id: account.id,
            payload: fields,
            base_updated_at: account.updated_at,
        });

        state.editAccountId = null;
        await refreshPending();
        render();

        if (navigator.onLine) {
            await flushQueue();
        } else {
            flash('Coordonnées enregistrées sur cet appareil.');
        }
    }

    async function saveContact(contactId) {
        const contact = findContact(contactId);

        if (!contact) {
            return;
        }

        const fields = {
            first_name: $(`#contact-first-${contactId}`)?.value.trim() || '',
            last_name: $(`#contact-last-${contactId}`)?.value.trim() || '',
            job_title: $(`#contact-job-${contactId}`)?.value.trim() || null,
            email: $(`#contact-email-${contactId}`)?.value.trim() || null,
            phone: $(`#contact-phone-${contactId}`)?.value.trim() || null,
            mobile: $(`#contact-mobile-${contactId}`)?.value.trim() || null,
            notes: $(`#contact-notes-${contactId}`)?.value || null,
        };

        if (fields.first_name === '' || fields.last_name === '') {
            flash('Le prénom et le nom du contact sont obligatoires.');

            return;
        }

        Object.assign(contact, fields, {
            name: [fields.first_name, fields.last_name].filter(Boolean).join(' '),
            _pending: true,
        });

        await putSnapshot(state.snapshot);
        await enqueueOperation({
            type: 'update_contact',
            entity_type: 'contact',
            entity_id: contact.id,
            payload: fields,
            base_updated_at: contact.updated_at,
        });

        state.editContactId = null;
        await refreshPending();
        render();

        if (navigator.onLine) {
            await flushQueue();
        } else {
            flash('Contact enregistré sur cet appareil.');
        }
    }

    async function saveNewActivity(accountId) {
        const account = findAccount(accountId);

        if (!account) {
            return;
        }

        const type = $(`#new-activity-type-${accountId}`)?.value || 'note';
        const subject = $(`#new-activity-subject-${accountId}`)?.value.trim() ?? '';
        const contactId = $(`#new-activity-contact-${accountId}`)?.value || '';
        const dueAt = $(`#new-activity-due-${accountId}`)?.value || '';
        const body = $(`#new-activity-body-${accountId}`)?.value || '';

        if (subject === '') {
            flash('L’objet de l’activité est obligatoire.');

            return;
        }

        const localId = -Math.abs(Date.now() + Math.floor(Math.random() * 1000));
        const clientUuid = uuid();
        const payload = {
            crm_account_id: account.id,
            crm_contact_id: contactId ? Number(contactId) : null,
            type,
            subject,
            body: body || null,
            due_at: dueAt ? new Date(dueAt).toISOString() : null,
            completed_at: null,
        };
        const activity = {
            ...localActivityFromOp({ client_uuid: clientUuid, local_id: localId, payload }),
            id: localId,
        };

        state.activities.push(activity);
        state.newActivityFor = null;

        await putActivity(activity);
        await enqueueOperation({
            type: 'create_activity',
            entity_type: 'activity',
            client_uuid: clientUuid,
            local_id: localId,
            payload,
        });

        await refreshPending();
        render();

        if (navigator.onLine) {
            await flushQueue();
        } else {
            flash('Activité enregistrée sur cet appareil.');
        }
    }

    async function saveActivity(activityId) {
        const activity = findActivity(activityId);
        const body = $(`[data-activity-body="${activityId}"]`)?.value ?? '';
        const done = $(`[data-activity-done="${activityId}"]`)?.checked ?? false;

        if (!activity) {
            return;
        }

        activity.body = body;
        activity.completed_at = done ? new Date().toISOString() : null;
        activity._pending = true;

        await putActivity(activity);

        if (activity.client_uuid) {
            const op = (await allOps()).find((item) => item.type === 'create_activity' && item.client_uuid === activity.client_uuid);

            if (op) {
                op.payload = { ...(op.payload ?? {}), body: activity.body, completed_at: activity.completed_at };
                op.queued_at = Date.now();
                await putOp(op);
            } else {
                await enqueueOperation({
                    type: 'update_activity',
                    entity_type: 'activity',
                    entity_id: activity.id,
                    payload: { body: activity.body, completed_at: activity.completed_at },
                    base_updated_at: activity.updated_at,
                });
            }
        } else {
            await enqueueOperation({
                type: 'update_activity',
                entity_type: 'activity',
                entity_id: activity.id,
                payload: { body: activity.body, completed_at: activity.completed_at },
                base_updated_at: activity.updated_at,
            });
        }

        await refreshPending();
        render();

        if (navigator.onLine) {
            await flushQueue();
        } else {
            flash('Activité enregistrée sur cet appareil.');
        }
    }

    async function applyServerActivity(updated) {
        const existingIndex = state.activities.findIndex((activity) => {
            return Number(activity.id) === Number(updated.id)
                || (updated.client_uuid && activity.client_uuid === updated.client_uuid);
        });

        if (existingIndex >= 0) {
            state.activities[existingIndex] = { ...state.activities[existingIndex], ...updated, _pending: false };
        } else {
            state.activities.push({ ...updated, _pending: false });
        }

        await putActivity({ ...updated, _pending: false });
    }

    async function flushQueue() {
        if (!navigator.onLine || state.syncing) {
            return;
        }

        const queue = await allOps();

        if (queue.length === 0) {
            renderStatus();
            return;
        }

        const startedAt = Date.now();
        state.syncing = true;
        renderStatus();

        try {
            const response = await fetch('/terrain/sync', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf,
                },
                body: JSON.stringify({
                    operations: queue.map((item) => ({
                        op_id: item.op_id,
                        type: item.type,
                        entity_id: item.entity_id,
                        client_uuid: item.client_uuid,
                        payload: item.payload,
                        base_updated_at: item.base_updated_at,
                    })),
                }),
            });

            if (response.status === 401 || response.status === 419) {
                flash('Session expirée. Vos modifications restent sur cet appareil : reconnectez-vous pour les synchroniser.');

                return;
            }

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            for (const result of data.results ?? []) {
                const op = queue.find((item) => item.op_id === result.op_id);

                if (!op) {
                    continue;
                }

                if (result.status === 'ok') {
                    if (result.entity_type === 'activity' && result.server) {
                        await applyServerActivity(result.server);

                        if (op.local_id) {
                            state.activities = state.activities.filter((activity) => Number(activity.id) !== Number(op.local_id));
                            await idb('activities', 'readwrite', (store) => store.delete(op.local_id));
                        }
                    }

                    if (result.entity_type === 'account' && result.server) {
                        const account = findAccount(result.entity_id);

                        if (account) {
                            Object.assign(account, result.server, { _pending: false });
                            account.contacts = account.contacts ?? [];
                        }
                    }

                    if (result.entity_type === 'contact' && result.server) {
                        const contact = findContact(result.entity_id);

                        if (contact) {
                            Object.assign(contact, result.server, { _pending: false });
                        }
                    }

                    const current = await idb('ops', 'readonly', (store) => store.get(op.op_id));

                    if (current && (current.queued_at ?? 0) <= startedAt) {
                        await deleteOp(op.op_id);
                    }
                } else {
                    op.status = 'error';
                    op.last_error = JSON.stringify(result.errors ?? {});
                    op.attempts = (op.attempts ?? 0) + 1;
                    await putOp(op);
                }
            }

            await putSnapshot(state.snapshot);
            flash('Modifications synchronisées.');
        } catch {
            flash('Synchronisation impossible. Les modifications restent dans la file.');
        } finally {
            state.syncing = false;
            await refreshPending();
            render();
        }
    }

    async function fetchActivities() {
        const pendingActivities = state.activities.filter((activity) => activity._pending || Number(activity.id) < 0);
        const fetched = [];
        let afterId = 0;

        while (true) {
            const response = await fetch(`/terrain/activities?after_id=${afterId}&limit=200`, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });

            if (!response.ok) {
                return;
            }

            const data = await response.json();
            const page = Array.isArray(data.activities) ? data.activities : [];

            fetched.push(...page);

            if (!data.next_cursor) {
                break;
            }

            afterId = Number(data.next_cursor);
        }

        await clearActivities();
        state.activities = [];

        for (const activity of fetched) {
            state.activities.push({ ...activity, _pending: false });
            await putActivity({ ...activity, _pending: false });
        }

        for (const activity of pendingActivities) {
            const existing = state.activities.find((item) => {
                return Number(item.id) === Number(activity.id)
                    || (activity.client_uuid && item.client_uuid === activity.client_uuid);
            });

            if (existing) {
                Object.assign(existing, activity, { id: existing.id });
                await putActivity(existing);
            } else {
                state.activities.push(activity);
                await putActivity(activity);
            }
        }
    }

    async function fetchSnapshot() {
        if (!navigator.onLine) {
            return;
        }

        try {
            const response = await fetch('/terrain/snapshot', {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });

            if (!response.ok) {
                return;
            }

            state.snapshot = await response.json();
            await fetchActivities();
            await applyQueueToState();
            await putSnapshot(state.snapshot);
            populateFilters();
            render();
            await flushQueue();
        } catch {
            flash('Récupération des données impossible.');
        }
    }

    function bindEvents() {
        document.addEventListener('click', (event) => {
            const target = event.target.closest('button');

            if (!target) {
                return;
            }

            if (target.dataset.saveActivity) {
                saveActivity(Number(target.dataset.saveActivity));
            } else if (target.dataset.editAccount) {
                state.editAccountId = Number(target.dataset.editAccount);
                render();
            } else if (target.dataset.cancelAccount) {
                state.editAccountId = null;
                render();
            } else if (target.dataset.saveAccount) {
                saveAccount(Number(target.dataset.saveAccount));
            } else if (target.dataset.editContact) {
                state.editContactId = Number(target.dataset.editContact);
                render();
            } else if (target.dataset.cancelContact) {
                state.editContactId = null;
                render();
            } else if (target.dataset.saveContact) {
                saveContact(Number(target.dataset.saveContact));
            } else if (target.dataset.newActivity) {
                state.newActivityFor = Number(target.dataset.newActivity);
                state.editAccountId = null;
                render();
            } else if (target.dataset.cancelNewActivity) {
                state.newActivityFor = null;
                render();
            } else if (target.dataset.saveNewActivity) {
                saveNewActivity(Number(target.dataset.saveNewActivity));
            }
        });

        $('#search').addEventListener('input', (event) => {
            state.search = event.target.value;
            render();
        });

        $('#filter-book').addEventListener('change', (event) => {
            state.filters.book = event.target.value;
            render();
        });

        $('#filter-status').addEventListener('change', (event) => {
            state.filters.status = event.target.value;
            render();
        });

        $('#filter-follow-up').addEventListener('change', (event) => {
            state.filters.followUp = event.target.value;
            render();
        });

        $('#filter-overdue').addEventListener('change', (event) => {
            state.filters.overdue = event.target.checked;
            render();
        });

        $('#sort').addEventListener('change', (event) => {
            state.sort = event.target.value;
            render();
        });

        $('#sync-button').addEventListener('click', async () => {
            await fetchSnapshot();
            await flushQueue();
        });

        $('#clear-button').addEventListener('click', async () => {
            const pending = (await allOps()).length;
            const message = pending > 0
                ? `${pending} modification(s) ne sont pas encore synchronisées. Tout effacer quand même ?`
                : 'Effacer les données locales de cet appareil ?';

            if (!window.confirm(message)) {
                return;
            }

            indexedDB.deleteDatabase(DB_NAME);
            window.location.reload();
        });

        window.addEventListener('online', () => {
            renderStatus();
            fetchSnapshot();
        });

        window.addEventListener('offline', renderStatus);
    }

    async function init() {
        try {
            db = await openDb();
        } catch {
            flash('Stockage local indisponible : le mode hors ligne est inactif.');
            return;
        }

        await migrateLegacyQueue();

        const stored = await getSnapshot();

        if (stored?.value) {
            state.snapshot = stored.value;
        }

        state.activities = await allActivities();
        await applyQueueToState();
        await refreshPending();
        populateFilters();
        bindEvents();
        render();

        if (navigator.onLine) {
            await fetchSnapshot();
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
