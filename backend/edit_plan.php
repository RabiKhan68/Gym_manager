<?php

session_start();

require_once "db.php";


/*
|--------------------------------------------------------------------------
| Check login
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["owner_id"])) {

    header("Location: ../login.php");

    exit();

}


$owner_id = $_SESSION["owner_id"];


/*
|--------------------------------------------------------------------------
| Only POST requests
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    header("Location: ../plans.php");

    exit();

}


/*
|--------------------------------------------------------------------------
| Get submitted data
|--------------------------------------------------------------------------
*/

$plan_id = intval($_POST["plan_id"] ?? 0);

$plan_name = trim(
    $_POST["plan_name"] ?? ""
);

$price = floatval(
    $_POST["price"] ?? 0
);

$duration_months = intval(
    $_POST["duration_months"] ?? 0
);

$description = trim(
    $_POST["description"] ?? ""
);


/*
|--------------------------------------------------------------------------
| Basic validation
|--------------------------------------------------------------------------
*/

if ($plan_id <= 0) {

    die("Invalid membership plan.");

}


if ($plan_name === "") {

    die("Plan name is required.");

}


if ($price < 0) {

    die("Price cannot be negative.");

}


if ($duration_months < 1) {

    die("Duration must be at least 1 month.");

}


/*
|--------------------------------------------------------------------------
| Verify that the plan belongs to
| the logged-in owner's gym
|--------------------------------------------------------------------------
*/

$sql = "SELECT mp.plan_id

        FROM membership_plans mp

        JOIN gyms g
            ON mp.gym_id = g.gym_id

        WHERE mp.plan_id = ?

        AND g.owner_id = ?";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ii",
    $plan_id,
    $owner_id
);

$stmt->execute();

$result = $stmt->get_result();


if ($result->num_rows !== 1) {

    die("Membership plan not found.");

}


/*
|--------------------------------------------------------------------------
| Update plan
|--------------------------------------------------------------------------
*/

$sql = "UPDATE membership_plans

        SET
            plan_name = ?,
            price = ?,
            duration_months = ?,
            description = ?

        WHERE plan_id = ?";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "sdisi",
    $plan_name,
    $price,
    $duration_months,
    $description,
    $plan_id
);


if ($stmt->execute()) {

    header("Location: ../plans.php");

    exit();

} else {

    echo "Error updating plan: "
         . $stmt->error;

}


$stmt->close();

$conn->close();

?>