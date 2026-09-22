<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests covering what happens after a user agrees, and who can reach the
 * Agreed Users admin page.
 *
 * Both came out of the same report: users had no idea what to do after
 * clicking Agree, and non-administrators got "Sorry, you are not allowed to
 * access this page" from the Agreed Users link.
 */
class PolicyWallPostAgreementTest extends TestCase
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
    }

    /**
     * Routes apply_filters so named tags return the given values and every
     * other filter passes its value straight through.
     */
    private function filterReturns(array $values): void
    {
        Functions\when('apply_filters')
            ->alias(function (string $tag, mixed $value) use ($values) {
                return array_key_exists($tag, $values) ? $values[$tag] : $value;
            });
    }

    // -------------------------------------------------------------------------
    // Post-agreement redirect target
    // -------------------------------------------------------------------------

    /**
     * With nothing filtered, the user is pointed at the site home page.
     */
    public function test_redirect_defaults_to_home_url(): void
    {
        Functions\when('apply_filters')->returnArg(2);
        Functions\when('home_url')->justReturn('https://example.com/');
        Functions\when('esc_url_raw')->returnArg();

        $this->assertSame('https://example.com/', PolicyWall::getPostAgreementRedirect());
    }

    /**
     * A site can send users somewhere other than the home page.
     */
    public function test_redirect_can_be_filtered(): void
    {
        $this->filterReturns(['pw_post_agreement_redirect' => 'https://example.com/dashboard/']);
        Functions\when('home_url')->justReturn('https://example.com/');
        Functions\when('esc_url_raw')->returnArg();

        $this->assertSame('https://example.com/dashboard/', PolicyWall::getPostAgreementRedirect());
    }

    /**
     * The redirect target is escaped before it is handed to the browser.
     */
    public function test_redirect_is_escaped(): void
    {
        $this->filterReturns(['pw_post_agreement_redirect' => 'javascript:alert(1)']);
        Functions\when('home_url')->justReturn('https://example.com/');
        Functions\expect('esc_url_raw')->once()->with('javascript:alert(1)')->andReturn('');

        $this->assertSame('', PolicyWall::getPostAgreementRedirect());
    }

    // -------------------------------------------------------------------------
    // Post-agreement link label
    // -------------------------------------------------------------------------

    /**
     * The default label tells the user they can move on.
     */
    public function test_redirect_label_has_a_default(): void
    {
        Functions\when('apply_filters')->returnArg(2);

        $this->assertSame(
            'You may now proceed to the home page.',
            PolicyWall::getPostAgreementRedirectLabel()
        );
    }

    /**
     * The label is escaped, since it ends up as link text on the page.
     */
    public function test_redirect_label_is_escaped(): void
    {
        $this->filterReturns([
            'pw_post_agreement_redirect_label' => '<script>alert(1)</script>',
        ]);

        $this->assertSame(
            '&lt;script&gt;alert(1)&lt;/script&gt;',
            PolicyWall::getPostAgreementRedirectLabel()
        );
    }

    // -------------------------------------------------------------------------
    // Post-agreement redirect delay
    // -------------------------------------------------------------------------

    /**
     * The default gives the user a few seconds to read the confirmation before
     * the browser moves them along.
     */
    public function test_redirect_delay_defaults_to_five_seconds(): void
    {
        Functions\when('apply_filters')->returnArg(2);

        $this->assertSame(5000, PolicyWall::getPostAgreementRedirectDelay());
    }

    /**
     * A delay of 0 is allowed and means "show the link, do not redirect".
     */
    public function test_redirect_delay_of_zero_is_preserved(): void
    {
        $this->filterReturns(['pw_post_agreement_redirect_delay' => 0]);

        $this->assertSame(0, PolicyWall::getPostAgreementRedirectDelay());
    }

    /**
     * A negative delay would mean an instant redirect with no confirmation, so
     * it is clamped to 0 instead.
     */
    public function test_negative_redirect_delay_is_clamped_to_zero(): void
    {
        $this->filterReturns(['pw_post_agreement_redirect_delay' => -1000]);

        $this->assertSame(0, PolicyWall::getPostAgreementRedirectDelay());
    }

    // -------------------------------------------------------------------------
    // savePolicyAgreement response payload
    // -------------------------------------------------------------------------

    /**
     * A successful agreement must hand the JS everything it needs to offer the
     * proceed link: the destination, its label, and the delay.
     */
    public function test_savePolicyAgreement_returns_redirect_details_on_success(): void
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
        Functions\when('update_user_meta')->justReturn(true);
        Functions\when('wp_kses_post')->returnArg();
        Functions\when('apply_filters')->returnArg(2);
        Functions\when('home_url')->justReturn('https://example.com/');
        Functions\when('esc_url_raw')->returnArg();

        $captured = null;
        Functions\when('wp_send_json_success')
            ->alias(function ($data) use (&$captured) {
                $captured = $data;
            });

        $pw = new PolicyWall();
        $pw->savePolicyAgreement();

        $this->assertTrue($captured['success']);
        $this->assertSame('https://example.com/', $captured['redirect']);
        $this->assertSame('You may now proceed to the home page.', $captured['redirectLabel']);
        $this->assertSame(5000, $captured['redirectDelay']);
    }

    /**
     * When the save fails there is nothing to proceed to, so no redirect
     * details are sent and the JS leaves the user where they are.
     */
    public function test_savePolicyAgreement_omits_redirect_details_on_failure(): void
    {
        $_POST['security'] = 'fake_nonce';
        $_POST['policyId'] = '5';

        Functions\when('check_ajax_referer')->justReturn(true);
        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('get_current_user_id')->justReturn(2);
        Functions\when('get_post_type')->justReturn('pw_policies');
        Functions\when('get_post_meta')->justReturn([]);
        Functions\when('update_post_meta')->justReturn(false); // save fails
        Functions\when('get_user_meta')->justReturn([]);
        Functions\when('update_user_meta')->justReturn(false);
        Functions\when('wp_kses_post')->returnArg();
        Functions\when('apply_filters')->returnArg(2);
        Functions\when('home_url')->justReturn('https://example.com/');
        Functions\when('esc_url_raw')->returnArg();

        $captured = null;
        Functions\when('wp_send_json_success')
            ->alias(function ($data) use (&$captured) {
                $captured = $data;
            });

        $pw = new PolicyWall();
        $pw->savePolicyAgreement();

        $this->assertFalse($captured['success']);
        $this->assertSame('', $captured['redirect']);
        $this->assertSame('', $captured['redirectLabel']);
        $this->assertSame(0, $captured['redirectDelay']);
    }

    // -------------------------------------------------------------------------
    // Agreed Users row action — capability gate
    // -------------------------------------------------------------------------

    /**
     * A user who can reach the Agreed Users page still gets the row link.
     */
    public function test_agreedUsersAction_adds_link_for_capable_user(): void
    {
        $post            = new stdClass();
        $post->ID        = 8789;
        $post->post_type = 'pw_policies';

        Functions\when('apply_filters')->returnArg(2);
        Functions\when('current_user_can')->justReturn(true);
        Functions\when('add_query_arg')->justReturn('https://example.com/wp-admin/edit.php');
        Functions\when('admin_url')->justReturn('https://example.com/wp-admin/edit.php');
        Functions\when('esc_url')->returnArg();
        Functions\when('esc_html__')->returnArg();

        $pw      = new PolicyWall();
        $actions = $pw->agreedUsersAction([], $post);

        $this->assertArrayHasKey('view_extra_data', $actions);
    }

    /**
     * A user without the capability must not be shown a link that will only
     * hand them "Sorry, you are not allowed to access this page".
     */
    public function test_agreedUsersAction_hides_link_without_capability(): void
    {
        $post            = new stdClass();
        $post->ID        = 8789;
        $post->post_type = 'pw_policies';

        Functions\when('apply_filters')->returnArg(2);
        Functions\when('current_user_can')->justReturn(false);

        $pw      = new PolicyWall();
        $actions = $pw->agreedUsersAction([], $post);

        $this->assertArrayNotHasKey('view_extra_data', $actions);
    }

    /**
     * The capability the row action checks must be the same one the page
     * itself enforces, or the link goes dead again the moment a site filters
     * pw_view_agreed_cap.
     */
    public function test_agreedUsersAction_checks_the_pw_view_agreed_cap_filter(): void
    {
        $post            = new stdClass();
        $post->ID        = 8789;
        $post->post_type = 'pw_policies';

        $this->filterReturns(['pw_view_agreed_cap' => 'proud_admin_cap']);

        Functions\expect('current_user_can')
            ->once()
            ->with('proud_admin_cap')
            ->andReturn(false);

        $pw      = new PolicyWall();
        $actions = $pw->agreedUsersAction([], $post);

        $this->assertArrayNotHasKey('view_extra_data', $actions);
    }

    /**
     * Other post types are left alone before any capability check runs.
     */
    public function test_agreedUsersAction_ignores_other_post_types(): void
    {
        $post            = new stdClass();
        $post->ID        = 12;
        $post->post_type = 'page';

        Functions\expect('current_user_can')->never();

        $pw      = new PolicyWall();
        $actions = $pw->agreedUsersAction(['edit' => 'Edit'], $post);

        $this->assertSame(['edit' => 'Edit'], $actions);
    }
}
