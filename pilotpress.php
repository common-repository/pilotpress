<?php
/*
Plugin Name: PilotPress
Plugin URI: https://ontraport.com/
Description: ONTRAPORT WordPress integration plugin.
Version: 2.0.34
Author: ONTRAPORT Inc.
Author URI: https://ontraport.com/
Text Domain: pilotpress
Copyright: 2024, Ontraport
*/

define("JS_DIR", plugin_dir_url(__FILE__) . "js/");

    if(defined("ABSPATH")) {
        global $wp_version;
        if (version_compare($wp_version,"5.9","<="))
        {
            include_once(ABSPATH.WPINC.'/class-http.php');
        }
        else
        {
            include_once(ABSPATH.WPINC.'/class-wp-http.php');
        }
        if (version_compare($wp_version,"3.1","<"))
        {
            include_once(ABSPATH.WPINC.'/registration.php');
        }
        register_activation_hook(__FILE__, "enable_pilotpress");
        register_deactivation_hook(__FILE__, "disable_pilotpress");
        $pilotpress = new PilotPress;
        //create and load up the PilotPress Text Widget statically
        add_action( 'widgets_init',array( 'PilotPress_Widget', 'register' ) );
        //Hook into the admin footer so as to load this JS 
        add_action( 'admin_footer-widgets.php' , "pilotpress_widget_js" );
    }



    class PilotPress {

        const VERSION = "2.0.34";
        const WP_MIN = "3.6";
        const NSPACE = "_pilotpress_";
        const AUTH_SALT = "M!E%VxpKvuQHn!PTPOTohtLbnOl&)5&0mb(Uj^c#Zz!-0898yfS#7^xttNW(x1ia";
        const TTL = 43200; /* 60*60*12 --> 12 hours in seconds */
        const FIVE_MINUTES = 300; //seconds

        public $system_pages = array();

        public static $brand = "ONTRAPORT";
        public static $brand_url = "ontraport.com";
        public static $url_api = "https://api.ontraport.com/pilotpress.php";

        // Will be init on construct, can't use defines + concat here
        public static $path_jqcss;
        public static $path_tjs;
        public static $path_jswpcss;
        public static $path_mrcss;

        //WP post statuses
        public static $valid_state = array(
            "publish",
            "draft"
        );

        /* Used for keeping a record of the current shortcodes to be merged */
        public $shortcodeFields = array();

        /* the various Centers */
        public $centers = array(
            "customer_center" => array(
                "title" => "Customer Center",
                "slug" => "customer-center",
                "content" => "This content will be replaced by the Customer Center"
            ),
            "affiliate_center" => array(
                "title" => "Partner Center",
                "slug" => "partner-center",
                "content" => "This content will be replaced by the Partner Center"
            ),
        );

        /* Various runtime, shared variables */
        private $uri;
        private $metaboxes;
        private $settings;
        private $api_version;
        private $status = 0;
        private $do_login = false;
        private $homepage_url;
        private $incrementalnumber = 1;
        private $tagsSequences;
        private static $stashed_transients = array();

        //Global ppprotect-category reference
        private $ppp;

        function __construct() 
        {
            self::$path_jqcss = JS_DIR . "jquery-ui.css";
            self::$path_tjs = JS_DIR . "tracking.js";
            self::$path_jswpcss = JS_DIR . "moonrayJS-only-wp-forms.css";
            self::$path_mrcss = JS_DIR . "moonray.css";
            // Includes new ppprotect class that has enhanced protections for things like categories etc.
            require_once( plugin_dir_path( __FILE__ ) . 'ppprotect-categories.php');
            $this->ppp = new PPProtect();

            $this->bind_hooks(); /* hook into WP */
            $this->start_session();

            $this->ppp->ppprotectHooks();
            
            /* use this var, it's handy */
            $this->uri = plugins_url('pilotpress', __FILE__);
            
            

            if (get_transient("pilotpress_admin_preview"))
            {
                self::$stashed_transients["pilotpress_admin_preview"] = array(get_transient("pilotpress_admin_preview"));
                delete_transient("pilotpress_admin_preview");
            }
        }
        
        /* this function loads up runtime settings from API or transient caches for both plugin and user (if logged in) */
        function load_settings() {
            global $wpdb;

            $this->system_pages = $this->get_system_pages();

            if(get_transient('pilotpress_cache')) {
                $this->settings = get_transient('pilotpress_cache');
                $this->api_version = get_option("pilotpress_api_version");

                // for debugging
                if(is_file(ABSPATH . "/pp_debug_include.php"))
                {
                    include_once(ABSPATH . "/pp_debug_include.php");
                }

                $this->settings["user"] = $this->get_user_settings();
                $contact_id = $this->get_setting("contact_id", "user");

                if(get_transient("usertags_".$contact_id))
                {
                    $tags = get_transient("usertags_".$contact_id);
                }
                else
                {
                    if (!empty($contact_id))
                    {
                        $tags = $this->api_call("get_contact_tags", array("contact_id" => $contact_id));
                        set_transient('usertags_'.$contact_id, $tags, self::TTL);
                    }
                }

                if(!empty($tags) && is_array($tags["tags"])) {
                    $this->settings["user"]["tags"] = $tags["tags"];
                }

                $this->status = 1;

                if($this->get_setting("usehome")) {
                    $this->homepage_url = home_url();
                } else {
                    $this->homepage_url = site_url();
                }

                $user_info= $this->get_stashed("authenticate_user", true);

                if (isset($user_info["authenticate_user"]) && !is_bool($user_info["authenticate_user"]))
                {
                    $this->ppp->ppprotectSetPPMemLevels($user_info["authenticate_user"]["membership_level"]);
                }
                $this->ppp->ppprotectSetPPSiteLevels($this->get_setting("membership_levels", "oap", true));

            } else {

                $this->settings["wp"] = array();
                $this->settings["wp"]["post_types"] = array();
                $this->settings["wp"]["permalink"] = get_option('permalink_structure');
                $this->settings["wp"]["template"] = get_option('template');
                $this->settings["wp"]["plugins"] = get_option('active_plugins');
                $this->settings["wp"]["post_types"] = get_post_types();

                $this->settings["pilotpress"] = get_option("pilotpress-settings");

                $this->api_version = get_option("pilotpress_api_version");

                if($this->get_setting("usehome")) {
                    $this->homepage_url = home_url();
                } else {
                    $this->homepage_url = site_url();
                }

                $this->settings["pilotpress"]["error_redirect_field"] = 'select-keyvalue';
                $this->settings["pilotpress"]["error_redirect_message"] = "Redirect to THIS page on error.";

                $results = $wpdb->get_results("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_pilotpress_show_in_nav'", ARRAY_A);
                if (is_array($results))
                {
                    foreach($results as $index => $page) {
                        $this->settings["pilotpress"]["show_in_nav"][] = $page["post_id"];
                    }
                }


                if($this->get_setting("api_key") && $this->get_setting("app_id")) {

                    //check if these are stored in the cache first
                    $pilotPressTrackingURL = get_transient("pilotpress_tracking_url");
                    $pilotPressTracking = get_transient("pilotpress_tracking");
                    $getSiteSettings = true;

                    if ($pilotPressTrackingURL !== false && $pilotPressTracking !== false)
                    {
                        $this->settings["oap"]["tracking_url"] = $pilotPressTrackingURL;
                        $this->settings["oap"]["tracking"] = $pilotPressTracking;
                        $getSiteSettings = false;
                    }

                    //Check to make sure we really need to even make this API call...
                    if (is_user_logged_in() || $getSiteSettings )
                    {
                        $contact_id = false;

                        if (isset($_COOKIE["contact_id"]))
                        {
                            $contact_id = self::validatePostVar($_COOKIE["contact_id"], "numeric");    
                        }

                        //Only make use of cookie if not an admin user.
                        if( $contact_id !== false
                            && !current_user_can('manage_options')
                        )
                        {
                            global $current_user;
                            wp_get_current_user();
                            $username = $current_user->user_login;
                            $api_result = $this->api_call("get_site_settings", array("site" => site_url(), "contact_id" => (int) $contact_id, "username" => $username , "version"=>self::VERSION ));
                        }
                        else
                        {
                            $api_result = $this->api_call("get_site_settings", array("site" => site_url() , "version"=>self::VERSION ));
                        }

                        if(is_array($api_result)) 
                        {
                            $this->settings["oap"] = $api_result;

                            if(isset($this->settings["user"])) 
                            {
                                unset($this->settings["user"]);
                            }

                            $this->ppp->ppprotectSetPPMemLevels($api_result["membership_levels"]);
                            $this->ppp->ppprotectSetPPSiteLevels($this->get_setting("membership_levels", "oap", true));

                            set_transient('pilotpress_cache', $this->settings, self::TTL * 2);  //24 hrs

                            $_SESSION["default_fields"] = $this->settings["oap"]["default_fields"];


                            if(isset($api_result["membership_level"])) {
                                $_SESSION["user_levels"] = $api_result["membership_level"];
                                if(!empty($username))
                                {
                                    $_SESSION["user_name"] = $username;
                                }
                            }

                            $this->status = 1;


                            //Lets store the API version into their options table if available
                            if (isset($api_result["pilotpress_api_version"]))
                            {
                                update_option("pilotpress_api_version" , $api_result["pilotpress_api_version"]);
                            }


                            //Cache the tracking link and custom domain so we can avoid calling this every page load
                            if (isset($api_result["tracking_url"]))
                            {
                                set_transient('pilotpress_tracking_url', $api_result["tracking_url"],self::TTL * 2);  //24 hrs
                            }


                            if (isset($api_result["tracking_url"]))
                            {
                                set_transient('pilotpress_tracking', $api_result["tracking"],self::TTL * 2);  //24 hrs
                            }

                        }
                    }
                } else {
                    $this->status = 0;
                }

                $this->settings["user"] = $this->get_user_settings();
                if($this->get_setting("contact_id", "user")) {
                    if(get_transient("usertags_".$this->get_setting("contact_id", "user"))) {
                        $tags = get_transient("usertags_".$this->get_setting("contact_id", "user"));
                    } else {
                        $tags = $this->api_call("get_contact_tags", array("contact_id" => $this->get_setting("contact_id", "user")));
                        set_transient('usertags_'.$this->get_setting("contact_id", "user"), $tags, self::TTL);
                    }
                    if(is_array($tags["tags"])) {
                        $this->settings["user"]["tags"] = $tags["tags"];
                    }
                }
            }
        }

        /* what protocol? */
        static function get_protocol() {
            if(isset($_SERVER["HTTPS"])) {
                if(!empty($_SERVER["HTTPS"])) {
                    return "https://";
                }
            }
            return "http://";
        }
        
        /* add metaboxes to said post types */
        function update_post_types() {

            $exclude = array("attachment","revision","nav_menu_item");
            $array = $this->get_setting("post_types","wp");

            $post_types = get_post_types('','names');
            if (is_array($post_types))
            {
                foreach($post_types as $post_type) {
                    if(!in_array($post_type, $array) && !in_array($post_type, $exclude)) {
                        $array[] = $post_type;
                    }
                }    
            }
            

            $this->settings["wp"]["post_types"] = $array;

        }
        
        function get_setting($key, $type = "pilotpress", $array = false) {
            if(isset($this->settings[$type][$key])) {
                if(!is_array($this->settings[$type][$key]) && $array) {
                    return array($this->settings[$type][$key]);
                } else {
                    return $this->settings[$type][$key];    
                }
            } else {
                if($array) {
                    return array();
                } else {
                    return false;
                }
            }
        }

        /**
         * @brief grab field for shortcode_field
         *
         * @param string $key
         * @return string $field
         */
        function get_field($key)
        {
            $key = $this->undo_quote_escaping($key);

            foreach($this->get_setting("fields", "user", true) as $group => $fields)
            {
                if(isset($fields[$key])) 
                {
                    return $fields[$key];
                }
                else if (isset($fields[html_entity_decode($key,ENT_COMPAT,"UTF-8")]))
                {
                    return $fields[html_entity_decode($key,ENT_COMPAT,"UTF-8")];
                }
                
            }

            foreach($this->get_setting("default_fields", "oap", true) as $group => $fields)
            {
                if(isset($fields[$key])) 
                {
                    return $fields[$key];
                }
                else if (isset($fields[html_entity_decode($key,ENT_COMPAT,"UTF-8")]))
                {
                    return $fields[html_entity_decode($key,ENT_COMPAT,"UTF-8")];
                }
            }

            return "";
        }
        
        function is_setup()
        {
            if($this->status != 0)
            {
                return true;
            }
            else
            {
                return false;
            }
        }
    
        /* this is a fancy getter, for user settings */
        function get_user_settings() {
            $return = array();
            $user_info = $this->get_stashed("authenticate_user", true);
            
            if (isset($user_info["authenticate_user"]['contact_id'])) {
                $return["contact_id"] = $user_info["authenticate_user"]["contact_id"];
            }
                        
            if(isset($user_info["authenticate_user"]["membership_level"])) {
                $return["name"] = $user_info["authenticate_user"]["username"];
                $return["username"] = $user_info["authenticate_user"]["username"];
                $return["nickname"] = $user_info["authenticate_user"]["nickname"];
                $return["levels"] = $user_info["authenticate_user"]["membership_level"];
            }
            return $return;
        }
        
        /* finally some fun: this sets up the admin edit page! */
        function settings_init() {
            
            add_options_page('PilotPress Settings' , 'PilotPress', 'manage_options', 'pilotpress-settings', array(&$this, 'settings_page'));
            register_setting('pilotpress-settings', 'pilotpress-settings', array(&$this, 'settings_validate'));
            
            add_settings_section('pilotpress-settings-general', __('General Settings', 'pilotpress'), array(&$this, 'settings_section_general'), 'pilotpress-settings'); 
            add_settings_field('pilotpress_app_id',   __('Application ID', 'pilotpress'), array(&$this, 'display_settings_app_id'), 'pilotpress-settings', 'pilotpress-settings-general');
            add_settings_field('pilotpress_api_key', __('API Key', 'pilotpress'), array(&$this, 'display_settings_api_key'), 'pilotpress-settings', 'pilotpress-settings-general');
            add_settings_field('wp_userlockout', __('Lock all users without Admin role out of profile editor', 'pilotpress'), array(&$this, 'display_settings_userlockout'), 'pilotpress-settings', 'pilotpress-settings-general');

            add_settings_section('settings_section_oap', __(self::$brand . ' Integration Settings', 'pilotpress'), array(&$this, 'settings_section_oap'), 'pilotpress-settings'); 
            add_settings_field('customer_center',  __('Enable Customer Center', 'pilotpress'), array(&$this, 'display_settings_cc'), 'pilotpress-settings', 'settings_section_oap');
            add_settings_field('affiliate_center',  __('Enable Partner Center', 'pilotpress'), array(&$this, 'display_settings_ac'), 'pilotpress-settings', 'settings_section_oap');
            add_settings_field('center_priority', __('Which center has priority when redirecting?'), array(&$this, 'display_settings_cpriority'), 'pilotpress-settings', 'settings_section_oap');
            add_settings_field('discrete_nickname', __("Enable Discrete Nicknames <br> <br> Uses first part of email address rather than first and last name."), array(&$this, 'display_settings_nicknames'), 'pilotpress-settings', 'settings_section_oap');

            add_settings_section('pilotpress-redirect-display', __('Post Login Redirect Settings', 'pilotpress'), array(&$this, 'settings_section_redirect'), 'pilotpress-settings'); 
            add_settings_field('pilotpress_customer_plr', __('Customers Redirect To', 'pilotpress'), array(&$this, 'display_settings_customer_plr'), 'pilotpress-settings', 'pilotpress-redirect-display');
            add_settings_field('pilotpress_affiliate_plr', __('Partners Redirect To', 'pilotpress'), array(&$this, 'display_settings_affiliate_plr'), 'pilotpress-settings', 'pilotpress-redirect-display');

            //Add the Customer Center Settings
            add_settings_section('pilotpress-customer-center-display', __('Customer Center Settings', 'pilotpress'), array(&$this, 'settings_section_customer_settings'), 'pilotpress-settings'); 
            add_settings_field('pilotpress_customer_center_header_image', __('Custom Header Image', 'pilotpress'), array(&$this, 'display_settings_customer_center_header_image'), 'pilotpress-settings', 'pilotpress-customer-center-display');
            add_settings_field('pilotpress_customer_center_primary_color', __('Primary Color', 'pilotpress'), array(&$this, 'display_settings_customer_center_primary_color'), 'pilotpress-settings', 'pilotpress-customer-center-display');
            add_settings_field('pilotpress_customer_center_secondary_color', __('Secondary (Background) Color', 'pilotpress'), array(&$this, 'display_settings_customer_center_secondary_color'), 'pilotpress-settings', 'pilotpress-customer-center-display');

            //Add the New User Register Settings
            add_settings_section('pilotpress-new-user-display', __('New User Register Settings', 'pilotpress'), array(&$this, 'settings_section_new_user_settings'), 'pilotpress-settings'); 
            add_settings_field('pilotpress_sync_users', __('Sync WordPress users to your ONTRAPORT contacts', 'pilotpress'), array(&$this, 'display_settings_sync_users'), 'pilotpress-settings', 'pilotpress-new-user-display');
            add_settings_field('pilotpress_newly_registered_tags', __('What tags should they have?', 'pilotpress'), array(&$this, 'display_settings_newly_registered_tags'), 'pilotpress-settings', 'pilotpress-new-user-display');
            add_settings_field('pilotpress_newly_registered_sequences', __('What sequences should they be on?', 'pilotpress'), array(&$this, 'display_settings_newly_registered_sequences'), 'pilotpress-settings', 'pilotpress-new-user-display');
            add_settings_field('pilotpress_newly_registered_campaigns', __('What automations should they be on?', 'pilotpress'), array(&$this, 'display_settings_newly_registered_campaigns'), 'pilotpress-settings', 'pilotpress-new-user-display');


            //Add the Logout Settings
            add_settings_section('pilotpress-logout-users-display', __('Logout Settings', 'pilotpress'), array(&$this, 'settings_section_logout_settings'), 'pilotpress-settings'); 
            add_settings_field('pilotpress_logout_users', __('Would you like to keep users logged into your site longer than normal?  <br /> <br /> <i>(*Please note that if the browser is closed for a long period the user will have to log in again.</i>) ', 'pilotpress'), array(&$this, 'display_settings_logout_users'), 'pilotpress-settings', 'pilotpress-logout-users-display');
        

            add_settings_section('pilotpress-settings-advanced', __('Advanced Settings', 'pilotpress'), array(&$this, 'settings_section_advanced'), 'pilotpress-settings'); 
            add_settings_field('pp_sslverify', __('Disable Verify Host SSL', 'pilotpress'), array(&$this, 'display_settings_disablesslverify'), 'pilotpress-settings', 'pilotpress-settings-advanced');
            add_settings_field('pp_use_home', __('Use WordPress URL instead of Site URL', 'pilotpress'), array(&$this, 'display_settings_usehome'), 'pilotpress-settings', 'pilotpress-settings-advanced');         
        }
        
        /* WP is sometimes silly, this is a function to echo a checkbox and have it registered.. annoying but easy */
        function display_settings_cc() {
            echo "<input type='checkbox' name='pilotpress-settings[customer_center]'";
            if($this->get_setting("customer_center")) {
                echo " checked";
            }
            echo ">";
        }
        
        /* ditto */
        function display_settings_ac() {
            echo "<input type='checkbox' name='pilotpress-settings[affiliate_center]'";
            if($this->get_setting("affiliate_center")) {
                echo " checked";
            }
            echo ">";
        }

        /**  
         * @brief output priority redirection settings HTML
         **/
        function display_settings_cpriority()
        {
            $centers_active = array("Partner Center" => $this->get_setting("affiliate_center"), "Customer Center" => $this->get_setting("customer_center"));
            $settings = $this->get_setting("center_priority");
            $incrementer = 1;

            echo "<select name=pilotpress-settings[center_priority]>";
            echo "<option value='0' selected='selected'>Please select one</option>";
            if (is_array($centers_active))
            {
                foreach ($centers_active as $center => $setting)
                {
                    echo "<option value='".$incrementer."' ".selected($settings, $incrementer).">".$center."</option>";
                    $incrementer++;
                }    
            }
            
            echo "</select>";
        }

        /**
         * @brief output discrete nicknames checkbox HTML (OIR-3224)
         */
        function display_settings_nicknames()
        {
            echo "<input type='checkbox' name='pilotpress-settings[discrete_nickname]'";
            if($this->get_setting("discrete_nickname")) {
                echo " checked";
            }
            echo ">";
        }

    
        /* customer center settings */
        function display_settings_customer_plr() {

            $setting = $this->get_setting("pilotpress_customer_plr");
            if(!$setting) {
                $setting = "-1";
            }

            $pages = $this->get_routeable_pages(array("-2"));
            echo "<select name='pilotpress-settings[pilotpress_customer_plr]'>";
            if (is_array($pages))
            {
                foreach($pages as $id => $title) {
                    echo "<option value='{$id}'";
                    if($id == $setting) {
                        echo " selected";
                    }
                    echo ">{$title}</option>";
                }    
            }
            
            echo "</select>";
        }
        
        /* ditto, but for affil center */
        function display_settings_affiliate_plr() {

            $setting = $this->get_setting("pilotpress_affiliate_plr");
            if(!$setting) {
                $setting = "-1";
            }

            $pages = $this->get_routeable_pages(array("-2"));
            echo "<select name='pilotpress-settings[pilotpress_affiliate_plr]'>";
            if (is_array($pages))
            {
                foreach($pages as $id => $title) {
                    echo "<option value='{$id}'";
                    if($id == $setting) {
                        echo " selected";
                    }
                    echo ">{$title}</option>";
                }
            }
            echo "</select>";
        }
        
        /**  @brief settings hook for showing the customer center header image  */
        function display_settings_customer_center_header_image()
        {
            $setting = $this->get_setting("pilotpress_customer_center_header_image");
            if (!$setting){
                $setting = "";
            }

            $output = "<input name='pilotpress-settings[pilotpress_customer_center_header_image]' class='pilotpress_customer_center_header_image_url' type='text' name='header_logo' size='60' value='$setting'>
                <a href='#' class='button pilotpress_header_logo_upload'>Upload</a>";

            echo $output;

        }

        /**  @brief settings hook for showing the customer center primary color */
        function display_settings_customer_center_primary_color()
        {
            $setting = $this->get_setting("pilotpress_customer_center_primary_color");
            if (!$setting){
                $setting = "";
            }
            $output = "<input type='text' name='pilotpress-settings[pilotpress_customer_center_primary_color]' id='primary-color' value='".$setting."' data-default-color='#ffffff' class='pilotpress-color-picker' />";

            echo $output;

        }

        /**  @brief settings hook for showing the customer center secondary (background) color  */
        function display_settings_customer_center_secondary_color()
        {
            $setting = $this->get_setting("pilotpress_customer_center_secondary_color");
            if (!$setting){
                $setting = "";
            }
            $output = "<input type='text' name='pilotpress-settings[pilotpress_customer_center_secondary_color]' id='secondary-color' value='".$setting."' data-default-color='#ffffff' class='pilotpress-color-picker' />";

            echo $output;           
        }

        /** @brief settings hook for showing the various sync_users options (yes --new, yes --new & existing, no) **/
        function display_settings_sync_users()
        {
            $setting = $this->get_setting("pilotpress_sync_users");
            if(!$setting)
            {
                $setting = "-1";
            }
            echo "<select name=pilotpress-settings[pilotpress_sync_users]>";
            echo "<option value='0' ".selected($setting, 0).">No</option>";
            echo "<option value='1' ".selected($setting, 1).">Yes, new users only</option>";
            echo "<option value='2' ".selected($setting, 2).">Yes, new and existing users";
            echo "</select>";
        }

        /** @brief displays the setting for the campaigns that should be added to the new user */
        function display_settings_newly_registered_campaigns()
        {
            $setting = $this->get_setting("pilotpress_newly_registered_campaigns");
            if (!$setting){
                $setting = "-1";
            }
            $output = "<select multiple name=pilotpress-settings[pilotpress_newly_registered_campaigns][]>";
            $campaigns = json_decode($this->tagsSequences["campaigns"] ,true );
            if(is_array($campaigns))
            {
                foreach ($campaigns as $campaign)
                {
                    $selected = "";
                    if(is_array($setting))
                    {
                        if (in_array($campaign['id'], $setting))
                        {
                            $selected = "selected='selected'";
                        }
                    }
                    $output .= "<option value='".$campaign['id']."' ".$selected . ">" .$campaign['name']."</option>";
                }
            }
            $output .= "</select>";
            echo $output; 
        }

        /** @brief displays the setting for the sequences that should be added to the new user */
        function display_settings_newly_registered_sequences() 
        {
            $setting = $this->get_setting("pilotpress_newly_registered_sequences");
            if (!$setting){
                $setting = "-1";
            }
            $output = "<select multiple name=pilotpress-settings[pilotpress_newly_registered_sequences][]>";
            $sequences = json_decode($this->tagsSequences["sequences"] ,true );
            if(is_array($sequences))
            {
                foreach ($sequences as $sequence)
                {
                    $selected = "";
                    if(is_array($setting))
                    {
                        if (in_array($sequence['drip_id'], $setting))
                        {
                            $selected = "selected='selected'";
                        }
                    }
                    $output .= "<option value='".$sequence['drip_id']."' ".$selected . ">" .$sequence['name']."</option>";
                }
            }
            $output .= "</select>";
            echo $output;           
        }

        /** @brief displays the setting for the tags to be added to new users */
        function display_settings_newly_registered_tags() 
        {
            $setting = $this->get_setting("pilotpress_newly_registered_tags");
            if (!$setting){
                $setting = "";
            }
            $output = "<select multiple name=pilotpress-settings[pilotpress_newly_registered_tags][]>";
            $tags = json_decode($this->tagsSequences["tags"] , true );
            if(is_array($tags))
            {
                foreach ($tags as $tag)
                {
                    $selected = "";
                    if(is_array($setting))
                    {
                        if (in_array($tag['tag_name'], $setting))
                        {
                            $selected = "selected='selected'";
                        }
                    }
                    $output .= "<option value='".$tag['tag_name']."' ".$selected . ">" .$tag['tag_name']."</option>";
                }
            }
            $output .= "</select>";
            echo $output;
        }

        /** @brief displays the setting for enabling or disabling logout duration settings */
        function display_settings_logout_users()
        {
            $setting = $this->get_setting("pilotpress_logout_users");
            if (!$setting){
                $setting = "-1";
            }
            echo  "<select name=pilotpress-settings[pilotpress_logout_users]>";
            echo  "<option value='0' ".selected( $setting, 0 ).">No</option>";
            echo  "<option value='1' ".selected( $setting, 1 ).">Yes</option>";
            echo  "</select>";      
        }

        /* section output, blank for austerity */
        function settings_section_customer_settings() {}
        function settings_section_new_user_settings() {}
        function settings_section_logout_settings() {}
        function settings_section_general() {}
        function settings_section_oap() {}
        function settings_section_redirect() {}
        function settings_section_advanced() {
            echo "<span class='pilotpress-advanced-warning'><b>WARNING:</b> these settings affect the core functionality of the PilotPress plugin, proceed with caution.</span>";
        }
    
        /* notices! this is where the magic nags happen */
        function display_notice() {

            global $post, $wp_version;

            if(basename($_SERVER["SCRIPT_NAME"]) == "post.php" && $_GET["action"] == "edit" && in_array($post->ID, $this->system_pages)) {
                echo '<div class="updated"><p>This page is used by the <b>PilotPress</b> plugin. You can edit the content but not delete the page itself.</p></div>';
            }

            if($wp_version < self::WP_MIN) {
                echo '<div class="error" style="padding-top: 5px; padding-bottom: 5px;">';
                _e('PilotPress requires WordPress '.self::WP_MIN.' or higher. Please de-activate the PilotPress plugin, upgrade to WordPress '.self::WP_MIN.' or higher then activate PilotPress again.', 'pilotpress');
                echo '</div>';
            }

            if (!$this->get_setting('api_key') || !$this->get_setting('app_id')) {

                echo '<div class="error" style="padding-top: 5px; padding-bottom: 5px;">';
                _e('PilotPress must be configured with an ' . self::$brand . ' API Key and App ID.', 'pilotpress');

                if($_GET['page'] != 'pilotpress-settings') {
                    _e(sprintf('Go to the <a href="%s" title="PilotPress Admin Page">PilotPress Admin Page</a> to finish setting up your site!', 'options-general.php?page=pilotpress-settings'), 'pilotpress');
                    echo ' ' ;
                    _e(sprintf('You need an <a href="%s" title="Visit '. self::$brand_url .'">' . self::$brand . '</a> account to use this plugin.', 'http://' . self::$brand_url));
                    echo ' ';
                    _e('Don\'t have one yet?', 'pilotpress');
                    echo ' ';
                    _e(sprintf('<a href="%s" title="' . self::$brand . ' SignUp">Sign up</a> now!', 'http://' . self::$brand_url, 'pilotpress'));               
                }

                echo '</div>';
            }

            if(!$this->is_setup() && $this->get_setting('api_key') && $this->get_setting('app_id')) {
                echo '<div class="error" style="padding-top: 5px; padding-bottom: 5px;">';
                _e('Either this site <b>'.str_replace("http://","",(string)site_url()).'</b> is not configured in ' . self::$brand . ' or the <a href="options-general.php?page=pilotpress-settings">API Key / App Id settings</a> are incorrect. ', 'pilotpress');
                _e('Most PilotPress features are disabled until this is configured. Please navigate to the plugin settings to set it up or contact <a href="mailto:support@ontraport.com">support@ontraport.com</a> for assistance.', 'pilotpress');
                echo '</div>';
            }

        }
        
        function display_settings_api_key() {
            ?>
            <input size="50" name="pilotpress-settings[api_key]" id="pilotpress_api_key" type="text" class="code" value="<?php echo $this->get_setting('api_key'); ?>" />
            <?php 
        }

        function display_settings_app_id() {
            ?>
            <input size="50" name="pilotpress-settings[app_id]" id="pilotpress_app_id" type="text" class="code" value="<?php echo $this->get_setting('app_id'); ?>" />
            <?php 
        }

        function display_settings_userlockout() {
            echo "<input type='checkbox' name='pilotpress-settings[wp_userlockout]'";
            if($this->get_setting("wp_userlockout")) {
                echo " checked";
            }
            echo ">";
        }
        
        function display_settings_disablesslverify() {
            echo "<input type='checkbox' name='pilotpress-settings[disablesslverify]'";
            if($this->get_setting("disablesslverify")) {
                echo " checked";
            }
            echo ">";
        }
        
        function display_settings_disableprotected() {
            echo "<input type='checkbox' name='pilotpress-settings[disableprotected]'";
            if($this->get_setting("disableprotected")) {
                echo " checked";
            }
            echo ">";
        }
        
        function display_settings_usehome() {
            echo "<input type='checkbox' name='pilotpress-settings[usehome]'";
            if($this->get_setting("usehome")) {
                echo " checked";
            }
            echo ">";
        }
    
        /* finally, we register the settings page itself. */
        function settings_page() {

            //get the sequences and tags... (and campaigns)
            $this->tagsSequences = $this->api_call("get_tags_sequences", array("site" => site_url()));

            ?>          
            <div class="wrap"><h2><?php _e('PilotPress Settings', 'pilotpress'); ?></h2><?php

            ?><form name="pilotpress-settings" method="post" action="options.php"><?php

            settings_fields('pilotpress-settings');
            do_settings_sections('pilotpress-settings');



            include_once(ABSPATH.'wp-admin/includes/plugin.php');
            if(!is_plugin_active('object-cache.php'))
            {
                echo "<input type='button' class='button-secondary' name='pilotpress-purge' value='Clear PilotPress Cache'></input>
                <p class='pilotpress-advanced-warning'>This will clear out all cached data for all currently logged in users and force PilotPress to go grab the data from ONTRAPORT again.</p>";

                wp_nonce_field( 'pp_purge_transients' , "trans_nonce");
            }
            ?>
                        
            <p class="submit"><input type="submit" class="button-primary" name="save" value="<?php _e('Save Changes', 'pilotpress'); ?>" />&nbsp;<input type="button" class="button-secondary" name="advanced" value="<?php _e('Advanced Settings', 'pilotpress'); ?>"></p></form></div>
            
            <script type="text/javascript">

                jQuery(function($)
                {
                    var purge_btn = jQuery(document).find("[name=pilotpress-purge]");
                    purge_btn.click(function()
                    {
                        var conf = confirm("Are you sure?\nThis will clear out all cached data tied to PilotPress.\n\nYour users will NOT be logged out.");

                        var wpnonceValue = document.querySelector('form[name="pilotpress-settings"] input[name="trans_nonce"]').value;

                        var data = {'action':'purge_transients', 'nonce': wpnonceValue};
                        if(conf == true)
                        {
                            $.post(ajaxurl, data, function(response)
                            {
                                alert("PilotPress cache cleared successfully.");
                            });
                        }   
                    });
                });

                jQuery(document).ready(function() {
                    jQuery(document).find("[name=pilotpress-settings] h3:eq(6)").toggle();
                    jQuery(document).find(".pilotpress-advanced-warning").toggle();
                    jQuery(document).find("[name=pilotpress-purge]").toggle();
                    jQuery(document).find("[name=pilotpress-settings] table:eq(6)").toggle();
                    jQuery(document).find("[name=advanced]").click(function() {
                        jQuery(document).find("[name=pilotpress-purge]").toggle();
                        jQuery(document).find("[name=pilotpress-settings] h3:eq(6)").toggle();
                        jQuery(document).find(".pilotpress-advanced-warning").toggle();
                        jQuery(document).find("[name=pilotpress-settings] table:eq(6)").toggle();
                    });

                    

                    //media uploader
                    jQuery('.pilotpress_header_logo_upload').click(function(e) {
                        e.preventDefault();

                        var custom_uploader = wp.media({
                            title: 'Customer Center Header Image',
                            button: {
                                text: 'Upload Image'
                            },
                            multiple: false  // Set this to true to allow multiple files to be selected
                        })
                        .on('select', function() {
                            var attachment = custom_uploader.state().get('selection').first().toJSON();
                            jQuery('.pilotpress_customer_center_header_image').attr('src', attachment.url);
                            jQuery('.pilotpress_customer_center_header_image_url').val(attachment.url);

                        })
                        .open();
                    });
                //primary color picker init
                jQuery('#primary-color.pilotpress-color-picker').iris();
                jQuery('#primary-color.pilotpress-color-picker').iris({ change: function(event, ui)
                {
                  var colorpickervar = jQuery("#primary-color.pilotpress-color-picker").val()
                  jQuery("#primary-color.pilotpress-color-picker").siblings('.iris-border').css('background-color', colorpickervar);
                }
                });

                //secondary color picker init
                jQuery('#secondary-color.pilotpress-color-picker').iris();
                jQuery('#secondary-color.pilotpress-color-picker').iris({ change: function(event, ui)
                {
                  var colorpickervar = jQuery("#secondary-color.pilotpress-color-picker").val()
                  jQuery("#secondary-color.pilotpress-color-picker").siblings('.iris-border').css('background-color', colorpickervar);
                }
                });
                });

            </script>
            
            <?php
        }
    
        /* use this to validate input, for now it simply creates the pages and/or resets cache */
        function settings_validate($input) {

            if(isset($input["app_id"]))
            {
                $sanitize = sanitize_text_field($input["app_id"]);
                $input["app_id"] = $sanitize;
            }

            if(isset($input["api_key"]))
            {
                $sanitize = sanitize_text_field($input["api_key"]);
                $input["api_key"] = $sanitize;
            }

            if(isset($input["customer_center"])) {
                $this->create_system_page("customer_center");
            } else {
                $this->delete_system_page("customer_center");
            }

            if(isset($input["affiliate_center"])) {
                $this->create_system_page("affiliate_center");
            } else {
                $this->delete_system_page("affiliate_center");
            }

            delete_transient("pilotpress_cache");

            return $input;  
        }
        
        /* OH YEAH! this is the API call method, wraps the static function as some other plugins may call via their own behalf */       
        function api_call($method, $data) {
            return self::api_call_static($method, $data, $this->get_setting("app_id"), $this->get_setting("api_key"), $this->get_setting("disablesslverify"));
        }
        
        /* this is the real function of the above, for errors... try dumping $post */
        static function api_call_static($method, $data, $app_id, $api_key, $ssl_verify = false) {
            
            $post = array('body' => array("app_id" => $app_id, 
                                        "api_key" => $api_key,
                                        "data" => json_encode($data)), 'timeout' => 500);
                                    
            if($ssl_verify) {
                $post["sslverify"] = 0;
            }

            $endpoint = sprintf(self::$url_api.'/%s/%s/%s', "json", "pilotpress", $method);
            $response = wp_remote_post($endpoint, $post);

            if(is_object($response))
            {
                if ($response->errors['http_request_failed']){
                    $endpoint = sprintf(self::$url_api.'/%s/%s/%s', "json", "pilotpress", $method);
                    $response = wp_remote_post($endpoint, $post);
                }
            }


            if(is_wp_error($response) || $response['response']['code'] == 500) {
                return false;
            } else {
                $body = json_decode(trim($response['body']), true);
            }

            if(isset($body["type"]) && $body["type"] == "error") {
                return false;
            } else {
                return $body["pilotpress"];
            }
            
        }
    
        /* all WP binding happens here, mostly. consolidated for your pleasure */
        private function bind_hooks() {

            /* hitup the API or grab transient */
            add_action("init", array(&$this, "load_settings") , 1);
            add_action("init", array(&$this, "load_scripts") , 10);
            add_action('init', array(&$this, "sessionslap_ping"));
            add_action('wp_print_styles', array(&$this, 'stylesheets'));
            add_action('wp_print_footer_scripts', array(&$this, 'tracking'));
            add_action('retrieve_password', array(&$this, 'retrieve_password'));
            add_action('profile_update', array(&$this, 'profile_update'));
            
            add_action("wp_ajax_pp_update_aff_details", array(&$this, 'update_aff_details'));
            add_action("wp_ajax_pp_update_cc_details", array(&$this, 'update_cc_details'));

            if(is_admin()) {
                add_action('admin_menu', array(&$this, 'settings_init'));
                add_filter('admin_init', array(&$this, 'clean_meta'));
                add_filter('admin_init', array(&$this, 'flush_rewrite_rules'));
                add_filter('admin_init', array(&$this, 'user_lockout'));
                add_action('admin_enqueue_scripts', array(&$this, 'admin_load_scripts'));
                add_action('admin_notices', array(&$this, 'display_notice'));

                add_action('admin_menu', array(&$this, 'metabox_add'));
                add_action('pre_post_update', array(&$this, 'metabox_save'));

                add_action('media_buttons', array(&$this, 'media_button_add'), 20);
                add_action('media_upload_forms', array(&$this, 'media_upload_forms'));
                add_action('media_upload_images', array(&$this, 'media_upload_images'));
                add_action('media_upload_videos', array(&$this, 'media_upload_videos'));
                add_action('media_upload_fields', array(&$this, 'media_upload_fields'));    
                add_action('wp_ajax_pp_insert_form', array(&$this, 'get_insert_form_html'));
                add_action('wp_ajax_pp_insert_video', array(&$this, 'get_insert_video_html'));
                add_action("wp_ajax_pp_get_aff_report", array(&$this, 'get_aff_report'));
                
                add_filter('tiny_mce_before_init', array(&$this, 'mce_valid_elements'));
                add_filter('tiny_mce_version', array(&$this, 'tiny_mce_version') );
                add_filter("mce_external_plugins", array(&$this, "mce_external_plugins"));
                add_filter('mce_buttons_3', array(&$this, 'mce_buttons'));
                add_action('admin_footer', array(&$this, 'grab_mce_fields'));
                add_action('admin_footer', array(&$this, 'grab_mce_shortcodes'));

                add_filter('manage_posts_columns', array(&$this, 'page_list_col'));
                add_action('manage_posts_custom_column', array(&$this, 'page_list_col_value'), 10, 2);
                add_filter('manage_pages_columns', array(&$this, 'page_list_col'));
                add_action('manage_pages_custom_column', array(&$this, 'page_list_col_value'), 10, 2);
                add_filter('user_has_cap', array(&$this, 'lock_delete'), 0, 3);
                add_filter('media_upload_tabs', array(&$this, 'modify_media_tab'));
                add_action('wp_loaded', array(&$this, 'update_post_types'));

                // For login_form
                add_action('admin_head', array(&$this, 'include_form_admin_options'));
                add_action('admin_head', array(&$this, 'admin_preview'));

                add_action('wp_ajax_purge_transients', array(&$this, 'purge_transients'));
                add_action('wp_ajax_admin_preview_redirect', array(&$this, 'admin_preview_redirect'));

                
                // add_action('admin_print_footer_scripts', array(&$this, 'tinymce_autop'), 50);

            } else {
                add_filter('rewrite_rules_array', array(&$this, 'filter_rewrite_rules'));
                add_action('wp', array(&$this, 'post_process'));
                add_filter('get_pages', array(&$this, 'get_pages'));
                add_filter("wp_nav_menu", array(&$this, 'get_nav_menus'));
                add_filter("wp_nav_menu_objects", array(&$this, 'get_nav_menu_objects'));
                add_filter('posts_where', array(&$this, 'posts_where'));
                add_filter('query_vars', array(&$this, 'filter_query_vars'));
                add_filter('the_content', array(&$this, 'content_process'));
                add_filter('login_message', array(&$this, 'content_process'));
                
                add_shortcode('protected', array(&$this, 'shortcode_show_if'));
                add_shortcode('show_if', array(&$this, 'shortcode_show_if'));
                add_shortcode('login_page', array(&$this, 'login_page'));
                add_shortcode('field', array(&$this, 'shortcode_field'));

                add_shortcode('pilotpress_protected', array(&$this, 'shortcode_show_if'));
                add_shortcode('pilotpress_show_if', array(&$this, 'shortcode_show_if'));
                add_shortcode('pilotpress_login_page', array(&$this, 'login_page'));
                add_shortcode('pilotpress_field', array(&$this, 'shortcode_field'));
                add_shortcode('pilotpress_sync_contact', array(&$this, 'shortcode_sync_contact'));
            }

            add_action('wp_authenticate', array(&$this, 'user_login'), 1, 2);
            add_action("wp_login_failed", array(&$this, 'user_login_failed'));
            add_action("lostpassword_post", array(&$this, 'user_lostpassword'));
            add_action('wp_logout', array(&$this, 'user_logout'));
            add_action('init', array(&$this, 'pp_login_button'));
            add_action('user_register', array(&$this, 'add_new_register_user_to_ONTRAPORT') , 10, 1);

        }

        /**
         * @brief echoes the necessary JS to produce the various buttons, and redirection for the admin preview functionality 
         **/
        function admin_preview()
        {
            global $post;

            if (is_object($post) && $post->ID)
            {
                // CSS so buttons aren't smooshed together.
                echo "<style type='text/css'>";
                echo ".admin-preview";
                echo "{margin:5px!important;font-size:110%!important;}";
                echo "</style>";

                // Only allow post previews if they're published or drafts.
                if (in_array(get_post_status($post->ID), self::$valid_state))
                {
                    echo "<script type='text/javascript'>"; 
    
                    // Grabs $_GET args.
                    echo "function getQueryStringValue (key) {  
                        return decodeURIComponent(window.location.search.replace(new RegExp('^(?:.*[&\\?]' + encodeURIComponent(key).replace(/[\.\+\*]/g, '\\$&') + '(?:\\=([^&]*))?)?.*$', 'i'), '$1'));  
                    }";
    
                    // Open new tab with newly set preview-status.
                    echo "
                        jQuery(function($)
                        {
                            $('.admin-preview').click(function(event)
                            {
                                event.preventDefault();
                                var post = getQueryStringValue('post');
                                var data = {'action':'admin_preview_redirect', 'value':$(this).attr('value'), 'post':post};
                                $.post(ajaxurl, data, function(response)
                                {
                                    var url = $.parseJSON(response);
                                    window.open(url['data'], '_blank');
                                }); 
                            });
                        });";
                    echo "</script>";
                }
                else
                {
                    // Disable preview buttons
                    echo "<script type='text/javascript'>"; 
                    echo "jQuery(function($)
                        {
                            $('.admin-preview').prop('disabled', true);
                        });";
                    echo "</script>";
                }
            }
            
            // If not on a page w/ a post.. do nothing.
        }

        /** 
         * @brief sets transient of the admin_preview's selected preview level
         * @return (echoes) json encoded URL of the chosen post back to the JS for redirection
         **/
        function admin_preview_redirect()
        {
            set_transient("pilotpress_admin_preview", self::validatePostVar($_POST['value'], "string"), self::TTL);
            $data = array('data' => get_permalink(self::validatePostVar($_POST["post"], "numeric")));
            echo json_encode($data);
            wp_die(); 
        }


        /**
         * @brief gets transient data related to the user or site. If not available -- call the API and make it
         * @params string $name, bool $unique(used to differentiate whether to grab site settings or user data)
         * @return array of various data
         **/
        function get_stashed($name, $unique)
        {

            $user = wp_get_current_user();

            $api_call_args = array();
            $suffix = $unique ? "_pilotpress_user".$user->ID : "_pilotpress_site";


            //try to grab transient from stash, return if success
            if(isset(self::$stashed_transients[$name.$suffix]))
            {
                return self::$stashed_transients[$name.$suffix];
            }

            //not in stash, build API call
            //load args array, if not admin, pass in username
            $api_call_args["site"] = site_url();
            $api_call_args["version"] = self::VERSION;
            $api_call_args["disablesslverify"] = $this->get_setting("disablesslverify");
            $api_call_args["app_id"] = $this->get_setting("app_id");
            $api_call_args["api_key"] = $this->get_setting("api_key");

            if(!$this->is_site_admin())
            {
                $api_call_args["username"] = $user->user_login;
                if($name == "authenticate_user" && (!isset($api_call_args["username"]) || $api_call_args["username"] == null))
                {
                    return array();
                }
            }


            if ($name == "authenticate_user" && $user->ID > 0) //$user->ID = 0 when not logged in, don't want to resync them
            {
                // Need to bypass password check as this is not a log in call but a re sync...
                $api_call_args["resync_user"] = true;
            }

            //build or grab transient from DB, stash & return it
            $transient = self::get_stashed_static($name, $unique, $api_call_args);
            self::$stashed_transients[$name.$suffix] = $transient;

            return self::$stashed_transients[$name.$suffix];
        }

        /**
         * @brief takes in name of API call, a unique flag and API call args --> sets result as a transient
         * @params string $name, bool $unique, array $data
         * @return array of data from API call
         **/
        static function get_stashed_static($name, $unique, $data)
        {
            $options = get_option("pilotpress-settings");
            $suffix = $unique ? "_pilotpress_user".get_current_user_id() : "_pilotpress_site";

            //try to grab from DB
            $return = get_transient($name.$suffix);
            
            //we got it!
            if($return)
            {
                return $return;
            }

            //prep args for API call
            $data["site"] = site_url();
            $data["version"] = self::VERSION;
            if(array_key_exists("disablesslverify", $data))
            {
                $options["disablesslverify"] = $data["disablesslverify"];
                unset($data["disablesslverify"]);
            }
            if(array_key_exists("api_key", $data))
            {
                $options["api_key"] = $data["api_key"];
                unset($data["api_key"]);
            }
            if(array_key_exists("app_id", $data))
            {
                $options["app_id"] = $data["app_id"];
                unset($data["app_id"]);
            }
            
            $return[$name] = self::api_call_static($name, $data, $options["app_id"], $options["api_key"], $options["disablesslverify"]);

            if ($return[$name])
            {
                $return["timestamp"] = time();
                set_transient($name.$suffix, $return, self::TTL);
            }

            return $return;
        }

        /**
         * @brief cleans up all transients associated with the current user
         **/
        function destroy_transients_logout()
        {
            $suffix = "_pilotpress_user".get_current_user_id();
            unset($stashed_transients);
            delete_transient("authenticate_user".$suffix);

            $contact_id = self::validatePostVar($_COOKIE["contact_id"], "numeric");
            delete_transient("login_url_pilotpress_user".(int) $contact_id);
            delete_transient("pilotpress_redirect_to".(int) $contact_id);
            delete_transient("usertags_".(int) $contact_id);
        }

        /**
         * @brief deletes all PilotPress related transients from the DB, triggered by a button on the admin settings page
         * @return error log back to the JS on the Front End
         **/
        function purge_transients()
        {
            global $wpdb;

            if (!is_user_logged_in())
            {
                return;
            }

            if (!current_user_can("manage_options"))
            {
                return;
            }

            if (!check_ajax_referer("pp_purge_transients", "nonce"))
            {
                return;
            }


            $error_log = array();
            $user = "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '\_transient\_%pilotpress\_%'";
            $tags = "DELETE FROM {$wpdb->prefix}options WHERE option_name LIKE '\_transient\_%usertags\_%'";
            try
            {
                $user_results = $wpdb->get_results($user);
                $tag_results = $wpdb->get_results($tags);
            }
            catch (Exception $e)
            {
                $error_log["error"] = "error during clearing";
            }

            if(empty($error_log))
            {
                $error_log["data"] = "success";
            }
            
            echo json_encode($error_log);

            wp_die();
        }

        /**
         * @brief translates the redirect transient into a URL
         * @param int $contact_id from $_COOKIE["contact_id"]
         *
         * @return string URL or false if no transient or bad type
         * 
         * @author Richard Young <ryoung@ontraport.com>
         **/
        function getRedirectURL($contact_id)
        {
            if (!isset($contact_id) || $contact_id === false)
            {
                return false;
            }

            $transient = get_transient("pilotpress_redirect_to".$contact_id);

            if($transient)
            {
                $transient = explode("_", $transient);
                $type = $transient[0];
                $id = $transient[1];

                switch($type)
                {
                    case "post":
                        $redirect_to = get_permalink($id);
                        break;

                    case "category":
                        $redirect_to = get_category_link($id);
                        break;

                    default:
                        return false;
                }

                return $redirect_to;
            }
            else
            {
                return false;
            }
        }

        /**
         * @brief checks whether or not the user passed in has administrator priveleges
         * @params WP_User $user
         * @return bool
         **/
        function is_site_admin($user = false)
        {
            if($user == false || ($user == true && !is_array($user->roles)))
            {
                $user_roles = wp_get_current_user()->roles;
                if (is_array($user_roles))
                {
                    return in_array('administrator', $user_roles);
                }
            }
            else
            {
                return in_array('administrator', $user->roles);
            }
        }

        
        function retrieve_password($name) {
            if(!isset($name) || $name == null)
            {
                return;
            }
            $return = $this->api_call("retrieve_password", array("site" => site_url(), "username" => $name));
        }
        
        /* update a persons profile */
        function profile_update($user_id) {
            if(isset($_POST['first_name']) && isset($_POST['last_name']) && isset($_POST['nickname']) && isset($_POST['pass1'])) {
                $user = get_userdata($user_id);
                
                $details = array();
                $details["site"] = site_url();
                $details["username"] = $user->user_login;
                $details["firstname"] = self::validatePostVar($_POST['first_name'], "string");
                $details["lastname"] = self::validatePostVar($_POST['last_name'], "string");
                $details["nickname"] = self::validatePostVar($_POST['nickname'], "string");
                $details["password"] = self::validatePostVar($_POST["pass1"], "string");


                $this->destroy_transients_logout();
                $return = $this->api_call("profile_update", $details);
            }
        }

        function user_lockout() {
            global $current_user;
            if(!current_user_can('manage_options') && $this->get_setting("wp_userlockout") && !isset($_POST["action"])) 
            {
                if (function_exists("wp_doing_ajax") && wp_doing_ajax())
                {
                    return;
                }
                
                $customer = $this->get_setting("pilotpress_customer_plr");
                if(!empty($customer) && $customer != "-1") {
                    self::redirect(get_permalink($customer));
                } else {
                    self::redirect($this->homepage_url);
                }
                die;
            }
        }

        /* please load scripts here vs. printing. it's so much healthier */
        function load_scripts() {
            wp_enqueue_script("jquery");
            wp_register_script("mr_tracking", self::$path_tjs, array('jquery'));
            wp_enqueue_script("mr_tracking");
        }

        /*
            @brief only load these scripts if in the admin dashboard

        */
        function admin_load_scripts() 
        {
            // Here to determine if the automattic color picker 'iris' is included with wordpress... if not, include and use it
            $version = get_bloginfo('version');
            if ($version < 3.5)
            {
                wp_register_style('irisstyle', plugins_url( '/js/iris.css' , __FILE__ )); 
                wp_enqueue_style('irisstyle');
                wp_register_style('jquery-ui', JS_DIR . "jquery.ui.all.css");
                wp_enqueue_style('jquery-ui');

                wp_deregister_script('jquery-color');
                wp_register_script('jquery-color', plugins_url( 'color.js' , __FILE__ ));
                wp_enqueue_script('jquery-color');
                wp_enqueue_script('jquery-ui-core');
                wp_enqueue_script('jquery-ui-draggable');
                wp_enqueue_script('jquery-ui-slider');
                wp_enqueue_script('jquery-ui-widget');
                wp_enqueue_script('jquery-ui-mouse');
                wp_enqueue_script('jquery-ui-tabs');
                wp_register_script('iris', plugins_url( '/js/iris.js' , __FILE__ ), array( 'jquery', 'jquery-color', 'jquery-ui-core', 'jquery-ui-widget', 'jquery-ui-draggable', 'jquery-ui-slider', 'jquery-ui-mouse', 'jquery-ui-tabs' )); 
                wp_enqueue_script('iris'); 
            }
            else
            {
                wp_register_style('jquery-ui', JS_DIR . "jquery.ui.all.css");
                wp_enqueue_style('jquery-ui');
                wp_enqueue_script('jquery-ui-tabs');
                wp_enqueue_style( 'wp-color-picker' );
                wp_enqueue_script('iris'); 
            }
            if(function_exists( 'wp_enqueue_media' )){
                wp_enqueue_media();
            }else{
                wp_enqueue_style('thickbox');
                wp_enqueue_script('media-upload');
                wp_enqueue_script('thickbox');
            }

        }
        
        function stylesheets() {
            wp_register_style("mrjswp", self::$path_jswpcss);
            wp_enqueue_style("mrjswp");

            wp_register_style("mrcss", self::$path_mrcss);
            wp_enqueue_style("mrcss");

            wp_register_style("jqcss", self::$path_jqcss);
            wp_enqueue_style("jqcss");
        }
    
        /* except this one. */
        function tracking() {
            echo "<script>_mri = \"".$this->get_setting('tracking','oap')."\";_mr_domain = \"" . $this->get_setting('tracking_url', 'oap') . "\"; mrtracking();</script>";
        }
    
        /* first of a few tinymce functions, this registers some of our buttons */
        function mce_buttons($buttons) {
            array_push($buttons, "separator", "merge_fields");
            array_push($buttons, "separator", "short_codes");
            return $buttons;
        }
        
        /* load up our marshalled plugin code (see comment prefixed: Xevious) */
        function mce_external_plugins($plugin_array) {
            global $wp_version;
            $version = 3.9;
            //test for wordpress version to load proper plugin scripts
            if ( version_compare( $wp_version, $version, '>=' ) ) {
                $plugin_array['pilotpress'] = plugins_url('js/', __FILE__) . 'pilotpress_mce_plugin.js';
            } 
            else 
            {
                $plugin_array['pilotpress']  =  plugins_url('js/', __FILE__) . 'pilotpress_mce_plugin_old.js';
            }
            return $plugin_array;
        }
    
        /* i forget what this did, but it is important */
        function tiny_mce_version($version) {
            return ++$version;
        }
    
        /* right so... lets just make most useful elements avaliable */
        function mce_valid_elements($in) {
            $em = '#p[*],p[*],form[*],div[*],span[*],script[*],link[*]';
            
            if(!is_array($in)) 
            {
                $in = array();
            }
            
            if(isset($in["extended_valid_elements"])) 
            {
                $in["extended_valid_elements"] .= ',';
                $in["extended_valid_elements"] .= $em;
            } else {
                $in["extended_valid_elements"] = $em;
            }

            if (isset($in['valid_children'])) 
            {
                $in['valid_children'] .= ',+body[link]';
            }
            else 
            {
                $in['valid_children'] = '+body[link]';
            }
            
            $in["entity_encoding"] = "raw";         
            
            return $in;
        }
    
        /* horrible but it gets the job done. WP said they'd fix this in 3.3, but they lied */
        function lock_delete($allcaps, $caps, $args) {

            global $wp_post;

            if(is_array($this->system_pages)) {
                if(isset($_GET["post"])) {
                    if(in_array($_GET["post"], $this->system_pages)) {
                        if(is_array($allcaps)) {
                            foreach($allcaps as $cap => $value) {                   
                                if(strpos($cap, "delete") !== false) {
                                    $allcaps[$cap] = 0;
                                }
                            }
                        }
                    }
                }
            }
            return $allcaps;
        }
    
        /* adds a column to the post list view */
        function page_list_col($cols) {
            $_cols = array();
            if(is_array($cols)) {
                foreach($cols as $col => $value) {
                    //need both terms in case date is loaded before author -- don't want to set twice
                    if(!isset($_cols["pilotpress"]) && $col == "author") {
                        $_cols["pilotpress"] = "PilotPress Levels";
                    }
                    else if(!isset($_cols["pilotpress"]) && $col == "date")
                    {
                        $_cols["pilotpress"] = "PilotPress Levels";
                    }     
                    $_cols[$col] = $value;
                }
                if(!isset($_cols["pilotpress"]))
                {
                    $_cols["pilotpress"] = "PilotPress Levels";
                }
            }
            return $_cols;
        }
    
        /* prints value of above */
        function page_list_col_value($column_name, $id) {           
            if ($column_name == "pilotpress") {
                if(in_array($id, $this->system_pages)) {
                    echo '<img src="https://optassets.ontraport.com/opt_assets/images/pilot_press/lock-icon-pp.png" width="16" height="16" alt="Locked" />&nbsp;System';
                } else {
                    $levels = get_post_meta($id, self::NSPACE.'level', false);                      
                    if(!empty($levels)) {
                        if(count($levels) == 1) {
                            echo $levels[0];
                        } else {
                            echo implode(', ', $levels);
                        }
                    } 
                    else if ( $catLevels = $this->ppp->ppprotectCheckForProtection( $id ) ) {
                        echo 'Category Protection - ' . $catLevels;
                    }
                    else {
                        echo '(not set)';
                    }
                }
            }
        }
    
        /* handy ajax call for Affiliate Center */
        function get_aff_report() {
            $return = $this->api_call("get_aff_report", $_POST);
            echo($return["report"]);
            die();
        }
    
        /* same but for aff details (setter) */
        function update_aff_details() {
            $return = $this->api_call("update_aff_details", $_POST);
            $this->destroy_transients_logout();
            
            echo($return["update"]);
            die();
        }
    
        /* same but for cc details (setter) */
        function update_cc_details() {
            global $wpdb;

            if(wp_verify_nonce($_POST['nonce'], basename(__FILE__))) {
                
                $data = $_POST;
                $data["site"] = site_url();

                $return = $this->api_call("update_cc_details", $data);

                if( (self::validatePostVar($_POST["oguser"],"string") != self::validatePostVar($_POST["username"], "string")) && 
                    username_exists(self::validatePostVar($_POST["username"], "string")) || 
                    ( 
                        array_key_exists("username_exists",$return) &&
                        $return["username_exists"]  
                    )
                )
                {
                    echo "display_notice('Error: That username is taken. Please try another username.');";
                    die();
                }           
                    
                $current_user = wp_get_current_user();
                
                if(isset($return["updateUser"])) 
                {
                    $wpdb->query($wpdb->prepare("UPDATE {$wpdb->users} SET `user_login` = %s WHERE `ID` = %d", self::validatePostVar($_POST['username'], "string"), $current_user->ID));

                    if(self::validatePostVar($_POST["nickname"], "string") == self::validatePostVar($_POST["oguser"], "string")) 
                    {
                        wp_update_user(array("ID" => $current_user->ID, "nickname" => self::validatePostVar($_POST["username"], "string"), "display_name" => self::validatePostVar($_POST["username"], "string")));
                    }
                }
                else {
                    wp_update_user(array("ID" => $current_user->ID, "user_pass" => self::validatePostVar($_POST["password"], "string")));
                }

                $this->destroy_transients_logout();
                echo($return["update"]);
                die();
            }
        }
    
        /* grabs form insert code, disables that pesky wpautop */
        function get_insert_form_html(){
            if(isset($_POST["form_id"])) {
                remove_filter('the_content', 'wpautop');
                $api_result = $this->api_call("get_form", array("form_id" => self::validatePostVar($_POST["form_id"], "numeric")));
                echo html_entity_decode($api_result["code"],ENT_COMPAT,"UTF-8");
                die;
            }
        }
    
        /* grabs video code */
        function get_insert_video_html(){
            if((bool) ($video_id = self::validatePostVar($_POST["video_id"], "numeric")) === true)
            {
                $player_selection = self::validatePostVar($_POST["use_player"], "numeric");
                $use_autoplay = self::validatePostVar($_POST["use_autoplay"], "numeric");
                $use_viral = self::validatePostVar($_POST["use_viral"], "numeric");
                $omit_flowplayer = self::validatePostVar($_POST["omit_flowplayerjs"], "boolean");

                $api_result = $this->api_call("get_video", array(
                    "video_id" => $video_id,
                    "width" => '480',
                    "height" => "320",
                    "player" => $player_selection,
                    "autoplay" => $use_autoplay,
                    "viral" => $use_viral,
                    "omit_flowplayerjs" => $omit_flowplayer
                ));
                echo $api_result["code"];
                die;
            }
        }

        /**
         * @param mixed $post_var
         * @param string $type
         * 
         * @return mixed|bool false if $post_var is not valid given $type
         * 
         * @author Richard Young <ryoung@ontraport.com>
         */
        public static function validatePostVar($post_var, $type)
        {
            $valid = false;
            if (isset($post_var))
            {
                switch($type)
                {
                    case "string":
                        $valid = is_string($post_var) ? sanitize_text_field($post_var) : false;
                    break;
                    case "numeric":
                        $valid = is_numeric($post_var) ? $post_var : false;
                    break;
                    case "boolean":
                        $valid = is_bool($post_var) ? $post_var : false;
                    break;
                }
            }
            return $valid;
        }

        /* does media insert form itself*/
        function media_upload_type_forms() {

            global $wpdb, $wp_query, $wp_locale, $type, $tab, $post_mime_types;

            media_upload_header();

            ?>
            <script type="text/javascript">

                var $ = jQuery;
                
                function insertForm(the_form_id) {
                    
                    $.post("<?php echo $this->homepage_url; ?>/wp-admin/admin-ajax.php", { action:"pp_insert_form", form_id: the_form_id, 'cookie': encodeURIComponent(document.cookie) },
                     function(str){
                        
                        if(typeof top.tinyMCE != 'undefined' && (ed = top.tinyMCE.activeEditor)) {

                            ed = top.tinyMCE.activeEditor;
                            ed.focus();

                            if(top.tinymce.isIE) {
                                ed.selection.moveToBookmark(top.tinymce.EditorManager.activeEditor.windowManager.bookmark);
                            }

                            ed.execCommand('mceInsertContent', false, str);
                            top.tb_remove();
                        } else {
                            top.send_to_editor(str);
                            top.tb_remove();
                        }

                    });
                }


            </script>                   
            <?php

            $forms_list = $this->api_call("get_form_list","");
            if(is_array($forms_list)) {
                foreach($forms_list as $group => $forms) {
                    natcasesort($forms);
                    echo "<div style='padding: 5px; line-height: 16px;'>";
                    echo "<h2>{$group}</h2>";
                    if(is_array($forms)) {
                        echo "<ul style='padding-left: 20px; list-style-type: disc !important;'>";    
                        foreach($forms as $idx => $name) {                  
                            echo "<li><b><a href='JavaScript:insertForm({$idx});' title='form_{$idx}'>{$name}</a></b></li>";
                        }
                        echo "</ul>";    
                    }
                    echo "</div>";
                    echo "<hr>";
                }
            }

        }
    
    
        /* same but for videos */
        function media_upload_type_videos() {
            media_upload_header();

            $api_result = $this->api_call("get_video_list","");

            ?>

            <style type="text/css">
            div.img
            {
              background: #EFEFEF;
              margin:2px;
              border:1px solid #CCC;
              height:auto;
              width:auto;
              float:left;
            }
            div.img img
            {
              display:inline;
              margin:3px;
              border:1px solid #ffffff;
            }
            div.desc
            {
              font-size: 10px;
              width:200px;
              margin:2px;
            }
            div.controls
            {
              font-size: 10px;
            }
            div.control_button img {
                padding: 0px;
                margin: 0px;
            }
            div.control_button {
                padding: 0px;
                margin: 0px;
                border: 1px solid #CCC;
            }
            </style>

            <script>
                var $ = jQuery;

                function toggle_autoplay(the_video_id) {
                    if($('#autoplay_'+the_video_id).val() != 0) {
                        $('#autoplay_'+the_video_id).val(0);
                        $('#autoplaybtn_'+the_video_id).css('background-color','#EEE');
                    } else {
                        $('#autoplay_'+the_video_id).val(1);
                        $('#autoplaybtn_'+the_video_id).css('background-color','#CCC');
                    }
                }
                
                function toggle_viral(the_video_id) {
                    if($('#viral_'+the_video_id).val() != 0) {
                        $('#viral_'+the_video_id).val(0);
                        $('#viralbtn_'+the_video_id).css('background-color','#EEE');
                    } else {
                        $('#viral_'+the_video_id).val(1);
                        $('#viralbtn_'+the_video_id).css('background-color','#CCC');
                    }
                }

                function insertVideo(the_video_id) {
                    
                    var player = $('#player_'+the_video_id).val();
                    var autoplay = $('#autoplay_'+the_video_id).val();
                    var viral = $('#viral_'+the_video_id).val();
                    var omit_flowplayerjs = false;

                    if($("#wpwrap", top.document).val().indexOf("oap_flow/flowplayer") !== -1) {
                        omit_flowplayerjs = true;
                    }

                    $.post("<?php echo $this->homepage_url; ?>/wp-admin/admin-ajax.php", { action: "pp_insert_video", video_id: the_video_id, use_viral: viral, use_player: player, use_autoplay: autoplay, 'cookie': encodeURIComponent(document.cookie), "omit_flowplayerjs": omit_flowplayerjs },
                     function(str){                     
                        var ed;
                        if(typeof top.tinyMCE != 'undefined' && (ed = top.tinyMCE.activeEditor)) {

                            ed = top.tinyMCE.activeEditor;
                            ed.focus();

                            if(top.tinymce.isIE) {
                                ed.selection.moveToBookmark(top.tinymce.EditorManager.activeEditor.windowManager.bookmark);
                            }

                            ed.execCommand('mceInsertContent', false, str);
                            top.tb_remove();
                        } else {
                            top.send_to_editor(str);
                            top.tb_remove();
                        }

                    });
                }
            </script>

            <?php

            if(is_array($api_result["list"]) && count($api_result["list"]) > 0) {
                echo "<div style='padding: 5px; line-height: 16px;'>";
                echo "<h2>Videos</h2>";
                if (is_array($api_result["list"]))
                {
                    foreach($api_result["list"] as $video) {

                        if(empty($api_result["thumb_url"]) OR $api_result["thumb_url"] == "") {
                            $thumb = $api_result["default_thumb"];
                        } else {
                            $thumb = $api_result["thumb_url"].$video["thumb_filename"];
                        }

                        echo "<div class='img' style=\"cursor: pointer;\"><div onClick='insertVideo({$video["video_id"]})'><img width='200' src='{$thumb}'></div>";
                        echo "<div class='desc'>{$video["name"]} <span>({$video["duration"]})</span></div>";
                        echo "<table><tr><td><select id='player_{$video["video_id"]}' name='player_{$video["video_id"]}'><option value='4' selected>HTML5</option><option value='0'>Hidden</option><option value='1'>Player 1</option><option value='2'>Player 2</option><option value='3'>Player 3</option></select></td>";
                        echo "<td><input type='hidden' id='autoplay_{$video["video_id"]}' name='autoplay_{$video["video_id"]}' value='0'><div id='autoplaybtn_{$video["video_id"]}' onClick='toggle_autoplay({$video["video_id"]})' style=\"cursor: pointer;\" class=\"control_button floatLeft\"><img title=\"Autoplay\" src=\"".$this->get_setting("mr_url", "oap")."include/images/boxes/autoplay_ico.gif\"></div></td>";
                        echo "<td><input type='hidden' id='viral_{$video["video_id"]}' name='viral_{$video["video_id"]}' value='0'><div id='viralbtn_{$video["video_id"]}' onClick='toggle_viral({$video["video_id"]})' style=\"cursor: pointer;\" class=\"control_button floatLeft\"><img title=\"Viral Features\" src=\"".$this->get_setting("mr_url", "oap")."include/images/boxes/viral_vid_ico.gif\"></div></td></tr></table>";
                        echo "</div>";
                    }
                }
                echo "</div>";
            }

        }
    
    
    
    
    
    
    
        /* headers for images.. never happened */
        function media_upload_type_images() {
            media_upload_header();
            echo "<div style='padding: 5px; line-height: 16px;'>";
            echo "<h2>Images</h2>";
            echo "</div>";
        }
    
        /* binds tab! */
        function modify_media_tab($tabs) {
            $new_tabs = array(
                'forms' =>  __('Forms', 'wp-media-oapforms'),
                'videos' =>  __('Videos', 'wp-media-oapvideos')
                );
            return array_merge($new_tabs, $tabs);
        }
    
        /* shows tab */
        function media_upload_forms() {
                wp_iframe(array($this, 'media_upload_type_forms'));
        }

        function media_upload_images() {
                wp_iframe(array($this, 'media_upload_type_images'));
        }

        function media_upload_videos() {
                wp_iframe(array($this, 'media_upload_type_videos'));
        }
        
        /* this function is disabled for now as it screws up HTML view tidyness... should be an advanced setting in the future */
        function tinymce_autop() {
            ?>
                <script type="text/javascript">
                //<![CDATA[
                jQuery('body').bind('afterPreWpautop', function(e, o){
                    o.data = o.unfiltered
                        .replace(/caption\]\[caption/g, 'caption] [caption')
                        .replace(/<object[\s\S]+?<\/object>/g, function(a) {
                            return a.replace(/[\r\n]+/g, ' ');
                        });

                }).bind('afterWpautop', function(e, o){
                    o.data = o.unfiltered;
                });
                //]]>
                </script>
            <?php
        }

        function modify_tinymce() {}
    
        /* south side rockers */
        function media_button_add() {

                global $post_ID, $temp_ID;

                if($this->is_setup()) {
                    $uploading_iframe_ID = (int) (0 == $post_ID ? $temp_ID : $post_ID);
                    $media_upload_iframe_src = "media-upload.php?post_id=$uploading_iframe_ID";
                    $media_oap_iframe_src = apply_filters('media_oap_iframe_src', "$media_upload_iframe_src&amp;tab=forms");
                    $media_oap_title = __('Add ' . self::$brand . ' Media', 'wp-media-oapform');
                    echo "<a href=\"{$media_oap_iframe_src}&amp;TB_iframe=true&amp;height=500&amp;width=640\" class=\"thickbox\" title=\"$media_oap_title\"><img src=\"".$this->get_setting("mr_url", "oap")."static/media-button-pp.gif\" alt=\"$media_oap_title\" /></a>";
                }
        }
    
        /* this function adds the metaboxes defined in construct() to the WP admin */
        function metabox_add() {
            if($this->is_setup()) {
                $this->load_metaboxes();
                foreach($this->metaboxes as $id => $details) {
                    $types = array();
                    foreach($this->get_setting("post_types","wp") as $type) {
                        add_meta_box($details['id'], $details['title'], array($this, "metabox_display"), $type, $details['context'], $details['priority']);
                        array_push($types, $type);
                    }
                    if ( !in_array( 'ontrapage', $types ) )
                    {
                        add_meta_box($details['id'], $details['title'], array($this, "metabox_display"), 'ontrapage', $details['context'], $details['priority']);
                    }
                }
            }
        }
    
        /* loop through and save some stuff for us */
        function metabox_save($post_id) {

            if (!wp_verify_nonce($_POST[self::NSPACE.'nonce'], basename(__FILE__))) {
                return $post_id;
            }

                if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
                    return $post_id;
                }

                if ('page' == $_POST['post_type']) {
                    if(!current_user_can('edit_page', $post_id)) {
                            return $post_id;
                    }
                } elseif (!current_user_can('edit_post', $post_id)) {
                    return $post_id;
                }

            foreach($_POST[self::NSPACE."metaboxes"] as $metabox) {
                foreach ($this->metaboxes[$metabox]["fields"] as $field) {

                    if(empty($_POST[$field['id']])) {
                        delete_post_meta($post_id, $field['id']);
                    }

                    if(isset($_POST[$field["id"]]) && is_array($_POST[$field['id']])) {
                        delete_post_meta($post_id, $field["id"]);
                        foreach($_POST[$field['id']] as $new) {
                            add_post_meta($post_id, $field['id'], $new);
                        }
                    } else {
                        if(isset($_POST[$field["id"]]) && !empty($_POST[$field['id']])) {
                            update_post_meta($post_id, $field['id'], $_POST[$field['id']]);
                        }
                    }
                }
            }
        }


        function metabox_display($post_ref, $pass_thru) {

            global $post;

            echo '<input type="hidden" name="'.self::NSPACE.'nonce" value="', wp_create_nonce(basename(__FILE__)), '" />';
            echo '<input type="hidden" name="'.self::NSPACE.'metaboxes[]" value="'.$pass_thru["id"].'" />';
            echo '<table class="form-table">';

            foreach ($this->metaboxes[$pass_thru["id"]]['fields'] as $field) {

                $meta = get_post_meta($post->ID, $field['id']);

                if(is_array($meta) && count($meta) < 2 && array_key_exists(0, $meta)) {
                    $meta = $meta[0];
                }

                if(empty($meta)) {
                    $meta = array();
                }

                if($field["type"] != "single-checkbox") {
                    echo '<tr><td><label for="', $field['id'], '"><b>', $field['name'], '</b></label><br/>';
                }
                
                switch ($field['type']) {
            
                        case "text":
                            echo "<input type='text' name='{$field['id']}' id='{$field['id']}'";
                            if(!empty($meta)) {
                                if(is_array($meta)) {
                                    echo " value='{$meta[0]}'";
                                } else {
                                    echo " value='{$meta}'";
                                }
                            }
                            echo "><br/>";
                        break;
            
                        case 'select':
                            echo '<select name="', $field['id'], '" id="', $field['id'], '">';
                            if (is_array($field["options"]))
                            {
                                foreach ($field['options'] as $option) {
                                    echo '<option', $meta == $option ? ' selected="selected"' : '', '>', $option, '</option>';
                                }    
                            }
                            echo '</select><br/>';
                        break;
        
                        case 'select-keyvalue':

                            if($field["id"] == self::NSPACE."redirect_location") {
                                $field["options"] = $this->get_routeable_pages(array($post->ID));
                            }

                            echo '<select name="', $field['id'], '" id="', $field['id'], '">';
                            if (is_array($field["options"]))
                            {
                                foreach ($field['options'] as $key => $option) {
                                    echo '<option value="'.$key.'" ', $meta == $key ? ' selected="selected"' : '', '>', $option, '</option>';
                                }
                            }
                            echo '</select><br/>';
                            
                        break;
                        
                        case 'multi-checkbox':
                            if(in_array($post->ID, $this->system_pages)) {
                                echo "<b style='color: green;'>N/A</b><br/>";
                            } else {
                                if(is_array($field["options"]) && count($field["options"]) > 0) {
                                    foreach ($field['options'] as $key => $option) {
                                        if(is_array($meta)) {
                                            echo '<label class="pp-access-level"><input type="checkbox" name="'.$field['id'].'[]" value="'.$option.'" ', in_array($option, $meta) ? ' checked' : '', ' /><span class="pp-access-level-label"> ', $option, '</span></label>';
                                        } else {
                                            echo '<label class="pp-access-level"><input type="checkbox" name="'.$field['id'].'[]" value="'.$option.'" ', $option == $meta ? ' checked' : '', ' /><span class="pp-access-level-label"> ', $option, '</span></label>';
                                        }
                                    }
                                }
                            }
                            
                        break;
                        case 'radio':
                            if(is_array($field["options"]) && count($field["options"]) > 0) {
                                foreach ($field['options'] as $option) {
                                    echo '<input type="radio" name="', $field['id'], '" value="', $option['value'], '"', $meta == $option['value'] ? ' checked="checked"' : '', ' />&nbsp;', $option['name'];
                                    echo "&nbsp;";
                                }
                            }
                        break;
                        case 'single-checkbox':
                            echo '<tr><td><input type="checkbox" name="', $field['id'], '" id="', $field['id'], '"', $meta ? ' checked="checked"' : '', ' /> <label for="', $field['id'], '"><b>', $field['name'], '</b></label>';
                            echo '';
                        break;
                        case 'preview-button':
                            if(is_array($field["options"]) && count($field["options"]) > 0) 
                            {
                                foreach ($field['options'] as $option)
                                {
                                    echo "<button class='admin-preview button button-small' value='".$option."'>".$option."</button>";
                                }
                            }
                        break;      
                    }

                if($field["id"] != self::NSPACE."redirect_location") {
                    
                } else {
                    
                }

                if($field["desc"]) {
                    echo '<span class="pp-access-level-note">'.$field["desc"].'</span>';
                }

                echo '</tr>';
            }

            echo '</table>';
        }


    
        /* ok, time for some seriousness... this does the login. see additional comments inline */
        function user_login($username, $password) {
            do_action('pilotpress_pre_user_login');
            if(isset($_POST["wp-submit"])) {
                if (!empty($username)) {


                    //Wordpress trims trailing and leading spaces before authenticating, lets do the same.
                    $password = trim($password);

                    $hashed_password = $username . self::VERSION . $password . self::AUTH_SALT;

                    $supported_algos = hash_algos();
                    if (in_array("sha256", $supported_algos)) {
                        $algo = "sha256";
                        $hash = hash("sha256", $hashed_password);
                    } else {
                        $algo = "md5";
                        $hash = md5($hashed_password);
                    }

                    if (isset($_COOKIE["sess_"])) {
                        $session_id = $_COOKIE["sess_"];
                    } else {
                        $session_id = $this->genmrSess(rand(15, 20));
                    }


                    $api_result = $this->api_call("authenticate_user", array("site" => site_url(), "username" => $username, "password" => $hash, "version" => self::VERSION, "algo" => $algo, "session_id" => $session_id));

                    if ($api_result == false && $this->get_setting('pilotpress_sync_users') == '2') {
                        //make sure user checks out with WP
                        $user = wp_authenticate($username, $password);
                        if (!$this->is_site_admin($user) && $user) {

                            $tagList = $this->get_setting("pilotpress_newly_registered_tags");
                            $sequenceList = $this->get_setting("pilotpress_newly_registered_sequences");
                            $campaignsList = $this->get_setting("pilotpress_newly_registered_campaigns");
                            $userData = array(
                                "username" => $user->user_login,
                                "password" => $password,
                                "firstname" => $user->user_firstname,
                                "lastname" => $user->user_lastname,
                                "email" => $user->user_email,
                                "website" => $user->user_url,
                                "tags" => $tagList,
                                "sequences" => $sequenceList,
                                "campaigns" => $campaignsList,
                                "site" => site_url(),
                                "version" => self::VERSION
                            );

                            //should return like "authenticate_user"
                            $api_result = $this->api_call("sync_user", $userData);
                        }
                    }

                    /* user does exist */
                    if (is_array($api_result)) {

                        if ( (!username_exists($username) && !email_exists($username)) && $api_result["status"] != 0 ) {
                            /* if their email is used (might have been a blog user before OAP perhaps), use alternate name */
                            if (email_exists($api_result["email"])) {
                                $email = $api_result["email_alt"];
                                $email_alt = $api_result["email"];
                            } else {
                                $email = $api_result["email"];
                                $email_alt = $api_result["email_alt"];
                            }

                            $firstname = "";
                            $lastname = "";

                            if ($api_result["firstname"]) {
                                $firstname = $api_result["firstname"];
                            }

                            if ($api_result["lastname"]) {
                                $lastname = $api_result["lastname"];
                            }

                            /* scary WP create user */
                            $create_user = wp_create_user($username, $password, $email);

                            /* if this errors, tell us! */
                            if (isset($create_user->errors) && isset($create_user->errors["existing_user_email"])) {
                                unset($create_user);
                                $create_user = wp_create_user($username, $password, $email_alt);
                            }

                            if (isset($create_user->errors)) {
                                $this->api_call("create_user_error", array("message" => site_url()));
                                return false;
                            }

                            if (isset($api_result["nickname"]))
                            {
                                $update = array(
                                    "ID" => $create_user,
                                    "nickname" => $api_result["nickname"],
                                    "display_name" => $api_result["nickname"],
                                    "first_name" => $firstname,
                                    "last_name" => $lastname
                                );

                                if($this->get_setting("discrete_nickname") == "on")
                                {
                                    $email_split = explode("@", $email);
                                    $update["nickname"] = $email_split[0];
                                }

                                wp_update_user($update);
                            }

                        } else {

                            /* this user does exist, so log us in */
                            $user = get_user_by("login", $username);
                            if ($user === false)
                            {
                                $user = get_user_by("email", $username);
                            }

                            if ($user === false) // If still false, something else is amiss, bail. 
                            {
                                return false;
                            }

                            /* ruhroh, this person is no longer welcomed! */
                            if ($api_result["status"] == "0") 
                            {
                                add_user_meta($user->ID, "pilotpress_blocked", "yes", true);
                                update_user_meta($user->ID, "pilotpress_blocked", "yes");
                                return false;
                            }
                            else if ($api_result["status"] == "1") 
                            {
                                update_user_meta($user->ID, "pilotpress_blocked", "no");
                                // We want to sync the password into wordpress in this case, but when we call
                                // wp_set_password it boots out any other sessions for this user. So, just do it once.
                                $recent_password_set = get_transient("pilotpress_recent_password_set". (int) $user->ID);
                                if ($recent_password_set === false)
                                {
                                    wp_set_password($password, $user->ID); // should sync pwd when auth'd to avoid the weird email issue.
                                    set_transient("pilotpress_recent_password_set". (int) $user->ID, 86400*30); // 30-day ttl to avoid buildup of transients for old users
                                }
                            }
                            else if ($user->user_level != 10) 
                            {
                                wp_set_password($password, $user->ID);
                            }
                            else 
                            {
                                return false;
                            }
                        }

                        /* store where the user logged in from for redirection after logout */
                        $referrer = false;
                        if (isset($_SERVER['HTTP_REFERER']))
                        {
                            $referrer = $_SERVER['HTTP_REFERER'];    
                        }
                        if (!empty($referrer)) {
                            $_SESSION["loginURL"] = $referrer;
                        }


                        $user = get_user_by("login", $username);
                        if ($user === false)
                        {
                            $user = get_user_by("email", $username);
                        }

                        //User is not the admin user... admin doesnt get to have their session set.
                        if ($user->user_level != 10) {

                            /* this person is not an admit, so lets make this person special */
                            if (defined("COOKIE_DOMAIN") && COOKIE_DOMAIN == "") {
                                $cookie_domain = str_replace($this->get_protocol(), "", site_url());
                            } else {
                                $cookie_domain = COOKIE_DOMAIN;
                            }

                            setcookie("contact_id", $api_result["contact_id"], (time() + 2419200), COOKIEPATH, $cookie_domain, false); //1 month

                            $contact_id = false;
                            if (isset($_COOKIE["contact_id"]))
                            {
                                $contact_id = self::validatePostVar($_COOKIE["contact_id"], "numeric");    
                            }
                            set_transient("login_url_pilotpress_user" . (int) $contact_id, $referrer, self::TTL);


                            $user_id = $user->ID;
                            $remember = false;
                            if (!empty($_POST["rememberme"])) {
                                $remember = true;
                            }
                            wp_set_current_user($user_id, $username);
                            wp_set_auth_cookie($user_id, $remember);
                            do_action('wp_login', $username, $user);

                            if (!isset($_SESSION["user_name"])) {

                                $this->start_session();

                                if (is_array($api_result))
                                {
                                    foreach ($api_result as $key => $value) {
                                        $_SESSION[$key] = $value;
                                    }    
                                }
                                
                                $_SESSION["user_name"] = $api_result["username"];
                                $_SESSION["nickname"] = $api_result["nickname"];
                                $_SESSION["user_levels"] = $api_result["membership_level"];
                                $_SESSION["rehash"] = true;
                            }

                            set_transient("authenticate_user_pilotpress_user" . get_current_user_id(), array("authenticate_user" => $api_result, "timestamp" => time()), self::TTL);
                            $this->ppp->ppprotectSetPPMemLevels($api_result["membership_level"]);

                            setcookie("sess_", $session_id, (time() + 2419200), COOKIEPATH, $cookie_domain, false);

                            do_action('pilotpress_post_user_login');

                            $contact_id = false;
                            /* where to go from here */
                            if (isset($_COOKIE["contact_id"]))
                            {
                                $contact_id = self::validatePostVar($_COOKIE["contact_id"], "numeric");    
                            }
                            $redirect_to = $this->getRedirectURL((int) $contact_id);
                            if ($redirect_to && !empty($redirect_to) && !strpos($referrer, 'wp-login.php')) 
                            {
                                unset($_SESSION["redirect_to"]);
                                delete_transient("pilotpress_redirect_to". (int) $contact_id);
                                self::redirect($redirect_to);
                                die;
                            }

                            /* this person is an affiliate, put them somewhere nice */
                            $center_priority = $this->get_setting("center_priority");
                            if ($center_priority == 2) //2 -> customer center
                            {
                                $cust_plr = $this->get_setting("pilotpress_customer_plr");
                                if ($cust_plr && $cust_plr != "-1") {
                                    self::redirect(get_permalink($cust_plr));
                                    die;
                                } else {
                                    self::redirect(site_url());
                                    die;
                                }
                            } else {
                                if (isset($api_result["program_id"])) {
                                    $aff_plr = $this->get_setting("pilotpress_affiliate_plr");
                                    if ($aff_plr && $aff_plr != "-1") {
                                        self::redirect(get_permalink($aff_plr));
                                        die;
                                        exit;
                                    } else {
                                        self::redirect(site_url());
                                        die;
                                    }
                                } else {

                                    $cust_plr = $this->get_setting("pilotpress_customer_plr");
                                    if ($cust_plr && $cust_plr != "-1") {
                                        self::redirect(get_permalink($cust_plr));
                                        die;
                                    } else {
                                        self::redirect(site_url());
                                        die;
                                    }
                                }
                            }
                            die;
                        }
                    }
                }
            }
        }
        
        /* redirect the user to a failed login page */
        function user_login_failed() {
            do_action('pilotpress_user_login_failed');

            $referrer = false;
            if (isset($_SERVER['HTTP_REFERER']))
            {
                $referrer = $_SERVER['HTTP_REFERER'];    
            }
            
            if(!empty($referrer) && !strstr($referrer, "wp-login") && !strstr($referrer, "wp-admin") ) {
                set_transient("pilotpress_login_failed", "true", self::TTL);
                $_SESSION["loginFailed"] = true;
                self::redirect($referrer);
                die;
            }
        }
        
        function user_lostpassword() {
            do_action('pilotpress_user_lostpassword');
            $api_result = $this->api_call("user_lostpassword", array("site" => site_url(), "username" => self::validatePostVar($_POST['user_login'], "string")));

            if(!is_array($api_result) || empty($api_result['email']))
            {
                /* display invalid username or e-mail message*/
                $_POST['user_login'] = "";
            }
            else
            {
                /* notify user of e-mail, end the rest of WP's processing */
                self::redirect(site_url() . "/wp-login.php?checkemail=confirm");
                die;
            }
        }
        
        function user_logout() {
            do_action('pilotpress_pre_user_logout');

            $contact_id = self::validatePostVar($_COOKIE["contact_id"], "numeric");
            $redirect_to = get_transient("login_url_pilotpress_user". (int) $contact_id);
            $this->destroy_transients_logout();
            $this->end_session(true);

            do_action('pilotpress_post_user_logout');

            if(isset($redirect_to) && !empty($redirect_to))
            {
                self::redirect($redirect_to);
            }
            else
            {
                self::redirect(site_url());
            }
        }

        /** @brief if possible add the new user to ONTRAPORT when registered in WordPress */
        function add_new_register_user_to_ONTRAPORT($user_id) {
            $bAddUser = $this->get_setting("pilotpress_sync_users");
            if ($bAddUser !== '1')
            {
                return;
            }
            $appid = $this->get_setting("app_id");
            $key = $this->get_setting("api_key");
            $tagList = $this->get_setting("pilotpress_newly_registered_tags");
            $sequenceList = $this->get_setting("pilotpress_newly_registered_sequences");
            $campaignList = $this->get_setting("pilotpress_newly_registered_campaigns");
            $user = get_userdata($user_id);
            $userData = array(
                        "username" => $user->user_login,
                        "password" => self::validatePostVar($_POST['pass1'], "string"),
                        "firstname"=>$user->user_firstname,
                        "lastname"=>$user->user_lastname,
                        "email"=>$user->user_email,
                        "tags"=>$tagList,
                        "sequences"=>$sequenceList,
                        "campaigns"=>$campaignList,
                        "site" => site_url(),
                        "version" => self::VERSION
                    );

            $api_result = $this->api_call("sync_user", $userData);
        }


        static function start_session() 
        {
            //sessions break theme editor & admin page doesn't need sessions
            if(!is_admin()) 
            {
                ob_start();
                if(!session_id()) {
                    session_start();
                }
                ob_end_clean();
            } 
        }

        static function end_session($logout = false) {

            if($logout) {
                /* redirect the user to where they logged in from */
                if(isset($_SESSION["loginURL"]))
                    self::redirect($_SESSION["loginURL"]);
                else
                    self::redirect(site_url());
            }
                    
            ob_start();
            if(session_id()) {
                delete_transient("pilotpress_cache");
                if(isset($_SESSION["contact_id"])) {
                    delete_transient("usertags_".$_SESSION["contact_id"]);
                }
                unset($_SESSION);
                session_destroy();
            }
            ob_end_clean();

            if($logout) die;
        }

        function filter_query_vars($vars) {
            return $vars;
        }

        function filter_rewrite_rules($rules) {
            global $wp_rewrite;
            $newRule = array('ref/(.+)' => 'index.php?ref='.$wp_rewrite->preg_index(1));
            $newRules = $newRule + $rules;
            return $newRules;
        }

        function flush_rewrite_rules() {
            global $wp_rewrite;
            $wp_rewrite->flush_rules();
        }

        function clean_meta() {
            global $wpdb;
            $wpdb->query("DELETE FROM $wpdb->postmeta WHERE meta_key = '_pilotpress_level' AND meta_value = ''");
        }

        /* Load up the membership level meta boxes but after we have gotten the levels */
        function load_metaboxes() {

            /* metaboxes in admin */
            $this->metaboxes[self::NSPACE."page_box"] = array(
                    'id' => self::NSPACE.'page_box',
                    'title' => 'PilotPress Options',
                    'context' => 'side',
                    'priority' => 'high',
                    'fields' => array(
                        array(
                                'name' => 'Access Levels',
                                'desc' => '(Leave blank to allow access to all users.)',
                                'id' => self::NSPACE.'level',
                                'type' => 'multi-checkbox',
                                'options' => $this->get_setting("membership_levels", "oap")
                        ),
                        array(
                                'name' => 'Show in Navigation',
                                'desc' => false,
                                'id' => self::NSPACE.'show_in_nav',
                                'type' => 'single-checkbox'
                        ),
                        array(
                                'name' => 'On Error',
                                'desc' => $this->get_setting("error_redirect_message"),
                                'id' => self::NSPACE.'redirect_location',
                                'type' => $this->get_setting("error_redirect_field"),
                                'options' => array()
                        )
                    )
            );

            $this->metaboxes[self::NSPACE."admin_preview"] = array(
                'id' => self::NSPACE."admin_preview",
                'title' => 'PilotPress Admin View As',
                'context' => 'side',
                'priority' => 'high',
                'fields' => array(
                    array(
                        'name' => 'Select membership level to view as:',
                        'desc' => false,
                        'id' => self::NSPACE.'view_as',
                        'options' => $this->get_setting("membership_levels", "oap"),
                        'type' => 'preview-button'
                    )
                )
            );

        }


        /**
         * @brief prevents a user from overloading the API using customizable params
         * @params array $options --> (string)id, (int)timeout, (int)passes, (int)interval, (bool ref)throttled, (bool)admin
         * @return sets (bool ref)throttled to true when user has hit its limit
         **/
        function throttle($options)
        {
            if (is_array($options) && isset($options['id']) && isset($options['throttled'])) 
            {

                //allow admin user to override the throttle
                $admin = $options['admin'];
                if(!$admin)
                {
                    $now = time();
                    $id = $options['id'];
                    $passes = $options['passes'];
                    $timeout = $options['timeout'];
                    $interval = $options['interval'];

                    $contact_id = self::validatePostVar($_COOKIE["contact_id"], "numeric");
                    $throttle_data = get_transient("pilotpress_throttle".(int) $contact_id);

                    //pass limit hit, need to check to throttle
                    if (isset($throttle_data[$id]['allowed'])) 
                    {
                        $timeLeft = $now - $throttle_data[$id]['allowed'];
                        
                        if ($timeLeft < 0) 
                        {
                            $options['throttled'] = true;
                        } 
                        else //reset their timers 
                        {
                            unset($throttle_data[$id]);
                            $throttle_data[$id]['pass']  = 1;
                            $throttle_data[$id]['setAt'] = $now;
                            
                            //edge case -- 1 pass allowed
                            if ($throttle_data[$id]['pass'] == $passes)
                            {
                                $throttle_data[$id]['allowed'] = $now + $timeout;
                            }
                        }
                    } 
                    else 
                    {
                        if (!isset($throttle_data[$id]['setAt'])) 
                        {
                            $throttle_data[$id]['setAt'] = $now;
                        } 
                        else 
                        {
                            //waited long enough, reset throttle
                            if ($now > ($throttle_data[$id]['setAt'] + $interval)) 
                            {
                                unset($throttle_data[$id]);
                                $throttle_data[$id]['setAt'] = $now;
                                $throttle_data[$id]['pass']  = 0;
                            }
                        }
                        
                        //# of passes handling
                        if (isset($throttle_data[$id]['pass'])) 
                        {
                            $throttle_data[$id]['pass']++;
                        }
                        else 
                        {
                            $throttle_data[$id]['pass'] = 1;
                        }
                        
                        if ($throttle_data[$id]['pass'] == ($passes)) 
                        {
                            $throttle_data[$id]['allowed'] = $now + ($timeout);
                        }
                    }

                    set_transient("pilotpress_throttle".(int) $contact_id, $throttle_data, self::TTL);
                }
            }
        }

        /**
         * @brief shortcode that re-syncs whichever user hits the page containing it, destroys all assoc. data and re-grabs it
         * @params array $atts, string $content
         * @return nothing, just carries on w/ other shortcode magic
         **/
        function shortcode_sync_contact($atts, $content = null)
        {
            $throttled = false;

            //throttle them for 5 minutes if they try to do this more than 3 times within a 5 minutes gap
            $this->throttle(array(
                    'id' => "sync_contact",
                    'timeout' => 60 * 5, //5 minutes 
                    'passes' => 3,
                    'interval' => 60 * 5, //5 minutes
                    'throttled' => &$throttled,
                    'admin' => current_user_can("manage_options")      
            ));


            if(!$throttled)
            {
                $this->destroy_transients_logout();
                $this->load_settings();
                return '<span class="pilotpress_protected">'.do_shortcode($content).'</span>';
            }
            else
            {
                return '<span class="pilotpress_protected">'.do_shortcode($content).'</span>';
            }
        }

        /* shortcodes for conditional ifs */
        function shortcode_show_if($atts, $content = null) {

            if(isset($atts[0]) && $atts[0] == "not_contact") {
                if(!$this->get_setting("contact_id","user")) {
                    return '<span class="pilotpress_protected">'.do_shortcode($content).'</span>';
                }
            }

            $user_info = $this->get_stashed("authenticate_user", true);

            $contact_id = false;
            //make cookie check befor login check to bypass it
            if (isset($_COOKIE["contact_id"]))
            {
                $contact_id = self::validatePostVar($_COOKIE["contact_id"], "numeric");    
            }
            
            if ( isset($atts[0]) && $atts[0] == "is_cookied_contact")
            {
                if ($contact_id !== false)
                {
                    return '<span class="pilotpress_protected">'.do_shortcode($content) . '</span>';
                }
            }

            if (isset($atts[0]) && $atts[0] == "not_cookied_contact") 
            {
                if ($contact_id === false)
                {
                    return '<span class="pilotpress_protected">'.do_shortcode($content) . '</span>';
                }
            }            
            if(!is_user_logged_in() || get_user_meta(get_current_user_id(), "pilotpress_blocked", true) == "yes")
            {
                return;
            }

            if ($found = self::DoShortcodeMagic($atts,$content))
            {
                return $found;
            }

            //if we fail to find something lets make sure Wordpress hasnt encoded the tags and membership levels.
            if(is_array($atts))
            {
                foreach ($atts as $key => $att)
                {
                    $atts[$key] = html_entity_decode($atts[$key],ENT_COMPAT,"UTF-8");
                }    
            }
            
            //process shortcodes with decoded entities
            return self::DoShortcodeMagic($atts,$content);
        }

        /* 
         *  @brief Process additional shortcode logic here
         * 
         **/
        function DoShortcodeMagic($atts,$content)
        {
            $user_info = $this->get_stashed("authenticate_user", true);
            $user_levels = false;

            if(current_user_can("manage_options") && isset(self::$stashed_transients["pilotpress_admin_preview"]))
            {
                $user_levels = self::$stashed_transients["pilotpress_admin_preview"];
            }
            else 
            {
                if (isset($user_info["authenticate_user"]["membership_level"]))
                {
                    $user_levels = $user_info["authenticate_user"]["membership_level"];
                }
            }

            if(!is_array($user_levels)) 
            {
                $user_levels = array();
            }
                        
            if(isset($atts["level"])) {
                if(in_array($atts["level"], $user_levels)) {
                    return '<span class="pilotpress_protected">'.do_shortcode($content).'</span>';
                }
            } else {
                                                
                if(isset($atts["has_one"])) {
                    $content_levels = explode(",", $atts["has_one"]);
                    if (is_array($user_levels))
                    {
                        foreach($user_levels as $level) {
                            if(in_array(ltrim(rtrim($level)), $content_levels)) {
                                return '<span class="pilotpress_protected">'.do_shortcode($content).'</span>';
                            }
                        }    
                    }
                }
                
                if(isset($atts["has_all"])) {
                    $content_levels = explode(",", $atts["has_all"]);
                    if (is_array($content_levels))
                    {
                        foreach($content_levels as $level) {
                            if(!in_array(ltrim(rtrim($level)), $user_levels)) {
                                return false;
                            }
                        }
                    }
                    return '<span class="pilotpress_protected">'.do_shortcode($content).'</span>'; 
                }
                
                if(isset($atts["not_one"])) {
                    $content_levels = explode(",", $atts["not_one"]);
                    if (is_array($content_levels))
                    {
                        foreach($content_levels as $level) {
                            if(in_array(ltrim(rtrim($level)), $user_levels)) {
                                return false;
                            }
                        }
                    }
                    return '<span class="pilotpress_protected">'.do_shortcode($content).'</span>'; 
                }
                
                if(isset($atts["not_any"])) {
                    $content_levels = explode(",", $atts["not_any"]);
                    if (is_array($content_levels))
                    {
                        foreach($content_levels as $level) {
                            if(!in_array(ltrim(rtrim($level)), $user_levels)) {
                                return '<span class="pilotpress_protected">'.do_shortcode($content).'</span>';
                            }
                        }
                    }
                }
                
                if(isset($atts[0]) && $atts[0] == "is_contact") {                           
                    if($user_info["authenticate_user"]["contact_id"]) {
                        return '<span class="pilotpress_protected">'.do_shortcode($content).'</span>';
                    }
                }
                if (isset($atts["has_tag"]))
                {
                    $tags = $this->get_setting("tags", "user");
                    if (is_array($tags))
                    {
                        $content_tags = explode(",", strtolower($atts["has_tag"]));
                        $content_tags = array_map('trim', $content_tags);
                        $tags = array_map('strtolower', $tags);
                        foreach ($tags as $tag)
                        {
                            if (in_array(trim($tag), $content_tags))
                            {
                                return '<span class="pilotpress_protected">' . do_shortcode($content) . '</span>';
                            }
                        }
                    }
                }

                if(isset($atts["does_not_have_tag"]))
                {
                    $tags = $this->get_setting("tags", "user");
                    if(empty($tags) || !(in_array($atts["does_not_have_tag"], $tags)))
                    {
                        return '<span class="pilotpress_protected">'.do_shortcode($content).'</span>';
                    }
                }

                if(isset($atts[0]) && in_array($atts[0], $user_levels)) {
                    return '<span class="pilotpress_protected">'.do_shortcode($content).'</span>';
                }
            }
        }

        function shortcode_field($atts, $content = null) {

            extract(shortcode_atts(array("name" => "All"), $atts));
            if(isset($atts["name"]))
            {
                return $this->get_field($atts["name"]);
            }

        }

        /* the big nasty content hiding function... tread carefully */
        function post_process()
        {
            global $post;
            if (isset($post->ID))
            {
                $id = $post->ID;
            }
            else
            {
                $id = $this->get_postid_by_url();
                if (empty($id) && get_option('show_on_front') == 'page')
                {
                    $id = get_option('page_on_front');
                }
            }

            if (!$this->is_viewable($id) || get_user_meta(get_current_user_id(), "pilotpress_blocked", true) == "yes")
            {
                $redirect = get_post_meta($id, self::NSPACE . "redirect_location", true);
                if (!empty($redirect))
                {
                    $contact_id = false;
                    if (isset($_COOKIE["contact_id"]))
                    {
                        $contact_id = self::validatePostVar($_COOKIE["contact_id"], "numeric");
                    }

                    if ($redirect == "-1")
                    {
                        return self::redirect(site_url());
                    }

                    if ($redirect == "-2")
                    {
                        if (!empty($id))
                        {
                            $_SESSION["redirect_to"] = $id;
                            if ($contact_id !== false)
                            {
                                set_transient("pilotpress_redirect_to" . $contact_id, "post_" . $id, self::FIVE_MINUTES);
                            }
                        }
                    }
                    $_SESSION["redirect_to"] = $id;
                    if ($contact_id !== false)
                    {
                        set_transient("pilotpress_redirect_to" . $contact_id, "post_" . $id, self::FIVE_MINUTES);
                    }
                    return self::redirect(get_permalink($redirect));
                }
                return self::redirect($this->homepage_url);
            }
        }

        /* is this a special page? if so render such */
        function content_process($content) {
            global $post;

            $loginFailed = get_transient("pilotpress_login_failed");

            //check to see if login failed on a custom login page!
            if(has_shortcode($content, 'login_page') && $loginFailed == "true")
            {
                preg_match_all("/\[login_page\s*[^\[\]]*\]/", $content, $matches);
                foreach($matches[0] as $index => $shortcode)
                {
                    $atts = shortcode_parse_atts($shortcode);
                    $login_page = $this->login_page($atts, 3);
                    $content = str_replace($shortcode, $login_page, $content, $count);
                }
                delete_transient("pilotpress_login_failed");
                return $content;
            }

            if($this->do_login == true) {
                if(!is_user_logged_in() && $loginFailed == "true") {
                    $login_page = $this->login_page(array(), 3);
                    $content = str_replace("[login_page]", $login_page, $content, $count);
                    if($count == 0) {
                        $content = $login_page;
                    }
                    delete_transient("pilotpress_login_failed");
                    unset($_SESSION["loginFailed"]);
                } else if(!is_user_logged_in()) {
                    $content = $this->login_page(array(), 1);
                } else {
                    $content = $this->login_page(array(), 2);
                }
                $this->do_login = false;
                add_filter("comments_open", array(&$this, 'ppDisableComments'), 10, 2);
                add_filter("get_comments_number", array(&$this, 'ppZeroCommentsNumber'), 10, 1);
            } else {
                if(is_page() && in_array($post->ID, $this->system_pages)) {
                    $content = $this->do_system_page($post->ID);
                }

                if (has_shortcode($content, "pilotpress_field") || has_shortcode($content, "field"))
                {
                    // Lets grab all the fields here with the API call and store them later
                    // Since the shortcode hook runs after this one it is a safe spot to check and make if needed.
                    $this->get_merge_field_settings($content);
                }
            }

            return $content;
        }

        /**
         * @brief close comments section by returning false
         * @param bool $open
         * @param int $post_id
         *
         * @return bool
         */
        public function ppDisableComments($open, $post_id)
        {
            return false;
        }

        /**
         * @brief Zero out comments number so comments don't load in template
         * @param int $post_id
         *
         * @return int
         */
        public function ppZeroCommentsNumber($post_id)
        {
            return 0;
        }

        /**
         *  @brief Make api call to grab merge fields that are only present in the content
         *
         *  @param String $content the string to check if merge fields are present
         *
         */
        function get_merge_field_settings($content , $makeApiCall = true)
        {
            $pattern = get_shortcode_regex();

            preg_match_all('/'.$pattern.'/uis', $content, $matches);

            for ( $i=0; $i < count($matches[0]); $i++ )
            {
                $fields = shortcode_parse_atts($matches[3][$i]);
                if (!is_array($fields)) // Case we only have one
                {
                    $fields = array("name" => $fields);
                }
                $fields["name"] = $this->undo_quote_escaping($fields["name"]);

                if ( isset( $matches[2][$i] ) && ($matches[2][$i] == "pilotpress_field" || $matches[2][$i] == "field") )
                {
                   $this->shortcodeFields[$fields["name"]] = 1;
                }
                elseif (!empty($matches[5][$i]))
                {
                    //call this recursively so we can process shortcodes inside shortcodes
                    $this->get_merge_field_settings($matches[5][$i] , false);
                }
            }

            $user_info = $this->get_stashed("authenticate_user", true);
            if(!isset($user_info["authenticate_user"]["username"]) || $user_info["authenticate_user"]["username"] == null)
            {
                return false;
            }

            //Since this can be called recursively lets make sure when it does call it we only make this at the initial call of the function
            if ($makeApiCall)
            {
                //make API call now as well if needed!
                if (!empty($this->shortcodeFields) && is_array($this->shortcodeFields) && !empty($user_info["authenticate_user"]["username"]))
                {
                    $data = array(
                        "username" => $user_info["authenticate_user"]["username"],
                        "fields" => $this->shortcodeFields,
                        "site" => site_url()
                    );

                    $api_result = $this->api_call("get_contact_merge_fields" , $data);

                    if(isset($api_result["fields"]))
                    {
                        // In order for the get_field() to work later on we need to add these fields to the group list of known merged fields.
                        $this->settings["user"]["fields"]["--merged fields--"] = $api_result["fields"];
                        $_SESSION["user_fields"]["--merged fields--"] = $api_result["fields"];
                    }
                }
            }
        }

        /* this is arguably the nastiest part of PilotPress, but unfortunately WP has consistently decided to not allow non-theme based manipulation of viewable pages */
        function get_routeable_pages($exclude = "") {

            global $wpdb;

            $array = array('-1' => "(homepage)", "-2" => "(login page)");

            $query = $wpdb->get_results("SELECT ID, post_title FROM $wpdb->posts WHERE post_status = 'publish' AND (post_type = 'page' OR post_type ='oaplesson' OR post_type ='ontrapage') AND post_title != ''");

            foreach($query as $index => $page) {
                $array[$page->ID] = $page->post_title;
            }

            if(is_array($exclude)) {
                foreach($exclude as $id) {
                    unset($array[$id]);
                }
            }

            return apply_filters("pilotpress_get_routeable_pages",$array);
        }



        /* this is where we part the seas: if something isn't routable, then tree falls in the woods to no fuss */
        function posts_where($where)
        {
            global $wpdb;

            if(current_user_can('manage_options'))
            {
                if (empty(self::$stashed_transients["pilotpress_admin_preview"]))
                {
                    return $where;
                }
                $user_levels = self::$stashed_transients["pilotpress_admin_preview"];
            }
            else
            {
                $user_levels = $this->get_setting("levels", "user", true);
            }

            $site_levels = $this->get_setting("membership_levels", "oap", true);

            //note empty beginner is important in situations where level_in or level_not_in are otherwise empty
            $level_in = "'',";
            $level_not_in = "'',";

            if (is_array($site_levels))
            {
                foreach ($site_levels as $level)
                {
                    if (in_array($level, $user_levels))
                    {
                        $level_in .= "'" . addslashes($level) . "',";
                    }
                    else
                    {
                        $level_not_in .= "'" . addslashes($level) . "',";
                    }
                }
            }

            $id = (int)$this->get_postid_by_url();
            if (empty($id) && get_option('show_on_front') == 'page')
            {
                $id = get_option('page_on_front');
            }

            if (!empty($id))
            {
                if ($this->is_viewable($id))
                {
                    return $where;
                }

                $redirect = get_post_meta($id, self::NSPACE . "redirect_location", true);
                if ($redirect == "-2")
                {
                    $this->do_login = $id;
                    return $where;
                }
            }

            $level_in = rtrim($level_in, ",");
            $level_not_in = rtrim($level_not_in, ",");

            if (!empty($level_in))
            {
                $where .= " 
                AND ID NOT IN 
                    (SELECT `post_id` FROM {$wpdb->postmeta} 
                        WHERE `meta_key` = '_pilotpress_level' 
                            AND `meta_value` IN (" . $level_not_in . ")
                            AND `post_id` NOT IN 
                                (SELECT `post_id` FROM {$wpdb->postmeta} 
                                    WHERE `meta_key` = '_pilotpress_level' 
                                    AND `meta_value` IN (" . $level_in . " )))";
            }

            return $where;
        }

        /* filters nav menu objects */
        function get_pages($pages) {
            global $wpdb;

            $show_in_nav = $this->get_setting("show_in_nav", "pilotpress", true);

            $filtered = array();
            if (is_array($pages))
            {
                foreach($pages as $page) {
                    if($this->is_viewable($page->ID) OR in_array($page->ID, $show_in_nav)) {
                        $filtered[] = $page;
                    }
                }    
            }
            
            return $filtered;
        }

        function get_nav_menu_objects($menus) {
            $show_in_nav = $this->get_setting("show_in_nav", "pilotpress", true);
            $new_menus = array();
            if (is_array($menus))
            {
                foreach($menus as $id => $object) {
                    $object_id = $object->object_id;
                    if($this->is_viewable($object_id)) {
                        $new_menus[] = $object;
                    } else {
                        if(in_array($object_id, $show_in_nav)) {
                            $new_menus[] = $object;
                        }
                    }
                }    
            }
            
            return $new_menus;
        }

        /* really returns filtered menus */
        function get_nav_menus($menus) {

            $show_in_nav = $this->get_setting("show_in_nav", "pilotpress", true);

            $excludes = array();
            $output = $menus;
            $xml = @simplexml_load_string($menus);

            if(is_object($xml)) {
                if(isset($xml->ul->li)) {
                    foreach($xml->ul->li as $obj) {
                        $post_id = url_to_postid((string)$obj->a->attributes()->href);
                        if(!$post_id){
                            $pages = preg_replace('#^.+/([^/]+)/*$#','$1',(string)$obj->a->attributes()->href);
                            $query = new WP_Query('pagename='.$pages);

                            if( $query->is_page && isset($query->queried_object) ) {
                                $post_id = $query->queried_object->ID;
                            }
                        }

                        if(!$this->is_viewable($post_id) AND !in_array($post_id, $show_in_nav)) {
                            $excludes[] = (string)$obj->attributes()->id;
                        }
                    }
                }

                if(is_array($excludes) && count($excludes) > 0) {
                    $output = "<style type='text/css'>";
                    foreach($excludes as $index => $id) {
                        $output .= '#'.$id." { display: none; }\n";
                    }
                    $output .= "</style>";
                    $output .= $menus;
                }
            }

            return $output;
        }

        /* i take it back, this is horrible. at the time of writing, WP cannot find what page(s) are being displayed, so this finds it by URL. */
        function get_postid_by_url()
        {
            global $wp, $wpdb;
            $vars_to_check = array(
                "page_id"  => "int",
                "p"        => "int",
                "pagename" => "str",
                "name"     => "str"
            );

            foreach ($vars_to_check as $key => $type)
            {
                if (isset($wp->query_vars[$key]))
                {
                    switch ($type)
                    {
                        case "int":
                            return $wp->query_vars[$key];
                        case "str":
                            $name = $wp->query_vars[$key];
                            $subpage = explode("/", $name);
                            if (count($subpage) > 1)
                            {
                                $name = $subpage[1];
                            }
                            return $wpdb->get_var($wpdb->prepare("SELECT `ID` FROM {$wpdb->posts} WHERE `post_name` = %s", $name));
                    }
                }
            }

            return false;
        }
    
        /* the most important function for content hiding. this finally decides if something can be seen or not. */
        function is_viewable($id) { 
            global $wpdb, $post;

            do_action('pilotpress_content_hiding');

            $ref = get_query_var("ref");
            if($ref) {
                switch($ref) {
                    case "customer_center":
                        $page_id = $wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_pilotpress_system_page' AND meta_value = 'customer_center'", ARRAY_A);
                        if($page_id) {
                            self::redirect(get_permalink($page_id));
                            die;
                        }
                    break;
                    case "affiliate_center":
                        $page_id = $wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_pilotpress_system_page' AND meta_value = 'affiliate_center'", ARRAY_A);
                        if($page_id) {
                            self::redirect(get_permalink($page_id));
                            die;
                        }
                    break;
                    default:
                    break;
                }
            }
            
            $page_levels = get_post_meta($id, "_pilotpress_level");

            if(current_user_can('manage_options') && isset(self::$stashed_transients["pilotpress_admin_preview"])) {
                $user_levels = self::$stashed_transients["pilotpress_admin_preview"];
            }
            else if(current_user_can('manage_options') && !isset(self::$stashed_transients["pilotpress_admin_preview"]))
            {
                return true;
            }
            else
            {
                $user_info = $this->get_stashed("authenticate_user", true);
                if (isset($user_info["authenticate_user"]["membership_level"]))
                {
                    $user_levels = $user_info["authenticate_user"]["membership_level"];
                }
                else
                {
                    $user_levels = array();
                }
            }
            
            if(!is_array($user_levels)) {
                $user_levels = array($user_levels);
            }

            if(in_array($id, $this->system_pages)) {
                if(!is_user_logged_in()) {
                    return false;
                } else {
                    return true;
                }
            }

            if (empty($page_levels) || count($page_levels) == 0)
            {
                return true;
            }

            if(count($page_levels) > 0) {
                if(count($user_levels) == 0) {
                    return false;
                } else {
                    foreach($user_levels as $level) {               
                        if(in_array($level, $page_levels)) {
                            return true;
                        }
                    }
                    return false;   
                }
            }   
        }
    
        /* simple getter */
        function get_system_pages() {
            global $wpdb;
            $return = array();
            $results = $wpdb->get_results("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_pilotpress_system_page'", ARRAY_A);
            if(is_array($results)) {
                foreach($results as $q_post) {
                    $return[] = $q_post["post_id"];
                }
            }
            return $return;
        }
    
        /* renders a system page */
        function do_system_page($id) {

            $type = get_post_meta($id, self::NSPACE."system_page", true);
            //send over our colors to style the pages nicely
            $styles["primary_color"] = $this->get_setting("pilotpress_customer_center_primary_color");
            $styles["secondary_color"] = $this->get_setting("pilotpress_customer_center_secondary_color");
            $styles["header_image"] = $this->get_setting("pilotpress_customer_center_header_image");

            $user_info = $this->get_stashed("authenticate_user", true);

            if(!is_user_logged_in() || $user_info["authenticate_user"]["username"] == null
               || !isset($user_info["authenticate_user"]["username"]))
            {
                $return = $this->login_page(array(), 1);
                return $return;
            }

            if($type == "affiliate_center") {
                $program_id = false;
                if (isset($user_info["authenticate_user"]["program_id"]))
                {
                    $program_id = self::validatePostVar($user_info["authenticate_user"]["program_id"], "numeric");
                }
                $api_result = $this->api_call("get_".$type, array("username" => $user_info["authenticate_user"]["username"], "program_id" => $program_id, "site" => site_url()  , "styles"=>$styles ));
            }  

            if($type == "customer_center"){
                $api_result = $this->api_call("get_".$type, array("username" => $user_info["authenticate_user"]["username"], "site" => site_url(), "nonce" => wp_create_nonce(basename(__FILE__)) , "styles"=>$styles , "version"=>self::VERSION ));
            }

            if($api_result) {
                if($api_result["code"] != "0") {
                    return $api_result["code"];
                } else {
                    $return = $this->login_page(array(), 2);
                    return $return;
                }
            }
        }
    
        /* creates a system page in a post somewhere */
        function create_system_page($name) {
            global $wpdb;
            $sql = $wpdb->prepare("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_pilotpress_system_page' AND meta_value = %s", $name );

            $pages = $wpdb->get_results($sql, ARRAY_A);
            if(count($pages) == 0) {
                $post = array(
                    'post_title' => "{$this->centers[$name]["title"]}",
                    'slug' => "{$this->centers[$name]["slug"]}",
                    'post_status' => 'publish', 
                    'post_type' => 'page',
                    'comment_status' => "closed",
                    'visibility' => "public",
                    'ping_status' => "closed",
                    'post_category' => array(1),
                    'post_content' => "{$this->centers[$name]["content"]}");                    
                $post_id = wp_insert_post($post);
                add_post_meta($post_id, PilotPress::NSPACE."system_page", $name);
                add_post_meta($post_id, PilotPress::NSPACE."redirect_location", "-2");
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->posts} WHERE `post_status` = 'trash' AND `post_name` = %s", $name));
                $wpdb->query($wpdb->prepare("UPDATE {$wpdb->posts } SET `post_name` = %s WHERE `ID` = %d", $name, $post_id));
                $this->flush_rewrite_rules();
            }
        }
    
        /* banished. */
        function delete_system_page($name) 
        {
            global $wpdb;
            $pages = $wpdb->get_results($wpdb->prepare("SELECT `post_id` FROM {$wpdb->postmeta} WHERE `meta_key` = '_pilotpress_system_page' AND `meta_value` = %s",$name), ARRAY_A);
            
            if(!empty($pages)) {
                foreach($pages as $page) {
                    delete_post_meta($page["post_id"], "_pilotpress_system_page");
                    wp_delete_post($page["post_id"], true);
                }   
            }
        }

        /**
         * Ping Logic
         * 
         * Imports jQuery logic to head which can then be utilized 
         * to send ajax calls to the same file to update the PilotPress
         * session.
         *
         *
         * @uses add_action()
         */
        function sessionslap_ping(){
            // Register JavaScript
            wp_enqueue_script('jquery');

            require_once( plugin_dir_path( __FILE__ ) . "/ping.php");
            
            // Append dynamic js to both admin and regular users head.
            add_action( "admin_head", "pilotpress_sessionslap_face" );
            add_action( "wp_head", "pilotpress_sessionslap_face" );
            
        }
            
        /* renders cute login page */
        function login_page ($atts, $message = false) 
        {
            // Allows shortcodes to be put in text widgets
            add_filter('widget_text', 'do_shortcode');

            global $wpdb;
            // This section allows the users to add custom styling by adding custom attributes to the shortcode [login_page]
            // Form general styling options
            if ( isset($atts['width']) ) 
            { 
                $width = $atts['width'];
                $width = 'max-width: '.$width.'!important;';
            }
            else
            {
                $width = 'max-width: 320px;';
            }

            if ( isset($atts['formalign']) ) 
            { 
                $formalign = $atts['formalign'];

                if ( $formalign == 'left' ) 
                {
                    $formalign = 'margin: 30px 30px 30px 0px; float: left;';
                }
                else if ( $formalign == 'center' ) 
                {
                    $formalign = 'margin: 30px auto!important;';
                }
                else if ( $formalign == 'right' ) 
                {
                    $formalign = 'margin: 30px 0px 30px 30px; float: right;';
                }
                else 
                {
                    $formalign = 'margin: 30px 0px;';
                }
            }
            else
            {
                $formalign = 'margin: 30px 0px; width: 100%;';
            }

            if ( isset($atts['bgcolor']) ) 
            { 
                $bgcolor = $atts['bgcolor'];
                $bgcolor = 'background-color: '.$bgcolor.'!important;';
            }
            else
            {
                $bgcolor = 'background-color: #fff;';
            }

            if ( isset($atts['textcolor']) ) 
            { 
                $textcolor = $atts['textcolor'];
                $textcolor = 'color: '.$textcolor.'!important;';
            }
            else
            {
                $textcolor = '';
            }

            // Header Text styling
            if ( isset($atts['headertextalignment']) ) 
            { 
                $headertextalignment = $atts['headertextalignment'];
                $headertextalignment = 'text-align: '.$headertextalignment.'!important;';
            }
            else
            {
                $headertextalignment = '';
            }

            if ( isset($atts['headertextfont']) ) 
            { 
                $headertextfont = $atts['headertextfont'];
                $headertextfont = 'font-family: '.$headertextfont.'!important;';
            }
            else
            {
                $headertextfont = '';
            }

            if ( isset($atts['headertextfontsize']) ) 
            { 
                $headertextfontsize = $atts['headertextfontsize'];
                $headertextfontsize = 'font-size: '.$headertextfontsize.'!important;';
            }
            else
            {
                $headertextfontsize = 'font-size: 20pt;';
            }

            if ( isset($atts['headertextfontcolor']) ) 
            { 
                $headertextfontcolor = $atts['headertextfontcolor'];
                $headertextfontcolor = 'color: '.$headertextfontcolor.'!important;';
            }
            else
            {
                $headertextfontcolor = 'color: #222;';
            }

            // Supporting Text styling
            if ( isset($atts['supportingtextfont']) ) 
            { 
                $supportingtextfont = $atts['supportingtextfont'];
                $supportingtextfont = 'font-family: '.$supportingtextfont.'!important;';
            }
            else
            {
                $supportingtextfont = '';
            }

            if ( isset($atts['supportingtextfontsize']) ) 
            { 
                $supportingtextfontsize = $atts['supportingtextfontsize'];
                $supportingtextfontsize = 'font-size: '.$supportingtextfontsize.'!important;';
            }
            else
            {
                $supportingtextfontsize = 'font-size: 12pt;';
            }

            if ( isset($atts['supportingtextfontcolor']) ) 
            { 
                $supportingtextfontcolor = $atts['supportingtextfontcolor'];
                $supportingtextfontcolor = 'color: '.$supportingtextfontcolor.'!important;';
            }
            else
            {
                $supportingtextfontcolor = 'color: #555;';
            }

            // Form Input styling
            if ( isset($atts['inputcolor']) ) 
            { 
                $inputcolor = $atts['inputcolor'];
                $inputcolor = 'background-color: '.$inputcolor.'!important;';
            }
            else
            {
                $inputcolor = '';
            }

            if ( isset($atts['inputtextcolor']) ) 
            { 
                $inputtextcolor = $atts['inputtextcolor'];
                $inputtextcolor = 'color: '.$inputtextcolor.'!important;';
            }
            else
            {
                $inputtextcolor = '';
            }

            if ( isset($atts['inputbordercolor']) ) 
            { 
                $inputbordercolor = $atts['inputbordercolor'];
                $inputbordercolor = 'border: 1px solid '.$inputbordercolor.'!important;';
            }
            else
            {
                $inputbordercolor = '';
            }

            if ( isset($atts['inputfieldsize']) ) 
            { 
                $inputfieldsize = $atts['inputfieldsize'];
                if ( $inputfieldsize == 'large' ) 
                {
                    $inputfieldsize = 'padding: 16px!important; font-size: 15pt;';
                }
                if ( $inputfieldsize == 'medium' ) 
                {
                    $inputfieldsize = 'padding: 9px!important; font-size: 12pt;';
                }
                if ( $inputfieldsize == 'small' ) 
                {
                    $inputfieldsize = 'padding: 6px!important; font-size: 10pt;';
                }
            }
            else
            {
                $inputfieldsize = 'padding: 6px!important; font-size: 10pt;';
            }

            // Form Button styling
            if ( isset($atts['buttonbgcolor']) ) 
            { 
                $buttonbgcolor = $atts['buttonbgcolor'];
                $buttonbgcolor = 'background-color: '.$buttonbgcolor.'!important; background-image: none!important;';
            }
            else
            {
                $buttonbgcolor = '';
            }

            if ( isset($atts['buttontextcolor']) ) 
            { 
                $buttontextcolor = $atts['buttontextcolor'];
                $buttontextcolor = 'color: '.$buttontextcolor.'!important;';
            }
            else
            {
                $buttontextcolor = '';
            }

            if ( isset($atts['buttonbordercolor']) ) 
            { 
                $buttonbordercolor = $atts['buttonbordercolor'];
                $buttonbordercolor = 'border: 1px solid '.$buttonbordercolor.'!important;';
            }
            else
            {
                $buttonbordercolor = '';
            }

            if ( isset($atts['buttonfont']) ) 
            { 
                $buttonfont = $atts['buttonfont'];
                $buttonfont = 'font-family: '.$buttonfont.'!important;';
            }
            else
            {
                $buttonfont = '';
            }

            if ( isset($atts['buttonfontsize']) ) 
            { 
                $buttonfontsize = $atts['buttonfontsize'];
                $buttonfontsize = 'font-size: '.$buttonfontsize.'!important;';
            }
            else
            {
                $buttonfontsize = 'font-size: 11pt;';
            }

            if ( isset($atts['buttonhovertextcolor']) ) 
            { 
                $buttonhovertextcolor = $atts['buttonhovertextcolor'];
                $buttonhovertextcolor = 'color: '.$buttonhovertextcolor.'!important;';
            }
            else
            {
                $buttonhovertextcolor = '';
            }

            if ( isset($atts['buttonhoverbgcolor']) ) 
            { 
                $buttonhoverbgcolor = $atts['buttonhoverbgcolor'];
                $buttonhoverbgcolor = 'background-color: '.$buttonhoverbgcolor.'!important;';
            }
            else
            {
                $buttonhoverbgcolor = '';
            }

            if ( isset($atts['buttonhoverbordercolor']) ) 
            { 
                $buttonhoverbordercolor = $atts['buttonhoverbordercolor'];
                $buttonhoverbordercolor = 'border: 1px solid '.$buttonhoverbordercolor.'!important;';
            }
            else
            {
                $buttonhoverbordercolor = '';
            }

            if ( isset($atts['buttonsize']) ) 
            { 
                $buttonsize = $atts['buttonsize'];
                switch ($buttonsize) 
                {
                    case 'extralarge':
                        $buttonsize = 'padding: 25px!important; font-size: 23pt;';
                    break;

                    case 'large':
                        $buttonsize = 'padding: 18px!important; font-size: 18pt;';
                    break;

                    case 'medium':
                        $buttonsize = 'padding: 10px!important; font-size: 13pt;';
                    break;

                    case 'small':
                        $buttonsize = 'padding: 6px!important; font-size: 10pt;';
                    break;
                }
            }
            else
            {
                $buttonsize = 'padding: 10px!important; font-size: 13pt;';
            }

            // Form Style - Responsible for the full width or side by side form style
            $default = '#pp-loginform .login-username LABEL
                {
                    max-width: 100%!important;
                    width: 100%!important;
                }
                #pp-loginform .login-username INPUT
                {
                    max-width: 100%!important;
                    width: 100%!important;
                }
                #pp-loginform .login-password LABEL
                {
                    max-width: 100%!important;
                    width: 100%!important;
                }
                #pp-loginform .login-password INPUT
                {
                    max-width: 100%!important;
                    width: 100%!important;
                }
                #pp-loginform .login-remember [type="checkbox"]
                {
                    opacity: 1;
                    position: static;
                    pointer-events: auto;
                }';

            if ( isset($atts['style']) ) 
            { 
                $style = $atts['style'];

                if ( $style == 'default' ) 
                {
                    $style = $default;
                }
                else if ( $style == 'fullwidth' ) 
                {
                    $style = $default . '.op-login-form { max-width: 100%!important; }';
                }
            }
            else
            {
                $style = $default;
            }

            
            // TEXT - Options to change the form text
            if ( isset($atts['headertext']) ) 
            { 
                $headertext = $atts['headertext'];
            }
            else
            {
                $headertext = '';
            }

            if ( isset($atts['supportingtext']) ) 
            { 
                $supportingtext = $atts['supportingtext'];
            }
            else
            {
                $supportingtext = '';
            }

            if ( isset($atts['usernametext']) ) 
            { 
                $usernametext = $atts['usernametext'];
                $usernametext = __($usernametext);
            }
            else
            {
                $usernametext = __('Username');
            }

            if ( isset($atts['passwordtext']) ) 
            { 
                $passwordtext = $atts['passwordtext'];
                $passwordtext = __($passwordtext);
            }
            else
            {
                $passwordtext = __('Password');
            }

            if ( isset($atts['remembertext']) ) 
            { 
                $remembertext = $atts['remembertext'];
                $remembertext = __($remembertext);
            }
            else
            {
                $remembertext = __('Remember me');
            }

            if ( isset($atts['buttontext']) ) 
            { 
                $buttontext = $atts['buttontext'];
                $buttontext = __($buttontext);
            }
            else
            {
                $buttontext = __('Log In');
            }

            
            // New style for the [login_page] forms with variables for user customization
            $output = "<style type='text/css'>
                .op-login-form-".$this->incrementalnumber."
                {
                    ".$formalign."
                    padding: 30px;
                    box-sizing: border-box;
                    -webkit-box-sizing: border-box;
                    -moz-box-sizing: border-box;
                    -moz-box-shadow: 0px 0px 2px 1px rgba(51,51,51,0.27);
                    -webkit-box-shadow: 0px 0px 2px 1px rgba(51,51,51,0.27);
                    box-shadow: 0px 0px 2px 1px rgba(51, 51, 51, 0.27);
                    -ms-filter: 'progid:DXImageTransform.Microsoft.Glow(Color=#ff333333,Strength=3)';
                    filter: progid:DXImageTransform.Microsoft.Glow(Color=#ff333333,Strength=3);
                    ".$bgcolor."
                    ".$width."
                }
                .op-login-form-".$this->incrementalnumber." .op-header-text-container
                {
                    margin-bottom: 25px;
                    width: 100%;
                    ".$headertextalignment."
                }
                .op-login-form-".$this->incrementalnumber." .op-header-text
                {
                    line-height: 1.2!important;
                    margin-bottom: 4px;".$headertextfont.$headertextfontsize.$headertextfontcolor."
                }
                .op-login-form-".$this->incrementalnumber." .op-supporting-text
                {
                    line-height: 1.2!important;".$supportingtextfont.$supportingtextfontsize.$supportingtextfontcolor."
                }
                .op-login-form-".$this->incrementalnumber." #pp-loginform P
                {
                    width: 100%;
                    display: table;
                    margin: 0px 0px 4px;
                    padding: 0px;
                }
                .op-login-form-".$this->incrementalnumber." LABEL,
                .op-login-form-".$this->incrementalnumber." INPUT
                {
                    display: table-cell;
                    box-sizing: border-box;
                    -webkit-box-sizing: border-box;
                    -moz-box-sizing: border-box;
                    line-height: 1.3;
                }
                .op-login-form-".$this->incrementalnumber." .login-username
                {
                    position: relative;
                }
                .op-login-form-".$this->incrementalnumber." .login-username LABEL
                {
                    width: 100%;
                    max-width: 25%;
                    min-width: 90px;
                    padding-right: 3%;
                    float: left;".$textcolor."
                }
                .op-login-form-".$this->incrementalnumber." .login-username INPUT
                {
                    width: 100%;
                    max-width: 72%;
                    float: right;
                    border-radius: 3px;".$inputcolor.$inputtextcolor.$inputbordercolor.$inputfieldsize."
                }
                .op-login-form-".$this->incrementalnumber." .login-password LABEL
                {
                    width: 100%;
                    max-width: 25%;
                    min-width: 90px;
                    padding-right: 3%;
                    float: left;".$textcolor."
                }
                .op-login-form-".$this->incrementalnumber." .login-password INPUT
                {
                    width: 100%;
                    max-width: 72%;
                    float: right;
                    border-radius: 3px;".$inputcolor.$inputtextcolor.$inputbordercolor.$inputfieldsize."
                }
                .op-login-form-".$this->incrementalnumber." .login-remember
                {
                    text-align: right;
                    font-style: italic;
                    cursor: pointer;".$textcolor."
                }
                .op-login-form-".$this->incrementalnumber." .login-remember INPUT
                {
                    float: right;
                    margin-left: 10px;
                    margin-top: 5px;
                    cursor: pointer;
                }
                .op-login-form-".$this->incrementalnumber." .login-remember LABEL
                {
                    cursor: pointer;".$textcolor."
                }
                .op-login-form-".$this->incrementalnumber." #wp-submit
                {
                    width: 100%;
                    padding: 10px;
                    margin-top: 15px;
                    margin-bottom: 0px;
                    white-space: pre-wrap;
                    border-radius: 3px;".$buttonbgcolor.$buttontextcolor.$buttonbordercolor.$buttonfont.$buttonfontsize.$buttonsize."
                }
                .op-login-form-".$this->incrementalnumber." #wp-submit:hover
                {
                    transition: background-color 1s ease, color 1s ease;
                    -moz-transition: background-color 1s ease, color 1s ease;
                    -webkit-transition: background-color 1s ease, color 1s ease;".$buttonhovertextcolor.$buttonhoverbgcolor.$buttonhoverbordercolor."
                }
                .op-login-form-".$this->incrementalnumber." .login_box
                {
                    margin-top: 6px;
                    padding: 5px;
                    border: 1px solid #E6D855;
                    background-color: #FFFFE0;
                    box-sizing: border-box;
                    -webkit-box-sizing: border-box;
                    -moz-box-sizing: border-box;
                }
                @media screen and (max-width: 480px) 
                {
                    .op-login-form-".$this->incrementalnumber." .login-username LABEL
                    {
                        max-width: 100%!important;
                    }
                    .op-login-form-".$this->incrementalnumber." .login-username INPUT
                    {
                        max-width: 100%!important;
                    }
                    .op-login-form-".$this->incrementalnumber." .login-password LABEL
                    {
                        max-width: 100%!important;
                    }
                    .op-login-form-".$this->incrementalnumber." .login-password INPUT
                    {
                        max-width: 100%!important;
                    }
                }
                ".$style."
                </style>";

            // Start Form output
            $output .= '<div class="op-login-form-'.$this->incrementalnumber.'">';

            // Setting header text
            if ( isset($atts['headertext']) || isset($atts['supporting']) ) 
            { 
                $output .= '<div class="op-header-text-container"><div class="op-header-text">'.$headertext.'</div><div class="op-supporting-text">'.$supportingtext.'</div></div>';
            }

            if(!empty($message)) 
            {
                switch($message) 
                {
                    case "1":
                        $output_message = "Must be logged in to see this page.";
                    break;
                    case "2":
                        $output_message = "You do not have sufficient access to view this page.";
                    break;
                    case "3":
                        $output_message = "Invalid Username or Password.";
                    break;
                    default:
                        $output_message = $message;
                    break;
                }
                $output .= "<p class='login_box' id='login_message_normal'>{$output_message}</p>";
            }
            
            if ( isset($atts['redirect']) ) 
            { 
                $redirect = $atts['redirect'];
            }
            else
            {
                $contact_id = self::validatePostVar($_COOKIE["contact_id"], "numeric");
                $redirect_to = get_transient("pilotpress_redirect_to". (int) $contact_id);
                if(isset($redirect_to) && !empty($redirect_to)) 
                {
                    $redirect = get_permalink($redirect_to);
                } 
                else 
                {
                    $redirect = (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
                }

            }

            $args = array(
                    'echo' => false,
                    'redirect' => $redirect, 
                    'form_id' => 'pp-loginform',
                    'label_username' => $usernametext,
                    'label_password' => $passwordtext,
                    'label_remember' => $remembertext,
                    'label_log_in' => $buttontext,
                    'id_username' => 'user_login',
                    'id_password' => 'user_pass',
                    'id_remember' => 'rememberme',
                    'id_submit' => 'wp-submit',
                    'remember' => true,
                    'value_username' => NULL,
                    'value_remember' => true);          
            $output .= wp_login_form($args);

            // Adds functionality for Lost Passwords
            if ( isset($atts['forgotpw']) && $atts['forgotpw'] == 'false')
            {
                $output .= '</div>';

                $this->incrementalnumber++;
                    
                return $output;
            }
            else
            {
                $output .= '<div class="pp-lf-forgot-username" style="text-align: right;"><a id="pp-lf-forgotpw" href="javascript://">Forgot password?</a></div>';

                $output .= '<script>
                        jQuery(".op-login-form-'.$this->incrementalnumber.' #pp-lf-forgotpw").click(function()
                            { 
                                jQuery(".op-login-form-'.$this->incrementalnumber.' .login-password, .op-login-form-'.$this->incrementalnumber.' .login-remember").hide(300);
                                jQuery(".op-login-form-'.$this->incrementalnumber.' #pp-loginform").attr( "action", "'.site_url().'/wp-login.php?action=lostpassword&wpe-login=true");
                                jQuery(".op-login-form-'.$this->incrementalnumber.' .login-username label").text("Enter your Username or Email");
                                jQuery(".op-login-form-'.$this->incrementalnumber.' .login-username input").attr("name", "user_login");
                                jQuery(".op-login-form-'.$this->incrementalnumber.' #wp-submit").attr("value", "Get New Password");
                            });
                        </script>';
            }

            $output .= '</div>';

            $this->incrementalnumber++;
                
            return $output;

        }


        /**
         * @brief clean up quotes and double quotes for shortcodes
         * @arg string str
         * @return string that's been cleaned
         */
        public function quote_escaping($str)
        {
            $str = str_replace("'", "|pp_single_quote|", $str);
            $str = str_replace('"', "|pp_double_quote|", $str);
            return $str;
        }

        /**
         * @brief undo the quote_escaping function
         * @arg string str
         * @return string that is back to normal
         */
        public function undo_quote_escaping($str)
        {
            if(strpos($str, "|pp_single_quote|"))
            {
                $str = str_replace("|pp_single_quote|", "'", $str);
            }
            else if(strpos($str, "|pp_double_quote|"))
            {
                $str = str_replace("|pp_double_quote|", '"', $str);
            }

            return $str;
        }

        /**
         * @brief output the various merge fields available to our tinyMCE plugin
         * @return jsonified array back to JS
         **/
        public function grab_mce_fields()
        {
            $site_settings = $this->get_stashed("get_site_settings", false);
            $fields = $site_settings["get_site_settings"]["default_fields"];

            global $wp_version;
            $version = 3.9;
            // test for wordpress version to load proper plugin scripts
            if ( version_compare( $wp_version, $version, '>=' ))
            {
                $json = array();
                $keys = array();
                $values = array();
                if (is_array($fields))
                {
                    foreach ($fields as $group => $items)
                    {
                        $keys[] = $group;
                        $values[] = '';
                        if (is_array($items))
                        {
                            foreach ($items as $key => $value)
                            {
                                $keys[] = ' + '.$key;
                                $values[] = $this->quote_escaping($key);
                            }    
                        }
                    }    
                }
                

                $i = 0;
                foreach ($keys as $key)
                {
                    $json[] = array('text' => $key, 'value' => $values[$i]);
                    $i++;
                }

                $jsonified =json_encode($json);
                $js_to_echo =   "
                                <!--FIELDS FOR PILOTPRESS MCE PLUGIN -->
                                <script type='text/javascript'>
                                    var pilotpress_tiny_mce_plugin_default_fields = $jsonified;
                                </script>
                                <!--END FIELDS FOR PILOTPRESS MCE PLUGIN -->";
                echo $js_to_echo;
            }
            else
            {
                $jsonified = json_encode($fields);
                $js_to_echo =   "
                                <!--SHORTCODES FOR PILOTPRESS MCE PLUGIN -->
                                <script type='text/javascript'>
                                    var pilotpress_tiny_mce_plugin_default_fields_old = $jsonified;
                                </script>
                                <!--END SHORTCODES FOR PILOTPRESS MCE PLUGIN -->";
                echo $js_to_echo;
            }
        }

        /**
         * @brief output the various shortcodes available to our tinyMCE plugin
         * @return jsonified array back to JS
         **/
        public function grab_mce_shortcodes()
        {
            $shortcodes = array("","has_one=\"\"", "has_all=\"\"", "not_one=\"\"", "not_any=\"\"", "has_tag=\"\"", "does_not_have_tag=\"\"", "is_contact", "not_contact", "is_cookied_contact", "not_cookied_contact", "pilotpress_sync_contact");
            $names = array("--shortcodes--", " + Has one", " + Has all", " + Does not have one", " + Does not have any", " + Has tag(s)", " + Does not have tag(s)", " + Is a contact", " + Is not a contact", " + Is a cookied contact", " + Is not a cookied contact", " + Resync contact");
            $json = array();
            $i = 0;
            foreach ($shortcodes as $shortcode)
            {
                $json[] = array("text" => $names[$i], "value" => $shortcode);
                $i++;
            }
            $jsonified = json_encode($json);
            $js_to_echo =   "
                                <!--FIELDS FOR PILOTPRESS MCE PLUGIN -->
                                <script type='text/javascript'>
                                    var pilotpress_tiny_mce_plugin_shortcodes = $jsonified;
                                </script>
                                <!--END FIELDS FOR PILOTPRESS MCE PLUGIN -->";
            echo $js_to_echo;
        }

        public function include_form_admin_options () 
        {
            include_once(plugin_dir_path(__FILE__) . "/login-button.php");
        }

        public function register_login_button ( $buttons ) 
        {
            array_push( $buttons, "|", "addloginform" );
            return $buttons;
        }

        public function add_login_button ( $plugin_array ) 
        {
           $plugin_array['addloginform'] = plugins_url( '/js/login-button.js' , __FILE__ );
           return $plugin_array;
        }

        public function pp_login_button () 
        {
            if ( ! current_user_can('edit_posts') && ! current_user_can('edit_pages') ) 
            {
                return;
            }
            if ( get_user_option('rich_editing') == 'true' ) 
            {
                add_filter( 'mce_external_plugins', array(&$this, 'add_login_button') );
                add_filter( 'mce_buttons_3', array(&$this, 'register_login_button') );
            }
        }

        
        /* the first process... enable the plugin create some values and cleanup "older" PilotPress metadata. could probably do with a redo. */
        public function do_enable() {

            global $wpdb;

            $data = array();
            $data["site"] = site_url();
            $data["version"] = self::VERSION;
            $data["url"] = $this->uri."/".basename(__FILE__);
            $api_result = $this->api_call("enable_pilotpress", $data);

            $um = array();
            $user = get_userdatabylogin("pilotpress-user");
            if(isset($user->ID)) {
                wp_delete_user($user->ID);
            }

            $meta = $wpdb->get_results("SELECT meta_id, post_id, meta_key, meta_value FROM $wpdb->postmeta WHERE meta_key LIKE '".PilotPress::NSPACE."%'");
            foreach($meta as $result) {

                if($result->meta_key == "_pilotpress_system_page" && $result->meta_value == "1") {
                    delete_post_meta($result->post_id, $result->meta_key);
                }

                if($result->meta_key == "_pilotpress_affiliate_center") {
                    $ac_exists = $wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_pilotpress_system_page' AND meta_value = 'affiliate_center'");

                    if(empty($ac_exists)) {
                        delete_post_meta($result->post_id, $result->meta_key);
                        add_post_meta($post_id, PilotPress::NSPACE."system_page", "affiliate_center");
                        wp_update_post(array("ID" => $post_id, "post_content" => "This content will be replaced by the Partner Center."));
                    }
                }

                if($result->meta_key == "_pilotpress_customer_center") {
                    $cc_exists = $wpdb->get_var("SELECT post_id FROM $wpdb->postmeta WHERE meta_key = '_pilotpress_system_page' AND meta_value = 'customer_center'");

                    if(empty($cc_exists)) {
                        delete_post_meta($result->post_id, $result->meta_key);
                        add_post_meta($post_id, PilotPress::NSPACE."system_page", "customer_center");
                        wp_update_post(array("ID" => $post_id, "post_content" => "This content will be replaced by the Customer Center."));
                    }
                }

                if($result->meta_key == "_pilotpress_user_level") {
                    if($result->meta_value == "All") {
                        delete_post_meta($result->post_id, $result->meta_key);
                    } else {
                        $um[$result->meta_value][] = $result->post_id;
                    }
                }
            }

            if(isset($api_result["upgrade"])) {
                $levels = $this->get_setting("membership_levels", "oap");
                $keys = array_flip($levels);                                
                if(count($um) > 0) {
                    foreach($keys as $level => $pos) {
                        $rec = array_slice($levels, $pos);
                        if(isset($um[$level])) {
                            foreach($um[$level] as $idx => $post_id) {
                                foreach($rec as $value) {
                                    add_post_meta($post_id, PilotPress::NSPACE."level", $value);
                                }
                                delete_post_meta($post_id, PilotPress::NSPACE."user_level");
                            }
                        }
                    }
                }
            }
        }
    
        /* let us know */
        public function do_disable() {
            $data = array();
            $data["site"] = site_url();
            $data["version"] = self::VERSION;
            $data["url"] = $this->uri."/".basename(__FILE__);
            $return = $this->api_call("disable_pilotpress", $data);
        }

        public static function redirect($url) {
            // Workaround for trac bug #21602
            $current_url = $_SERVER["HTTP_HOST"] . $_SERVER["REQUEST_URI"];

            if(substr($current_url, -1) == "/") {
                $current_url = substr($current_url, 0, -1);
            }
            $compare_url = str_replace("https://", "", $url);
            $compare_url = str_replace("http://", "", $compare_url);
            
            if($current_url != $compare_url) {
                return wp_redirect($url);
            }
        }
    
        /* used by external plugins: get items available to this account */
        static function get_oap_items() {
            $options = get_option("pilotpress-settings");
            if(!empty($options)) {
                if(isset($options["app_id"]) && isset($options["api_key"])) {
                    $return = array();
                    $return["files"] = PilotPress::api_call_static("get_files_list", "", $options["app_id"], $options["api_key"], $options["disablesslverify"]);
                    $return["videos"] = PilotPress::api_call_static("get_video_list", "", $options["app_id"], $options["api_key"], $options["disablesslverify"]);
                    return $return;
                }
            }
            return false;
        }
        
        /* grab some video code! also for external plugins */
        static function get_oap_video($video_id) {
            $options = get_option("pilotpress-settings");
            if(!empty($options)) {
                if(isset($options["app_id"]) && isset($options["api_key"])) {
                    $return= PilotPress::api_call_static("get_video", 
                                                        array(
                                                            "video_id" => $video_id, 
                                                            "width" => '480', 
                                                            "height" => "320", 
                                                            "player" => 1, 
                                                            "autoplay" => 0, 
                                                            "viral" => 0
                                                        ), 
                                                        $options["app_id"], 
                                                        $options["api_key"], 
                                                        $options["disablesslverify"]);
                    return $return;
                }
            }
            return false;
        }

        /**
         * @brief generates a unique session ID
         * @params int $length
         * @return int $session (unique ID)
         **/
        public function genmrSess($length)
        {
            $session = "";
            $possible = "0123456789bcdfghjkmnpqrstvwxyz";
            $i = 0;
            while ($i < $length)
            {
                $mychar = substr($possible, rand(0, strlen($possible)), 1);
                $session .= $mychar;
                $i++;
            }
            return $session;
        }
    }


    //Since we ping this file independent of the WordPress bootstrap lets make sure the class exists...
    if (class_exists("WP_Widget"))
    {
        // Creating the widget 
        class Pilotpress_Widget extends WP_Widget {

            //Registers the widget with the WordPress Widget API.
            public static function register() {
                register_widget( __CLASS__ );
            }

            public function __construct() {

                parent::__construct(
                    // Base ID of your widget
                    'pilotpress_widget', 

                    // Widget name will appear in UI
                    __('PilotPress Text', 'pilotpress_widget_domain'), 

                    // Widget description
                    array( 
                        'description' => __( 'An enhanced text area widget that helps you display your ONTRAPORT merge fields', 'pilotpress_widget_domain' )
                    )
                );
            }

            // Creating widget front-end
            public function widget( $args, $instance ) {
                global $pilotpress;
                $title = apply_filters( 'widget_title', $instance['title'] );
                
                $textarea =  $instance["textarea"];
                // before and after widget arguments are defined by themes
                echo $args['before_widget'];
                if ( ! empty( $title ) )
                {
                    echo $args['before_title'] . $title . $args['after_title'];
                }
                //Lets check and process merge fields if we need too!
                if (has_shortcode( $textarea , "pilotpress_field") || has_shortcode( $textarea ,"field")  )
                {
                    $pilotpress->get_merge_field_settings($textarea);
                }
                //Apply the default filter in case they have somehting to make PHP work...
                echo apply_filters( 'widget_text' , do_shortcode($textarea) );
                
                echo $args['after_widget'];
            }
                    
            // Widget Backend 
            public function form( $instance ) {
                global $pilotpress;

                //Handle merge codes
                $mergeFieldDropDown = "<p>";
                $mergeFieldDropDown .= "<label for='" . $this->get_field_id( "merge-codes" ) ."'>" . __("Merge Fields:", "pilotpress_widget_domain") . "</label>";
                $mergeFieldDropDown .= "<select id='" . $this->get_field_id( "merge-codes" ) . "' class='op-merge-codes__select' name='" . $this->get_field_id( "merge-codes" ) . "'>";
                $mergeFieldDropDown .= "</p>";

                foreach($pilotpress->get_setting("default_fields", "oap", true) as $group => $fields) {
                    
                    $mergeFieldDropDown .= "<option value=''>  " . $group . "</option>";
                    foreach ($fields as $key => $field)
                    {
                        
                        $mergeFieldDropDown .= "<option value='[pilotpress_field name=\"{$key}\"]'>&nbsp;&nbsp;&nbsp;" . $key . "</option>";
                    }
                }

                $mergeFieldDropDown .= "</select>";

                if ( isset( $instance[ 'title' ] ) ) {
                    $title = $instance[ 'title' ];
                }
                else {
                    $title = __( '', 'pilotpress_widget_domain' );
                }
                
                if (isset( $instance[ 'textarea' ] )) {
                    $textarea = $instance[ 'textarea' ];
                }
                else {
                    $textarea = __( '', 'pilotpress_widget_domain' );
                }

                $titleText = "<p>";
                $titleText .= "<label for='" . $this->get_field_id( 'title' ) ."'>". __( 'Title:' ) ."</label>";
                $titleText .= "<input class='widefat' id='". $this->get_field_id( 'title' ) ."' name='". $this->get_field_name( 'title' )."' type='text' value='". esc_attr( $title )."' />";
                $titleText .= "</p>";

                $textAreaText = "<p>";
                $textAreaText .= "<textarea class='widefat' id='". $this->get_field_id( 'textarea' )."' name='" . $this->get_field_name( 'textarea' ) ."' rows='16' cols='20'>". esc_attr( $textarea ) ."</textarea>";
                $textAreaText .= "</p>";

                //echo out the actual widget content block
                echo $mergeFieldDropDown;
                echo $titleText;
                echo $textAreaText;
                
            }
                
            // Updating widget replacing old instances with new
            public function update( $new_instance, $old_instance ) {
                $instance = array();
                $instance['title'] = ( ! empty( $new_instance['title'] ) ) ? strip_tags( $new_instance['title'] ) : '';
                $instance['textarea'] = ( ! empty( $new_instance['textarea'] ) ) ?  $new_instance['textarea']  : '';
                return $instance;
            }
        } // Class pilotpress_widget ends here

    }

    function pilotpress_widget_js() {
        $widgetJavascript = "
            <script type='text/javascript'>
                jQuery( document ).ready( function(){
                        jQuery( 'body' ).on( 'change', 'select.op-merge-codes__select', function( ev ) {
                            var textarea = jQuery(this).closest( 'form' ).find( 'textarea' );
                            textarea.val(textarea.val() + jQuery(this).val());
                        } );
                 } );
            </script>
        ";
        echo $widgetJavascript;
    }

    function enable_pilotpress() {
        $pilotpress = new PilotPress;
        $pilotpress->do_enable();
    }
    
    function disable_pilotpress() {
        $pilotpress = new PilotPress;
        $pilotpress->do_disable();
    }


