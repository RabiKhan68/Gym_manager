<?php

session_start();

require_once "db.php";

if (!isset($_SESSION["owner_id"])) {
    header("Location: ../login.php");
    exit();
}

if (!isset($_GET["id"])) {
    header("Location: ../members.php");
    exit();
}

$member_id = intval($_GET["id"]);
$owner_id = $_SESSION["owner_id"];


/*
    Update only if this member belongs
    to the logged-in owner's gym.
*/

$sql = "UPDATE members m
        JOIN gyms g
            ON m.gym_id = g.gym_id
        SET m.status = 'inactive'
        WHERE m.member_id = ?
        AND g.owner_id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ii",
    $member_id,
    $owner_id
);

$stmt->execute();

$stmt->close();
$conn->close();

header("Location: ../members.php");
exit();

?>