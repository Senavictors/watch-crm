const DEFAULT_API_BASE_URL = "http://localhost:8000/api";

export function getApiBaseUrl() {
  return process.env.NEXT_PUBLIC_API_BASE_URL ?? DEFAULT_API_BASE_URL;
}

export async function ensureCsrfCookie(apiBaseUrl: string) {
  await fetch(`${apiBaseUrl}/csrf-cookie`, {
    method: "GET",
    credentials: "include",
    headers: {
      Accept: "application/json",
    },
  });
}

export function getCsrfTokenFromCookie() {
  if (typeof document === "undefined") return "";

  const cookie = document.cookie
    .split("; ")
    .find((entry) => entry.startsWith("XSRF-TOKEN="));

  return cookie ? decodeURIComponent(cookie.split("=").slice(1).join("=")) : "";
}

export async function apiFetch(input: string, init: RequestInit = {}, options?: { csrf?: boolean }) {
  const headers = new Headers(init.headers);
  headers.set("Accept", "application/json");

  if (options?.csrf) {
    const token = getCsrfTokenFromCookie();
    if (token) {
      headers.set("X-XSRF-TOKEN", token);
    }
  }

  return fetch(input, {
    ...init,
    credentials: "include",
    headers,
  });
}

export async function getErrorMessage(response: Response, fallback: string): Promise<string> {
  let message = fallback;
  try {
    const payload = await response.json();
    if (payload?.message) message = payload.message;
    if (payload?.errors && typeof payload.errors === "object") {
      const first = Object.values(payload.errors)[0];
      if (Array.isArray(first) && first[0]) message = String(first[0]);
    }
  } catch {
    // noop
  }
  return message;
}

export async function apiCreate<T>(path: string, body: unknown, fallback: string): Promise<T> {
  const apiBaseUrl = getApiBaseUrl();
  await ensureCsrfCookie(apiBaseUrl);
  const response = await apiFetch(
    `${apiBaseUrl}${path}`,
    {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    },
    { csrf: true }
  );
  if (!response.ok) {
    throw new Error(await getErrorMessage(response, fallback));
  }
  return response.json() as Promise<T>;
}

export async function apiUpdate<T>(path: string, body: unknown, fallback: string): Promise<T> {
  const apiBaseUrl = getApiBaseUrl();
  await ensureCsrfCookie(apiBaseUrl);
  const response = await apiFetch(
    `${apiBaseUrl}${path}`,
    {
      method: "PATCH",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(body),
    },
    { csrf: true }
  );
  if (!response.ok) {
    throw new Error(await getErrorMessage(response, fallback));
  }
  return response.json() as Promise<T>;
}
