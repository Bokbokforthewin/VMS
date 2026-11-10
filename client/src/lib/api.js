import axios from "axios";

export const TOKEN_KEY = "vms_token";

export const api = axios.create({
  baseURL: import.meta.env.VITE_API_URL,
  headers: { Accept: "application/json" },
});

api.interceptors.request.use((config) => {
  const token = localStorage.getItem(TOKEN_KEY);
  if (token) config.headers.Authorization = `Bearer ${token}`;
  return config;
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const isLogin = error.config?.url?.endsWith("/login");
    if (error.response?.status === 401 && !isLogin) {
      localStorage.removeItem(TOKEN_KEY);
      window.location.href = "/login";
    }
    return Promise.reject(error);
  },
);

/** Turns a Laravel error response into { message, fields } (fields = per-field errors). */
export function getErrors(err) {
  if (axios.isAxiosError(err)) {
    const data = err.response?.data;
    return { message: data?.message ?? err.message, fields: data?.errors ?? {} };
  }
  return { message: "Something went wrong.", fields: {} };
}

/** Laravel sometimes wraps lists in { data: [...] }, sometimes not. */
export function unwrap(value) {
  if (!value) return [];
  return Array.isArray(value) ? value : value.data;
}