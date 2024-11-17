<?php
// Copyright 2023-2024 Stephen Warren <swarren@wwwdotorg.org>
// SPDX-License-Identifier: MIT

    // https://stackoverflow.com/questions/2810124/how-can-i-add-a-php-page-to-wordpress
    require_once(dirname(__FILE__) . '/../wp-config.php');
    $wp->init();
    $wp->parse_request();
    $wp->query_posts();
    $wp->register_globals();

    $wpUser = wp_get_current_user();
    $userRoles = [];
    $userName = "";
    if ($wpUser->exists()) {
        $userRoles = $wpUser->roles;
        $userName = $wpUser->user_login;
    }

    $acceptable = false;

    $acceptableRoles = [];
    $acceptableRoles[] = "administrator";
    $acceptableRoles[] = "wa_level_993871"; // Docent; https://fortcollinscreatorhub.wildapricot.org/admin/members/levels/details/?mLevelId=993871&tab=general
    foreach ($userRoles as $role) {
        #error_log("fcch-restrict-access.php: role is '${role}'");
        if (in_array($role, $acceptableRoles)) {
            $acceptable = true;
        }
    }

    $acceptableUserNames = [];
    # Generally the following people may not be on the docent payment plan,
    # so need to be listed here in order to access the other FCCH scripts
    # in this directory.
    $acceptableUserNames[] = "Scott Baily"; # Baily, Scott (Wood shop trainer)
    $acceptableUserNames[] = "wa_contact_48782877"; # Chou, Danice (Tour, Orientation, LASER trainer)
    $acceptableUserNames[] = "wa_contact_47226670"; # Croak, Brian (Tour, Orientation, LASER trainer)
    $acceptableUserNames[] = "Nicholas Dalke"; # Dalke, Nicholas (Tour, Orientation)
    $acceptableUserNames[] = "concretedog"; # Davis, Chris (Wood shop trainer)
    $acceptableUserNames[] = "wa_contact_47226387"; # Kluibenschaedl, Florian (Wood shop trainer)
    $acceptableUserNames[] = "wa_contact_50573082"; # Mitchell, Ike (Tour, Orientation, LASER trainer)
    $acceptableUserNames[] = "wa_contact_52158593"; # Moore, Casey (Wood ABC, LASER)
    $acceptableUserNames[] = "wa_contact_47226595"; # Warren, Stephen (Tour, Orientation, LASER trainer)
    $acceptableUserNames[] = "wa_contact_48895380"; # Wolff, Wirt (Tour, Orientation, LASER trainer)
    $acceptableUserNames[] = "wa_contact_49417179"; # Krnan, Luko (Treasurer)
    $acceptableUserNames[] = "wa_contact_52782389"; # Kurotsuchi, Brian (3D printer boss)
    $acceptableUserNames[] = "Jordan Marsh"; # Marsh, Jordan (Welding training)
    $acceptableUserNames[] = "wa_contact_59937520"; # McLaughlin, Jamie (Education coordinator)
    $acceptableUserNames[] = "wa_contact_47884235"; # Miller, Jim (Wood shop boss)
    $acceptableUserNames[] = "wa_contact_47226689"; # Moore, Dave (Wood shop boss)
    $acceptableUserNames[] = "Ronald Petrozzo"; # Petrozzon, Ron (Metal shop boss)
    $acceptableUserNames[] = "wa_contact_53371846"; # Pickman, Ellen (2024/25 board)
    $acceptableUserNames[] = "wa_contact_47226671"; # Poehlman, Steve (Ex-president, tech backup)
    $acceptableUserNames[] = "wa_contact_49011295"; # Showers, Derek (LASER boss backup)
    $acceptableUserNames[] = "wa_contact_61289556"; # Simms, Billy (2024/25 board)
    $acceptableUserNames[] = "Dave Taylor"; # Taylor, Dave (Metal shop trainer)
    $acceptableUserNames[] = "wa_contact_47218896"; # Undy, Steve (Ex-president, tech backup)
    $acceptableUserNames[] = "wa_contact_47226472"; # Van, Ray (Metal shop trainer)
    $acceptableUserNames[] = "wa_contact_70584132"; # Vitorino, Alicia (2024/25 board)
    $acceptableUserNames[] = "wa_contact_47226688"; # Zdunek, Jim (LASER boss, 2024/25 board, etc.)
    #error_log("fcch-restrict-access.php: userName is '${userName}'");
    if (in_array($userName, $acceptableUserNames)) {
        $acceptable = true;
    }

    $wp->send_headers();
    #error_log("fcch-restrict-access.php: acceptable is '${acceptable}'");
    if (!$acceptable) {
        echo "Permission denied; you need to log in as an admin, a docent, or a specifically authorized user.";
        exit;
    }
?>
