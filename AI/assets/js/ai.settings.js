(function () {
    'use strict';

    function init() {
        var root = document.getElementById('ai-settings-root');

        if (!root) {
            return;
        }

        var templates = {
            provider: document.getElementById('ai-provider-template'),
            instruction: document.getElementById('ai-instruction-template'),
            reference_link: document.getElementById('ai-reference-link-template')
        };

        var lists = {
            provider: document.getElementById('ai-providers-list'),
            instruction: document.getElementById('ai-instructions-list'),
            reference_link: document.getElementById('ai-reference-links-list')
        };

        root.addEventListener('click', function (event) {
            var addButton = event.target.closest('[data-add-row]');
            var removeButton = event.target.closest('.ai-remove-row');

            if (addButton) {
                event.preventDefault();
                addRow(addButton.getAttribute('data-add-row'));
                return;
            }

            if (removeButton) {
                event.preventDefault();
                var row = removeButton.closest('.ai-repeat-row');

                if (row) {
                    row.remove();
                }
            }
        });

        if (lists.provider && !lists.provider.querySelector('.ai-provider-row')) {
            addRow('provider');
        }
    }

    function addRow(type) {
        var template = document.getElementById('ai-' + type.replace('_', '-') + '-template')
            || document.getElementById('ai-' + type + '-template');
        var list = document.getElementById('ai-' + type.replace('_', '-') + 's-list')
            || document.getElementById('ai-' + type + 's-list');

        if (!template || !list) {
            return;
        }

        var html = template.innerHTML.replace(/__ROW_ID__/g, generateId(type));
        var wrapper = document.createElement('div');
        wrapper.innerHTML = html.trim();

        if (wrapper.firstElementChild) {
            list.appendChild(wrapper.firstElementChild);
        }
    }

    function generateId(prefix) {
        return prefix + '_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 8);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    }
    else {
        init();
    }
}());

