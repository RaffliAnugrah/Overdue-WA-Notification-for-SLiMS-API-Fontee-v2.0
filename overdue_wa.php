<?php

define('INDEX_AUTH', '1');

require '../../../sysconfig.inc.php';

// IP based access limitation
require LIB.'ip_based_access.inc.php';

do_checkIP('smc');
do_checkIP('smc-membership');

// Start session
require SB.'admin/default/session.inc.php';
require SB.'admin/default/session_check.inc.php';

// Privileges checking
$can_read = utility::havePrivilege('membership', 'r');

if (!$can_read) {
    die();
}

// Required library
require_once SIMBIO.'simbio_UTILS/simbio_date.inc.php';
require_once MDLBS.'membership/member_base_lib.inc.php';

// Get member ID
$memberID = $dbs->escape_string(trim($_POST['memberID'] ?? ''));

if (empty($memberID)) {
    die('<div class="alert alert-danger">Member ID kosong!</div>');
}

// Create member object
$member = new member($dbs, $memberID);

// Check member
if (!$member->valid()) {
    die('<div class="alert alert-danger">Member tidak ditemukan!</div>');
}

// Send WhatsApp overdue
$status = $member->sendOverdueNoticeWA();

// Output result
if (is_array($status)) {

    $alertType = ($status['status'] == 'SENT')
        ? 'alert-success'
        : 'alert-danger';

    echo '<div class="alert '.$alertType.'">'
        .$status['message'].
        '</div>';

} else {

    echo '<div class="alert alert-danger">'
        .$status.
        '</div>';
}
