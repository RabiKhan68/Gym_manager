<?php

session_start();

require_once "db.php";

if (!isset($_SESSION["owner_id"])) {
    header("Location: ../login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../add_plan.php");
    exit();
}

$owner_id = $_SESSION["owner_id"];

$plan_name = trim($_POST["plan_name"]);
$price = floatval($_POST["price"]);
$duration_months = intval($_POST["duration_months"]);
$description = trim($_POST["description"]);

/*
    Find owner's gym
*/

$sql = "SELECT gym_id
        FROM gyms
        WHERE owner_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $owner_id);
$stmt->execute();

$result = $stmt->get_result();
$gym = $result->fetch_assoc();

if (!$gym) {
    die("Gym not found.");
}

$gym_id = $gym["gym_id"];

/*
    Create plan
*/

$sql = "INSERT INTO membership_plans
        (gym_id, plan_name, price, duration_months, description)
        VALUES (?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "isdis",
    $gym_id,
    $plan_name,
    $price,
    $duration_months,
    $description
);

if ($stmt->execute()) {

    header("Location: ../plans.php");
    exit();

} else {

    echo "Error: " . $stmt->error;

}

$stmt->close();
$conn->close();

?>