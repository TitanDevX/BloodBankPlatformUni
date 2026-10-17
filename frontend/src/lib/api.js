// Vite proxies /v1 to Laravel during development; deployed hosts should route it there too.
export function createApiClient(baseUrl, fetcher = fetch) {
  const base = baseUrl.replace(/\/$/, '');
  return async function request(path, { token, ...options } = {}) {
    let response;
    try {
      response = await fetcher(`${base}${path}`, { ...options, headers: { Accept: 'application/json', ...(options.body ? { 'Content-Type': 'application/json' } : {}), ...(token ? { Authorization: `Bearer ${token}` } : {}) } });
    } catch { throw new Error('We couldn’t reach the service. Check your connection and try again.'); }
    if (!response.ok) {
      if (response.status === 401) throw new Error('Your email or password is incorrect, or your account is inactive.');
      if (response.status === 429) throw new Error('Too many attempts. Please wait a moment before trying again.');
      throw new Error('We couldn’t complete your request. Please try again or contact your administrator.');
    }
    return response.status === 204 || options.method === 'DELETE' ? null : response.json();
  };
}
export const api = createApiClient(import.meta.env?.VITE_API_BASE_URL || '/v1');
