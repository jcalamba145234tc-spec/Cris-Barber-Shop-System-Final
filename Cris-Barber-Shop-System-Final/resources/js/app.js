import Alpine from 'alpinejs';

window.Alpine = Alpine;
Alpine.start();

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
const dashboardTabs = [...document.querySelectorAll('[data-dashboard-tab]')];

const activateDashboardTab = (activeTab, shouldFocus = false) => {
    dashboardTabs.forEach((tab) => {
        const isActive = tab === activeTab;
        const panel = document.getElementById(tab.getAttribute('aria-controls'));

        tab.setAttribute('aria-selected', String(isActive));
        tab.tabIndex = isActive ? 0 : -1;
        tab.classList.toggle('is-active', isActive);

        if (panel) {
            panel.hidden = !isActive;
        }
    });

    if (shouldFocus) {
        activeTab.focus();
    }
};

dashboardTabs.forEach((tab) => {
    tab.addEventListener('click', () => activateDashboardTab(tab));
    tab.addEventListener('keydown', (event) => {
        const currentIndex = dashboardTabs.indexOf(tab);
        let nextIndex = currentIndex;

        if (event.key === 'ArrowRight') {
            nextIndex = (currentIndex + 1) % dashboardTabs.length;
        } else if (event.key === 'ArrowLeft') {
            nextIndex = (currentIndex - 1 + dashboardTabs.length) % dashboardTabs.length;
        } else if (event.key === 'Home') {
            nextIndex = 0;
        } else if (event.key === 'End') {
            nextIndex = dashboardTabs.length - 1;
        } else {
            return;
        }

        event.preventDefault();
        activateDashboardTab(dashboardTabs[nextIndex], true);
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
                [['Increase quantity', 1, '▲'], ['Decrease quantity', -1, '▼']].forEach(([label, step, arrow]) => {
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

document.querySelectorAll('form[data-confirm]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (!window.confirm(form.dataset.confirm)) event.preventDefault();
    });
});

// Prevent accidental repeated submissions; server completion is also idempotent.
document.querySelectorAll('form[method="POST"]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        if (event.defaultPrevented) return;
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }
        form.dataset.submitting = 'true';
        const button = event.submitter;
        if (button) {
            button.setAttribute('aria-busy', 'true');
            button.textContent = 'Saving…';
        }
    });
});

// Native step validation plus an explicit message. Backend rules remain authoritative.
document.querySelectorAll('input[type="number"][step="1"]').forEach((input) => {
    const validateQuantity = () => {
        input.setCustomValidity(input.value !== '' && !/^\d+$/.test(input.value)
            ? 'Enter a whole-number quantity. Decimals and negative quantities are not allowed.' : '');
    };
    input.addEventListener('input', validateQuantity);
    validateQuantity();
});
