<?php

/**
 * PHPUnit bootstrap for policy-wall.
 *
 * Load order is critical:
 *   1. Patchwork — must be active before any file that defines functions you
 *      want to patch per-test, so its stream wrapper is in place when
 *      stubs.php and the plugin files are loaded.
 *   2. Composer autoload — loads Brain\Monkey and PHPUnit infrastructure.
 *   3. stubs.php — minimal WP function stubs for load-time calls, defined
 *      with if(!function_exists) so Patchwork can still patch them per-test.
 *   4. Plugin files — included once here; all tests run against these already-
 *      loaded class definitions.
 *
 * Run tests from the plugin root:
 *   composer install
 *   composer test
 */

require_once __DIR__ . '/../vendor/antecedent/patchwork/Patchwork.php';
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/stubs.php';

// Loading policy-wall.php triggers PolicyWall::instance() → init(), which
// calls constants() (defines POLICY_WALL_PLUGIN_DIR) and includes the sub-
// files (cpt, shortcodes, metaboxes, generic). All WP hooks called during
// init are no-ops thanks to the add_action/add_filter stubs above.
require_once __DIR__ . '/../policy-wall.php';
