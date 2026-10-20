(() => {
    'use strict';

    const DB_NAME = 'c2me-field';
    const DB_VERSION = 1;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const state = { snapshot: null, pending: 0, syncing: false, search: '' };
    let db = null;

    const $ = (selector, root = document) => root.querySelector(selector);

    const escapeHtml = (value) =>
        String(value ?? '').replace(/[&<>"']/g, (character) => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;',
        })[character]);

    const formatDate = (iso) => {
        if (!iso) {
            return '';
        }

        const date = new Date(iso);

        return Number.isNaN(date.getTime())
            ? ''
            : date.toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' });
    };

    function openDb() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onupgradeneeded = () => {
                const database = request.result;

                if (!database.objectStoreNames.contains('snapshot')) {
                    database.createObjectStore('snapshot', { keyPath: 'key' });
                }

                if (!database.objectStoreNames.contains('queue')) {
                    database.createObjectStore('queue', { keyPath: 'activity_id' });
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
    const allQueued = () => idb('queue', 'readonly', (store) => store.getAll());
    const enqueue = (item) => idb('queue', 'readwrite', (store) => store.put(item));
    const dequeue = (activityId) => idb('queue', 'readwrite', (store) => store.delete(activityId));

    function accounts() {
        return Array.isArray(state.snapshot?.accounts) ? state.snapshot.accounts : [];
    }

    function findActivity(activityId) {
        for (const account of accounts()) {
            for (const activity of account.activities ?? []) {
                if (activity.id === activityId) {
                    return activity;
                }
            }
        }

        return null;
    }

    function matches(account, term) {
        if (term === '') {
            return true;
        }

        const haystack = [
            account.name,
            account.address,
            account.email,
            account.phone,
            account.mobile,
            account.notes,
            ...(account.contacts ?? []).map((contact) => [contact.name, contact.email, contact.phone, contact.mobile].join(' ')),
        ]
            .join(' ')
            .toLowerCase();

        return haystack.includes(term);
    }

    function contactRow(contact) {
        return `<div class="contact">
            <p><strong>${escapeHtml(contact.name)}</strong>${contact.is_primary ? ' <span class="pill">Principal</span>' : ''}</p>
            ${contact.job_title ? `<p class="muted">${escapeHtml(contact.job_title)}</p>` : ''}
            ${contact.phone ? `<p class="muted">Tél. ${escapeHtml(contact.phone)}</p>` : ''}
            ${contact.mobile ? `<p class="muted">Mobile ${escapeHtml(contact.mobile)}</p>` : ''}
            ${contact.email ? `<p class="muted">${escapeHtml(contact.email)}</p>` : ''}
        </div>`;
    }

    function activityRow(activity) {
        const done = Boolean(activity.completed_at);
        const due = formatDate(activity.due_at);

        return `<div class="activity ${done ? 'activity--done' : ''}">
            <div class="activity__head">
                <strong>${escapeHtml(activity.subject)}</strong>
                <span class="muted">
                    ${escapeHtml(activity.type_label)}
                    ${due ? ' · ' + escapeHtml(due) : ' · sans échéance'}
                    ${activity.is_overdue ? ' · en retard' : ''}
                </span>
            </div>
            <textarea id="activity-body-${activity.id}" rows="3" placeholder="Compte rendu…">${escapeHtml(activity.body ?? '')}</textarea>
            <label class="check">
                <input type="checkbox" id="activity-done-${activity.id}" ${done ? 'checked' : ''}>
                Terminée
            </label>
            <button type="button" class="primary" data-save="${activity.id}">Enregistrer</button>
        </div>`;
    }

    function accountCard(account) {
        const contacts = (account.contacts ?? []).map(contactRow).join('');
        const activities = (account.activities ?? []).map(activityRow).join('');

        return `<details class="account">
            <summary>
                <span class="account__name">${escapeHtml(account.name)}</span>
                <span class="pill">${escapeHtml(account.status_label)}</span>
                <span class="account__meta">${escapeHtml(account.book_label)} · ${escapeHtml(account.category_label)}</span>
            </summary>
            <div class="account__body">
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
                    </div>
                </div>

                <h3>Contacts</h3>
                ${contacts || '<p class="muted">Aucun contact.</p>'}

                <h3>Activités</h3>
                ${activities || '<p class="muted">Aucune activité.</p>'}
            </div>
        </details>`;
    }

    function render() {
        const container = $('#accounts');
        const term = state.search.trim().toLowerCase();
        const list = accounts().filter((account) => matches(account, term));

        $('#count').textContent = `${accounts().length} compte(s) · ${list.length} affiché(s)`;
        $('#last-sync').textContent = state.snapshot?.generated_at
            ? `Dernière synchro : ${formatDate(state.snapshot.generated_at)}`
            : 'Jamais synchronisé';

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
        state.pending = (await allQueued()).length;
        renderStatus();
    }

    async function applyQueueToSnapshot() {
        const queue = await allQueued();

        for (const item of queue) {
            const activity = findActivity(item.activity_id);

            if (activity) {
                activity.body = item.body;
                activity.completed_at = item.completed_at;
            }
        }
    }

    function applyServerActivity(updated) {
        const activity = findActivity(updated.id);

        if (!activity) {
            return;
        }

        Object.assign(activity, updated);
    }

    async function saveActivity(activityId) {
        const activity = findActivity(activityId);
        const body = $(`#activity-body-${activityId}`)?.value ?? '';
        const done = $(`#activity-done-${activityId}`)?.checked ?? false;

        if (!activity) {
            return;
        }

        activity.body = body;
        activity.completed_at = done ? new Date().toISOString() : null;

        await putSnapshot(state.snapshot);
        await enqueue({
            activity_id: activityId,
            body,
            completed_at: activity.completed_at,
            base_updated_at: activity.updated_at ?? null,
            queued_at: Date.now(),
        });

        await refreshPending();
        render();

        if (navigator.onLine) {
            await flushQueue();
        } else {
            flash('Enregistré sur cet appareil, en attente de connexion.');
        }
    }

    async function flushQueue() {
        if (!navigator.onLine || state.syncing) {
            return;
        }

        const queue = await allQueued();

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
                    activities: queue.map((item) => ({
                        id: item.activity_id,
                        body: item.body,
                        completed_at: item.completed_at,
                    })),
                }),
            });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const data = await response.json();

            for (const updated of data.activities ?? []) {
                applyServerActivity(updated);
            }

            await putSnapshot(state.snapshot);

            for (const item of queue) {
                const current = await idb('queue', 'readonly', (store) => store.get(item.activity_id));

                if (current && (current.queued_at ?? 0) <= startedAt) {
                    await dequeue(item.activity_id);
                }
            }

            flash('Modifications synchronisées.');
        } catch {
            flash('Synchronisation impossible. Nouvel essai au retour du réseau.');
        } finally {
            state.syncing = false;
            await refreshPending();
            render();
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
            await applyQueueToSnapshot();
            await putSnapshot(state.snapshot);
            render();
            await flushQueue();
        } catch {
            flash('Récupération des données impossible.');
        }
    }

    async function init() {
        try {
            db = await openDb();
        } catch {
            flash('Stockage local indisponible : le mode hors ligne est inactif.');
            return;
        }

        const stored = await getSnapshot();

        if (stored?.value) {
            state.snapshot = stored.value;
        }

        await refreshPending();
        render();

        document.addEventListener('click', (event) => {
            const button = event.target.closest('[data-save]');

            if (button) {
                saveActivity(Number(button.dataset.save));
            }
        });

        $('#search').addEventListener('input', (event) => {
            state.search = event.target.value;
            render();
        });

        $('#sync-button').addEventListener('click', async () => {
            await fetchSnapshot();
            await flushQueue();
        });

        $('#clear-button').addEventListener('click', async () => {
            if (!window.confirm('Effacer les données locales de cet appareil ?')) {
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
