(() => {
    'use strict';

    const DB_NAME = 'sigec-offline-visits-v1';
    const DB_VERSION = 1;
    let database;
    let encryptionKey = null;
    let editingDraftId = null;
    let editingKind = null;
    const form = document.getElementById('visit-form');
    const incidentForm = document.getElementById('incident-form');
    const stockForm = document.getElementById('stock-form');
    const clinicForm = document.getElementById('clinic-form');
    const transferForm = document.getElementById('transfer-form');
    const admissionForm = document.getElementById('admission-form');
    const message = document.getElementById('message');
    const network = document.getElementById('network');
    const queue = document.getElementById('queue');
    const pendingCount = document.getElementById('pending-count');
    const passphrase = document.getElementById('passphrase');
    const newPassphrase = document.getElementById('new-passphrase');
    const confirmPassphrase = document.getElementById('confirm-passphrase');
    const changePassphraseButton = document.getElementById('change-passphrase');
    const cancelEditButton = document.getElementById('cancel-edit');
    const editingIndicator = document.getElementById('editing-indicator');
    const backupFile = document.getElementById('backup-file');
    const backupPassphrase = document.getElementById('backup-passphrase');
    const exportBackupButton = document.getElementById('export-backup');
    const importBackupButton = document.getElementById('import-backup');

    function show(text, type = '') {
        message.textContent = text;
        message.className = `status ${type}`.trim();
    }

    function openDatabase() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);
            request.onupgradeneeded = () => {
                const db = request.result;
                db.createObjectStore('drafts', { keyPath: 'id' });
                db.createObjectStore('settings', { keyPath: 'key' });
            };
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    function requestResult(request) {
        return new Promise((resolve, reject) => {
            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    function store(name, mode = 'readonly') {
        return database.transaction(name, mode).objectStore(name);
    }

    function toBase64(buffer) {
        return btoa(String.fromCharCode(...new Uint8Array(buffer)));
    }

    function fromBase64(value) {
        return Uint8Array.from(atob(value), character => character.charCodeAt(0));
    }

    async function getSalt() {
        let setting = await requestResult(store('settings').get('salt'));
        if (!setting) {
            const salt = crypto.getRandomValues(new Uint8Array(16));
            setting = { key: 'salt', value: toBase64(salt) };
            await requestResult(store('settings', 'readwrite').put(setting));
        }
        return fromBase64(setting.value);
    }

    async function deriveKey(secret, salt) {
        const material = await crypto.subtle.importKey('raw', new TextEncoder().encode(secret), 'PBKDF2', false, ['deriveKey']);
        return crypto.subtle.deriveKey(
            { name: 'PBKDF2', salt, iterations: 250000, hash: 'SHA-256' },
            material,
            { name: 'AES-GCM', length: 256 },
            false,
            ['encrypt', 'decrypt'],
        );
    }

    async function encryptPayload(payload, kind, key = encryptionKey) {
        const iv = crypto.getRandomValues(new Uint8Array(12));
        const ciphertext = await crypto.subtle.encrypt({ name: 'AES-GCM', iv }, key, new TextEncoder().encode(JSON.stringify(payload)));
        return { id: payload.offline_reference, kind, iv: toBase64(iv), ciphertext: toBase64(ciphertext), created_at: new Date().toISOString() };
    }

    async function decryptDraft(draft, key = encryptionKey) {
        const plaintext = await crypto.subtle.decrypt({ name: 'AES-GCM', iv: fromBase64(draft.iv) }, key, fromBase64(draft.ciphertext));
        return JSON.parse(new TextDecoder().decode(plaintext));
    }

    function replaceEncryption(updatedRecords, salt) {
        return new Promise((resolve, reject) => {
            const transaction = database.transaction(['drafts', 'settings'], 'readwrite');
            const draftStore = transaction.objectStore('drafts');
            updatedRecords.forEach(record => draftStore.put(record));
            transaction.objectStore('settings').put({ key: 'salt', value: toBase64(salt) });
            transaction.oncomplete = () => resolve();
            transaction.onerror = () => reject(transaction.error);
            transaction.onabort = () => reject(transaction.error || new Error('Mise à jour annulée'));
        });
    }

    async function drafts() {
        return requestResult(store('drafts').getAll());
    }

    async function refreshQueue() {
        const records = await drafts();
        pendingCount.textContent = `${records.length} fiche(s) en attente`;
        if (!encryptionKey) {
            queue.innerHTML = '<li>Déverrouillez la file pour consulter les fiches chiffrées.</li>';
            return;
        }
        queue.replaceChildren();
        if (!records.length) {
            queue.innerHTML = '<li>Aucune fiche en attente.</li>';
            return;
        }
        for (const record of records) {
            const payload = await decryptDraft(record);
            const item = document.createElement('li');
            const description = record.kind === 'incident_report'
                ? `Incident · ${payload.incident_type} · ${payload.severity} · ${new Date(record.created_at).toLocaleString('fr-FR')}`
                : record.kind === 'stock_movement'
                    ? `Stock · ${payload.item_code} · ${payload.movement_type === 'entry' ? 'entrée' : 'sortie'} · ${payload.quantity} · ${new Date(record.created_at).toLocaleString('fr-FR')}`
                    : record.kind === 'medical_consultation'
                        ? `Consultation médicale · ${payload.consultation_type} · ${new Date(record.created_at).toLocaleString('fr-FR')}`
                    : record.kind === 'detainee_transfer'
                        ? `Mouvement détenu · dossier ${payload.detainee_record_number} · ${payload.destination_establishment_code}/${payload.destination_building_code}/${payload.destination_cell_code} · ${new Date(record.created_at).toLocaleString('fr-FR')}`
                    : record.kind === 'admission_draft'
                        ? `Pré-admission à vérifier · ${payload.family_name} ${payload.post_name || ''} ${payload.given_names} · ordre ${payload.order_reference} · ${new Date(record.created_at).toLocaleString('fr-FR')}`
                    : `Visite · ${payload.family_name} ${payload.post_name || ''} ${payload.given_names} · dossier ${payload.detainee_record_number} · ${new Date(record.created_at).toLocaleString('fr-FR')}`;
            const details = document.createElement('span');
            const serverState = record.server_status === 'pending_review' ? ' — transmise, en attente de vérification en ligne'
                : record.server_status === 'rejected' ? ` — rejetée en ligne${record.server_review_note ? ` : ${record.server_review_note}` : ''} ; corrigez la fiche puis resynchronisez`
                : record.server_status === 'approved' ? ' — approuvée en ligne'
                : '';
            details.textContent = `${description}${serverState}${record.sync_error ? ` — ${record.sync_error}` : ''} `;
            const edit = document.createElement('button');
            edit.type = 'button';
            edit.className = 'secondary';
            edit.textContent = 'Corriger';
            edit.addEventListener('click', () => editDraft(record, payload));
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'secondary';
            remove.textContent = 'Supprimer';
            remove.addEventListener('click', async () => {
                if (!window.confirm('Supprimer définitivement cette fiche locale en attente ?')) return;
                await requestResult(store('drafts', 'readwrite').delete(record.id));
                if (editingDraftId === record.id) cancelEdit();
                await refreshQueue();
                show('Fiche locale supprimée.');
            });
            item.append(details, edit, document.createTextNode(' '), remove);
            queue.append(item);
        }
    }

    function formForKind(kind) {
        if (kind === 'incident_report') return incidentForm;
        if (kind === 'stock_movement') return stockForm;
        if (kind === 'medical_consultation') return clinicForm;
        if (kind === 'detainee_transfer') return transferForm;
        if (kind === 'admission_draft') return admissionForm;
        return form;
    }

    function cancelEdit() {
        if (editingDraftId) formForKind(editingKind).reset();
        editingDraftId = null;
        editingKind = null;
        cancelEditButton.hidden = true;
        editingIndicator.hidden = true;
        incidentForm.elements.occurred_at.value = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16);
        clinicForm.elements.consulted_at.value = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16);
        clinicForm.elements.referral_required.value = '0';
        clinicForm.elements.referral_notes.required = false;
        stockForm.elements.location.value = 'Magasin principal';
        stockForm.elements.reorder_threshold.value = '0';
        transferForm.elements.transferred_at.value = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    }

    function editDraft(record, payload) {
        cancelEdit();
        const kind = record.kind || 'visit_arrival';
        const target = formForKind(kind);
        target.reset();
        for (const [name, value] of Object.entries(payload)) {
            const field = target.elements.namedItem(name);
            if (!field || value === null || value === undefined) continue;
            if (field.type === 'datetime-local') {
                const date = new Date(value);
                field.value = new Date(date.getTime() - date.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            } else if (field.type !== 'submit' && field.type !== 'button') {
                field.value = String(value);
            }
        }
        editingDraftId = record.id;
        editingKind = kind;
        cancelEditButton.hidden = false;
        editingIndicator.hidden = false;
        if (kind === 'medical_consultation') updateReferralRequirement();
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
        show('Modifiez les champs puis enregistrez la fiche corrigée.');
    }

    transferForm.elements.transferred_at.value = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    admissionForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (!encryptionKey) return show('Déverrouillez d’abord la file avec votre phrase secrète.', 'error');
        if (editingDraftId && editingKind !== 'admission_draft') return show('Cette fiche doit être corrigée dans son formulaire d’origine.', 'error');
        const values = Object.fromEntries(new FormData(admissionForm).entries());
        const payload = { ...values, date_of_birth: values.date_of_birth || null, case_reference: values.case_reference || null, court_level: values.court_level || null, judgment_final_at: values.judgment_final_at || null, issued_at: values.issued_at || null, expires_at: values.expires_at || null, unit_reference: values.unit_reference || null, offline_reference: editingDraftId || crypto.randomUUID() };
        try {
            await requestResult(store('drafts', 'readwrite').put(await encryptPayload(payload, 'admission_draft')));
            editingDraftId = null;
            editingKind = null;
            admissionForm.reset();
            await refreshQueue();
            show('Pré-admission chiffrée sur cet appareil. Elle ne deviendra officielle qu’après vérification de l’ordre en ligne.');
        } catch {
            show('Impossible d’enregistrer localement. Vérifiez que le navigateur autorise le stockage du site.', 'error');
        }
    });
    transferForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (!encryptionKey) {
            show('Déverrouillez d’abord la file avec votre phrase secrète.', 'error');
            return;
        }
        if (editingDraftId && editingKind !== 'detainee_transfer') return show('Cette fiche doit être corrigée dans son formulaire d’origine.', 'error');
        const values = Object.fromEntries(new FormData(transferForm).entries());
        const payload = {
            ...values,
            transferred_at: new Date(values.transferred_at).toISOString(),
            offline_reference: editingDraftId || crypto.randomUUID(),
        };
        try {
            await requestResult(store('drafts', 'readwrite').put(await encryptPayload(payload, 'detainee_transfer')));
            editingDraftId = null;
            editingKind = null;
            transferForm.reset();
            transferForm.elements.transferred_at.value = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            await refreshQueue();
            show('Mouvement enregistré et chiffré sur cet appareil. Les contrôles seront faits à la synchronisation.');
        } catch {
            show('Impossible d’enregistrer localement. Vérifiez que le navigateur autorise le stockage du site.', 'error');
        }
    });

    function updateNetwork() {
        const online = navigator.onLine;
        network.textContent = online ? 'Connexion disponible. Connectez-vous à SIGEC pour synchroniser.' : 'Hors connexion. Les nouvelles fiches restent chiffrées sur cet appareil.';
        network.className = `status ${online ? '' : 'warning'}`.trim();
        document.getElementById('sync').disabled = !online || !encryptionKey;
    }

    function readCookie(name) {
        const match = document.cookie.split('; ').find(cookie => cookie.startsWith(`${name}=`));
        return match ? decodeURIComponent(match.slice(name.length + 1)) : '';
    }

    async function unlock() {
        if (passphrase.value.length < 8) {
            show('Choisissez une phrase secrète d’au moins 8 caractères.', 'error');
            return;
        }
        try {
            encryptionKey = await deriveKey(passphrase.value, await getSalt());
            passphrase.value = '';
            await refreshQueue();
            changePassphraseButton.disabled = false;
            exportBackupButton.disabled = false;
            importBackupButton.disabled = false;
            updateNetwork();
            show('File déverrouillée. Les données restent chiffrées lorsque la page est fermée.');
        } catch {
            encryptionKey = null;
            changePassphraseButton.disabled = true;
            exportBackupButton.disabled = true;
            importBackupButton.disabled = false;
            show('Phrase secrète incorrecte ou file locale illisible.', 'error');
        }
    }

    form.addEventListener('submit', async event => {
        event.preventDefault();
        if (!encryptionKey) {
            show('Déverrouillez d’abord la file avec votre phrase secrète.', 'error');
            return;
        }
        if (editingDraftId && editingKind !== 'visit_arrival') return show('Cette fiche doit être corrigée dans son formulaire d’origine.', 'error');
        const values = Object.fromEntries(new FormData(form).entries());
        const payload = {
            ...values,
            date_of_birth: values.date_of_birth || null,
            captured_at: new Date().toISOString(),
            offline_reference: editingDraftId || crypto.randomUUID(),
        };
        try {
            await requestResult(store('drafts', 'readwrite').put(await encryptPayload(payload, 'visit_arrival')));
            editingDraftId = null;
            editingKind = null;
            form.reset();
            await refreshQueue();
            show('Visite enregistrée et chiffrée sur cet appareil. Synchronisez-la quand le réseau revient.');
        } catch {
            show('Impossible d’enregistrer localement. Vérifiez que le navigateur autorise le stockage du site.', 'error');
        }
    });

    incidentForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (!encryptionKey) {
            show('Déverrouillez d’abord la file avec votre phrase secrète.', 'error');
            return;
        }
        if (editingDraftId && editingKind !== 'incident_report') return show('Cette fiche doit être corrigée dans son formulaire d’origine.', 'error');
        const values = Object.fromEntries(new FormData(incidentForm).entries());
        const payload = {
            ...values,
            occurred_at: new Date(values.occurred_at).toISOString(),
            location: values.location || null,
            immediate_action: values.immediate_action || null,
            offline_reference: editingDraftId || crypto.randomUUID(),
        };
        try {
            await requestResult(store('drafts', 'readwrite').put(await encryptPayload(payload, 'incident_report')));
            editingDraftId = null;
            editingKind = null;
            incidentForm.reset();
            incidentForm.elements.occurred_at.value = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16);
            await refreshQueue();
            show('Signalement enregistré et chiffré sur cet appareil. Synchronisez-le lorsque le réseau revient.');
        } catch {
            show('Impossible d’enregistrer localement. Vérifiez que le navigateur autorise le stockage du site.', 'error');
        }
    });

    incidentForm.elements.occurred_at.value = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16);

    const setClinicDate = () => {
        clinicForm.elements.consulted_at.value = new Date(Date.now() - new Date().getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    };
    const updateReferralRequirement = () => {
        clinicForm.elements.referral_notes.required = clinicForm.elements.referral_required.value === '1';
    };
    setClinicDate();
    updateReferralRequirement();
    clinicForm.elements.referral_required.addEventListener('change', updateReferralRequirement);
    clinicForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (!encryptionKey) {
            show('Déverrouillez d’abord la file avec votre phrase secrète.', 'error');
            return;
        }
        if (editingDraftId && editingKind !== 'medical_consultation') return show('Cette fiche doit être corrigée dans son formulaire d’origine.', 'error');
        const values = Object.fromEntries(new FormData(clinicForm).entries());
        const payload = {
            ...values,
            consulted_at: new Date(values.consulted_at).toISOString(),
            reason: values.reason || null,
            observations: values.observations || null,
            care_provided: values.care_provided || null,
            referral_notes: values.referral_notes || null,
            offline_reference: editingDraftId || crypto.randomUUID(),
        };
        try {
            await requestResult(store('drafts', 'readwrite').put(await encryptPayload(payload, 'medical_consultation')));
            editingDraftId = null;
            editingKind = null;
            clinicForm.reset();
            setClinicDate();
            updateReferralRequirement();
            await refreshQueue();
            show('Consultation enregistrée et chiffrée sur cet appareil. Elle sera inscrite au dossier médical après validation à la synchronisation.');
        } catch {
            show('Impossible d’enregistrer localement. Vérifiez que le navigateur autorise le stockage du site.', 'error');
        }
    });

    stockForm.addEventListener('submit', async event => {
        event.preventDefault();
        if (!encryptionKey) {
            show('Déverrouillez d’abord la file avec votre phrase secrète.', 'error');
            return;
        }
        if (editingDraftId && editingKind !== 'stock_movement') return show('Cette fiche doit être corrigée dans son formulaire d’origine.', 'error');
        const values = Object.fromEntries(new FormData(stockForm).entries());
        const payload = {
            ...values,
            item_code: values.item_code.trim(),
            quantity: Number(values.quantity),
            reorder_threshold: values.reorder_threshold === '' ? null : Number(values.reorder_threshold),
            reference: values.reference || null,
            reason: values.reason || null,
            captured_at: new Date().toISOString(),
            offline_reference: editingDraftId || crypto.randomUUID(),
        };
        try {
            await requestResult(store('drafts', 'readwrite').put(await encryptPayload(payload, 'stock_movement')));
            editingDraftId = null;
            editingKind = null;
            stockForm.reset();
            stockForm.elements.location.value = 'Magasin principal';
            stockForm.elements.reorder_threshold.value = '0';
            await refreshQueue();
            show('Mouvement de stock enregistré et chiffré sur cet appareil. Il sera vérifié à la synchronisation.');
        } catch {
            show('Impossible d’enregistrer localement. Vérifiez que le navigateur autorise le stockage du site.', 'error');
        }
    });

    document.getElementById('unlock').addEventListener('click', unlock);
    cancelEditButton.addEventListener('click', () => {
        cancelEdit();
        show('Correction annulée. La fiche reste dans la file chiffrée.');
    });
    changePassphraseButton.addEventListener('click', async () => {
        if (!encryptionKey) {
            show('Déverrouillez d’abord la file avec la phrase actuelle.', 'error');
            return;
        }
        if (newPassphrase.value.length < 8) {
            show('La nouvelle phrase doit contenir au moins 8 caractères.', 'error');
            return;
        }
        if (newPassphrase.value !== confirmPassphrase.value) {
            show('Les deux nouvelles phrases ne correspondent pas.', 'error');
            return;
        }

        changePassphraseButton.disabled = true;
        try {
            const records = await drafts();
            const decrypted = await Promise.all(records.map(async record => ({
                record,
                payload: await decryptDraft(record),
            })));
            const salt = crypto.getRandomValues(new Uint8Array(16));
            const newKey = await deriveKey(newPassphrase.value, salt);
            const updatedRecords = await Promise.all(decrypted.map(async ({ record, payload }) => ({
                ...await encryptPayload(payload, record.kind || 'visit_arrival', newKey),
                created_at: record.created_at,
            })));
            await replaceEncryption(updatedRecords, salt);
            encryptionKey = newKey;
            newPassphrase.value = '';
            confirmPassphrase.value = '';
            await refreshQueue();
            show('Phrase secrète modifiée. La file a été rechiffrée avec la nouvelle phrase.');
        } catch {
            show('Changement impossible. Aucune fiche n’a été supprimée ; vérifiez le stockage du navigateur et réessayez.', 'error');
        } finally {
            changePassphraseButton.disabled = !encryptionKey;
        }
    });

    exportBackupButton.addEventListener('click', async () => {
        if (!encryptionKey) return show('Déverrouillez la file avant de créer une sauvegarde.', 'error');
        try {
            const salt = await requestResult(store('settings').get('salt'));
            const records = await drafts();
            const backup = {
                format: 'SIGEC-OFFLINE-QUEUE',
                version: 1,
                exported_at: new Date().toISOString(),
                salt: salt.value,
                drafts: records.map(({ sync_error, ...record }) => record),
            };
            const blob = new Blob([JSON.stringify(backup, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `sigec-sauvegarde-hors-connexion-${new Date().toISOString().slice(0, 10)}.json`;
            link.click();
            setTimeout(() => URL.revokeObjectURL(url), 1000);
            show(`${records.length} fiche(s) chiffrée(s) exportée(s). Gardez la phrase secrète séparément.`);
        } catch {
            show('La sauvegarde n’a pas pu être créée.', 'error');
        }
    });

    importBackupButton.addEventListener('click', async () => {
        const file = backupFile.files?.[0];
        if (!file) return show('Choisissez d’abord un fichier de sauvegarde JSON.', 'error');
        if (file.size > 15 * 1024 * 1024) return show('Le fichier dépasse la taille maximale de 15 Mo.', 'error');
        try {
            const backup = JSON.parse(await file.text());
            if (backup.format !== 'SIGEC-OFFLINE-QUEUE' || backup.version !== 1 || !Array.isArray(backup.drafts) || backup.drafts.length > 1000) {
                throw new Error('Format de sauvegarde invalide.');
            }
            const salt = fromBase64(backup.salt);
            if (salt.length !== 16) throw new Error('Sel de chiffrement invalide.');

            const currentRecords = await drafts();
            const currentSalt = await getSalt();
            let importKey;
            if (currentRecords.length) {
                if (!encryptionKey) throw new Error('Déverrouillez d’abord la file existante.');
                if (toBase64(currentSalt) !== backup.salt) throw new Error('La file locale et la sauvegarde utilisent des phrases différentes. Synchronisez ou exportez la file locale avant de restaurer cette sauvegarde.');
                importKey = encryptionKey;
            } else {
                if (backupPassphrase.value.length < 8) throw new Error('Saisissez la phrase secrète utilisée pour cette sauvegarde.');
                importKey = await deriveKey(backupPassphrase.value, salt);
            }

            const allowedKinds = ['visit_arrival', 'incident_report', 'stock_movement', 'medical_consultation', 'detainee_transfer', 'admission_draft'];
            const backupIds = new Set();
            const decoded = await Promise.all(backup.drafts.map(async record => {
                if (!record || typeof record.id !== 'string' || !record.iv || !record.ciphertext || !record.created_at || !allowedKinds.includes(record.kind || 'visit_arrival')) {
                    throw new Error('Une fiche du fichier est invalide.');
                }
                if (backupIds.has(record.id)) throw new Error('La sauvegarde contient des références en double.');
                backupIds.add(record.id);
                const payload = await decryptDraft(record, importKey);
                if (payload.offline_reference !== record.id) throw new Error('Référence de fiche invalide.');
                return { record, payload };
            }));
            const knownIds = new Set(currentRecords.map(record => record.id));
            const newRecords = decoded.filter(({ record }) => !knownIds.has(record.id)).map(({ record }) => {
                const { sync_error, ...safeRecord } = record;
                return safeRecord;
            });
            await replaceEncryption(newRecords, salt);
            encryptionKey = importKey;
            backupPassphrase.value = '';
            await refreshQueue();
            updateNetwork();
            show(`${newRecords.length} fiche(s) restaurée(s). Une éventuelle copie déjà présente n’a pas été remplacée.`);
        } catch (error) {
            show(error instanceof Error ? error.message : 'Restauration impossible. La file locale n’a pas été remplacée.', 'error');
        }
    });
    document.getElementById('sync').addEventListener('click', async () => {
        if (!navigator.onLine || !encryptionKey) return;
        const records = await drafts();
        let synced = 0;
        let failed = 0;
        for (const record of records) {
            try {
                if (record.kind === 'admission_draft' && record.server_draft_id) {
                    const statusResponse = await fetch(`/offline-detainee-admissions/${encodeURIComponent((await decryptDraft(record)).offline_reference)}/status`, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } });
                    if (!statusResponse.ok || !statusResponse.headers.get('content-type')?.includes('application/json')) {
                        if ([401, 403, 419].includes(statusResponse.status) || statusResponse.redirected) {
                            record.sync_error = 'Connectez-vous avec un compte autorisé pour vérifier la décision.';
                            await requestResult(store('drafts', 'readwrite').put(record));
                            failed++;
                            break;
                        }
                        throw new Error('État de pré-admission indisponible.');
                    }
                    const serverState = await statusResponse.json();
                    record.server_status = serverState.status;
                    record.server_review_note = serverState.review_note;
                    if (serverState.status === 'approved') {
                        await requestResult(store('drafts', 'readwrite').delete(record.id));
                        synced++;
                        continue;
                    }
                    if (serverState.status === 'pending_review') {
                        await requestResult(store('drafts', 'readwrite').put(record));
                        continue;
                    }
                    await requestResult(store('drafts', 'readwrite').put(record));
                }
                const endpoint = record.kind === 'incident_report' ? '/offline-incidents/sync'
                    : record.kind === 'stock_movement' ? '/offline-stocks/sync'
                    : record.kind === 'medical_consultation' ? '/offline-clinic/sync'
                    : record.kind === 'detainee_transfer' ? '/offline-detainee-transfers/sync'
                    : record.kind === 'admission_draft' ? '/offline-detainee-admissions/sync' : '/offline-visits/sync';
                const response = await fetch(endpoint, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-XSRF-TOKEN': readCookie('XSRF-TOKEN') },
                    body: JSON.stringify(await decryptDraft(record)),
                });
                const isJsonResponse = response.headers.get('content-type')?.includes('application/json');
                if (!response.ok || !isJsonResponse) {
                    let validationDetail = '';
                    if (response.status === 422 && isJsonResponse) {
                        const body = await response.json();
                        validationDetail = Object.values(body.errors || {}).flat()[0] || body.message || '';
                    }
                    const permanentRejection = [403, 404, 409, 422].includes(response.status);
                    const needsLogin = [401, 419].includes(response.status) || response.redirected;
                    const text = response.status === 422 ? 'Une fiche contient une donnée à corriger. Elle reste enregistrée sur cet appareil.'
                        : response.status === 403 ? 'Votre compte n’a pas accès à l’établissement de cette fiche.'
                        : needsLogin ? 'Connectez-vous à SIGEC puis réessayez la synchronisation.'
                        : 'Le serveur a refusé la fiche. Vérifiez le code de l’établissement et réessayez.';
                    record.sync_error = response.status === 422 ? (validationDetail || 'À corriger : données refusées par le serveur.').slice(0, 300)
                        : response.status === 403 ? 'Accès refusé pour cet établissement.'
                        : needsLogin ? 'Connexion requise pour synchroniser.'
                        : 'Synchronisation refusée ; vérifiez les codes saisis.';
                    await requestResult(store('drafts', 'readwrite').put(record));
                    failed++;
                    show(text, 'error');
                    if (permanentRejection) continue;
                    break;
                }
                const result = await response.json();
                synced++;
                if (record.kind === 'admission_draft' && result.status === 'pending_review') {
                    record.server_draft_id = result.draft_id;
                    record.server_status = 'pending_review';
                    delete record.server_review_note;
                    delete record.sync_error;
                    await requestResult(store('drafts', 'readwrite').put(record));
                    show(`Pré-admission transmise pour examen (référence ${result.draft_id}). Elle reste sur cet appareil jusqu’à l’approbation ou au rejet.`);
                } else {
                    await requestResult(store('drafts', 'readwrite').delete(record.id));
                }
            } catch {
                record.sync_error = 'Connexion interrompue ; la fiche reste sur cet appareil.';
                await requestResult(store('drafts', 'readwrite').put(record));
                failed++;
                show('Synchronisation interrompue. Les fiches non confirmées restent dans la file.', 'error');
                break;
            }
        }
        await refreshQueue();
        if (synced && failed) show(`${synced} fiche(s) synchronisée(s) ; ${failed} fiche(s) restent à corriger ou à réessayer.`);
        else if (synced) show(`${synced} fiche(s) synchronisée(s). Les pré-admissions transmises restent en attente de validation dans le module Pré-admissions.`);
        else if (failed) show(`${failed} fiche(s) restent dans la file. Consultez les motifs affichés pour les corriger ou réessayer.`, 'error');
    });

    window.addEventListener('online', updateNetwork);
    window.addEventListener('offline', updateNetwork);
    openDatabase().then(async db => {
        database = db;
        await refreshQueue();
        updateNetwork();
    }).catch(() => show('Le stockage hors connexion est indisponible dans ce navigateur.', 'error'));
})();
