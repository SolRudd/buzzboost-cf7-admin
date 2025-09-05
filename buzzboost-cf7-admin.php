<?php

/**
 * Plugin Name: BuzzBoost Submissions for Contact Form 7 (Admin-Only)
 * Plugin URI:  https://github.com/SolRudd/buzzboost-cf7-admin
 * Description: Saves Contact Form 7 submissions into a private, admin-only post type with CSV export. No front-end output.
 * Version:     1.4.3
 * Author:      BuzzBoost Digital
 * Text Domain: buzzboost-cf7-admin
 * Domain Path: /languages
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Update URI:  https://github.com/SolRudd/buzzboost-cf7-admin
 */

if (!defined('ABSPATH')) exit;

define('BBD_CF7_ADMIN_VERSION', '1.4.3');

/**
 * I18N
 */
add_action('plugins_loaded', function () {
    load_plugin_textdomain('buzzboost-cf7-admin', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

/**
 * Optional: GitHub updates via Plugin Update Checker v5.6
 * - Fully guarded: will not fatal if files missing or other plugins load PUC first.
 * - Kill switch: define('BBD_CF7_DISABLE_PUC', true) in wp-config.php to disable.
 */
add_action('init', function () {
    if (!is_admin()) return;
    if (defined('BBD_CF7_DISABLE_PUC') && BBD_CF7_DISABLE_PUC) return;

    $loader = plugin_dir_path(__FILE__) . 'includes/plugin-update-checker/load-v5p6.php';

    if (is_readable($loader) && !class_exists('Puc_v5p6_Factory', false)) {
        require $loader;
    }

    if (class_exists('Puc_v5p6_Factory', false)) {
        $updateChecker = Puc_v5p6_Factory::buildUpdateChecker(
            'https://github.com/SolRudd/buzzboost-cf7-admin',
            __FILE__,
            'buzzboost-cf7-admin'
        );
        $updateChecker->setBranch('main');

        // For private repos later:
        // define('BBD_CF7_GITHUB_TOKEN', 'YOUR_TOKEN_HERE'); // in wp-config.php
        // if (defined('BBD_CF7_GITHUB_TOKEN')) {
        //     $updateChecker->setAuthentication(BBD_CF7_GITHUB_TOKEN);
        //     // $updateChecker->getVcsApi()->enableReleaseAssets();
        // }
    }
});

/**
 * Gentle notice if CF7 is missing
 */
add_action('admin_init', function () {
    if (!class_exists('WPCF7')) {
        add_action('admin_notices', function () {
            echo '<div class="notice notice-warning"><p><strong>BuzzBoost CF7 Submissions:</strong> Contact Form 7 is not active. Install/activate CF7 to save submissions.</p></div>';
        });
    }
});

/**
 * Register private CPT for submissions
 */
function bbd_register_cf7_cpt()
{
    $labels = array(
        'name'               => _x('Form Submissions', 'Post Type General Name', 'buzzboost-cf7-admin'),
        'singular_name'      => _x('Form Submission', 'Post Type Singular Name', 'buzzboost-cf7-admin'),
        'menu_name'          => __('Form Submissions', 'buzzboost-cf7-admin'),
        'all_items'          => __('All Submissions', 'buzzboost-cf7-admin'),
        'search_items'       => __('Search Submissions', 'buzzboost-cf7-admin'),
        'not_found'          => __('Not Found', 'buzzboost-cf7-admin'),
        'not_found_in_trash' => __('Not found in Trash', 'buzzboost-cf7-admin'),
    );

    register_post_type('cf7_submission', array(
        'labels'              => $labels,
        'description'         => __('Contact Form 7 Submissions', 'buzzboost-cf7-admin'),
        'supports'            => array('title', 'editor'),
        'hierarchical'        => false,
        'public'              => false,
        'publicly_queryable'  => false,
        'exclude_from_search' => true,
        'has_archive'         => false,
        'rewrite'             => false,
        'query_var'           => false,
        'show_ui'             => true,
        'show_in_menu'        => 'bbd-cf7',
        'show_in_admin_bar'   => false,
        'show_in_nav_menus'   => false,
        'show_in_rest'        => false,
        'menu_icon'           => 'dashicons-email-alt',
        'capability_type'     => 'post',
        'map_meta_cap'        => true,
        'capabilities'        => array(
            'create_posts' => 'do_not_allow', // no manual "Add New"
        ),
    ));
}
add_action('init', 'bbd_register_cf7_cpt');

/**
 * Admin menu + Export submenu
 */
add_action('admin_menu', function () {
    if (!current_user_can('manage_options')) return;

    add_menu_page(
        __('Form Submissions', 'buzzboost-cf7-admin'),
        __('Form Submissions', 'buzzboost-cf7-admin'),
        'manage_options',
        'bbd-cf7',
        function () {
            wp_safe_redirect(admin_url('edit.php?post_type=cf7_submission'));
            exit;
        },
        'dashicons-email-alt',
        26
    );

    add_submenu_page(
        'edit.php?post_type=cf7_submission',
        __('Export CSV', 'buzzboost-cf7-admin'),
        __('Export CSV', 'buzzboost-cf7-admin'),
        'manage_options',
        'bbd-cf7-export',
        'bbd_cf7_export_page'
    );
}, 20);

/**
 * Gate submission screens to Admins only
 */
function bbd_gate_submission_screens()
{
    if (!is_admin()) return;
    if (!function_exists('get_current_screen')) return;
    $screen = get_current_screen();
    if ($screen && in_array($screen->id, array('edit-cf7_submission', 'cf7_submission'), true)) {
        if (!current_user_can('manage_options')) {
            wp_die(__('Sorry, you are not allowed to access submissions.', 'buzzboost-cf7-admin'));
        }
    }
}
add_action('load-edit.php', 'bbd_gate_submission_screens');
add_action('load-post.php', 'bbd_gate_submission_screens');
add_action('load-post-new.php', 'bbd_gate_submission_screens');

/**
 * Save CF7 submission after mail is sent
 */
add_action('wpcf7_mail_sent', function ($contact_form) {
    if (!class_exists('WPCF7_Submission')) return;

    $submission = WPCF7_Submission::get_instance();
    if (!$submission) return;

    $form_title = method_exists($contact_form, 'title') ? sanitize_text_field($contact_form->title()) : 'Contact Form 7';
    $data = $submission->get_posted_data();
    if (!is_array($data)) return;

    $post_content = '';
    $safe_map = array();

    foreach ($data as $key => $value) {
        if (strpos((string)$key, '_wpcf7') === 0 || $key === 'g-recaptcha-response' || $key === 'submit') continue;
        $label = esc_html(ucwords(str_replace(array('_', '-'), ' ', (string)$key)));

        if (is_array($value)) {
            $san = array_map('sanitize_text_field', $value);
            $post_content .= "<p><strong>{$label}:</strong><br>" . esc_html(implode(', ', $san)) . "</p>";
            $safe_map[$key] = $san;
        } else {
            $san = sanitize_text_field((string)$value);
            $post_content .= "<p><strong>{$label}:</strong> " . esc_html($san) . "</p>";
            $safe_map[$key] = $san;
        }
    }

    // Uploaded files
    $uploaded_files = $submission->uploaded_files();
    if (!empty($uploaded_files) && is_array($uploaded_files)) {
        $post_content .= '<p><strong>Uploaded Files:</strong><br>';
        foreach ($uploaded_files as $field_name => $path) {
            if (is_array($path)) $path = reset($path);
            $filename = $path ? wp_basename($path) : '';
            $post_content .= esc_html(ucwords(str_replace(array('_', '-'), ' ', (string)$field_name))) . ': ' . esc_html($filename) . '<br>';
        }
        $post_content .= '</p>';
    }

    $post_id = wp_insert_post(array(
        'post_title'   => sprintf('Submission — %s', $form_title),
        'post_content' => wp_kses_post($post_content),
        'post_status'  => 'private',
        'post_type'    => 'cf7_submission',
    ));
    if (!$post_id || is_wp_error($post_id)) return;

    update_post_meta($post_id, '_form_title', $form_title);

    foreach ($safe_map as $key => $val) {
        $meta_key = '_' . sanitize_key((string)$key);
        if (is_array($val)) {
            update_post_meta($post_id, $meta_key, array_map('sanitize_text_field', $val));
        } else {
            update_post_meta($post_id, $meta_key, sanitize_text_field((string)$val));
        }
    }

    if (!empty($uploaded_files) && is_array($uploaded_files)) {
        foreach ($uploaded_files as $field_name => $path) {
            if (is_array($path)) $path = reset($path);
            update_post_meta($post_id, '_file_' . sanitize_key((string)$field_name), sanitize_text_field((string)$path));
        }
    }
});

/**
 * Remove "View" action (no front-end)
 */
add_filter('post_row_actions', 'bbd_cf7_remove_view_action', 10, 2);
add_filter('page_row_actions', 'bbd_cf7_remove_view_action', 10, 2);
function bbd_cf7_remove_view_action($actions, $post)
{
    if (isset($post->post_type) && $post->post_type === 'cf7_submission') {
        unset($actions['view']);
        unset($actions['inline hide-if-no-js']);
    }
    return $actions;
}

/**
 * Helpers
 */
function bbd_cf7_first_meta($post_id, $keys)
{
    foreach ($keys as $key) {
        $val = get_post_meta($post_id, '_' . sanitize_key($key), true);
        if ($val !== '' && $val !== null) {
            return is_array($val) ? implode(', ', array_map('strval', $val)) : $val;
        }
    }
    return '';
}

function bbd_cf7_guess_full_name($post_id)
{
    $first = get_post_meta($post_id, '_first-name', true);
    $last  = get_post_meta($post_id, '_last-name', true);
    $full  = trim((string)$first . ' ' . (string)$last);
    if ($full !== '') return $full;
    return bbd_cf7_first_meta($post_id, array('your-name', 'name', 'full_name', 'fullname', 'first-name', 'first_name', 'contact-name'));
}

/**
 * Admin columns
 */
add_filter('manage_cf7_submission_posts_columns', function ($cols) {
    $new = array();
    $new['cb']        = isset($cols['cb']) ? $cols['cb'] : '';
    $new['title']     = __('Title', 'buzzboost-cf7-admin');
    $new['bbd_name']  = __('Name', 'buzzboost-cf7-admin');
    $new['bbd_email'] = __('Email', 'buzzboost-cf7-admin');
    $new['bbd_phone'] = __('Phone', 'buzzboost-cf7-admin');
    $new['bbd_form']  = __('Form', 'buzzboost-cf7-admin');
    $new['date']      = isset($cols['date']) ? $cols['date'] : __('Date', 'buzzboost-cf7-admin');
    return $new;
});
add_action('manage_cf7_submission_posts_custom_column', function ($col, $post_id) {
    if ($col === 'bbd_name') {
        echo esc_html(bbd_cf7_guess_full_name($post_id));
    } elseif ($col === 'bbd_email') {
        echo esc_html(bbd_cf7_first_meta($post_id, array('your-email', 'email', 'email_address', 'contact-email')));
    } elseif ($col === 'bbd_phone') {
        echo esc_html(bbd_cf7_first_meta($post_id, array('tel', 'phone', 'your-phone', 'phone_number', 'contact-phone')));
    } elseif ($col === 'bbd_form') {
        echo esc_html(get_post_meta($post_id, '_form_title', true));
    }
}, 10, 2);

/**
 * Export page
 */
function bbd_cf7_export_page()
{
    if (!current_user_can('manage_options')) return; ?>
    <div class="wrap">
        <h1><?php esc_html_e('Export Submissions (CSV)', 'buzzboost-cf7-admin'); ?></h1>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <?php wp_nonce_field('bbd_cf7_export_nonce', 'bbd_cf7_export_nonce'); ?>
            <input type="hidden" name="action" value="bbd_cf7_export">
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="from"><?php esc_html_e('From date', 'buzzboost-cf7-admin'); ?></label></th>
                    <td><input type="date" id="from" name="from" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="to"><?php esc_html_e('To date', 'buzzboost-cf7-admin'); ?></label></th>
                    <td><input type="date" id="to" name="to" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="form_title"><?php esc_html_e('Filter by form title', 'buzzboost-cf7-admin'); ?></label></th>
                    <td><input type="text" id="form_title" name="form_title" placeholder="e.g. Website Enquiry" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="limit"><?php esc_html_e('Limit', 'buzzboost-cf7-admin'); ?></label></th>
                    <td><input type="number" id="limit" name="limit" min="1" step="1" value="1000" /></td>
                </tr>
            </table>
            <?php submit_button(__('Download CSV', 'buzzboost-cf7-admin')); ?>
        </form>
    </div>
<?php }

/**
 * CSV download handler
 */
add_action('admin_post_bbd_cf7_export', function () {
    if (!current_user_can('manage_options')) wp_die(__('Insufficient permissions.', 'buzzboost-cf7-admin'));
    if (!isset($_POST['bbd_cf7_export_nonce']) || !wp_verify_nonce($_POST['bbd_cf7_export_nonce'], 'bbd_cf7_export_nonce')) {
        wp_die(__('Invalid request.', 'buzzboost-cf7-admin'));
    }

    $from       = isset($_POST['from']) ? sanitize_text_field(wp_unslash($_POST['from'])) : '';
    $to         = isset($_POST['to']) ? sanitize_text_field(wp_unslash($_POST['to'])) : '';
    $limit      = isset($_POST['limit']) ? max(1, (int) $_POST['limit']) : 1000;
    $form_title = isset($_POST['form_title']) ? sanitize_text_field(wp_unslash($_POST['form_title'])) : '';

    $meta_query = array();
    if ($form_title !== '') {
        $meta_query[] = array(
            'key'     => '_form_title',
            'value'   => $form_title,
            'compare' => 'LIKE',
        );
    }

    $args = array(
        'post_type'        => 'cf7_submission',
        'post_status'      => array('private', 'publish'),
        'posts_per_page'   => $limit,
        'orderby'          => 'date',
        'order'            => 'DESC',
        'date_query'       => array(),
        'fields'           => 'ids',
        'meta_query'       => $meta_query,
        'suppress_filters' => true,
        'no_found_rows'    => true,
    );

    if ($from) $args['date_query'][] = array('after' => $from);
    if ($to)   $args['date_query'][] = array('before' => $to . ' 23:59:59');

    $ids = get_posts($args);
    if (empty($ids)) {
        wp_safe_redirect(add_query_arg('bbd_export', 'empty', wp_get_referer() ?: admin_url('edit.php?post_type=cf7_submission&page=bbd-cf7-export')));
        exit;
    }

    // Clean all output buffers before headers
    if (function_exists('ob_get_level')) {
        while (ob_get_level()) {
            ob_end_clean();
        }
    }

    nocache_headers();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=cf7-submissions-' . gmdate('Ymd-His') . '.csv');

    $out = fopen('php://output', 'w');

    // Build dynamic header from all meta keys (except helper/file paths)
    $all_keys = array();
    foreach ($ids as $pid) {
        $meta = get_post_meta($pid);
        foreach ($meta as $k => $vals) {
            if (!is_string($k) || $k === '' || $k[0] !== '_') continue;
            if ($k === '_form_title') continue;
            if (strpos($k, '_file_') === 0) continue; // skip file paths
            $all_keys[$k] = true;
        }
    }
    ksort($all_keys);
    $columns = array_merge(array('ID', 'Date', 'Form'), array_map(function ($k) {
        return ltrim($k, '_');
    }, array_keys($all_keys)));
    fputcsv($out, $columns);

    foreach ($ids as $post_id) {
        $row = array();
        $row[] = $post_id;

        // Get local time string safely
        $post_obj = get_post($post_id);
        $row[] = $post_obj ? $post_obj->post_date : '';

        $row[] = get_post_meta($post_id, '_form_title', true);

        $meta = get_post_meta($post_id);
        foreach (array_keys($all_keys) as $k) {
            if (!isset($meta[$k])) {
                $row[] = '';
                continue;
            }
            $val = is_array($meta[$k]) ? reset($meta[$k]) : $meta[$k];
            $val = maybe_unserialize($val);
            if (is_array($val)) $val = implode(', ', array_map('strval', $val));
            $row[] = $val;
        }

        fputcsv($out, $row);
    }

    fclose($out);
    exit;
});

/**
 * Notice when export found nothing
 */
add_action('admin_notices', function () {
    if (!current_user_can('manage_options')) return;
    if (isset($_GET['bbd_export']) && $_GET['bbd_export'] === 'empty') {
        echo '<div class="notice notice-info is-dismissible"><p>' .
            esc_html__('No submissions matched your export filter.', 'buzzboost-cf7-admin') .
            '</p></div>';
    }
});

/**
 * Plugins screen quick links
 */
add_filter('plugin_action_links_' . plugin_basename(__FILE__), function ($links) {
    $view_url   = admin_url('edit.php?post_type=cf7_submission');
    $export_url = admin_url('edit.php?post_type=cf7_submission&page=bbd-cf7-export');
    array_unshift($links, '<a href="' . esc_url($view_url) . '">' . esc_html__('View Submissions', 'buzzboost-cf7-admin') . '</a>');
    $links[] = '<a href="' . esc_url($export_url) . '">' . esc_html__('Export CSV', 'buzzboost-cf7-admin') . '</a>';
    return $links;
});
