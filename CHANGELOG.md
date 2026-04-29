## 2026-04-29
- CSV export: replaced display name with login username; column renamed to `Username`
- CSV export: added `Agreed Date` column (format `Y-m-d H:i:s`) populated from new per-user timestamp
- CSV export: skips rows where the user account no longer exists
- `savePolicyToUser()` now records agreement timestamp in user meta `_policy_agreed_date_{policyId}`

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
