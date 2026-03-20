# Suggested Zabbix webhook media type setup

Use `examples/media_type_ai_webhook.js` as the JavaScript body of a Zabbix **Webhook** media type.

## Suggested parameters

Set these media type parameters:

- `ai_webhook_url` = `https://your-zabbix-frontend/zabbix.php?action=ai.webhook`
- `shared_secret` = `your-shared-secret` (optional but recommended)
- `eventid` = `{EVENT.ID}`
- `event_value` = `{EVENT.VALUE}`
- `trigger_name` = `{EVENT.NAME}`
- `hostname` = `{HOST.HOST}`
- `severity` = `{EVENT.SEVERITY}`
- `opdata` = `{EVENT.OPDATA}`
- `event_url` = `https://your-zabbix-frontend/tr_events.php?triggerid={TRIGGER.ID}&eventid={EVENT.ID}`
- `event_tags` = `{EVENT.TAGS}`

## Notes

- If you prefer JSON tags, add your own parameter like `event_tags_json` and populate it from a preprocessing step or a custom action format.
- The module accepts both `X-AI-Webhook-Secret` header and `shared_secret` in the JSON body.
- The module can ignore resolved events when `event_value=0` if that option is enabled in module settings.
- The AI answer can be split into multiple problem update comments when it exceeds the configured chunk size.

## Where the result appears

If **Add problem update** is enabled in module settings, the AI response is written back to the originating Zabbix event as one or more problem update comments.
