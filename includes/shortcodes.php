<?php
/**
 * Provides the displayable shortcodes for Policies
 *
 * @package PolicyWall\Shortcodes
 * @author  Curtis McHale, <curtis@curtismchale.ca>
 * @license https://opensource.org/licenses/gpl-license.php GNU Public License
 * @see     https://sfndesign.ca
 */
class PolicyWallShortcodes
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
            self::$_instance = new PolicyWallShortcodes();
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

        add_shortcode('pw_show_latest_policy', [__CLASS__, 'showLatestPolicy']);
        add_shortcode('pw_embed_content', [__CLASS__, 'embedContent']);
        add_shortcode('pw_embed_content_accordion', [__CLASS__, 'embedContentAccordion']);
        // @todo show revisions of policies

    } // init

    /**
     * Embeds content in an accordion so it can be hidden or shown
     * by the user if they're interested in reading the content
     *
     * @param array  $atts    optional Define your custom settings if needed
     * @param string $content optional We don't use this actually
     *
     * @author Curtis McHale
     * @since  2025.09.16
     *
     * @uses absint() Makes sure we do not have negative numbers
     * @uses esc_attr() Escapes attributes
     * @uses apply_filters() Brings along any filters WP is already using
     *
     * @return string $html The text we want for the policy accordion from the other page
     */
    public static function embedContentAccordion( $atts, $content )
    {

        $a = shortcode_atts(
            array(
                'contentid' => 'null',
                'title' => false,
            ), $atts
        );

        $post = get_post(absint($a['contentid']));

        if (! $post || $post->post_status !== 'publish' || ! empty($post->post_password)) {
            return '';
        }

        $html = '';

        $html .= '<div class="pw-embedded-content pw-embedded-content-accordion">';
        if (false === (bool) $a['title']) {
            $html .= '<h4 class="pw-accordion-title">'. esc_html(get_the_title(absint($a['contentid']))) .'</h4>';
        } else {
            $html .= '<h4 class="pw-accordion-title">'. esc_attr($a['title']) .'</h4>';
        }

            $html .= '<div class="pw-accordion-content">'. apply_filters('the_content', $post->post_content) .'</div>';
        $html .= '</div><!-- /.pw-embedded-content -->';

        return $html;
    }

    /**
     * Does a straight embed of content by ID
     *
     * @uses array  $atts    required The passed attributes
     * @uses string $content optional Any content surrounded by the shortcode
     *
     * @since  1.0
     * @author Curtis McHale <curtis@curtismchale.ca>
     *
     * @return $html string The content of the shortcode
     */
    public static function embedContent( $atts, $content )
    {

        $a = shortcode_atts(
            array(
                'contentid' => 'null',
            ), $atts
        );

        $post = get_post(absint($a['contentid']));

        if (! $post || $post->post_status !== 'publish' || ! empty($post->post_password)) {
            return '';
        }

        $html = '';

        $html .= '<div class="pw-embedded-content">';
            $html .= '<h4>'. esc_html(get_the_title(absint($a['contentid']))) .'</h4>';
            $html .= apply_filters('the_content', $post->post_content);
        $html .= '</div><!-- /.pw-embedded-content -->';

        return $html;

    } // embedContent

    /**
     * Shows the most recently published policy
     *
     * @param array  $atts    optional any settings (and there are none right now)
     * @param string $content optional Content between shortcodes but there isn't any
     *
     * @since  1.0
     * @author Curtis McHale <curtis@curtismchale.ca>
     *
     * @return string $html The HTML from the shortcode
     */
    public static function showLatestPolicy( $atts, $content )
    {

        $a = shortcode_atts(
            array(
                'size'                  => 'small',
                'number'                => '1',
                'content_title'         => '',
                'content_subtitle'      => '',
                'class'                 => '',
            ), $atts
        );

        $latestPolicyId = PwGeneric::getCurrentAgreementId();
        $postObject = get_post(absint($latestPolicyId));

        if (! $postObject) {
            return '';
        }

        $postContent = $postObject->post_content;

        $html = '';

        $html .= '<p>Published on: ' . get_the_time('l F j g:i a', $latestPolicyId) .'</p>';
        $html .= '<p>Modified on: ' . get_the_modified_date('l F j g:i a', $latestPolicyId) .'</p>';
        $html .= $postContent;

        $html .= '<div class="pw-button-wrapper">';
        $html .= '<p>';
            $html .= '<a href="#" class="btn btn-sm btn-primary text-white" id="pw_policy_save" data-userid="'. get_current_user_id().'" data-policyid="'. absint($latestPolicyId).'">Agree</a>';
            $html .= '<span class="pw-spinner hidden"><img src="'. admin_url('images/spinner.gif') .'"/></span>';
        $html .= '</p>';
        $html .= '<p id="pw_user_feedback"></p>';
        $html .= '</div><!-- /.button-wrapper -->';

        return apply_filters('the_content', $html);

    }

}

PolicyWallShortcodes::instance();
