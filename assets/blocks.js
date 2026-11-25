/* global wp, ConvaincreConnectBlocks */
(function () {
    'use strict';
    const el = wp.element.createElement;
    const { registerBlockType } = wp.blocks;
    const { useBlockProps } = wp.blockEditor;
    const { SelectControl, Placeholder, Notice } = wp.components;
    const { __ } = wp.i18n;
    const catalog = typeof ConvaincreConnectBlocks === 'object' ? ConvaincreConnectBlocks : {};
    const tr = (value) => __(value, 'convaincre-connect');

    function register(name, title, icon, rows, agenda) {
        const items = Array.isArray(rows) ? rows : [];
        registerBlockType('convaincre-connect/' + name, {
            apiVersion: 3,
            title: tr(title),
            icon: icon,
            category: 'widgets',
            attributes: { id: { type: 'string', default: '' } },
            supports: { html: false },
            edit: function ({ attributes, setAttributes }) {
                const props = useBlockProps();
                const id = attributes.id || '';
                const current = items.find((item) => item.id === id);
                const options = [{ label: tr(agenda ? 'Tous les événements publiés' : 'Choisir un formulaire'), value: '' }]
                    .concat(items.map((item) => ({ label: item.label, value: item.id })));
                if (id && !current) {
                    options.push({ label: tr('Sélection indisponible') + ' (' + id + ')', value: id });
                }
                return el('div', props, el(Placeholder, {
                    icon: icon,
                    label: tr(title),
                    instructions: tr('Le contenu public est actualisé automatiquement depuis Convaincre.'),
                },
                    el(SelectControl, {
                        label: tr(agenda ? 'Événement' : 'Formulaire'),
                        value: id,
                        options: options,
                        onChange: (value) => setAttributes({ id: value }),
                    }),
                    id && !current ? el(Notice, { status: 'warning', isDismissible: false },
                        tr('Cette sélection n’est plus dans le catalogue local. Actualisez les formulaires depuis le menu Campagne Convaincre et rechargez l’éditeur.')) : null,
                    !items.length ? el('p', null, tr('Aucun élément synchronisé. Vérifiez la connexion dans le menu Campagne Convaincre.')) : null,
                    current ? el('p', null, current.label) : null,
                    el('p', null, tr('Aperçu indicatif : consultez la page publique pour voir le rendu et les styles du thème.'))
                ));
            },
            save: function () { return null; },
        });
    }
    register('form', 'Convaincre — Formulaire', 'feedback', catalog.forms, false);
    register('agenda', 'Convaincre — Agenda', 'calendar-alt', catalog.events, true);
})();
