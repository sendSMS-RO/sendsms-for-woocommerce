<?php
/*
Plugin Name: SendSMS for WooCommerce
Plugin URI: https://www.sendsms.ro/ro/ecommerce/plugin-woocommerce/
Description: Send SMS notifications to your customers on every WooCommerce order status change. Per-status templates, campaign sender, single-order SMS, full history.
Version: 1.0.0
Author: sendSMS
License: GPLv2
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Domain Path: /languages/
*/

defined( 'ABSPATH' ) || exit;

global $wc_sendsms_db_version;
$wc_sendsms_db_version = '1.2.8';

if (!function_exists('is_plugin_active_for_network')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$wc_sendsms_need_wc = false;
if (is_multisite()) {
    if (is_plugin_active_for_network(plugin_basename(__FILE__))) {
        // Plugin is network-activated: WooCommerce must also be network-activated.
        $wc_sendsms_need_wc = !is_plugin_active_for_network('woocommerce/woocommerce.php');
    } else {
        // Plugin is activated on a single site; WooCommerce can be network- or site-active.
        $wc_sendsms_need_wc = !is_plugin_active('woocommerce/woocommerce.php');
    }
} else {
    $wc_sendsms_need_wc = !is_plugin_active('woocommerce/woocommerce.php');
}

if ($wc_sendsms_need_wc === true) {
    return;
}

# Declare HPOS (custom order tables) compatibility so WooCommerce stops
# flagging this plugin on Settings -> Advanced -> Features.
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});

# history table
if (!class_exists('WP_List_Table')) {
    require_once(ABSPATH . 'wp-admin/includes/class-wp-list-table.php');
}

include 'HistoryListTable.php';
# create database
function wc_sendsms_install()
{
    global $wpdb;
    global $wc_sendsms_db_version;

    $table_name = $wpdb->prefix . 'wcsendsms_history';
    $charset_collate = $wpdb->get_charset_collate();
    $installed_ver = get_option('wc_sendsms_db_version');

    if ($installed_ver != $wc_sendsms_db_version) {
        $sql = "CREATE TABLE `$table_name` (
          `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
          `phone` varchar(255) DEFAULT NULL,
          `status` varchar(255) DEFAULT NULL,
          `message` varchar(255) DEFAULT NULL,
          `details` longtext,
          `content` longtext,
          `type` varchar(255) DEFAULT NULL,
          `sent_on` datetime DEFAULT NULL,
          PRIMARY KEY (`id`)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        add_option('wc_sendsms_db_version', $wc_sendsms_db_version);
    }
}
register_activation_hook(__FILE__, 'wc_sendsms_install');

add_action('init', 'wc_sendsms_load_textdomain');

/**
 * Load plugin textdomain.
 */
function wc_sendsms_load_textdomain()
{
    load_plugin_textdomain('sendsms-for-woocommerce', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

# update db structure
function wc_sendsms_update_db_check()
{
    global $wc_sendsms_db_version;
    if (get_option('wc_sendsms_db_version') != $wc_sendsms_db_version) {
        wc_sendsms_install();
    }
}
add_action('plugins_loaded', 'wc_sendsms_update_db_check');

# add scripts
function wc_sendsms_load_scripts($hook)
{
    // WooCommerce already registers these, just enqueue them on our admin pages.
    wp_enqueue_style('woocommerce_admin_styles');
    wp_enqueue_script('wc-enhanced-select');
}
add_action('admin_enqueue_scripts', 'wc_sendsms_load_scripts');

#  checkout field for opt-out
function wc_sendsms_optout($checkout)
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['optout'])) {
        $optout = $options['optout'];
    } else {
        $optout = '';
    }
    if (!empty($optout)) {
        echo '<div>';
        woocommerce_form_field('wc_sendsms_optout', array(
            'type' => 'checkbox',
            'class' => array('input-checkbox', 'form-row-wide'),
            'label' => __('&nbsp;I do not want to receive an SMS with the status of the order', 'sendsms-for-woocommerce'),
        ), $checkout->get_value('wc_sendsms_optout'));
        echo '</div><div style="clear: both">&nbsp;</div>';
    }
}
add_action('woocommerce_after_order_notes', 'wc_sendsms_optout');

function wc_sendsms_optout_update_order_meta($orderId)
{
    // Nonce is verified by WooCommerce's checkout flow before this action fires.
    // phpcs:ignore WordPress.Security.NonceVerification.Missing
    if (isset($_POST['wc_sendsms_optout'])) {
        $order = wc_get_order($orderId);
        if ($order) {
            // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            $order->update_meta_data('wc_sendsms_optout', wc_sendsms_sanitize_bool($_POST['wc_sendsms_optout']));
            $order->save();
        }
    }
}
add_action('woocommerce_checkout_update_order_meta', 'wc_sendsms_optout_update_order_meta');

# admin page
add_action('admin_menu', 'wc_sendsms_add_menu');

function wc_sendsms_add_menu()
{
    add_menu_page(
        __('SendSMS', 'sendsms-for-woocommerce'),
        __('SendSMS', 'sendsms-for-woocommerce'),
        'manage_options',
        'wc_sendsms_main',
        'wc_sendsms_main',
        plugin_dir_url(__FILE__) . 'images/sendsms.png'
    );

    add_submenu_page(
        'wc_sendsms_main',
        __('Configuration', 'sendsms-for-woocommerce'),
        __('Configuration', 'sendsms-for-woocommerce'),
        'manage_options',
        'wc_sendsms_login',
        'wc_sendsms_login'
    );

    add_submenu_page(
        'wc_sendsms_main',
        __('History', 'sendsms-for-woocommerce'),
        __('History', 'sendsms-for-woocommerce'),
        'manage_options',
        'wc_sendsms_history',
        'wc_sendsms_history'
    );

    add_submenu_page(
        'wc_sendsms_main',
        __('Campaign', 'sendsms-for-woocommerce'),
        __('Campaign', 'sendsms-for-woocommerce'),
        'manage_options',
        'wc_sendsms_campaign',
        'wc_sendsms_campaign'
    );

    add_submenu_page(
        'wc_sendsms_main',
        __('Send a test', 'sendsms-for-woocommerce'),
        __('Send a test', 'sendsms-for-woocommerce'),
        'manage_options',
        'wc_sendsms_test',
        'wc_sendsms_test'
    );
}

function wc_sendsms_main()
{
?>
    <div class="wrap">
        <h2><?php echo esc_html('SendSMS for WooCommerce', 'sendsms-for-woocommerce') ?></h2>
        <br />
        <p><?php echo esc_html('To use the module, please enter your credentials on the configuration page.', 'sendsms-for-woocommerce') ?></p><br />
        <p><?php echo esc_html('You don\'t have a sendSMS account?', 'sendsms-for-woocommerce') ?><br />
            <?php echo esc_html('Sign up for FREE', 'sendsms-for-woocommerce') ?> <a href="http://www.sendsms.ro/ro" target="_blank"><?php echo esc_html('here', 'sendsms-for-woocommerce') ?></a>.<br />
            <?php echo esc_html('You can find out more about sendSMS', 'sendsms-for-woocommerce') ?> <a href="http://www.sendsms.ro/ro"><?php echo esc_html('here', 'sendsms-for-woocommerce') ?></a>.</p>
        <p><?php echo esc_html('On the settings page, below the credentials, you\'ll find a text field for each status available in WooCommerce. You will need to enter a message for the fields to which you want to send the notification. If a field is empty, then the text message will not be sent.', 'sendsms-for-woocommerce') ?></p>
        <p><?php echo esc_html('Example: If you want to send a message when the status of the order changes to Completed, then you will need to fill in a message in the text field.', 'sendsms-for-woocommerce') ?> <strong><?php echo esc_html('"Message: Completed"', 'sendsms-for-woocommerce') ?></strong>.</p><br />
        <p><?php echo esc_html('You can enter variables that will be filled in according to the order data.', 'sendsms-for-woocommerce') ?></p>
        <p><?php echo esc_html('Example message:', 'sendsms-for-woocommerce') ?> <strong><?php echo esc_html('Hi {billing_first_name}. Your order with order {order_number} has been completed.', 'sendsms-for-woocommerce') ?></strong></p>
        <p><?php echo esc_html('The message entered must not contain diacritics. If they are entered the letters with diacritics will be replaced with their equivalent without diacritics.', 'sendsms-for-woocommerce') ?></p>
        <br /><br />
        <p style="text-align: center"><a href="http://sendsms.ro" target="_blank"><img src="<?php plugin_dir_url(__FILE__) . 'images/sendsms_logo.png' ?>" /></a></p>
    </div>
<?php
}

# options
add_action('admin_init', 'wc_sendsms_admin_init');
function wc_sendsms_admin_init()
{
    # for login
    register_setting(
        'wc_sendsms_plugin_options',
        'wc_sendsms_plugin_options',
        'wc_sendsms_plugin_options_validate'
    );
    add_settings_section(
        'wc_sendsms_plugin_login',
        '',
        'wc_sendsms_plugin_login_section_text',
        'wc_sendsms_plugin'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_username',
        __('Username', 'sendsms-for-woocommerce'),
        'wc_sendsms_settings_display_username',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_password',
        __('Password / API Key', 'sendsms-for-woocommerce'),
        'wc_sendsms_settings_display_password',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_from',
        __('Shipper label', 'sendsms-for-woocommerce'),
        'wc_sendsms_settings_display_from',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_cc',
        __('Country Code', 'sendsms-for-woocommerce'),
        'wc_sendsms_settings_display_cc',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_simulation',
        __('SMS sending simulation', 'sendsms-for-woocommerce'),
        'wc_sendsms_settings_display_simulation',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_simulation_number',
        __('Simulation phone number', 'sendsms-for-woocommerce'),
        'wc_sendsms_settings_display_simulation_number',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_send_to_owner',
        __('Send an SMS to each new order', 'sendsms-for-woocommerce'),
        'wc_sendsms_settings_display_send_to_owner',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_send_to_owner_short',
        __('Short URL?', 'sendsms-for-woocommerce'),
        'wc_sendsms_settings_display_send_to_owner_short',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_send_to_owner_gdpr',
        __('Add unsubscribe link?', 'sendsms-for-woocommerce'),
        'wc_sendsms_settings_display_send_to_owner_gdpr',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_send_to_owner_number',
        __('The phone number where the messages will be sent', 'sendsms-for-woocommerce'),
        'wc_sendsms_settings_display_send_to_owner_number',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_send_to_owner_content',
        __('Message', 'sendsms-for-woocommerce'),
        'wc_sendsms_settings_display_send_to_owner_content',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_optout',
        __('Opt-out in cart', 'sendsms-for-woocommerce'),
        'wc_sendsms_settings_display_optout',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_content',
        __('Status Updates', 'sendsms-for-woocommerce'),
        'wc_sendsms_settings_display_content',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
    add_settings_field(
        'wc_sendsms_plugin_options_enabled',
        '',
        'wc_sendsms_settings_display_enabled',
        'wc_sendsms_plugin',
        'wc_sendsms_plugin_login'
    );
}

function wc_sendsms_login()
{
?>
    <div class="wrap">
        <h2><?php esc_html_e('SendSMS - Login data', 'sendsms-for-woocommerce'); ?></h2>
        <h3><?php
            $options = get_option('wc_sendsms_plugin_options');
            $username = "";
            $password = "";
            $from = "";
            wc_sendsms_get_account_info($username, $password, $from, $options);

            $results = json_decode(wp_remote_retrieve_body(wp_remote_get('https://api.sendsms.ro/json?action=user_get_balance&username=' . urlencode($username) . '&password=' . urlencode($password))), true);

            if ($results['status'] >= 0) {
                echo esc_html('You have ', 'sendsms-for-woocommerce') . esc_html($results['details']) . esc_html(' euro in your sendSMS account.', 'sendsms-for-woocommerce');
            } else {
                echo esc_html('The plugin is not configured.', 'sendsms-for-woocommerce');
            }
            ?></h3>
        <?php settings_errors(); ?>
        <form action="options.php" method="post">
            <?php settings_fields('wc_sendsms_plugin_options'); ?>
            <?php do_settings_sections('wc_sendsms_plugin'); ?>

            <input name="Submit" type="submit" class="button button-primary button-large" value="<?php echo esc_html('Save', 'sendsms-for-woocommerce') ?>" />
        </form>
    </div>
<?php
}

function wc_sendsms_get_woocommerce_product_list()
{
    $full_product_list = array();

    // Query for published products and variations
    $loop = new WP_Query(array(
        'post_type' => array('product', 'product_variation'),
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'fields' => 'ids' // Only get IDs for better performance
    ));

    if ($loop->have_posts()) {
        foreach ($loop->posts as $product_id) {
            $product = wc_get_product($product_id);

            // Skip if product object is invalid
            if (!$product) {
                continue;
            }

            // Get product details
            $product_name = $product->get_name();
            $sku = $product->get_sku();

            // If no SKU, use product ID as identifier
            if (empty($sku)) {
                $sku = 'ID-' . $product_id;
            }

            // For variations, include parent product name
            if ($product->is_type('variation')) {
                $parent_id = $product->get_parent_id();
                if ($parent_id) {
                    $parent = wc_get_product($parent_id);
                    if ($parent) {
                        $product_name = $parent->get_name() . ' - ' . $product_name;
                    }
                }
            }

            // Add to list: [name, sku, id]
            $full_product_list[] = array($product_name, $sku, $product_id);
        }
    }

    wp_reset_postdata();

    // Sort by product name
    sort($full_product_list);

    return $full_product_list;
}

function wc_sendsms_test()
{
    if (isset($_POST) && !empty($_POST)) {
        check_admin_referer('wc_sendsms_test');
        if (empty($_POST['wc_sendsms_phone'])) {
            echo '<div class="notice notice-error is-dismissible">
                <p>' . esc_html('You have not entered your phone number!', 'sendsms-for-woocommerce') . '</p>
            </div>';
        }
        if (empty($_POST['wc_sendsms_message'])) {
            echo '<div class="notice notice-error is-dismissible">
                <p>' . esc_html('You have not entered a message!', 'sendsms-for-woocommerce') . '</p>
            </div>';
        }
        if (!empty($_POST['wc_sendsms_message']) && !empty($_POST['wc_sendsms_phone'])) {
            $options = get_option('wc_sendsms_plugin_options');
            $username = '';
            $password = '';
            $short = filter_var(isset($_POST['wc_sendsms_url']) ? sanitize_text_field(wp_unslash($_POST['wc_sendsms_url'])) : "false", FILTER_VALIDATE_BOOLEAN);
            $gdpr  = filter_var(isset($_POST['wc_sendsms_gdpr']) ? sanitize_text_field(wp_unslash($_POST['wc_sendsms_gdpr'])) : "false", FILTER_VALIDATE_BOOLEAN);
            if (!empty($options) && is_array($options) && isset($options['username'])) {
                $username = $options['username'];
            }
            if (!empty($options) && is_array($options) && isset($options['password'])) {
                $password = $options['password'];
            }
            if (!empty($options) && is_array($options) && isset($options['from'])) {
                $from = $options['from'];
            }
            if (!empty($username) && !empty($password) && !empty($from)) {
                $phone = wc_sendsms_validate_phone(sanitize_text_field(wp_unslash($_POST['wc_sendsms_phone'])));
                if (!empty($phone)) {
                    wc_sendsms_send($username, $password, $phone, sanitize_textarea_field(wp_unslash($_POST['wc_sendsms_message'])), $from, 'test', $short, $gdpr);
                    echo '<div class="notice notice-success is-dismissible">
                    <p>' . esc_html('The message was sent.', 'sendsms-for-woocommerce') . '</p>
                </div>';
                } else {
                    echo '<div class="notice notice-error is-dismissible">
                    <p>' . esc_html('The validated phone number is empty!', 'sendsms-for-woocommerce') . '</p>
                </div>';
                }
            } else {
                echo '<div class="notice notice-error is-dismissible">
                    <p>' . esc_html('You have not configured the module!', 'sendsms-for-woocommerce') . '</p>
                </div>';
            }
        }
    }
?>
    <div class="wrap">
        <h2><?php esc_html_e('SendSMS - Send an SMS test', 'sendsms-for-woocommerce'); ?></h2>
        <form method="post" action="<?php admin_url('admin.php?page=wc_sendsms_test') ?>">
            <?php wp_nonce_field('wc_sendsms_test'); ?>
            <table class="form-table">
                <tbody>
                    <tr>
                        <th scope="row"><?php echo esc_html('Phone number', 'sendsms-for-woocommerce') ?></th>
                        <td><input type="text" name="wc_sendsms_phone" style="width: 400px;" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Short URL? (Please use only links starting with https:// or http://)', 'sendsms-for-woocommerce') ?></th>
                        <td><input type="checkbox" name="wc_sendsms_url" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Add unsubscribe link? (You must specify the {gdpr} key message. The {gdpr} key will be automatically replaced with the unique confirmation link. If the {gdpr} key is not specified, the confirmation link will be placed at the end of the message.)', 'sendsms-for-woocommerce') ?></th>
                        <td><input type="checkbox" name="wc_sendsms_gdpr" /></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php echo esc_html('Message', 'sendsms-for-woocommerce') ?></th>
                        <td>
                            <textarea name="wc_sendsms_message" class="wc_sendsms_content" style="width: 400px; height: 100px;"></textarea>
                            <p><?php echo esc_html("The field is empty", 'sendsms-for-woocommerce') ?></p>
                        </td>
                    </tr>
                </tbody>
            </table>
            <p style="clear: both;"><button type="submit" class="button button-primary button-large" id="wc_sendsms_send_test"><?php echo esc_html('Send the message', 'sendsms-for-woocommerce') ?></button></p>
        </form>
        <script type="text/javascript">
            document.addEventListener("DOMContentLoaded", (event) => {
                var wc_sendsms_content = document.getElementsByClassName('wc_sendsms_content')[0];

                wc_sendsms_content.addEventListener("input", (event) => {
                    lenghtCounter(event.target || event.srcElement, event.target.nextElementSibling || event.srcElement.nextElementSibling);
                });
                wc_sendsms_content.addEventListener("change", (event) => {
                    lenghtCounter(event.target || event.srcElement, event.target.nextElementSibling || event.srcElement.nextElementSibling);
                });

                function lenghtCounter(textarea, counter) {
                    var lenght = textarea.value.length;
                    var messages = lenght / 160 + 1;
                    if (lenght > 0) {
                        if (lenght % 160 === 0) {
                            messages--;
                        }
                        counter.textContent = "<?php echo esc_html('The approximate number of messages: ', 'sendsms-for-woocommerce'); ?>" + Math.floor(messages) + " (" + lenght + ")";
                    } else {
                        counter.textContent = "<?php echo esc_html('The field is empty', 'sendsms-for-woocommerce'); ?>";
                    }
                }
            });
        </script>
    </div>
<?php
}

function wc_sendsms_campaign()
{
    global $wpdb;

    # get all products
    $products = wc_sendsms_get_woocommerce_product_list();

    // Get billing states from completed orders using HPOS-compatible method
    $orders_for_states = wc_get_orders(array(
        'limit' => -1,
        'status' => 'completed',
        'type' => 'shop_order', // Exclude refunds
        'return' => 'objects'
    ));

    $billing_states_data = array(); // Array to hold state_code => state_name
    foreach ($orders_for_states as $order) {
        // Extra safety check to skip refunds
        if ($order->get_type() !== 'shop_order') {
            continue;
        }
        $state_code = $order->get_billing_state();
        $country_code = $order->get_billing_country();

        if (!empty($state_code) && !isset($billing_states_data[$state_code])) {
            // Get the full state name from WooCommerce
            $states = WC()->countries->get_states($country_code);
            $state_name = isset($states[$state_code]) ? $states[$state_code] : $state_code;
            $billing_states_data[$state_code] = $state_name;
        }
    }

    // Sort by state name
    asort($billing_states_data);

    // Convert to objects to match old format (but store both code and name)
    $billing_states = array();
    foreach ($billing_states_data as $code => $name) {
        $obj = new stdClass();
        $obj->state_code = $code; // Store the code for filtering
        $obj->meta_value = $name; // Display the full name
        $billing_states[] = $obj;
    }

    // phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only GET filter render on a manage_options page
    $orders = array();
    if (!isset($_REQUEST['filtering'])) {
        $orders = wc_sendsms_get_all_orders();
    }
    if (isset($_REQUEST['filtering']) && $_REQUEST['filtering'] === "true") {
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), "wc_sendsms_send_campaign")) {
            wp_die(esc_html__('Security check failed', 'sendsms-for-woocommerce'), '', 403);
        }
        $orders = wc_sendsms_get_orders_filtered(
            isset($_GET['perioada_start']) ? sanitize_text_field(wp_unslash($_GET['perioada_start'])) : "",
            isset($_GET['perioada_final']) ? sanitize_text_field(wp_unslash($_GET['perioada_final'])) : "",
            isset($_GET['suma']) ? sanitize_text_field(wp_unslash($_GET['suma'])) : "",
            isset($_GET['judete']) ? array_map('sanitize_text_field', wp_unslash((array) $_GET['judete'])) : array(),
            isset($_GET['produse']) ? array_map('sanitize_text_field', wp_unslash((array) $_GET['produse'])) : array()
        );
    }
    // phpcs:enable WordPress.Security.NonceVerification.Recommended

    $phones = array();
    if (count($orders)) {
        foreach ($orders as $order) {
            $phone = wc_sendsms_validate_phone($order->_billing_phone);
            if (!empty($phone)) {
                $phones[] = $phone;
            }
        }
    }
    $phones = array_unique($phones);

    // // Generate dumy phones for testing
    // $phones = array();
    // for ($i = 0; $i < 10; $i++) {
    //     $phones[] = "4021" . wc_sendsms_randomNumberSequence();
    // }

?>
    <div class="wrap">
        <h2><?php echo esc_html('SendSMS - Campaign', 'sendsms-for-woocommerce') ?></h2>

        <!-- This is the filtering form -->
        <form method="GET" action="">
            <?php
            wp_nonce_field("wc_sendsms_send_campaign");
            ?>
            <input type="hidden" name="page" value="wc_sendsms_campaign" />
            <input type="hidden" name="filtering" value="true" />
            <div style="width: 100%; clear: both;">
                <div style="width: 48%; float: left;">
                    <p><?php echo esc_html__('Period', 'sendsms-for-woocommerce'); ?> <input type="date" name="perioada_start" value="<?php echo isset($_GET['perioada_start']) ? esc_attr(wc_sendsms_sanitize_event_time(sanitize_text_field(wp_unslash($_GET['perioada_start'])))) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET filter render only ?>" /> - <input type="date" name="perioada_final" value="<?php echo isset($_GET['perioada_final']) ? esc_attr(wc_sendsms_sanitize_event_time(sanitize_text_field(wp_unslash($_GET['perioada_final'])))) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET filter render only ?>" /></p>
                </div>
                <div style="width: 48%; float: left">
                    <p><?php echo esc_html__('Minimum amount per order:', 'sendsms-for-woocommerce'); ?> <input type="number" name="suma" value="<?php
                        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- GET filter render only
                        echo isset($_GET['suma']) ? esc_attr(wc_sendsms_sanitize_float(sanitize_text_field(wp_unslash($_GET['suma'])))) : '0';
                    ?>" /></p>
                </div>
                <div style="width: 100%; clear: both;">
                    <div style="width: 48%; float: left;" class="mySelect">
                        <p><?php echo esc_html('The purchased product (leave blank to select all products):', 'sendsms-for-woocommerce') ?></p>
                        <select id="produse_selectate" name="produse[]" multiple="multiple" class="wc-enhanced-select" data-placeholder="<?php echo esc_attr('Select products...', 'sendsms-for-woocommerce'); ?>" style="width: 90%;">
                                <?php
                                // phpcs:disable WordPress.Security.NonceVerification.Recommended -- GET filter render only
                                $selected_products = isset($_GET['produse']) ? array_map('sanitize_text_field', wp_unslash((array) $_GET['produse'])) : array();
                                // phpcs:enable WordPress.Security.NonceVerification.Recommended
                                for ($i = 0; $i < count($products); $i++) {
                                    $selected = in_array("id_" . $products[$i][2], $selected_products, true);
                                ?>
                                    <option value="<?php echo "id_" . esc_attr($products[$i][2]); ?>" <?php echo $selected ? 'selected="selected"' : ''; ?>><?php echo esc_html($products[$i][0]) . " - " . esc_html($products[$i][1]); ?></option>
                                <?php
                                }
                                ?>
                            </select>
                    </div>
                    <div style="width: 48%; float: left;">
                        <p><?php echo esc_html('Billing County (leave blank to select all counties):', 'sendsms-for-woocommerce') ?></p>
                        <select id="judete_selectate" name="judete[]" multiple="multiple" class="wc-enhanced-select" data-placeholder="<?php echo esc_attr('Select counties...', 'sendsms-for-woocommerce'); ?>" style="width: 90%;">
                                <?php
                                // phpcs:disable WordPress.Security.NonceVerification.Recommended -- GET filter render only
                                $selected_states = isset($_GET['judete']) ? array_map('sanitize_text_field', wp_unslash((array) $_GET['judete'])) : array();
                                // phpcs:enable WordPress.Security.NonceVerification.Recommended
                                for ($i = 0; $i < count($billing_states); $i++) {
                                    $selected = in_array("id_" . $billing_states[$i]->state_code, $selected_states, true);
                                ?>
                                    <option value="<?php echo "id_" . esc_attr($billing_states[$i]->state_code); ?>" <?php echo $selected ? 'selected="selected"' : ''; ?>><?php echo esc_html($billing_states[$i]->meta_value); ?></option>
                                <?php
                                }
                                ?>
                            </select>
                    </div>
                </div>
            </div>
            <div style="width: 100%; clear: both; padding-top: 20px;">
                <button type="submit" class="button button-default button-large aligncenter" value="filter"><?php echo esc_html('Filter', 'sendsms-for-woocommerce') ?></button>
            </div>
        </form>


        <hr />
        <h3><?php echo esc_html('Filter results:', 'sendsms-for-woocommerce') ?> <?php echo count($phones) ?> <?php echo esc_html('phone number(s)', 'sendsms-for-woocommerce') ?></h3>

        <!-- Send campaign form -->
        <form method="POST" action="">
            <input type="hidden" name="page" value="wc_sendsms_campaign" />
            <input type="hidden" name="action" value="send_campaign" />
            <div style="width: 100%; clear: both; padding-top: 20px;">
                <div style="width: 73%; float: left">
                    <div><?php echo esc_html('Message:', 'sendsms-for-woocommerce') ?> <br />
                        <textarea name="content" class="wc_sendsms_content" id="wc_sendsms_content" style="width: 90%; height: 250px;"></textarea>
                        <p><?php echo esc_html('The field is empty', 'sendsms-for-woocommerce') ?></p>
                    </div>
                </div>
                <div style="width: 25%; float: left">
                    <p><?php echo esc_html('Phone numbers:', 'sendsms-for-woocommerce') ?> <br /></p>
                    <div style="margin-bottom: 10px">
                        <input type="checkbox" id="wc_sendsms_to_all" class="wc_sendsms_to_all" name="wc_sendsms_to_all" checked />
                        <?php echo esc_html('Send SMS to every number.', 'sendsms-for-woocommerce') ?></label>
                    </div>
                    <select name="phones[]" id="phones" multiple="MULTIPLE" class="wc-enhanced-select" data-placeholder="<?php echo esc_attr('Select phone numbers...', 'sendsms-for-woocommerce'); ?>" style="width: 90%">
                        <?php
                        if (!empty($phones)) :
                            foreach ($phones as $phone) :
                        ?>
                                <option value="<?php echo esc_attr($phone); ?>" selected><?php echo esc_html($phone); ?></option>
                        <?php
                            endforeach;
                        endif;
                        ?>
                    </select>
                </div>
            </div>
            <p style="clear: both;">
                <button type="submit" class="button button-primary button-large" id="wc_sendsms_send_campaign"><?php echo esc_html('Send the message', 'sendsms-for-woocommerce') ?></button>
                <button type="button" class="button button-primary button-large" name="action" value="estimate_price" id="wc_sendsms_send_campaign_estimate_price"><?php echo esc_html('Estimate the price', 'sendsms-for-woocommerce') ?></button>
            </p>
        </form>
    </div>
    <?php wc_sendsms_javascript_estimate_price(); //just add the check price as a separated function
    ?>
    <script type="text/javascript">
        document.addEventListener("DOMContentLoaded", (event) => {
            var wc_sendsms_content = document.getElementsByClassName('wc_sendsms_content')[0];

            wc_sendsms_content.addEventListener("input", (event) => {
                lenghtCounter(event.target || event.srcElement, event.target.nextElementSibling || event.srcElement.nextElementSibling);
            });
            wc_sendsms_content.addEventListener("change", (event) => {
                lenghtCounter(event.target || event.srcElement, event.target.nextElementSibling || event.srcElement.nextElementSibling);
            });

            function lenghtCounter(textarea, counter) {
                var lenght = textarea.value.length;
                var messages = lenght / 160 + 1;
                if (lenght > 0) {
                    if (lenght % 160 === 0) {
                        messages--;
                    }
                    counter.textContent = "<?php echo esc_html('The approximate number of messages: ', 'sendsms-for-woocommerce'); ?>" + Math.floor(messages) + " (" + lenght + ")";
                } else {
                    counter.textContent = "<?php echo esc_html('The field is empty', 'sendsms-for-woocommerce'); ?>";
                }
            }
        });
    </script>
<?php
}

function wc_sendsms_javascript_send()
{ ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            jQuery('#wc_sendsms_send_campaign').on('click', function() {
                jQuery('#wc_sendsms_send_campaign').html("<?php echo esc_html('It\'s being sent...', 'sendsms-for-woocommerce') ?>");
                jQuery('#wc_sendsms_send_campaign').attr('disabled', 'disabled');
                all = jQuery('#wc_sendsms_to_all').is(":checked");
                <?php
                // phpcs:disable WordPress.Security.NonceVerification.Recommended -- JS payload pulls the active GET filter values for re-submission
                $js_produse = isset($_GET['produse']) ? array_map('sanitize_text_field', wp_unslash((array) $_GET['produse'])) : array();
                $js_judete  = isset($_GET['judete'])  ? array_map('sanitize_text_field', wp_unslash((array) $_GET['judete']))  : array();
                $js_suma    = isset($_GET['suma'])    ? wc_sendsms_sanitize_float(sanitize_text_field(wp_unslash($_GET['suma'])))     : '';
                $js_pstart  = isset($_GET['perioada_start']) ? wc_sendsms_sanitize_event_time(sanitize_text_field(wp_unslash($_GET['perioada_start']))) : '';
                $js_pfinal  = isset($_GET['perioada_final']) ? wc_sendsms_sanitize_event_time(sanitize_text_field(wp_unslash($_GET['perioada_final']))) : '';
                $js_filter  = isset($_REQUEST['filtering']) ? 'true' : 'false';
                // phpcs:enable WordPress.Security.NonceVerification.Recommended
                ?>
                if (all) {
                    phones = '';
                    produse = <?php echo wp_json_encode($js_produse); ?>;
                    judete = <?php echo wp_json_encode($js_judete); ?>;
                    suma = "<?php echo esc_js($js_suma); ?>";
                    perioada_final = "<?php echo esc_js($js_pfinal); ?>";
                    perioada_start = "<?php echo esc_js($js_pstart); ?>";
                    filtering = "<?php echo esc_js($js_filter); ?>";
                } else {
                    phones = jQuery('#phones').val().join("|");
                    produse = "";
                    judete = "";
                    suma = "";
                    perioada_final = "";
                    perioada_start = "";
                    filtering = "";
                }
                var data = {
                    'security': '<?php echo esc_js(wp_create_nonce('wc_sendsms_send_campaign')); ?>',
                    'action': 'wc_sendsms_campaign',
                    'all': all,
                    'phones': phones,
                    'perioada_start': perioada_start,
                    'perioada_final': perioada_final,
                    'suma': suma,
                    'judete': judete,
                    'produse': produse,
                    'filtering': filtering,
                    'content': jQuery('#wc_sendsms_content').val(),
                    // 'short': jQuery('#wc_sendsms_short').is(":checked"),
                    // 'gdpr': jQuery('#wc_sendsms_gdpr').is(":checked")
                };
                jQuery.post(ajaxurl, data, function(response) {
                    jQuery('#wc_sendsms_send_campaign').html('<?php echo esc_html('Send the message', 'sendsms-for-woocommerce') ?>');
                    jQuery('#wc_sendsms_send_campaign').removeAttr('disabled');
                    alert(response);
                });
            });
        });
    </script>
<?php
}

add_action('admin_footer', 'wc_sendsms_javascript_send');

function wc_sendsms_ajax_send()
{
    check_ajax_referer('wc_sendsms_send_campaign', 'security');
    if (!current_user_can('manage_options')) {
        wp_die('', '', 403);
    }
    if (empty($_POST['content'])) {
        echo esc_html('You must complete the message first.', 'sendsms-for-woocommerce');
        wp_die();
    }

    if (isset($_POST['all']) && $_POST['all'] === "true") {
        $orders = array();
        $is_filtered = isset($_POST['filtering']) && $_POST['filtering'] === "true";
        if ($is_filtered) {
            $orders = wc_sendsms_get_orders_filtered(
                isset($_POST['perioada_start']) ? sanitize_text_field(wp_unslash($_POST['perioada_start'])) : "",
                isset($_POST['perioada_final']) ? sanitize_text_field(wp_unslash($_POST['perioada_final'])) : "",
                isset($_POST['suma'])           ? sanitize_text_field(wp_unslash($_POST['suma']))           : "",
                isset($_POST['judete'])         ? array_map('sanitize_text_field', wp_unslash((array) $_POST['judete']))   : array(),
                isset($_POST['produse'])        ? array_map('sanitize_text_field', wp_unslash((array) $_POST['produse']))  : array()
            );
        } else {
            $orders = wc_sendsms_get_all_orders();
        }
        $phones = array();
        if (count($orders)) {
            foreach ($orders as $order) {
                $phone = wc_sendsms_validate_phone($order->_billing_phone);
                if (!empty($phone)) {
                    $phones[] = $phone;
                }
            }
        }
        $phones = array_unique($phones);
    } else {
        $phones_raw = isset($_POST['phones']) ? sanitize_text_field(wp_unslash($_POST['phones'])) : '';
        $phones = $phones_raw !== '' ? explode("|", $phones_raw) : array();
        if (count($phones) === 0) {
            echo esc_html__('You must choose at least one phone number.', 'sendsms-for-woocommerce');
            wp_die();
        }
    }

    $options = get_option('wc_sendsms_plugin_options');
    if (empty($options) || !is_array($options) || empty($options['username'])) {
        echo esc_html('You did not enter a username', 'sendsms-for-woocommerce');
        wp_die();
    }
    if (empty($options['password'])) {
        echo esc_html('You have not entered a password', 'sendsms-for-woocommerce');
        wp_die();
    }
    $username = $options['username'];
    $password = $options['password'];
    $from = isset($options['from']) ? $options['from'] : '';
    $content = sanitize_textarea_field(wp_unslash($_POST['content']));

    // Build the CSV body in memory as a string (no filesystem, no fopen).
    // RFC 4180: wrap every cell in double quotes; escape internal quotes by doubling.
    $csv_quote = function ($cell) {
        return '"' . str_replace('"', '""', (string) $cell) . '"';
    };
    $data = $csv_quote('message') . ',' . $csv_quote('to') . ',' . $csv_quote('from') . "\r\n";
    foreach ($phones as $phone) {
        $data .= $csv_quote($content) . ',' . $csv_quote($phone) . ',' . $csv_quote($from) . "\r\n";
    }

    $start_time = current_time('mysql');
    $name = 'Wordpress - ' . get_site_url() . ' - ' . uniqid();
    $results = json_decode(wp_remote_retrieve_body(wp_remote_post(
        'https://api.sendsms.ro/json?action=batch_create&username=' . urlencode($username) . '&password=' . urlencode($password) . '&start_time=' . urlencode($start_time) . '&name=' . urlencode($name),
        array('body' => array('data' => $data))
    )), true);
    if (!isset($results['status']) || $results['status'] < 0) {
        echo wp_json_encode($results);
        wp_die();
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'wcsendsms_history';
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- writing to our plugin's own history table; no cache layer applicable.
    $wpdb->query(
        $wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}wcsendsms_history
             (`phone`, `status`, `message`, `details`, `content`, `type`, `sent_on`)
             VALUES (%s, %s, %s, %s, %s, %s, %s)",
            esc_html__('Go to hub.sendsms.ro', 'sendsms-for-woocommerce'),
            isset($results['status']) ? $results['status'] : '',
            isset($results['message']) ? $results['message'] : '',
            isset($results['details']) ? $results['details'] : '',
            esc_html__('We created your campaign. Go and check the batch called: ', 'sendsms-for-woocommerce') . $name,
            esc_html__('Batch Campaign', 'sendsms-for-woocommerce'),
            current_time('mysql')
        )
    );
    echo esc_html__('Success', 'sendsms-for-woocommerce');
    wp_die();
}

add_action('wp_ajax_wc_sendsms_campaign', 'wc_sendsms_ajax_send');

function wc_sendsms_javascript_estimate_price()
{ ?>
    <script type="text/javascript">
        jQuery(document).ready(function($) {
            jQuery('#wc_sendsms_send_campaign_estimate_price').on('click', function() {
                all = jQuery('#wc_sendsms_to_all').is(":checked");
                if (all) {
                    phones = jQuery('select[id=phones] > option').length;
                } else {
                    phones = jQuery('#phones').val().length;
                }
                var wc_sendsms_content = document.getElementsByClassName('wc_sendsms_content')[0];
                var lenght = wc_sendsms_content.value.length;
                var messages = lenght / 160 + 1;
                if (lenght > 0) {
                    if (lenght % 160 === 0) {
                        messages--
                    }
                    messages = Math.floor(messages);
                    price = <?php echo esc_js(get_option('wc-sendsms-default-price', 0)); ?>;
                    if (price > 0) {
                        alert("<?php echo esc_html('The estimate price is: ', 'sendsms-for-woocommerce') ?>" + parseFloat(messages * price * phones).toPrecision(4) + "<?php echo esc_html(' (This is just an estimation, and not the actual price)', 'sendsms-for-woocommerce') ?>");
                    } else {
                        alert("<?php echo esc_html('Please send a message first', 'sendsms-for-woocommerce') ?>");
                    }
                } else {
                    alert("<?php echo esc_html('Please fill the message box first', 'sendsms-for-woocommerce') ?>")
                }
            });
        });
    </script>
<?php
}

function wc_sendsms_history()
{
?>
    <div class="wrap">
        <h2><?php echo esc_html('SendSMS - Historic', 'sendsms-for-woocommerce') ?></h2>
        <form method="get">
            <?php
            $_table_list = new WC_SendSMS_History_List_Table();
            $_table_list->prepare_items();
            echo '<input type="hidden" name="page" value="wc_sendsms_history" />';

            $_table_list->views();
            $_table_list->search_box(esc_html('Search', 'sendsms-for-woocommerce'), 'key');
            $_table_list->display();
            ?>
        </form>
    </div>
<?php
}

function wc_sendsms_plugin_login_section_text()
{
    //
}

function wc_sendsms_settings_display_username()
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['username'])) {
        $username = $options['username'];
    } else {
        $username = '';
    }
    echo '<input id="wc_sendsms_settings_username" name="wc_sendsms_plugin_options[username]" type="text" value="' . esc_attr($username) . '" style="width: 400px;" />';
}

function wc_sendsms_settings_display_password()
{
    $options = get_option('wc_sendsms_plugin_options');
    $has_password = !empty($options) && is_array($options) && !empty($options['password']);
    $placeholder = $has_password ? '••••••••••••' : '';
    echo '<input id="wc_sendsms_settings_password" name="wc_sendsms_plugin_options[password]" type="password" value="" placeholder="' . esc_attr($placeholder) . '" style="width: 400px;" autocomplete="new-password" />';
    if ($has_password) {
        echo ' <span class="description">' . esc_html__('Leave empty to keep the current password.', 'sendsms-for-woocommerce') . '</span>';
    }
}

function wc_sendsms_settings_display_from()
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['from'])) {
        $from = $options['from'];
    } else {
        $from = '';
    }
    echo '<input id="wc_sendsms_settings_from" name="wc_sendsms_plugin_options[from]" type="text" value="' . esc_attr($from) . '" style="width: 400px;" /> <span>' . esc_html('maximum 11 alpha-numeric characters', 'sendsms-for-woocommerce') . '</span>';
}

function wc_sendsms_settings_display_cc()
{
    include 'cc.php';
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['cc'])) {
        $cc = $options['cc'];
    } else {
        $cc = '';
    }
?>
    <select id="wc_sendsms_settings_cc" name="wc_sendsms_plugin_options[cc]">
        <option value="INT">International</option>
        <?php
        foreach ($wc_sendsms_country_codes as $key => $value) {
            echo '<option value="' . esc_attr($key) . '" ' . ($cc == $key ? 'selected' : '') . '>' . esc_html($key) . ' (+' . esc_html($value) . '.)</option>';
        }
        ?>
    </select>
    <?php
}

function wc_sendsms_settings_display_simulation()
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['simulation'])) {
        $simulation = $options['simulation'];
    } else {
        $simulation = '';
    }
    echo '<input id="wc_sendsms_settings_simulation" name="wc_sendsms_plugin_options[simulation]" type="checkbox" value="1" ' . (!empty($simulation) ? 'checked="checked"' : '') . ' />';
}

function wc_sendsms_settings_display_send_to_owner()
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['send_to_owner'])) {
        $send_to_owner = $options['send_to_owner'];
    } else {
        $send_to_owner = '';
    }
    echo '
    <input id="wc_sendsms_settings_send_to_owner" name="wc_sendsms_plugin_options[send_to_owner]" type="checkbox" value="1" ' . (!empty($send_to_owner) ? 'checked="checked"' : '') . ' />';
}

function wc_sendsms_settings_display_send_to_owner_short()
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['send_to_owner_short'])) {
        $send_to_owner_short = $options['send_to_owner_short'];
    } else {
        $send_to_owner_short = '';
    }
    echo '<label>
    <input id="wc_sendsms_settings_send_to_owner_short" name="wc_sendsms_plugin_options[send_to_owner_short]" type="checkbox" value="1" ' . (!empty($send_to_owner_short) ? 'checked="checked"' : '') . ' />' . esc_html('Please use only links starting with https:// or http://', 'sendsms-for-woocommerce') . '</label>';
}

function wc_sendsms_settings_display_send_to_owner_gdpr()
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['send_to_owner_gdpr'])) {
        $send_to_owner_gdpr = $options['send_to_owner_gdpr'];
    } else {
        $send_to_owner_gdpr = '';
    }
    echo '<label>
    <input id="wc_sendsms_settings_send_to_owner_gdpr" name="wc_sendsms_plugin_options[send_to_owner_gdpr]" type="checkbox" value="1" ' . (!empty($send_to_owner_gdpr) ? 'checked="checked"' : '') . ' />' . esc_html('You must specify the key message {gdpr}. The {gdpr} key will be automatically replaced with the unique confirmation link. If the {gdpr} key is not specified, the confirmation link will be placed at the end of the message.', 'sendsms-for-woocommerce') . '</label>';
}

function wc_sendsms_settings_display_simulation_number()
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['simulation_number'])) {
        $number = $options['simulation_number'];
    } else {
        $number = '';
    }
    echo '
    <input id="wc_sendsms_settings_simulation_number" name="wc_sendsms_plugin_options[simulation_number]" type="text" value="' . esc_attr($number) . '" style="width: 400px;" />';
}

function wc_sendsms_settings_display_send_to_owner_number()
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['send_to_owner_number'])) {
        $number = $options['send_to_owner_number'];
    } else {
        $number = '';
    }
    echo '
    <input id="wc_sendsms_settings_send_to_owner_number" name="wc_sendsms_plugin_options[send_to_owner_number]" type="text" value="' . esc_attr($number) . '" style="width: 400px;" />';
}

function wc_sendsms_settings_display_optout()
{
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['optout'])) {
        $optout = $options['optout'];
    } else {
        $optout = '';
    }
    echo '
    <input id="wc_sendsms_settings_optout" name="wc_sendsms_plugin_options[optout]" type="checkbox" value="1" ' . (!empty($optout) ? 'checked="checked"' : '') . ' />';
}

function wc_sendsms_settings_display_send_to_owner_content()
{
    echo '<p>' . esc_html('Variable available:', 'sendsms-for-woocommerce') . ' {billing_first_name}, {billing_last_name}, {shipping_first_name}, {shipping_last_name}, {order_number}, {order_date}, {order_total}</p><br />';
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['send_to_owner_content'])) {
        $content = $options['send_to_owner_content'];
    } else {
        $content = "";
    }

    echo '<div style="width: 100%; clear: both;">
            <div style="width: 45%; float: left">
                <textarea id="wc_sendsms_settings_send_to_owner_content" name="wc_sendsms_plugin_options[send_to_owner_content]" style="width: 400px; height: 100px;" class="wc_sendsms_content">' . esc_textarea(!empty($content) ? $content : '') . '</textarea>
                <p></p>
            </div>
            <div style="width: 45%; float: left">
            </div>
        </div>';
}

function wc_sendsms_settings_display_enabled()
{
}

function wc_sendsms_settings_display_content()
{
    $examples = array(
        'wc-pending' => __('The order with the number {order_number} has been placed successfully and will be shipped as soon as we receive your payment in the amount of {order_total} EURO. sitename.com', 'sendsms-for-woocommerce'),
        'wc-processing' => __('The order with the number {order_number} is being processed and is to be delivered. sitename.com', 'sendsms-for-woocommerce'),
        'wc-on-hold' => __('The order with the number {order_number} is pending, one or more products are missing', 'sendsms-for-woocommerce'),
        'wc-completed' => __('The order {order_number} has been prepared and will be delivered to the Courier. Payment: {order_total} LEI. Thank you, sitename.com', 'sendsms-for-woocommerce'),
        'wc-cancelled' => __('The order with the number {order_number} has been canceled. For details: sitename.com', 'sendsms-for-woocommerce'),
        'wc-refunded' => __('Refund request for order {order_number} has been completed.', 'sendsms-for-woocommerce'),
        'wc-failed' => __('There is a problem processing the payment for the order with the number {order_number}. Please contact us.', 'sendsms-for-woocommerce')
    );
    echo '<p>' . esc_html('Variable available:', 'sendsms-for-woocommerce') . ' {billing_first_name}, {billing_last_name}, {shipping_first_name}, {shipping_last_name}, {order_number}, {order_date}, {order_total}</p><br />';
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['content'])) {
        $content = $options['content'];
        if (isset($options['enabled'])) {
            $enabled = $options['enabled'];
        } else {
            $enabled = array();
        }
        if (isset($options['short'])) {
            $short = $options['short'];
        } else {
            $short = array();
        }
        if (isset($options['gdpr'])) {
            $gdpr = $options['gdpr'];
        } else {
            $gdpr = array();
        }
    } else {
        $content = array();
        $enabled = array();
        $short = array();
        $gdpr = array();
    }

    $statuses = wc_get_order_statuses();
    foreach ($statuses as $key => $value) {
        $shortChecked = false;
        $gdprChecked = false;
        $checked = false;
        if (isset($enabled[$key])) {
            $checked = true;
        }
        if (isset($short[$key])) {
            $shortChecked = true;
        }
        if (isset($gdpr[$key])) {
            $gdprChecked = true;
        }

        echo '  <p style="clear: both; padding-top: 10px;">' . esc_html('Message: ', 'sendsms-for-woocommerce') . esc_html($value) . '</p><p><label><input type="checkbox" name="wc_sendsms_plugin_options[enabled][' . esc_attr($key) . ']" value="1" ' . ($checked ? 'checked="checked"' : '') . ' /> ' . esc_html('Activated', 'sendsms-for-woocommerce') . '</label></p>
                <label style="width:40%;"><input type="checkbox" name="wc_sendsms_plugin_options[short][' . esc_attr($key) . ']" value="1" ' . ($shortChecked ? 'checked="checked"' : '') . ' />' . esc_html('Short URL? (Please use only links starting with https:// or http://)', 'sendsms-for-woocommerce') . '</label>
                <label style="display:block; width:40%;"><input type="checkbox" name="wc_sendsms_plugin_options[gdpr][' . esc_attr($key) . ']" value="1" ' . ($gdprChecked ? 'checked="checked"' : '') . ' />' . esc_html('Add unsubscribe link? (You must specify the {gdpr} key message. The {gdpr} key will be automatically replaced with the unique confirmation link. If the {gdpr} key is not specified, the confirmation link will be placed at the end of the message.)', 'sendsms-for-woocommerce') . '</label>
        <div style="width: 100%; clear: both;">
            <div style="width: 45%; float: left">
                <textarea id="wc_sendsms_settings_content_' . esc_attr($key) . '" name="wc_sendsms_plugin_options[content][' . esc_attr($key) . ']" style="width: 400px; height: 100px;" class="wc_sendsms_content">' . esc_textarea(isset($content[$key]) ? $content[$key] : '') . '</textarea>
                <p></p>
            </div>
            <div style="width: 45%; float: left">
            ';
        if (isset($examples[$key])) {
            echo esc_html('Example: ', 'sendsms-for-woocommerce') . esc_html($examples[$key]);
        }
        echo '
            </div>
        </div>';
    }

    echo    '
            <script type="text/javascript">
                document.addEventListener("DOMContentLoaded", (event) => {
                    var wc_sendsms_content = document.getElementsByClassName(\'wc_sendsms_content\');

                    for (var i = 0; i < wc_sendsms_content.length; i++) {
                        var wc_sendsms_element = wc_sendsms_content[i];
                        wc_sendsms_element.addEventListener("input", (event) => 
                            {
                                lenghtCounter(event.target || event.srcElement, event.target.nextElementSibling || event.srcElement.nextElementSibling);
                            });
                        wc_sendsms_element.addEventListener("change", (event) => 
                            {
                                lenghtCounter(event.target || event.srcElement, event.target.nextElementSibling || event.srcElement.nextElementSibling);
                            });
                        lenghtCounter(wc_sendsms_element, wc_sendsms_element.nextElementSibling);
                        function lenghtCounter(textarea, counter)
                        {
                            var lenght = textarea.value.length;
                            var messages = lenght / 160 + 1;
                            if(lenght > 0)
                            {
                                if(lenght % 160 === 0)
                                {
                                    messages--;
                                }
                                counter.textContent = "' . esc_html('The approximate number of messages: ', 'sendsms-for-woocommerce') . '" + Math.floor(messages) + " (" + lenght + ")";
                            }else
                            {
                                counter.textContent = "' . esc_html('The field is empty', 'sendsms-for-woocommerce') . '";
                            }
                        }
                    };
                });
            </script>';
}

function wc_sendsms_plugin_options_validate($input)
{
    // Preserve the stored password when the field is submitted empty so the
    // settings page never has to render the password back into the HTML.
    if (is_array($input) && (empty($input['password']) || !isset($input['password']))) {
        $existing = get_option('wc_sendsms_plugin_options');
        if (is_array($existing) && !empty($existing['password'])) {
            $input['password'] = $existing['password'];
        }
    }
    return $input;
}

# magic
add_action("woocommerce_order_status_changed", "wc_sendsms_order_status_changed");

function wc_sendsms_order_status_changed($order_id, $checkout = null)
{
    $order = wc_get_order($order_id);
    if (!$order) {
        return;
    }
    $status = $order->get_status();

    # check if user opted out for the order (HPOS-aware)
    if ($order->get_meta('wc_sendsms_optout')) {
        return;
    }

    $options = get_option('wc_sendsms_plugin_options');

    if (!empty($options) && is_array($options) && isset($options['content'])) {
        $content = isset($options['content']) ? $options['content'] : array();
        $enabled = isset($options['enabled']) ? $options['enabled'] : array();
        $short = isset($options['short']) ? $options['short'] : array();
        $gdpr = isset($options['gdpr']) ? $options['gdpr'] : array();
    } else {
        $content = array();
        $enabled = array();
        $short = array();
        $gdpr = array();
    }

    wc_sendsms_get_account_info($username, $password, $from, $options);

    if (!empty($username) && !empty($password)) {
        if (isset($content['wc-' . $status]) && !empty($content['wc-' . $status]) && isset($enabled['wc-' . $status])) {
            # replace variables
            $message = $content['wc-' . $status];
            wc_sendsms_replace_characters($message, $order, $order_id);

            # check if simulation is on and number is entered
            if (!empty($options) && is_array($options) && isset($options['content']) && isset($options['simulation']) && !empty($options['simulation_number'])) {
                # generate valid phone number
                $phone = wc_sendsms_validate_phone($options['simulation_number']);
            } else {
                # generate valid phone number
                $phone = wc_sendsms_validate_phone($order->get_billing_phone());
            }

            if (!empty($phone)) {
                # send sms
                wc_sendsms_send($username, $password, $phone, $message, $from, 'order', isset($short['wc-' . $status]) ? true : false, isset($gdpr['wc-' . $status]) ? true : false);
            }
        }
    }
}

# magic - 2
add_action('woocommerce_new_order', 'wc_sendsms_new_order');

function wc_sendsms_new_order($order_id)
{
    $options = get_option('wc_sendsms_plugin_options');

    if (isset($options) && isset($options['send_to_owner']) && isset($options['send_to_owner_number']) && isset($options['send_to_owner_content'])) {

        $order = new WC_Order($order_id);

        wc_sendsms_get_account_info($username, $password, $from, $options);

        if (!empty($username) && !empty($password)) {
            $phone = wc_sendsms_validate_phone($options['send_to_owner_number']);
            $message = $options['send_to_owner_content'];
            $short = $options['send_to_owner_short'] == 1 ? true : false;
            $gdpr = $options['send_to_owner_gdpr'] == 1 ? true : false;
            wc_sendsms_replace_characters($message, $order, $order_id);

            if (!empty($phone)) {
                # send sms
                wc_sendsms_send($username, $password, $phone, $message, $from, 'new order', $short, $gdpr);
            }
        }
    }
};

# afisare casuta de trimitere sms in comenzi
add_action('add_meta_boxes', 'wc_sendsms_order_details_meta_box');
function wc_sendsms_order_details_meta_box()
{
    // Register on both the legacy CPT screen ('shop_order') and the HPOS Orders
    // screen so the metabox appears regardless of which order store the site uses.
    $screens = array('shop_order');
    if (function_exists('wc_get_page_screen_id')) {
        $hpos_screen = wc_get_page_screen_id('shop-order');
        if ($hpos_screen && $hpos_screen !== 'shop_order') {
            $screens[] = $hpos_screen;
        }
    }
    add_meta_box(
        'wc_sendsms_meta_box',
        __('Send SMS', 'sendsms-for-woocommerce'),
        'wc_sendsms_order_details_sms_box',
        $screens,
        'side',
        'high'
    );
}

function wc_sendsms_order_details_sms_box($post)
{
    // HPOS passes a WC_Order; the legacy CPT screen passes a WP_Post.
    $order_id = ($post instanceof WC_Order) ? $post->get_id() : (int) $post->ID;
    ?>
        <input type="hidden" name="wc_sendsms_order_id" id="wc_sendsms_order_id" value="<?php echo esc_attr($order_id); ?>" />
        <p><?php echo esc_html('Phone:', 'sendsms-for-woocommerce') ?></p>
        <p><input type="text" name="wc_sendsms_phone" id="wc_sendsms_phone" style="width: 100%" /></p>
        <p><?php echo esc_html('Short URL? (Please use only links starting with https:// or http://)', 'sendsms-for-woocommerce') ?></p>
        <p><input type="checkbox" name="wc_sendsms_short" id="wc_sendsms_short" /></p>
        <p><?php echo esc_html('Add unsubscribe link? (You must specify the {gdpr} key message. The {gdpr} key will be automatically replaced with the unique confirmation link. If the {gdpr} key is not specified, the confirmation link will be placed at the end of the message.)', 'sendsms-for-woocommerce') ?></p>
        <p><input type="checkbox" name="wc_sendsms_gdpr" id="wc_sendsms_gdpr" /></p>
        <p><?php echo esc_html('Message:', 'sendsms-for-woocommerce') ?></p>
        <div>
            <textarea name="wc_sendsms_content" class="wc_sendsms_content" id="wc_sendsms_content" style="width: 100%; height: 100px;"></textarea>
            <p><?php echo esc_html('The field is empty', 'sendsms-for-woocommerce') ?></p>
        </div>
        <p><button type="submit" class="button" id="wc_sendsms_send_single"><?php echo esc_html('Send the message', 'sendsms-for-woocommerce') ?></button></p>
        <script type="text/javascript">
            document.addEventListener("DOMContentLoaded", (event) => {
                var wc_sendsms_content = document.getElementsByClassName('wc_sendsms_content')[0];

                wc_sendsms_content.addEventListener("input", (event) => {
                    lenghtCounter(event.target || event.srcElement, event.target.nextElementSibling || event.srcElement.nextElementSibling);
                });
                wc_sendsms_content.addEventListener("change", (event) => {
                    lenghtCounter(event.target || event.srcElement, event.target.nextElementSibling || event.srcElement.nextElementSibling);
                });

                function lenghtCounter(textarea, counter) {
                    var lenght = textarea.value.length;
                    var messages = lenght / 160 + 1;
                    if (lenght > 0) {
                        if (lenght % 160 === 0) {
                            messages--;
                        }
                        counter.textContent = "<?php echo esc_html('The approximate number of messages: ', 'sendsms-for-woocommerce'); ?>" + Math.floor(messages) + " (" + lenght + ")";
                    } else {
                        counter.textContent = "<?php echo esc_html('The field is empty', 'sendsms-for-woocommerce'); ?>";
                    }
                }
            });
        </script>
    <?php
}

function wc_sendsms_javascript_send_single()
{ ?>
        <script type="text/javascript">
            jQuery(document).ready(function($) {
                jQuery('#wc_sendsms_send_single').on('click', function() {
                    jQuery('#wc_sendsms_send_single').html("<?php echo esc_html('It\'s being sent...', 'sendsms-for-woocommerce') ?>");
                    jQuery('#wc_sendsms_send_single').attr('disabled', 'disabled');
                    var data = {
                        'action': 'wc_sendsms_single',
                        'security': '<?php echo esc_js(wp_create_nonce('wc_sendsms_send_single')); ?>',
                        'phone': jQuery('#wc_sendsms_phone').val(),
                        'content': jQuery('#wc_sendsms_content').val(),
                        'order': jQuery('#wc_sendsms_order_id').val(),
                        'short': jQuery('#wc_sendsms_short').is(":checked"),
                        'gdpr': jQuery('#wc_sendsms_gdpr').is(":checked")
                    };

                    jQuery.post(ajaxurl, data, function(response) {
                        jQuery('#wc_sendsms_send_single').html('<?php echo esc_js(__('Send the message', 'sendsms-for-woocommerce')); ?>');
                        jQuery('#wc_sendsms_send_single').removeAttr('disabled');
                        jQuery('#wc_sendsms_phone').val('');
                        jQuery('#wc_sendsms_content').val('');
                        jQuery('#wc_sendsms_short').prop('checked', false);
                        jQuery('#wc_sendsms_gdpr').prop('checked', false);
                        alert(response);
                    });
                });
            });
        </script>
    <?php
}
add_action('admin_footer', 'wc_sendsms_javascript_send_single');

function wc_sendsms_ajax_send_single()
{
    if (!check_ajax_referer('wc_sendsms_send_single', 'security', false)) {
        wp_die('', '', 403);
    }
    if (!current_user_can('manage_woocommerce')) {
        wp_die('', '', 403);
    }
    if (!empty($_POST['content']) && !empty($_POST['phone']) && !empty($_POST['order'])) {
        $order_id = absint($_POST['order']);
        $order = wc_get_order($order_id);
        if (!$order) {
            echo esc_html('Invalid order.', 'sendsms-for-woocommerce');
            wp_die();
        }
        $options = get_option('wc_sendsms_plugin_options');
        $username = '';
        $password = '';
        $short = isset($_POST['short']) ? filter_var(sanitize_text_field(wp_unslash($_POST['short'])), FILTER_VALIDATE_BOOLEAN) : false;
        $gdpr  = isset($_POST['gdpr'])  ? filter_var(sanitize_text_field(wp_unslash($_POST['gdpr'])),  FILTER_VALIDATE_BOOLEAN) : false;
        if (!empty($options) && is_array($options) && isset($options['username'])) {
            $username = $options['username'];
        } else {
            echo esc_html('You did not enter a username', 'sendsms-for-woocommerce');
            wp_die();
        }
        if (!empty($options) && is_array($options) && isset($options['password'])) {
            $password = $options['password'];
        } else {
            echo esc_html('You did not enter a password', 'sendsms-for-woocommerce');
            wp_die();
        }
        if (!empty($options) && is_array($options) && isset($options['from'])) {
            $from = $options['from'];
        } else {
            $from = '';
        }
        $phone = wc_sendsms_validate_phone(sanitize_text_field(wp_unslash($_POST['phone'])));
        if (!empty($phone)) {
            $content = sanitize_textarea_field(wp_unslash($_POST['content']));
            wc_sendsms_send($username, $password, $phone, $content, $from, 'single order', $short, $gdpr);
            $order->add_order_note(__('SMS message sent to ', 'sendsms-for-woocommerce') . $phone . ': ' . $content);
        }
        echo esc_html('The message was sent', 'sendsms-for-woocommerce');
    } else {
        echo esc_html('You must complete the message and a phone number', 'sendsms-for-woocommerce');
    }
    wp_die();
}
add_action('wp_ajax_wc_sendsms_single', 'wc_sendsms_ajax_send_single');

function wc_sendsms_send($username, $password, $phone, $message, $from, $type = 'order', $short = false, $gdpr = false)
{
    global $wpdb;

    $args['headers'] = [
        'url' => get_site_url()
    ];

    $results = json_decode(wp_remote_retrieve_body(wp_remote_get('https://api.sendsms.ro/json?action=message_send' . ($gdpr ? "_gdpr" : "") . '&username=' . urlencode($username) . '&password=' . urlencode($password) . '&from=' . urlencode($from) . '&to=' . urlencode(trim($phone)) . '&text=' . urlencode($message) . '&short=' . ($short ? 'true' : 'false'), $args)), true);

    # history
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- writing to our plugin's own history table; no cache layer applicable.
    $wpdb->query(
        $wpdb->prepare(
            "INSERT INTO {$wpdb->prefix}wcsendsms_history
             (`phone`, `status`, `message`, `details`, `content`, `type`, `sent_on`)
             VALUES (%s, %s, %s, %s, %s, %s, %s)",
            $phone,
            isset($results['status']) ? $results['status'] : '',
            isset($results['message']) ? $results['message'] : '',
            isset($results['details']) ? $results['details'] : '',
            $message,
            $type,
            current_time('mysql')
        )
    );
    if (!get_option('wc-sendsms-default-price-time', false) || get_option('wc-sendsms-default-price-time') < gmdate('Y-m-d H:i:s')) {
        $results = json_decode(wp_remote_retrieve_body(wp_remote_get('https://api.sendsms.ro/json?action=route_check_price&username=' . urlencode($username) . '&password=' . urlencode($password) . '&to=' . urlencode($phone), $args)), true);
        if ($results['details']['status'] === 64) {
            update_option('wc-sendsms-default-price', $results['details']['cost']);
            update_option('wc-sendsms-default-price-time', gmdate('Y-m-d H:i:s', strtotime('+1 day')));
        }
    }
}


function wc_sendsms_validate_phone($phone_number)
{
    if (empty($phone_number)) {
        return '';
    }
    include 'cc.php';
    $phone_number = wc_sendsms_clear_phone_number($phone_number);
    // Strip leading zeros and apply country code if needed.
    $options = get_option('wc_sendsms_plugin_options');
    if (!empty($options) && is_array($options) && isset($options['cc'])) {
        $cc = $options['cc'];
    } else {
        $cc = 'INT';
    }
    if ($cc === "INT") {
        return $phone_number;
    }
    $phone_number = ltrim($phone_number, '0');
    $country_code = isset($wc_sendsms_country_codes[$cc]) ? $wc_sendsms_country_codes[$cc] : '';

    if (!preg_match('/^' . $country_code . '/', $phone_number)) {
        $phone_number = $country_code . $phone_number;
    }

    return $phone_number;
}

function wc_sendsms_clear_phone_number($phone_number)
{
    $phone_number = str_replace(['+', '-'], '', filter_var($phone_number, FILTER_SANITIZE_NUMBER_INT));
    //Strip spaces and non-numeric characters:
    $phone_number = preg_replace("/[^0-9]/", "", $phone_number);
    return $phone_number;
}

function wc_sendsms_clean_diacritice($string)
{
    $balarii = array(
        "\xC4\x82",
        "\xC4\x83",
        "\xC3\x82",
        "\xC3\xA2",
        "\xC3\x8E",
        "\xC3\xAE",
        "\xC8\x98",
        "\xC8\x99",
        "\xC8\x9A",
        "\xC8\x9B",
        "\xC5\x9E",
        "\xC5\x9F",
        "\xC5\xA2",
        "\xC5\xA3",
        "\xC3\xA3",
        "\xC2\xAD",
        "\xe2\x80\x93"
    );
    $cleanLetters = array("A", "a", "A", "a", "I", "i", "S", "s", "T", "t", "S", "s", "T", "t", "a", " ", "-");
    return str_replace($balarii, $cleanLetters, $string);
}

function wc_sendsms_get_orders_filtered($perioada_start, $perioada_final, $suma, $judete, $produse)
{
    // Build WooCommerce query args (HPOS-compatible)
    $args = array(
        'limit' => -1,
        'status' => 'completed',
        'type' => 'shop_order', // Exclude refunds
        'return' => 'objects'
    );

    // Add date range filter
    if (!empty($perioada_start)) {
        $args['date_created'] = '>=' . wc_sendsms_sanitize_event_time($perioada_start);
    }
    if (!empty($perioada_final)) {
        // Add one day to include orders from the final date
        $final_date = gmdate('Y-m-d', strtotime(wc_sendsms_sanitize_event_time($perioada_final) . ' +1 day'));
        if (!empty($perioada_start)) {
            $args['date_created'] = wc_sendsms_sanitize_event_time($perioada_start) . '...' . $final_date;
        } else {
            $args['date_created'] = '<' . $final_date;
        }
    }

    // Get orders using WooCommerce API
    $orders = wc_get_orders($args);

    // Convert to old format and apply filters
    $result = array();
    foreach ($orders as $order) {
        // Skip refunds (extra safety check)
        if ($order->get_type() !== 'shop_order') {
            continue;
        }

        // Filter by minimum order total
        if (!empty($suma) && $order->get_total() < floatval(wc_sendsms_sanitize_float($suma))) {
            continue;
        }

        // Filter by billing state
        if (!empty($judete)) {
            $order_state = $order->get_billing_state();
            $state_match = false;
            foreach ($judete as $judet) {
                $clean_judet = str_replace("id_", "", sanitize_text_field($judet));
                if ($order_state === $clean_judet) {
                    $state_match = true;
                    break;
                }
            }
            if (!$state_match) {
                continue;
            }
        }

        // Filter by products
        if (!empty($produse)) {
            $order_product_ids = array();
            foreach ($order->get_items() as $item) {
                $order_product_ids[] = $item->get_product_id();
            }

            $product_match = false;
            foreach ($produse as $product) {
                $clean_product = str_replace("id_", "", sanitize_text_field($product));
                if (in_array($clean_product, $order_product_ids)) {
                    $product_match = true;
                    break;
                }
            }
            if (!$product_match) {
                continue;
            }
        }

        // Convert to old format
        $order_data = new stdClass();
        $order_data->order_id = $order->get_id();
        $order_data->post_date = $order->get_date_created()->date('Y-m-d H:i:s');
        $order_data->billing_email = $order->get_billing_email();
        $order_data->_billing_first_name = $order->get_billing_first_name();
        $order_data->_billing_last_name = $order->get_billing_last_name();
        $order_data->_billing_address_1 = $order->get_billing_address_1();
        $order_data->_billing_address_2 = $order->get_billing_address_2();
        $order_data->_billing_city = $order->get_billing_city();
        $order_data->_billing_state = $order->get_billing_state();
        $order_data->_billing_phone = $order->get_billing_phone();
        $order_data->_billing_postcode = $order->get_billing_postcode();
        $order_data->_shipping_first_name = $order->get_shipping_first_name();
        $order_data->_shipping_last_name = $order->get_shipping_last_name();
        $order_data->_shipping_address_1 = $order->get_shipping_address_1();
        $order_data->_shipping_address_2 = $order->get_shipping_address_2();
        $order_data->_shipping_city = $order->get_shipping_city();
        $order_data->_shipping_state = $order->get_shipping_state();
        $order_data->_shipping_postcode = $order->get_shipping_postcode();
        $order_data->order_total = $order->get_total();
        $order_data->order_tax = $order->get_total_tax();
        $order_data->paid_date = $order->get_date_paid() ? $order->get_date_paid()->date('Y-m-d H:i:s') : '';

        // Get product IDs
        $product_ids = array();
        foreach ($order->get_items() as $item) {
            $product_ids[] = $item->get_product_id();
        }
        $order_data->items_id = implode('|', $product_ids);

        $result[] = $order_data;
    }

    return $result;
}

function wc_sendsms_get_all_orders()
{
    // Use WooCommerce HPOS-compatible API
    $orders = wc_get_orders(array(
        'limit' => -1,
        'status' => 'completed',
        'type' => 'shop_order', // Exclude refunds
        'return' => 'objects'
    ));

    // Convert WC_Order objects to stdClass objects matching the old format
    $result = array();
    foreach ($orders as $order) {
        // Skip refunds (extra safety check)
        if ($order->get_type() !== 'shop_order') {
            continue;
        }

        $order_data = new stdClass();
        $order_data->order_id = $order->get_id();
        $order_data->post_date = $order->get_date_created()->date('Y-m-d H:i:s');
        $order_data->billing_email = $order->get_billing_email();
        $order_data->_billing_first_name = $order->get_billing_first_name();
        $order_data->_billing_last_name = $order->get_billing_last_name();
        $order_data->_billing_address_1 = $order->get_billing_address_1();
        $order_data->_billing_address_2 = $order->get_billing_address_2();
        $order_data->_billing_city = $order->get_billing_city();
        $order_data->_billing_state = $order->get_billing_state();
        $order_data->_billing_phone = $order->get_billing_phone();
        $order_data->_billing_postcode = $order->get_billing_postcode();
        $order_data->_shipping_first_name = $order->get_shipping_first_name();
        $order_data->_shipping_last_name = $order->get_shipping_last_name();
        $order_data->_shipping_address_1 = $order->get_shipping_address_1();
        $order_data->_shipping_address_2 = $order->get_shipping_address_2();
        $order_data->_shipping_city = $order->get_shipping_city();
        $order_data->_shipping_state = $order->get_shipping_state();
        $order_data->_shipping_postcode = $order->get_shipping_postcode();
        $order_data->order_total = $order->get_total();
        $order_data->order_tax = $order->get_total_tax();
        $order_data->paid_date = $order->get_date_paid() ? $order->get_date_paid()->date('Y-m-d H:i:s') : '';

        // Get product IDs from order items
        $product_ids = array();
        foreach ($order->get_items() as $item) {
            $product_ids[] = $item->get_product_id();
        }
        $order_data->items_id = implode('|', $product_ids);

        $result[] = $order_data;
    }

    return $result;
}

function wc_sendsms_sanitize_event_time($event_time)
{
    // General sanitization, to get rid of malicious scripts or characters
    $event_time = sanitize_text_field($event_time);
    // Note: filter_var with FILTER_SANITIZE_STRING is deprecated in PHP 8.1+
    // sanitize_text_field() already handles the sanitization we need

    // Validation to see if it is the right format
    if (wc_sendsms_my_validate_date($event_time)) {
        return $event_time;
    }
    // default value, to return if checks have failed
    return "";
}

function wc_sendsms_my_validate_date($date, $format = 'Y-m-d')
{
    // Create the format date
    $d = DateTime::createFromFormat($format, $date);

    // Return the comparison    
    return $d && $d->format($format) === $date;
}

function wc_sendsms_sanitize_float($input)
{
    return filter_var($input, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
}

function wc_sendsms_get_account_info(&$username, &$password, &$from, $options)
{
    if (!empty($options) && is_array($options) && isset($options['username'])) {
        $username = $options['username'];
    } else {
        $username = '';
    }
    if (!empty($options) && is_array($options) && isset($options['password'])) {
        $password = $options['password'];
    } else {
        $password = '';
    }
    if (!empty($options) && is_array($options) && isset($options['from'])) {
        $from = $options['from'];
    } else {
        $from = '';
    }
}

function wc_sendsms_replace_characters(&$message, $order, $order_id)
{
    $replace = array(
        '{billing_first_name}' => wc_sendsms_clean_diacritice($order->get_billing_first_name()),
        '{billing_last_name}' => wc_sendsms_clean_diacritice($order->get_billing_last_name()),
        '{shipping_first_name}' => wc_sendsms_clean_diacritice($order->get_shipping_first_name()),
        '{shipping_last_name}' => wc_sendsms_clean_diacritice($order->get_shipping_last_name()),
        '{order_number}' => $order_id,
        '{order_date}' => $order->get_date_created() ? $order->get_date_created()->date('d-m-Y') : '',
        '{order_total}' => number_format($order->get_total(), wc_get_price_decimals(), ',', '')
    );
    foreach ($replace as $key => $value) {
        $message = str_replace($key, $value, $message);
    }
}

function wc_sendsms_sanitize_bool($data)
{
    return $data ? 1 : 0;
}


function wc_sendsms_randomNumberSequence($requiredLength = 7, $highestDigit = 8)
{
    $sequence = '';
    for ($i = 0; $i < $requiredLength; ++$i) {
        $sequence .= wp_rand(0, $highestDigit);
    }
    return $sequence;
}
