<?php

/**
 * Provides the custom post type and taxonomies for Privacy Wall
 *
 * @package PolicyWall\CustomPostType
 * @author  Curtis McHale, <curtis@curtismchale.ca>
 * @license https://opensource.org/licenses/gpl-license.php GNU Public License
 * @see     https://sfndesign.ca
 */
class PolicyWallCpt
{

    private static $_instance;

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

        if ( ! self::$_instance ){
            self::$_instance = new PolicyWallCpt();
            self::$_instance->init();
        }

    } // instance

    /**
     * Spins up all the actions/filters in the plugin to really get the engine running
     *
     * @since  1.0
     * @author SFNdesign, Curtis McHale
     */
    public function init()
    {
        add_action( 'init', [__CLASS__, 'registerPostType']);
    } // init

    /**
     * Builds out the custom post types for the site
     *
     * @uses register_post_type Adds the CPT to WP
     *
     * @since  1.0
     * @author Curtis McHale, <curtis@curtismchale.ca>
     *
     * @return null
     */
    public static function registerPostType()
    {

        register_post_type(
            'pw_policies', // http://codex.wordpress.org/Function_Reference/register_post_type
            array(
                'labels'                => array(
                    'name'                  => __('Policies'),
                    'singular_name'         => __('Policy'),
                    'add_new'               => __('Add New'),
                    'add_new_item'          => __('Add New Policy'),
                    'edit'                  => __('Edit'),
                    'edit_item'             => __('Edit Policy'),
                    'new_item'              => __('New Policy'),
                    'view'                  => __('View Policies'),
                    'view_item'             => __('View Policy'),
                    'search_items'          => __('Search Policies'),
                    'not_found'             => __('No Policies Found'),
                    'not_found_in_trash'    => __('No Policies found in Trash')
                ), // end array for labels
                'public'                => true,
                'menu_position'         => 5, // sets admin menu position
                'show_in_rest'          => true,
                'menu_icon'             => 'dashicons-welcome-write-blog',
                'hierarchical'          => false, // funcions like posts
                'supports'              => array('title', 'editor', 'revisions'),
                'rewrite'               => array('slug' => 'pw-policy', 'with_front' => true,), // permalinks format
                'can_export'            => true,
            )
        );

    }


}

PolicyWallCpt::instance();
