<?php

require_once "db.php";

/*
    Mark memberships as expired
    when their end date has passed.
*/

$sql = "UPDATE member_memberships
        SET status = 'expired'
        WHERE end_date < CURDATE()
        AND status = 'active'";

if (!$conn->query($sql)) {
    die("Error updating memberships: " . $conn->error);
}

?>