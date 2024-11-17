<?php
require_once(dirname(__FILE__) . '/fcch-wa-utils.php');
waInit();
?>
<!DOCTYPE html>
<html>
<!-- Stephen Warren wrote this 2024/11/13 -->
<head>
<title>FCCH Wild Apricot Contact Search</title>
<?php // Wordpress restrictions prevent the browser from fetching these itself:  ?>
<?php require_once(dirname(__FILE__) . '/fcch-searchable-table.css.php'); ?>
</head>
<body>
<?php
if (!isset($_POST['searchstring'])) {
    $searchString = '';
} else {
    $searchString = $_POST['searchstring'];
    if (!preg_match('/^[-A-Za-z0-9@_\(\) ]*$/', $searchString)) {
        $searchString = '';
        echo "<b>Illegal search string; only letters, numbers, -@_(), space accepted.</b>\n";
    }
}
$searchFields = [
    'FirstName',
    'LastName',
    //'DisplayName', // not supported by WA!
    'Email',
    'Phone',
    'RFID ID',
];
$searchFieldsStr = implode(', ', $searchFields);
?>
<h1>FCCH Wild Apricot Contact Search</h1>
<p>This form will search by: <?php echo $searchFieldsStr;?>.</p>
<form method="POST">
<label for="searchstring">Search for:</label>
<input type="text" id="searchstring" name="searchstring" value="<?php echo htmlentities($searchString);?>">
<br/>
<input type="submit" value="Search">
</form>
<br/>
<hr/>
<?php
if ($searchString !== '') {
    $filter =
        implode(' or ',
            array_map(
                function ($searchField) {
                    global $searchString;
                    return "substringof('$searchField', '$searchString')";
                },
                $searchFields));
    $contacts = waGetContacts($filter);

    echo "<p>Search results:</p>\n";
    echo "<table>\n";
    echo "<thead>\n";
    echo "<th>WA ID</th>\n";
    echo "<th>Status</th>\n";
    echo "<th>FirstName</th>\n";
    echo "<th>LastName</th>\n";
    echo "<th>DisplayName</th>\n";
    echo "<th>Email</th>\n";
    echo "<th>Phone</th>\n";
    echo "<th>RFID ID</th>\n";
    echo "<th>Privileges</th>\n";
    echo "</thead>\n";
    foreach ($contacts as $contact) {
        echo "<tr>\n";
        echo "<td>" . $contact['Id'] . "</td>\n";
        echo "<td>" . waMemberStatusOfContact($contact) . "</td>\n";
        echo "<td>" . $contact['FirstName'] . "</td>\n";
        echo "<td>" . $contact['LastName'] . "</td>\n";
        echo "<td>" . $contact['DisplayName'] . "</td>\n";
        echo "<td>" . $contact['Email'] . "</td>\n";
        $phone = waFieldValueOfContact($contact, 'Phone');
        echo "<td>" . $phone . "</td>\n";
        $rfids = waRfidsOfContact($contact);
        echo "<td>";
        foreach($rfids as $rfid) {
            echo $rfid;
            echo "<br/>";
        }
        echo "</td>\n";
        $privilegeNames = waPrivilegeNamesOfContact($contact);
        echo "<td>";
        foreach($privilegeNames as $privilegeName => $unused) {
            echo $privilegeName;
            echo "<br/>";
        }
        echo "</td>\n";
        echo "</tr>\n";
    }
    echo "</table>\n";

    echo "<hr/>\n";
    echo "<p>Raw search results:</p>\n";
    echo "<pre>\n";
    var_dump($contacts);
    echo "</pre>\n";
}
?>
</body>
</html>