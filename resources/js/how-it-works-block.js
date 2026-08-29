(function(wp) {
    const el = wp.element.createElement;
    const registerBlockType = wp.blocks.registerBlockType;
    const ServerSideRender = wp.serverSideRender;

    registerBlockType('gw/how-it-works', {
        title: 'How It Works (Editorial)',
        icon: 'info',
        category: 'gary-editorial-native',
        edit: function(props) {
            return el('div', { className: 'gw-block-wrapper' },
                el(ServerSideRender, {
                    block: 'gw/how-it-works',
                    attributes: props.attributes
                })
            );
        },
        save: function() { return null; }
    });
})(window.wp);
