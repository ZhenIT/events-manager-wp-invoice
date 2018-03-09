<?php
function emwi_init_actions()
{
    if ( ! empty($_REQUEST['action']) && substr($_REQUEST['action'], 0, 5) == 'emwi_') {
        global $EM_Notices, $EM_Booking;
        //Load the booking object, with saved booking if requested
        $EM_Booking = ( ! empty($_REQUEST['booking_id'])) ? em_get_booking($_REQUEST['booking_id']) : em_get_booking();
        unset($_REQUEST['booking_id']);
        $invoice = EMWI_Booking::create_invoice($EM_Booking);
        if(!$invoice){
            $feedback  = __('Booking already invoiced', 'emwi');
        }else{
            $view_link = '<a class="em-bookings-view-invoice"  target="_blank" href="' . get_invoice_permalink($EM_Booking->booking_meta['wp_invoice']['post_id']) . '">' . __('View invoice',
                    'emwi') . '</a>';
            $feedback  = __('Invoice created.', 'emwi') . ' ' . $view_link;
        }
        emwi_give_ajax_action_feedback($feedback, $EM_Booking);
    }
}

function emwi_give_ajax_action_feedback($feedback, $EM_Booking)
{
    ob_clean();
    header('Content-Type: application/javascript; charset=UTF-8',
        true); //add this for HTTP -> HTTPS requests which assume it's a cross-site request
    echo $feedback;
    ob_flush();
    die();
}

add_action('init', 'emwi_init_actions', 99);