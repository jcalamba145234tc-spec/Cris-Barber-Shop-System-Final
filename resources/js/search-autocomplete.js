// Shared, progressively enhanced list search. Server-rendered GET forms still
// work if suggestions fail or JavaScript is unavailable. Never render names as HTML.
document.querySelectorAll('[data-search-autocomplete]').forEach((root) => {
    const input = root.querySelector('input[name="q"]');
    const form = input?.form;
    const panel = root.querySelector('[data-suggestion-panel]');
    const list = root.querySelector('[role="listbox"]');
    const feedback = root.querySelector('[data-suggestion-feedback]');
    if (!input || !form || !panel || !list || !feedback) return;

    let timer, controller, generation = 0, activeIndex = -1, options = [];
    const invalidate = () => {
        clearTimeout(timer);
        controller?.abort();
        generation++;
    };
    const close = () => {
        invalidate();
        panel.hidden = true;
        input.setAttribute('aria-expanded', 'false');
        input.removeAttribute('aria-activedescendant');
        list.removeAttribute('aria-busy');
        activeIndex = -1;
    };
    const highlight = (element, text, term) => {
        const source = text.toLocaleLowerCase();
        const needle = term.toLocaleLowerCase();
        let position = 0, found;
        while (needle && (found = source.indexOf(needle, position)) !== -1) {
            element.append(document.createTextNode(text.slice(position, found)));
            const match = document.createElement('strong');
            match.textContent = text.slice(found, found + term.length);
            element.append(match);
            position = found + term.length;
        }
        element.append(document.createTextNode(text.slice(position)));
    };
    const activate = (index, scroll = false) => {
        activeIndex = index;
        [...list.children].forEach((option, i) => option.setAttribute('aria-selected', String(i === index)));
        const selected = list.children[index];
        if (selected) {
            input.setAttribute('aria-activedescendant', selected.id);
            if (scroll) selected.scrollIntoView({ block: 'nearest' });
        } else input.removeAttribute('aria-activedescendant');
    };
    const choose = (index) => {
        if (!options[index]) return;
        input.value = options[index].value;
        close();
        form.requestSubmit();
    };
    const render = (suggestions, message) => {
        const term = input.value.trim();
        options = [{ label: `Search for “${term}”`, value: term, isQuery: true }, ...suggestions];
        list.replaceChildren();
        options.forEach((item, index) => {
            const option = document.createElement('button');
            option.type = 'button';
            option.tabIndex = -1;
            option.className = `search-suggestion${item.isQuery ? ' search-suggestion-query' : ''}`;
            option.id = `${list.id}-option-${index}`;
            option.setAttribute('role', 'option');
            option.setAttribute('aria-selected', 'false');
            const label = document.createElement('span');
            label.className = 'search-suggestion-label';
            if (item.isQuery) label.textContent = item.label;
            else highlight(label, item.label, term);
            option.append(label);
            if (item.detail) {
                const detail = document.createElement('span');
                detail.className = 'search-suggestion-detail';
                highlight(detail, item.detail, term);
                option.append(detail);
            }
            option.addEventListener('mousedown', event => event.preventDefault());
            option.addEventListener('mouseenter', () => activate(index));
            option.addEventListener('click', () => choose(index));
            list.append(option);
        });
        feedback.textContent = message;
        panel.hidden = false;
        input.setAttribute('aria-expanded', 'true');
        activate(activeIndex < options.length ? activeIndex : -1);
    };
    const schedule = () => {
        invalidate();
        activeIndex = -1;
        const term = input.value.trim();
        if (!term) { close(); return; }
        const current = generation;
        render([], 'Searching matching records…');
        list.setAttribute('aria-busy', 'true');
        timer = setTimeout(async () => {
            controller = new AbortController();
            const url = new URL(root.dataset.suggestionUrl, location.href);
            url.search = new URLSearchParams(new FormData(form)).toString();
            try {
                const response = await fetch(url, {
                    signal: controller.signal,
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) throw new Error('Suggestions unavailable');
                const data = await response.json();
                if (current !== generation) return;
                const suggestions = Array.isArray(data.suggestions) ? data.suggestions
                    .filter(item => typeof item.label === 'string' && typeof item.value === 'string').slice(0, 8) : [];
                render(suggestions, suggestions.length
                    ? `${suggestions.length} suggestion${suggestions.length === 1 ? '' : 's'} · Use ↑ ↓ and Enter to select.`
                    : 'No matching records. Press Enter to search this text.');
            } catch (error) {
                if (error.name !== 'AbortError' && current === generation) {
                    render([], 'Suggestions unavailable. Press Enter or use the Search / Filter button.');
                }
            } finally {
                if (current === generation) list.removeAttribute('aria-busy');
            }
        }, 250);
    };
    input.addEventListener('input', event => {
        if (event.isComposing) { close(); return; }
        schedule();
    });
    input.addEventListener('compositionend', schedule);
    input.addEventListener('focus', schedule);
    input.addEventListener('keydown', event => {
        if (event.isComposing) return;
        if (event.key === 'Escape' && !panel.hidden) {
            event.preventDefault(); event.stopPropagation(); close();
        } else if (['ArrowDown', 'ArrowUp'].includes(event.key) && input.value.trim()) {
            event.preventDefault();
            if (panel.hidden) schedule();
            const index = event.key === 'ArrowDown'
                ? (activeIndex + 1) % options.length
                : activeIndex <= 0 ? options.length - 1 : activeIndex - 1;
            activate(index, true);
        } else if (event.key === 'Enter' && !panel.hidden && activeIndex >= 0) {
            event.preventDefault(); choose(activeIndex);
        } else if (event.key === 'Tab') close();
    });
    document.addEventListener('pointerdown', event => { if (!root.contains(event.target)) close(); });
    root.addEventListener('focusout', event => { if (!root.contains(event.relatedTarget)) close(); });
    form.addEventListener('change', event => { if (event.target !== input) close(); });
    form.addEventListener('submit', close);
    form.addEventListener('reset', close);
    window.addEventListener('pagehide', close);
});
