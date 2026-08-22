<?php

session_start();

require_once "db.php";


/*
|--------------------------------------------------------------------------
| Check owner login
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["owner_id"])) {

    header("Location: ../login.php");

    exit();

}


$owner_id = $_SESSION["owner_id"];


/*
|--------------------------------------------------------------------------
| Check member ID
|--------------------------------------------------------------------------
*/

if (!isset($_GET["id"])) {

    die("Member ID is required.");

}

$member_id = (int) $_GET["id"];


/*
|--------------------------------------------------------------------------
| Find the member and make sure
| he/she belongs to this owner's gym
|--------------------------------------------------------------------------
*/

$sql = "UPDATE members m

        JOIN gyms g
            ON m.gym_id = g.gym_id

        SET m.status = 'active'

        WHERE m.member_id = ?

        AND g.owner_id = ?";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ii",
    $member_id,
    $owner_id
);

$stmt->execute();


/*
|--------------------------------------------------------------------------
| Go back to members page
|--------------------------------------------------------------------------
*/

header("Location: ../members.php");

exit();

?>