<?php
/**
 * Plugin Name: Contact Form 7 Spam Filter
 * Plugin URI: https://www.hyperuix.com.au/
 * Description: Places spam filters into Wordpress to reduce spam, set AU phone numbers & address fields and states.
 * Author: HYPERUIX
 * Author URI: https://www.hyperuix.com.au/
 * Version: 1.0
 * Text Domain: cf7-spam-filter
 * License: GPL3+
*/

// Add custom validation to Contact Form 7
add_filter('wpcf7_validate', 'custom_contact_form_validation', 10, 2);

function custom_contact_form_validation($result, $tags) {
    $form_id = $tags->id();
    $email_field_name = 'wpcf7-email'; // Replace 'your-email-field' with the name of your email field
    $phone_field_name = 'wpcf7-tel'; // Replace 'your-phone-field' with the name of your phone field
    $ip_limit_field_name = 'ip-limit-field'; // Replace 'ip-limit-field' with the name of the hidden field for storing IP addresses
    $ip_limit_timeframe = 3600; // Timeframe in seconds (3600 seconds = 1 hour)
    $max_entries_per_ip = 3;

    // Perform email format check
    $email = isset($_POST[$email_field_name]) ? sanitize_email($_POST[$email_field_name]) : '';
    if (!empty($email)) {
        if (!is_email($email)) {
            $result->invalidate($tags, 'Please enter a valid email address.');
        }
    }

    // Perform phone number format check
    $phone_number = isset($_POST[$phone_field_name]) ? sanitize_text_field($_POST[$phone_field_name]) : '';
    if (!empty($phone_number)) {
        if (!is_australian_phone_number($phone_number)) {
            $result->invalidate($tags, 'Please enter a valid Australian phone number.');
        }
    }

    // Get the user's IP address
    $user_ip = $_SERVER['REMOTE_ADDR'];

    // Get the stored IP addresses from the hidden field
    $stored_ips = isset($_POST[$ip_limit_field_name]) ? unserialize(base64_decode($_POST[$ip_limit_field_name])) : array();

    // Remove IP addresses that are older than the specified timeframe
    $current_time = time();
    foreach ($stored_ips as $key => $ip_data) {
        if ($current_time - $ip_data['time'] > $ip_limit_timeframe) {
            unset($stored_ips[$key]);
        }
    }

    // Add the current IP address to the stored IP addresses
    $stored_ips[$user_ip] = array('time' => $current_time);

    // Serialize and encode the stored IPs for storage in the hidden field
    $encoded_ips = base64_encode(serialize($stored_ips));
    $_POST[$ip_limit_field_name] = $encoded_ips;

    // Check if the number of entries from the current IP exceeds the limit
    if (count($stored_ips) > $max_entries_per_ip) {
        $result->invalidate($tags, 'You have exceeded the maximum number of submissions within the specified timeframe.');
    }

    // If the submission is flagged as spam, prevent admin notification email from being sent
    if ($result->is_spam()) {
        add_filter('wpcf7_skip_mail', '__return_true');
    }

    // You can add more checks here as needed

    return $result;
}

// Function to check if a phone number is in Australian format
function is_australian_phone_number($phone_number) {
    // Australian phone numbers typically start with '+61', '0', or '61'
    // We'll check if it starts with one of these patterns
    if (preg_match('/^\+?(61)?0?(4|3|7|8|2)\d{8}$/', $phone_number)) {
        return true;
    }
    return false;
}