"use client";

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
