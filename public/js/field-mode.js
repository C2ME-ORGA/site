(() => {
    'use strict';

    const DB_NAME = 'c2me-field';
    const DB_VERSION = 3;
    const BACKOFF_STEPS = [5000, 30000, 120000, 600000];
    const MAX_SYNC_BATCH = 50;
    const MAX_PHOTOS = 5;
    const MAX_PHOTO_BYTES = 5 * 1024 * 1024;
    const TOUR_CHUNK_SIZE = 10;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const loginUrl = document.querySelector('meta[name="login-url"]')?.content ?? '/admin/login';
    const state = {
        snapshot: null,
        activities: [],
        photos: [],
        queue: [],
        tour: [],
        tourDate: null,
        tab: 'accounts',
        theme: 'light',
        openAccounts: new Set(),
        pending: 0,
        conflicts: 0,
        errors: 0,
        syncing: false,
        syncingPhotos: false,
        sessionExpired: false,
        search: '',
        filters: { book: '', status: '', followUp: '', overdue: false },
        sort: 'priority',
        editAccountId: null,
        editContactId: null,
        newActivityFor: null,
        mergeOpId: null,
        retryTimer: null,
        photoRetryTimer: null,
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

    const FIELD_LABELS = {
        body: 'Compte rendu',
        completed_at: 'Clôture',
        subject: 'Objet',
        due_at: 'Échéance',
        email: 'E-mail',
        phone: 'Téléphone',
        mobile: 'Mobile',
        address_line1: 'Adresse',
        address_line2: 'Complément',
        postal_code: 'Code postal',
        city: 'Commune',
        notes: 'Notes',
        first_name: 'Prénom',
        last_name: 'Nom',
        job_title: 'Fonction',
    };

    const isPendingOp = (op) => (op.status ?? 'pending') === 'pending';
    const isConflictOp = (op) => op.status === 'conflict';
    const isErrorOp = (op) => op.status === 'error';

    const formatBytes = (bytes) => {
        if (!Number.isFinite(bytes) || bytes <= 0) {
            return '0 o';
        }

        const units = ['o', 'Ko', 'Mo', 'Go'];
        const exponent = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);

        return `${(bytes / (1024 ** exponent)).toFixed(exponent === 0 ? 0 : 1)} ${units[exponent]}`;
    };

    const emptyValue = (value) => (value === null || value === undefined || value === '' ? '—' : String(value));

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

                if (!database.objectStoreNames.contains('photos')) {
                    database.createObjectStore('photos', { keyPath: 'photo_id' });
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
    const allPhotos = () => idb('photos', 'readonly', (store) => store.getAll());
    const putPhoto = (photo) => idb('photos', 'readwrite', (store) => store.put(photo));
    const deleteStoredPhoto = (photoId) => idb('photos', 'readwrite', (store) => store.delete(photoId));
    const getMeta = (key) => idb('meta', 'readonly', (store) => store.get(key));
    const putMeta = (key, value) => idb('meta', 'readwrite', (store) => store.put({ key, value }));

    const photoUrls = new Map();

    function photoPreviewUrl(photo) {
        if (!photoUrls.has(photo.photo_id)) {
            photoUrls.set(photo.photo_id, URL.createObjectURL(photo.blob));
        }

        return photoUrls.get(photo.photo_id);
    }

    function revokePhotoUrl(photoId) {
        const url = photoUrls.get(photoId);

        if (url) {
            URL.revokeObjectURL(url);
            photoUrls.delete(photoId);
        }
    }

    function loadImage(file) {
        if ('createImageBitmap' in window) {
            return createImageBitmap(file).catch(() => loadImageElement(file));
        }

        return loadImageElement(file);
    }

    function loadImageElement(file) {
        return new Promise((resolve, reject) => {
            const url = URL.createObjectURL(file);
            const image = new Image();

            image.onload = () => {
                URL.revokeObjectURL(url);
                resolve(image);
            };

            image.onerror = () => {
                URL.revokeObjectURL(url);
                reject(new Error('Photo illisible'));
            };

            image.src = url;
        });
    }

    /**
     * Réduit la photo à 1600 px de côté maximum et la réencode en JPEG
     * avant stockage local : les photos brutes de téléphone dépassent
     * souvent la limite d'upload et saturent le quota IndexedDB.
     */
    async function compressImage(file, maxDimension = 1600, quality = 0.82) {
        const source = await loadImage(file);
        const width = source.width || source.naturalWidth || 0;
        const height = source.height || source.naturalHeight || 0;

        if (width === 0 || height === 0) {
            throw new Error('Photo illisible');
        }

        const scale = Math.min(1, maxDimension / Math.max(width, height));
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(width * scale));
        canvas.height = Math.max(1, Math.round(height * scale));

        const context = canvas.getContext('2d');

        if (!context) {
            throw new Error('Compression impossible');
        }

        // Fond blanc : le JPEG ne gère pas la transparence des PNG.
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(source, 0, 0, canvas.width, canvas.height);

        if (typeof source.close === 'function') {
            source.close();
        }

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));

        if (!blob) {
            throw new Error('Compression impossible');
        }

        return blob;
    }

    function pendingPhotosFor(activity) {
        return state.photos.filter((photo) => {
            if (activity.client_uuid && photo.activity_client_uuid === activity.client_uuid) {
                return true;
            }

            return Number(photo.activity_id) === Number(activity.id);
        });
    }

    function photoGallery(activity) {
        const server = Array.isArray(activity.attachments) ? activity.attachments : [];
        const local = pendingPhotosFor(activity);
        const total = server.length + local.length;
        const remaining = Math.max(0, MAX_PHOTOS - total);

        const serverHtml = server.map((attachment) => `<figure class="photo">
            <a href="${escapeHtml(attachment.url)}" target="_blank" rel="noopener">
                <img src="${escapeHtml(attachment.url)}" alt="${escapeHtml(attachment.original_name)}" loading="lazy">
            </a>
            <figcaption>${escapeHtml(attachment.human_size ?? '')}</figcaption>
        </figure>`).join('');

        const localHtml = local.map((photo) => `<figure class="photo photo--pending">
            <img src="${photoPreviewUrl(photo)}" alt="Photo en attente d’envoi" loading="lazy">
            <button type="button" class="photo__remove" data-photo-remove="${escapeHtml(photo.photo_id)}" aria-label="Retirer cette photo">×</button>
            <figcaption>${photo.status === 'error' ? 'Échec de l’envoi' : 'En attente'} · ${formatBytes(photo.size)}</figcaption>
        </figure>`).join('');

        const addButton = remaining > 0
            ? `<label class="photo-add">
                <span>+ Photo</span>
                <input type="file" accept="image/*" data-photo-input="${activity.id}" hidden>
            </label>`
            : '';

        return `<div class="photos">
            <div class="photos__grid">${serverHtml}${localHtml}${addButton}</div>
            <p class="muted photos__count">${total}/${MAX_PHOTOS} photo(s)${remaining === 0 ? ' · maximum atteint' : ''}</p>
        </div>`;
    }

    function todayKey() {
        const date = new Date();
        const pad = (value) => String(value).padStart(2, '0');

        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    }

    function tourAddress(account) {
        return formatAddress(account) || account.name || '';
    }

    function tourPosition(accountId) {
        return state.tour.findIndex((id) => Number(id) === Number(accountId));
    }

    function tourRoutes() {
        const stops = state.tour
            .map((id) => findAccount(id))
            .filter(Boolean);

        const routes = [];

        for (let index = 0; index < stops.length; index += TOUR_CHUNK_SIZE) {
            const chunk = stops.slice(index, index + TOUR_CHUNK_SIZE);
            const addresses = chunk.map(tourAddress).filter(Boolean);

            if (addresses.length === 0) {
                continue;
            }

            const destination = addresses[addresses.length - 1];
            const waypoints = addresses.slice(0, -1);
            const params = new URLSearchParams({
                api: '1',
                destination,
                travelmode: 'driving',
            });

            if (waypoints.length > 0) {
                params.set('waypoints', waypoints.join('|'));
            }

            routes.push({
                label: chunk.length > 1
                    ? `Itinéraire ${routes.length + 1} · étapes ${index + 1} à ${index + chunk.length}`
                    : `Itinéraire ${routes.length + 1} · étape ${index + 1}`,
                url: `https://www.google.com/maps/dir/?${params.toString()}`,
            });
        }

        return routes;
    }

    async function saveTour() {
        await putMeta('tour', { date: todayKey(), ids: state.tour });
    }

    async function loadTour() {
        const record = await getMeta('tour');
        const value = record?.value;
        state.tourDate = todayKey();

        if (!value || value.date !== state.tourDate || !Array.isArray(value.ids)) {
            state.tour = [];

            if (value) {
                await saveTour();
            }

            return;
        }

        state.tour = value.ids
            .map(Number)
            .filter((id) => findAccount(id) !== null);
    }

    async function toggleTour(accountId) {
        const id = Number(accountId);
        const position = tourPosition(id);

        if (position >= 0) {
            state.tour.splice(position, 1);
        } else {
            state.tour.push(id);
        }

        await saveTour();
        render();
    }

    async function moveTour(accountId, offset) {
        const position = tourPosition(accountId);
        const target = position + offset;

        if (position < 0 || target < 0 || target >= state.tour.length) {
            return;
        }

        const [moved] = state.tour.splice(position, 1);
        state.tour.splice(target, 0, moved);

        await saveTour();
        render();
    }

    async function removeFromTour(accountId) {
        const position = tourPosition(accountId);

        if (position < 0) {
            return;
        }

        state.tour.splice(position, 1);
        await saveTour();
        render();
    }

    async function resetTour() {
        if (state.tour.length > 0 && !window.confirm('Vider la tournée du jour ?')) {
            return;
        }

        state.tour = [];
        await saveTour();
        render();
    }

    function renderTour() {
        const section = $('#tour-list');
        const count = $('#tour-count');
        const routes = $('#tour-routes');

        if (!section) {
            return;
        }

        const stops = state.tour
            .map((id) => findAccount(id))
            .filter(Boolean);

        if (count) {
            count.textContent = `${stops.length} étape(s)`;
        }

        const badge = $('#tab-tour-badge');

        if (badge) {
            badge.hidden = stops.length === 0;
            badge.textContent = String(stops.length);
        }

        section.innerHTML = stops.length
            ? stops.map((account, index) => `<li class="tour__item">
                <span class="tour__index">${index + 1}</span>
                <span class="tour__label">
                    <strong>${escapeHtml(account.name)}</strong>
                    <span class="muted">${escapeHtml(tourAddress(account) || 'Adresse inconnue')}</span>
                </span>
                <span class="tour__controls">
                    <button type="button" class="small" data-tour-up="${account.id}" aria-label="Monter" ${index === 0 ? 'disabled' : ''}>↑</button>
                    <button type="button" class="small" data-tour-down="${account.id}" aria-label="Descendre" ${index === stops.length - 1 ? 'disabled' : ''}>↓</button>
                    <button type="button" class="small danger" data-tour-remove="${account.id}" aria-label="Retirer">×</button>
                </span>
            </li>`).join('')
            : '<li class="muted">Aucune étape. Ajoutez des comptes depuis la liste ci-dessous.</li>';

        if (!routes) {
            return;
        }

        const links = tourRoutes();

        routes.innerHTML = links.map((route) => `<a class="button small primary" href="${escapeHtml(route.url)}" target="_blank" rel="noopener">${escapeHtml(route.label)}</a>`).join('');
    }

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
        const pending = activity._conflict
            ? ' <span class="pill pill--danger">Conflit</span>'
            : (activity._error
                ? ' <span class="pill pill--danger">Erreur</span>'
                : (activity._pending ? ' <span class="pill pill--pending">En attente</span>' : ''));

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
            ${photoGallery(activity)}
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
        const phone = account.phone || account.mobile;

        if (phone) {
            actions.push(`<a class="button small" href="tel:${escapeHtml(phone)}">Appeler</a>`);
        }

        if (account.mobile) {
            actions.push(`<a class="button small" href="sms:${escapeHtml(account.mobile)}">SMS</a>`);
        }

        if (account.email) {
            actions.push(`<a class="button small" href="mailto:${escapeHtml(account.email)}">E-mail</a>`);
        }

        if (address) {
            actions.push(`<a class="button small" href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(address)}" target="_blank" rel="noopener">Plans</a>`);
            actions.push(`<button type="button" class="small" data-copy-address="${escapeHtml(address)}">Copier l’adresse</button>`);
        }

        const inTour = tourPosition(account.id) >= 0;

        actions.push(`<button type="button" class="small ${inTour ? 'danger' : ''}" data-tour-toggle="${account.id}">${inTour ? 'Retirer de la tournée' : 'Ajouter à la tournée'}</button>`);

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
        const pendingBadge = account._conflict
            ? ' <span class="pill pill--danger">Conflit</span>'
            : (account._error
                ? ' <span class="pill pill--danger">Erreur</span>'
                : (account._pending ? ' <span class="pill pill--pending">Modifié localement</span>' : ''));
        const overdueBadge = counts.overdue > 0 ? ` <span class="pill pill--danger">${counts.overdue} en retard</span>` : '';
        const todayBadge = counts.today > 0 ? ` <span class="pill pill--warning">${counts.today} aujourd’hui</span>` : '';
        const tourIndex = tourPosition(account.id);
        const tourBadge = tourIndex >= 0 ? ` <span class="pill pill--tour">Tournée ${tourIndex + 1}</span>` : '';
        const editingContact = state.editContactId !== null
            && (account.contacts ?? []).some((contact) => Number(contact.id) === Number(state.editContactId));
        const isOpen = state.openAccounts.has(Number(account.id))
            || state.editAccountId === account.id
            || state.newActivityFor === account.id
            || editingContact;

        return `<details class="account" data-account-id="${account.id}" ${isOpen ? 'open' : ''}>
            <summary>
                <span class="account__name">${escapeHtml(account.name)}${pendingBadge}</span>
                <span class="pill">${escapeHtml(account.status_label)}</span>
                ${overdueBadge}
                ${todayBadge}
                ${tourBadge}
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
        renderTour();
    }

    function renderStatus() {
        const online = navigator.onLine;
        const parts = [];

        if (state.syncing) {
            parts.push('Synchronisation…');
        }

        if (state.pending > 0) {
            parts.push(`${state.pending} en attente`);
        }

        if (state.conflicts > 0) {
            parts.push(`${state.conflicts} conflit${state.conflicts > 1 ? 's' : ''}`);
        }

        if (state.errors > 0) {
            parts.push(`${state.errors} erreur${state.errors > 1 ? 's' : ''}`);
        }

        $('#connection').textContent = online ? 'En ligne' : 'Hors ligne';
        $('#status-dot').classList.toggle('dot--off', !online);
        $('#pending').textContent = parts.length ? parts.join(' · ') : 'À jour';
        $('#sync-button').disabled = state.syncing || !online;

        const banner = $('#session-banner');

        if (banner) {
            banner.hidden = !state.sessionExpired;
        }
    }

    function flash(message) {
        const toast = $('#toast');
        toast.textContent = message;
        toast.classList.add('toast--visible');

        window.clearTimeout(flash.timeout);
        flash.timeout = window.setTimeout(() => toast.classList.remove('toast--visible'), 3500);
    }

    function storedTheme() {
        try {
            const value = localStorage.getItem('c2me-field-theme');

            return value === 'light' || value === 'dark' ? value : null;
        } catch {
            return null;
        }
    }

    function applyTheme(theme, { persist = false } = {}) {
        const next = theme === 'dark' ? 'dark' : 'light';
        state.theme = next;
        document.documentElement.dataset.theme = next;

        const button = $('#theme-toggle');

        if (button) {
            button.setAttribute('aria-pressed', next === 'dark' ? 'true' : 'false');
            button.setAttribute('aria-label', next === 'dark' ? 'Passer en mode clair' : 'Passer en mode sombre');
        }

        const meta = document.querySelector('meta[name="theme-color"]');

        if (meta) {
            meta.content = next === 'dark' ? '#0b1418' : '#0e8577';
        }

        if (persist) {
            try {
                localStorage.setItem('c2me-field-theme', next);
            } catch {
                // Le thème reste appliqué pour la session en cours.
            }
        }
    }

    function initTheme() {
        applyTheme(document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light');

        const media = window.matchMedia('(prefers-color-scheme: dark)');

        media.addEventListener?.('change', (event) => {
            if (!storedTheme()) {
                applyTheme(event.matches ? 'dark' : 'light');
            }
        });
    }

    function syncTabBadges() {
        const blocked = state.conflicts + state.errors;
        const accountsBadge = $('#tab-accounts-badge');

        if (accountsBadge) {
            accountsBadge.hidden = blocked === 0;
            accountsBadge.textContent = String(blocked);
        }

        const dataBadge = $('#tab-data-badge');
        const pending = state.pending + blocked;

        if (dataBadge) {
            dataBadge.hidden = pending === 0;
            dataBadge.textContent = String(pending);
        }
    }

    function setTab(tab, { focus = false } = {}) {
        const tabs = $$('.tab');
        const valid = tabs.some((button) => button.dataset.tab === tab) ? tab : 'accounts';
        state.tab = valid;

        for (const button of tabs) {
            const selected = button.dataset.tab === valid;
            button.setAttribute('aria-selected', selected ? 'true' : 'false');
            button.tabIndex = selected ? 0 : -1;

            if (selected && focus) {
                button.focus();
            }
        }

        for (const panel of $$('[data-tab-panel]')) {
            panel.hidden = window.matchMedia('(max-width: 899px)').matches
                && panel.dataset.tabPanel !== valid;
        }

        if (valid === 'data') {
            const panel = $('#data-panel');

            if (panel) {
                panel.open = true;
            }
        }

        try {
            sessionStorage.setItem('c2me-field-tab', valid);
        } catch {
            // La sélection d'onglet n'est pas mémorisée.
        }
    }

    function initTab() {
        let stored = null;

        try {
            stored = sessionStorage.getItem('c2me-field-tab');
        } catch {
            stored = null;
        }

        setTab(stored ?? 'accounts');
    }

    async function refreshPending() {
        state.queue = await allOps();
        state.pending = state.queue.filter(isPendingOp).length;
        state.conflicts = state.queue.filter(isConflictOp).length;
        state.errors = state.queue.filter(isErrorOp).length;

        renderStatus();
        renderConflicts();
        renderStorage();
        syncTabBadges();
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
        for (const account of accounts()) {
            account._pending = false;
            account._conflict = false;
            account._error = false;
        }

        for (const contact of contacts()) {
            contact._pending = false;
            contact._conflict = false;
            contact._error = false;
        }

        for (const activity of state.activities) {
            activity._pending = false;
            activity._conflict = false;
            activity._error = false;
        }

        for (const op of await allOps()) {
            if (op.type === 'update_account') {
                const account = findAccount(op.entity_id);

                if (account) {
                    Object.assign(account, op.payload ?? {});
                    account.address = formatAddress(account);
                    account._pending = true;
                    account._conflict = isConflictOp(op);
                    account._error = isErrorOp(op);
                }
            }

            if (op.type === 'update_contact') {
                const contact = findContact(op.entity_id);

                if (contact) {
                    Object.assign(contact, op.payload ?? {});
                    contact.name = [contact.first_name, contact.last_name].filter(Boolean).join(' ');
                    contact._pending = true;
                    contact._conflict = isConflictOp(op);
                    contact._error = isErrorOp(op);
                }
            }

            if (op.type === 'update_activity') {
                const activity = findActivity(op.entity_id);

                if (activity) {
                    Object.assign(activity, op.payload ?? {});
                    activity._pending = true;
                    activity._conflict = isConflictOp(op);
                    activity._error = isErrorOp(op);
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
                    _conflict: isConflictOp(op),
                    _error: isErrorOp(op),
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
            existing.attempts = 0;
            existing.next_attempt_at = 0;
            existing.last_error = null;
            delete existing.server;
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
            next_attempt_at: 0,
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

    function backoffDelay(attempts) {
        return BACKOFF_STEPS[Math.min(Math.max(attempts - 1, 0), BACKOFF_STEPS.length - 1)];
    }

    function scheduleRetry() {
        window.clearTimeout(state.retryTimer);

        if (!navigator.onLine || state.sessionExpired) {
            return;
        }

        const upcoming = state.queue
            .filter(isPendingOp)
            .map((op) => Number(op.next_attempt_at ?? 0))
            .filter((value) => value > 0);

        if (upcoming.length === 0) {
            return;
        }

        const delay = Math.max(0, Math.min(...upcoming) - Date.now());
        state.retryTimer = window.setTimeout(() => flushQueue(), delay + 250);
    }

    function failAttempt(op) {
        op.attempts = (op.attempts ?? 0) + 1;
        op.status = 'pending';
        op.next_attempt_at = Date.now() + backoffDelay(op.attempts);

        return op;
    }

    function markSessionExpired(expired) {
        state.sessionExpired = expired;
        renderStatus();

        if (expired) {
            flash('Session expirée. Vos modifications restent sur cet appareil : reconnectez-vous pour les synchroniser.');
        }
    }

    async function applyServerResult(op, result) {
        if (!result.server) {
            return;
        }

        if (result.entity_type === 'activity') {
            await applyServerActivity(result.server);
            await reassignPendingPhotos(result.server, op.local_id, op.client_uuid);

            if (op.local_id) {
                state.activities = state.activities.filter((activity) => Number(activity.id) !== Number(op.local_id));
                await idb('activities', 'readwrite', (store) => store.delete(op.local_id));
            }
        }

        if (result.entity_type === 'account') {
            const account = findAccount(result.entity_id);

            if (account) {
                Object.assign(account, result.server, {
                    _pending: false,
                    _conflict: false,
                    _error: false,
                });
                account.contacts = account.contacts ?? [];
            }
        }

        if (result.entity_type === 'contact') {
            const contact = findContact(result.entity_id);

            if (contact) {
                Object.assign(contact, result.server, {
                    _pending: false,
                    _conflict: false,
                    _error: false,
                });
            }
        }
    }

    async function flushQueue({ force = false } = {}) {
        if (state.syncing || !navigator.onLine) {
            return;
        }

        if (state.sessionExpired && !force) {
            return;
        }

        const now = Date.now();
        const queue = state.queue
            .filter((op) => isPendingOp(op) && (force || Number(op.next_attempt_at ?? 0) <= now))
            .slice(0, MAX_SYNC_BATCH);

        if (queue.length === 0) {
            scheduleRetry();
            renderStatus();
            await flushPhotos();

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
                markSessionExpired(true);

                return;
            }

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();
            markSessionExpired(false);

            const handled = new Set();

            for (const result of data.results ?? []) {
                const op = queue.find((item) => item.op_id === result.op_id);

                if (!op) {
                    continue;
                }

                handled.add(op.op_id);

                if (result.status === 'ok') {
                    await applyServerResult(op, result);

                    const current = await idb('ops', 'readonly', (store) => store.get(op.op_id));

                    if (current && (current.queued_at ?? 0) <= startedAt) {
                        await deleteOp(op.op_id);
                    }
                } else if (result.status === 'conflict') {
                    op.status = 'conflict';
                    op.server = result.server ?? null;
                    op.server_updated_at = result.updated_at ?? null;
                    op.last_error = null;
                    op.attempts = (op.attempts ?? 0) + 1;
                    op.next_attempt_at = 0;
                    await putOp(op);
                } else {
                    op.status = 'error';
                    op.last_error = JSON.stringify(result.errors ?? {});
                    op.attempts = (op.attempts ?? 0) + 1;
                    op.next_attempt_at = 0;
                    await putOp(op);
                }
            }

            for (const op of queue) {
                if (!handled.has(op.op_id)) {
                    await putOp(failAttempt(op));
                }
            }

            await putSnapshot(state.snapshot);

            const blocked = (await allOps()).some((op) => !isPendingOp(op));

            flash(blocked
                ? 'Synchronisation partielle : des opérations demandent votre attention.'
                : 'Modifications synchronisées.');
        } catch {
            for (const op of queue) {
                const current = await idb('ops', 'readonly', (store) => store.get(op.op_id));

                if (current) {
                    await putOp(failAttempt(current));
                }
            }

            flash('Synchronisation impossible. Nouvelle tentative planifiée automatiquement.');
        } finally {
            state.syncing = false;
            await applyQueueToState();
            await refreshPending();
            render();
            scheduleRetry();
            await flushPhotos();
        }
    }

    async function reassignPendingPhotos(serverActivity, localId, clientUuid) {
        const photos = await allPhotos();
        let changed = false;

        for (const photo of photos) {
            const sameLocalId = localId && Number(photo.activity_id) === Number(localId);
            const sameClientUuid = clientUuid && photo.activity_client_uuid === clientUuid;

            if (!sameLocalId && !sameClientUuid) {
                continue;
            }

            photo.activity_id = Number(serverActivity.id);
            photo.activity_client_uuid = serverActivity.client_uuid ?? clientUuid ?? null;
            await putPhoto(photo);
            changed = true;
        }

        if (changed) {
            state.photos = await allPhotos();
        }
    }

    function failPhotoAttempt(photo) {
        photo.attempts = (photo.attempts ?? 0) + 1;
        photo.status = 'pending';
        photo.next_attempt_at = Date.now() + backoffDelay(photo.attempts);

        return photo;
    }

    function schedulePhotoRetry() {
        window.clearTimeout(state.photoRetryTimer);

        if (!navigator.onLine || state.sessionExpired) {
            return;
        }

        const upcoming = state.photos
            .filter((photo) => photo.status !== 'error')
            .map((photo) => Number(photo.next_attempt_at ?? 0))
            .filter((value) => value > 0);

        if (upcoming.length === 0) {
            return;
        }

        const delay = Math.max(0, Math.min(...upcoming) - Date.now());
        state.photoRetryTimer = window.setTimeout(() => flushPhotos(), delay + 250);
    }

    async function photoErrorMessage(response) {
        try {
            const data = await response.json();

            if (data?.errors) {
                return Object.values(data.errors).flat().join(' ');
            }

            return data?.message ?? `HTTP ${response.status}`;
        } catch {
            return `HTTP ${response.status}`;
        }
    }

    async function flushPhotos({ force = false } = {}) {
        if (state.syncingPhotos || !navigator.onLine || state.sessionExpired) {
            return;
        }

        const stored = await allPhotos();
        state.photos = stored;

        const now = Date.now();
        const queue = stored.filter((photo) => {
            const activity = findActivity(photo.activity_id);

            return activity !== null
                && Number(activity.id) > 0
                && (force || Number(photo.next_attempt_at ?? 0) <= now);
        });

        if (queue.length === 0) {
            schedulePhotoRetry();

            return;
        }

        state.syncingPhotos = true;
        let changed = false;

        try {
            for (const photo of queue) {
                const activity = findActivity(photo.activity_id);

                if (!activity || Number(activity.id) <= 0) {
                    continue;
                }

                const form = new FormData();
                form.append('photo', photo.blob, photo.original_name || 'photo.jpg');
                form.append('client_uuid', photo.client_uuid);

                const response = await fetch(`/terrain/activities/${activity.id}/attachments`, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: form,
                });

                if (response.status === 401 || response.status === 419) {
                    markSessionExpired(true);

                    break;
                }

                if (response.ok) {
                    const data = await response.json();
                    const attachment = data.attachment ?? null;

                    await deleteStoredPhoto(photo.photo_id);
                    revokePhotoUrl(photo.photo_id);

                    if (attachment) {
                        activity.attachments = [
                            ...(activity.attachments ?? []).filter((item) => Number(item.id) !== Number(attachment.id)),
                            attachment,
                        ];
                        await putActivity(activity);
                    }

                    changed = true;

                    continue;
                }

                if (response.status === 404 || response.status === 409 || response.status === 422) {
                    photo.status = 'error';
                    photo.attempts = (photo.attempts ?? 0) + 1;
                    photo.next_attempt_at = 0;
                    photo.last_error = await photoErrorMessage(response);
                    await putPhoto(photo);
                    changed = true;

                    continue;
                }

                await putPhoto(failPhotoAttempt(photo));
                changed = true;
            }
        } catch {
            for (const photo of queue) {
                const current = await idb('photos', 'readonly', (store) => store.get(photo.photo_id));

                if (current) {
                    await putPhoto(failPhotoAttempt(current));
                }
            }

            flash('Envoi des photos impossible. Nouvelle tentative planifiée.');
            changed = true;
        } finally {
            state.syncingPhotos = false;
            state.photos = await allPhotos();

            if (changed) {
                await putSnapshot(state.snapshot);
                render();
            }

            schedulePhotoRetry();
        }
    }

    async function addPhoto(activityId, file) {
        const activity = findActivity(activityId);

        if (!activity) {
            flash('Activité introuvable.');

            return;
        }

        const count = (activity.attachments ?? []).length + pendingPhotosFor(activity).length;

        if (count >= MAX_PHOTOS) {
            flash(`Maximum de ${MAX_PHOTOS} photos par activité.`);

            return;
        }

        if (!file.type.startsWith('image/')) {
            flash('Seules les images sont acceptées.');

            return;
        }

        if (file.size > MAX_PHOTO_BYTES * 4) {
            flash('Photo trop lourde : 20 Mo maximum avant compression.');

            return;
        }

        flash('Compression de la photo…');

        let blob = null;

        try {
            blob = await compressImage(file);
        } catch {
            flash('Cette photo ne peut pas être lue par le navigateur.');

            return;
        }

        if (blob.size > MAX_PHOTO_BYTES) {
            try {
                blob = await compressImage(file, 1280, 0.7);
            } catch {
                // On garde le premier résultat : la taille sera vérifiée juste après.
            }
        }

        if (blob.size > MAX_PHOTO_BYTES) {
            flash('Photo trop lourde même après compression.');

            return;
        }

        const photoId = uuid();
        const baseName = (file.name || 'photo').replace(/\.[^.]+$/, '');

        await putPhoto({
            photo_id: photoId,
            client_uuid: photoId,
            activity_id: Number(activity.id),
            activity_client_uuid: activity.client_uuid ?? null,
            account_id: Number(activity.crm_account_id),
            blob,
            original_name: `${baseName}.jpg`,
            mime_type: 'image/jpeg',
            size: blob.size,
            created_at: Date.now(),
            attempts: 0,
            next_attempt_at: 0,
            status: 'pending',
            last_error: null,
        });

        state.photos = await allPhotos();
        render();
        flash(`Photo ajoutée (${formatBytes(blob.size)}). Envoi automatique.`);

        if (navigator.onLine) {
            await flushPhotos();
        }
    }

    async function removePhoto(photoId) {
        const photo = state.photos.find((item) => item.photo_id === photoId);

        if (!photo) {
            return;
        }

        if (!window.confirm('Retirer cette photo non envoyée ?')) {
            return;
        }

        await deleteStoredPhoto(photoId);
        revokePhotoUrl(photoId);
        state.photos = await allPhotos();
        render();
        flash('Photo retirée.');
    }

    function entityForOp(op) {
        if (op.entity_type === 'account') {
            return findAccount(op.entity_id);
        }

        if (op.entity_type === 'contact') {
            return findContact(op.entity_id);
        }

        return findActivity(op.entity_id);
    }

    function entityLabel(op) {
        const entity = entityForOp(op);

        if (!entity) {
            return `Élément #${op.entity_id ?? '—'}`;
        }

        if (op.entity_type === 'activity') {
            return entity.subject || `Activité #${op.entity_id}`;
        }

        return entity.name || `Élément #${op.entity_id}`;
    }

    function diffFields(op) {
        const local = op.payload ?? {};
        const server = op.server ?? {};

        return Object.keys(local).filter((key) => String(local[key] ?? '') !== String(server[key] ?? ''));
    }

    function displayFieldValue(field, value) {
        if (field === 'completed_at' || field === 'due_at') {
            return value ? formatDate(value) : '—';
        }

        return emptyValue(value);
    }

    function conflictCard(op) {
        const fields = diffFields(op);
        const merging = state.mergeOpId === op.op_id;

        const rows = fields.map((field) => {
            const label = FIELD_LABELS[field] ?? field;

            if (!merging) {
                return `<li><strong>${escapeHtml(label)}</strong> : local « ${escapeHtml(displayFieldValue(field, op.payload?.[field]))} » · serveur « ${escapeHtml(displayFieldValue(field, op.server?.[field]))} »</li>`;
            }

            const name = escapeHtml(`merge-${op.op_id}-${field}`);

            return `<li class="merge-row">
                <strong>${escapeHtml(label)}</strong>
                <label><input type="radio" name="${name}" value="local" checked> Local : ${escapeHtml(displayFieldValue(field, op.payload?.[field]))}</label>
                <label><input type="radio" name="${name}" value="server"> Serveur : ${escapeHtml(displayFieldValue(field, op.server?.[field]))}</label>
            </li>`;
        }).join('');

        const mergeActions = fields.length
            ? (merging
                ? `<button type="button" class="small primary" data-conflict-apply-merge="${op.op_id}">Appliquer la fusion</button>
                   <button type="button" class="small" data-conflict-cancel-merge="${op.op_id}">Annuler</button>`
                : `<button type="button" class="small" data-conflict-merge="${op.op_id}">Fusionner champ par champ</button>`)
            : '';

        return `<article class="conflict" data-conflict="${op.op_id}">
            <header>
                <strong>${escapeHtml(entityLabel(op))}</strong>
                <span class="pill pill--danger">Conflit</span>
                <span class="muted">Version serveur du ${escapeHtml(formatDate(op.server_updated_at) || '—')}</span>
            </header>
            ${fields.length
                ? `<ul class="conflict__fields">${rows}</ul>`
                : '<p class="muted">Aucun champ différent : vous pouvez conserver votre version.</p>'}
            <div class="form-actions">
                <button type="button" class="small primary" data-conflict-keep-local="${op.op_id}">Garder ma version</button>
                <button type="button" class="small" data-conflict-keep-server="${op.op_id}">Prendre la version serveur</button>
                ${mergeActions}
            </div>
        </article>`;
    }

    function errorCard(op) {
        let message = op.last_error ?? 'Erreur inconnue';

        try {
            const parsed = JSON.parse(message);
            message = Object.values(parsed).flat().join(' ');
        } catch {
            // Le message d'erreur est déjà lisible.
        }

        return `<article class="conflict conflict--error" data-error="${op.op_id}">
            <header>
                <strong>${escapeHtml(entityLabel(op))}</strong>
                <span class="pill pill--danger">Erreur</span>
            </header>
            <p class="muted">${escapeHtml(message)}</p>
            <div class="form-actions">
                <button type="button" class="small" data-error-retry="${op.op_id}">Réessayer</button>
                <button type="button" class="small danger" data-error-discard="${op.op_id}">Abandonner</button>
            </div>
        </article>`;
    }

    function renderConflicts() {
        const container = $('#conflicts');

        if (!container) {
            return;
        }

        const conflicts = state.queue.filter(isConflictOp);
        const errors = state.queue.filter(isErrorOp);

        container.hidden = conflicts.length === 0 && errors.length === 0;
        container.innerHTML = [
            conflicts.length ? `<h2>Conflits de synchronisation (${conflicts.length})</h2>${conflicts.map(conflictCard).join('')}` : '',
            errors.length ? `<h2>Opérations en erreur (${errors.length})</h2>${errors.map(errorCard).join('')}` : '',
        ].join('');
    }

    async function retryWithPayload(op, payload) {
        op.payload = payload;
        op.base_updated_at = op.server_updated_at ?? op.base_updated_at;
        op.status = 'pending';
        op.attempts = 0;
        op.next_attempt_at = 0;
        op.last_error = null;
        delete op.server;
        delete op.server_updated_at;

        await putOp(op);
        state.mergeOpId = null;
        await refreshPending();
        await flushQueue({ force: true });
    }

    async function keepServerVersion(op) {
        if (op.server) {
            await applyServerResult(op, {
                entity_type: op.entity_type,
                entity_id: op.entity_id,
                server: op.server,
            });
            await putSnapshot(state.snapshot);
        }

        await deleteOp(op.op_id);
        await applyQueueToState();
        await refreshPending();
        render();
        flash('Version serveur appliquée.');
    }

    async function applyMerge(op) {
        const merged = { ...(op.payload ?? {}) };

        for (const field of diffFields(op)) {
            const selected = document.querySelector(`input[name="merge-${op.op_id}-${field}"]:checked`)?.value ?? 'local';
            merged[field] = selected === 'server'
                ? (op.server?.[field] ?? null)
                : (op.payload?.[field] ?? null);
        }

        await retryWithPayload(op, merged);
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

            if (response.status === 401 || response.status === 419) {
                markSessionExpired(true);

                return;
            }

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

    function mergeSnapshot(data) {
        if (data.full || !state.snapshot || !Array.isArray(state.snapshot.accounts)) {
            state.snapshot = {
                ...data,
                cursor: data.cursor ?? data.generated_at ?? null,
            };

            return;
        }

        const deletedAccounts = new Set((data.deleted?.accounts ?? []).map(Number));
        const deletedContacts = new Set((data.deleted?.contacts ?? []).map(Number));
        const byId = new Map(accounts().map((account) => [Number(account.id), account]));

        for (const id of deletedAccounts) {
            byId.delete(id);
        }

        for (const incoming of data.accounts ?? []) {
            const current = byId.get(Number(incoming.id));
            const contactMap = new Map();

            for (const contact of current?.contacts ?? []) {
                if (!deletedContacts.has(Number(contact.id))) {
                    contactMap.set(Number(contact.id), contact);
                }
            }

            for (const contact of incoming.contacts ?? []) {
                contactMap.set(Number(contact.id), contact);
            }

            byId.set(Number(incoming.id), {
                ...(current ?? {}),
                ...incoming,
                contacts: [...contactMap.values()],
            });
        }

        if (deletedContacts.size > 0) {
            for (const account of byId.values()) {
                account.contacts = (account.contacts ?? []).filter(
                    (contact) => !deletedContacts.has(Number(contact.id)),
                );
            }
        }

        state.snapshot = {
            ...state.snapshot,
            generated_at: data.generated_at ?? state.snapshot.generated_at,
            cursor: data.cursor ?? data.generated_at ?? state.snapshot.cursor ?? null,
            accounts: [...byId.values()],
        };
    }

    async function fetchSnapshot() {
        if (!navigator.onLine) {
            return;
        }

        try {
            const cursor = state.snapshot?.cursor ?? null;
            const url = cursor
                ? `/terrain/snapshot?updated_since=${encodeURIComponent(cursor)}`
                : '/terrain/snapshot';

            const response = await fetch(url, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
            });

            if (response.status === 401 || response.status === 419) {
                markSessionExpired(true);

                return;
            }

            if (!response.ok) {
                return;
            }

            markSessionExpired(false);
            mergeSnapshot(await response.json());
            await fetchActivities();
            await applyQueueToState();
            await putSnapshot(state.snapshot);
            populateFilters();
            render();
            await refreshPending();
            await flushQueue();
        } catch {
            flash('Récupération des données impossible.');
        }
    }

    async function renderStorage() {
        const usage = $('#storage-usage');
        const summary = $('#queue-summary');

        if (summary) {
            summary.textContent = state.queue.length === 0
                ? 'Aucune opération locale en attente.'
                : `${state.queue.length} opération(s) locale(s) : ${state.pending} en attente, ${state.conflicts} conflit(s), ${state.errors} erreur(s).`;
        }

        if (!usage) {
            return;
        }

        try {
            const estimate = await navigator.storage?.estimate?.();

            if (!estimate || estimate.usage === undefined || estimate.usage === null) {
                usage.textContent = 'Espace utilisé : non disponible.';

                return;
            }

            usage.textContent = `Espace utilisé : ${formatBytes(estimate.usage)}`
                + (estimate.quota ? ` sur ${formatBytes(estimate.quota)} disponibles.` : '.');
        } catch {
            usage.textContent = 'Espace utilisé : non disponible.';
        }
    }

    async function exportQueue() {
        const operations = await allOps();
        const blob = new Blob([JSON.stringify({
            exported_at: new Date().toISOString(),
            operations,
        }, null, 2)], { type: 'application/json' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');

        link.href = url;
        link.download = `file-terrain-${new Date().toISOString().slice(0, 10)}.json`;
        link.click();

        window.setTimeout(() => URL.revokeObjectURL(url), 1000);
        flash('File d’opérations exportée.');
    }

    async function clearLocalData() {
        const operations = await allOps();

        if (operations.length > 0) {
            const exportFirst = window.confirm(
                `${operations.length} opération(s) ne sont pas encore synchronisées.\n\n`
                + 'Voulez-vous exporter la file en JSON avant de vider les données ?',
            );

            if (exportFirst) {
                await exportQueue();
            }

            if (!window.confirm(
                'Suppression DÉFINITIVE des données locales (snapshot, activités et file d’opérations).\n\n'
                + 'Ces modifications seront perdues. Confirmer ?',
            )) {
                return;
            }
        } else if (!window.confirm('Vider les données locales de cet appareil ? Elles seront rechargées au prochain passage en ligne.')) {
            return;
        }

        if (db) {
            db.close();
            db = null;
        }

        indexedDB.deleteDatabase(DB_NAME);
        window.location.reload();
    }

    function bindEvents() {
        document.addEventListener('click', async (event) => {
            const target = event.target.closest('button');

            if (!target) {
                return;
            }

            const linkedOp = (opId) => state.queue.find((item) => item.op_id === opId);

            if (target.dataset.tab) {
                setTab(target.dataset.tab);
            } else if (target.id === 'theme-toggle') {
                applyTheme(state.theme === 'dark' ? 'light' : 'dark', { persist: true });
            } else if (target.dataset.saveActivity) {
                await saveActivity(Number(target.dataset.saveActivity));
            } else if (target.dataset.editAccount) {
                state.editAccountId = Number(target.dataset.editAccount);
                render();
            } else if (target.dataset.cancelAccount) {
                state.editAccountId = null;
                render();
            } else if (target.dataset.saveAccount) {
                await saveAccount(Number(target.dataset.saveAccount));
            } else if (target.dataset.editContact) {
                state.editContactId = Number(target.dataset.editContact);
                render();
            } else if (target.dataset.cancelContact) {
                state.editContactId = null;
                render();
            } else if (target.dataset.saveContact) {
                await saveContact(Number(target.dataset.saveContact));
            } else if (target.dataset.newActivity) {
                state.newActivityFor = Number(target.dataset.newActivity);
                state.editAccountId = null;
                render();
            } else if (target.dataset.cancelNewActivity) {
                state.newActivityFor = null;
                render();
            } else if (target.dataset.saveNewActivity) {
                await saveNewActivity(Number(target.dataset.saveNewActivity));
            } else if (target.dataset.conflictKeepLocal) {
                const op = linkedOp(target.dataset.conflictKeepLocal);

                if (op) {
                    await retryWithPayload(op, { ...(op.payload ?? {}) });
                }
            } else if (target.dataset.conflictKeepServer) {
                const op = linkedOp(target.dataset.conflictKeepServer);

                if (op) {
                    await keepServerVersion(op);
                }
            } else if (target.dataset.conflictMerge) {
                state.mergeOpId = target.dataset.conflictMerge;
                renderConflicts();
            } else if (target.dataset.conflictCancelMerge) {
                state.mergeOpId = null;
                renderConflicts();
            } else if (target.dataset.conflictApplyMerge) {
                const op = linkedOp(target.dataset.conflictApplyMerge);

                if (op) {
                    await applyMerge(op);
                }
            } else if (target.dataset.errorRetry) {
                const op = linkedOp(target.dataset.errorRetry);

                if (op) {
                    op.status = 'pending';
                    op.attempts = 0;
                    op.next_attempt_at = 0;
                    await putOp(op);
                    await refreshPending();
                    await flushQueue({ force: true });
                }
            } else if (target.dataset.errorDiscard) {
                const op = linkedOp(target.dataset.errorDiscard);

                if (op && window.confirm('Abandonner cette modification locale ?')) {
                    await deleteOp(op.op_id);
                    await applyQueueToState();
                    await refreshPending();
                    render();
                }
            } else if (target.dataset.photoRemove) {
                await removePhoto(target.dataset.photoRemove);
            } else if (target.dataset.tourToggle) {
                await toggleTour(Number(target.dataset.tourToggle));
            } else if (target.dataset.tourUp) {
                await moveTour(Number(target.dataset.tourUp), -1);
            } else if (target.dataset.tourDown) {
                await moveTour(Number(target.dataset.tourDown), 1);
            } else if (target.dataset.tourRemove) {
                await removeFromTour(Number(target.dataset.tourRemove));
            } else if (target.id === 'tour-reset') {
                await resetTour();
            } else if (target.dataset.copyAddress) {
                try {
                    await navigator.clipboard.writeText(target.dataset.copyAddress);
                    flash('Adresse copiée.');
                } catch {
                    flash('Copie impossible sur cet appareil.');
                }
            }
        });

        document.addEventListener('toggle', (event) => {
            const details = event.target;

            if (!(details instanceof HTMLDetailsElement) || !details.classList.contains('account')) {
                return;
            }

            const accountId = Number(details.dataset.accountId);

            if (details.open) {
                state.openAccounts.add(accountId);
            } else {
                state.openAccounts.delete(accountId);
            }
        }, true);

        document.addEventListener('change', async (event) => {
            const input = event.target.closest('[data-photo-input]');

            if (!input || !input.files || input.files.length === 0) {
                return;
            }

            const activityId = Number(input.dataset.photoInput);
            const files = Array.from(input.files);
            input.value = '';

            for (const file of files) {
                await addPhoto(activityId, file);
            }
        });

        $('#tabbar').addEventListener('keydown', (event) => {
            const tabs = $$('.tab');
            const index = tabs.indexOf(document.activeElement);
            let next = null;

            if (index < 0) {
                return;
            }

            if (event.key === 'ArrowRight') {
                next = tabs[(index + 1) % tabs.length];
            } else if (event.key === 'ArrowLeft') {
                next = tabs[(index - 1 + tabs.length) % tabs.length];
            } else if (event.key === 'Home') {
                next = tabs[0];
            } else if (event.key === 'End') {
                next = tabs[tabs.length - 1];
            }

            if (next) {
                event.preventDefault();
                setTab(next.dataset.tab, { focus: true });
            }
        });

        window.matchMedia('(max-width: 899px)').addEventListener?.('change', () => {
            setTab(state.tab);
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
            await flushQueue({ force: true });
            await fetchSnapshot();
        });

        $('#export-queue').addEventListener('click', exportQueue);

        $('#clear-button').addEventListener('click', clearLocalData);

        window.addEventListener('online', async () => {
            renderStatus();
            await fetchSnapshot();
            await flushQueue({ force: true });
            await flushPhotos({ force: true });
        });

        window.addEventListener('offline', renderStatus);

        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible' && navigator.onLine) {
                if (state.tourDate && state.tourDate !== todayKey()) {
                    loadTour().then(render);
                }

                flushQueue();
                flushPhotos();
            }
        });
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
        state.photos = await allPhotos();
        await applyQueueToState();
        await loadTour();
        initTheme();
        initTab();
        await refreshPending();
        populateFilters();
        bindEvents();
        render();
        scheduleRetry();

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
