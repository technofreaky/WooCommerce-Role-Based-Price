<?php
/**
 * Plugin Name:       WooCommerce Role Based Price
 * Plugin URI:        https://wordpress.org/plugins/woocommerce-role-based-price/
 * Description:       Set WooCommerce Product Price Based On User Role
 * Version:           2.1.1
 * Author:            Varun Sridharan
 * Author URI:        http://varunsridharan.in
 * Text Domain:       woocommerce-role-based-price
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * GitHub Plugin URI: @TODO
 */

if ( ! defined( 'WPINC' ) ) { die; }

define('WC_RBP_NAME','WC Role Based Price',true); # Plugin Name
define('WC_RBP_SLUG','wc-role-based-price',true); # Plugin Slug
define('WC_RBP_VERSION','2.1',true); # Plugin Version
define('WC_RBP_PATH',plugin_dir_path( __FILE__ ),true); # Plugin DIR
define('WC_RBP_ADMIN_PATH',WC_RBP_PATH.'admin/',true); # Plugin DIR
define('WC_RBP_ADMIN_CSS',WC_RBP_PATH.'admini/css/'); # Plugin DIR
define('WC_RBP_ADMIN_JS',WC_RBP_PATH.'admini/js/'); # Plugin DIR
define('rbp_key','wc_rbp_'); # PLugin DB Prefix
define('pp_key','wc_rbp'); # PLugin DB Prefix
define('WC_DB_KEY',rbp_key); # Plugin Prefix
define('lang_dom','woocommerce-role-based-price',true); #plugin lang Domain
define('plugin_url',plugins_url('', __FILE__ ));


/**
 * Class to initiate the plugin
 */
final class  WooCommerce_Role_Based_Price{

    private static $_instance = null;
    public static $admin_instance = null;
    private $avaiable_price = array('regular','sale');

    /**
     * Creates or returns an instance of this class.
     */
    public static function get_instance() {
        if ( null == self::$_instance ) {
            self::$_instance = new self;
        }
        return self::$_instance;
    }

    /**
     * Class Constructor
     */
    private function __construct() {
        add_action( 'init', array( $this, 'init' ), 0 );
        $this->load_plugins();
        // Autoload Required Files
        foreach( glob(WC_RBP_PATH . 'includes/*.php' ) as $files ){
            require_once( $files );
        }

        if($this->is_request( 'admin' )){
            require_once(WC_RBP_PATH . 'admin/class-admin-init.php' );
        }

         if($this->is_request( 'frontend' )){
             new front_end_product_pricing;
         }


    }

    public function load_plugins(){
        $plugins = $this->get_activated_plugin();
        if(! empty($plugins)){
            foreach($plugins as $plugin){
                include(WC_RBP_PATH.'plugins/'.$plugin);
            }
        }
    }

    public function is_request( $type ) {
        $is_ajax = defined('DOING_AJAX') && DOING_AJAX;
        switch ( $type ) {
            case 'admin' :
                return ( is_admin() && !$is_ajax ) || ( is_admin() && $is_ajax && isset( $_REQUEST['action'] ));
            case 'frontend' :
                return ! $this->is_request('bot') && ( ! is_admin() || ( ! is_admin() && ! $is_ajax ) ) && ! defined( 'DOING_CRON' );
            case 'bot':
                $user_agent = strtolower ( $_SERVER['HTTP_USER_AGENT'] );
                return preg_match ( "/googlebot|adsbot|yahooseeker|yahoobot|msnbot|watchmouse|pingdom\.com|feedfetcher-google/", $user_agent );
        }
    }

    /**
     * Runs After WP Loaded
     */
    public function init(){
        add_action('plugins_loaded', array( $this, 'langs' ));
        add_filter('load_textdomain_mofile',  array( $this, 'replace_my_plugin_default_language_files' ), 10, 2);

        if($this->is_request( 'admin' )){
            $this->admin_init();
        }
        new WooCommerce_Role_Based_Price_Simple_Product_Functions;
    }

    public function langs(){
        load_plugin_textdomain(lang_dom, false, dirname(plugin_basename(__FILE__)).'/lang/' );
    }

    function replace_my_plugin_default_language_files($mofile, $domain) {
        if (lang_dom === $domain)
            return WC_RBP_PATH.'lang/'.get_locale().'.mo';

        return $mofile;
    }

    /**
     * Inits Admin Class
     */
    public function admin_init(){
        self::$admin_instance = WooCommerce_Role_Based_Price_Admin::get_instance();
    }

    /**
     * Inits Simple Product Function Class
     * @return [[Type]] [[Description]]
     */
    public function sp_function(){
        return WooCommerce_Role_Based_Price_Simple_Product_Functions::get_instance();
    }


    /**
     * Get Registered WP User Roles
     * @return Array
     */
    public function get_registered_roles(){
        $user_roles = get_editable_roles();
        $user_roles['logedout'] = array('name' => 'Visitor / LogedOut User');
        return $user_roles;
    }

	/**
	 * Get Current Logged In User Role
	 * @since 0.1
	 */
	public function current_role(){
		global $current_user;
		$user_roles = $current_user->roles;
		$user_role = array_shift($user_roles);
        if($user_role == null){
            return 'logedout';
        }
		return $user_role;
	}

    /**
     * Returns Admin Class Instance
     */
    public function admin(){
        return self::$admin_instance;
    }

    public function get_option($key){
        return get_option($key);
    }

    /**
     * Returns User Selected / Defined Roles From Settings
     * @return [[Type]] [[Description]]
     */
    public function get_allowed_roles(){
        $db_roles = $this->get_option(rbp_key.'list_roles');
        $registered_roles = $this->get_registered_roles();
        $return_roles = array();
        if(!empty($db_roles)){
            foreach($db_roles as $role){
                if(isset($registered_roles[$role])){
                    $return_roles[$role] = $registered_roles[$role];
                } else {
                    continue;
                }
            }
        } else {
            $return_roles = $registered_roles;
        }
        return $return_roles;
    }

    /**
     * Returns User Selected / Defined Role Price Files From Settings
     * @return [[Type]] [[Description]]
     */
    public function get_allowed_price($price = 'all'){
        $allowed_price = $this->get_option(rbp_key.'allowed_price');

        if(empty($allowed_price)) { $allowed_price = $this->avaiable_price; }

        if($price !== 'all'){
            if(in_array($price, $allowed_price)){
                return true;
            } else {
                return false;
            }
        } else {
            if(!empty($allowed_price)){
                return $allowed_price;
            } else {
                return $this->avaiable_price;
            }
        }


    }

    public function get_mod_name($role_name = ''){
        $name = $this->get_option(rbp_key.'role_name');
        $registered_roles = $this->get_registered_roles();

        if(!empty($name)){
            if(isset($name[$role_name]) && ! empty($name[$role_name])){
                return $name[$role_name];
            } else {
                if(isset($registered_roles[$role_name]['name'])){
                    return $registered_roles[$role_name]['name'];
                }
            }
        }  else {
            if(isset($registered_roles[$role_name]['name'])){
                return $registered_roles[$role_name]['name'];
            }
        }

    }


    public function get_activated_plugin(){
        $plugins = $this->get_option(rbp_key.'activated_plugin');
        return $plugins;
    }

		/**
		 * Indicates if we are processing an Ajax call.
		 *
		 * @return bool
		 * @since 2.1.1
		 * @author Aelia <support@aelia.co>
		 * @link http://aelia.co/about
		 */
		public static function doing_ajax() {
			return defined('DOING_AJAX') && DOING_AJAX;
		}

		/**
		 * Indicates if Aelia Currency Switcher plugin is active.
		 *
		 * @return bool
		 * @since 2.1.1
		 * @author Aelia <support@aelia.co>
		 * @link http://aelia.co/about
		 */
		public static function is_currency_switcher_active() {
			return isset($GLOBALS['woocommerce-aelia-currencyswitcher']) && is_object($GLOBALS['woocommerce-aelia-currencyswitcher']);
		}
}



/**
 * Check if WooCommerce is active
 * if yes then call the class
 */

#add_action( 'init', 'wc_rbp_run' );

#function wc_rbp_run(){
    if(! function_exists('is_plugin_active')){ require_once( ABSPATH . '/wp-admin/includes/plugin.php' ); }

    if (is_plugin_active( 'woocommerce/woocommerce.php' )) {
        if(! function_exists( 'WC_RBP' )){
            function WC_RBP(){ return WooCommerce_Role_Based_Price::get_instance(); }
        }

        $GLOBALS['woocommerce'] = WC_RBP();
        do_action( 'wc_rbp_loaded' );

    } else {
        add_action( 'admin_notices', 'wc_rbp_activate_failed_notice' );
    }
#}

register_activation_hook( __FILE__, 'welcome_screen_activate' );
function welcome_screen_activate() {
    global $wpdb;
    set_transient( 'wc_rbp_welcome_screen_activation_redirect', true, 30 );
}


add_action( 'admin_init', 'welcome_screen_do_activation_redirect' );
function welcome_screen_do_activation_redirect() {
    if ( ! get_transient( 'wc_rbp_welcome_screen_activation_redirect' ) ) { return; }
    delete_transient( 'wc_rbp_welcome_screen_activation_redirect' );
    if ( is_network_admin() || isset( $_GET['activate-multi'] ) ) { return; }
    wp_safe_redirect( add_query_arg( array( 'page' => 'wc-settings','tab' => 'wc_rbp','section'=>'newsletter' ), admin_url( 'admin.php' ) ) );

}

function wc_rbp_activate_failed_notice() {
	echo '<div class="error"><p> '.__('<strong> <i> WooCommerce Role Based Pricing </i> </strong> Requires',lang_dom).'<a href="'.admin_url( 'plugin-install.php?tab=plugin-information&plugin=woocommerce').'"> <strong>'.__(' <u>Woocommerce</u>',lang_dom).'</strong>  </a> '.__(' To Be Installed And Activated',lang_dom).' </p></div>';
}
