(() => {
    'use strict';
    let counter = Date.now();
    const editor = document.getElementById('ccsa-editor');
    if (editor) {
        const nav = editor.querySelector('.ccsa-wizard-nav');
        const actions = editor.querySelector('.ccsa-wizard-actions');
        const steps = Array.from(editor.querySelectorAll('[data-wizard-step]'));
        const links = Array.from(nav.querySelectorAll('[data-step]'));
        const stepInput = editor.querySelector('#ccsa-wizard-step');
        let active = 0;
        const published = nav.dataset.published === '1';
        const quick = editor.querySelector('.ccsa-quick-publish');
        const progress = nav.querySelector('progress');
        const updateReady = () => {
            const checks = Array.from(editor.querySelectorAll('[data-ready-field]'));
            let complete = 0;
            checks.forEach(check => {
                const field = editor.elements.namedItem(check.dataset.readyField);
                const ready = Boolean(field && field.value.trim());
                check.querySelector('span').textContent = ready ? 'Renseigné' : 'À renseigner';
                check.classList.toggle('ccsa-ready', ready);
                if (ready) complete++;
            });
            editor.querySelector('.ccsa-ready-summary').textContent = complete + ' / ' + checks.length + ' informations requises renseignées';
        };
        const show = (index, focus = false) => {
            active = Math.max(0, Math.min(steps.length - 1, index));
            steps.forEach((step, i) => { step.hidden = i !== active; });
            links.forEach((link, i) => {
                link.classList.toggle('button-primary', i === active);
                if (i === active) link.setAttribute('aria-current', 'step');
                else link.removeAttribute('aria-current');
            });
            stepInput.value = steps[active].dataset.wizardStep;
            nav.querySelector('.ccsa-step-status').textContent = published ? 'Gestion du site — ' + links[active].textContent : 'Étape ' + (active + 1) + ' sur ' + steps.length + ' — ' + links[active].textContent;
            progress.hidden = published;
            if (quick) {
                quick.hidden = steps[active].dataset.wizardStep === 'publish';
                quick.querySelector('.ccsa-quick-review').hidden = !['forms','legal'].includes(steps[active].dataset.wizardStep);
            }
            nav.querySelector('progress').value = active + 1;
            actions.querySelector('.ccsa-prev').disabled = active === 0;
            actions.querySelector('.ccsa-next').hidden = active === steps.length - 1;
            updateReady();
            if (focus) {
                const heading = steps[active].querySelector('h2');
                heading.tabIndex = -1;
                heading.focus();
            }
        };
        links.forEach((link, i) => link.addEventListener('click', () => show(i, true)));
        actions.querySelector('.ccsa-prev').addEventListener('click', () => show(active - 1, true));
        editor.addEventListener('input', updateReady);
        // Render the real private site template, not a separate mock-up.
        const appearanceFrame = document.getElementById('ccsa-appearance-frame');
        const models = ['editorial','classic','minimal','modern','photo','poster','local','magazine'];
        const modelLayouts = {editorial:'split',classic:'split',minimal:'stacked',modern:'split',photo:'stacked',poster:'split',local:'reverse',magazine:'split'};
        const ink = color => {
            const rgb = color.slice(1).match(/.{2}/g).map(c => parseInt(c, 16) / 255);
            const lin = c => c <= .04045 ? c / 12.92 : ((c + .055) / 1.055) ** 2.4;
            return .2126*lin(rgb[0]) + .7152*lin(rgb[1]) + .0722*lin(rgb[2]) > .179 ? '#000000' : '#ffffff';
        };
        const updateAppearance = () => {
            try {
                const doc = appearanceFrame?.contentDocument;
                const shell = doc?.querySelector('.ccs-shell');
                if (!shell) return;
                const model = editor.elements.namedItem('model').value;
                const font = editor.elements.namedItem('font').value;
                const layout = editor.elements.namedItem('layout').value;
                const color = editor.elements.namedItem('color').value;
                const secondary = editor.elements.namedItem('secondary').value;
                if (!models.includes(model)) return;
                shell.classList.remove(...models.map(m => 'ccs-' + m), ...['model','sans','serif','rounded'].map(f => 'ccs-font-' + f), ...['split','reverse','stacked'].map(l => 'ccs-layout-' + l));
                shell.classList.add('ccs-' + model, 'ccs-font-' + font, 'ccs-layout-' + (layout === 'model' ? modelLayouts[model] : layout));
                if (/^#[0-9a-f]{6}$/i.test(color)) {
                    doc.body.style.setProperty('--ccs-accent', color);
                    doc.body.style.setProperty('--ccs-ink', ink(color));
                }
                if (/^#[0-9a-f]{6}$/i.test(secondary)) {
                    doc.body.style.setProperty('--ccs-secondary', secondary);
                    doc.body.style.setProperty('--ccs-secondary-ink', ink(secondary));
                }
            } catch (_) { /* Preview is only available in the same-origin private frame. */ }
        };
        appearanceFrame?.addEventListener('load', updateAppearance);
        editor.addEventListener('input', updateAppearance);
        editor.addEventListener('change', updateAppearance);
        updateAppearance();
        nav.hidden = false;
        actions.hidden = published;
        show(Math.max(0, steps.findIndex(step => step.dataset.wizardStep === nav.dataset.start)));
    }
    document.addEventListener('click', (event) => {
        const button = event.target.closest('button');
        if (!button) return;
        if (button.matches('.ccsa-support-up,.ccsa-support-down')) {
            const item = button.closest('.ccsa-supporter');
            if (!item) return;
            if (button.matches('.ccsa-support-up') && item.previousElementSibling) item.parentNode.insertBefore(item, item.previousElementSibling);
            else if (button.matches('.ccsa-support-down') && item.nextElementSibling) item.parentNode.insertBefore(item.nextElementSibling, item);
            button.focus();
        } else if (button.matches('.ccsa-order-up,.ccsa-order-down')) {
            const item = button.closest('li');
            if (!item) return;
            if (button.matches('.ccsa-order-up') && item.previousElementSibling) {
                item.parentNode.insertBefore(item, item.previousElementSibling);
            } else if (button.matches('.ccsa-order-down') && item.nextElementSibling && item.nextElementSibling.querySelector('.ccsa-order-down')) {
                item.parentNode.insertBefore(item.nextElementSibling, item);
            }
            button.focus();
        } else if (button.matches('.ccsa-device')) {
            const panel = button.closest('#ccsa-preview-panel,#ccsa-live-appearance');
            const iframe = panel?.querySelector('iframe');
            if (!iframe) return;
            iframe.style.width = button.dataset.width === '390px' ? '390px' : '100%';
            panel.querySelectorAll('.ccsa-device').forEach(el => el.setAttribute('aria-pressed', String(el === button)));
        } else if (button.matches('.ccsa-add')) {
            const kind = button.dataset.list;
            const list = document.getElementById('ccsa-' + kind);
            const template = document.getElementById('ccsa-' + kind + '-template');
            if (!list || !template || list.children.length >= (kind === 'supporters' ? 30 : 100)) return;
            const fragment = template.content.cloneNode(true);
            const index = String(++counter);
            fragment.querySelectorAll('[name]').forEach(el => { el.name = el.name.replace('__INDEX__', index); });
            list.append(fragment);
        } else if (button.matches('.ccsa-remove')) {
            button.closest('.ccsa-item')?.remove();
        } else if (button.matches('.ccsa-clear')) {
            const wrapper = button.closest('.ccsa-media');
            wrapper.querySelector('input').value = '';
            wrapper.querySelector('.ccsa-preview').replaceChildren();
        } else if (button.matches('.ccsa-pick') && window.wp?.media) {
            const wrapper = button.closest('.ccsa-media');
            const frame = wp.media({title:'Choisir une image',library:{type:'image'},multiple:false});
            frame.on('select', () => {
                const file = frame.state().get('selection').first().toJSON();
                wrapper.querySelector('input').value = String(file.id);
                const img = document.createElement('img');
                img.src = file.sizes?.thumbnail?.url || file.url;
                img.alt = file.alt || '';
                wrapper.querySelector('.ccsa-preview').replaceChildren(img);
            });
            frame.open();
        }
    });
})();