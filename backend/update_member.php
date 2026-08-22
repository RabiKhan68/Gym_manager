<?php

session_start();

require_once "db.php";

if (!isset($_SESSION["owner_id"])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../members.php");
    exit();
}

$owner_id = $_SESSION["owner_id"];

$member_id = intval($_POST["member_id"]);
$name = trim($_POST["name"]);
$phone = trim($_POST["phone"]);
$email = trim($_POST["email"]);
$joining_date = $_POST["joining_date"];

$sql = "UPDATE members m
        JOIN gyms g
            ON m.gym_id = g.gym_id
        SET
            m.name = ?,
            m.phone = ?,
            m.email = ?,
            m.joining_date = ?
        WHERE m.member_id = ?
        AND g.owner_id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ssssii",
    $name,
    $phone,
    $email,
    $joining_date,
    $member_id,
    $owner_id
);

if ($stmt->execute()) {

    header("Location: ../members.php");
    exit();

} else {

    echo "Error: " . $stmt->error;

}

$stmt->close();
$conn->close();

?>