<?php

/**
 * Does restrictions for the BuddyPress environment
 *
 * @package PolicyWall\Restrictions/BuddyPress
 * @author  Curtis McHale, <curtis@curtismchale.ca>
 * @license https://opensource.org/licenses/gpl-license.php GNU Public License
 * @see     https://sfndesign.ca
 */
class PwBuddyPress extends PwGeneric
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
            self::$_instance = new PwBuddyPress();
            self::$_instance->init();
        }

    } // instance

    /**
     * Spins up all the actions/filters in the plugin to really get the engine running
     *
     * @since 1.0
     * @author SFNdesign, Curtis McHale
     */
    public function init()
    {
        // @todo add user agreement form to BP signup
        // @todo check bp_init to see if user has agreed
        //  - should see about caching that instead of checking every time BP starts
        // @todo make sure groups are closed
        // @todo make sure profiles are closed
        // @todo make sure private messaging is closed

    } // init


}

PwBuddyPress::instance();
