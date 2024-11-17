<?php
// Copyright 2024 Stephen Warren <swarren@wwwdotorg.org>
// SPDX-License-Identifier: MIT

require_once(dirname(__FILE__) . '/fcch-wa-utils.php');
waInit();
?>
<!DOCTYPE html>
<html>
<head>
<title>FCCH Wild Apricot Edit RFID</title>
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
<h1>FCCH Wild Apricot Edit RFID</h1>
<?php
if (is_null($waSelfContact)) {
    echo "ERROR: This form doesn't work for WP admins; docent RFID validation is required\n";
    exit;
}

if (!isset($_POST['action'])) {
?>
    <form method="POST">
    <table>
    <tr>
    <td><label for="action">Your RFID (for authentication):</label></td>
    <td><input type="number" id="auth_rfid" name="auth_rfid"></td>
    </tr><tr>
    <td><label for="action">Action:</label></td>
    <td><select id="action" name="action">
    <option value="add">Add specified RFID to user's list</option>
    <option value="remove">Remove specified RFID from user's list if present</option>
    <option value="replace">Replace ALL RFIDs assigned to user with specified RFID</option>
    <option value="clear">Remove ALL RFIDs assigned to user</option>
    <option value="query">Query user's assigned RFIDs</option>
    </select></td>
    </tr><tr>
    <td><label for="email">Member Email:</label></td>
    <td><input type="email" id="email" name="email" size="64"></td>
    </tr><tr>
    <td><label for="rfid">Member RFID:</label></td>
    <td><input type="number" id="rfid" name="rfid"></td>
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

// FIXME: Should we remove fcch-wa-utils.php's use of fcch-restrict-access.php,
// so stop relying on the manually-maintained list of authorized users,
// and solely rely on the WA contact privilege field trusted flag?
// If so, we'd probably want to apply that to the other scripts too?

$auth_rfid = array_get_or_default($_POST, 'auth_rfid', 'invalid');
$legal_auth_rfids = waRfidsOfContact($waSelfContact);
if (!in_array($auth_rfid, $legal_auth_rfids)) {
    echo "ERROR: Invalid authentication RFID\n";
    echo "</body></html>";
}

$action = array_get_or_default($_POST, 'action', 'invalid');
switch ($action) {
    case 'add':
    case 'remove':
    case 'replace':
        $need_rfid = true;
        $do_write = true;
        break;
    case 'clear':
        $need_rfid = false;
        $do_write = true;
        break;
    case 'query':
        $need_rfid = false;
        $do_write = false;
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

if ($need_rfid) {
    $rfid = array_get_or_default($_POST, 'rfid', '');
    if ($rfid === '') {
        echo "ERROR: Empty member RFID\n";
        echo "</body></html>";
        exit;
    }
    if (!preg_match('/^[0-9]+$/', $rfid)) {
        echo "ERROR: Illegal member RFID\n";
        echo "</body></html>";
        exit;
    }
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

if ($need_rfid) {
    $contactsWithRfid = waGetContacts("substringof('RFID ID', '$rfid'");
    $conflictCount = 0;
    foreach ($contactsWithRfid as $contactWithRfid) {
        if ($contactWithRfid['Id'] !== $contact['Id']) {
            $conflictCount++;
        }
    }
    if ($conflictCount !== 0) {
        echo "ERROR: RFID is already assigned to contacts:<br/>\n";
        dumpContactList($contactsWithRfid);
        echo "</body></html>";
        exit;
    }
}

$rfids = waRfidsOfContact($contact);
switch ($action) {
    case 'add':
        if (!in_array($rfid, $rfids)) {
            echo "NOTE: RFID added to user's RFID list.<br/>";
            $rfids[] = $rfid;
        } else {
            echo "NOTE: RFID already present in user's RFID list.<br/>";
            $do_write = false;
        }
        break;
    case 'remove':
        $key = array_search($rfid, $rfids);
        if ($key !== false) {
            echo "NOTE: RFID removed from user's RFID list.<br/>";
            unset($rfids[$key]);
        } else {
            echo "NOTE: RFID not previously present in user's RFID list.<br/>";
            $do_write = false;
        }
        break;
    case 'replace':
        if (count($rfids) === 0) {
            echo "NOTE: User's RFID list was previously empty.<br/>";
        }
        echo "NOTE: User's RFID list replaced.<br/>";
        $rfids = [$rfid];
        break;
    case 'clear':
        if (count($rfids) !== 0) {
            echo "NOTE: User's RFID list cleared.<br/>";
            $rfids = [];
        } else {
            echo "NOTE: User's RFID list was previously empty.<br/>";
            $do_write = false;
        }
        break;
    default:
        break;
}

if ($do_write) {
    waWriteContactFieldValue(
        $contact['Id'], 'RFID ID', implode(',', $rfids)
    );
}

echo "RFIDs now:<br/>\n";
foreach ($rfids as $rfid) {
    echo "... $rfid<br/>\n";
}
?>

</body>
</html>
