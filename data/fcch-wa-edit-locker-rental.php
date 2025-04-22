<?php
// Copyright 2024 Stephen Warren <swarren@wwwdotorg.org>
// SPDX-License-Identifier: MIT

require_once(dirname(__FILE__) . '/fcch-restrict-access.php');
fcchRestrictAccess();
?>
<!DOCTYPE html>
<html>
<head>
<title>FCCH Wild Apricot Edit Locker Rental</title>
<style>
table {
     border: 0;
}
td:first-child {
    text-align: right;
}
</style>
</head>
<body>
<h1>FCCH Wild Apricot Edit Locker Rental</h1>
<?php
if (is_null($waSelfContact)) {
    echo "ERROR: This form doesn't work for WP admins; docent RFID validation is required\n";
    echo "</body></html>";
    exit;
}

if (!isset($_POST['action'])) {
?>
    <form method="POST">
    <table>
    <tr>
    <td><label for="action">Your RFID (for authentication):</label></td>
    <td><input type="password" autocomplete="new-password" id="auth_rfid" name="auth_rfid"></td>
    </tr><tr>
    <td><label for="action">Action:</label></td>
    <td><select id="action" name="action">
    <option value="query">Query locker count for user</option>
    <option value="add">Increase locker count for user</option>
    <option value="sub">Reduce locker count for user</option>
    <option value="set">Set locker count for user</option>
    <option value="zero">Zero/clear locker count for user</option>
    </select></td>
    </tr><tr>
    <td><label for="email">Member Email:</label></td>
    <td><input type="email" id="email" name="email" size="64"></td>
    </tr><tr>
    <td><label for="count">Locker count:</label></td>
    <td><input type="number" id="count" name="count" value="1"></td>
    </tr><tr>
    <td></td>
    <td><input type="submit" value="Submit"></td>
    </tr>
    </table>
    </form>
    </body>
    </html>
<?php
    exit;
}

$auth_rfid = array_get_or_default($_POST, 'auth_rfid', 'invalid');
$legal_auth_rfids = waRfidsOfContact($waSelfContact);
if (!in_array($auth_rfid, $legal_auth_rfids)) {
    echo "ERROR: Invalid authentication RFID\n";
    echo "</body></html>";
    exit;
}

$action = array_get_or_default($_POST, 'action', 'invalid');
switch ($action) {
    case 'query':
        $needCount = false;
        $doWrite = false;
        break;
    case 'add':
    case 'sub':
    case 'set':
        $needCount = true;
        $doWrite = true;
        break;
    case 'zero':
        $needCount = false;
        $doWrite = true;
        break;
    default:
        echo "ERROR: Invalid action\n";
        echo "</body></html>";
        exit;
}

$email = array_get_or_default($_POST, 'email', '');
if ($email === '') {
    echo "ERROR: Empty member email\n";
    echo "</body></html>";
    exit;
}
if (preg_match('/[\'"]/', $email)) {
    echo "ERROR: Illegal character in member email\n";
    echo "</body></html>";
    exit;
}
// Other email validation is not required; we simply search against whatever
// values are already in the Wild Apricot DB.
$email = strtolower($email);

if ($needCount) {
    $count = array_get_or_default($_POST, 'count', '');
    if (!preg_match('/^[0-9]+$/', $count)) {
        echo "ERROR: Illegal count\n";
        echo "</body></html>";
        exit;
    }
    $count = ltrim($count, '0');
    if ($count === '') {
        echo "ERROR: Empty count\n";
        echo "</body></html>";
        exit;
    }
    $count = (int)$count;
} else {
    $count = 0;
}

function dumpContactList($contacts) {
    foreach ($contacts as $contact) {
        echo $contact['FirstName'];
        echo " ";
        echo $contact['LastName'];
        echo " ";
        echo $contact['Email'];
        echo "<br/>\n";
    }
}

$contacts = waGetContacts("'Email' eq '$email'");
$contactCount = count($contacts);
if ($contactCount === 0) {
    echo "</body></html>";
    echo "ERROR: Member email not found\n";
    exit;
}
if ($contactCount !== 1) {
    echo "ERROR: Multiple member email matches found:<br/>\n";
    dumpContactList($contacts);
    echo "</body></html>";
    exit;
}
$contact = $contacts[0];
$contactStatus = waMemberStatusOfContact($contact);
$contactBundleMember = waFieldValueOfContact($contact, 'Member role');
if (
    (!str_starts_with($contactStatus, 'Active')) ||
    ($contactBundleMember === 'Bundle member') ||
    (str_contains($contactStatus, '(Docent)'))
) {
    echo "ERROR: Contact is not active, or is a bundle member, or is a docent<br/>\n";
    echo "</body></html>";
    exit;
}

list($lockerFieldName, $lockerCount) = waLockerRentalOfContact($contact);
$origLockerCount = $lockerCount;
$origDoWrite = $doWrite;
switch ($action) {
    case 'add':
        $lockerCount += $count;
        break;
    case 'sub':
        if ($lockerCount < $count) {
            echo "NOTE: Locker count less than requested reduction; set to 0.<br/>";
            $count = 0;
        } else {
            $lockerCount -= $count;
        }
        break;
    case 'set':
        $lockerCount = $count;
        break;
    case 'zero':
        $lockerCount = 0;
        break;
    default:
        break;
}
if ($lockerCount == $origLockerCount) {
    if ($origDoWrite) {
        echo "NOTE: Database write skipped.<br/>";
    }
    $doWrite = 0;
}

if ($doWrite) {
    $auditArray = array(
        'date' => date('Y-m-d H:i:s'),
        'site' => $_SERVER['SERVER_NAME'],
        'script' => basename(__FILE__),
        'waId' => $waSelfContact['Id'],
        'waName' => $waSelfContact['DisplayName'],
        'formAction' => $action,
        'formEmail' => $email,
        'formCount' => $count,
        'origLockerCount' => $origLockerCount,
        'lockerCount' => $lockerCount,
    );
    $auditText = json_encode($auditArray) . "\n";
    file_put_contents('/home/u930-v2vbn3xb6dhb/.wa/audit.txt', $auditText, FILE_APPEND);

    waWriteContactFieldValue(
        $contact['Id'], $lockerFieldName, $lockerCount
    );
}

if ($lockerCount > $origLockerCount) {
    echo "Locker count INCREASED; make sure to collect key deposit(s), and perhaps partial-month payment(s).<br/>\n";
}
if ($action !== 'query') {
    echo "Locker count was: {$origLockerCount}<br/>\n";
}
echo "Locker count now: {$lockerCount}<br/>\n";
echo "Locker field name: {$lockerFieldName}<br/>\n";
?>

</body>
</html>
