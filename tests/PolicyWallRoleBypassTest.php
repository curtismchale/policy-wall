<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests covering the role-based policy-wall bypass added for external users.
 */
class PolicyWallRoleBypassTest extends TestCase
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

    // -------------------------------------------------------------------------
    // Role bypass — external_user
    // -------------------------------------------------------------------------

    /**
     * A user with the external_user role must bypass the policy check even when
     * their _policy_agreed_to meta does not match the current policy ID.
     */
    public function test_external_user_role_bypasses_policy_check(): void
    {
        $user        = (object) ['ID' => 99, 'roles' => ['external_user']];

        Functions\when('apply_filters')->returnArg(2);
        Functions\when('user_can')->justReturn(false);

        $result = PwGeneric::hasUserAgreed($user);

        $this->assertTrue($result, 'external_user role should bypass the policy wall');
    }

    // -------------------------------------------------------------------------
    // Regression — subscriber is NOT bypassed
    // -------------------------------------------------------------------------

    /**
     * A user with only the subscriber role must not be bypassed and must still
     * be gated by the policy wall when they have not agreed.
     */
    public function test_subscriber_without_role_is_not_bypassed(): void
    {
        $user        = (object) ['ID' => 42, 'roles' => ['subscriber']];

        Functions\when('apply_filters')->returnArg(2);
        Functions\when('user_can')->justReturn(false);
        Functions\when('get_user_meta')->justReturn(0);
        Functions\when('get_posts')->justReturn([10]);
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);

        $result = PwGeneric::hasUserAgreed($user);

        $this->assertFalse($result, 'subscriber role should not bypass the policy wall');
    }

    // -------------------------------------------------------------------------
    // pw_bypass_roles filter — custom role added via filter
    // -------------------------------------------------------------------------

    /**
     * When a site hooks pw_bypass_roles to append a custom role, a user holding
     * that role must bypass the policy check.
     */
    public function test_pw_bypass_roles_filter_can_add_role(): void
    {
        $user = (object) ['ID' => 77, 'roles' => ['vendor']];

        Functions\when('apply_filters')
            ->alias(function (string $tag, mixed $value) {
                if ($tag === 'pw_bypass_roles') {
                    return ['external_user', 'vendor'];
                }
                return $value;
            });
        Functions\when('user_can')->justReturn(false);

        $result = PwGeneric::hasUserAgreed($user);

        $this->assertTrue($result, 'a role added via pw_bypass_roles filter should bypass the policy wall');
    }

    // -------------------------------------------------------------------------
    // Regression — existing capability bypass is unchanged
    // -------------------------------------------------------------------------

    /**
     * A user with the activate_plugins capability must still bypass the policy
     * check — confirms the existing capability bypass was not disturbed.
     */
    public function test_existing_capability_bypass_still_works(): void
    {
        $user = (object) ['ID' => 1, 'roles' => ['administrator']];

        Functions\when('apply_filters')->returnArg(2);
        Functions\when('user_can')
            ->alias(fn (mixed $user, string $cap): bool => (bool) $user && $cap === 'activate_plugins');

        $result = PwGeneric::hasUserAgreed($user);

        $this->assertTrue($result, 'activate_plugins capability bypass should still work');
    }
}
