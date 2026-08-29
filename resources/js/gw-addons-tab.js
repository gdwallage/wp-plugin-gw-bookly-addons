jQuery(function($) {
    // 1. Hook into Bookly Service Edit Modal opening
    $(document).on('click', '.bookly-js-edit', function() {
        const serviceId = $(this).closest('tr').data('id') || $('#bookly-edit-service-modal input[name="id"]').val();
        
        // Give Bookly time to render its modal content
        setTimeout(() => {
            if ($('#bookly-services-gw-tab').length === 0) {
                injectGWTab(serviceId);
            }
        }, 500);
    });

    function injectGWTab(serviceId) {
        // A. Add Tab
        const $tabs = $('.bookly-js-service-tabs .nav-tabs');
        $tabs.append(`
            <li class="nav-item">
                <a id="bookly-services-gw-tab" class="nav-link" href="#bookly-services-gw" data-toggle="bookly-tab">
                    <i class="fas fa-fw fa-link mr-lg-1"></i>
                    <span class="d-none d-lg-inline">GW Addons</span>
                </a>
            </li>
        `);

        // B. Add Pane
        const $containers = $('.bookly-js-service-containers');
        $containers.append(`
            <div class="tab-pane" id="bookly-services-gw">
                <div class="form-group">
                    <label for="gw-wp-page-id">Associated Wedding Detail Page</label>
                    <select id="gw-wp-page-id" class="form-control mb-3">
                        <option value="0">Loading pages...</option>
                    </select>
                    <button type="button" id="gw-save-link" class="btn btn-success">Save GW Link</button>
                    <span id="gw-save-status" class="ml-2"></span>
                </div>
                <small class="text-muted">Linking a Bookly Service to a WordPress page makes it the "official" detail page for this service across the site.</small>
            </div>
        `);

        // C. Fetch Data
        fetchGWData(serviceId);
    }

    function fetchGWData(serviceId) {
        $.ajax({
            url: GW_BooklyAddons.ajaxurl,
            type: 'POST',
            data: {
                action: 'gw_bookly_get_link',
                service_id: serviceId,
                nonce: GW_BooklyAddons.nonce
            },
            success: function(response) {
                if (response.success) {
                    let options = '<option value="0">-- Select WordPress Page --</option>';
                    response.data.pages.forEach(page => {
                        const selected = (page.ID == response.data.current_id) ? 'selected' : '';
                        options += `<option value="${page.ID}" ${selected}>${page.post_title}</option>`;
                    });
                    $('#gw-wp-page-id').html(options);
                }
            }
        });
    }

    // Handle Save
    $(document).on('click', '#gw-save-link', function() {
        const $btn = $(this);
        const serviceId = $('#bookly-edit-service-modal input[name="id"]').val();
        const pageId = $('#gw-wp-page-id').val();
        
        $btn.prop('disabled', true).text('Saving...');
        
        $.ajax({
            url: GW_BooklyAddons.ajaxurl,
            type: 'POST',
            data: {
                action: 'gw_bookly_save_link',
                service_id: serviceId,
                wp_page_id: pageId,
                nonce: GW_BooklyAddons.nonce
            },
            success: function(response) {
                $btn.prop('disabled', false).text('Save GW Link');
                $('#gw-save-status').text('Saved!').fadeOut(2000, function() { $(this).text('').show(); });
            }
        });
    });
});
