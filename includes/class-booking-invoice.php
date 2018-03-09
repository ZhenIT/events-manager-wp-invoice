<?php

class EMWI_Booking
{

    public static function init()
    {
        if (is_admin()) {
            add_filter('em_bookings_table_booking_actions_1', array(self::class, 'booking_actions'), 1, 2);
            add_filter('em_bookings_table_cols_template', array(
                self::class,
                'emec_bookings_table_cols_template'
            ), 1, 3);
            add_filter('em_bookings_table_rows_col', array(self::class, 'emec_bookings_table_rows_col'), 10, 5);
        }else{
            add_filter('em_my_bookings_booking_actions', array(self::class, 'tmsr_my_bookings_booking_actions'),10,2);

        }
    }

    public static function calculate_price($price, $em_booking)
    {
        if (self::$price_added) {
            return $price;
        }
        foreach ($em_booking->booking_meta['emec_extras'] as $type => $extra) {
            $price += $extra['price'];
        }
        self::$price_added = true;

        return $price;
    }
    public static function tmsr_my_bookings_booking_actions($cancel_link, $EM_Booking){
        if (is_array($EM_Booking->booking_meta['wp_invoice'])) {
            $cancel_link .= '<a class="em-bookings-view-invoice"  target="_blank" href="' . get_invoice_permalink($EM_Booking->booking_meta['wp_invoice']['post_id']) . '">' . __('View invoice',
                    'emwi') . '</a>';
        }
        return $cancel_link;
    }
    public static function booking_actions($booking_actions, $EM_Booking)
    {
        if (is_array($EM_Booking->booking_meta['wp_invoice'])) {
            $booking_actions['view_invoice'] = '<a class="em-bookings-view-invoice"  target="_blank" href="' . get_invoice_permalink($EM_Booking->booking_meta['wp_invoice']['post_id']) . '">' . __('View invoice',
                    'emwi') . '</a>';
        } else {
            $booking_actions['create_invoice'] = '<a class="em-bookings-invoice" href="' . em_add_get_params($EM_Booking->get_event()->get_bookings_url(),
                    array(
                        'action'     => 'emwi_create_invoice',
                        'booking_id' => $EM_Booking->booking_id
                    )) . '">' . __('Create invoice', 'emwi') . '</a>';
        }

        return $booking_actions;
    }

    /*
     * ----------------------------------------------------------
     * Booking Table and CSV Export
     * ----------------------------------------------------------
     */

    public static function create_invoice($EM_Booking)
    {
        if (is_array($EM_Booking->booking_meta['wp_invoice'])) {//Avoid creating multiple invoices for one booking
            return false;
        }
        if ( ! empty($EM_Booking->event_id)) {
            //Load the event object, with saved event if requested
            $EM_Event = $EM_Booking->get_event();
        } elseif ( ! empty($_REQUEST['event_id'])) {
            $EM_Event = new EM_Event($_REQUEST['event_id']);
        }

        //** New Invoice object */
        global $wpi_settings;
        $invoice = new WPI_Invoice();
        //** Load invoice by ID */
        $invoice->create_new_invoice(array(
            'subject' => sprintf(__('Booking ‘%s’', 'emwi'), $EM_Event->post_title)
        ));
        self::emwi_complete_user_data($EM_Booking->person_id);
        $user_data = array(
            'user_id' => $EM_Booking->person_id,
            'email'   => $EM_Booking->person->data->user_email
        );
        $invoice->load_user(apply_filters('emwi_load_user', $user_data, $EM_Booking, $EM_Event));
        $tickets      = $EM_Booking->get_tickets()->tickets;
        $tax_rate     = get_option('dbem_bookings_tax', 0);
        $tax_included = get_option('dbem_bookings_tax_auto_add', 0);
        foreach ($EM_Booking->get_tickets_bookings()->tickets_bookings as $booked_ticket) {
            $line = array(
                'name'     => $tickets[$booked_ticket->ticket_id]->ticket_name,
                'quantity' => $booked_ticket->spaces,
                'price'    => $tax_included ? $booked_ticket->price * (100 / (100 + $tax_rate)) : $booked_ticket->price,
                'tax_rate' => $tax_rate
            );
            $invoice->line_item(apply_filters('emwi_add_invoice_line', $line, $booked_ticket));
        }
        //$invoice->set('subtotal', $EM_Booking->booking_price);
        $invoice_args =
            array(
                'type' => 'invoice',
                'post_date' => date('Y-m-d h:i:s',$EM_Booking->timestamp)
            );
        if ($wpi_settings['increment_invoice_id'] == 'true') {
            $invoice_args['custom_id'] = WPI_Functions::get_highest_custom_id() + 1;
        }
        $invoice->set($invoice_args);
        $invoice = apply_filters('emwi_before_save_invoice', $invoice, $EM_Booking, $EM_Event);
        $invoice->save_invoice();

        $event_note   = sprintf(__('%s paid at booking', 'emwi'),
            WPI_Functions::currency_format(abs($EM_Booking->booking_price), $invoice->data['invoice_id']));
        $event_amount = (float)$EM_Booking->get_total_paid();
        $event_type   = 'add_payment';
        /** Log balance changes */
        $invoice->add_entry("attribute=balance&note=$event_note&amount=$event_amount&type=$event_type");
        $invoice->save_invoice();
        /** ... and mark invoice as paid */
        wp_invoice_mark_as_paid($invoice->data['invoice_id'], $check = true);
        $EM_Booking->booking_meta['wp_invoice'] = array(
            'post_id'    => $invoice->data['ID'],
            'invoice_id' => $invoice->data['invoice_id']
        );
        $EM_Booking->save();
        return $invoice;
    }

    public static function emwi_complete_user_data($id)
    {
        $mapping = apply_filters('emwi_map_billing_info', array(
            //"company_name"  =>'',
            "phonenumber"   => 'dbem_phone',
            "streetaddress" => 'dbem_address',
            "city"          => 'dbem_city',
            "state"         => 'dbem_state',
            "zip"           => 'dbem_zip',
            "country"       => 'dbem_country'
        ), $id);
        foreach ($mapping as $wi_key => $em_key) {
            if ( ! get_user_meta($id, $wi_key, true) && $em_key) {
                update_user_meta($id, $wi_key, get_user_meta($id, $em_key, true));
            }
        }
    }

    function emec_bookings_table_rows_col($value, $col, $EM_Booking, $EM_Bookings_Table, $csv)
    {
        if ($col == 'invoice_id') {
            $value = $EM_Booking->invoice_id;
        }

        return $value;
    }

    function emec_bookings_table_cols_template($cols)
    {
        $cols['invoice_id'] = __('Inovice id', 'emwi');

        return $cols;
    }
}

EMWI_Booking::init();