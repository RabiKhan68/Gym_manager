<?php

session_start();

require_once "db.php";

header("Content-Type: application/json");


if (!isset($_SESSION["owner_id"])) {

    echo json_encode([
        "success" => false,
        "message" => "Unauthorized"
    ]);

    exit();
}


$owner_id = $_SESSION["owner_id"];


/*
|--------------------------------------------------------------------------
| Get gym
|--------------------------------------------------------------------------
*/

$sql = "SELECT gym_id
        FROM gyms
        WHERE owner_id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $owner_id
);

$stmt->execute();

$result = $stmt->get_result();

$gym = $result->fetch_assoc();


if (!$gym) {

    echo json_encode([
        "success" => false,
        "message" => "Gym not found"
    ]);

    exit();
}


$gym_id = $gym["gym_id"];

$today = date("Y-m-d");


/*
|--------------------------------------------------------------------------
| Get today's attendance
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            m.name AS member_name,
            a.attendance_time

        FROM attendance a

        JOIN members m
            ON a.member_id = m.member_id

        WHERE m.gym_id = ?
        AND a.attendance_date = ?

        ORDER BY a.attendance_time DESC";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "is",
    $gym_id,
    $today
);

$stmt->execute();

$result = $stmt->get_result();


$attendance = [];


while ($row = $result->fetch_assoc()) {

    $attendance[] = [

        "name" => $row["member_name"],

        "time" => date(
            "h:i A",
            strtotime($row["attendance_time"])
        )

    ];

}


echo json_encode([

    "success" => true,

    "total" => count($attendance),

    "attendance" => $attendance

]);

?>