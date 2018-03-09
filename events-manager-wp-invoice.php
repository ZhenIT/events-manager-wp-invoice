<?php
/*
Plugin Name: Events Manager wp-invoice bridge
Plugin URI: http://zhenit.com/
Description: Create invoices for booking done in eventsmanager
Version: 1.0
Author: Mikel Martin <mikel@zhenit.com>
Author URI: http://ZhenIT.com/
License: GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  emwi
Domain Path:  /languages
*/
////SETTINGS
define('EMWI_VERSION', '1.0');

////////////
// Exit if accessed directly
if ( ! defined('ABSPATH')) {
    exit;
}

/// Base includes
if(is_admin()){
    include_once("includes/emwi-actions.php");

    add_action('em_enqueue_admin_scripts', function(){
        wp_enqueue_script('emwi', plugins_url('includes/js/emwi.js',__FILE__),array(), EM_VERSION); //jQuery will load as dependency
    });
}

register_activation_hook(__FILE__, 'emwi_activation');
/**
 * Run once on plugin activation
 */
function emwi_activation()
{
    require_once(trailingslashit(plugin_dir_path(__FILE__) . 'includes') . 'class-emwi-installer.php');
    EMWI_Installer::install();

    //emwi_schedule_anything();
}


/**
 * Main EM WI Bridge Loader  Class
 *
 * @since EM_WI_Bridge_Loader 1.0
 */
final class EM_WI_Bridge_Loader
{
    /**
     * @var instance The one true EM_WI_Bridge_Loader instance
     */
    public static $instance;

    /**
     * Start your engines.
     *
     * @since EM_WI_Bridge_Loader 1.0
     *
     * @return void
     */
    public function __construct()
    {
        if(is_admin()){
            if( !function_exists('is_plugin_active') ) {
                include_once( ABSPATH . 'wp-admin/includes/plugin.php' );
            }
            //check that EM is installed
            self::is_em_activated();
            self::is_wi_activated();
        }
        $this->setup_globals();
        $this->includes();
        $this->setup_actions();
    }

    /**
     * Set some smart defaults to class variables. Allow some of them to be
     * filtered to allow for early overriding.
     *
     * @since EM_WI_Bridge_Loader 1.0
     *
     * @return void
     */
    private function setup_globals()
    {
        /** Versions **********************************************************/

        $this->version    = EMWI_VERSION;
        $this->version_db = get_option('emwi_version');
        $this->db_version = '1';

        /** Paths *************************************************************/

        $this->file       = __FILE__;
        $this->basename   = apply_filters('emwi_plugin_basename', plugin_basename($this->file));
        $this->plugin_dir = apply_filters('emwi_plugin_dir_path', plugin_dir_path($this->file));
        $this->plugin_url = apply_filters('emwi_plugin_dir_url', plugin_dir_url($this->file));

        $this->template_url = apply_filters('emwi_plugin_template_url', 'emwi/');

        // Includes
        $this->includes_dir = apply_filters('emwi_includes_dir', trailingslashit($this->plugin_dir . 'includes'));
        $this->includes_url = apply_filters('emwi_includes_url', trailingslashit($this->plugin_url . 'includes'));

        // Languages
        $this->lang_dir = apply_filters('emwi_lang_dir', trailingslashit(dirname($this->basename) . '/languages'));

        /** Misc **************************************************************/
        /**
         * Load emec.
         */
        load_plugin_textdomain('emwi', false, $this->lang_dir);
    }

    /** Private Methods *******************************************************/

    /**
     * Include required files.
     *
     * @since EM_WI_Bridge_Loader 1.0
     *
     * @return void
     */
    private function includes()
    {
        if ( ! class_exists('EM_Events')) {
            return;
        }
        require_once($this->includes_dir . 'class-booking-invoice.php');
        if (is_admin()) {
            require_once($this->includes_dir . 'class-emwi-installer.php');
            do_action('emwi_include_admin_files');
        }
        do_action('emwi_include_files');
    }

    /**
     * Setup the default hooks and actions
     *
     * @since EM_WI_Bridge_Loader 1.0
     *
     * @return void
     */
    private function setup_actions()
    {
        add_action('init', array($this, 'is_em_activated'), 1);
        if ( ! class_exists('EM_Events')) {
            return;
        }

        if (is_admin() && version_compare($this->version, get_option('emwi_version', 0),
                '>')) { //although activate_plugins would be beter here, superusers don't visit every single site on MS
            include_once($this->includes_dir . 'emwi-actions.php');
            add_action('init', array('EMWI_Installer', 'upgrade'), 2);
        }
        do_action('emwi_setup_actions');
    }

    /**
     * Main EM_WI_Bridge_Loaderg Instance
     *
     * Ensures that only one instance of EM_WI_Bridge_Loader exists in memory at any one
     * time. Also prevents needing to define globals all over the place.
     *
     * @since EM_WI_Bridge_Loader 1.0
     *
     * @return The one true EM_WI_Bridge_Loader
     */
    public static function instance()
    {
        if ( ! isset (self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     *
     */
    function is_wi_activated()
    {
        if ( ! function_exists('ud_check_wp_invoice') || ! ud_check_wp_invoice()) {
            if (is_plugin_active($this->basename)) {
                deactivate_plugins($this->basename);
                unset($_GET['activate']); // Ghetto

                add_action('admin_notices', array($this, 'wi_notice'));
                add_action('network_admin_notices', array($this, 'wi_notice'));
            }
        }
    }
    /**
     * Admin notice.
     *
     * @since EM_WI_Bridge_Loader 1.0
     *
     * @return void
     */
    function wi_notice()
    {
        ?>
        <div class="updated">
            <p><?php printf(
                    __('<strong>Notice:</strong> Events Manager Wp invoice bridge requires <a href="%s">wp-invoice</a> in order to function properly.',
                        'emwi'),
                    wp_nonce_url(network_admin_url('update.php?action=install-plugin&plugin=wp-invoice'),
                        'install-plugin_wp-invoice')
                ); ?></p>
        </div>
        <?php
    }

    /**
     * Events Manager
     *
     * @since EM_WI_Bridge_Loader 1.0
     *
     * @return void
     */
    function is_em_activated()
    {
        if ( ! class_exists('EM_Events')) {
            if (is_plugin_active($this->basename)) {
                deactivate_plugins($this->basename);
                unset($_GET['activate']); // Ghetto

                add_action('admin_notices', array($this, 'em_notice'));
                add_action('network_admin_notices', array($this, 'em_notice'));
            }
        }
    }

    /**
     * Admin notice.
     *
     * @since EM_WI_Bridge_Loader 1.0
     *
     * @return void
     */
    function em_notice()
    {
        ?>
        <div class="updated">
            <p><?php printf(
                    __('<strong>Notice:</strong> Events Manager Wp invoice bridge requires <a href="%s">Events Manager</a> in order to function properly.',
                        'emwi'),
                    wp_nonce_url(network_admin_url('update.php?action=install-plugin&plugin=events-manager'),
                        'install-plugin_events-manager')
                ); ?></p>
        </div>
        <?php
    }

    public function __destruct()
    {

    }
}


function emwibridge()
{
    return EM_WI_Bridge_Loader::instance();
}

if (get_option('dbem_rsvp_enabled')) {
    add_action('plugins_loaded', 'emwibridge', 120);
}