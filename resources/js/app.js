import Alpine from 'alpinejs';
import './search-autocomplete';

window.Alpine = Alpine;
Alpine.data('barberEmail', (endpoint, first = '', last = '') => ({
    first: first ?? '', last: last ?? '', email: '', requestId: 0,
    hint: 'Numbers are appended when a name is already taken. Final address is assigned on save.',
    async preview() {
        const id = ++this.requestId;
        if (!String(this.first ?? '').trim() || !String(this.last ?? '').trim()) { this.email = ''; return; }
        this.hint = 'Checking available login…';
        try {
            const url = new URL(endpoint);
            url.searchParams.set('first_name', this.first);
            url.searchParams.set('last_name', this.last);
            const response = await fetch(url, { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error('Preview unavailable');
            const data = await response.json();
            if (id !== this.requestId) return;
            this.email = data.email;
            this.hint = 'Read-only preview. The final unique address is assigned when the account is saved.';
        } catch {
            if (id !== this.requestId) return;
            this.email = '';
            this.hint = 'Preview unavailable. The server will still generate a unique address when you save.';
        }
    },
}));
Alpine.start();

const shopNavigation = document.getElementById('shop-navigation');
const navigationOpenButton = document.querySelector('[data-nav-open]');
const navigationCloseButtons = document.querySelectorAll('[data-nav-close]');
const sidebarCollapseButton = document.querySelector('[data-sidebar-collapse]');
const sidebarRestoreButton = document.querySelector('[data-sidebar-restore]');

const setNavigationOpen = (isOpen) => {
    if (!shopNavigation || !navigationOpenButton) {
        return;
    }

    shopNavigation.classList.toggle('is-open', isOpen);
    shopNavigation.inert = !isOpen && window.innerWidth <= 760;
    document.querySelector('main')?.toggleAttribute('inert', isOpen);
    if (isOpen) shopNavigation.querySelector('a')?.focus();
    else if (window.innerWidth <= 760) navigationOpenButton.focus();
    if (isOpen) {
        shopNavigation.classList.remove('is-collapsed');
        document.body.classList.remove('sidebar-collapsed');
    }
    navigationOpenButton.setAttribute('aria-expanded', String(isOpen));
    document.querySelectorAll('.nav-backdrop').forEach((backdrop) => {
        backdrop.hidden = !isOpen;
    });
    document.body.classList.toggle('navigation-open', isOpen);
};

const setSidebarCollapsed = (isCollapsed) => {
    if (!shopNavigation || !sidebarRestoreButton) {
        return;
    }

    shopNavigation.classList.toggle('is-collapsed', isCollapsed);
    shopNavigation.inert = isCollapsed;
    document.body.classList.toggle('sidebar-collapsed', isCollapsed);
    sidebarRestoreButton.setAttribute('aria-expanded', String(!isCollapsed));
};

if (shopNavigation) shopNavigation.inert = window.innerWidth <= 760;
window.addEventListener('resize', () => {
    if (!shopNavigation) return;
    shopNavigation.inert = window.innerWidth <= 760 ? !shopNavigation.classList.contains('is-open') : shopNavigation.classList.contains('is-collapsed');
    if (window.innerWidth > 760) {
        document.querySelector('main')?.removeAttribute('inert');
        document.body.classList.remove('navigation-open');
        document.querySelectorAll('.nav-backdrop').forEach(el => { el.hidden = true; });
    }
});
shopNavigation?.addEventListener('keydown', (event) => {
    if (event.key !== 'Tab' || !shopNavigation.classList.contains('is-open') || window.innerWidth > 760) return;
    const controls = [...shopNavigation.querySelectorAll('a,button')].filter(el => el.getClientRects().length && !el.disabled);
    const first = controls[0], last = controls.at(-1);
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
    if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
});
navigationOpenButton?.addEventListener('click', () => setNavigationOpen(true));
sidebarCollapseButton?.addEventListener('click', () => setSidebarCollapsed(true));
sidebarRestoreButton?.addEventListener('click', () => setSidebarCollapsed(false));
navigationCloseButtons.forEach((button) => {
    button.addEventListener('click', () => setNavigationOpen(false));
});
shopNavigation?.querySelectorAll('.nav-links a').forEach((link) => {
    link.addEventListener('click', () => setNavigationOpen(false));
});
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
        setNavigationOpen(false);
    }
});

document.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
    toggle.addEventListener('click', () => {
        const input = document.getElementById(toggle.dataset.passwordToggle);

        if (!input) {
            return;
        }

        const isVisible = input.type === 'password';
        input.type = isVisible ? 'text' : 'password';
        toggle.setAttribute('aria-pressed', String(isVisible));
        toggle.setAttribute(
            'aria-label',
            `${isVisible ? 'Hide' : 'Show'} ${input.id === 'password_confirmation' ? 'confirm password' : 'password'}`,
        );
        input.focus();
    });
});
// Cash tender/change and GCash reference are mutually exclusive payment controls.
document.querySelectorAll('[data-pos-form]').forEach((form) => {
    const payment = form.querySelector('[data-payment-method]');
    const wrapper = form.querySelector('[data-gcash-field]');
    const reference = wrapper.querySelector('input');
    const cashWrapper = form.querySelector('[data-cash-field]');
    const cash = form.querySelector('[data-cash-received]');
    const changeCard = form.querySelector('[data-cash-change-card]');
    const change = form.querySelector('[data-cash-change]');
    const cashError = form.querySelector('[data-cash-error]');
    const services = [...form.querySelectorAll('[data-pos-service]')];
    const serviceQuantities = [...form.querySelectorAll('[data-pos-service-quantity]')];
    const serviceList = form.querySelector('.service-choice-list');
    const servicePickerTrigger = form.querySelector('[data-service-picker-trigger]');
    const servicePickerActions = form.querySelector('[data-service-picker-actions]');
    const servicePickerDone = form.querySelector('[data-service-picker-done]');
    const serviceSummary = form.querySelector('[data-service-selection-summary]');
    const total = form.querySelector('[data-pos-total]');
    const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const toCents = (value) => {
        if (!/^\d{1,10}(?:\.\d{1,2})?$/.test(value)) return null;
        const [whole, fraction = ''] = value.split('.');
        return Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
    };
    const renderServiceSummary = () => {
        const items = services
            .filter((service) => service.checked)
            .map((service) => {
                const item = document.createElement('div');
                item.className = 'service-selection-item';
                const description = document.createElement('span');
                description.className = 'service-selection-description';
                description.textContent = `${service.nextElementSibling.textContent} - ${money.format((toCents(service.dataset.price || '0') ?? 0) / 100)}`;
                const sourceQuantity = service.closest('.service-choice').querySelector('[data-pos-service-quantity]');
                const quantityControl = document.createElement('div');
                quantityControl.className = 'service-selection-quantity-control';
                const count = document.createElement('input');
                count.type = 'number';
                count.min = '1';
                count.max = '100';
                count.step = '1';
                count.inputMode = 'numeric';
                count.className = 'service-selection-quantity';
                count.setAttribute('aria-label', `Quantity for ${service.nextElementSibling.textContent}`);
                count.dataset.serviceId = service.value;
                count.name = `service_quantities[${service.value}]`;
                count.required = true;
                count.disabled = true;
                count.value = sourceQuantity.value;
                count.addEventListener('input', () => {
                    sourceQuantity.value = count.value;
                    updateTotal();
                });
                const arrows = document.createElement('span');
                arrows.className = 'service-selection-quantity-arrows';
                [['Decrease quantity', -1, '−'], ['Increase quantity', 1, '+']].forEach(([label, step, arrow]) => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.textContent = arrow;
                    button.setAttribute('aria-label', `${label} for ${service.nextElementSibling.textContent}`);
                    button.addEventListener('click', () => {
                        const current = Number(count.value);
                        count.value = String(Math.max(1, Math.min(100, (Number.isInteger(current) ? current : 1) + step)));
                        count.dispatchEvent(new Event('input', { bubbles: true }));
                    });
                    arrows.append(button);
                });
                quantityControl.append(count, arrows);
                item.append(description, quantityControl);
                return item;
            });
        serviceSummary.replaceChildren(...items);
    };
    const updateTotal = () => {
        const priceCents = services.reduce((sum, service) => {
            if (!service.checked) return sum;
            const quantity = Number(service.closest('.service-choice').querySelector('[data-pos-service-quantity]').value);
            return sum + (toCents(service.dataset.price || '0') ?? 0) * (Number.isInteger(quantity) && quantity > 0 ? quantity : 0);
        }, 0);
        const totalCents = priceCents;
        if (services.length) {
            const hasSelection = services.some((service) => service.checked);
            const pickerOpen = servicePickerTrigger.getAttribute('aria-expanded') === 'true';
            services[0].required = !hasSelection && pickerOpen;
            services[0].setCustomValidity(hasSelection || !pickerOpen ? '' : 'Choose at least one service.');
        }
        total.textContent = money.format(totalCents / 100);
        cash.setCustomValidity('');
        cashError.hidden = true;
        cashError.textContent = '';
        if (payment.value !== 'cash') return;
        const received = toCents(cash.value);
        change.textContent = money.format(Math.max(0, (received ?? 0) - totalCents) / 100);
        if (cash.value !== '' && received === null) {
            cash.setCustomValidity('Enter a valid cash amount with at most two decimal places.');
        } else if (received !== null && received < totalCents) {
            cash.setCustomValidity('Customer cash must cover the transaction total.');
            cashError.textContent = `Insufficient cash. Still needed: ${money.format((totalCents - received) / 100)}.`;
            cashError.hidden = false;
        }
    };
    const updatePayment = () => {
        const gcash = payment.value === 'gcash';
        wrapper.hidden = !gcash;
        reference.disabled = !gcash;
        reference.required = gcash;
        cashWrapper.hidden = gcash;
        changeCard.hidden = gcash;
        cash.disabled = gcash;
        cash.required = !gcash;
        updateTotal();
    };
    const setServicePickerExpanded = (expanded) => {
        const hasSelection = services.some((service) => service.checked);
        if (!expanded) renderServiceSummary();
        serviceList.hidden = !expanded;
        servicePickerActions.hidden = !expanded;
        serviceSummary.hidden = expanded || !hasSelection;
        services.forEach((service) => {
            const sourceQuantity = service.closest('.service-choice').querySelector('[data-pos-service-quantity]');
            const summaryQuantity = serviceSummary.querySelector(`[data-service-id="${service.value}"]`);
            if (summaryQuantity) summaryQuantity.disabled = expanded;
            sourceQuantity.disabled = !expanded || !service.checked;
        });
        servicePickerTrigger.setAttribute('aria-expanded', String(expanded));
        servicePickerTrigger.textContent = 'Select Services';
    };
    setServicePickerExpanded(false);
    servicePickerTrigger.addEventListener('click', () => {
        setServicePickerExpanded(servicePickerTrigger.getAttribute('aria-expanded') !== 'true');
    });
    servicePickerDone.addEventListener('click', () => {
        if (!services.some((service) => service.checked)) {
            setServicePickerExpanded(true);
            services[0]?.reportValidity();
            return;
        }
        setServicePickerExpanded(false);
    });
    form.addEventListener('submit', (event) => {
        if (services.some((service) => service.checked)) return;
        event.preventDefault();
        setServicePickerExpanded(true);
        services[0]?.reportValidity();
    });
    payment.addEventListener('change', updatePayment);
    services.forEach((service) => service.addEventListener('change', () => {
        const quantity = service.closest('.service-choice').querySelector('[data-pos-service-quantity]');
        quantity.disabled = !service.checked;
        if (service.checked && quantity.value === '') quantity.value = '1';
        updateTotal();
        if (servicePickerTrigger.getAttribute('aria-expanded') === 'false' && services.some((item) => item.checked)) renderServiceSummary();
    }));
    serviceQuantities.forEach((quantity) => quantity.addEventListener('input', () => {
        updateTotal();
        if (servicePickerTrigger.getAttribute('aria-expanded') === 'false') renderServiceSummary();
    }));
    cash.addEventListener('input', updateTotal);
    updatePayment();
});

// Native dialog provides keyboard focus containment, Escape, and focus restoration.
const confirmation = document.getElementById('action-confirm');
let pendingAction = null;
document.addEventListener('submit', (event) => {
    const form = event.target;
    if (!form.matches('form')) return;
    const confirmationMessage = event.submitter?.dataset.confirm || form.dataset.confirm;
    if (confirmationMessage && form.dataset.confirmedOnce !== 'true') {
        event.preventDefault();
        pendingAction = { form, button: event.submitter };
        confirmation.querySelector('#confirm-message').textContent = confirmationMessage;
        confirmation.showModal();
        confirmation.querySelector('[data-confirm-cancel]').focus();
        return;
    }
    delete form.dataset.confirmedOnce;
    if (event.defaultPrevented || form.method.toLowerCase() !== 'post') return;
    if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
    form.dataset.submitting = 'true';
    form.setAttribute('aria-busy', 'true');
    const button = event.submitter;
    // Preserve the clicked action before disabling controls (clock in/out, queue status, GCash verification).
    if (button?.name) {
        const action = document.createElement('input');
        action.type = 'hidden'; action.name = button.name; action.value = button.value;
        form.append(action);
    }
    form.querySelectorAll('button:not([type="button"])').forEach((control) => { control.disabled = true; });
    if (button) { button.setAttribute('aria-busy', 'true'); button.textContent = 'Saving…'; }
});
confirmation?.querySelector('[data-confirm-cancel]').addEventListener('click', () => { pendingAction = null; confirmation.close(); });
confirmation?.addEventListener('cancel', () => { pendingAction = null; });
confirmation?.querySelector('[data-confirm-accept]').addEventListener('click', () => {
    const action = pendingAction;
    pendingAction = null;
    confirmation.close();
    if (!action) return;
    action.form.dataset.confirmedOnce = 'true';
    action.form.requestSubmit(action.button);
});

// Keep small-screen lists primary; filters remain keyboard-accessible native disclosures.
document.querySelectorAll('.filter-disclosure').forEach(details => { details.open = window.innerWidth > 760; });

// Enhance tables without hiding any columns on small screens.
const enhanceTable = (table) => {
    table.querySelectorAll('th').forEach((cell) => { cell.scope = 'col'; });
    [...table.querySelectorAll('thead th')].forEach((heading, index) => {
        if (!/^(amount|total|price|barber share|barber earnings|your earnings|unclaimed|claimed|transactions|completed services|eligible transactions|threshold|hours)$/i.test(heading.textContent.trim())) return;
        heading.classList.add('numeric');
        table.querySelectorAll('tbody tr').forEach((row) => {
            if (row.children.length > index && !row.children[0].hasAttribute('colspan')) row.children[index].classList.add('numeric');
        });
    });
    if (table.parentElement.classList.contains('table-scroll')) return;
    const wrapper = document.createElement('div');
    wrapper.className = 'table-scroll'; wrapper.tabIndex = 0;
    wrapper.setAttribute('role', 'region'); wrapper.setAttribute('aria-label', 'Data table; scroll horizontally for more columns');
    table.before(wrapper); wrapper.append(table);
};
document.querySelectorAll('table').forEach(enhanceTable);
// Place field-level validation by all ordinary form fields, including older forms.
const fieldErrors = JSON.parse(document.getElementById('field-errors')?.textContent || '{}');
Object.entries(fieldErrors).forEach(([name, messages]) => {
    document.querySelectorAll('[name]').forEach((field) => {
        if (field.name !== name || field.type === 'hidden') return;
        field.setAttribute('aria-invalid', 'true');
        if (!field.nextElementSibling?.classList.contains('error')) {
            const error = document.createElement('p'); error.className = 'error'; error.textContent = messages[0];
            error.id = `${field.id || name.replaceAll('.', '-')}-error`;
            field.after(error); field.setAttribute('aria-describedby', [field.getAttribute('aria-describedby'), error.id].filter(Boolean).join(' '));
        }
    });
});
// Debounced search updates only the result region, retaining input focus and filter state.
const liveSearch = document.querySelector('[data-live-search]');
if (liveSearch) {
    let timer, controller, generation = 0;
    liveSearch.addEventListener('submit', () => {
        clearTimeout(timer);
        controller?.abort();
        generation++;
    });
    const search = liveSearch.querySelector('[name="q"]');
    search.addEventListener('input', () => {
        clearTimeout(timer);
        controller?.abort();
        const currentGeneration = ++generation;
        timer = setTimeout(async () => {
            controller = new AbortController();
            const url = new URL(liveSearch.action || location.href);
            url.search = new URLSearchParams(new FormData(liveSearch)).toString();
            const status = document.getElementById('inventory-search-status');
            const results = document.getElementById('inventory-results');
            status.textContent = 'Searching products…'; results.setAttribute('aria-busy', 'true');
            try {
                const response = await fetch(url, { signal: controller.signal, headers: { 'X-Requested-With': 'XMLHttpRequest' } });
                if (!response.ok) throw new Error('Search failed');
                const page = new DOMParser().parseFromString(await response.text(), 'text/html');
                if (currentGeneration !== generation) return;
                const content = page.getElementById('inventory-results');
                if (!content) throw new Error('Session expired');
                const table = content.querySelector('table');
                if (table) {
                    enhanceTable(table);
                }
                results.replaceChildren(...content.childNodes);
                status.textContent = page.getElementById('inventory-search-status').textContent;
                document.getElementById('inventory-summary').replaceChildren(...page.getElementById('inventory-summary').childNodes);
                history.replaceState(null, '', url);
            } catch (error) {
                if (error.name !== 'AbortError' && currentGeneration === generation) status.textContent = 'Live search unavailable. Press Filter to search normally.';
            } finally { if (currentGeneration === generation) results.removeAttribute('aria-busy'); }
        }, 350);
    });
}

// Native step validation plus an explicit message. Backend rules remain authoritative.
document.querySelectorAll('input[type="number"][step="1"]').forEach((input) => {
    const validateQuantity = () => {
        input.setCustomValidity(input.value !== '' && !/^\d+$/.test(input.value)
            ? 'Enter a whole-number quantity. Decimals and negative quantities are not allowed.' : '');
    };
    input.addEventListener('input', validateQuantity);
    validateQuantity();
});

// Autosave keeps the existing native POST/PATCH request and its server confirmation.
document.querySelectorAll('[data-autosave]').forEach((form) => {
    form.querySelector('[data-autosave-control]')?.addEventListener('change', () => {
        if (form.dataset.submitting === 'true') return;
        const feedback = form.querySelector('[data-save-feedback]');
        if (feedback) feedback.textContent = 'Saving…';
        form.requestSubmit();
    });
});
