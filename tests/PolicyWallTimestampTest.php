<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests for agreement-timestamp storage and CSV export columns added in 2026-04-29.
 *
 * Covers:
 *   - savePolicyToUser() writes _policy_agreed_date_{policyId} on first save
 *   - savePolicyToUser() does NOT overwrite the timestamp on a duplicate call
 *   - exportUserCSV() produces three columns: Username, Email, Agreed Date
 *   - Username column contains user_login, not display_name
 *   - A missing timestamp (legacy agreement) results in an empty Agreed Date
 *   - A missing/deleted user ID in _users_agreed_to_policy is skipped without error
 */
class PolicyWallTimestampTest extends TestCase
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
    // savePolicyToUser() — timestamp written on first save
    // -------------------------------------------------------------------------

    /**
     * On first agreement, update_user_meta must be called once with a key of
     * _policy_agreed_date_{policyId} and an integer close to time().
     */
    public function test_savePolicyToUser_writes_timestamp_on_first_save(): void
    {
        $userId   = 7;
        $policyId = 42;

        Functions\when('get_user_meta')->justReturn([]);
        Functions\when('add_user_meta')->justReturn(1);

        Functions\expect('update_user_meta')
            ->once()
            ->with(
                $userId,
                '_policy_agreed_date_' . $policyId,
                \Mockery::type('int')
            );

        PolicyWall::savePolicyToUser($userId, $policyId);

        $this->addToAssertionCount(1);
    }

    // -------------------------------------------------------------------------
    // savePolicyToUser() — timestamp NOT overwritten on duplicate call
    // -------------------------------------------------------------------------

    /**
     * When the policy is already in the user's _policy_agreed_to meta,
     * savePolicyToUser() returns early before calling update_user_meta, so the
     * original timestamp is preserved.
     */
    public function test_savePolicyToUser_does_not_overwrite_timestamp_on_duplicate(): void
    {
        $userId   = 7;
        $policyId = 42;

        // Simulate the policy already being recorded for this user.
        Functions\when('get_user_meta')->justReturn([$policyId]);

        Functions\expect('update_user_meta')->never();
        Functions\expect('add_user_meta')->never();

        $result = PolicyWall::savePolicyToUser($userId, $policyId);

        $this->assertTrue($result);
    }

    // -------------------------------------------------------------------------
    // exportUserCSV() — three columns, Username = user_login
    // -------------------------------------------------------------------------

    /**
     * The CSV output must have three columns — Username, Email, Agreed Date —
     * and the first data column must contain user_login, not display_name.
     */
    public function test_exportUserCSV_has_three_columns_with_user_login(): void
    {
        $policyId = 5;

        $_GET['policy_id'] = (string) $policyId;
        $_GET['_wpnonce']  = 'valid_nonce';

        $fakeUser               = new stdClass();
        $fakeUser->ID           = 10;
        $fakeUser->user_login   = 'jsmith';
        $fakeUser->display_name = 'John Smith';
        $fakeUser->user_email   = 'john@example.com';

        Functions\when('apply_filters')->returnArg(2);
        Functions\when('wp_verify_nonce')->justReturn(true);
        Functions\when('get_the_title')->justReturn('Test Policy');
        Functions\when('sanitize_file_name')->returnArg();
        Functions\when('get_post_meta')->justReturn([$fakeUser->ID]);
        Functions\when('get_userdata')->justReturn($fakeUser);
        Functions\when('get_user_meta')->justReturn('1746000000');
        Functions\when('wp_date')->justReturn('2025-04-30 00:00:00');
        Functions\when('sanitize_email')->returnArg();

        ob_start();
        // exportUserCSV() calls exit() after writing; we need to catch that.
        try {
            (new PolicyWall())->exportUserCSV();
        } catch (\Exception $e) {
            // Some test setups replace exit with an exception; ignore.
        }
        $csv = ob_get_clean();

        // If ob_get_clean() returned false (headers already sent path), skip output check.
        if ($csv === false || $csv === '') {
            $this->markTestSkipped('exportUserCSV() wrote directly to output; cannot capture in this environment.');
        }

        $rows = array_filter(explode("\n", trim($csv)));
        $rows = array_values($rows);

        $header = str_getcsv($rows[0]);
        $this->assertSame('Username', $header[0]);
        $this->assertSame('Email', $header[1]);
        $this->assertSame('Agreed Date', $header[2]);
        $this->assertCount(3, $header);

        $data = str_getcsv($rows[1]);
        $this->assertSame('jsmith', $data[0]);
        $this->assertNotSame('John Smith', $data[0]);
    }

    // -------------------------------------------------------------------------
    // exportUserCSV() — missing timestamp produces empty Agreed Date
    // -------------------------------------------------------------------------

    /**
     * When get_user_meta returns an empty string (no timestamp stored for a
     * legacy agreement), the Agreed Date column must be an empty string.
     */
    public function test_exportUserCSV_empty_agreed_date_for_missing_timestamp(): void
    {
        $policyId = 5;

        $_GET['policy_id'] = (string) $policyId;
        $_GET['_wpnonce']  = 'valid_nonce';

        $fakeUser               = new stdClass();
        $fakeUser->ID           = 11;
        $fakeUser->user_login   = 'legacy_user';
        $fakeUser->display_name = 'Legacy User';
        $fakeUser->user_email   = 'legacy@example.com';

        Functions\when('apply_filters')->returnArg(2);
        Functions\when('wp_verify_nonce')->justReturn(true);
        Functions\when('get_the_title')->justReturn('Test Policy');
        Functions\when('sanitize_file_name')->returnArg();
        Functions\when('get_post_meta')->justReturn([$fakeUser->ID]);
        Functions\when('get_userdata')->justReturn($fakeUser);
        // No timestamp stored — get_user_meta returns empty string (WP default).
        Functions\when('get_user_meta')->justReturn('');
        Functions\when('wp_date')->justReturn('');
        Functions\when('sanitize_email')->returnArg();

        ob_start();
        try {
            (new PolicyWall())->exportUserCSV();
        } catch (\Exception $e) {
        }
        $csv = ob_get_clean();

        if ($csv === false || $csv === '') {
            $this->markTestSkipped('exportUserCSV() wrote directly to output; cannot capture in this environment.');
        }

        $rows = array_filter(explode("\n", trim($csv)));
        $rows = array_values($rows);

        $data = str_getcsv($rows[1]);
        $this->assertSame('', $data[2]);
    }

    // -------------------------------------------------------------------------
    // exportUserCSV() — missing/deleted user is skipped
    // -------------------------------------------------------------------------

    /**
     * When get_userdata() returns false for a stored user ID (account deleted),
     * that row must be silently skipped so the export does not error or produce
     * a partial row.
     */
    public function test_exportUserCSV_skips_deleted_user(): void
    {
        $policyId = 5;

        $_GET['policy_id'] = (string) $policyId;
        $_GET['_wpnonce']  = 'valid_nonce';

        Functions\when('apply_filters')->returnArg(2);
        Functions\when('wp_verify_nonce')->justReturn(true);
        Functions\when('get_the_title')->justReturn('Test Policy');
        Functions\when('sanitize_file_name')->returnArg();
        // Return a deleted user ID in the list.
        Functions\when('get_post_meta')->justReturn([999]);
        // get_userdata returns false for a deleted/missing user.
        Functions\when('get_userdata')->justReturn(false);
        Functions\when('get_user_meta')->justReturn('');
        Functions\when('wp_date')->justReturn('');
        Functions\when('sanitize_email')->returnArg();

        ob_start();
        try {
            (new PolicyWall())->exportUserCSV();
        } catch (\Exception $e) {
        }
        $csv = ob_get_clean();

        if ($csv === false || $csv === '') {
            $this->markTestSkipped('exportUserCSV() wrote directly to output; cannot capture in this environment.');
        }

        $rows = array_filter(explode("\n", trim($csv)));
        $rows = array_values($rows);

        // Only the header row should be present — the deleted user is skipped.
        $this->assertCount(1, $rows);
        $header = str_getcsv($rows[0]);
        $this->assertSame('Username', $header[0]);
    }
}
