<?php
/**
 * Plugin Name:       Ultimate member addon for custom messages
 * Plugin URI:        https://odesdigital.com
 * Description:       This plugin overrides messages in ultimate member
 * Author: Shabbar Abbas
 * Author URI: shabbarabbasodes@gmail.com
 * Version: 1.0.0
 * Text Domain: 'umcm'
 * Domain Path: languages
 * License: GPL2 or later.
 */

/**
 * Main Class.
 *
 * @since 1.0.0
 */
final class Ultimate_Member_Custom_Messages
{
    /**
     * @var The one true instance
     * @since 1.0.0
     */
    protected static $_instance = null;

    public $version = '1.0.0';

    /**
     *
     * @since 2.0.0
     */
    public function __construct()
    {
        $this->define_constants();
        $this->includes();
        $this->localisation();
        $this->init();

        do_action('umcm_loaded');
    }

    /**
     * Define Constants.
     * @since  1.0.0
     */
    private function define_constants()
    {
        $this->define('ULTIMATE_MEMBER_CUSTOM_MESSAGES_DIR', plugin_dir_path(__FILE__));
        $this->define('ULTIMATE_MEMBER_CUSTOM_MESSAGES_URL', plugin_dir_url(__FILE__));
        $this->define('ULTIMATE_MEMBER_CUSTOM_MESSAGES_BASENAME', plugin_basename(__FILE__));
        $this->define('ULTIMATE_MEMBER_CUSTOM_MESSAGES_VERSION', $this->version);
    }

    /**
     * Define constant if not already set.
     * @since  1.0.0
     */
    private function define($name, $value)
    {
        if (!defined($name)) {
            define($name, $value);
        }
    }

    /**
     * Include required files.
     * @since  1.0.0
     */
    public function includes()
    {
        include_once 'includes/class-worker.php';
        include_once 'includes/functions.php';
    }

    /**
     * Load Localisation files.
     * @since  1.0.0
     */
    public function localisation()
    {
        $locale = apply_filters('plugin_locale', get_locale(), 'umcm');

        load_textdomain('umcm', WP_LANG_DIR . '/ultimate-member-custom-messages/ultimate-member-custom-messages-' . $locale . '.mo');
        load_plugin_textdomain('umcm', false, plugin_basename(dirname(__FILE__)) . '/languages');
    }

    public function init()
    {
        register_activation_hook(__FILE__, [$this, 'activate']);

        add_action('plugins_loaded', [$this, 'upgrade']);
    }

    /**
     * Main Instance.
     */
    public static function instance()
    {
        if (is_null(self::$_instance)) {
            self::$_instance = new self();
        }
        return self::$_instance;
    }

    /**
     * Throw error on object clone.
     *
     * @return void
     * @since 1.0.0
     * @access protected
     */
    public function __clone()
    {
        _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?', 'umcm'), '1.0.0');
    }

    /**
     * Disable unserializing of the class.
     * @since 1.0.0
     */
    public function __wakeup()
    {
        _doing_it_wrong(__FUNCTION__, __('Cheatin&#8217; huh?', 'umcm'), '1.0.0');
    }

    /**
     * responsible to do actions on activation
     */
    public function activate()
    {

    }

    /**
     * responsible to upgrade plugin
     */
    public function upgrade()
    {

    }
}


/**
 * Run the plugin.
 */
function umcm()
{
    return Ultimate_Member_Custom_Messages::instance();
}

umcm();