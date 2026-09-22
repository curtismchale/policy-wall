<?php

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;

/**
 * Tests covering the content allowlist that lets users read the policies
 * before they agree to them.
 *
 * Without the allowlist, enforceAgreementForActiveSession() bounced every link
 * on the policy page back to the policy page — the documents in the sidebar
 * looked like dead links to anyone who had not agreed yet.
 */
class PolicyWallAllowlistTest extends TestCase
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
     * Routes apply_filters so the allowlist filters return $values and every
     * other filter passes its value straight through.
     *
     * @param array $values Filter tag => value to return
     */
    private function filterReturns(array $values): void
    {
        Functions\when('apply_filters')
            ->alias(function (string $tag, mixed $value) use ($values) {
                return array_key_exists($tag, $values) ? $values[$tag] : $value;
            });
    }

    // -------------------------------------------------------------------------
    // Default state — nothing allowlisted
    // -------------------------------------------------------------------------

    /**
     * With no filters hooked, nothing is exempt and the wall behaves exactly
     * as it did before the allowlist existed.
     */
    public function test_nothing_is_exempt_by_default(): void
    {
        Functions\when('apply_filters')->returnArg(2);

        $this->assertFalse(PwGeneric::isExemptFromAgreement());
    }

    /**
     * An empty allowlist must short-circuit before touching the query, so the
     * common case costs nothing.
     */
    public function test_empty_allowlist_never_queries_the_request(): void
    {
        Functions\when('apply_filters')->returnArg(2);
        Functions\expect('get_queried_object')->never();
        Functions\expect('get_queried_object_id')->never();

        $this->assertFalse(PwGeneric::isExemptFromAgreement());
    }

    // -------------------------------------------------------------------------
    // pw_allowed_post_ids
    // -------------------------------------------------------------------------

    /**
     * A page whose ID is in pw_allowed_post_ids must be exempt.
     */
    public function test_allowlisted_post_id_is_exempt(): void
    {
        $this->filterReturns(['pw_allowed_post_ids' => [4935]]);
        Functions\when('get_queried_object')->justReturn(null);
        Functions\when('get_queried_object_id')->justReturn(4935);

        $this->assertTrue(PwGeneric::isExemptFromAgreement());
    }

    /**
     * A page that is not on the allowlist must stay behind the wall.
     */
    public function test_non_allowlisted_post_id_is_not_exempt(): void
    {
        $this->filterReturns(['pw_allowed_post_ids' => [4935]]);
        Functions\when('get_queried_object')->justReturn(null);
        Functions\when('get_queried_object_id')->justReturn(7777);
        Functions\when('has_term')->justReturn(false);

        $this->assertFalse(PwGeneric::isExemptFromAgreement());
    }

    /**
     * IDs arriving as strings from a filter must still match — absint() is
     * applied to the allowlist before comparison.
     */
    public function test_allowlisted_post_id_matches_when_filter_returns_string(): void
    {
        $this->filterReturns(['pw_allowed_post_ids' => ['4935']]);
        Functions\when('get_queried_object')->justReturn(null);
        Functions\when('get_queried_object_id')->justReturn(4935);

        $this->assertTrue(PwGeneric::isExemptFromAgreement());
    }

    /**
     * A request with no queried object (a 404, say) must not be exempt.
     */
    public function test_request_without_queried_object_is_not_exempt(): void
    {
        $this->filterReturns(['pw_allowed_post_ids' => [4935]]);
        Functions\when('get_queried_object')->justReturn(null);
        Functions\when('get_queried_object_id')->justReturn(0);

        $this->assertFalse(PwGeneric::isExemptFromAgreement());
    }

    // -------------------------------------------------------------------------
    // pw_allowed_terms — singular posts
    // -------------------------------------------------------------------------

    /**
     * A document carrying an allowlisted term must be exempt. This is the
     * sidebar document list case from the original bug report.
     */
    public function test_post_in_allowlisted_term_is_exempt(): void
    {
        $this->filterReturns(['pw_allowed_terms' => ['document_taxonomy' => [141]]]);
        Functions\when('get_queried_object')->justReturn(null);
        Functions\when('get_queried_object_id')->justReturn(8000);

        Functions\expect('has_term')
            ->once()
            ->with([141], 'document_taxonomy', 8000)
            ->andReturn(true);

        $this->assertTrue(PwGeneric::isExemptFromAgreement());
    }

    /**
     * A document in some other category must stay behind the wall.
     */
    public function test_post_outside_allowlisted_term_is_not_exempt(): void
    {
        $this->filterReturns(['pw_allowed_terms' => ['document_taxonomy' => [141]]]);
        Functions\when('get_queried_object')->justReturn(null);
        Functions\when('get_queried_object_id')->justReturn(8000);
        Functions\when('has_term')->justReturn(false);

        $this->assertFalse(PwGeneric::isExemptFromAgreement());
    }

    /**
     * An empty term list for a taxonomy must be skipped rather than passed to
     * has_term(), which treats an empty term list as "has any term".
     */
    public function test_empty_term_list_is_skipped(): void
    {
        $this->filterReturns(['pw_allowed_terms' => ['document_taxonomy' => []]]);
        Functions\when('get_queried_object')->justReturn(null);
        Functions\when('get_queried_object_id')->justReturn(8000);
        Functions\expect('has_term')->never();

        $this->assertFalse(PwGeneric::isExemptFromAgreement());
    }

    // -------------------------------------------------------------------------
    // pw_allowed_terms — term archives
    // -------------------------------------------------------------------------

    /**
     * The archive for an allowlisted term must be exempt, matched by term ID.
     */
    public function test_allowlisted_term_archive_is_exempt_by_id(): void
    {
        $this->filterReturns(['pw_allowed_terms' => ['document_taxonomy' => [141]]]);
        Functions\when('get_queried_object')
            ->justReturn(new WP_Term(141, 'policies-and-procedures', 'document_taxonomy'));

        $this->assertTrue(PwGeneric::isExemptFromAgreement());
    }

    /**
     * Term archives must also match on slug, so pw_allowed_terms can be
     * written with slugs instead of environment-specific IDs.
     */
    public function test_allowlisted_term_archive_is_exempt_by_slug(): void
    {
        $this->filterReturns([
            'pw_allowed_terms' => ['document_taxonomy' => ['policies-and-procedures']],
        ]);
        Functions\when('get_queried_object')
            ->justReturn(new WP_Term(141, 'policies-and-procedures', 'document_taxonomy'));

        $this->assertTrue(PwGeneric::isExemptFromAgreement());
    }

    /**
     * A term archive for a different term must stay behind the wall.
     */
    public function test_non_allowlisted_term_archive_is_not_exempt(): void
    {
        $this->filterReturns(['pw_allowed_terms' => ['document_taxonomy' => [141]]]);
        Functions\when('get_queried_object')
            ->justReturn(new WP_Term(999, 'forms', 'document_taxonomy'));

        $this->assertFalse(PwGeneric::isExemptFromAgreement());
    }

    /**
     * A matching term ID in the wrong taxonomy must not be exempt — the
     * allowlist is keyed by taxonomy for a reason.
     */
    public function test_term_archive_in_other_taxonomy_is_not_exempt(): void
    {
        $this->filterReturns(['pw_allowed_terms' => ['document_taxonomy' => [141]]]);
        Functions\when('get_queried_object')
            ->justReturn(new WP_Term(141, 'policies-and-procedures', 'category'));

        $this->assertFalse(PwGeneric::isExemptFromAgreement());
    }

    // -------------------------------------------------------------------------
    // enforceAgreementForActiveSession integration
    // -------------------------------------------------------------------------

    /**
     * A non-agreed user on allowlisted content must not be redirected. This is
     * the end-to-end version of the reported bug.
     */
    public function test_enforceAgreement_does_not_redirect_on_allowlisted_content(): void
    {
        $user     = new stdClass();
        $user->ID = 5;

        Functions\when('is_user_logged_in')->justReturn(true);
        Functions\when('is_admin')->justReturn(false);
        Functions\when('wp_doing_ajax')->justReturn(false);
        Functions\when('get_option')->justReturn(5011);
        Functions\when('is_page')->justReturn(false);

        $this->filterReturns(['pw_allowed_post_ids' => [4935]]);
        Functions\when('get_queried_object')->justReturn(null);
        Functions\when('get_queried_object_id')->justReturn(4935);

        // hasUserAgreed() would return false for this user, so a redirect is
        // only avoided by the allowlist check firing first.
        Functions\when('wp_get_current_user')->justReturn($user);
        Functions\when('user_can')->justReturn(false);
        Functions\when('get_user_meta')->justReturn(5);
        Functions\when('get_posts')->justReturn([10]);
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);

        Functions\expect('wp_safe_redirect')->never();

        PwGeneric::enforceAgreementForActiveSession();
        $this->addToAssertionCount(1);
    }
}
