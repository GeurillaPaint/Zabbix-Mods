(function () {
    'use strict';

    function parseJsonSafe(text) {
        try {
            return JSON.parse(text);
        }
        catch (e) {
            return null;
        }
    }

    function unwrapResponse(text) {
        var parsed = parseJsonSafe(text);

        if (parsed && typeof parsed === 'object' && typeof parsed.main_block === 'string') {
            var inner = parseJsonSafe(parsed.main_block);
            if (inner) {
                return inner;
            }
        }

        return parsed;
    }

    function showPageStatus(root, message, isError) {
        if (!root) {
            return;
        }

        var existing = root.querySelector('.hc-page-status');
        if (existing) {
            existing.remove();
        }

        var el = document.createElement('div');
        el.className = 'hc-page-status hc-status ' + (isError ? 'hc-status-error' : 'hc-status-ok');
        el.textContent = message;

        root.insertBefore(el, root.firstChild.nextSibling);
    }

    function generateId(prefix) {
        return prefix + '_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
    }

    function initSettingsPage() {
        var root = document.getElementById('healthcheck-settings-root');

        if (!root) {
            return;
        }

        root.addEventListener('click', function (event) {
            var addButton = event.target.closest('[data-add-row]');
            var removeButton = event.target.closest('.hc-remove-row');
            var copyButton = event.target.closest('.hc-copy-btn');

            if (addButton) {
                event.preventDefault();
                addRow(addButton.getAttribute('data-add-row'));
                return;
            }

            if (removeButton) {
                event.preventDefault();
                var row = removeButton.closest('.hc-repeat-row');
                if (row) {
                    row.remove();
                }
                return;
            }

            if (copyButton) {
                event.preventDefault();
                var block = copyButton.closest('.hc-copy-block');
                var target = block ? block.querySelector('.hc-copy-target') : null;

                if (target) {
                    var text = target.value || target.textContent;
                    navigator.clipboard.writeText(text).then(function () {
                        var original = copyButton.textContent;
                        copyButton.textContent = 'Copied!';
                        setTimeout(function () { copyButton.textContent = original; }, 1500);
                    });
                }
            }
        });

        var list = document.getElementById('healthcheck-checks-list');
        if (list && !list.querySelector('.hc-check-row')) {
            addRow('check');
        }

        var form = document.getElementById('healthcheck-settings-form');
        if (!form) {
            return;
        }

        form.addEventListener('submit', function (event) {
            event.preventDefault();

            var submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Saving…';
            }

            fetch(form.action, {
                method: 'POST',
                credentials: 'same-origin',
                body: new FormData(form)
            })
                .then(function (response) {
                    return response.text().then(function (text) {
                        return unwrapResponse(text) || {ok: false, error: 'Unexpected response from server.'};
                    });
                })
                .then(function (data) {
                    if (data.ok) {
                        window.location.reload();
                    }
                    else {
                        showPageStatus(root, data.error || data.message || 'Save failed.', true);
                    }
                })
                .catch(function (error) {
                    showPageStatus(root, 'Save failed: ' + error.message, true);
                })
                .finally(function () {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Save settings';
                    }
                });
        });

        function addRow(type) {
            var template = document.getElementById('healthcheck-' + type + '-template');
            var target = document.getElementById('healthcheck-' + type + 's-list');

            if (!template || !target) {
                return;
            }

            var html = template.innerHTML.replace(/__ROW_ID__/g, generateId(type));
            var wrapper = document.createElement('div');
            wrapper.innerHTML = html.trim();

            if (wrapper.firstElementChild) {
                target.appendChild(wrapper.firstElementChild);
            }
        }
    }

    function initRunButtons() {
        var root = document.getElementById('healthcheck-heartbeat-root');

        if (!root) {
            return;
        }

        root.addEventListener('click', function (event) {
            var button = event.target.closest('.hc-run-button');
            if (!button) {
                return;
            }

            event.preventDefault();

            var runUrl = root.getAttribute('data-run-url');
            if (!runUrl) {
                showPageStatus(root, 'Run URL is missing.', true);
                return;
            }

            var originalText = button.textContent;
            button.disabled = true;
            button.textContent = 'Running…';

            var formData = new FormData();
            formData.append('force', button.getAttribute('data-force') || '1');

            var csrfToken = root.getAttribute('data-run-csrf-token');
            var csrfName = root.getAttribute('data-run-csrf-name') || '_csrf_token';
            if (csrfToken) {
                formData.append(csrfName, csrfToken);
            }

            var checkId = button.getAttribute('data-checkid') || '';
            if (checkId !== '') {
                formData.append('checkid', checkId);
            }

            fetch(runUrl, {
                method: 'POST',
                credentials: 'same-origin',
                body: formData
            })
                .then(function (response) {
                    return response.text().then(function (text) {
                        return unwrapResponse(text) || {ok: false, message: 'Unexpected response from server.'};
                    });
                })
                .then(function (data) {
                    showPageStatus(root, data.message || (data.ok ? 'Run completed.' : 'Run failed.'), !data.ok);

                    if (data.ok) {
                        window.setTimeout(function () {
                            window.location.reload();
                        }, 800);
                    }
                })
                .catch(function (error) {
                    showPageStatus(root, 'Run failed: ' + error.message, true);
                })
                .finally(function () {
                    button.disabled = false;
                    button.textContent = originalText;
                });
        });
    }

    function initSchedulerCommands() {
        var section = document.getElementById('healthcheck-scheduler-section');

        if (!section) {
            return;
        }

        var select = document.getElementById('hc-runner-user-select');
        var cronEl = document.getElementById('hc-cron-commands');
        var systemdEl = document.getElementById('hc-systemd-commands');
        var testEl = document.getElementById('hc-test-command');

        if (!select || !cronEl || !systemdEl || !testEl) {
            return;
        }

        var runnerPath = section.getAttribute('data-runner-path');
        var cronSchedule = section.getAttribute('data-cron-schedule');

        function buildCommands(user) {
            var cronLine = cronSchedule + ' /usr/bin/php ' + runnerPath + ' --json >/var/log/zabbix/healthcheck-runner.log 2>&1';

            cronEl.value = [
                '# Install or update the cron job for user "' + user + '"',
                '# (safe to re-run \u2014 replaces any previous healthcheck-runner entry)',
                'sudo crontab -u ' + user + ' -l 2>/dev/null | grep -v healthcheck-runner | { cat; echo \'' + cronLine + '\'; } | sudo crontab -u ' + user + ' -',
                '',
                '# Verify',
                'sudo crontab -u ' + user + ' -l'
            ].join('\n');

            systemdEl.value = [
                '# Create / update the service unit',
                'cat << \'EOF\' | sudo tee /etc/systemd/system/healthcheck-runner.service > /dev/null',
                '[Unit]',
                'Description=Zabbix Healthcheck module runner',
                'Wants=network-online.target',
                'After=network-online.target',
                '',
                '[Service]',
                'Type=oneshot',
                'User=' + user,
                'Group=' + user,
                'ExecStart=/usr/bin/php ' + runnerPath + ' --json',
                'NoNewPrivileges=true',
                'PrivateTmp=true',
                'ProtectHome=true',
                'ProtectSystem=full',
                'EOF',
                '',
                '# Create / update the timer unit',
                'cat << \'EOF\' | sudo tee /etc/systemd/system/healthcheck-runner.timer > /dev/null',
                '[Unit]',
                'Description=Run the Zabbix Healthcheck module runner every minute',
                '',
                '[Timer]',
                'OnBootSec=1min',
                'OnUnitActiveSec=1min',
                'Unit=healthcheck-runner.service',
                'Persistent=true',
                '',
                '[Install]',
                'WantedBy=timers.target',
                'EOF',
                '',
                '# Reload, enable and (re)start the timer',
                'sudo systemctl daemon-reload',
                'sudo systemctl enable --now healthcheck-runner.timer',
                'sudo systemctl restart healthcheck-runner.timer',
                '',
                '# Verify',
                'systemctl list-timers healthcheck-runner.timer'
            ].join('\n');

            testEl.value = 'sudo -u ' + user + ' /usr/bin/php ' + runnerPath + ' --json';
        }

        select.addEventListener('change', function () {
            buildCommands(select.value);
        });

        // Set initial values.
        buildCommands(select.value);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            initSettingsPage();
            initRunButtons();
            initSchedulerCommands();
        });
    }
    else {
        initSettingsPage();
        initRunButtons();
        initSchedulerCommands();
    }
}());
