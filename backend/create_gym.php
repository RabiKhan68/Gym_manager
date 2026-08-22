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

    header("Location: ../create_gym.php");
    exit();

}


/*
|--------------------------------------------------------------------------
| Get form data
|--------------------------------------------------------------------------
*/

$gym_name = trim(
    $_POST["gym_name"] ?? ""
);

$address = trim(
    $_POST["address"] ?? ""
);


/*
|--------------------------------------------------------------------------
| Validate
|--------------------------------------------------------------------------
*/

if ($gym_name === "") {

    die("Gym name is required.");

}


/*
|--------------------------------------------------------------------------
| Get owner's phone
|--------------------------------------------------------------------------
*/

$sql = "SELECT phone
        FROM gym_owners
        WHERE owner_id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $owner_id
);

$stmt->execute();

$result = $stmt->get_result();

$owner = $result->fetch_assoc();


if (!$owner) {

    die("Owner account not found.");

}


$phone = $owner["phone"];


/*
|--------------------------------------------------------------------------
| Prevent duplicate gym
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


if ($result->num_rows > 0) {

    header("Location: ../dashboard.php");
    exit();

}


/*
|--------------------------------------------------------------------------
| Create gym
|--------------------------------------------------------------------------
*/

$sql = "INSERT INTO gyms
        (
            owner_id,
            gym_name,
            address,
            phone
        )

        VALUES (?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "isss",
    $owner_id,
    $gym_name,
    $address,
    $phone
);


if (!$stmt->execute()) {

    die(
        "Could not create gym: " .
        $stmt->error
    );

}


/*
|--------------------------------------------------------------------------
| Success
|--------------------------------------------------------------------------
*/

header("Location: ../dashboard.php");

exit();

?>