const BASE_URL = 'http://localhost/education_platform_teachers';
const API_BASE_URL = `${BASE_URL}/backend`;

function normalizeApiPath(path) {
  return path.startsWith('/') ? path : `/${path}`;
}

function buildUrl(path, params = {}, useApiBase = true) {
  const url = new URL(`${useApiBase ? API_BASE_URL : BASE_URL}${normalizeApiPath(path)}`);

  Object.entries(params).forEach(([key, value]) => {
    if (value !== undefined && value !== null && value !== '') {
      url.searchParams.set(key, value);
    }
  });

  return url;
}

async function parseResponse(response) {
  const payload = await response.json().catch(() => null);

  if (!response.ok || !payload || payload.status === 'error') {
    throw new Error(payload?.message || 'Request failed');
  }

  if (Object.prototype.hasOwnProperty.call(payload, 'data')) {
    return payload.data;
  }

  return payload;
}

function getToken() {
  return localStorage.getItem('student_token') || '';
}

function getStudentData() {
  const raw = localStorage.getItem('student_data');

  if (!raw) {
    return null;
  }

  try {
    return JSON.parse(raw);
  } catch {
    return null;
  }
}

function setAuth(token, student) {
  localStorage.setItem('student_token', token);
  localStorage.setItem('student_data', JSON.stringify(student || null));
}

function clearAuth() {
  localStorage.removeItem('student_token');
  localStorage.removeItem('student_data');
  localStorage.removeItem('pending_quiz_attempt');
}

function authHeaders() {
  const token = getToken();
  return token ? { 'X-Authorization': `Bearer ${token}` } : {};
}


async function apiGet(path, auth = false, params = {}) {
  const response = await fetch(buildUrl(path, params).toString(), {
    headers: auth ? authHeaders() : {}
  });

  return parseResponse(response);
}

async function apiPost(path, body, auth = false) {
  const response = await fetch(buildUrl(path).toString(), {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      ...(auth ? authHeaders() : {})
    },
    body: JSON.stringify(body || {})
  });

  return parseResponse(response);
}

async function apiPut(path, body, auth = false) {
  const response = await fetch(buildUrl(path).toString(), {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      ...(auth ? authHeaders() : {})
    },
    body: JSON.stringify(body || {})
  });

  return parseResponse(response);
}

function getGrades() {
  return apiGet('/grades/get.php');
}

function getItems(params = {}) {
  return apiGet('/items/public_get_item.php', false, params);
}

function getItemById(id) {
  return apiGet('/items/public_get_item.php', false, { id });
}

function getQueryParam(name) {
  return new URLSearchParams(window.location.search).get(name);
}

function resolveImageUrl(path) {
  if (!path) {
    return '';
  }

  if (/^https?:\/\//i.test(path)) {
    return path;
  }

  const normalized = path.startsWith('/') ? path : `/${path}`;
  return `${API_BASE_URL}${normalized}`;
}

function formatPrice(item) {
  if (Number(item.is_free) === 1 || Number(item.price) <= 0) {
    return 'Free';
  }

  return `$${Number(item.price).toFixed(2)}`;
}

function formatDuration(days) {
  if (!days || Number(days) <= 0) {
    return 'Lifetime access';
  }

  return `${days} day${Number(days) === 1 ? '' : 's'}`;
}

function toTitleCase(value) {
  return String(value || '')
    .replace(/_/g, ' ')
    .replace(/\b\w/g, (char) => char.toUpperCase());
}

function escapeHtml(value) {
  return String(value ?? '')
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;')
    .replace(/'/g, '&#39;');
}

function getRedirectUrl(defaultUrl = 'my-items.html') {
  return getQueryParam('redirect') || defaultUrl;
}

function requireStudentAuth() {
  if (getToken()) {
    return true;
  }

  const redirect = `${window.location.pathname.split('/').pop()}${window.location.search}`;
  window.location.href = `auth.html?redirect=${encodeURIComponent(redirect)}`;
  return false;
}

function storePendingAttempt(data) {
  localStorage.setItem('pending_quiz_attempt', JSON.stringify(data));
}

function getPendingAttempt() {
  const raw = localStorage.getItem('pending_quiz_attempt');

  if (!raw) {
    return null;
  }

  try {
    return JSON.parse(raw);
  } catch {
    return null;
  }
}
