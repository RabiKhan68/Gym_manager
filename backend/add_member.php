<?php

session_start();

require_once "db.php";

if (!isset($_SESSION["owner_id"])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../add_member.php");
    exit();
}

$owner_id = $_SESSION["owner_id"];

$name = trim($_POST["name"]);
$phone = trim($_POST["phone"]);
$email = trim($_POST["email"]);
$joining_date = $_POST["joining_date"];


/*
    Find the gym belonging to this owner
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
    die("Gym not found.");
}

$gym_id = $gym["gym_id"];


/*
    Insert member
*/

$sql = "INSERT INTO members
        (gym_id, name, phone, email, joining_date)
        VALUES (?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "issss",
    $gym_id,
    $name,
    $phone,
    $email,
    $joining_date
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