(function () {
    'use strict';
    document.querySelectorAll('[data-sag-search-input]').forEach(function (input) {
        const root = input.closest('[data-sag-navbar-search]');
        const form = input.form;
        const dropdown = root.querySelector('[data-sag-search-results]');
        let timer, controller, sequence = 0, composing = false;
        const length = value => Array.from(value).length;
        function cancel() {
            clearTimeout(timer);
            if (controller) controller.abort();
            sequence++;
        }
        function close() {
            cancel();
            dropdown.hidden = true;
            dropdown.replaceChildren();
            input.setAttribute('aria-expanded', 'false');
        }
        function text(tag, value, className) {
            const element = document.createElement(tag);
            element.textContent = value;
            if (className) element.className = className;
            return element;
        }
        function safeUrl(value) {
            if (typeof value !== 'string' || /[\x00-\x20\x7f\\]/.test(value)) return null;
            if (!/^\/(?!\/)/.test(value) && !/^https?:\/\//i.test(value)) return null;
            try {
                const url = new URL(value, window.location.origin);
                return ['http:', 'https:'].includes(url.protocol) ? url.href : null;
            } catch (_) { return null; }
        }
        function show(message, keyword) {
            dropdown.replaceChildren();
            if (message) dropdown.append(text('p', message, 'p-2 mb-0'));
            const link = text('a', 'View search results', 'd-block p-2 border-top');
            const url = new URL(form.action, window.location.href);
            url.searchParams.set('q', keyword);
            link.href = url.href;
            dropdown.append(link);
            dropdown.hidden = false;
            input.setAttribute('aria-expanded', 'true');
        }
        function changed() {
            close();
            const keyword = input.value.trim();
            if (composing || length(keyword) < Number(input.dataset.minLength) || length(keyword) > Number(input.dataset.maxLength)) return;
            const current = sequence;
            timer = setTimeout(async function () {
                controller = new AbortController();
                show('Searching…', keyword);
                try {
                    const url = new URL(input.dataset.url, window.location.href);
                    url.searchParams.set('q', keyword);
                    const response = await fetch(url.href, {headers: {'Accept': 'application/json'}, credentials: 'same-origin', signal: controller.signal});
                    if (!response.ok) throw new Error('Search failed');
                    const data = await response.json();
                    if (current !== sequence || input.value.trim() !== keyword) return;
                    show(data.results.length ? '' : 'No results found.', keyword);
                    data.results.forEach(function (result) {
                        const url = safeUrl(result.url);
                        if (!url) return;
                        const link = text('a', '', 'd-block p-2 border-bottom');
                        link.style.overflowWrap = 'anywhere';
                        link.href = url;
                        link.append(text('span', result.title));
                        link.append(text('span', result.provider_label, 'badge badge-secondary ml-2'));
                        if (result.description) link.append(text('small', result.description, 'd-block text-muted'));
                        dropdown.insertBefore(link, dropdown.lastChild);
                    });
                } catch (error) {
                    if (error.name !== 'AbortError' && current === sequence) show('Unable to load suggestions. You can still submit your search.', keyword);
                }
            }, Math.max(0, Number(input.dataset.debounce) || 0));
        }
        input.addEventListener('input', changed);
        input.addEventListener('compositionstart', function () { composing = true; close(); });
        input.addEventListener('compositionend', function () { composing = false; changed(); });
        root.querySelector('button[data-widget="navbar-search"]').addEventListener('click', close);
        document.addEventListener('click', function (event) { if (!root.contains(event.target)) close(); });
        root.addEventListener('keydown', function (event) { if (event.key === 'Escape') close(); });
        form.addEventListener('submit', close);
    });
})();
