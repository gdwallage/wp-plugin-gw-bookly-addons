<?php
namespace GW_BooklyAddons\Lib;

if ( ! defined( 'ABSPATH' ) ) exit;

class Blocks
{
    public static function init()
    {
        add_action( 'init', array( __CLASS__, 'register' ) );
        add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editorAssets' ) );
        add_shortcode( 'gw_how_it_works', array( __CLASS__, 'renderHowItWorks' ) );
        
        // AJAX Handle for Modal Inquiries
        add_action( 'wp_ajax_gw_submit_request', array( __CLASS__, 'handleRequestSubmission' ) );
        add_action( 'wp_ajax_nopriv_gw_submit_request', array( __CLASS__, 'handleRequestSubmission' ) );
    }

    public static function handleRequestSubmission()
    {
        $name    = sanitize_text_field( $_POST['user_name'] ?? '' );
        $email   = sanitize_email( $_POST['user_email'] ?? '' );
        $note    = sanitize_textarea_field( $_POST['user_note'] ?? '' );
        $target  = sanitize_email( $_POST['target_email'] ?? '' );
        $service = sanitize_text_field( $_POST['service_name'] ?? '' );

        if ( ! $name || ! $email || ! $target ) {
            wp_send_json_error( 'Please provide your name and a valid email.' );
        }

        $subject = "Photography Inquiry: " . $service . " from " . $name;
        $body    = "You have received a new inquiry via the Boutique Investment Box.\n\n" .
                   "--------------------------------------------------\n" .
                   "SERVICE:   " . $service . "\n" .
                   "NAME:      " . $name . "\n" .
                   "EMAIL:     " . $email . "\n" .
                   "--------------------------------------------------\n\n" .
                   "MESSAGE:\n" . $note . "\n\n" .
                   "--------------------------------------------------\n";
        
        $headers = array(
            'Content-Type: text/plain; charset=UTF-8',
            'From: ' . get_bloginfo('name') . ' <' . get_option('admin_email') . '>',
            'Reply-To: ' . $name . ' <' . $email . '>'
        );

        // Send to Gary (Target)
        wp_mail( $target, $subject, $body, $headers );
        
        // Send Copy to Client
        $client_body = "Hi " . $name . ",\n\n" .
                       "Thank you for your interest in " . $service . ". This is a copy of the inquiry you sent to Gary Wallage.\n\n" .
                       "We will be in touch with you shortly.\n\n" .
                       "--------------------------------------------------\n" .
                       $body;
        wp_mail( $email, "Your Inquiry: " . $service, $client_body, $headers );

        wp_send_json_success();
    }

    public static function register()
    {
        register_block_type( 'gw/investment-plaque', array(
            'render_callback' => array( __CLASS__, 'renderInvestmentPlaque' ),
            'attributes'      => array(
                'service_id'   => array( 'type' => 'string', 'default' => '' ),
                'target_email' => array( 'type' => 'string', 'default' => get_option('admin_email') ),
                'booking_url'  => array( 'type' => 'string', 'default' => '#booking' ),
                'request_label' => array( 'type' => 'string', 'default' => 'Request Details' ),
                'booking_label' => array( 'type' => 'string', 'default' => 'Book Consultation' ),
            ),
        ) );

        register_block_type( 'gw/package-includes', array(
            'render_callback' => array( __CLASS__, 'renderPackageIncludes' ),
            'attributes'      => array(
                'service_id'  => array( 'type' => 'string', 'default' => '' ),
                'title'       => array( 'type' => 'string', 'default' => 'Package Includes' ),
                'columns'     => array( 'type' => 'number', 'default' => 2 ),
            ),
        ) );

        register_block_type( 'gw/how-it-works', array(
            'render_callback' => array( __CLASS__, 'renderHowItWorks' ),
        ) );
    }

    public static function editorAssets()
    {
        wp_enqueue_script(
            'gw-investment-plaque-block-js',
            plugins_url( 'resources/js/investment-plaque-block.js', dirname( __DIR__ ) . '/main.php' ),
            array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ),
            '1.19.0',
            true
        );

        wp_enqueue_script(
            'gw-package-includes-block-js',
            plugins_url( 'resources/js/package-includes-block.js', dirname( __DIR__ ) . '/main.php' ),
            array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ),
            '1.6.0',
            true
        );

        wp_enqueue_script(
            'gw-how-it-works-block-js',
            plugins_url( 'resources/js/how-it-works-block.js', dirname( __DIR__ ) . '/main.php' ),
            array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render' ),
            '1.18.1',
            true
        );

        // Localize options for the dropdown
        global $wpdb;
        $options = array( array( 'label' => '-- Current Page Default --', 'value' => '' ) );
        $table_name = $wpdb->prefix . 'bookly_services';
        if ( $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) == $table_name ) {
            $services = $wpdb->get_results( "SELECT id, title FROM $table_name ORDER BY title ASC" );
            foreach ( $services as $s ) {
                $options[] = array( 'label' => $s->title, 'value' => $s->id );
            }
        }

        wp_localize_script( 'gw-package-includes-block-js', 'gwBooklyServiceOptions', $options );
    }

    public static function renderInvestmentPlaque( $atts )
    {
        $atts = wp_parse_args( $atts, array(
            'service_id'   => '',
            'target_email' => get_option('admin_email'),
            'booking_url'  => '#booking',
            'request_label' => 'Request Details',
            'booking_label' => 'Book Consultation',
        ) );

        $service_id = ! empty( $atts['service_id'] ) ? $atts['service_id'] : (function_exists('gary_get_service_id_for_page') ? gary_get_service_id_for_page( get_the_ID() ) : '');
        $clean_title = function_exists('gary_clean_service_name') ? gary_clean_service_name(get_the_title()) : get_the_title();

        ob_start();

        // If the unified theme renderer exists, use it but inject our modal trigger
        if ( function_exists( 'gary_get_service_data_unified' ) && function_exists( 'gary_render_service_plaque_html' ) ) {
            $data = gary_get_service_data_unified( $service_id, 'bookly' );
            
            // Hard fallback: if duration is completely empty, try the current page's custom field
            if ( empty( $data['duration'] ) ) {
                $manual_dur = get_post_meta( get_the_ID(), '_gary_service_duration', true );
                if ( $manual_dur ) {
                    $data['duration'] = $manual_dur;
                }
            }

            $html = gary_render_service_plaque_html( $data );
            
            // Inject modal trigger into the 'Request Details' button (target the #request anchor)
            $html = str_replace( 'href="#request"', 'href="javascript:void(0)" class="gw-request-modal-trigger" data-email="' . esc_attr($atts['target_email']) . '" data-service="' . esc_attr($data['title']) . '"', $html );
            
            // Inject booking URL
            $html = str_replace( 'href="/booking/"', 'href="' . esc_attr($atts['booking_url']) . '"', $html );
            // Inject custom labels if provided
            if($atts['request_label'] !== 'Request Details') $html = str_replace('Request Details', esc_html($atts['request_label']), $html);
            if($atts['booking_label'] !== 'Book Consultation') $html = str_replace('Book Consultation', esc_html($atts['booking_label']), $html);

            echo $html;
        } else {
            // Fallback for standalone plugin usage
            $data = function_exists('gary_get_bookly_service_data') ? gary_get_bookly_service_data( $service_id ) : false;
            $price = $data ? (float) $data['price'] : 0;
            $summary = function_exists('gary_get_sub_service_summary') ? gary_get_sub_service_summary($service_id, false) : array('savings' => 0);
            $savings = !empty($summary['savings']) ? (float)$summary['savings'] : 0;
            $duration = !empty($summary['total_duration']) ? $summary['total_duration'] : ($data['duration'] ?? 0);
            
            ?>
            <div class="investment-sidebar plaque-rendering-context" style="max-width: 420px; margin: 40px auto;">
                <div class="investment-plaque" style="position: relative; overflow: hidden; border: 2px solid var(--brand-gold-light); padding: 40px; background: #fff; box-shadow: var(--shadow-deep); text-align:center;">
                    <?php if ( $savings > 0 ) : ?>
                        <div class="investment-savings-ribbon">SAVE £<?php echo number_format($savings, 0); ?></div>
                    <?php endif; ?>

                    <div class="price-wrap" style="padding-bottom: 20px; margin-bottom: 10px;">
                        <div class="price-label" style="font-size:0.75rem; text-transform:uppercase; letter-spacing:2px; opacity:0.6; margin-bottom:15px; font-weight:700;">PACKAGE PRICE</div>
                        <div class="price-val" style="font-size:4.2rem; font-family:var(--font-primary); font-weight:700; color:var(--brand-black); line-height:1; margin-bottom: 15px;">£<?php echo number_format($price, 2); ?></div>
                        <div class="package-name-sub" style="font-family:'Lato', sans-serif; font-size:0.9rem; text-transform:uppercase; letter-spacing:2px; opacity:0.9; font-weight:700; margin-bottom: 25px;"><?php echo esc_html($clean_title); ?></div>
                    </div>

                    <?php if ( $duration > 0 ) : ?>
                        <div class="duration-val" style="margin-bottom:30px; font-size:0.75rem; letter-spacing:1px; font-weight:700; opacity:0.7; text-transform:uppercase; display:flex; align-items:center; justify-content:center;">
                            <img src="data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%23C5A059' viewBox='0 0 16 16'><path d='M8 3.5a.5.5 0 0 0-1 0V8a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 7.71V3.5z'/><path d='M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16zm7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0z'/></svg>" style="margin-right:10px;"/>
                            <?php echo esc_html( 'Typically ' . \GW_BooklyAddons\Lib\Utils::formatDuration( $duration ) ); ?>
                        </div>
                    <?php endif; ?>

                    <div class="investment-buttons" style="display:flex; flex-direction:column; gap:12px;">
                        <a href="javascript:void(0)" class="btn-black gw-request-modal-trigger" 
                           data-email="<?php echo esc_attr($atts['target_email']); ?>"
                           data-service="<?php echo esc_attr($clean_title); ?>"
                           style="background:#000; color:#fff; text-decoration:none; text-align:center; padding:18px; text-transform:uppercase; letter-spacing:2px; font-size:0.85rem; font-weight:700;">
                            <?php echo esc_html( $atts['request_label'] ); ?>
                        </a>
                        <a href="<?php echo esc_attr( $atts['booking_url'] ); ?>" class="btn-gold"
                           style="background:var(--brand-gold-light); color:#fff; text-decoration:none; text-align:center; padding:18px; text-transform:uppercase; letter-spacing:2px; font-size:0.85rem; font-weight:700;">
                            <?php echo esc_html( $atts['booking_label'] ); ?>
                        </a>
                    </div>
                </div>
            </div>
            <?php
        }
        ?>

        <!-- Hidden Request Modal Structure -->
        <div id="gw-request-modal" class="gw-modal" style="display:none; position:fixed; inset:0; z-index:10000; align-items:center; justify-content:center;">
            <div class="gw-modal-overlay" style="position:absolute; inset:0; background:rgba(0,0,0,0.85); backdrop-filter:blur(5px);"></div>
            <div class="gw-modal-content gw-editorial-gold-box" style="position:relative; z-index:2; max-width:500px; width:90%; background:#fff; padding:40px; border:2px solid var(--brand-gold-light); box-shadow:var(--shadow-deep);">
                <span class="gw-modal-close" style="position:absolute; top:20px; right:20px; font-size:30px; cursor:pointer; line-height:1; color:var(--brand-gold-light);">&times;</span>
                <h3 style="text-align:center; text-transform:uppercase; letter-spacing:3px; margin-bottom:10px; color:var(--brand-accent);"><?php echo esc_html($atts['request_label']); ?></h3>
                <p style="text-align:center; font-size:0.9rem; opacity:0.7; margin-bottom:30px;">For: <?php echo esc_html($clean_title ?? ''); ?></p>
                
                <form id="gw-request-form">
                    <input type="hidden" name="action" value="gw_submit_request">
                    <input type="hidden" name="target_email" value="<?php echo esc_attr($atts['target_email']); ?>">
                    <input type="hidden" name="service_name" value="<?php echo esc_attr($clean_title ?? ''); ?>">
                    
                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-size:0.75rem; text-transform:uppercase; letter-spacing:2px; font-weight:700; margin-bottom:8px; opacity:0.6;">Your Name</label>
                        <input type="text" name="user_name" required style="width:100%; padding:12px; border:1px solid #ddd; font-family:var(--font-primary);">
                    </div>
                    <div style="margin-bottom:20px;">
                        <label style="display:block; font-size:0.75rem; text-transform:uppercase; letter-spacing:2px; font-weight:700; margin-bottom:8px; opacity:0.6;">Email Address</label>
                        <input type="email" name="user_email" required style="width:100%; padding:12px; border:1px solid #ddd; font-family:var(--font-primary);">
                    </div>
                    <div style="margin-bottom:25px;">
                        <label style="display:block; font-size:0.75rem; text-transform:uppercase; letter-spacing:2px; font-weight:700; margin-bottom:8px; opacity:0.6;">Message / Note</label>
                        <textarea name="user_note" rows="4" style="width:100%; padding:12px; border:1px solid #ddd; font-family:var(--font-primary);"></textarea>
                    </div>
                    
                    <button type="submit" class="btn-black-gold" style="width:100%; border:none; padding:18px; cursor:pointer;">Send Request</button>
                    <div class="gw-form-status" style="margin-top:20px; text-align:center; font-weight:700; font-size:0.9rem;"></div>
                </form>
            </div>
        </div>

        <script>
        (function($){
            $(document).on('click', '.gw-request-modal-trigger', function(){
                $('#gw-request-modal').fadeIn(300).css('display', 'flex');
                $('body').css('overflow', 'hidden');
            });
            $(document).on('click', '.gw-modal-close, .gw-modal-overlay', function(){
                $('#gw-request-modal').fadeOut(300);
                $('body').css('overflow', 'auto');
            });
            $('#gw-request-form').on('submit', function(e){
                e.preventDefault();
                var $status = $(this).find('.gw-form-status').text('Sending Inquiry...').css('color', '#C5A059');
                var $btn = $(this).find('button').prop('disabled', true).css('opacity', '0.5');
                
                $.post('<?php echo admin_url('admin-ajax.php'); ?>', $(this).serialize(), function(res){
                    if(res.success){
                        $status.text('Inquiry sent successfully!').css('color', '#2ecc71');
                        setTimeout(function(){ 
                            $('#gw-request-modal').fadeOut(300); 
                            $('body').css('overflow', 'auto');
                            $btn.prop('disabled', false).css('opacity', '1');
                            $status.text('');
                        }, 2500);
                    } else {
                        $status.text('Error: ' + (res.data || 'Submission failed')).css('color', '#e74c3c');
                        $btn.prop('disabled', false).css('opacity', '1');
                    }
                });
            });
        })(jQuery);
        </script>
        <?php return ob_get_clean();
    }

    public static function renderPackageIncludes( $atts )
    {
        $service_id = ! empty( $atts['service_id'] ) ? $atts['service_id'] : gary_get_service_id_for_page( get_the_ID() );
        $summary = gary_get_sub_service_summary( get_the_ID(), $service_id );
        
        if ( empty( $summary['grid_items'] ) ) return '';

        $title = ! empty( $atts['title'] ) ? $atts['title'] : 'Package Includes';
        $cols = ! empty( $atts['columns'] ) ? (int) $atts['columns'] : 2;
        
        ob_start(); ?>
        <div class="detailed-components-section" style="margin-top: 10px !important; border-top: none !important; padding-top: 0 !important;">
            <h2 style="text-align: center; margin-bottom: 25px; text-transform: uppercase; letter-spacing: 3px; font-size: 1.1rem; opacity: 0.7; margin-top: 0 !important; font-weight: 700;"><?php echo esc_html( $title ); ?></h2>
            <div class="condensed-service-grid" style="grid-template-columns: repeat(<?php echo $cols; ?>, 1fr);">
                <?php foreach ( $summary['grid_items'] as $item ) : 
                    $item_price = (float)$item['price'];
                    $card_tag = !empty($item['permalink']) && $item['permalink'] !== '#' ? 'a' : 'div';
                    $card_href = $card_tag === 'a' ? ' href="' . esc_url($item['permalink']) . '"' : '';
                ?>
                    <<?php echo $card_tag; ?><?php echo $card_href; ?> class="condensed-service-card gw-editorial-gold-box" style="display: flex; gap: 15px; align-items: flex-start; padding: 12px 16px; text-decoration: none; color: inherit; min-height: 0; height: auto;">
                        <div class="condensed-coin" style="width: 45px; height: 45px; border-radius: 50%; border: 1.5px solid var(--brand-gold-light, #C5A059); overflow: hidden; flex-shrink: 0; background: #f9f9f9; margin-top: 2px;">
                            <?php 
                            $thumb = !empty( $item['thumbnail'] ) ? $item['thumbnail'] : '';
                            if ( !$thumb ) {
                                $logo_id = get_theme_mod( 'custom_logo' );
                                $thumb = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';
                            }
                            if ( $thumb ) : ?>
                                <img src="<?php echo esc_url( $thumb ); ?>" style="width: 100%; height: 100%; object-fit: contain;" />
                            <?php else : ?>
                                <div style="width:100%; height:100%; background:#f9f9f9; display:flex; align-items:center; justify-content:center; color:var(--brand-gold-light); font-weight:700;">GW</div>
                            <?php endif; ?>
                        </div>
                        <div class="condensed-info" style="flex: 1;">
                            <div class="condensed-header" style="display: flex; flex-direction: row; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 4px; width: 100%;">
                                <h3 style="margin: 0; font-family: var(--font-secondary, 'Lora', serif); font-size: 1.05rem; font-weight: 700; color: var(--brand-black, #111111); line-height: 1.2;"><?php echo esc_html( $item['clean_title'] ); ?></h3>
                                
                                <?php if ( $item_price > 0 ) : ?>
                                    <div style="font-size: 0.72rem; font-weight: 700; letter-spacing: 1px; font-family: var(--font-primary, 'Lato', sans-serif); text-transform: uppercase; margin: 0; display: inline-flex; align-items: center; gap: 5px;">
                                        <span style="text-decoration: line-through; opacity: 0.4;">&pound;<?php echo number_format($item_price, 0); ?></span> 
                                        <span style="color: var(--brand-gold-light, #C5A059);">INCLUDED</span> 
                                    </div>
                                <?php endif; ?>
                            </div>

                            <?php if ( ! empty( $item['info'] ) ) : ?>
                                <div class="condensed-desc" style="font-size: 0.8rem; opacity: 0.9; margin-top: 4px; line-height: 1.4; font-family: var(--font-primary, 'Lato', sans-serif); color: #555555;">
                                    <?php echo wp_kses_post( $item['info'] ); ?>
                                </div>
                            <?php endif; ?>


                        </div>
                    </<?php echo $card_tag; ?>>
                <?php endforeach; ?>
            </div>
        </div>
        <?php return ob_get_clean();
    }

    public static function renderHowItWorks( $atts )
    {
        ob_start(); ?>
        <div id="booking" class="gw-how-it-works-wrap gw-editorial-gold-box" style="margin: 10px auto; padding: 30px clamp(15px, 4vw, 40px); text-align: center; background: #fff; max-width: 1000px; border: 2px solid var(--brand-gold-light); box-shadow: var(--shadow-soft);">
            <h3 style="text-transform: uppercase; letter-spacing: 4px; font-size: 1.1rem; margin: 0 0 20px 0 !important; color: var(--brand-accent); font-weight: 700; font-family: var(--font-primary);">How it Works</h3>
            <div class="how-it-works-quote" style="font-size: 1.15rem; line-height: 1.8; color: var(--brand-text); max-width: 800px; margin: 0 auto; font-family: var(--font-primary); font-style: normal; opacity: 0.9;">
                Either enquire via the contact form and I confirm availability. Or use the booking form below (without committment) to reserve your date and I will be in touch with you shortly.
            </div>
        </div>
        <?php return ob_get_clean();
    }
}
