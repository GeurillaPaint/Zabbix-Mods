<?php declare(strict_types = 0);

namespace Modules\AI\Lib;

class PromptBuilder {

    public static function buildSystemPrompt(array $config, array $context = []): string {
        $config = Config::mergeWithDefaults($config);

        $blocks = [];

        foreach ($config['instructions'] as $instruction) {
            if (!is_array($instruction) || !Util::truthy($instruction['enabled'] ?? false)) {
                continue;
            }

            $content = Util::cleanMultiline($instruction['content'] ?? '', 50000);

            if ($content !== '') {
                $blocks[] = $content;
            }
        }

        if ($blocks === []) {
            $blocks[] = Config::defaults()['instructions'][0]['content'];
        }

        $enabled_links = [];

        foreach ($config['reference_links'] as $link) {
            if (!is_array($link) || !Util::truthy($link['enabled'] ?? false)) {
                continue;
            }

            $url = Util::cleanUrl($link['url'] ?? '');

            if ($url === '') {
                continue;
            }

            $title = Util::cleanString($link['title'] ?? '', 128);
            $enabled_links[] = ($title !== '') ? ('- '.$title.': '.$url) : ('- '.$url);
        }

        if ($enabled_links) {
            $blocks[] = "If useful, suggest these operator reference links exactly as written:\n".implode("\n", $enabled_links);
        }

        if (!empty($context['mode'])) {
            $blocks[] = 'Current mode: '.Util::cleanString($context['mode'], 64).'.';
        }

        if (!empty($context['response_style'])) {
            $blocks[] = Util::cleanMultiline($context['response_style'], 1000);
        }

        return trim(implode("\n\n", array_filter($blocks, static function($value) {
            return trim((string) $value) !== '';
        })));
    }

    public static function buildChatContextBlock(array $context): string {
        $lines = [];

        if (!empty($context['eventid'])) {
            $lines[] = 'Event ID: '.Util::cleanString($context['eventid'], 128);
        }

        if (!empty($context['hostname'])) {
            $lines[] = 'Hostname: '.Util::cleanString($context['hostname'], 255);
        }

        if (!empty($context['problem_summary'])) {
            $lines[] = 'Problem summary: '.Util::cleanMultiline($context['problem_summary'], 2000);
        }

        if (!empty($context['os_type'])) {
            $lines[] = 'Host OS: '.Util::cleanString($context['os_type'], 128);
        }

        if (!empty($context['netbox_info'])) {
            $lines[] = "NetBox / CMDB context:\n".$context['netbox_info'];
        }

        if (!empty($context['extra_context'])) {
            $lines[] = "Additional operator context:\n".Util::cleanMultiline($context['extra_context'], 6000);
        }

        return trim(implode("\n\n", $lines));
    }

    public static function buildWebhookUserPrompt(array $payload, array $context): string {
        $lines = [];
        $lines[] = 'Generate first-line troubleshooting guidance for the following Zabbix problem.';

        if (!empty($payload['trigger_name'])) {
            $lines[] = 'Problem: '.Util::cleanMultiline($payload['trigger_name'], 2000);
        }

        if (!empty($payload['hostname'])) {
            $lines[] = 'Hostname: '.Util::cleanString($payload['hostname'], 255);
        }

        if (!empty($payload['eventid'])) {
            $lines[] = 'Event ID: '.Util::cleanString($payload['eventid'], 128);
        }

        if (!empty($payload['severity'])) {
            $lines[] = 'Severity: '.Util::cleanString($payload['severity'], 128);
        }

        if (!empty($payload['opdata'])) {
            $lines[] = "Operational data:\n".Util::cleanMultiline($payload['opdata'], 4000);
        }

        if (!empty($payload['event_url'])) {
            $lines[] = 'Event URL: '.Util::cleanUrl($payload['event_url']);
        }

        if (!empty($payload['event_tags_text'])) {
            $lines[] = "Event tags:\n".$payload['event_tags_text'];
        }

        if (!empty($context['os_type'])) {
            $lines[] = 'Host OS: '.Util::cleanString($context['os_type'], 128);
        }

        if (!empty($context['netbox_info'])) {
            $lines[] = "NetBox / CMDB context:\n".$context['netbox_info'];
        }

        $lines[] = 'Reply in Markdown.';

        return implode("\n\n", $lines);
    }
}
