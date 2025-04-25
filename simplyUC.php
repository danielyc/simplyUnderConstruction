<?php
/**
 * Plugin Name: Simply Under Construction
 * Description: The easiest way to display a static "under construction" page for visitors.
 * Version: 1.2
 * Author: danielyc
 */

/**
 * Front-end override: If under construction is enabled and the current user isn’t allowed to bypass,
 * output the stored HTML and halt further processing.
 */
function uc_display_under_construction() {
    // Check if under construction mode is enabled.
    if ( intval( get_option( 'uc_enabled', 0 ) ) !== 1 ) {
        return;
    }

    // Retrieve the access mode. Default is "administrators".
    $access_mode = get_option( 'uc_access_mode', 'administrators' );

    // Determine if the current user should bypass the under construction page.
    $bypass = false;
    if ( $access_mode === 'administrators' ) {
        // Only administrators bypass.
        if ( is_user_logged_in() && current_user_can( 'administrator' ) ) {
            $bypass = true;
        }
    } elseif ( $access_mode === 'all_users' ) {
        // All logged in users bypass.
        if ( is_user_logged_in() ) {
            $bypass = true;
        }
    }
    // Add IP whitelist bypass
    $ip_whitelist = get_option( 'uc_ip_whitelist', '' );
    if ( ! empty( $ip_whitelist ) ) {
        $ips       = array_map( 'trim', explode( ',', $ip_whitelist ) );
        $remote_ip = $_SERVER['REMOTE_ADDR'];
        if ( in_array( $remote_ip, $ips, true ) ) {
            $bypass = true;
        }
    }

    // If the user is not allowed to bypass, output the under construction HTML.
    if ( ! $bypass ) {
        $html = get_option( 'uc_html_content', '' );
        if ( ! empty( $html ) ) {
            // Process shortcodes if enabled
            if ( get_option( 'uc_process_shortcodes', 0 ) ) {
                $html = do_shortcode( $html );
            }
            echo $html;
            exit;
        } else {
            wp_die( 'Under Construction. Please check back later.' );
        }
    }
}
add_action( 'template_redirect', 'uc_display_under_construction' );

/**
 * Register our settings for the plugin.
 */
function uc_register_settings() {
    register_setting( 'uc_settings_group', 'uc_enabled' );
    register_setting( 'uc_settings_group', 'uc_html_content' );
    register_setting( 'uc_settings_group', 'uc_access_mode' );
    register_setting( 'uc_settings_group', 'uc_rich_editor' );
    register_setting( 'uc_settings_group', 'uc_process_shortcodes' );
    register_setting( 'uc_settings_group', 'uc_ip_whitelist' );
    
    // Add a callback to clear LSCache if it's active
    add_action('update_option_uc_enabled', 'uc_clear_cache', 10, 2);
    add_action('update_option_uc_html_content', 'uc_clear_cache', 10, 2);
    add_action('update_option_uc_access_mode', 'uc_clear_cache', 10, 2);
    add_action('update_option_uc_process_shortcodes', 'uc_clear_cache', 10, 2);
    add_action('update_option_uc_ip_whitelist', 'uc_clear_cache', 10, 2);
}
add_action( 'admin_init', 'uc_register_settings' );

/**
 * Clear LiteSpeed Cache if it's active when settings are changed.
 */
function uc_clear_cache($old_value, $new_value) {
    // Only proceed if the values are different
    if ($old_value === $new_value) {
        return;
    }
    
    // Check if LiteSpeed Cache is active
    if (class_exists('\LiteSpeed\Purge')) {
        // Clear all cache - LiteSpeed will show its own notice
        do_action('litespeed_purge_all');
    }
}



/**
 * Add a menu page to the admin dashboard.
 */
function uc_admin_menu() {
    add_menu_page(
        'Simply Under Construction Settings', // Page title
        'Simply Under Construction',          // Menu title
        'manage_options',              // Capability required to access this menu
        'simply-under-construction',          // Menu slug
        'uc_settings_page'             // Callback function to render the settings page
    );
}
add_action( 'admin_menu', 'uc_admin_menu' );

/**
 * Render the settings page.
 */
function uc_settings_page() {
    ?>
    <div class="wrap">
        <style>
            .switch {
                position: relative;
                display: inline-block;
                width: 40px;
                height: 24px;
            }
            .switch input { 
                opacity: 0;
                width: 0;
                height: 0;
            }
            .slider {
                position: absolute;
                cursor: pointer;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: #ccc;
                -webkit-transition: .4s;
                transition: .4s;
            }
            .slider:before {
                position: absolute;
                content: "";
                height: 16px;
                width: 16px;
                left: 4px;
                bottom: 4px;
                background-color: white;
                -webkit-transition: .4s;
                transition: .4s;
            }
            input:checked + .slider {
                background-color: #2196F3;
            }
            input:focus + .slider {
                box-shadow: 0 0 1px #2196F3;
            }
            input:checked + .slider:before {
                -webkit-transform: translateX(16px);
                -ms-transform: translateX(16px);
                transform: translateX(16px);
            }
            .slider.round {
                border-radius: 24px;
            }
            .slider.round:before {
                border-radius: 50%;
            }
            .description {
                margin-top: 5px;
            }
            #uc-status {
                vertical-align: middle;
                margin-left: 7px;
                font-weight: 500;
            }
            #uc-rich-editor-status {
                vertical-align: middle;
                margin-left: 7px;
                font-weight: 500;
            }
        </style>
        <h1>Simply Under Construction Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'uc_settings_group' ); ?>
            <?php do_settings_sections( 'uc_settings_group' ); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Under Construction Page</th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" name="uc_enabled" value="1" <?php checked( 1, get_option( 'uc_enabled', 0 ) ); ?> onchange="document.getElementById('uc-status').textContent = this.checked ? 'Enabled' : 'Disabled'" />
                            <span class="slider round"></span>
                        </label>
                        <span id="uc-status"><?php echo get_option('uc_enabled', 0) ? 'Enabled' : 'Disabled'; ?></span>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Bypass under construction page</th>
                    <td>
                        <select name="uc_access_mode">
                            <option value="administrators" <?php selected( get_option( 'uc_access_mode', 'administrators' ), 'administrators' ); ?>>Only Administrators</option>
                            <option value="all_users" <?php selected( get_option( 'uc_access_mode', 'administrators' ), 'all_users' ); ?>>All Logged In Users</option>
                        </select>
                        <p class="description">Select who can access the original site content when the under construction page is enabled.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Enable Rich Text Editor</th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" name="uc_rich_editor" value="1" <?php checked( 1, get_option( 'uc_rich_editor', 0 ) ); ?> onchange="document.getElementById('uc-rich-editor-status').textContent = this.checked ? 'Enabled' : 'Disabled'" />
                            <span class="slider round"></span>
                        </label>
                        <span id="uc-rich-editor-status"><?php echo get_option('uc_rich_editor', 0) ? 'Enabled' : 'Disabled'; ?></span>
                        <p class="description">Enable this to use the WordPress rich text editor instead of plain HTML editing.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Process Shortcodes</th>
                    <td>
                        <label class="switch">
                            <input type="checkbox" name="uc_process_shortcodes" value="1" <?php checked( 1, get_option( 'uc_process_shortcodes', 0 ) ); ?> onchange="document.getElementById('uc-shortcodes-status').textContent = this.checked ? 'Enabled' : 'Disabled'" />
                            <span class="slider round"></span>
                        </label>
                        <span id="uc-shortcodes-status"><?php echo get_option('uc_process_shortcodes', 0) ? 'Enabled' : 'Disabled'; ?></span>
                        <p class="description">Enable this to process WordPress shortcodes in your HTML content.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">Whitelisted IP Addresses</th>
                    <td>
                        <input type="text" name="uc_ip_whitelist" value="<?php echo esc_attr( get_option( 'uc_ip_whitelist', '' ) ); ?>" size="50" />
                        <p class="description">Comma-separated list of IP addresses to bypass the under construction page.</p>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">HTML Content</th>
                    <td>
                        <?php 
                        $editor_settings = array(
                            'textarea_name' => 'uc_html_content',
                            'editor_class' => 'large-text code',
                            'tinymce' => get_option( 'uc_rich_editor', 0 ) ? true : false,
                            'quicktags' => get_option( 'uc_rich_editor', 0 ) ? true : false,
                            'media_buttons' => get_option( 'uc_rich_editor', 0 ) ? true : false,
                            'wpautop' => get_option( 'uc_rich_editor', 0 ) ? true : false,
                            'editor_height' => 300
                        );
                        wp_editor( get_option( 'uc_html_content', '<!DOCTYPE html>
<html>
    <head>
        <meta charset="UTF-8">
        <title>Under Construction</title>
        <style>
            body { font-family: Arial, sans-serif; text-align: center; padding-top: 50px; }
        </style>
    </head>
    <body>
        <h1>Site Under Construction</h1>
        <p>We are currently making improvements. Please check back soon!</p>
    </body>
</html>' ), 'uc_html_content', $editor_settings ); 
                        ?>
                        <p class="description">Enter the (HTML) content to display on the under construction page. <button type="button" id="reset-html-content" class="button button-secondary">Reset to Default</button></p>
                        
                        <script>
                        jQuery(document).ready(function($) {
                            $('#reset-html-content').click(function() {
                                var defaultContent = '<!DOCTYPE html>\n\
<html>\n\
    <head>\n\
        <meta charset="UTF-8">\n\
        <title>Under Construction</title>\n\
        <style>\n\
            body { font-family: Arial, sans-serif; text-align: center; padding-top: 50px; }\n\
        </style>\n\
    </head>\n\
    <body>\n\
        <h1>Site Under Construction</h1>\n\
        <p>We are currently making improvements. Please check back soon!</p>\n\
    </body>\n\
</html>';
                                // Confirm reset
                                if (!confirm('Are you sure you want to reset the HTML content to default?')) {
                                    return;
                                }
                                
                                if (typeof tinyMCE !== 'undefined' && tinyMCE.get('uc_html_content') !== null) {
                                    // If rich editor is active
                                    tinyMCE.get('uc_html_content').setContent(defaultContent);
                                } else {
                                    // If text editor is active
                                    $('#uc_html_content').val(defaultContent);
                                }
                                
                                return false;
                            });
                        });
                        </script>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}
?>
