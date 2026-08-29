<?php
namespace GW_BooklyAddons\Lib;

if ( ! defined( 'ABSPATH' ) ) exit;

use Bookly\Lib as BooklyLib;

class Ajax extends BooklyLib\Base\Ajax
{
    public static function init()
    {
        parent::init();
        add_action( 'wp_ajax_gw_bookly_get_link', array( __CLASS__, 'getLink' ) );
        add_action( 'wp_ajax_gw_bookly_save_link', array( __CLASS__, 'saveLink' ) );
        add_action( 'wp_ajax_gary_check_availability', array( __CLASS__, 'checkAvailability' ) );
        add_action( 'wp_ajax_nopriv_gary_check_availability', array( __CLASS__, 'checkAvailability' ) );
    }

    /**
     * SELF-HEALING: Ensure the table exists for the current site before saving.
     */
    protected static function ensureTableExists() {
        Installer::install();
    }

    public static function checkAvailability()
    {
        try {
            $service_id = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;
            $duration = isset($_GET['duration']) ? sanitize_text_field($_GET['duration']) : '';
            $date = isset($_GET['check_date']) ? sanitize_text_field($_GET['check_date']) : '';

            // SELF-HEALING: If no service_id but duration is provided (e.g. "Full Day")
            if ( $service_id === 0 && ! empty( $duration ) ) {
                global $wpdb;
                $table = $wpdb->prefix . 'bookly_services';
                $found_id = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM $table WHERE title LIKE %s LIMIT 1", '%' . $wpdb->esc_like($duration) . '%' ) );
                if ( $found_id ) {
                    $service_id = (int)$found_id;
                }
            }

            if ( empty($date) || empty($service_id) ) {
                wp_send_json_error( array( 'message' => 'Please select a date and service. (Found: ' . $duration . ' -> ID: ' . $service_id . ')' ) );
            }

            $service = \Bookly\Lib\Entities\Service::find( $service_id );
            if ( ! $service ) {
                wp_send_json_error( array( 'message' => 'Service not found (ID: ' . $service_id . ').' ) );
            }

            // EXACT REPLICA OF _setDataForSkippedServiceStep (Bookly core Ajax.php:1297)
            // For compound services, staff must be looked up from the FIRST sub-service, not the parent.
            $lookup_service_id = $service_id;
            if ( $service->withSubServices() ) {
                $sub_services = $service->getSubServices();
                $lookup_service_id = reset( $sub_services )->getId();
            }
            $staff_ids = \Bookly\Lib\Entities\StaffService::query( 'ss' )
                ->leftJoin( 'Staff', 's', 's.id = ss.staff_id' )
                ->where( 'ss.service_id', $lookup_service_id )
                ->where( 's.visibility', 'public' )
                ->fetchCol( 'ss.staff_id' );

            $days_times = \Bookly\Lib\Config::getDaysAndTimes();

            $userData = new \Bookly\Lib\UserBookingData( 'gw_ajax' );
            $userData->chain->clear();

            $chain_item = new \Bookly\Lib\ChainItem();
            $chain_item
                ->setNumberOfPersons( $service->getCapacityMin() )
                ->setQuantity( 1 )
                ->setUnits( $service->getUnitsMin() )
                ->setServiceId( $service_id )
                ->setStaffIds( $staff_ids )
                ->setLocationId( null );
            $userData->chain->add( $chain_item );

            $tz = \Bookly\Lib\Config::getTimeZone();
            $userData->setTimeZone( $tz );
            \Bookly\Lib\Slots\DatePoint::$client_timezone = $tz;

            $time_from = key( $days_times['times'] );
            end( $days_times['times'] );
            $time_to = key( $days_times['times'] );

            $userData->fillData( array(
                'date_from'      => $date,
                'days'           => array_keys( $days_times['days'] ),
                'edit_cart_keys' => array(),
                'slots'          => array(),
                'time_from'      => $time_from,
                'time_to'        => $time_to,
            ) );

            // OFFICIAL FINDER CALL — identical to Bookly core renderTime (line 242)
            $finder = new \Bookly\Lib\Slots\Finder(
                $userData,
                null,
                null,
                null,
                array(),
                null,
                \Bookly\Lib\Config::showSingleTimeSlotPerDay()
            );
            // Constrain search to the specific requested date (as renderTime does)
            $finder->setSelectedDate( $date );
            $finder->prepare()->load();
            $slots = $finder->getSlots();

            // Count only slots on the specific requested date
            $total_slots = isset( $slots[ $date ] ) ? count( $slots[ $date ] ) : 0;

            // Today is never "Available" — minimum booking notice enforced
            $today = current_time( 'Y-m-d' );
            $is_available = ( $total_slots > 0 ) && ( $date > $today );

            wp_send_json_success( array(
                'status'  => $is_available ? 'available' : 'tentative',
                'message' => $is_available
                    ? 'Excellent... Now let\'s book your FREE wedding consultation to discuss this in detail.'
                    : 'I may be free! I have restricted hours on this day, but I may be able to rearrange plans for your wedding. Please book a FREE consultation to discuss.',
            ) );

        } catch ( \Exception $e ) {
            error_log( 'GW Bookly availability error: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => 'An error occurred. Please try again.' ) );
        } catch ( \Error $e ) {
            error_log( 'GW Bookly availability fatal: ' . $e->getMessage() );
            wp_send_json_error( array( 'message' => 'An error occurred. Please try again.' ) );
        }
    }




    public static function saveLink()
    {
        check_ajax_referer( 'gw_bookly_addons', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorised.' ) );
        }
        self::ensureTableExists();

        $service_id = (int) self::parameter( 'service_id' );
        $page_id = (int) self::parameter( 'page_id' );
        $inclusions = self::parameter( 'inclusions', array() );

        global $wpdb;
        $table_links = $wpdb->prefix . 'gw_bookly_service_links';
        $table_inclusions = $wpdb->prefix . 'gw_bookly_service_inclusions';

        // 1. Save Page Link
        if ( $page_id == 0 ) {
            $wpdb->delete( $table_links, array( 'service_id' => $service_id ), array( '%d' ) );
        } else {
            $wpdb->replace( 
                $table_links, 
                array( 'service_id' => $service_id, 'wp_page_id' => $page_id ), 
                array( '%d', '%d' ) 
            );
        }

        // 2. Save Inclusions (Clear and Re-insert to maintain sequence)
        $wpdb->delete( $table_inclusions, array( 'parent_id' => $service_id ), array( '%d' ) );
        if ( ! empty( $inclusions ) && is_array( $inclusions ) ) {
            $pos = 0;
            foreach ( $inclusions as $inc_id ) {
                $wpdb->insert( 
                    $table_inclusions, 
                    array( 'parent_id' => $service_id, 'included_id' => (int)$inc_id, 'position' => $pos++ ), 
                    array( '%d', '%d', '%d' ) 
                );
            }
        }

        wp_send_json_success( 'Saved' );
    }

    public static function getLink()
    {
        check_ajax_referer( 'gw_bookly_addons', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( array( 'message' => 'Unauthorised.' ) );
        }
        self::ensureTableExists();

        $service_id = (int) self::parameter( 'service_id' );
        global $wpdb;
        $table_links = $wpdb->prefix . 'gw_bookly_service_links';
        $table_inclusions = $wpdb->prefix . 'gw_bookly_service_inclusions';
        $table_bookly_services = $wpdb->prefix . 'bookly_services';
        
        $current_page_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT wp_page_id FROM $table_links WHERE service_id = %d", $service_id ) );
        
        // Fetch current inclusions
        $current_inclusions = $wpdb->get_results( $wpdb->prepare( "SELECT included_id FROM $table_inclusions WHERE parent_id = %d ORDER BY position ASC", $service_id ), ARRAY_A );
        
        // Fetch all possible Simple Services for the builder
        $all_simple_services = $wpdb->get_results( "SELECT id, title FROM $table_bookly_services WHERE type = 'simple' ORDER BY title ASC", ARRAY_A );

        // FETCH FILTERED PAGES: Only those using the service-detail template
        $pages = get_posts( array(
            'post_type' => 'page',
            'numberposts' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
            'meta_query' => array(
                array(
                    'key' => '_wp_page_template',
                    'value' => 'page-service-detail.php'
                )
            )
        ) );

        ob_start();
        ?>
        <div class="gw-addons-container">
            <!-- Part 1: Page Linking -->
            <div class="form-group mb-4">
                <label for="gw_bookly_page_id" class="h6 font-weight-bold">1. Editorial Detail Page</label>
                <select id="gw_bookly_page_id" class="form-control custom-select">
                    <option value="0">-- Select Linked Detail Page --</option>
                    <?php foreach ( $pages as $page ) : ?>
                        <option value="<?php echo $page->ID ?>" <?php selected( $current_page_id, (int)$page->ID ) ?>><?php echo esc_html( $page->post_title ) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="form-text text-muted">This links the Bookly service to its high-fidelity detail page.</small>
            </div>

            <hr />

            <!-- Part 2: Package Builder (Inclusions) -->
            <div class="form-group mb-0">
                <label class="h6 font-weight-bold">2. Package Inclusions (Build-up)</label>
                <p class="small text-muted mb-2">Select the simple services that are included in this package. These will be added to the customer dashboard as credits.</p>
                
                <div id="gw-package-builder-list" class="mb-3">
                    <?php if ( ! empty( $current_inclusions ) ) : ?>
                        <?php foreach ( $current_inclusions as $inc ) : 
                            $inc_title = '';
                            foreach($all_simple_services as $s) if($s['id'] == $inc['included_id']) $inc_title = $s['title'];
                            if(!$inc_title) continue;
                        ?>
                            <div class="gw-inclusion-item d-flex align-items-center mb-2 p-2 bg-light border rounded">
                                <span class="flex-grow-1"><i class="fas fa-cube mr-2 text-primary"></i> <?php echo esc_html( $inc_title ) ?></span>
                                <input type="hidden" name="gw_inclusions[]" value="<?php echo (int)$inc['included_id'] ?>">
                                <button type="button" class="btn btn-sm btn-outline-danger gw-remove-inclusion"><i class="fas fa-times"></i></button>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="input-group">
                    <select id="gw_add_inclusion_select" class="form-control custom-select">
                        <option value="0">-- Add Included Service --</option>
                        <?php foreach ( $all_simple_services as $s ) : ?>
                            <option value="<?php echo $s['id'] ?>"><?php echo esc_html( $s['title'] ) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="input-group-append">
                        <button class="btn btn-primary" type="button" id="gw_add_inclusion_btn"><i class="fas fa-plus"></i> Add</button>
                    </div>
                </div>
            </div>

            <div id="gw-save-indicator" class="mt-3 text-success" style="display:none; font-weight: bold;">
                <i class="fas fa-check-circle"></i> Changes Saved Automatically!
            </div>
        </div>
        <?php
        $html = ob_get_clean();

        wp_send_json_success( array(
            'html' => $html
        ) );
    }
}
