(function(wp) {
    const el = wp.element.createElement;
    const registerBlockType = wp.blocks.registerBlockType;
    const InspectorControls = wp.blockEditor.InspectorControls;
    const SelectControl = wp.components.SelectControl;
    const PanelBody = wp.components.PanelBody;
    const ServerSideRender = wp.serverSideRender;
    const TextControl = wp.components.TextControl;

    registerBlockType('gw/investment-plaque', {
        title: 'Investment Plaque (Service Box)',
        icon: 'money-alt',
        category: 'gary-editorial-native',
        attributes: {
            service_id: { type: 'string', default: '' },
            target_email: { type: 'string', default: '' },
            booking_url: { type: 'string', default: '#booking' },
            request_label: { type: 'string', default: 'Request Details' },
            booking_label: { type: 'string', default: 'Book Consultation' }
        },
        edit: function(props) {
            const serviceOptions = window.gwBooklyServiceOptions || [];
            
            const inspector = el(InspectorControls, null,
                el(PanelBody, { title: 'Plaque Configuration', initialOpen: true },
                    el(SelectControl, {
                        label: 'Linked Bookly Service',
                        help: 'Defaults to current page link if empty.',
                        value: props.attributes.service_id,
                        options: serviceOptions,
                        onChange: function(newVal) { props.setAttributes({ service_id: newVal }); }
                    }),
                    el(TextControl, {
                        label: 'Request Button Text',
                        value: props.attributes.request_label,
                        onChange: function(newVal) { props.setAttributes({ request_label: newVal }); }
                    }),
                    el(TextControl, {
                        label: 'Target Email for Inquiries',
                        help: 'Where the Request Details form should send emails.',
                        value: props.attributes.target_email,
                        onChange: function(newVal) { props.setAttributes({ target_email: newVal }); }
                    }),
                    el(TextControl, {
                        label: 'Booking Button Text',
                        value: props.attributes.booking_label,
                        onChange: function(newVal) { props.setAttributes({ booking_label: newVal }); }
                    }),
                    el(TextControl, {
                        label: 'Booking Anchor/URL',
                        value: props.attributes.booking_url,
                        onChange: function(newVal) { props.setAttributes({ booking_url: newVal }); }
                    })
                )
            );

            const preview = el(ServerSideRender, {
                block: 'gw/investment-plaque',
                attributes: props.attributes,
                EmptyResponsePlaceholder: function() {
                    return el('div', { style: { padding: '20px', border: '1px dashed #ccc', textAlign: 'center' } }, 'Loading Investment Plaque...');
                }
            });

            return el('div', { className: 'gw-block-wrapper' }, inspector, preview);
        },
        save: function() { return null; }
    });

})(window.wp);
