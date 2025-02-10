<?php
/**
 * Plugin Name: Booking Stats
 * Description: Displays the number of bookings made in the last N days and available slots.
 * Version: 1.3
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
    $use_static = get_option('booking_stats_use_static', false);
    $static_value = get_option('booking_stats_static_value', 20);
    $days = get_option('booking_stats_days', 7);

    $use_static_slots = get_option('booking_stats_use_static_slots', false);
    $static_slots = get_option('booking_stats_static_slots', 50);

    echo '<div class="wrap">';
    echo '<h1>Booking Stats Settings</h1>';
    echo '<form method="post" action="options.php">';
    settings_fields('booking_stats_settings');
    do_settings_sections('booking_stats');
    echo '<table class="form-table">';
    echo '<tr><th>Days to Count:</th><td><input type="number" name="booking_stats_days" value="' . esc_attr($days) . '" /></td></tr>';
    echo '<tr><th>Use Static Booking Value:</th><td><input type="checkbox" name="booking_stats_use_static" value="1" ' . checked(1, $use_static, false) . ' /></td></tr>';
    echo '<tr><th>Static Booking Value:</th><td><input type="number" name="booking_stats_static_value" value="' . esc_attr($static_value) . '" /></td></tr>';
    echo '</table>';
    echo '<h1>Available slots</h1>';
    echo '<table class="form-table">';
    echo '<tr><th>Use Static Slots Value:</th><td><input type="checkbox" name="booking_stats_use_static_slots" value="1" ' . checked(1, $use_static_slots, false) . ' /></td></tr>';
    echo '<tr><th>Static Slots Available:</th><td><input type="number" name="booking_stats_static_slots" value="' . esc_attr($static_slots) . '" /></td></tr>';
    echo '</table>';
    submit_button();
    echo '</form>';
    echo '</div>';
}

function booking_stats_register_settings() {
    register_setting('booking_stats_settings', 'booking_stats_days');
    register_setting('booking_stats_settings', 'booking_stats_use_static');
    register_setting('booking_stats_settings', 'booking_stats_static_value');
    register_setting('booking_stats_settings', 'booking_stats_use_static_slots');
    register_setting('booking_stats_settings', 'booking_stats_static_slots');
}
add_action('admin_init', 'booking_stats_register_settings');

// AJAX Handler to Fetch Bookings
function fetch_booking_stats() {
    check_ajax_referer('booking_stats_nonce', 'nonce');

    global $wpdb;
    $days = get_option('booking_stats_days', 7);
    $use_static = get_option('booking_stats_use_static', false);
    $static_value = get_option('booking_stats_static_value', 20);

    if ($use_static) {
        $count = $static_value;
    } else {
        $date_limit = date('Y-m-d H:i:s', strtotime("-$days days"));
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ea_appointments WHERE created >= %s",
            $date_limit
        ));
    }

    wp_send_json(['count' => $count]);
}
add_action('wp_ajax_fetch_booking_stats', 'fetch_booking_stats');

// AJAX Handler to Fetch Available Slots
function fetch_slots_stats() {
    check_ajax_referer('booking_stats_nonce', 'nonce');

    global $wpdb;
    $use_static_slots = get_option('booking_stats_use_static_slots', false);
    $static_slots = get_option('booking_stats_static_slots', 50);

    if ($use_static_slots) {
        $slots = $static_slots;
    } else {
        // Define the available slots per weekday
        $slots_per_day = 4;
        $total_weekdays = 5; // Monday to Friday
        $total_slots = $slots_per_day * $total_weekdays;

        // Calculate the number of booked slots this week
        $booked_slots = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}ea_appointments 
             WHERE YEARWEEK(date, 1) = YEARWEEK(CURDATE(), 1)"
        ));

        // Calculate available slots
        $slots = $total_slots - $booked_slots;
    }

    wp_send_json(['slots' => $slots]);
}
add_action('wp_ajax_fetch_slots_stats', 'fetch_slots_stats');

/* -------------------- TASK 1: Shortcode -------------------- */
function booking_count_shortcode() {
    $message = '<span id="booking-count">Loading...</span>';
    $message .= '<script>
        document.addEventListener("DOMContentLoaded", function() {
            fetch("' . admin_url('admin-ajax.php') . '", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "action=fetch_booking_stats&nonce=" + "' . wp_create_nonce('booking_stats_nonce') . '"
            })
            .then(response => response.json())
            .then(data => {
                document.getElementById("booking-count").innerText = data.count ?? 0;
            })
            .catch(error => console.error("Error fetching booking count:", error));
        });
    </script>';
    return $message;
}
add_shortcode('booking_count', 'booking_count_shortcode');

function booking_days_shortcode() {
    $days = get_option('booking_stats_days', 7);
    return '<span id="booking-days">' . esc_html($days) . '</span>';
}
add_shortcode('booking_days', 'booking_days_shortcode');

/* -------------------- TASK 2: Shortcode -------------------- */
function slots_stats_shortcode() {
    $message = '<span id="slots-stats-count">Loading...</span>';
    $message .= '<script>
        document.addEventListener("DOMContentLoaded", function() {
            fetch("' . admin_url('admin-ajax.php') . '", {
                method: "POST",
                headers: { "Content-Type": "application/x-www-form-urlencoded" },
                body: "action=fetch_slots_stats&nonce=" + "' . wp_create_nonce('booking_stats_nonce') . '"
            })
            .then(response => response.json())
            .then(data => {
                console.log("Slots Stats Response:", data);
                document.getElementById("slots-stats-count").innerText = (data.slots ?? 0);
            })
            .catch(error => console.error("Error fetching slots stats:", error));
        });
    </script>';
    return $message;
}
add_shortcode('slots_stats', 'slots_stats_shortcode');
