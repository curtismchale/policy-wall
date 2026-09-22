## 2026.09.22.1023

Addresses [saintra#57](https://github.com/proudcity/saintra/issues/57).

### Content allowlist — read the policies before agreeing to them
- Added `PwGeneric::isExemptFromAgreement()`, checked inside `enforceAgreementForActiveSession()`. Previously the wall redirected every front-end request back to the policy page, so the document links listed alongside the policy looked broken to anyone who had not agreed yet.
- New `pw_allowed_post_ids` filter — an array of post IDs that stay readable before agreement. Empty by default.
- New `pw_allowed_terms` filter — an array of taxonomy terms (by ID or slug) keyed by taxonomy, e.g. `['document_taxonomy' => [141]]`. Any post carrying one of those terms is exempt, as is the term's own archive. Empty by default.
- Both filters default to empty so no site-specific IDs live in the plugin.
- The exemption check runs *after* `hasUserAgreed()`, so the term lookups only cost a query for users who are actually being walled.

### Post-agreement guidance
- `savePolicyAgreement()` now returns `redirect`, `redirectLabel`, and `redirectDelay` alongside the existing message. Agreeing unlocks the site, but nothing on the page said so and users did not know what to do next.
- New `pw_post_agreement_redirect` filter (default `home_url('/')`) — where the user is sent after agreeing.
- New `pw_post_agreement_redirect_label` filter (default "You may now proceed to the home page.") — the link text.
- New `pw_post_agreement_redirect_delay` filter (default `5000`) — milliseconds before the automatic redirect. Set to `0` to show the link and leave the user where they are. Negative values are clamped to `0`.
- Front-end JS: replaced `typeAndFade()` with `typeMessage()`. The confirmation no longer fades out after five seconds — it stays on screen with the proceed link beneath it.
- Front-end JS: `wp_send_json_error()` responses were silently swallowed because they carry no `success` key. Their messages now display.
- Front-end JS: repeat clicks no longer stack duplicate proceed links.
- Styles: `.pw-button-wrapper a { text-decoration: none }` was flattening the new proceed link, so the underline is restored for `.pw-proceed a`.

### Agreed Users access
- `agreedUsersAction()` now checks `pw_view_agreed_cap` before adding the row link. The link was shown to every user who could edit a policy, but the page behind it requires `manage_options` — anyone below that got "Sorry, you are not allowed to access this page" (regression from the 2026-04-29 capability change). The page's own `current_user_can()` guard is unchanged.

### Tests
- New `PolicyWallAllowlistTest` — 14 tests covering both filters, ID and slug term matching, term archives, taxonomy isolation, the empty-term-list guard, and the end-to-end no-redirect case.
- New `PolicyWallPostAgreementTest` — 14 tests covering the redirect target/label/delay filters and their escaping, the AJAX payload on success and failure, and the row-action capability gate.
- `tests/stubs.php` — added `get_queried_object`, `get_queried_object_id`, `has_term`, `home_url`, `esc_url_raw`, `__`, and a `WP_Term` class stub.

## 2026-05-05

### Role-based policy bypass
- Added role-based bypass to `PwGeneric::hasUserAgreed()` — users in any role returned by the new `pw_bypass_roles` filter skip the policy wall entirely. Default list is `['external_user']` so temporary logins issued to external partners (via Temporary Login Without Password) are not redirected to the policies page and reach their configured landing URL instead.
- New `pw_bypass_roles` filter — accepts an array of role slugs.
- Existing capability bypass (`pw_bypass_caps`) is unchanged.

## 2026-04-29

### CSV export improvements
- Replaced display name with login username (`user_login`); column renamed to `Username`
- Added `Agreed Date` column (format `Y-m-d H:i:s`, site timezone) populated from new per-user timestamp
- Skips rows where the user account no longer exists
- Email cell now runs through `sanitize_csv_cell()` to prevent formula injection
- `savePolicyToUser()` switched from `add_user_meta` to `update_user_meta` for `_policy_agreed_to` — ensures `get_user_meta(..., true)` always returns the most recently agreed policy ID rather than the first; fixes a redirect loop for users who had agreed to multiple policies
- `savePolicyToUser()` now records agreement timestamp in user meta `_policy_agreed_date_{policyId}`

### Active-session policy enforcement
- Added `template_redirect` hook (`enforceAgreementForActiveSession`) — already-logged-in users are now redirected to the policy page when a new policy is published, without requiring a logout/login cycle
- Added `pw_policy_grace_period` filter (default `0`) — set to a number of seconds to allow active sessions to finish before enforcement kicks in (e.g. `add_filter('pw_policy_grace_period', fn() => DAY_IN_SECONDS)` for a 24-hour grace period)

### Security fixes
- Medium: `savePolicyAgreement()` — dropped `$_POST['userId']` trust; now uses `get_current_user_id()` and requires `is_user_logged_in()`; validates `policyId` is a published `pw_policies` post
- Medium: `pw_export_users_cap` filter — changed signature to pass capability string instead of bool, preventing a permissive `__return_true` hook from granting export access to any user
- Medium: Agreed Users admin page — capability raised from `edit_posts` to `manage_options` (filterable via `pw_view_agreed_cap`); added `current_user_can()` guard inside `pwAgreed()` callback
- Medium: `embedContent` / `embedContentAccordion` shortcodes — added `post_status` and `post_password` checks; draft, private, and password-protected posts no longer render to front-end visitors; null post guard added to `showLatestPolicy()`
- Low: `savePolicyPageIdNumber()` — validates post exists and is published before storing in `pw_policy_page_id` option
- Low: `foreach` on `get_post_meta` result — added `is_array()` guard in `pwShowAgreedTable()` and `exportUserCSV()` to prevent PHP 8 warnings on empty meta
- Low: `esc_attr()` → `esc_html()` for text-node output in `pwShowAgreedTable()` and `renderAgreedMetabox()`
- Low: Fixed double-slash and missing `esc_url()` on `admin_url()` output in `policySelect()`, `pwShowAgreedTable()`, and `renderAgreedMetabox()`
- Low: Fixed missing `echo` on spinner `<img src>` in instructions page
- Low: Front-end scripts and nonce no longer enqueued for logged-out users

## 2026-04-20

- High: Fixed IDOR in `savePolicyAgreement` — added identity check so a logged-in user can only save their own policy agreement, not another user's
- Medium: Fixed missing capability check in `savePolicyPageIdNumber` — added `current_user_can('manage_options')` after nonce verification
- Medium: Fixed stored XSS in `getPolicySelectOptions` admin dropdown — wrapped `get_the_title()` with `esc_html()` in `<option>` output
- Medium: Fixed stored XSS in `pwShowAgreedTable` heading — wrapped `get_the_title()` with `esc_html()`
- Medium: Fixed stored XSS in `embedContentAccordion` shortcode — wrapped `get_the_title()` with `esc_html()`
- Medium: Fixed stored XSS in `embedContent` shortcode — wrapped `get_the_title()` with `esc_html()`
- Low: Fixed CSV export `Content-Disposition` header — replaced `esc_attr()` + manual `str_replace` with `sanitize_file_name()` and quoted filename per RFC 6266
- Low: Fixed CSV formula injection — added `sanitize_csv_cell()` helper that prefixes dangerous leading characters (`=`, `+`, `-`, `@`) with a tab
- Low: Fixed CSV column order — `fputcsv` call was outputting email/name but header row declared name/email; now consistent
- Added test infrastructure: `devenv.nix`, `composer.json`, `phpunit.xml`, `tests/bootstrap.php`, `tests/stubs.php`, `tests/PolicyWallSecurityTest.php` (6 regression tests, all passing on PHP 8.3)

References: https://github.com/proudcity/wp-proudcity/issues/2797
