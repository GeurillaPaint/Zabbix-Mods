# AI module webhook setup

## Webhook endpoint (Zabbix 7.0+)

In Zabbix 7.0, module action controllers registered via `zabbix.php` require an
authenticated user session with explicit module access permissions. The Zabbix
server's media type engine makes HTTP requests without a frontend session, so
`zabbix.php?action=ai.webhook` returns "Page not found" for all external callers.

The module ships a standalone `webhook.php` at the module root that bypasses the
frontend router entirely. It connects to the database via PDO, loads the module
config, and processes the webhook — no session required.

**Set `ai_webhook_url` to:**

    https://YOUR_ZABBIX_FQDN/modules/AI/webhook.php

The `actions/Webhook.php` controller remains in the manifest for authenticated
use (e.g. testing from the browser while logged in), but the media type must
point at the standalone script.

## Installation

Copy the module into your Zabbix frontend's modules directory so the tree looks
like this:

- `AI/manifest.json`
- `AI/Module.php`
- `AI/webhook.php`          <-- standalone webhook endpoint
- `AI/actions/*.php`
- `AI/assets/css/ai.css`
- `AI/assets/js/ai.chat.js`
- `AI/assets/js/ai.settings.js`
- `AI/mediatype/media_type_ai_webhook.js`
- `AI/mediatype/AI_Troubleshooter_mediatypes.yaml`
- `AI/lib/*.php`
- `AI/views/ai.chat.php`
- `AI/views/ai.settings.php`

After placing the files, go to **Administration -> General -> Modules**, confirm
the module is enabled, and click **Scan directory** if it does not appear.

## Media type

Re-import `mediatype/AI_Troubleshooter_mediatypes.yaml` or paste
`mediatype/media_type_ai_webhook.js` into your Webhook media type.

Set the `ai_webhook_url` parameter to the standalone endpoint URL above.

In the **Test** dialog, replace macros with real values. If **Add problem
update** is enabled in module settings, use a real writable event ID.
