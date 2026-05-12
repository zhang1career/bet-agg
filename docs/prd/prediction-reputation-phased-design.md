# Phased product design: prediction + reputation (compliance)

## Goal

Shift the product from **wagering language** (stakes, odds, balances) to **outcome predictions** with **non-redeemable reputation** only. Settlement updates line results and applies bounded reputation deltas; void outcomes do not move reputation.

## Scope

- **This repository (bet-agg):** backend API, persistence, admin tooling, settlement batch integration.
- **Frontend:** already exists; must align with new paths, payloads, and copy (see `frontend-compliance-migration.md`).

## Phase 0 — Contract freeze (done in this iteration)

- Canonical HTTP surface: `/api/bet/*` (+ `/api/openapi.json`, `/up`).
- Schema source of truth: `docs/schema.sql`.
- API contract: `docs/api.json` (OpenAPI 3.0.3).

## Phase 1 — User-facing prediction loop

1. **Browse:** `GET /api/bet/games`, `GET /api/bet/markets` (nested game, CMS-enriched copy; **no odds**).
2. **Choose outcome:** client shows synthetic legs (`home_win` / `draw` / `away_win`) from `selections` on market detail when present.
3. **Submit:** `POST /api/bet/snowflake` → use `data.id` as `X-Request-Id` → `POST /api/bet/submit` with body `{ "lines": [{ "market_id", "outcome_code" }] }` (exactly one line).
4. **History:** `GET /api/bet/orders`, `GET /api/bet/orders/{id}`.
5. **Reputation:** `GET /api/bet/reputation` (`score`); **leaderboard** `GET /api/bet/leaderboard` (rank, uid, score only).

**Compliance notes:** no stake field; no quoted odds; no cashable wallet; copy should say **预测 / 声誉** not **下注 / 赔率 / 提现**.

## Phase 2 — Settlement + reputation ledger

- Batch settlement (existing job flow) resolves prediction lines against game `settle_outcomes`.
- **Win / lose:** apply configured `reputation.delta_win` / `reputation.delta_lose` once per order (idempotent flows keyed by order + kind).
- **Void:** line `result` void; **no** reputation credit/debit for that order.

## Phase 3 — Admin & operations

- Admin UI: markets without odds editing; orders show prediction status; reputation instead of points where applicable.
- Dict keys: `bet_order_status`, `order_item_result` (and existing catalog dicts as needed).

## Out of scope (explicit)

- Backward compatibility with legacy **`POST /api/bet/place`** (stake/odds), points APIs, or old points tables.
- Payouts, redemption, or fiat/crypto rails.

## Success criteria

- OpenAPI route contract tests pass against Laravel route table.
- PHPUnit green for prediction submit, catalog, settlement reputation, OpenAPI.
- Frontend can migrate using `docs/api.json` + `docs/schema.sql` + migration doc without guessing field semantics.
