<?php
/* ----------------------------
SETTINGS MANAGEMENT
---------------------------- */

/**
 * Get setting value
 */
function sfm_pro_get_setting($key, $default = '') {
    global $wpdb;
    $table = $wpdb->prefix . 'sfm_pro_settings';
    
    $value = $wpdb->get_var($wpdb->prepare(
        "SELECT setting_value FROM $table WHERE setting_key = %s", 
        $key
    ));
    
    return $value !== null ? $value : $default;
}

/**
 * Update setting value
 */
function sfm_pro_update_setting($key, $value) {
    global $wpdb;
    $table = $wpdb->prefix . 'sfm_pro_settings';
    
    $existing = sfm_pro_get_setting($key);
    
    if ($existing !== null) {
        return $wpdb->update(
            $table,
            ['setting_value' => $value],
            ['setting_key' => $key]
        );
    } else {
        return $wpdb->insert(
            $table,
            [
                'setting_key' => $key,
                'setting_value' => $value
            ]
        );
    }
}
?>
