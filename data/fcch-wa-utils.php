<?php
// Stephen Warren wrote this 2024/11/10

require_once(dirname(__FILE__) . '/fcch-restrict-access.php');
require_once(dirname(__FILE__) . '/WaApi.php');
require_once('/home/u930-v2vbn3xb6dhb/.wa/api.php');

function array_get_or_default($array, $key, $default=null) {
    if (!isset($array[$key])) {
        return $default;
    }
    return $array[$key];
}

function waGetSelfWaId() {
    global $wpdb;
    global $waSelfIsAdmin;
    global $waSelfWaId;

    $wpUser = wp_get_current_user();
    $wpRoles = $wpUser->roles;
    if (in_array("administrator", $wpRoles)) {
        $waSelfIsAdmin = true;
        $waSelfWaId = null;
        return;
    }
    $waSelfIsAdmin = false;

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
        echo "ERROR: Could not query user WA ID\n";
        exit;
    }
    $waSelfWaId = $results[0]->meta_value;
    if (!$waSelfWaId) {
        echo "ERROR: Could not query user WA ID\n";
        exit;
    }
}

function waGetAccountDetails() {
    global $waApiClient;
    global $waAccountUrl;

    $url = 'https://api.wildapricot.org/v2.2/Accounts/';
    $response = $waApiClient->makeRequest($url); 
    $waAccountUrl = $response[0]['Url'];
}

function waGetContact($userWaId) {
    global $waApiClient;
    global $waAccountUrl;

    $url = $waAccountUrl . '/contacts/' . $userWaId;
    return $waApiClient->makeRequest($url);
}

function waGetContacts($filter) {
    global $waApiClient;
    global $waAccountUrl;

    $queryParams = array(
        '$async' => 'false',
        '$filter' => $filter
    );
    $url = $waAccountUrl . '/contacts/?' . http_build_query($queryParams);
    return $waApiClient->makeRequest($url)['Contacts'];
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

function waInit() {
    global $waApiClient;
    global $waApiKey;
    global $waSelfIsAdmin;
    global $waSelfWaId;
    global $waSelfContact;

    waGetSelfWaId();

    $waApiClient = WaApiClient::getInstance();
    $waApiClient->initTokenByApiKey($waApiKey);
    waGetAccountDetails();

    if ($waSelfIsAdmin) {
        $waSelfContact = null;
    } else {
        $waSelfContact = waGetContact($waSelfWaId);
    }
}
?>
