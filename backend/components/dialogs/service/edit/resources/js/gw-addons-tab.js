jQuery(function($) {
    console.log('GW Addons: Auto-Save JS Loaded');

    function injectGWAddonsTab($panel) {
        if (!$panel.length) return;
        
        var service_id = $panel.find('input[name="id"]').val();
        var $tabsHandleContainer = $panel.find('.nav-tabs');
        var $tabsContentContainer = $panel.find('.tab-content');

        if ($tabsHandleContainer.length && !$tabsHandleContainer.find('a[href="#gw-bookly-link-tab"]').length) {
            $tabsHandleContainer.append(
                '<li class="nav-item">' +
                '<a class="nav-link" href="#gw-bookly-link-tab" data-toggle="bookly-tab">' +
                '<i class="fas fa-fw fa-link mr-lg-1"></i>' +
                '<span class="d-none d-lg-inline">GW Addons</span>' +
                '</a></li>'
            );

            $tabsContentContainer.append(
                '<div class="tab-pane" id="gw-bookly-link-tab">' +
                '<div id="gw-bookly-link-container" class="p-4">' +
                '</div></div>'
            );
        }

        if (service_id) {
            $('#gw-bookly-link-container').html('<div class="bookly-loading"></div>');
            loadLinkData(service_id);
        }
    }

    function loadLinkData(service_id) {
        $.post(GW_BooklyAddons.ajaxurl, {
            action: 'gw_bookly_get_link',
            service_id: service_id,
            nonce: GW_BooklyAddons.nonce
        }, function(response) {
            if (response.success) {
                $('#gw-bookly-link-container').html(response.data.html + '<div id="gw-save-indicator" class="mt-2 text-success" style="display:none;"><i class="fas fa-check-circle"></i> Saved!</div>');
            }
        });
    }

    function saveGwAddons() {
        var $modal = $('#bookly-edit-service-modal');
        var service_id = $modal.find('input[name="id"]').val();
        var page_id = $('#gw_bookly_page_id').val();
        var $indicator = $('#gw-save-indicator');
        
        // Collect all inclusions
        var inclusions = [];
        $('input[name="gw_inclusions[]"]').each(function() {
            inclusions.push($(this).val());
        });

        console.log('GW Addons: Auto-Saving...', { service_id: service_id, page_id: page_id, inclusions: inclusions });
        
        $indicator.hide();

        $.post(GW_BooklyAddons.ajaxurl, {
            action: 'gw_bookly_save_link',
            service_id: service_id,
            page_id: page_id,
            inclusions: inclusions,
            nonce: GW_BooklyAddons.nonce
        }, function(response) {
            if (response.success) {
                $indicator.fadeIn().delay(2000).fadeOut();
            } else {
                console.error('GW Addons Error:', response.data);
            }
        });
    }

    /**
     * AUTO-SAVE TRIGGERS
     */
    $(document).on('change', '#gw_bookly_page_id', function() {
        saveGwAddons();
    });

    // Add Inclusion
    $(document).on('click', '#gw_add_inclusion_btn', function() {
        var $select = $('#gw_add_inclusion_select');
        var inc_id = $select.val();
        var inc_title = $select.find('option:selected').text();

        if (inc_id === "0") return;

        var html = 
            '<div class="gw-inclusion-item d-flex align-items-center mb-2 p-2 bg-light border rounded">' +
            '<span class="flex-grow-1"><i class="fas fa-cube mr-2 text-primary"></i> ' + inc_title + '</span>' +
            '<input type="hidden" name="gw_inclusions[]" value="' + inc_id + '">' +
            '<button type="button" class="btn btn-sm btn-outline-danger gw-remove-inclusion"><i class="fas fa-times"></i></button>' +
            '</div>';

        $('#gw-package-builder-list').append(html);
        $select.val("0");
        saveGwAddons();
    });

    // Remove Inclusion
    $(document).on('click', '.gw-remove-inclusion', function() {
        $(this).closest('.gw-inclusion-item').remove();
        saveGwAddons();
    });

    /**
     * Listen for Bookly events
     */
    $(document.body).on('service.initForm', function(event, $panel) {
        injectGWAddonsTab($panel);
    });

    $(document).ajaxComplete(function(event, xhr, settings) {
        if (settings.data && settings.data.indexOf('action=bookly_get_service_data') !== -1) {
            setTimeout(function() {
                injectGWAddonsTab($('#bookly-edit-service-modal'));
            }, 100);
        }
    });

    setInterval(function() {
        var $modal = $('#bookly-edit-service-modal');
        if ($modal.is(':visible') && $modal.find('.nav-tabs').length && !$('#gw_bookly_page_id').length && !$('#gw-bookly-link-tab .bookly-loading').length) {
            injectGWAddonsTab($modal);
        }
    }, 2000);
});
