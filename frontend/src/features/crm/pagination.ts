import { PaginationMeta } from "./types";

export const EMPTY_PAGINATION: PaginationMeta = {
  currentPage: 1,
  lastPage: 1,
  perPage: 20,
  total: 0,
  from: null,
  to: null,
};

export function appendPagination(params: URLSearchParams, page: number) {
  params.set("page", String(page));
  params.set("perPage", "20");
  return params;
}
