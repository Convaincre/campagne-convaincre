(function () {
    'use strict';
    function init(form) {
        const groups = Array.from(form.querySelectorAll('fieldset[data-cc-parent]'));
        const pages = Array.from(form.querySelectorAll('[data-cc-page]'));
        if (!groups.length && !pages.length) return;
        const submit = form.querySelector('button[type="submit"]');
        let active = pages[0];
        function refresh() {
            for (const group of groups) {
                const name = 'answers[' + group.dataset.ccParent + ']';
                const selected = Array.from(form.querySelectorAll('input[type="radio"]:checked')).find(
                    (input) => input.name === name && !input.matches(':disabled')
                );
                const visible = !!selected && selected.value === group.dataset.ccValue;
                group.hidden = !visible;
                group.disabled = !visible;
                if (!visible) {
                    for (const input of group.querySelectorAll('input, select, textarea')) {
                        if (input.type === 'radio' || input.type === 'checkbox') input.checked = false;
                        else input.value = '';
                        input.setCustomValidity('');
                    }
                }
            }
        }
        function eligiblePages() {
            return pages.filter(page => Array.from(page.querySelectorAll('[data-cc-question]')).some(
                question => !question.closest('fieldset[data-cc-parent][disabled]')
            ));
        }
        const progressBox = form.querySelector('[data-cc-steps]');
        const navigation = form.querySelector('[data-cc-navigation]');
        const previous = form.querySelector('[data-cc-prev]');
        const next = form.querySelector('[data-cc-next]');
        const label = form.querySelector('[data-cc-step-label]');
        const progress = form.querySelector('[data-cc-progress]');
        function showPage(focus) {
            const available = eligiblePages();
            if (!available.includes(active)) active = available[0];
            const index = available.indexOf(active);
            pages.forEach(page => { page.hidden = page !== active; });
            previous.hidden = index <= 0;
            next.hidden = index >= available.length - 1;
            submit.hidden = index < available.length - 1;
            label.textContent = label.dataset.ccLabel.replace('%1$d', String(index + 1)).replace('%2$d', String(available.length));
            progress.value = available.length ? 100 * (index + 1) / available.length : 0;
            if (focus && active) active.focus();
        }
        function validate(page) {
            for (const group of page.querySelectorAll('[data-cc-required-multiple]')) {
                const inputs = Array.from(group.querySelectorAll('input[type="checkbox"]')).filter(input => !input.matches(':disabled'));
                if (inputs.length) inputs[0].setCustomValidity(inputs.some(input => input.checked) ? '' : group.dataset.ccRequiredMultiple);
            }
            for (const input of page.querySelectorAll('input, select, textarea')) {
                if (input.willValidate && !input.checkValidity()) {
                    active = page;
                    showPage(false);
                    input.reportValidity();
                    return false;
                }
            }
            return true;
        }
        function forward() {
            refresh();
            if (!active || !validate(active)) return;
            const available = eligiblePages();
            const index = available.indexOf(active);
            if (index < available.length - 1) {
                active = available[index + 1];
                showPage(true);
            }
        }
        if (pages.length) {
            // Hidden pages stay enabled so the final POST contains every visible answer.
            // Native whole-form validation would otherwise focus a hidden page.
            form.noValidate = true;
            progressBox.hidden = false;
            navigation.hidden = false;
            previous.addEventListener('click', () => {
                const available = eligiblePages();
                const index = available.indexOf(active);
                if (index > 0) { active = available[index - 1]; showPage(true); }
            });
            next.addEventListener('click', forward);
        }
        function update() {
            refresh();
            if (pages.length) showPage(false);
        }
        form.addEventListener('change', update);
        form.addEventListener('submit', event => {
            refresh();
            if (!pages.length) return;
            const available = eligiblePages();
            if (active !== available[available.length - 1]) {
                event.preventDefault();
                forward();
                return;
            }
            for (const page of available) {
                if (!validate(page)) { event.preventDefault(); return; }
            }
            // Validate any remaining controls outside the pages as well.
            if (!form.checkValidity()) { event.preventDefault(); form.reportValidity(); }
        });
        form.addEventListener('reset', () => window.setTimeout(() => { active = pages[0]; update(); }, 0));
        update();
        if (submit && submit.hasAttribute('data-cc-conditional-submit')) submit.disabled = false;
    }
    function boot() {
        document.querySelectorAll('.convaincre-connect-form').forEach(init);
    }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
    else boot();
})();
