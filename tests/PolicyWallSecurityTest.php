<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Security regression tests for the three vulnerabilities found in issue #2797.
 *
 * Each finding has a rejection test (documents the fix) and a happy-path test
 * (documents that legitimate requests still succeed).
 */
class PolicyWallSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
        $_POST = [];
    }

    // -------------------------------------------------------------------------
    // Finding 1 — High: unauthenticated meta write via savePolicyAgreement
    // The original IDOR used $_POST['userId']; that parameter is now ignored.
    // The fix requires is_user_logged_in() and a valid pw_policies post type.
    // -------------------------------------------------------------------------

    /**
     * A logged-out request (is_user_logged_in returns false) must be rejected
     * even if the nonce passes, so userId=0 cannot be used to bypass the check.
     */
    public function test_savePolicyAgreement_rejects_logged_out_user(): void
    {
        $_POST['security'] = 'fake_nonce';
        $_POST['policyId'] = '5';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('get_current_user_id')->justReturn(0);
        Functions\when('wp_kses_post')->returnArg();

        Functions\expect('wp_send_json_error')->once();
        Functions\expect('wp_send_json_success')->never();

        $pw = new PolicyWall();
        $pw->savePolicyAgreement();

        $this->addToAssertionCount(1);
    }

    /**
     * A logged-in user with a valid pw_policies post must succeed normally.
     */
    public function test_savePolicyAgreement_allows_logged_in_user_with_valid_policy(): void
    {
        $_POST['security'] = 'fake_nonce';
        $_POST['policyId'] = '5';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('get_current_user_id')->justReturn(2);
        Functions\when('get_post_type')->justReturn('pw_policies');
        Functions\when('get_post_meta')->justReturn([]);
        Functions\when('update_post_meta')->justReturn(true);
        Functions\when('get_user_meta')->justReturn([]);
        Functions\when('add_user_meta')->justReturn(1);
        Functions\when('update_user_meta')->justReturn(true);
        Functions\when('wp_kses_post')->returnArg();

        Functions\expect('wp_send_json_success')->once();

        $pw = new PolicyWall();
        $pw->savePolicyAgreement();

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Finding 2 — Medium: missing capability check in savePolicyPageIdNumber
    // -------------------------------------------------------------------------

    /**
     * A user without manage_options must receive a JSON error and must NOT have
     * the policy page option updated.
     */
    public function test_savePolicyPageIdNumber_rejects_user_without_manage_options(): void
    {
        $_POST['security'] = 'fake_nonce';
        $_POST['policyId'] = '10';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(false);
        Functions\when('update_option')->justReturn(true);
        Functions\when('wp_kses_post')->returnArg();

        Functions\expect('wp_send_json_error')->once();
        Functions\expect('wp_send_json_success')->never();

        $pw = new PolicyWall();
        $pw->savePolicyPageIdNumber();

        $this->addToAssertionCount(1);
    }

    /**
     * A user with manage_options must be allowed to save the policy page ID
     * when the supplied post ID is a published post.
     */
    public function test_savePolicyPageIdNumber_allows_user_with_manage_options(): void
    {
        $_POST['security'] = 'fake_nonce';
        $_POST['policyId'] = '10';

        $fakePost              = new stdClass();
        $fakePost->post_status = 'publish';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')->justReturn($fakePost);
        Functions\when('update_option')->justReturn(true);
        Functions\when('wp_kses_post')->returnArg();

        Functions\expect('wp_send_json_success')->once();

        $pw = new PolicyWall();
        $pw->savePolicyPageIdNumber();

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Finding 3 — Medium: stored XSS in admin policy dropdown
    // -------------------------------------------------------------------------

    /**
     * A policy title containing a <script> tag must be HTML-escaped in the
     * <option> output so it cannot execute in the browser.
     */
    public function test_getPolicySelectOptions_escapes_script_tag_in_title(): void
    {
        Functions\when('get_posts')->justReturn([1]);
        Functions\when('get_the_title')->justReturn('<script>alert(document.cookie)</script>');
        Functions\when('selected')->justReturn('');

        $pw   = new PolicyWall();
        $html = $pw->getPolicySelectOptions();

        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('&lt;script&gt;', $html);
    }

    /**
     * A policy title containing an attribute-breaking quote must be escaped so
     * it cannot inject event handlers.
     */
    public function test_getPolicySelectOptions_escapes_quotes_in_title(): void
    {
        Functions\when('get_posts')->justReturn([1]);
        Functions\when('get_the_title')->justReturn('" onmouseover="alert(1)');
        Functions\when('selected')->justReturn('');

        $pw   = new PolicyWall();
        $html = $pw->getPolicySelectOptions();

        $this->assertStringNotContainsString('" onmouseover=', $html);
        $this->assertStringContainsString('&quot;', $html);
    }
}
