(function(wp) {
    const el = wp.element.createElement;
    const registerBlockType = wp.blocks.registerBlockType;
    const InspectorControls = wp.blockEditor.InspectorControls;
    const SelectControl = wp.components.SelectControl;
    const PanelBody = wp.components.PanelBody;
    const ServerSideRender = wp.serverSideRender;
    const TextControl = wp.components.TextControl;

    registerBlockType('gw/package-includes', {
        title: 'Package Includes (Sub-services)',
        icon: 'grid-view',
        category: 'gary-editorial-native',
        attributes: {
            service_id: { type: 'string', default: '' },
            title: { type: 'string', default: 'Package Includes' },
            columns: { type: 'number', default: 2 }
        },
        edit: function(props) {
            const serviceOptions = window.gwBooklyServiceOptions || [];
            
            const inspector = el(InspectorControls, null,
                el(PanelBody, { title: 'Package Options', initialOpen: true },
                    el(SelectControl, {
                        label: 'Target Package',
                        help: 'Select the package service to display inclusions for.',
                        value: props.attributes.service_id,
                        options: serviceOptions,
                        onChange: function(newVal) { props.setAttributes({ service_id: newVal }); }
                    }),
                    el(TextControl, {
                        label: 'Section Title',
                        value: props.attributes.title,
                        onChange: function(newVal) { props.setAttributes({ title: newVal }); }
                    }),
                    el(SelectControl, {
                        label: 'Grid Columns',
                        value: props.attributes.columns,
                        options: [
                            { label: '1 Column', value: 1 },
                            { label: '2 Columns', value: 2 },
                            { label: '3 Columns', value: 3 }
                        ],
                        onChange: function(newVal) { props.setAttributes({ columns: parseInt(newVal) }); }
                    })
                )
            );

            const preview = el(ServerSideRender, {
                block: 'gw/package-includes',
                attributes: props.attributes,
                EmptyResponsePlaceholder: function() {
                    return el('div', { style: { padding: '20px', border: '1px dashed #ccc', textAlign: 'center' } }, 'Loading Package Inclusions...');
                }
            });

            return el('div', { className: 'gw-block-wrapper' }, inspector, preview);
        },
        save: function() { return null; }
    });

})(window.wp);
