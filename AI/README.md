# Installation

# Place AI project folder in
/usr/share/zabbix/modules/AI

# Set directory permissions
find /usr/share/zabbix/modules/AI -type d -exec chmod 0755 {} \;

# Set file permissions
find /usr/share/zabbix/modules/AI -type f -exec chmod 0644 {} \;

# Set SELinux context
sudo semanage fcontext -a -t httpd_sys_content_t '/usr/share/zabbix/modules/AI(/.*)?'

# Apply the SELinux context
sudo restorecon -Rv /usr/share/zabbix/modules/AI

setsebool -P httpd_can_network_connect on

# AI frontend module for Zabbix 7

A self-contained Zabbix frontend module that adds:

- **Monitoring → AI → Chat** for operator chat inside Zabbix
- **Monitoring → AI → Settings** for provider, instruction, secret and integration management
- **`zabbix.php?action=ai.webhook`** as an internal webhook endpoint for problem enrichment and AI-generated first-line guidance
- **Problem update posting** back to the originating event through the Zabbix API
- **Optional NetBox enrichment** for VM/device/service context

## What this module does

### Chat page

- Session-only chat UI inside Zabbix
- Chat history is stored in **browser `sessionStorage` only**
- No server-side chat persistence is implemented by the module
- Optional context fields: Event ID, hostname, problem summary, extra operator context
- Button to post the **last AI answer** back to a Zabbix event as problem update comments

### Settings page

You can add/remove/manage:

- Providers
- Global instruction blocks
- Reference links
- Zabbix API settings
- NetBox settings
- Webhook behavior
- Chat behavior

### Provider types supported in this version

- `openai_compatible`
- `ollama`

This means you can use:

- OpenAI / OpenAI-compatible APIs
- Local Ollama endpoints
- Other gateways that expose an OpenAI-compatible `/chat/completions` schema

If you need a native provider schema later (for example a non-OpenAI-compatible Anthropic/Gemini endpoint), extend `lib/ProviderClient.php`.

## Directory layout

Place the **`AI`** folder inside your frontend modules directory, for example:

```text
/usr/share/zabbix/ui/modules/AI
```

or for source installs:

```text
/path/to/zabbix/ui/modules/AI
```

The important part is that the module directory contains `manifest.json` directly.

## Installation

1. Copy the `AI` directory into `zabbix/ui/modules/`.
2. Ensure the web server user can read the files.
3. In Zabbix frontend, go to:

   ```text
   Administration → General → Modules
   ```

4. Click **Scan directory**.
5. Enable the **AI** module.
6. Open:

   ```text
   Monitoring → AI → Settings
   ```

7. Configure at least one provider.

## Recommended initial configuration

### 1. Provider

For OpenAI-compatible APIs:

- **Type:** `openai_compatible`
- **Endpoint:**
  - `https://api.openai.com/v1`
  - or the full endpoint `https://api.openai.com/v1/chat/completions`
- **Model:** e.g. `gpt-4.1-mini`
- **API key:** use the field or preferably an environment variable

For Ollama:

- **Type:** `ollama`
- **Endpoint:** `http://localhost:11434/api/chat`
- **Model:** e.g. `llama3.2:3b`

### 2. Zabbix API

Configure a Zabbix API URL and token if you want:

- OS lookup by hostname
- Problem update comments back to the event

Example:

- **API URL:** `https://zabbix.example.se/api_jsonrpc.php`
- **Auth mode:** `auto`
- **Token env var:** `ZABBIX_API_TOKEN`

### 3. NetBox

Optional.

If enabled, the module tries to find matching:

- Virtual machines
- Devices
- Related IPAM services

### 4. Webhook

The internal webhook URL is:

```text
https://your-zabbix-frontend/zabbix.php?action=ai.webhook
```

You can protect it with a shared secret.

## Suggested media type wiring

An example Zabbix webhook script is included in:

```text
examples/media_type_ai_webhook.js
```

Suggested media type parameters are documented in:

```text
examples/media_type_setup.md
```

## Webhook payload compatibility

The module accepts either:

- a direct JSON payload with fields like `eventid`, `trigger_name`, `hostname`, etc.
- or a payload containing:

```json
{
  "message": "{...json string...}"
}
```

This was done so you can keep compatibility with the payload shape from your existing Python webhook.

## Security notes

- Prefer **environment variables** for secrets over storing them directly in module config.
- Enable TLS verification unless you have a specific internal reason not to.
- Treat third-party endpoints and third-party modules as trusted-only components.
- The webhook endpoint does **not** require a logged-in Zabbix UI session, so use a shared secret if you expose it beyond localhost/internal networks.

## Problem comment chunking

Zabbix problem update comments are size-limited, so the module splits longer AI answers into chunks before posting them back to the event.

## Important limitations

- No chat persistence by design
- No external FastAPI service required
- The module does **not** create any new Zabbix DB tables
- The module currently supports only `openai_compatible` and `ollama` provider schemas

## Files of interest

```text
manifest.json                     Module registration and default config
Module.php                        Menu wiring
actions/ChatView.php              Chat page controller
actions/ChatSend.php              Chat AJAX endpoint
actions/EventComment.php          Post AI response back to a Zabbix event
actions/SettingsView.php          Settings page controller
actions/SettingsSave.php          Settings save action
actions/Webhook.php               Internal webhook endpoint
lib/ProviderClient.php            LLM provider abstraction
lib/ZabbixApiClient.php           Zabbix API wrapper
lib/NetBoxClient.php              NetBox enrichment wrapper
lib/PromptBuilder.php             System/user prompt assembly
views/ai.chat.php                 Chat page view
views/ai.settings.php             Settings page view
assets/js/ai.chat.js              Session-only chat logic
assets/js/ai.settings.js          Dynamic settings rows
assets/css/ai.css                 Module styling
examples/media_type_ai_webhook.js Example Zabbix webhook media type script
examples/media_type_setup.md      Suggested media type parameters/macros
```

## Quick webhook smoke test

```bash
curl -k -X POST \
  'https://your-zabbix-frontend/zabbix.php?action=ai.webhook' \
  -H 'Content-Type: application/json' \
  -H 'X-AI-Webhook-Secret: your-shared-secret' \
  -d '{
        "eventid": "123456",
        "event_value": "1",
        "trigger_name": "CPU utilization is too high",
        "hostname": "server01",
        "severity": "High",
        "opdata": "CPU: 97%",
        "event_tags": [
          {"tag": "service", "value": "api"},
          {"tag": "team", "value": "platform"}
        ]
      }'
```

## Notes for future extension

Easy next extensions if you want them later:

- add a problem-page launcher or button
- add host inventory / item enrichment
- add provider-specific adapters (Anthropic, Gemini, Azure OpenAI, vLLM gateways)
- add RBAC checks per menu/action
- add markdown rendering instead of `<pre>` transcript display
- add per-provider temperature/max token controls
