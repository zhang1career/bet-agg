# Frontend compliance migration (API + schema)

This document ties **regulatory/compliance positioning** to concrete **client changes** after the backend cutover to **predictions + reputation**. The public HTTP prefix remains **`/api/bet/*`**; legacy **`POST /api/bet/place`** and points-related APIs are **not** available.

## 1. Base URL and routes

All documented JSON lives under **`/api/bet/*`** (same prefix as before): `dict`, `games`, `markets`, `submit`, `orders`, `reputation`, `leaderboard`, `snowflake`. Health remains `/up`; spec remains `GET /api/openapi.json`.

Replace any client code still calling **`POST /api/bet/place`** with **`POST /api/bet/submit`** and the new body shape (see below).

## 2. Authentication (unchanged mechanism)

- Continue sending user token per gateway contract; bet-agg still reads **`X-User-Access-Token`** (raw JWT) when calling the service directly.

## 3. Snowflake + submit (breaking)

| Step | Old | New |
|------|-----|-----|
| Mint id | `POST /api/bet/snowflake` | `POST /api/bet/snowflake` |
| Submit | `POST /api/bet/place` | `POST /api/bet/submit` |

**Request body:** remove `stake_points`, `expected_odds_millis`, and any odds/stake UI. Body must be:

```json
{ "lines": [{ "market_id": 123, "outcome_code": "home_win" }] }
```

Only **one** element in `lines` is accepted server-side.

**Headers:** still require **`X-Request-Id`** (decimal snowflake from mint) for idempotent submit.

**Response:** shape is still envelope + `data` with `order`, `is_replay`, `_dict`; `_dict` keys use **`bet_order_status`** for submit responses.

## 4. Catalog (games / markets)

- Endpoints: `GET /api/bet/games`, `GET /api/bet/games/{game_id}`, `GET /api/bet/markets`, `GET /api/bet/markets/{market_id}`.
- **Remove** all UI and types for `current_odds_millis`, `odds_millis`, or any per-outcome price. Market list/detail no longer expose odds columns.
- **Detail** may include `selections` (synthetic legs with `code` + label). Drive pickers from that list; map selected `code` to `outcome_code` in submit payload.

## 5. Orders

- List: `GET /api/bet/orders` — summary rows: `id`, `uid`, `status`, `ct`, `ut` (no monetary fields).
- Detail: `GET /api/bet/orders/{id}` — includes `lines[]` with `market_id`, `selection`, `pick_label`, `result` (integer enum). `_dict` includes **`bet_order_status`** and **`order_item_result`**.

Client models may keep a “BetOrder” name aligned with persistence (`bet_order` / `order_item` tables).

**Note:** JSON uses `market_id` on each line; the DB column is `order_item.mid` — no extra client field is required beyond the public API.
 
## 6. Points → reputation

- Remove screens and API calls for **points balance / points ledger / hold state**.
- **New:** `GET /api/bet/reputation` → `{ "score": <int> }` inside envelope `data`.
- **New:** `GET /api/bet/leaderboard` → `data.items[]` with `{ rank, uid, score }`, plus pagination.

**Copy / compliance:** present `score` as **reputation** (skill track record), not currency; no “充值 / 提现 / 积分兑换”. Avoid implying guaranteed financial return.

## 7. Dictionary endpoint

- `GET /api/bet/dict?codes=...` (same query shape as before if you used dict on bet path).
- Replace requested code strings: use **`bet_order_status`**, **`order_item_result`** where you previously used bet order / line dicts. Remove **`points_hold_state`** if referenced.
- Catalog payloads (`games` / `markets`) may embed `_dict` with **`game_status`** and **`market_status`**; request those codes from `dict` when you need labels outside embedded `_dict`.

## 8. Schema-driven expectations (`docs/schema.sql`)

- **Core tables** for orders and reputation: **`bet_order`**, **`order_item`** (FK-style columns `oid` → `bet_order.id`, `mid` → `biz_market.id`), **`points_balance`**, **`points_flow`**. The `points_*` names are legacy; in this product they store **non-redeemable reputation** only (`points_flow.oid` ties ledger rows to **`bet_order.id`**).
- **Removed vs legacy wager stack:** **`POST /api/bet/place`**, stake/odds request fields, redeemable wallet / hold flows, and **`biz_market.odds_millis`** (column absent in current schema).
- When building admin or support tools that read the DB directly, use these table/column names; the public JSON API still uses stable field names such as `market_id` on lines (see §5).

## 9. Minimal UI checklist

- [ ] Rename visible product strings: prediction, reputation, leaderboard — not “下注 / 盘口赔率 / 余额”.
- [ ] Strip stake inputs and balance displays.
- [ ] Strip odds movement / “expected odds” validation.
- Wire mint → **`POST /api/bet/submit`**; handle HTTP **201** vs **200** on replay (same as before semantically).
- [ ] Order status labels from `_dict.bet_order_status`.
- [ ] Line result labels from `_dict.order_item_result` on detail views.

## 10. Testing suggestions (frontend)

- Contract test or smoke against `docs/api.json` for path list and example `BetSubmitBody`.
- E2E: open market → pick outcome → mint → submit → list orders → see reputation/leaderboard update after settlement in a staging environment.
