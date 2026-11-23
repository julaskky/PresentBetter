const API_BASE = 'http://localhost/presentbetter/api';

let csrfToken = '';

async function request(path, options = {}) {
  const method = (options.method || 'GET').toUpperCase();
  const headers = new Headers(options.headers || {});
  const isFormData = options.body instanceof FormData;

  if (
    !isFormData &&
    options.body !== undefined &&
    !headers.has('Content-Type')
  ) {
    headers.set('Content-Type', 'application/json');
  }

  if (method !== 'GET' && method !== 'HEAD' && csrfToken) {
    headers.set('X-CSRF-Token', csrfToken);
  }

  let response;

  try {
    response = await fetch(`${API_BASE}${path}`, {
      ...options,
      method,
      headers,
      credentials: 'include',
    });
  } catch {
    throw new Error(
      'Could not connect to PresentBetter. Check that Apache and MySQL are running in XAMPP.'
    );
  }

  if (response.status === 204) return {};

  const contentType = response.headers.get('content-type') || '';
  const data = contentType.includes('application/json')
    ? await response.json()
    : { message: 'The server returned an unexpected response.' };

  if (!response.ok) {
    if (response.status === 419) {
      csrfToken = '';
    }

    throw new Error(data.message || 'The request could not be completed.');
  }

  return data;
}

export async function bootstrap() {
  const result = await request('/auth/csrf.php');

  csrfToken = result.csrfToken || result.token || '';

  if (!csrfToken) {
    throw new Error(
      'Could not establish a security token. Refresh and try again.'
    );
  }

  return result;
}

export const api = {
  // Authentication
  me: () => request('/auth/me.php'),

  register: (payload) =>
    request('/auth/register.php', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),

  login: (payload) =>
    request('/auth/login.php', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),

  logout: () =>
    request('/auth/logout.php', {
      method: 'POST',
      body: JSON.stringify({}),
    }),

  // Dashboard and presentations
  dashboardOverview: () =>
    request('/dashboard/overview.php'),

  presentations: () =>
    request('/presentations/index.php'),

  submitPresentation: (formData) =>
    request('/presentations/index.php', {
      method: 'POST',
      body: formData,
    }),

  presentationDownloadUrl: (id) =>
    `${API_BASE}/presentations/download.php?id=${encodeURIComponent(id)}`,

  // Admin: assignment management
  assignments: () =>
    request('/assignments/index.php'),

  assignPresentation: (presentationId, consultantId) =>
    request('/assignments/index.php', {
      method: 'POST',
      body: JSON.stringify({
        action: 'assign',
        presentationId,
        consultantId,
      }),
    }),

  unassignPresentation: (presentationId, consultantId) =>
    request('/assignments/index.php', {
      method: 'POST',
      body: JSON.stringify({
        action: 'unassign',
        presentationId,
        consultantId,
      }),
    }),

  // Consultant: load a review editor for an assigned presentation
  review: (presentationId) =>
    request(
      `/reviews/index.php?presentationId=${encodeURIComponent(presentationId)}`
    ),

  saveReview: (payload) =>
    request('/reviews/index.php', {
      method: 'POST',
      body: JSON.stringify(payload),
    }),

  // Presenter: list published feedback for their own presentations
  feedback: () =>
    request('/reviews/index.php'),
};
