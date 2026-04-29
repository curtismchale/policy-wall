<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests covering the security fixes applied in the second security pass.
 *
 * Each test corresponds to a numbered fix in the security report.
 */
class PolicyWallSecurityFixesTest extends TestCase
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
        $_GET  = [];
        $_POST = [];
    }

    // -------------------------------------------------------------------------
    // Fix 4 — savePolicyAgreement: logged-out user (userId=0 bypass)
    // -------------------------------------------------------------------------

    /**
     * When is_user_logged_in() returns false the handler must return a JSON
     * error immediately, even though the nonce passes and get_current_user_id()
     * would return 0.
     */
    public function test_savePolicyAgreement_rejects_unauthenticated_request(): void
    {
        $_POST['security'] = 'fake_nonce';
        $_POST['policyId'] = '5';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('is_user_logged_in')->justReturn(false);
        Functions\when('get_current_user_id')->justReturn(0);
        Functions\when('wp_kses_post')->returnArg();

        Functions\expect('wp_send_json_error')->once();
        Functions\expect('wp_send_json_success')->never();

        (new PolicyWall())->savePolicyAgreement();

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Fix 4 — savePolicyAgreement: invalid policyId post type
    // -------------------------------------------------------------------------

    /**
     * When get_post_type() does not return 'pw_policies' for the supplied
     * policyId the handler must return a JSON error and not write any meta.
     */
    public function test_savePolicyAgreement_rejects_invalid_policy_post_type(): void
    {
        $_POST['security'] = 'fake_nonce';
        $_POST['policyId'] = '99';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('get_current_user_id')->justReturn(3);
        Functions\when('get_post_type')->justReturn('page');
        Functions\when('wp_kses_post')->returnArg();

        Functions\expect('wp_send_json_error')->once();
        Functions\expect('wp_send_json_success')->never();
        Functions\expect('update_post_meta')->never();
        Functions\expect('add_user_meta')->never();

        (new PolicyWall())->savePolicyAgreement();

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Fix 5 — savePolicyPageIdNumber: non-existent post
    // -------------------------------------------------------------------------

    /**
     * When the supplied policyId is non-zero and get_post() returns null
     * (post does not exist), the handler must return a JSON error and must NOT
     * call update_option().
     */
    public function test_savePolicyPageIdNumber_rejects_nonexistent_post(): void
    {
        $_POST['security'] = 'fake_nonce';
        $_POST['policyId'] = '999';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('get_post')->justReturn(null);
        Functions\when('wp_kses_post')->returnArg();

        Functions\expect('wp_send_json_error')->once();
        Functions\expect('wp_send_json_success')->never();
        Functions\expect('update_option')->never();

        (new PolicyWall())->savePolicyPageIdNumber();

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // Fix 2 — embedContent: draft post returns empty string
    // -------------------------------------------------------------------------

    /**
     * embedContent() must return an empty string when the referenced post has
     * post_status 'draft', so draft content is never exposed to site visitors.
     */
    public function test_embedContent_returns_empty_for_draft_post(): void
    {
        $draftPost              = new stdClass();
        $draftPost->post_status = 'draft';
        $draftPost->post_password = '';
        $draftPost->post_content  = 'Secret draft content';

        Functions\when('get_post')->justReturn($draftPost);

        $result = PolicyWallShortcodes::embedContent(['contentid' => '42'], '');

        $this->assertSame('', $result);
    }

    // -------------------------------------------------------------------------
    // Fix 2 — embedContent: password-protected post returns empty string
    // -------------------------------------------------------------------------

    /**
     * embedContent() must return an empty string when the referenced post has
     * a non-empty post_password, so password-protected content is not leaked.
     */
    public function test_embedContent_returns_empty_for_password_protected_post(): void
    {
        $protectedPost              = new stdClass();
        $protectedPost->post_status   = 'publish';
        $protectedPost->post_password = 'secret123';
        $protectedPost->post_content  = 'Protected content';

        Functions\when('get_post')->justReturn($protectedPost);

        $result = PolicyWallShortcodes::embedContent(['contentid' => '42'], '');

        $this->assertSame('', $result);
    }

    // -------------------------------------------------------------------------
    // Fix 2 — embedContent: null/non-existent post returns empty string
    // -------------------------------------------------------------------------

    /**
     * embedContent() must return an empty string when get_post() returns null
     * (post does not exist), preventing a fatal error on ->post_content access.
     */
    public function test_embedContent_returns_empty_for_nonexistent_post(): void
    {
        Functions\when('get_post')->justReturn(null);

        $result = PolicyWallShortcodes::embedContent(['contentid' => '0'], '');

        $this->assertSame('', $result);
    }

    // -------------------------------------------------------------------------
    // Fix 2 — embedContentAccordion: draft post returns empty string
    // -------------------------------------------------------------------------

    /**
     * embedContentAccordion() must return an empty string when the referenced
     * post has post_status 'draft'.
     */
    public function test_embedContentAccordion_returns_empty_for_draft_post(): void
    {
        $draftPost              = new stdClass();
        $draftPost->post_status   = 'draft';
        $draftPost->post_password = '';
        $draftPost->post_content  = 'Secret draft content';

        Functions\when('get_post')->justReturn($draftPost);

        $result = PolicyWallShortcodes::embedContentAccordion(
            ['contentid' => '42', 'title' => false],
            ''
        );

        $this->assertSame('', $result);
    }

    // -------------------------------------------------------------------------
    // Fix 2 — showLatestPolicy: non-existent policy post returns empty string
    // -------------------------------------------------------------------------

    /**
     * showLatestPolicy() must return an empty string when getCurrentAgreementId()
     * returns an ID for which get_post() returns null, preventing a fatal error.
     *
     * Note: this test stubs PwGeneric::getCurrentAgreementId() indirectly via
     * get_posts() returning an empty array (so getCurrentAgreementId() returns 0)
     * and get_post(0) returning null.
     */
    public function test_showLatestPolicy_returns_empty_when_no_policy_post_exists(): void
    {
        Functions\when('get_posts')->justReturn([]);
        Functions\when('set_transient')->justReturn(true);
        Functions\when('get_transient')->justReturn(false);
        Functions\when('get_post')->justReturn(null);

        $result = PolicyWallShortcodes::showLatestPolicy([], '');

        $this->assertSame('', $result);
    }
}
