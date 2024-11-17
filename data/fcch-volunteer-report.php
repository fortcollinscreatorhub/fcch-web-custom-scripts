<?php
// Copyright 2024 Stephen Warren <swarren@wwwdotorg.org>
// SPDX-License-Identifier: MIT

require_once(dirname(__FILE__) . '/fcch-restrict-access.php');
fcchRestrictAccess();
?>
<!DOCTYPE html>
<html>
<head>
<title>FCCH Volunteer Schedule Report</title>
<?php // Wordpress restrictions prevent the browser from fetching these itself:  ?>
</head>
<body>
<h1>FCCH Volunteer Schedule Report</h1>
<pre>
<?php
$results_services = $wpdb->get_results('
    SELECT
        `id`,
        `title`
    FROM
        `wp_mgcf_bookly_services`
    ;
');
$service_id_to_title = array();
foreach ($results_services as $row) {
    $service_id_to_title[$row->id] = $row->title;
}

$results_staff_services = $wpdb->get_results('
    SELECT
        `staff_id`,
        `service_id`
    FROM
        `wp_mgcf_bookly_staff_services`
    ;
');
$staff_id_to_service_ids = array();
foreach ($results_staff_services as $row) {
    if (!array_key_exists($row->staff_id, $staff_id_to_service_ids)) {
        $staff_id_to_service_ids[$row->staff_id] = array();
    }
    array_push($staff_id_to_service_ids[$row->staff_id], $row->service_id);
}

$results_staff_schedule_items = $wpdb->get_results('
    SELECT
        `id`,
        `staff_id`,
        `day_index`,
        `start_time`,
        `end_time`
    FROM
        `wp_mgcf_bookly_staff_schedule_items`
    ;
');
$staff_to_day_to_schedule = array();
foreach ($results_staff_schedule_items as $row) {
    if ($row->start_time == "" && $row->end_time == "") {
         continue;
    }
    $staff_to_day_to_schedule[$row->staff_id][$row->day_index] = array(
        $row->id,
        $row->start_time,
        $row->end_time);
}

$results_schedule_item_breaks = $wpdb->get_results('
    SELECT
        `staff_schedule_item_id`,
        `start_time`,
        `end_time`
    FROM
        `wp_mgcf_bookly_schedule_item_breaks`
    ;
');
$schedule_item_breaks = array();
foreach ($results_schedule_item_breaks as $row) {
    if (!array_key_exists($row->staff_schedule_item_id, $schedule_item_breaks)) {
        $schedule_item_breaks[$row->staff_schedule_item_id] = array();
    }
    array_push(
        $schedule_item_breaks[$row->staff_schedule_item_id],
        array($row->start_time, $row->end_time));
}

$day_to_name = array(
    1 => 'Sun',
    2 => 'Mon',
    3 => 'Tue',
    4 => 'Wed',
    5 => 'Thu',
    6 => 'Fri',
    7 => 'Sat',
);

$results_staff = $wpdb->get_results('
    SELECT
        `id`,
        `wp_user_id`,
        `full_name`,
        `email`,
        `visibility`
    FROM
        `wp_mgcf_bookly_staff`
    WHERE
        `category_id` = 4
    ORDER BY
        `full_name` ASC
    ;
');

foreach ($results_staff as $row_staff) {
    if ($row_staff->visibility === "archive") {
        continue;
    }

    echo "$row_staff->full_name ($row_staff->email)\n";

    echo "    Services\n";
    $service_ids = $staff_id_to_service_ids[$row_staff->id] ?? array();
    foreach ($service_ids as $service_id) {
        $service_title = $service_id_to_title[$service_id] ?? "???";
        echo "        $service_title\n";
    }
    if (count($service_ids) == 0) {
        echo "        (none)\n";
    }

    echo "    Hours\n";
    $printed = false;
    $staff_days = $staff_to_day_to_schedule[$row_staff->id] ?? array();
    foreach ($day_to_name as $day_id => $day_name) {
        if (!array_key_exists($day_id, $staff_days)) {
            continue;
        }
        $schedule = $staff_days[$day_id];
        $printed = true;
        $schedule_item_id = $schedule[0];
        $start_time = $schedule[1];
        $end_time = $schedule[2];
        echo "        $day_name: $start_time to $end_time\n";
        $breaks = $schedule_item_breaks[$schedule_item_id] ?? array();
        foreach ($breaks as $break) {
            $start_time = $break[0];
            $end_time = $break[1];
            echo "             except $start_time to $end_time\n";
        }
    }
    if (!$printed) {
        echo "        (none)\n";
    }

    echo "\n";
}
?>
</pre>
</body>
</html>
