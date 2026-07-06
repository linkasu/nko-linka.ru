<?php
/**
 * Filesystem settings required for WordPress admin-managed updates.
 */

if (!defined('FS_METHOD')) {
    define('FS_METHOD', 'direct');
}

if (!defined('WP_TEMP_DIR')) {
    define('WP_TEMP_DIR', getenv('WORDPRESS_TMP_DIR') ?: '/tmp/wordpress');
}
