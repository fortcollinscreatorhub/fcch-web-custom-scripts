<?php
// Copyright 2024-2025 Stephen Warren <swarren@wwwdotorg.org>
// SPDX-License-Identifier: MIT

require_once(dirname(__FILE__) . '/fcch-restrict-access.php');
fcchRestrictAccess();
?>
<!DOCTYPE html>
<html>
<head>
<title>FCCH Wild Apricot Clean Deleted Privs</title>
</head>
<body>
<h1>FCCH Wild Apricot Clean Deleted Privs</h1>
<pre>
<?php
if (is_null($waSelfContact)) {
    echo "ERROR: This form doesn't work for WP admins; docent RFID validation is required.\n";
    echo "</pre></body></html>";
    exit;
}

// Disabled so this can't be run except by explicit admin action to re-enable it
echo "ERROR: This form is currently disabled.\n";
echo "</pre></body></html>";
exit;

$url = $waAccountUrl . '/contactfields/10379662';
$privDefs = waArrayQuery($url, array(), 'AllowedValues');

echo "Privileges:\n";
$knownPrivilegeIds = [];
foreach ($privDefs as $privDef) {
    $privilegeId = $privDef['Id'];
    $knownPrivilegeIds[$privilegeId] = true;
    $label = $privDef['Label'];
    echo "    $privilegeId $label\n";
    #foreach ($privDef as $key => $value) {
    #    echo "        \"" . $key . "\"=\"" . $value . "\"\n";
    #}
}

$contacts = waGetContacts("");
$contactCount = count($contacts);
echo "Contacts:\n";
foreach ($contacts as $contact) {
    echo "    " . $contact['Email'] . "\n";
    $privileges = waPrivilegeIdsOfContact($contact);
    $removePrivilegeIds = [];
    echo "        Initial privileges:\n";
    foreach (array_keys($privileges) as $privilegeId) {
        echo "            " . $privilegeId . " ";
        if (isset($knownPrivilegeIds[$privilegeId])) {
            echo "known";
        } else {
            echo "UNKNOWN";
            $removePrivilegeIds[] = $privilegeId;
        }
        echo "\n";
    }
    echo "        To remove:\n";
    foreach ($removePrivilegeIds as $removePrivilegeId) {
        echo "            $removePrivilegeId\n";
        unset($privileges[$removePrivilegeId]);
    }
    $removeCount = count($removePrivilegeIds);
    if ($removeCount) {
        echo "        Writing...\n";
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
}
?>
</pre>
</body>
</html>
