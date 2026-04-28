(function () {
  const APP_SCOPE = '/';
  const DB_NAME = 'psdLogbookOffline';
  const DB_VERSION = 1;
  const STORE_NAME = 'queuedLogs';
  const MAX_IMAGE_BYTES = 8 * 1024 * 1024;
  const MAX_VIDEO_BYTES = 50 * 1024 * 1024;
  const MAX_IMAGE_DIMENSION = 1600;
  const REMINDER_CHECK_MS = 60 * 1000;
  const NOTIFICATION_KEY_PREFIX = 'psd-notified:';

  function openDb() {
    return new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);
      request.onupgradeneeded = (event) => {
        const db = event.target.result;
        if (!db.objectStoreNames.contains(STORE_NAME)) {
          const store = db.createObjectStore(STORE_NAME, { keyPath: 'id', autoIncrement: true });
          store.createIndex('queued_at', 'queued_at', { unique: false });
        }
      };
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error || new Error('IndexedDB unavailable'));
    });
  }

  async function withStore(mode, callback) {
    const db = await openDb();
    return new Promise((resolve, reject) => {
      const tx = db.transaction(STORE_NAME, mode);
      const store = tx.objectStore(STORE_NAME);
      let result;
      try {
        result = callback(store, tx);
      } catch (error) {
        reject(error);
        return;
      }
      tx.oncomplete = () => resolve(result);
      tx.onerror = () => reject(tx.error || new Error('IndexedDB transaction failed'));
      tx.onabort = () => reject(tx.error || new Error('IndexedDB transaction aborted'));
    }).finally(() => db.close());
  }

  async function getAllQueuedLogs() {
    return withStore('readonly', (store) => {
      return new Promise((resolve, reject) => {
        const request = store.getAll();
        request.onsuccess = () => resolve((request.result || []).sort((a, b) => String(a.queued_at).localeCompare(String(b.queued_at))));
        request.onerror = () => reject(request.error || new Error('Failed to read queued logs'));
      });
    });
  }

  async function addQueuedLog(payload) {
    const queuedAt = new Date().toISOString();
    return withStore('readwrite', (store) => {
      store.add({ ...payload, queued_at: queuedAt });
    });
  }

  async function deleteQueuedLog(id) {
    return withStore('readwrite', (store) => {
      store.delete(id);
    });
  }

  async function getQueueCount() {
    const items = await getAllQueuedLogs();
    return items.length;
  }

  async function notifyQueueCount() {
    let count = 0;
    try {
      count = await getQueueCount();
    } catch (e) {
      count = 0;
    }

    document.querySelectorAll('[data-queue-count]').forEach(el => {
      el.textContent = String(count);
      el.style.display = count ? 'inline-flex' : 'none';
    });
  }

  function setNetworkState() {
    document.querySelectorAll('[data-network-status]').forEach(el => {
      if (navigator.onLine) {
        el.textContent = 'Online';
        el.className = 'badge bg-success';
      } else {
        el.textContent = 'Offline';
        el.className = 'badge bg-warning text-dark';
      }
    });
  }



  function formatBytes(bytes) {
    const size = Number(bytes) || 0;
    if (size < 1024) return `${size} B`;
    const units = ['KB', 'MB', 'GB'];
    let value = size / 1024;
    let idx = 0;
    while (value >= 1024 && idx < units.length - 1) {
      value /= 1024;
      idx += 1;
    }
    return `${value.toFixed(value >= 10 ? 0 : 1)} ${units[idx]}`;
  }

  function isSupportedImage(file) {
    return !!file && ['image/jpeg', 'image/png', 'image/webp'].includes(file.type);
  }

  function isSupportedVideo(file) {
    return !!file && ['video/mp4', 'video/webm', 'video/quicktime'].includes(file.type);
  }

  function loadImageFromFile(file) {
    return new Promise((resolve, reject) => {
      const img = new Image();
      const objectUrl = URL.createObjectURL(file);
      img.onload = () => {
        URL.revokeObjectURL(objectUrl);
        resolve(img);
      };
      img.onerror = () => {
        URL.revokeObjectURL(objectUrl);
        reject(new Error('Could not read image file.'));
      };
      img.src = objectUrl;
    });
  }

  async function compressImageFile(file) {
    if (!isSupportedImage(file)) {
      return file;
    }

    const image = await loadImageFromFile(file);
    const ratio = Math.min(1, MAX_IMAGE_DIMENSION / Math.max(image.width || 1, image.height || 1));
    const width = Math.max(1, Math.round((image.width || 1) * ratio));
    const height = Math.max(1, Math.round((image.height || 1) * ratio));

    const canvas = document.createElement('canvas');
    canvas.width = width;
    canvas.height = height;
    const ctx = canvas.getContext('2d');
    if (!ctx) {
      return file;
    }

    ctx.drawImage(image, 0, 0, width, height);

    const targetMime = file.type === 'image/png' ? 'image/jpeg' : (file.type || 'image/jpeg');
    let quality = 0.82;

    const renderBlob = (q) => new Promise((resolve, reject) => {
      canvas.toBlob((blob) => {
        if (!blob) {
          reject(new Error('Image compression failed.'));
          return;
        }
        resolve(blob);
      }, targetMime, q);
    });

    let blob = await renderBlob(quality);
    while (blob.size > MAX_IMAGE_BYTES && quality > 0.45) {
      quality -= 0.08;
      blob = await renderBlob(quality);
    }

    if (blob.size >= file.size && file.size <= MAX_IMAGE_BYTES) {
      return file;
    }

    const extension = targetMime === 'image/webp' ? 'webp' : 'jpg';
    const baseName = (file.name || 'image').replace(/\.[^.]+$/, '');
    return new File([blob], `${baseName}.${extension}`, {
      type: targetMime,
      lastModified: Date.now()
    });
  }

  async function normalizeMediaFile(file) {
    if (!file || typeof file !== 'object' || !file.size) {
      return { file: null, message: '' };
    }

    if (isSupportedImage(file)) {
      const processed = await compressImageFile(file);
      if (processed.size > MAX_IMAGE_BYTES) {
        throw new Error(`Image is still too large after compression (${formatBytes(processed.size)}). Please choose a smaller image.`);
      }
      const changed = processed.name !== file.name || processed.size !== file.size || processed.type !== file.type;
      return {
        file: processed,
        message: changed ? `Image optimized from ${formatBytes(file.size)} to ${formatBytes(processed.size)} for faster sync.` : `Image ready at ${formatBytes(processed.size)}.`
      };
    }

    if (isSupportedVideo(file)) {
      if (file.size > MAX_VIDEO_BYTES) {
        throw new Error(`Video is ${formatBytes(file.size)}. The limit is ${formatBytes(MAX_VIDEO_BYTES)}.`);
      }
      return { file, message: `Video ready at ${formatBytes(file.size)}.` };
    }

    throw new Error('Only JPG, PNG, WEBP, MP4, WEBM, and MOV files are allowed.');
  }

  async function prepareFormMedia(form) {
    const input = form.querySelector('input[name="training_media"]');
    const statusEl = document.querySelector('[data-media-status]');
    if (!input || !input.files || !input.files.length) {
      if (statusEl) statusEl.textContent = 'No media attached.';
      return null;
    }

    const originalFile = input.files[0];
    const result = await normalizeMediaFile(originalFile);
    if (result.file && result.file !== originalFile) {
      const dt = new DataTransfer();
      dt.items.add(result.file);
      input.files = dt.files;
    }
    if (statusEl) {
      statusEl.textContent = result.message || 'Media ready.';
    }
    return result.file || originalFile;
  }

  function bindMediaInputHelpers() {
    document.querySelectorAll('input[name="training_media"]').forEach(input => {
      input.addEventListener('change', async () => {
        const statusEl = document.querySelector('[data-media-status]');
        if (statusEl) {
          statusEl.textContent = 'Preparing media…';
        }
        try {
          if (!input.files || !input.files.length) {
            if (statusEl) statusEl.textContent = 'No media attached.';
            return;
          }
          const form = input.closest('form');
          if (form) {
            await prepareFormMedia(form);
          }
        } catch (error) {
          input.value = '';
          if (statusEl) statusEl.textContent = error.message || 'Could not prepare media.';
          alert(error.message || 'Could not prepare media.');
        }
      });
    });
  }

  function setNotificationState(message, kind = 'secondary') {
    document.querySelectorAll('[data-notification-state]').forEach((el) => {
      el.textContent = message;
      el.className = `badge bg-${kind}`;
    });
  }

  function getNotificationPermission() {
    if (!('Notification' in window)) {
      return 'unsupported';
    }
    return Notification.permission;
  }

  function formatDueText(dateString) {
    const date = new Date(dateString);
    if (Number.isNaN(date.getTime())) {
      return 'Unknown time';
    }
    return date.toLocaleString([], { month: 'short', day: 'numeric', hour: 'numeric', minute: '2-digit' });
  }

  function makeReminderStorageKey(event, stage) {
    return `${NOTIFICATION_KEY_PREFIX}${event.event_type}:${event.appointment_id}:${event.due_at}:${stage}`;
  }

  function hasSeenReminder(event, stage) {
    try {
      return localStorage.getItem(makeReminderStorageKey(event, stage)) === '1';
    } catch (e) {
      return false;
    }
  }

  function markReminderSeen(event, stage) {
    try {
      localStorage.setItem(makeReminderStorageKey(event, stage), '1');
    } catch (e) {}
  }

  function getReminderStage(event) {
    const due = new Date(event.due_at).getTime();
    if (!Number.isFinite(due)) {
      return null;
    }
    const diffMs = due - Date.now();
    const oneHour = 60 * 60 * 1000;
    const oneDay = 24 * oneHour;
    const fourHoursPast = -4 * oneHour;

    if (diffMs <= 0 && diffMs >= fourHoursPast) {
      return 'now';
    }
    if (diffMs > 0 && diffMs <= oneHour) {
      return 'hour';
    }
    if (diffMs > oneHour && diffMs <= oneDay) {
      return 'day';
    }
    return null;
  }

  function buildNotificationCopy(event, stage) {
    const dueLabel = event.event_type === 'appointment' ? 'Appointment' : 'Reminder';
    let prefix = dueLabel;
    if (stage === 'day') prefix = `${dueLabel} within 24 hours`;
    if (stage === 'hour') prefix = `${dueLabel} within 1 hour`;
    if (stage === 'now') prefix = event.event_type === 'appointment' ? 'Appointment due now' : 'Reminder due now';

    const bodyParts = [
      `${event.dog_name}: ${event.title}`,
      formatDueText(event.due_at)
    ];
    if (event.clinic_name) bodyParts.push(event.clinic_name);
    if (event.location_text) bodyParts.push(event.location_text);

    return {
      title: `${prefix} • ${event.dog_name}`,
      body: bodyParts.join(' • '),
      data: {
        url: `${APP_SCOPE}appointments.php`,
        appointmentId: event.appointment_id,
        dogId: event.dog_id,
        eventType: event.event_type,
        dueAt: event.due_at,
        stage
      },
      tag: `appointment-${event.appointment_id}-${event.event_type}-${stage}`
    };
  }

  async function showReminderNotification(event, stage) {
    const payload = buildNotificationCopy(event, stage);
    if ('serviceWorker' in navigator) {
      const registration = await navigator.serviceWorker.getRegistration(APP_SCOPE).catch(() => null);
      if (registration && registration.active) {
        registration.active.postMessage({ type: 'SHOW_NOTIFICATION', payload });
        return;
      }
    }

    if ('Notification' in window && Notification.permission === 'granted') {
      new Notification(payload.title, { body: payload.body, tag: payload.tag, data: payload.data });
    }
  }

  async function fetchReminderEvents() {
    const response = await fetch(`${APP_SCOPE}appointment_notifications.php?hours=24`, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
      cache: 'no-store'
    });
    if (!response.ok) {
      throw new Error('Could not load reminder events.');
    }
    const data = await response.json();
    return Array.isArray(data.events) ? data.events : [];
  }

  async function checkAppointmentNotifications() {
    const permission = getNotificationPermission();
    if (permission === 'unsupported') {
      setNotificationState('Notifications unsupported', 'secondary');
      return;
    }
    if (permission !== 'granted') {
      setNotificationState(permission === 'denied' ? 'Notifications blocked' : 'Notifications off', permission === 'denied' ? 'danger' : 'secondary');
      return;
    }

    setNotificationState('Notifications on', 'success');

    if (!navigator.onLine) {
      return;
    }

    const events = await fetchReminderEvents();
    for (const event of events) {
      const stage = getReminderStage(event);
      if (!stage || hasSeenReminder(event, stage)) {
        continue;
      }
      await showReminderNotification(event, stage);
      markReminderSeen(event, stage);
    }
  }

  function bindNotificationButtons() {
    document.querySelectorAll('[data-enable-notifications]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (!('Notification' in window)) {
          alert('This browser does not support notifications.');
          setNotificationState('Notifications unsupported', 'secondary');
          return;
        }
        const permission = await Notification.requestPermission();
        if (permission === 'granted') {
          setNotificationState('Notifications on', 'success');
          await checkAppointmentNotifications().catch(() => {});
        } else if (permission === 'denied') {
          setNotificationState('Notifications blocked', 'danger');
          alert('Notifications were blocked. You can re-enable them in your browser or app site settings.');
        } else {
          setNotificationState('Notifications off', 'secondary');
        }
      });
    });

    document.querySelectorAll('[data-test-notification]').forEach((btn) => {
      btn.addEventListener('click', async () => {
        if (getNotificationPermission() !== 'granted') {
          alert('Enable notifications first.');
          return;
        }
        await showReminderNotification({
          appointment_id: 0,
          dog_id: 0,
          dog_name: 'Test Dog',
          title: 'Vet reminder test',
          event_type: 'reminder',
          due_at: new Date().toISOString(),
          clinic_name: 'PSD Logbook',
          location_text: 'Notification test'
        }, 'now');
      });
    });
  }

  async function fetchFreshCsrfToken() {
    const response = await fetch(APP_SCOPE + 'csrf_token.php', {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' }
    });

    if (!response.ok) {
      throw new Error('Could not refresh CSRF token');
    }

    const data = await response.json();
    if (!data.success || !data.csrf_token) {
      throw new Error(data.message || 'Invalid CSRF token response');
    }

    document.querySelectorAll('input[name="csrf_token"]').forEach(el => {
      el.value = data.csrf_token;
    });

    return data.csrf_token;
  }

  function payloadToFormData(item, csrfToken) {
    const formData = new FormData();
    formData.append('csrf_token', csrfToken || item.csrf_token || '');
    formData.append('dog_id', item.dog_id || '');
    formData.append('location_name', item.location_name || '');
    formData.append('location_city_state', item.location_city_state || '');
    formData.append('location_type', item.location_type || 'Other');
    formData.append('focus_level', item.focus_level || '3');
    formData.append('handler_notes', item.handler_notes || '');
    formData.append('latitude', item.latitude || '');
    formData.append('longitude', item.longitude || '');

    const skills = Array.isArray(item.skills) ? item.skills : [];
    skills.forEach(skill => formData.append('skills[]', skill));

    if (item.training_media && item.training_media.blob) {
      const fileName = item.training_media.name || 'offline-upload';
      const mimeType = item.training_media.type || 'application/octet-stream';
      const blob = item.training_media.blob instanceof Blob
        ? item.training_media.blob
        : new Blob([item.training_media.blob], { type: mimeType });
      formData.append('training_media', blob, fileName);
    }

    return formData;
  }

  async function postQueuedLog(item) {
    const csrfToken = await fetchFreshCsrfToken();
    const formData = payloadToFormData(item, csrfToken);

    const response = await fetch(APP_SCOPE + 'save_log.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'fetch' }
    });

    if (!response.ok) {
      let message = 'Upload failed';
      try {
        const data = await response.json();
        message = data.message || message;
      } catch (e) {}
      throw new Error(message);
    }

    const data = await response.json();
    if (!data.success) {
      throw new Error(data.message || 'Save failed');
    }

    return data;
  }

  async function syncQueuedLogs() {
    if (!navigator.onLine) {
      return { synced: 0, remaining: await getQueueCount() };
    }

    const items = await getAllQueuedLogs();
    if (!items.length) {
      return { synced: 0, remaining: 0 };
    }

    let synced = 0;
    for (const item of items) {
      try {
        await postQueuedLog(item);
        await deleteQueuedLog(item.id);
        synced += 1;
      } catch (error) {
        console.warn('Queued log sync failed', error);
      }
    }

    const remaining = await getQueueCount();
    await notifyQueueCount();
    return { synced, remaining };
  }

  async function buildOfflinePayload(form) {
    await prepareFormMedia(form);
    const formData = new FormData(form);
    const file = formData.get('training_media');
    const payload = {
      csrf_token: formData.get('csrf_token') || '',
      dog_id: formData.get('dog_id') || '',
      location_name: formData.get('location_name') || '',
      location_city_state: formData.get('location_city_state') || '',
      location_type: formData.get('location_type') || 'Other',
      focus_level: formData.get('focus_level') || '3',
      handler_notes: formData.get('handler_notes') || '',
      latitude: formData.get('latitude') || '',
      longitude: formData.get('longitude') || '',
      skills: formData.getAll('skills[]')
    };

    if (file && typeof file === 'object' && file.size > 0) {
      payload.training_media = {
        name: file.name,
        type: file.type,
        size: file.size,
        lastModified: file.lastModified,
        blob: file
      };
    }

    return payload;
  }

  async function submitOnlineForm(form) {
    await prepareFormMedia(form);
    const formData = new FormData(form);
    const response = await fetch(APP_SCOPE + 'save_log.php', {
      method: 'POST',
      body: formData,
      credentials: 'same-origin',
      headers: { 'X-Requested-With': 'fetch' }
    });

    const data = await response.json().catch(() => ({ success: false, message: 'Unexpected server response.' }));
    if (!response.ok || !data.success) {
      throw new Error(data.message || 'Could not save the log.');
    }

    window.location.href = APP_SCOPE + 'view_logs.php?status=created';
  }

  async function handleQueuedForm(event) {
    const form = event.target;
    if (!form.matches('[data-offline-log-form]')) return;

    event.preventDefault();

    try {
      await prepareFormMedia(form);
    } catch (error) {
      alert(error.message || 'Could not prepare media.');
      return;
    }

    if (navigator.onLine) {
      const submitButton = form.querySelector('button[type="submit"]');
      const originalLabel = submitButton ? submitButton.textContent : '';
      try {
        if (submitButton) {
          submitButton.disabled = true;
          submitButton.textContent = 'Saving...';
        }
        await submitOnlineForm(form);
      } catch (error) {
        alert(error.message || 'Could not save the log.');
      } finally {
        if (submitButton) {
          submitButton.disabled = false;
          submitButton.textContent = originalLabel;
        }
      }
      return;
    }

    try {
      const payload = await buildOfflinePayload(form);
      await addQueuedLog(payload);
      form.reset();
      document.querySelectorAll('input[name="latitude"], input[name="longitude"]').forEach(el => {
        el.value = '';
      });
      const gpsStatus = document.getElementById('gps-status');
      if (gpsStatus) {
        gpsStatus.textContent = payload.training_media
          ? 'Offline log and media queued on this device. They will sync automatically when you reconnect.'
          : 'Offline log queued on this device. It will sync automatically when you reconnect.';
      }
      await notifyQueueCount();
      alert(payload.training_media ? 'Offline log and media queued on this device.' : 'Offline log queued on this device.');
    } catch (error) {
      alert(error.message || 'Could not queue the offline log.');
    }
  }

  function bindSyncButtons() {
    document.querySelectorAll('[data-sync-queued]').forEach(btn => {
      btn.addEventListener('click', async () => {
        btn.disabled = true;
        const original = btn.textContent;
        btn.textContent = 'Syncing...';
        try {
          const result = await syncQueuedLogs();
          alert(result.synced ? `Synced ${result.synced} queued log(s).` : `No queued logs synced. Remaining: ${result.remaining}.`);
          if (!result.remaining && location.pathname.endsWith('view_logs.php')) {
            location.reload();
          }
        } finally {
          btn.disabled = false;
          btn.textContent = original;
        }
      });
    });
  }

  function registerServiceWorker() {
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register(APP_SCOPE + 'sw.js').catch(() => {});
    }
  }

  document.addEventListener('submit', handleQueuedForm);
  window.addEventListener('online', async () => {
    setNetworkState();
    const result = await syncQueuedLogs();
    if (result.synced) {
      console.log(`Synced ${result.synced} queued logs.`);
    }
  });
  window.addEventListener('offline', setNetworkState);
  document.addEventListener('DOMContentLoaded', async () => {
    registerServiceWorker();
    setNetworkState();
    bindSyncButtons();
    bindMediaInputHelpers();
    bindNotificationButtons();
    await notifyQueueCount();
    checkAppointmentNotifications().catch(() => {});
    setInterval(() => { checkAppointmentNotifications().catch(() => {}); }, REMINDER_CHECK_MS);
    syncQueuedLogs().catch(() => {});
  });
})();
