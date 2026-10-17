const base = (import.meta.env.VITE_API_BASE_URL || '').replace(/\/$/, '');
export const apiConfigured = Boolean(base);
export async function api(path, { token, ...options } = {}) {
  if (!base) throw new Error('Sign-in is not connected yet. Explore the demo workspace, or contact your administrator.');
  let response;
  try {
    response = await fetch(`${base}${path}`, { ...options, headers: { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...(token ? { Authorization: `Bearer ${token}` } : {}) } });
  } catch { throw new Error('We couldn’t reach the service. Check your connection and try again.'); }
  if (!response.ok) {
    if (response.status === 401) throw new Error('Your email or password is incorrect, or your account is inactive.');
    if (response.status === 429) throw new Error('Too many attempts. Please wait a moment before trying again.');
    throw new Error('We couldn’t complete your request. Please try again or contact your administrator.');
  }
  return response.status === 204 || options.method === 'DELETE' ? null : response.json();
}
