# Approval Gate for WooCommerce

**Let AI agents, automations and contractors *propose* changes through the WooCommerce REST API. A human approves in wp-admin; only then does WooCommerce apply the change.**

[![CI](https://github.com/walidsadfi-evolurise/approval-gate-for-woocommerce/actions/workflows/ci.yml/badge.svg)](https://github.com/walidsadfi-evolurise/approval-gate-for-woocommerce/actions/workflows/ci.yml)
![WordPress 6.4+](https://img.shields.io/badge/WordPress-6.4%2B-blue)
![WooCommerce 8.0+](https://img.shields.io/badge/WooCommerce-8.0%2B-96588a)
![License GPLv2](https://img.shields.io/badge/license-GPLv2-green)

## The problem

An AI agent (or a Make scenario, or the agency's developer) can do real work on a store through the REST API: fix prices, update stock, cancel orders, issue refunds. The only two options WooCommerce offers are *read* (it can't do the work) and *write* (it does whatever it decides, immediately). Teams end up either doing the writes by hand from the agent's suggestions, or trusting it blindly.

## What it does

Put a key in **proposal mode**. Its reads are unchanged. Its writes become proposals:

```http
PUT /wp-json/wc/v3/products/9
Authorization: Basic ck_…:cs_…
X-WC-Approval-Reason: competitor dropped to 19.90
Content-Type: application/json

{"regular_price":"19.90"}
```
```http
HTTP/1.1 202 Accepted
Location: https://shop.example/wp-json/wc/v3/approval-gate/proposals/5f1c…
X-WC-Approval-Status: pending

{
  "id": "5f1c…", "status": "pending",
  "summary": "update product #9 (regular_price: 19.90)", "risk": "medium",
  "status_url": "…/wc/v3/approval-gate/proposals/5f1c…",
  "review_url": "…/wp-admin/admin.php?page=wcag&proposal=42",
  "expires_at": "2026-11-20T10:00:00"
}
```

The reviewer sees it in **WooCommerce → Approval Gate**: summary, risk, who asked, why, the full payload. **Approve** replays the stored request through `WP_REST_Server` as the key's user — WooCommerce validates and applies it, exactly as if it had arrived now — and stores WooCommerce's response. **Reject** stores a note. The integration polls the status URL:

```json
{ "status": "approved", "note": "ok for the sale", "result_code": 200, "result": { "id": 9, "regular_price": "19.90", … } }
```

Nothing is reimplemented. The plugin never writes to orders or products itself; it holds the request and lets WooCommerce run it later.

## Features

- **Per-key proposal mode**, everything else untouched. **Per-request opt-in** with `X-WC-Approval: propose` for agents that act directly on small changes and ask on risky ones.
- **Gated resources**: orders (incl. refunds, notes), products (incl. variations, terms), customers, coupons, settings, webhooks, shipping, taxes — pick which.
- **Summary + risk** for the reviewer: deletes, refunds, settings, webhooks, customers → *high*; prices, statuses, batches → *medium*.
- `X-WC-Approval-Reason` shown to the reviewer; `Idempotency-Key` honoured (same key → same proposal).
- Bulk approve/reject, note returned to the integration, admin-bar badge, email, Slack-compatible webhook (`proposal.created|approved|rejected|failed`).
- **Expiry** (default 7 days): stale proposals can never be applied later by accident.
- WP-CLI: `wp wc approval-gate list|show|approve|reject|gate|stats`.
- REST: `GET /wc/v3/approval-gate/proposals[/{id}]`, scoped to the calling key (managers see all).
- A *read*-only key never gets a write through, approved or not. HPOS compatible.

## Install

Requires WordPress 6.4+, PHP 7.4+, WooCommerce 8.0+.

```bash
wp plugin install https://github.com/walidsadfi-evolurise/approval-gate-for-woocommerce/archive/refs/heads/main.zip --activate
wp wc approval-gate gate 12         # key #12 now proposes instead of writing
```

Or **WooCommerce → Approval Gate → Keys**, tick the key, save.

## How it works

```
determine_current_user ─ WooCommerce validates consumer key + secret        (unchanged)
rest_pre_dispatch @10  ─ WooCommerce checks the key's read/write permission  (unchanged)
rest_pre_dispatch @25  ─ WCAG_Gate: write on a gated resource from a gated key?
                           → store {method, route, query, body, summary, risk}
                           → return 202 + status URL           (controller never runs)
──────────────── later, in wp-admin ────────────────
approve                ─ WCAG_Decision: wp_set_current_user(key owner)
                           rest_do_request(stored request)      (WooCommerce runs it)
                           store response, notify, webhook
```

Hooks: `wcag_is_gated( $gated, $key, $request )` to gate conditionally (refunds above an amount, outside office hours…), `wcag_proposal_created`, `wcag_proposal_decided`, `wcag_webhook_payload`.

## The trio

| plugin | question it answers |
|---|---|
| [Scoped API Keys](https://github.com/walidsadfi-evolurise/scoped-api-keys-for-woocommerce) | what may this key **see and touch**? |
| **Approval Gate** | may this key **change** that **without asking**? |
| [Webhook Guard](https://github.com/walidsadfi-evolurise/webhook-guard-for-woocommerce) | did the store's **events reach** the integration? |

[woocommerce-mcp-server](https://github.com/walidsadfi-evolurise/woocommerce-mcp-server) is the agent-side counterpart (MCP server with PII masking and a proposal flow).

## Development

```bash
composer install && composer test && composer lint
php tests/run-without-phpunit.php      # unit tests, zero dependencies
```

Integration tests (CI, real WordPress + WooCommerce + MariaDB) authenticate with a consumer key, send writes through `WP_REST_Server`, and assert: ungated writes pass, gated writes return 202 and change nothing, approval applies the change and the key can poll the result, rejection leaves the store intact, header opt-in, idempotency, cross-key isolation, expiry.

## License

GPLv2 or later. Built by [Walid Sadfi](https://walidsadfi.com) — Forward Deployed Engineer, WooCommerce & AI agents.
