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
    const service = form.querySelector('[data-pos-service]');
    const quantity = form.querySelector('[data-pos-quantity]');
    const total = form.querySelector('[data-pos-total]');
    const money = new Intl.NumberFormat('en-PH', { style: 'currency', currency: 'PHP' });
    const toCents = (value) => {
        if (!/^\d{1,10}(?:\.\d{1,2})?$/.test(value)) return null;
        const [whole, fraction = ''] = value.split('.');
        return Number(whole) * 100 + Number(fraction.padEnd(2, '0'));
    };
    const updateTotal = () => {
        const priceCents = toCents(service.selectedOptions[0]?.dataset.price || '0') ?? 0;
        const units = Number(quantity.value);
        const totalCents = priceCents * (Number.isInteger(units) && units > 0 ? units : 0);
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
    payment.addEventListener('change', updatePayment);
    service.addEventListener('change', updateTotal);
    quantity.addEventListener('input', updateTotal);
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
