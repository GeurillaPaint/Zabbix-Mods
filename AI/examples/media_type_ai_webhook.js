var AIWebhook = {
    logPrefix: '[AI frontend module webhook] ',

    parseParams: function (raw) {
        var params;

        try {
            params = JSON.parse(raw);
        }
        catch (error) {
            throw 'Failed to parse webhook parameters JSON: ' + error;
        }

        if (typeof params !== 'object' || params === null) {
            throw 'Webhook parameters must be a JSON object.';
        }

        return params;
    },

    ensure: function (params, key) {
        if (!params[key] || String(params[key]).trim() === '') {
            throw 'Missing required parameter: ' + key;
        }
    },

    normalizeTags: function (params) {
        if (params.event_tags_json && String(params.event_tags_json).trim() !== '') {
            try {
                return JSON.parse(params.event_tags_json);
            }
            catch (error) {
                Zabbix.log(4, AIWebhook.logPrefix + 'Could not parse event_tags_json, falling back to text. ' + error);
            }
        }

        if (params.event_tags && String(params.event_tags).trim() !== '') {
            return String(params.event_tags);
        }

        return [];
    },

    buildBody: function (params) {
        return {
            eventid: params.eventid || '',
            event_value: params.event_value || '1',
            trigger_name: params.trigger_name || params.problem_name || '',
            hostname: params.hostname || params.host || '',
            severity: params.severity || '',
            opdata: params.opdata || '',
            event_url: params.event_url || '',
            event_tags: AIWebhook.normalizeTags(params)
        };
    },

    send: function (params) {
        var request = new HttpRequest();
        var body = AIWebhook.buildBody(params);
        var response;
        var parsed;

        request.addHeader('Content-Type: application/json');
        request.addHeader('Accept: application/json');

        if (params.shared_secret && String(params.shared_secret).trim() !== '') {
            request.addHeader('X-AI-Webhook-Secret: ' + String(params.shared_secret).trim());
            body.shared_secret = String(params.shared_secret).trim();
        }

        Zabbix.log(4, AIWebhook.logPrefix + 'POST ' + params.ai_webhook_url + ' payload=' + JSON.stringify(body));
        response = request.post(params.ai_webhook_url, JSON.stringify(body));

        if (request.getStatus() < 200 || request.getStatus() >= 300) {
            throw 'HTTP ' + request.getStatus() + ': ' + response;
        }

        try {
            parsed = JSON.parse(response);
        }
        catch (error) {
            throw 'Webhook response is not valid JSON: ' + error + '; body=' + response;
        }

        if (!parsed.ok) {
            throw parsed.error || 'Webhook endpoint returned ok=false';
        }

        return parsed;
    }
};

try {
    var params = AIWebhook.parseParams(value);

    AIWebhook.ensure(params, 'ai_webhook_url');
    AIWebhook.ensure(params, 'eventid');
    AIWebhook.ensure(params, 'event_value');
    AIWebhook.ensure(params, 'trigger_name');
    AIWebhook.ensure(params, 'hostname');

    var result = AIWebhook.send(params);

    Zabbix.log(4, AIWebhook.logPrefix + 'Success. posted_chunks=' + (result.posted_chunks || 0));

    return JSON.stringify({
        ok: true,
        posted_chunks: result.posted_chunks || 0
    });
}
catch (error) {
    Zabbix.log(4, AIWebhook.logPrefix + 'Failed: ' + error);
    throw 'AI webhook failed: ' + error;
}
