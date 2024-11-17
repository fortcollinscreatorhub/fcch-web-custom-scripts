<?php require_once(dirname(__FILE__) . '/fcch-restrict-access.php'); ?>
<!DOCTYPE html>
<html>
<!-- Stephen Warren wrote this 2023/06/08 -->
<head>
<title>FCCH Bookly Appointment List</title>
<?php // Wordpress restrictions prevent the browser from fetching these itself:  ?>
<?php require_once(dirname(__FILE__) . '/fcch-searchable-table.css.php'); ?>
<?php require_once(dirname(__FILE__) . '/fcch-searchable-table.js.php'); ?>
</head>
<body>
<p>Search: <input id="searchAppointmentList" type="text" oninput="doSearch('appointmentList', this.value);"/></p>
<?php
$oneDayAgo = time() - (24 * 60 * 60);
$fromDate = date("Y-m-d", $oneDayAgo);
$results = $wpdb->get_results("
    SELECT
        `wp_mgcf_bookly_appointments`.`start_date` AS dateTime,
        `wp_mgcf_bookly_services`.`title` AS service,
        `wp_mgcf_bookly_customers`.`full_name` AS custName,
        `wp_mgcf_bookly_customers`.`email` AS custEmail,
        `wp_mgcf_bookly_customers`.`phone` AS custPhone,
        `wp_mgcf_bookly_staff`.`full_name` AS staffName,
        `wp_mgcf_bookly_customer_appointments`.`notes` AS notes
    FROM
        `wp_mgcf_bookly_appointments`
    INNER JOIN `wp_mgcf_bookly_services`
            ON `wp_mgcf_bookly_services`.`id` = `wp_mgcf_bookly_appointments`.`service_id`
    INNER JOIN `wp_mgcf_bookly_customer_appointments`
            ON `wp_mgcf_bookly_customer_appointments`.`appointment_id` = `wp_mgcf_bookly_appointments`.`id`
    INNER JOIN `wp_mgcf_bookly_customers`
            ON `wp_mgcf_bookly_customers`.`id` = `wp_mgcf_bookly_customer_appointments`.`customer_id`
    INNER JOIN `wp_mgcf_bookly_staff`
            ON `wp_mgcf_bookly_staff`.`id` = `wp_mgcf_bookly_appointments`.`staff_id`
    INNER JOIN `wp_mgcf_bookly_staff_categories`
            ON `wp_mgcf_bookly_staff_categories`.`id` = `wp_mgcf_bookly_staff`.`category_id`
    WHERE
        `wp_mgcf_bookly_appointments`.`end_date` >= '${fromDate}' AND
        `wp_mgcf_bookly_customer_appointments`.`status` = 'approved' AND
        `wp_mgcf_bookly_staff_categories`.`name` = 'Volunteer'
    ORDER BY
        `wp_mgcf_bookly_appointments`.`start_date` ASC,
        `wp_mgcf_bookly_staff`.`full_name` ASC
    ;
");
echo "<table id=\"appointmentList\">\n";
echo "<thead><tr>\n";
echo "<th>Date/Time</th>\n";
echo "<th>Service</th>\n";
echo "<th>Customer Name</th>\n";
echo "<th>Customer Email</th>\n";
echo "<th>Customer Phone</th>\n";
echo "<th>Staff Name</th>\n";
echo "<th>Notes</th>\n";
echo "</tr></thead><tbody>\n";
foreach ($results as $row) {
    echo "<tr>\n";
    echo "<td>$row->dateTime</td>\n";
    echo "<td>$row->service</td>\n";
    echo "<td>$row->custName</td>\n";
    echo "<td>$row->custEmail</td>\n";
    echo "<td>$row->custPhone</td>\n";
    echo "<td>$row->staffName</td>\n";
    echo "<td>$row->notes</td>\n";
    echo "</tr>\n";
}
echo "</tbody></table>\n";
?>
</body>
</html>
