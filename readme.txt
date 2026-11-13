=== Approval Gate for WooCommerce ===
Contributors: evolurise
Tags: woocommerce, rest api, ai agents, approval, automation
Requires at least: 6.4
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let AI agents, automations and contractors propose changes through the REST API. A human approves in wp-admin; only then is the change applied.

== Description ==

You want an AI agent, a Zapier/Make/n8n scenario or an outside developer to work on your store through the REST API. You do not want it to change prices, cancel orders, refund customers or delete products **without someone looking first**.

Approval Gate turns selected API keys into *proposal mode*:

* Reads work exactly as before.
* A write (POST, PUT, PATCH, DELETE) on orders, products, customers, coupons, settings, webhooks, shipping or taxes is **stored instead of executed** and answered with `202 Accepted`, a proposal id and a status URL.
* The proposal shows up in **WooCommerce → Approval Gate** with a one-line summary (*update product #9 (regular_price: 19.90)*), a risk level (low / medium / high), the full payload and the reason the integration gave.
* **Approve** replays the exact request through WooCommerce, as the key's user. WooCommerce validates it, applies it, and the response is stored. **Reject** stores your note.
* The integration polls `GET /wc/v3/approval-gate/proposals/{id}` and gets `approved` with WooCommerce's response, `rejected` with your note, or `expired`.

Nothing is reimplemented: the plugin never writes to orders or products itself. It delays the request and lets WooCommerce run it later.

= Why =

AI agents are good at *proposing* changes and bad at being trusted blindly. Most stores solve this today by giving the agent a read-only key and doing the writes by hand, or by giving it a full key and hoping. This plugin is the middle: the agent does the work, you keep the decision, and the audit trail writes itself.

= Features =

* **Per-key proposal mode** — tick the keys that must go through review. Everything else is untouched.
* **Per-request opt-in** — any key can send `X-WC-Approval: propose` to have one risky request reviewed while acting directly on the rest.
* **Gated resources** — choose which resources need approval (default: all eight).
* **Summary & risk** — the reviewer sees what matters in two seconds; deletes, refunds, settings, webhooks and customers are *high*; prices and statuses *medium*.
* **Reason header** — `X-WC-Approval-Reason: competitor dropped price` is shown to the reviewer.
* **Idempotency-Key** — resending the same request yields the same proposal, not a duplicate.
* **Bulk approve / reject**, a note sent back to the integration, admin bar badge, email on new proposals, Slack-compatible webhook on created / approved / rejected / failed.
* **Expiry** — unreviewed proposals expire (default 7 days) and can never be applied by accident later.
* **WP-CLI** — `wp wc approval-gate list|show|approve|reject|gate|stats`.
* **REST** — `GET /wc/v3/approval-gate/proposals` and `/{id}`, scoped to the calling key.
* Compatible with High-Performance Order Storage.

= Works with =

Pairs with [Scoped API Keys for WooCommerce](https://github.com/walidsadfi-evolurise/scoped-api-keys-for-woocommerce) (what a key may *see*) and [woocommerce-mcp-server](https://github.com/walidsadfi-evolurise/woocommerce-mcp-server) (an MCP server that already talks to this plugin's proposal flow).

= Hooks =

* `wcag_is_gated( $gated, $key, $request )` — decide per request (e.g. gate only orders over 500).
* `wcag_proposal_created( $row, $request )`
* `wcag_proposal_decided( $row, $response|null )`
* `wcag_webhook_payload( $payload, $row )`

== Installation ==

1. Install and activate. WooCommerce 8.0+ must be active.
2. **WooCommerce → Approval Gate → Keys**: tick the API keys that must go through review.
3. Optionally set a Slack webhook under Settings so the team sees proposals as they arrive.
4. Your integration keeps calling the REST API as before. It receives `202` with a `status_url` for gated writes; poll it, or wait for your own webhook.

== Frequently Asked Questions ==

= Does the integration need to change its code? =

Only if it wants to know the outcome. A write returns `202` instead of `200/201`, with `{"status":"pending","id":"…","status_url":"…"}`. Poll the status URL, or treat 202 as "submitted". The Location header also points to the status URL.

= Who runs the change when I approve? =

The user who owns the API key, with WooCommerce's own permission checks. A *read*-only key can never get a write through, even approved.

= What if the request is no longer valid when approved (product deleted, order already refunded)? =

WooCommerce answers with its usual error. The proposal is marked *failed* with that response, and the integration sees it.

= Does this touch the Store API, checkout or the admin? =

No. Only requests authenticated with a consumer key on `/wc/v3/*` writes to the gated resources are evaluated. Cookie-authenticated admin requests and application passwords are never gated.

= Can I gate only some situations? =

Use the `wcag_is_gated` filter, e.g. require approval for refunds above an amount, or only outside business hours.

== Screenshots ==

1. Review queue: summary, risk, key, request, expiry; approve or reject in bulk.
2. One proposal: payload, reason, WooCommerce response after approval.
3. Keys in proposal mode.

== Changelog ==

= 1.0.0 =
* Initial release.
