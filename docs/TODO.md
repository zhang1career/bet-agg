# TODO（改进 backlog）

与安全、性能相关的后续改进，便于排期与跟踪。

## 安全

- **`POST /api/bet/snowflake` 鉴权**：需携带 `X-User-Access-Token`。仍建议对 `api/bet/snowflake` 做限流，避免刷爆 service_foundation。
- **生产 `APP_DEBUG`**：`ApiJsonExceptionHandler` 在 500 时若开启 debug 会把异常信息返回给客户端。生产环境必须保持 `APP_DEBUG=false`。
- **`LogApiHttpErrors`**：对 `api/*` 非 2xx 会记录最多约 2KB 的 `response_preview`，可能含敏感片段。生产可考虑缩短/脱敏 5xx 的 body 预览，或关闭详细 body 日志。
- **`APP_DEBUG` 与出站 HTTP 调试**：开启 debug 时全局 Http 中间件可能记录请求体；snowflake 请求 JSON 中含 `access_key`，勿在含真实密钥的环境长期打开详细出站日志。
- **Foundation 用户缓存**：`UserFoundationGateway` 按 token 哈希缓存上游 `/api/user/me` 的 JSON；Redis 需访问控制与敏感数据分级；可按需缩短 `BET_FOUNDATION_USER_CACHE_TTL_SECONDS`。
- **`SF_SNOWFLAKE_ACCESS_KEY`**：仅存环境变量或密钥管理系统；与 foundation 侧轮换策略对齐。
- **应用层限流（全局）**：当前未见对 `api/*` 的 Laravel `throttle`；暴力请求、无效 token 探测等更依赖网关/WAF；可按路由补充限流策略。

## 性能

- **Snowflake 预取**：每次下单前若都调用 `POST /api/bet/snowflake` 会增加一次 RTT；agent 或客户端可批量预取若干 id 再消费（产品/协议层策略）。
- **`GET /api/bet/dict`**：已对 `codes` 查询串做 512 字符上限；若仍担心热点，可对 `(codes)` 做短 TTL 缓存（注意字典变更时的失效策略）。
- **Catalog 与 CMS**：列表页依赖 CMS 批量接口；关注 CMS/网关延迟与 `API_GATEWAY_TIMEOUT_SECONDS`，必要时监控 p99 与错误率。
- **OpenAPI 发现**：`/api/openapi.json` 已设 `Cache-Control: public, max-age=300`；高并发 agent 场景可经 CDN/网关缓存减轻本机压力。
