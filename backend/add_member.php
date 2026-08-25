<?php

session_start();

require_once "db.php";


/*
|--------------------------------------------------------------------------
| OWNER LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["owner_id"])) {

    header("Location: ../login.php");

    exit();

}


$owner_id =
    (int) $_SESSION["owner_id"];


/*
|--------------------------------------------------------------------------
| REQUEST METHOD
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] !== "POST"
) {

    header("Location: ../add_member.php");

    exit();

}


/*
|--------------------------------------------------------------------------
| SUBSCRIPTION CHECK
|--------------------------------------------------------------------------
|
| This is the server-side protection.
|
| Even if someone bypasses add_member.php and directly sends
| a POST request to this file, they cannot add a member unless
| they have a currently active subscription.
|
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/check_subscription.php";


/*
|--------------------------------------------------------------------------
| GET OWNER'S GYM
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        gym_id,
        gym_name

    FROM gyms

    WHERE owner_id = ?

    LIMIT 1
";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    die(
        "Database error: " .
        htmlspecialchars(
            $conn->error
        )
    );

}


$stmt->bind_param(
    "i",
    $owner_id
);


if (!$stmt->execute()) {

    $stmt->close();

    die(
        "Unable to find your gym."
    );

}


$result =
    $stmt->get_result();


$gym =
    $result->fetch_assoc();


$stmt->close();


if (!$gym) {

    die(
        "Gym not found."
    );

}


$gym_id =
    (int) $gym["gym_id"];


/*
|--------------------------------------------------------------------------
| GET CURRENT SUBSCRIPTION AND MEMBER LIMIT
|--------------------------------------------------------------------------
|
| check_subscription.php has already confirmed:
|
| - subscription exists
| - status = active
| - start_date <= today
| - end_date >= today
|
| We now retrieve the member limit of that same valid subscription.
|
| NULL member_limit = unlimited.
|
|--------------------------------------------------------------------------
*/

$member_limit = null;


$sql = "
    SELECT
        s.subscription_id,
        sp.member_limit

    FROM gym_owner_subscriptions s

    INNER JOIN subscription_plans sp
        ON s.subscription_plan_id =
           sp.subscription_plan_id

    WHERE s.owner_id = ?

    AND s.status = 'active'

    AND s.start_date <= ?

    AND s.end_date >= ?

    ORDER BY
        s.end_date DESC,
        s.subscription_id DESC

    LIMIT 1
";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    die(
        "Unable to check subscription limit: " .
        htmlspecialchars(
            $conn->error
        )
    );

}


$today =
    date("Y-m-d");


$stmt->bind_param(
    "iss",
    $owner_id,
    $today,
    $today
);


if (!$stmt->execute()) {

    $stmt->close();

    die(
        "Unable to verify subscription."
    );

}


$result =
    $stmt->get_result();


$subscription =
    $result->fetch_assoc();


$stmt->close();


/*
|--------------------------------------------------------------------------
| SAFETY CHECK
|--------------------------------------------------------------------------
|
| This should normally never happen because check_subscription.php
| has already verified the subscription.
|
|--------------------------------------------------------------------------
*/

if (!$subscription) {

    header(
        "Location: ../my_subscription.php?subscription=required"
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| MEMBER LIMIT
|--------------------------------------------------------------------------
*/

if (
    $subscription["member_limit"] !== null
) {

    $member_limit =
        (int)
        $subscription["member_limit"];

}


/*
|--------------------------------------------------------------------------
| COUNT CURRENT MEMBERS
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        COUNT(*) AS total

    FROM members

    WHERE gym_id = ?
";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    die(
        "Unable to check member count: " .
        htmlspecialchars(
            $conn->error
        )
    );

}


$stmt->bind_param(
    "i",
    $gym_id
);


if (!$stmt->execute()) {

    $stmt->close();

    die(
        "Unable to check current members."
    );

}


$result =
    $stmt->get_result();


$row =
    $result->fetch_assoc();


$stmt->close();


$current_members =
    (int)
    (
        $row["total"] ?? 0
    );


/*
|--------------------------------------------------------------------------
| MEMBER LIMIT ENFORCEMENT
|--------------------------------------------------------------------------
|
| NULL = unlimited.
|
| Otherwise:
|
| current members >= plan limit
|
| means another member cannot be added.
|
|--------------------------------------------------------------------------
*/

if (
    $member_limit !== null
    &&
    $current_members >= $member_limit
) {

    $_SESSION["add_member_error"] =
        "Your current subscription allows a maximum of " .
        number_format($member_limit) .
        " members. Your gym currently has " .
        number_format($current_members) .
        " members. Please upgrade your subscription to add more members.";


    header(
        "Location: ../add_member.php"
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| GET FORM DATA
|--------------------------------------------------------------------------
*/

$name =
    trim(
        $_POST["name"] ?? ""
    );


$phone =
    trim(
        $_POST["phone"] ?? ""
    );


$email =
    trim(
        $_POST["email"] ?? ""
    );


$joining_date =
    trim(
        $_POST["joining_date"] ?? ""
    );


/*
|--------------------------------------------------------------------------
| BASIC VALIDATION
|--------------------------------------------------------------------------
*/

if ($name === "") {

    $_SESSION["add_member_error"] =
        "Member name is required.";


    header(
        "Location: ../add_member.php"
    );

    exit();

}


if ($joining_date === "") {

    $_SESSION["add_member_error"] =
        "Joining date is required.";


    header(
        "Location: ../add_member.php"
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| VALIDATE JOINING DATE
|--------------------------------------------------------------------------
*/

$date_object =
    DateTime::createFromFormat(
        "Y-m-d",
        $joining_date
    );


$date_errors =
    DateTime::getLastErrors();


if (
    $date_object === false
    ||
    (
        $date_errors !== false
        &&
        (
            $date_errors["warning_count"] > 0
            ||
            $date_errors["error_count"] > 0
        )
    )
) {

    $_SESSION["add_member_error"] =
        "Please enter a valid joining date.";


    header(
        "Location: ../add_member.php"
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| INSERT MEMBER
|--------------------------------------------------------------------------
*/

$sql = "
    INSERT INTO members
    (
        gym_id,
        name,
        phone,
        email,
        joining_date
    )

    VALUES
    (?, ?, ?, ?, ?)
";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    die(
        "Database error: " .
        htmlspecialchars(
            $conn->error
        )
    );

}


$stmt->bind_param(
    "issss",
    $gym_id,
    $name,
    $phone,
    $email,
    $joining_date
);


/*
|--------------------------------------------------------------------------
| EXECUTE INSERT
|--------------------------------------------------------------------------
*/

if ($stmt->execute()) {

    $stmt->close();

    $conn->close();


    header(
        "Location: ../members.php"
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| INSERT FAILED
|--------------------------------------------------------------------------
*/

$error =
    $stmt->error;


$stmt->close();

$conn->close();


echo "Error: " .
    htmlspecialchars(
        $error
    );

?>