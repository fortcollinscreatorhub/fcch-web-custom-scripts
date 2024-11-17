<?php
// Copyright 2023-2024 Stephen Warren <swarren@wwwdotorg.org>
// SPDX-License-Identifier: MIT

require_once(dirname(__FILE__) . '/../wp-config.php');
require_once(dirname(__FILE__) . '/fcch-wa-utils.php');

function fcchRestrictAccess() {
    global $wp;

    // https://stackoverflow.com/questions/2810124/how-can-i-add-a-php-page-to-wordpress
    $wp->init();
    $wp->parse_request();
    $wp->query_posts();
    $wp->register_globals();
    $wp->send_headers();

    waInit();
}
?>
