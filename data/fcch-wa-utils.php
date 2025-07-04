<?php
// Copyright 2024 Stephen Warren <swarren@wwwdotorg.org>
// SPDX-License-Identifier: MIT

$homePath = '/home/u930-v2vbn3xb6dhb/';
$dotWaPath = $homePath . '/.wa';

require_once(dirname(__FILE__) . '/WaApi.php');
require_once($dotWaPath . '/api.php');

function waAuditWrite($auditArray) {
    global $dotWaPath;

    $auditArrayBase = array(
        'date' => date('Y-m-d H:i:s'),
        'site' => $_SERVER['SERVER_NAME'],
    );
    $auditArray = array_merge($auditArrayBase, $auditArray);
    $auditText = json_encode($auditArray) . "\n";
    $fn = 'audit-' . date('Y-m') . '-' . $_SERVER['SERVER_NAME'] . '.txt';
    file_put_contents($dotWaPath . '/' . $fn, $auditText, FILE_APPEND);
}

function array_get_or_default($array, $key, $default=null) {
    if (!isset($array[$key])) {
        return $default;
    }
    return $array[$key];
}

function waArrayQuery($baseUrl, $baseQueryParams, $arrayName) {
    global $waApiClient;

    $pageSize = 100;
    $pageStart = 0;
    $result = array();
    while (true) {
        $queryParams = $baseQueryParams;
        $queryParams['$top'] = $pageSize;
        $queryParams['$skip'] = $pageStart;
        $url = $baseUrl . '?' . http_build_query($queryParams);
        $response = $waApiClient->makeRequest($url);
        $thisResult = array_get_or_default($response, $arrayName, []);
        $thisResultLen = count($thisResult);
        $result = array_merge($result, $thisResult);
        $pageStart += $thisResultLen;
        if ($thisResultLen != $pageSize)
            break;
    }
    return $result;
}

function waGetAccountDetails() {
    global $waApiClient;
    global $waAccountUrl;

    $url = 'https://api.wildapricot.org/v2.2/Accounts/';
    $response = $waApiClient->makeRequest($url);
    $waAccountUrl = $response[0]['Url'];
}

function waGetSelfWaId() {
    global $wpdb;
    global $waSelfWaId;

    $wpUser = wp_get_current_user();
    if (!$wpUser->exists()) {
        echo "ERROR: Not logged in to WP; visit www.fortcollinscreatorhub.org and click the login button.\n";
        exit;
    }

    $wpUserID = $wpUser->ID;
    $results = $wpdb->get_results("
        SELECT
            `meta_value`
        FROM
            `wp_mgcf_usermeta`
        WHERE
            `user_id` = '$wpUserID' AND
            `meta_key` = 'wa_contact_id'
        LIMIT 1
        ;
    ");
    if (count($results) != 1) {
        echo "ERROR: count(usermeta.wa_contact_id)!=1 for WP user\n";
        exit;
    }
    $waSelfWaId = $results[0]->meta_value;
    if (!$waSelfWaId) {
        echo "ERROR: null usermeta.wa_contact_id for WP user\n";
        exit;
    }
}

function waGetContact($userWaId) {
    global $waApiClient;
    global $waAccountUrl;

    $url = $waAccountUrl . '/contacts/' . $userWaId;
    return $waApiClient->makeRequest($url);
}

function waGetContacts($filter) {
    global $waAccountUrl;

    $queryParams = array(
        '$async' => 'false',
        '$filter' => $filter
    );
    $url = $waAccountUrl . '/contacts/';
    return waArrayQuery($url, $queryParams, 'Contacts');
}

function waFieldValueOfContact($contact, $fieldName) {
    if (!isset($contact['FieldValuesArray'])) {
        $fieldValuesHash = array();
        foreach ($contact['FieldValues'] as $fieldValue) {
            $fieldValuesHash[$fieldValue['FieldName']] = $fieldValue['Value'];
        }
        $contact['FieldValuesHash'] = $fieldValuesHash;
    }

    return array_get_or_default($contact['FieldValuesHash'], $fieldName, null);
}

function waMemberStatusOfContact($contact) {
    $archived = waFieldValueOfContact($contact, 'Archived');
    if ($archived)
        return "Archived";
    $suspended = waFieldValueOfContact($contact, 'Suspended member');
    if ($suspended)
        return "Suspended";
    $membershipEnabled = array_get_or_default($contact, 'MembershipEnabled', false);
    if (!$membershipEnabled)
        return "NoMembershipEnabled";
    $membershipLevel = $contact['MembershipLevel']['Name'];
    $status = $contact['Status'];
    return "$status ($membershipLevel)";
}

function waRenewalDueOfContact($contact) {
    $renewalDue = waFieldValueOfContact($contact, 'Renewal due');
    if (is_null($renewalDue))
        return "";
    return $renewalDue;
}

function waRfidsOfContact($contact) {
    $rawValue = waFieldValueOfContact($contact, 'RFID ID');
    if (is_null($rawValue))
        return [];

    $results = [];
    $rawValues = explode(',', $rawValue);
    foreach ($rawValues as $rawValue) {
        $rfid = trim($rawValue);
        if ($rfid === '')
            continue;
        $results[] = $rfid;
    }
    return $results;
}

function waPrivilegeIdsOfContact($contact) {
    $privileges = waFieldValueOfContact($contact, 'Privileges');
    if (is_null($privileges))
        return [];

    $results = [];
    foreach ($privileges as $privilege) {
        $privilegeId = $privilege['Id'];
        $results[$privilegeId] = true;
    }
    return $results;
}

function waPrivilegeNamesOfContact($contact) {
    $privileges = waFieldValueOfContact($contact, 'Privileges');
    if (is_null($privileges))
        return [];

    $results = [];
    foreach ($privileges as $privilege) {
        $privilegeName = $privilege['Label'];
        $results[$privilegeName] = true;
    }
    return $results;
}

function waLockerRentalOfContact($contact) {
    $contactStatus = waMemberStatusOfContact($contact);
    if (
        (str_contains($contactStatus, 'Prepaid')) ||
        (str_contains($contactStatus, 'Annual'))
    ) {
        $lockerFieldName = 'Annual Locker Rental';
    } else {
        $lockerFieldName = 'Monthly Locker Rental';
    }

    $lockerCount = waFieldValueOfContact($contact, $lockerFieldName);
    if (is_null($lockerCount))
        $lockerCount = 0;
    return array($lockerFieldName, $lockerCount);
}

function waWriteContactFieldValue($userWaId, $fieldName, $fieldValue) {
    global $waApiClient;
    global $waAccountUrl;

    $url = $waAccountUrl . '/contacts/' . $userWaId;
    $field = [];
    $field['FieldName'] = $fieldName;
    $field['Value'] = $fieldValue;
    $fields = [];
    $fields[] = $field;
    $data = [];
    $data['Id'] = $userWaId;
    $data['FieldValues'] = $fields;
    return $waApiClient->makeRequest($url, 'PUT', $data);
    // Note: docs say the updated contact is returned, but in fact
    // it looks like the original contact data is returned:-(
}

function waCheckSelfIsTrusted() {
    global $waSelfContact;

    $membershipEnabled = array_get_or_default($waSelfContact, 'MembershipEnabled', false);
    if (!$membershipEnabled) {
        var_dump($waSelfContact);
        echo "ERROR: MembershipEnabled not set to true in Wild Apricot.\n";
        exit;
    }

    $status = array_get_or_default($waSelfContact, 'Status', '');
    if ($status !== 'Active') {
        echo "ERROR: Status field not set to Active in Wild Apricot.\n";
        exit;
    }

    $privileges = waPrivilegeNamesOfContact($waSelfContact);
    $key = array_search('Trusted', $privileges);
    if ($key === false) {
        echo "ERROR: Trusted privilege not set in Wild Apricot.\n";
        exit;
    }
}

function waInit() {
    global $waApiClient;
    global $waApiKey;
    global $waSelfWaId;
    global $waSelfContact;

    waGetSelfWaId();

    $waApiClient = WaApiClient::getInstance();
    $waApiClient->initTokenByApiKey($waApiKey);
    waGetAccountDetails();
    $waSelfContact = waGetContact($waSelfWaId);
    waCheckSelfIsTrusted();
}
?>
