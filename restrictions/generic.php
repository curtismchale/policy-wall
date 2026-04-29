<?php

/**
 * Provides generic restrictions for all environments
 *
 * @package PolicyWall\Restrictions\Generic
 * @author  Curtis McHale, <curtis@curtismchale.ca>
 * @license https://opensource.org/licenses/gpl-license.php GNU Public License
 * @see     https://sfndesign.ca
 */
class PwGeneric
{

    private static $_instance;

    const TRANSIENT_KEY = 'pw_latest_privacy_agreement_id';

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
            self::$_instance = new PwGeneric();
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
        // @todo save agreement to user and CPT entry
        // @todo show agreement on a page
        //   - allow site owner to choose the page
        // @todo build WP Block to show the latest policy
        // @todo build shortcode to show the latest policy
        // @todo shortcode to show previous policies
        // @todo show revisions of the policy?
        //   - is that really in scope for the Intranet project?

        add_action('save_post', [__CLASS__, 'invalidatePolicyCacheKey'], 10, 3);
        add_action('login_redirect', [__CLASS__, 'checkAgreementOnLogin'], 10, 3);
        add_action('template_redirect', [__CLASS__, 'enforceAgreementForActiveSession']);
    } // init


    /**
     * Gives an array of capabilities that sholud bypass the Policy check
     *
     * @since  1.0
     * @author Curtis McHale <curtis@curtismchale.ca>
     *
     * @uses apply_filters() Allows changing of passed value without hacking the plugin
     *
     * @return array $caps
     */
    private static function _getBypassedCaps()
    {

        $caps = array(
            'activate_plugins'
        );

        return apply_filters('pw_bypass_caps', $caps);
    }

    /**
     * Checks if the logging in user has signed the agreement
     * Sends them to the agreement page if NOT agreed
     *
     * @param string $redirect_to required Redirect destination URL
     * @param string $requested_redirect_to required The requested redirect destination URL passed as a parameter
     * @param object $user optional WP_User object if successful WP_Error if not
     *
     * @uses is_wp_error() Returns true if WP_Error object
     * @uses self::hasUserAgreed() Returns true if the user has agreed to current policy version
     * @uses self::getPolicyAgreementPageUrl() Returns string of current policy URL
     *
     * @since  1.0
     * @author Curtis <curtis@curtismchale.ca>
     *
     * @return string $url The URL we are sending the user to
     */
    public static function checkAgreementOnLogin( $redirect_to, $requested_redirect_to, $user )
    {

        // bail early if not a valid WP_User object
        if (!($user instanceof \WP_User)) {
            return $redirect_to;
        }

        $agreed = self::hasUserAgreed($user);

        if (true === (bool)$agreed) {
            return $redirect_to;
        } else {
            return self::getPolicyAgreementPageUrl();
        }
    }

    /**
     * Redirects already-logged-in users to the policy agreement page if they
     * have not agreed to the current policy version.
     *
     * Runs on template_redirect so it catches users who were already logged in
     * when a new policy was published, not just users logging in fresh.
     *
     * A grace period (in seconds) can be set via the pw_policy_grace_period filter.
     * When non-zero, users are only redirected once the current policy is older than
     * the grace period — letting active sessions finish before enforcement kicks in.
     * Default is 0 (redirect immediately on the next page load).
     *
     * Example — allow a 24-hour grace period:
     *   add_filter( 'pw_policy_grace_period', fn() => DAY_IN_SECONDS );
     *
     * Skipped for: logged-out visitors, admin pages, AJAX requests, and the
     * policy page itself (to prevent redirect loops).
     *
     * @since  2026.04.29
     * @author Curtis <curtis@curtismchale.ca>
     *
     * @return void
     */
    public static function enforceAgreementForActiveSession()
    {
        if (! is_user_logged_in() || is_admin() || wp_doing_ajax()) {
            return;
        }

        $policy_page_id = get_option('pw_policy_page_id') ?: self::getCurrentAgreementId();
        if (is_page(absint($policy_page_id))) {
            return;
        }

        $user = wp_get_current_user();
        if (self::hasUserAgreed($user)) {
            return;
        }

        $grace_period = (int) apply_filters('pw_policy_grace_period', 0);
        if ($grace_period > 0) {
            $policy    = get_post(self::getCurrentAgreementId());
            $published = $policy ? strtotime($policy->post_date_gmt) : 0;
            if ($published && (time() - $published) < $grace_period) {
                return;
            }
        }

        wp_safe_redirect(self::getPolicyAgreementPageUrl());
        exit;
    }

    /**
     * Returns the URL of the currently active policy
     *
     * @uses self::getCurrentAgreementId() Returns int of the current policy post_id
     * @uses get_permalink() Returns string with the permalink given cpt_id
     *
     * @since  1.0
     * @author Curtis <curtis@curtismchale.ca>
     *
     * @return string URL of the policy page
     */
    public static function getPolicyAgreementPageUrl()
    {
        // custom value for policy page set by user
        $policy_page_id = get_option('pw_policy_page_id');

        if ($policy_page_id == false ) {
            $policy_page_id = self::getCurrentAgreementId();
        }

        return get_permalink($policy_page_id);
    }

    /**
     * Returns true if the user has agreed to the latest policy
     * Also returns true if the user is WP Admin
     *
     * @param object $user_object The full WP user object for the user you're checking
     *
     * @since  1.0
     * @author Curtis, <curtis@curtismchale.ca>
     *
     * @return bool $agreed True if the user has agreed
     */
    public static function hasUserAgreed($user_object)
    {

        $allowed_caps = self::_getBypassedCaps();

        // skip anyone with an allowed capability in the array
        foreach ($allowed_caps as $cap) {
            if (user_can($user_object, $cap)) {
                return true;
            }
        }

        $agreed = false;
        $agreement_id = self::getCurrentAgreementId();
        $user_agreement_id = get_user_meta(absint($user_object->ID), '_policy_agreed_to', true);

        if (absint($agreement_id) === absint($user_agreement_id)) {
            $agreed = true; // user has accepted
        } else {
            $agreed = false; // user has NOT accepted
        }

        return (bool) $agreed;

    } // _hasUserAgreed

    /**
     * Invalidates the cache key if the saved Policy is new
     *
     * @param int $post_id required The ID of the post we're saving
     * @param object $post_object required The post object
     * @param bool $update required True if we're updating a post
     *
     * @since  1.0
     * @author Curtis, <curtis@curtismchale.ca>
     *
     * @return null
     */
    public static function invalidatePolicyCacheKey( $post_id, $post_object, $update )
    {

        // skip if we're updating a post
        if (true === $update){
            return;
        }

        // this is not the post_type you're looking for
        if (get_post_type(absint($post_id)) !== 'pw_policies') {
            return;
        }


        $cached_id = get_transient(self::TRANSIENT_KEY);

        if (absint($cached_id) !== absint($post_id) ) {
            delete_transient(self::TRANSIENT_KEY);
        }

    } // _invalidatePolicyCacheKey

    /**
     * Returns current agreement post_id
     *
     * @since  1.0
     * @author Curtis, <curtis@curtismchale.ca>
     *
     * @return int $latest_post_id The ID of the most recent agreement
     */
    public static function getCurrentAgreementId()
    {

        $cached_id = false; // get_transient(self::TRANSIENT_KEY);

        if (false !== $cached_id ) {
            return absint($cached_id);
        }

        $latest_post = get_posts(
            array(
                'post_type'      => 'pw_policies',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'orderby'        => 'date',
                'order'          => 'DESC',
                'fields'         => 'ids',
                'no_found_rows'  => true,
                'cache_results'  => false,
            )
        );

        $latest_post_id = !empty($latest_post) ? $latest_post[0] : false;

        // Cache it for 1 week
        // cache is invalidated on save_post
        set_transient(self::TRANSIENT_KEY, absint($latest_post_id), 604800);

        return absint($latest_post_id);

    } // getCurrentAgreementId

}

PwGeneric::instance();
