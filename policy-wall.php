<?php

/**
Plugin Name: Policy Wall
Plugin URI: https://sfndesign.ca
Description: Forces acceptance of site policy before site content access
Version: 2026.04.20.1042
Author: ProudCity, Curtis McHale
Author URI: https://sfndesign.ca
License: GPLv2 or later
 */

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

class PolicyWall
{
    private static $instance;

    /**
     * Spins up the instance of the plugin so that we don't get many instances running at once
     *
     * @since  1.0
     * @author SFNdesign, Curtis McHale
     *
     * @uses $instance->init()                      The main get it running function
     */
    public static function instance()
    {

        if (! self::$instance) {
            self::$instance = new PolicyWall();
            self::$instance->init();
        }
    } // instance

    /**
     * Spins up all the actions/filters in the plugin to really get the engine running
     *
     * @since  1.0
     * @author SFNdesign, Curtis McHale
     *
     * @uses $this->constants()                 Defines our constants
     * @uses $this->includes()                  Gets any includes we have
     *
     * @return null
     */
    public function init()
    {

        $this->constants();
        $this->includes();

        add_action('wp_enqueue_scripts', array($this, 'enqueue'));

        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue'));

        add_action('wp_ajax_savePolicyAgreement', array($this, 'savePolicyAgreement'));
        add_action('wp_ajax_savePolicyPageIdNumber', array($this, 'savePolicyPageIdNumber'));

        add_action('admin_menu', array($this, 'adminMenuInstructions'));
        add_action('admin_menu', array($this, 'policyWallUserAgreementList'));

        add_filter('post_row_actions', array($this, 'agreedUsersAction'), 10, 2);


        add_action('wp_ajax_exportUserCSV', array($this, 'exportUserCSV'));

        // Register hooks that are fired when the plugin is activated, deactivated, and uninstalled, respectively.
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
        register_uninstall_hook(__FILE__, array(__CLASS__, 'uninstall'));
    } // init

    /**
     * Provides the link to view all users that have agreed to a Policy
     *
     * @since  2025.11.27
     * @author Curtis <curtis@curtismchale.ca>
     *
     * @return $actions
     */
    public function agreedUsersAction($actions, $post)
    {

        // return early if we're not on the post type we want
        if ('pw_policies' !== $post->post_type) {
            return $actions;
        }

        // Build the URL to your custom page, including the post_id
        $url = add_query_arg(
            array(
                'post_type' => 'pw_policies',
                'page'      => 'pw-agreed', // matches submenu slug above
                'post_id'   => absint($post->ID),
            ),
            admin_url('edit.php')
        );

        $actions['view_extra_data'] = sprintf(
            '<a href="%s">%s</a>',
            esc_url($url),
            esc_html__('Agreed Users', 'policywall')
        );

        return $actions;
    }

    /**
     * Sets the menu for list of users that have agreed
     *
     * @since  1.0
     * @author Curtis McHale <curtis@curtismchale.ca>
     *
     * @return null
     */
    public function policyWallUserAgreementList()
    {
        add_submenu_page(
            'edit.php?post_type=pw_policies', // parent menu slug
            'List of Agreed Users', // page title
            'Agreed Users', // title in menu
            'edit_posts', // capability
            'pw-agreed', // slug
            [$this, 'pwAgreed'],
        );
    }

    /**
     * Provides the admin page to view all users that have agreed to a Policy
     *
     * @since  2025.11.27
     * @author Curtis <curtis@curtismchale.ca>
     *
     * @return null
     */
    public function pwAgreed()
    {

        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;

        echo '<div class="wrap">';

        if ($post_id) {
            $this->policySelect(absint($post_id));
            $this->pwShowAgreedTable(absint($post_id));
        } else {
            $this->policySelect();
        }

        echo '</div>';
    }

    /*
     * Shows the policy select dropdown
     *
     * @param int $post_id optional The post/policy we're viewing
     *
     * @since  2025.11.27
     * @author Curtis <curtis@curtismchale.ca>
     *
     * @return null
     */
    public function policySelect($post_id = null)
    {
?>
        <h3>Select a Policy</h3>

        <form id="policyForm" method="GET"
            action="<?php echo admin_url('edit.php'); ?>">

            <input type="hidden" name="post_type" value="pw_policies">
            <input type="hidden" name="page" value="pw-agreed">

            <label for="post_id">Choose a Policy</label>
            <select name="post_id">
                <option value="">None</option>
                <?php echo $this->getPolicySelectOptions(absint($post_id)); ?>
            </select>
            <p class="description">Choose a policy to view users that have agreed to it.</p>

            <button type="submit" class="button button-primary">Select Policy</button>
        </form>
    <?php
    }

    /**
     * Shows the table with the list of users that have agreed to the policy
     *
     * @param int $post_id required The ID of the policy/post we want to view
     *
     * @since  2025.11.27
     * @author Curtis <curtis@curtismchale.ca>
     *
     * @return null
     */
    public function pwShowAgreedTable($post_id)
    {
        $nonce     = wp_create_nonce('exportUserCSV_' . $post_id);
        $agreedUsers = get_post_meta(absint($post_id), '_users_agreed_to_policy', true);
    ?>
        <h3>Users that have agreed to <?php echo esc_html(get_the_title(absint($post_id))); ?></h3>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th scope="col">User Name</th>
                    <th scope="col">User Email</th>
                    <th scope="col">Profile Link</th>
                </tr>
            </thead>

            <tbody>
                <?php foreach ($agreedUsers as $user) {
                    $userData = get_userdata(absint($user));
                    $email = $userData->user_email;
                    $name = $userData->display_name;
                ?>
                    <tr>
                        <td class="column-primary"><?php echo esc_attr($name); ?></td>
                        <td><?php echo esc_attr($email); ?></td>
                        <td><a href="<?php echo admin_url(); ?>/user-edit.php?user_id=<?php echo absint($user); ?>">View Profile</a></td>
                    </tr>
                <?php } // foreach 
                ?>
            </tbody>
        </table>
        <br />
        <button
            id="csvExport"
            class="button button-primary"
            data-postid="<?php echo esc_attr($post_id); ?>"
            data-nonce="<?php echo esc_attr($nonce); ?>">
            Export list to CSV
        </button>
    <?php
    }

    /**
     * Builds our CSV for download
     *
     * @since  2025.12.01
     * @author Curtis <curtis@proudcity.com>
     *
     * @return null
     */
    public function exportUserCSV()
    {

        if (! apply_filters('pw_export_users_cap', current_user_can('manage_options'))) { // adjust capability as needed
            wp_die('You do not have permission to export this data.');
        }

        $policy_id = isset($_GET['policy_id']) ? absint($_GET['policy_id']) : 0;

        // Nonce check
        if (! isset($_GET['_wpnonce']) || ! wp_verify_nonce($_GET['_wpnonce'], 'exportUserCSV_' . $policy_id)) {
            wp_die('Security check failed.');
        }

        if (! $policy_id) {
            wp_die('Invalid policy.');
        }

        $policy_title = sanitize_file_name(get_the_title(absint($policy_id)));

        // Tell the browser this is a CSV download
        $filename = 'policy-' . $policy_title . '-' . absint($policy_id) . '-agreed-users-' . date('Y-m-d') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // CSV header row
        fputcsv($output, array('User Name', 'Email'));

        // ⬇️ Replace this with whatever logic you're already using in your metabox
        // to get "users that have agreed to this policy".
        $users = get_post_meta(absint($policy_id), '_users_agreed_to_policy', true);

        foreach ($users as $u) {
            // Example assumes $user is a WP_User object
            $user = get_userdata(absint($u));

            fputcsv($output, array(
                self::sanitize_csv_cell($user->display_name),
                sanitize_email($user->user_email),
            ));
        }

        fclose($output);
        exit; // or wp_die()
    }

    /**
     * Prevents spreadsheet formula injection in CSV exports.
     *
     * Spreadsheet applications (Excel, Google Sheets) treat cell values that
     * begin with =, +, -, or @ as formulas. Prefixing with a tab character
     * forces the cell to be treated as text without altering the visible value.
     *
     * @param string $value The raw cell value.
     * @return string The sanitized value, safe for fputcsv().
     */
    private static function sanitize_csv_cell(string $value): string
    {
        if (in_array(mb_substr($value, 0, 1), ['=', '+', '-', '@'], true)) {
            return "\t" . $value;
        }
        return $value;
    }

    public function getPolicySelectOptions($post_id = null)
    {
        $args = array(
            'post_type' => 'pw_policies',
            'posts_per_page' => -1,
            'fields' => 'ids',
        );
        $html = '';

        $policies = get_posts($args);

        foreach ($policies as $p) {
            $html .= '<option value="' . absint($p) . '" ' . selected(absint($post_id), absint($p), false) . '>' . esc_html(get_the_title(absint($p))) . '</option>';
        }

        return $html;
    }


    /**
     * Sets the menu for instructions
     *
     * @since  1.0
     * @author Curtis McHale <curtis@curtismchale.ca>
     *
     * @return null
     */
    public function adminMenuInstructions()
    {
        add_submenu_page(
            'edit.php?post_type=pw_policies', // parent menu slug
            'Policy Wall Instructions', // page title
            'Instructions', // title in menu
            'edit_posts', // capability
            'pw-instructions', // slug
            [$this, 'pwInstructions'],
        );
    }

    /**
     * Provides an admin page with instructions
     *
     * @since  1.0
     * @author Curtis McHale <curtis@curtismchale.ca>
     *
     * @return null
     */
    public function pwInstructions()
    {
        $policy_page_id = get_option('pw_policy_page_id');

        if (! isset($policy_page_id) || empty($policy_page_id)) {
            $policy_page_id = null;
        }

    ?>
        <div class="wrap">
            <h1>Policy Wall Instructions</h1>
            <p>To use Policy Wall you need to setup a new Policy. Only the most recent policy is used to block a user's access to the site.</p>
            <p>Every time you add a new policy to the site it will become active and users must agree to it to use your site.</p>
            <p>Site Administrators are exempt from agreeing to policies.</p>

            <h2>Shortcodes</h2>
            <p>Content from other parts of your site can be imported with the shortcodes below. The <code>embed</code> shortcodes should <strong>only</strong> be used inside the Policy Custom Post type entries not on a custom policy page.</p>

            <h3><code>[pw_show_latest_policy]</code></h3>
            <p><code>[pw_show_latest_policy]</code>: Shows the most recently published policy on the page along with save functions. Users will be redirected here when a new policy is published.</p>
            <p><strong>Note:</strong> You MUST set the policy page ID if you want to use a generic policy page as your default redirection location. Without setting this users will be redirected to the URL of the most recent policy.</p>

            <h3><code>[pw_embed_content]</code></h3>
            <p><code>[pw_embed_content]</code>: Used to embed the full content of other pages.</p>
            <p><code>[pw_embed_content contentid="12"]</code>: Would be used to embed the content of a page/post with the id of <code>12</code></p>
            <p><strong>Example</strong>: Given an admin url like:<code>https://example.com/wp-admin/post.php?post=12029&action=edit</code> the <code>contentid</code> is <code>12029</code>. This would only embed any content in the standard WordPress text box.</p>

            <h3><code>[pw_embed_content_accordion]</code></h3>
            <p><code>[pw_embed_content_accordion]</code>: Used to embed the full content of other pages inside an accordion that can be opened by users to view the content. Use the <code>title</code> option to set a title for the accordion.</p>
            <p><strong>Example:</strong></p>
            <p><code>[pw_embed_content_accordion]</code>: Embeds the content and uses the standard title of the page as the accordion title.</p>
            <p><code>[pw_embed_content_accordion title="Custom Title"]</code>: Embeds the content and uses "Custom Title" as the accordion title.</p>

            <p><strong>Example:</strong></p>
            <p><code>[pw_embed_content_accordion]</code>: Embeds the content and uses the standard title of the page as the accordion title.</p>
            <p><code>[pw_embed_content_accordion title="Custom Title"]</code>: Embeds the content and uses "Custom Title" as the accordion title.</p>

            <h2>Custom Policy Page ID Form</h2>
            <p><strong>Example</strong>: Given a url like <code>https://example.com/wp-admin/post.php?post=12029&action=edit</code> the content ID is <code>12029</code>. That ID would go in the field at the bottom of this page.</p>
            <p>Setting the ID to <code>0</code> removes the association with a custom page.</p>

            <form id="pw_custom_policy_page_id_form">
                <table class="form-table" role="presentation">
                    <tr>
                        <th><label for="policy_page_number">Insert the post_id for the custom policy page.</label></th>
                        <td><input id="policy_page_number" name="policy_page_number" value="<?php echo esc_attr($policy_page_id); ?>" type="number"></input></td>
                    </tr>
                </table>
                <?php submit_button(
                    'Save Policy Page ID', // button text
                    'primary', // type
                    '', // name
                    true, // wrap
                    array('id' => 'policy_page_number_submit'),
                ); ?>
                <span class="pwa-spinner hidden"><img src="<?php admin_url('images/spinner.gif') ?>" /></span>
                <p id="pw_user_feedback"></p>
            </form>
        </div><!-- /.wrap -->
<?php
    }

    /**
     * Saves the custom policy page ID
     *
     * @uses check_ajax_referer() Makes sure the security nonce is right
     * @uses self::_saveUserToPolicy() Saves the user ID to the provided policy
     * @uses self::_savePolicyToUser() Saves the policy ID to the given user
     * @uses wp_kses_post() Makes the data safe
     * @uses wp_send_json_success() Sends success message in json back to our JS
     *
     * @since  1.0
     * @author Curtis McHale <curtis@curtismchale.ca>
     *
     * @return array $data Sends response via wp_send_json_success()
     */
    public function savePolicyPageIdNumber()
    {
        check_ajax_referer('pwa_ajax_nonce', 'security');

        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized.']);
            return;
        }

        $success = false;
        $message = 'We were unable to save the custom Policy Page ID. Please contact your site administrator.';

        $policyId = absint($_POST['policyId']);

        $policIdSaved = update_option('pw_policy_page_id', absint($policyId), false);

        if (true === $policIdSaved) {
            $success = true;
            $message = 'Policy ID saved.';
        }

        $data = array(
            'success' => (bool) $success,
            'message' => wp_kses_post($message),
        );

        wp_send_json_success($data);
    } // savePolicyAgreement

    /**
     * Saves the agreement status of a user
     *
     * @uses check_ajax_referer() Makes sure the security nonce is right
     * @uses self::_saveUserToPolicy() Saves the user ID to the provided policy
     * @uses self::_savePolicyToUser() Saves the policy ID to the given user
     * @uses wp_kses_post() Makes the data safe
     * @uses wp_send_json_success() Sends success message in json back to our JS
     *
     * @since  1.0
     * @author Curtis McHale <curtis@curtismchale.ca>
     *
     * @return array $data Sends response via wp_send_json_success()
     */
    public function savePolicyAgreement()
    {
        check_ajax_referer('pw_ajax_nonce', 'security');

        if (absint($_POST['userId']) !== get_current_user_id()) {
            wp_send_json_error(['message' => 'Unauthorized.']);
            return;
        }

        $success = false;
        $message = 'We were unable to save your agreement to the policy. Please contact your site administrator.';

        $userId = absint($_POST['userId']);
        $policyId = absint($_POST['policyId']);

        $userSavedToPolicy = self::saveUserToPolicy(absint($userId), absint($policyId));
        $policySavedToUser = self::savePolicyToUser(absint($userId), absint($policyId));

        if (true === $userSavedToPolicy && true === $policySavedToUser) {
            $success = true;
            $message = 'Agreement to the policy has been saved.';
        }

        $data = array(
            'success' => (bool) $success,
            'message' => wp_kses_post($message),
        );

        wp_send_json_success($data);
    } // savePolicyAgreement

    /**
     * Saves the current policyId to usermeta
     *
     * @param int $userId   required The ID of the user we're saving the agreement for
     * @param int $policyId required The ID of the policy the user is agreeing to
     *
     * @uses get_user_meta() Returns user meta given user ID and meta key
     * @uses add_user_meta() Adds another value to the meta keys without overriding
     *
     * @since  1.0
     * @author Curtis McHale <curtis@curtismchale.ca>
     *
     * @return bool $saved True if the value was saved
     */
    public static function savePolicyToUser($userId, $policyId)
    {

        $savedToUser = false;
        $userAgreements = get_user_meta(absint($userId), '_policy_agreed_to', false);

        if (!in_array(absint($policyId), $userAgreements, true)) {
            $savedToUser = add_user_meta(absint($userId), '_policy_agreed_to', absint($policyId));
        } else {
            return true; // it was already saved
        }

        if ($savedToUser !== false) {
            $savedToUser = true; // saved properly
        } else {
            // add_user_meta may return many values but only TRUE means it was saved
            // so all others are a failure value of some sort
            $savedToUser = false;
        }

        return (bool) $savedToUser;
    } // _savePolicyToUser

    /**
     * Saves the userID to the Policy that they have agreed to
     *
     * @param int $userId   required The ID of the user we're saving the agreement for
     * @param int $policyId required The ID of the policy the user is agreeing to
     *
     * @uses get_post_meta()   Returns post meta given post ID and meta key
     * @uses update_post_meta() Updates meta value overwriting the old one
     *
     * @since  1.0
     * @author Curtis McHale <curtis@curtismchale.ca>
     *
     * @return bool $saved True if the userId was saved to the current policy
     */
    public static function saveUserToPolicy($userId, $policyId)
    {

        $saved = false;
        $agreedUser = get_post_meta(absint($policyId), '_users_agreed_to_policy', true);

        if (!in_array(absint($userId), (array) $agreedUser)) {
            // if not saved once it won't be an array so we force it
            if (!is_array($agreedUser)) {
                $agreedUser = array();
            }
            $agreedUser[] = absint($userId);
        } else {
            return true; // because it already existed and we can exit early
        }

        $saved = update_post_meta(absint($policyId), '_users_agreed_to_policy', $agreedUser);

        return (bool) $saved;
    } // _saveUserToPolicy

    /**
     * Registers and enqueues scripts and styles
     *
     * @uses wp_enqueue_style
     * @uses wp_enqueue_script
     *
     * @since  1.0
     * @author SFNdesign, Curtis McHale
     *
     * @return null
     */
    public function admin_enqueue()
    {

        // version plugin
        $plugin_data = get_plugin_data(__FILE__);

        $screen = get_current_screen();

        // only enqueue on the page we need it on
        if (
            'pw_policies_page_pw-instructions' !== $screen->id
            && 'pw_policies_page_pw-agreed' !== $screen->id
        ) {
            return;
        }

        // scripts plugin
        wp_enqueue_script(
            'pw_admin_scripts',
            plugins_url('/policy-wall/assets/pw-admin-scripts.js'),
            array('jquery'),
            esc_attr($plugin_data['Version']),
            array(
                'in_footer' => true,
                'srategy' => 'async'
            )
        );

        wp_localize_script(
            'pw_admin_scripts',
            'PWA',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'pwa_ajax_nonce' => wp_create_nonce('pwa_ajax_nonce'),
            )
        );
    }

    /**
     * Registers and enqueues scripts and styles
     *
     * @uses wp_enqueue_style
     * @uses wp_enqueue_script
     *
     * @since  1.0
     * @author SFNdesign, Curtis McHale
     *
     * @return null
     */
    public function enqueue()
    {

        // version plugin
        $plugin_data = get_plugin_data(__FILE__);

        // styles plugin
        wp_enqueue_style(
            'pw_frontent_styles',
            plugins_url('/policy-wall/assets/pw-frontend-styles.css'),
            '',
            esc_attr($plugin_data['Version']),
            'all'
        );

        // scripts plugin
        wp_enqueue_script(
            'pw_front_scripts',
            plugins_url('/policy-wall/assets/pw-front-scripts.js'),
            array('jquery'),
            esc_attr($plugin_data['Version']),
            array(
                'in_footer' => true,
                'srategy' => 'async'
            )
        );

        wp_localize_script(
            'pw_front_scripts',
            'PW',
            array(
                'ajaxurl' => admin_url('admin-ajax.php'),
                'pw_ajax_nonce' => wp_create_nonce('pw_ajax_nonce'),
            )
        );
    }

    /**
     * Gives us any constants we need in the plugin
     *
     * @since 1.0
     */
    public function constants()
    {

        define('POLICY_WALL_PLUGIN_DIR', plugin_dir_path(__FILE__));
    }

    /**
     * Includes any externals
     *
     * @since 1.0
     * @author SFNdesign, Curtis McHale
     * @access public
     *
     * @return null
     */
    public function includes()
    {

        include_once POLICY_WALL_PLUGIN_DIR . 'includes/cpt.php';
        include_once POLICY_WALL_PLUGIN_DIR . 'includes/shortcodes.php';
        include_once POLICY_WALL_PLUGIN_DIR . 'includes/metaboxes.php';

        include_once POLICY_WALL_PLUGIN_DIR . 'restrictions/generic.php';

        // @todo include the metaboxes to show which users agreed to the policy

        if (is_plugin_active('buddypress/bp-loader.php')) {
            include_once POLICY_WALL_PLUGIN_DIR . 'restrictions/buddypress.php';
        }
    }

    /**
     * Fired when plugin is activated
     *
     * @param   bool    $network_wide   TRUE if WPMU 'super admin' uses Network Activate option
     */
    public function activate($network_wide) {} // activate

    /**
     * Fired when plugin is deactivated
     *
     * @param   bool    $network_wide   TRUE if WPMU 'super admin' uses Network Activate option
     */
    public function deactivate($network_wide) {} // deactivate

    /**
     * Fired when plugin is uninstalled
     *
     * @param   bool    $network_wide   TRUE if WPMU 'super admin' uses Network Activate option
     */
    public function uninstall($network_wide) {} // uninstall

} // PolicyWall

PolicyWall::instance();
