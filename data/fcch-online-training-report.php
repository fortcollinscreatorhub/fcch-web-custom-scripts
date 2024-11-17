<?php
// Copyright 2023-2024 Stephen Warren <swarren@wwwdotorg.org>
// SPDX-License-Identifier: MIT

require_once(dirname(__FILE__) . '/fcch-restrict-access.php');
fcchRestrictAccess();
?>
<!DOCTYPE html>
<html>
<head>
<title>FCCH Online Training Report</title>
<?php // Wordpress restrictions prevent the browser from fetching these itself:  ?>
<?php require_once(dirname(__FILE__) . '/fcch-searchable-table.css.php'); ?>
<?php require_once(dirname(__FILE__) . '/fcch-searchable-table.js.php'); ?>
</head>
<body>
<h1>FCCH Online Training Report</h1>
<p>Search: <input id="searchCourseCompletion" type="text" oninput="doSearch('courseCompletion', this.value);"/></p>
<?php
$results = $wpdb->get_results('
    SELECT
        `wp_mgcf_users`.`display_name` AS displayName,
        `wp_mgcf_users`.`user_email` AS userEmail,
        `wp_mgcf_posts`.`post_title` AS courseName,
        `wp_mgcf_usermeta`.`meta_value` AS completionPercent
    FROM
        `wp_mgcf_users`
    INNER JOIN `wp_mgcf_usermeta`
            ON `wp_mgcf_usermeta`.`user_id` = `wp_mgcf_users`.`ID`
    INNER JOIN `wp_mgcf_posts`
            ON CONCAT("llms_course_", `wp_mgcf_posts`.`ID`, "_progress") = `wp_mgcf_usermeta`.`meta_key`
    WHERE
        `wp_mgcf_usermeta`.`meta_value` > 0 AND
        `wp_mgcf_posts`.`post_type` = "course" AND
        `wp_mgcf_posts`.`post_status` = "publish"
    ORDER BY
        `wp_mgcf_users`.`display_name` ASC,
        `wp_mgcf_users`.`user_email` ASC,
        `wp_mgcf_posts`.`post_title` ASC,
        `wp_mgcf_usermeta`.`meta_value` ASC
    ;
');
echo "<table id=\"courseCompletion\">\n";
echo "<thead><tr>\n";
echo "<th>Display Name</th>\n";
echo "<th>Email</th>\n";
echo "<th>Course Name</th>\n";
echo "<th>Completion %</th>\n";
echo "</tr></thead><tbody>\n";
foreach ($results as $row) {
    echo "<tr>\n";
    echo "<td>$row->displayName</td>\n";
    echo "<td>$row->userEmail</td>\n";
    echo "<td>$row->courseName</td>\n";
    echo "<td>$row->completionPercent</td>\n";
    echo "</tr>\n";
}
echo "</tbody></table>\n";
?>
</body>
</html>
