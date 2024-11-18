<?php
// Copyright 2024 Stephen Warren <swarren@wwwdotorg.org>
// SPDX-License-Identifier: MIT

require_once(dirname(__FILE__) . '/fcch-restrict-access.php');
fcchRestrictAccess();
?>
<!DOCTYPE html>
<html>
<head>
<title>FCCH Wild Apricot Edit Privileges</title>
<style>
table {
     border: 0;
}
td:first-child {
    vertical-align: top;
    text-align: right;
}
</style>
</head>
<body>
<h1>FCCH Wild Apricot Edit Privileges</h1>
<?php
if (is_null($waSelfContact)) {
    echo "ERROR: This form doesn't work for WP admins; docent RFID validation is required\n";
    echo "</body></html>";
    exit;
}

$url = $waAccountUrl . '/contactfields/10379662';
$ret = $waApiClient->makeRequest($url);
$privDefs = array_get_or_default($ret, 'AllowedValues', []);
if (!count($privDefs)) {
    echo "ERROR: could not query privileges field definition.\n";
    echo "</body></html>";
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
    <option value="add">Add specified privileges to user's list</option>
    <option value="remove">Remove specified privileges from user's list if present</option>
    <option value="replace">Replace ALL privileges assigned to user with specified privileges</option>
    <option value="clear">Remove ALL privileges assigned to user</option>
    <option value="query">Query user's assigned privileges</option>
    </select></td>
    </tr><tr>
    <td><label for="email">Member Email:</label></td>
    <td><input type="email" id="email" name="email" size="64"></td>
    </tr><tr>
    <td>Member privileges:</td>
    <td>
    <?php
    foreach ($privDefs as $privDef) {
        $privilegeId = $privDef['Id'];
        $label = $privDef['Label'];
        echo "<input type=\"checkbox\" name=\"priv$privilegeId\"/>$label<br/>\n";
    }
    ?>
    </td>
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
    case 'add':
    case 'remove':
    case 'replace':
        $needFormPrivs = true;
        $doWrite = true;
        break;
    case 'clear':
        $needFormPrivs = false;
        $doWrite = true;
        break;
    case 'query':
        $needFormPrivs = false;
        $doWrite = false;
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

if ($needFormPrivs) {
    $formPrivs = array();
    foreach ($privDefs as $privDef) {
        $privilegeId = $privDef['Id'];
        $inputName = "priv$privilegeId";
        $privChecked = array_get_or_default($_POST, $inputName, 'off');
        if ($privChecked === 'on') {
            $formPrivs[] = $privilegeId;
        }
    }
} else {
    $formPrivs = [];
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

$privileges = waPrivilegeIdsOfContact($contact);
$origPrivileges = $privileges;
$origDoWrite = $doWrite;
switch ($action) {
    case 'add':
        $someAdded = false;
        $someNotAdded = false;
        foreach ($formPrivs as $privilegeId) {
            if (isset($privileges[$privilegeId])) {
                $someNotAdded = true;
            } else {
                $someAdded = true;
                $privileges[$privilegeId] = true;
            }
        }
        if ($someAdded) {
            echo "NOTE: Some privileges added to user's privileges list.<br/>";
        }
        if ($someNotAdded) {
            echo "NOTE: Some privileges already present in user's privileges list.<br/>";
        }
        $doWrite = $someAdded;
        break;
    case 'remove':
        $someRemoved = false;
        $someNotRemoved = false;
        foreach ($formPrivs as $privilegeId) {
            if (isset($privileges[$privilegeId])) {
                $someRemoved = true;
                unset($privileges[$privilegeId]);
            } else {
                $someNotRemoved = true;
            }
        }
        if ($someRemoved) {
            echo "NOTE: Some privileges removed from user's privileges list.<br/>";
        }
        if ($someNotRemoved) {
            echo "NOTE: Some privileges weren't present in user's privileges list.<br/>";
        }
        $doWrite = $someRemoved;
        break;
    case 'replace':
        if (count($privileges) === 0) {
            echo "NOTE: User's privileges list was previously empty.<br/>";
        } else {
            echo "NOTE: User's privileges list was NOT previously empty.<br/>";
        }
        echo "NOTE: User's privileges list replaced.<br/>";
        $privileges = [];
        foreach ($formPrivs as $privilegeId) {
            $privileges[$privilegeId] = true;
        }
        break;
    case 'clear':
        if (count($privileges) !== 0) {
            echo "NOTE: User's privileges list cleared.<br/>";
            $privileges = [];
        } else {
            echo "NOTE: User's privileges list was previously empty.<br/>";
            $doWrite = false;
        }
        break;
    default:
        break;
}
if (!$doWrite && $origDoWrite) {
    echo "NOTE: Database write skipped.<br/>";
}

if ($doWrite) {
    $auditArray = array(
        'date' => date('Y-m-d H:i:s'),
        'site' => $_SERVER['SERVER_NAME'],
        'script' => basename(__FILE__),
        'waId' => $waSelfContact['Id'],
        'waName' => $waSelfContact['DisplayName'],
        'formAction' => $action,
        'formPrivs' => $formPrivs,
        'origPrivileges' => array_keys($origPrivileges),
        'privileges' => array_keys($privileges),
    );
    $auditText = json_encode($auditArray) . "\n";
    file_put_contents('/home/u930-v2vbn3xb6dhb/.wa/audit.txt', $auditText, FILE_APPEND);

    $fieldValue = [];
    foreach ($privileges as $privilegeId => $unused) {
        $fieldValue[] = array(
            'Id' => $privilegeId,
        );
    }
    waWriteContactFieldValue(
        $contact['Id'], 'Privileges', $fieldValue
    );
}

echo "Privileges now:<br/>\n";
foreach ($privDefs as $privDef) {
    $privilegeId = $privDef['Id'];
    $label = $privDef['Label'];
    if (isset($privileges[$privilegeId])) {
        echo "... $label<br/>\n";
    }
}
?>

</body>
</html>
