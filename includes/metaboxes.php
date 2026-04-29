<?php
/**
 * Provides the displayable list of users that have agreed to a policy
 *
 * @package PolicyWall\Metaboxes
 * @author  Curtis McHale, <curtis@curtismchale.ca>
 * @license https://opensource.org/licenses/gpl-license.php GNU Public License
 * @see     https://sfndesign.ca
 */
class PolicyWallMetaboxes
{

    private static $_instance;

    /**
     * Spins up the instance of the plugin so that we don't get many instances running at once
     *
     * @since  1.0
     * @author SFNdesign, Curtis McHale
     *
     * @uses $instance->init()                      The main get it running function
     *
     * @return null
     */
    public static function instance()
    {

        if (! self::$_instance) {
            self::$_instance = new PolicyWallMetaboxes();
            self::$_instance->init();
        }

    } // instance

    /**
     * Spins up all the actions/filters in the plugin to really get the engine running
     *
     * @since  1.0
     * @author SFNdesign, Curtis McHale
     *
     * @return null
     */
    public function init()
    {

        add_action('add_meta_boxes', [$this, 'addMetabox']);

    } // init

    /**
     * Adds the metabox to our CPT
     *
     * @since  2025.09.16
     * @author Curtis McHale <curtis@curtismchale.ca>
     *
     * @uses add_meta_box() WP Function that adds the metabox to the page/CPT defined in the 4th param
     *
     * @return null
     */
    public function addMetabox()
    {
        add_meta_box(
            'pw_users_agreed_to_policy', // metabox_id
            'Agreed Users', // metabox title
            [$this, 'renderAgreedMetabox'], // callback
            'pw_policies', // CPT
            'normal', // context
            'default' // priority
        );

    } // addMetabox

    /**
     * Renders the metabox so we can see who agreed to a policy
     *
     * @param object $postObject required The post object that we're working with
     *
     * @since  2025.09.16
     * @author Curtis McHale <curtis@curtismchale.ca>
     *
     * @uses get_post_meta() Returns post meta given post_id and key
     * @uses get_userdata()  Returns userdata given user_id
     *
     * @return string Just echoes the string onto the page
     */
    public function renderAgreedMetabox( $postObject )
    {

        $users = get_post_meta(absint($postObject->ID), '_users_agreed_to_policy', true);

        // deal with no value being set
        if (empty($users)) {
            echo 'No users have agreed';
            return;
        }
        ?>

        <ul>

        <?php
        foreach ($users as $u) {
            $userData = get_userdata(absint($u));
            $email = $userData->user_email;
            $name = $userData->display_name;
            ?>

            <li>
                <?php echo esc_html($name); ?>
                (<?php echo esc_html($email); ?>)
                - <a href="<?php echo esc_url(admin_url('user-edit.php?user_id=' . absint($u))); ?>">View Profile</a>
            </li>

        <?php } ?>

        </ul>
        <?php
    }

}

PolicyWallMetaboxes::instance();
