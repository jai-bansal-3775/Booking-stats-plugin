<?php
/**
 * Plugin Name: Booking Stats
 * Description: Displays the number of bookings made in the last N days, configurable from the admin panel.
 * Version: 1.1
 * Author: Your Name
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

// Enqueue React App
function booking_stats_enqueue_scripts($hook) {
    if ($hook !== 'toplevel_page_booking-stats') return;

    wp_enqueue_script(
        'booking-stats-script',
        plugins_url('/build/index.js', __FILE__),
        ['wp-element'],
        time(),
        true
    );

    wp_localize_script('booking-stats-script', 'bookingStatsData', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce'   => wp_create_nonce('booking_stats_nonce'),
        'days'    => get_option('booking_stats_days', 7) // Default to 7 days
    ]);
}
add_action('admin_enqueue_scripts', 'booking_stats_enqueue_scripts');

// Register Admin Menu
function booking_stats_menu() {
    add_menu_page('Booking Stats', 'Booking Stats', 'manage_options', 'booking-stats', 'booking_stats_admin_page');
}
add_action('admin_menu', 'booking_stats_menu');

// Admin Page
function booking_stats_admin_page() {
    ?>
    <div class="wrap">
        <h1>Booking Stats</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('booking_stats_settings');
            do_settings_sections('booking_stats');
            submit_button();
            ?>
        </form>
        <div id="booking-stats-root"></div>
    </div>
    <?php
}

// Register Settings
function booking_stats_register_settings() {
    register_setting('booking_stats_settings', 'booking_stats_days');
    add_settings_section('booking_stats_section', 'Settings', null, 'booking_stats');
    add_settings_field(
        'booking_stats_days',
        'Number of Days',
        'booking_stats_days_callback',
        'booking_stats',
        'booking_stats_section'
    );
}
add_action('admin_init', 'booking_stats_register_settings');

function booking_stats_days_callback() {
    $days = get_option('booking_stats_days', 7);
    echo "<input type='number' name='booking_stats_days' value='{$days}' min='1' />";
}

// AJAX Handler to Fetch Booking Count
function fetch_booking_stats() {
    check_ajax_referer('booking_stats_nonce', 'nonce');

    global $wpdb;
    $days = isset($_POST['days']) ? intval($_POST['days']) : get_option('booking_stats_days', 7);
    $date_limit = date('Y-m-d H:i:s', strtotime("-$days days"));

    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ea_appointments WHERE created >= %s",
        $date_limit
    ));

    wp_send_json(['count' => $count]);
}
add_action('wp_ajax_fetch_booking_stats', 'fetch_booking_stats');


function last_ndays_bookings_shortcode() {
    global $wpdb;
    $days = get_option('booking_stats_days', 7);
    $date_limit = date('Y-m-d H:i:s', strtotime("-$days days"));

    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}ea_appointments WHERE created >= %s",
        $date_limit
    ));

    return "<p>Number of bookings created in the last {$days} days: <strong>{$count}</strong></p>";
}
add_shortcode('last_ndays_bookings', 'last_ndays_bookings_shortcode');
