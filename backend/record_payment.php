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

    header("Location: ../payments.php");

    exit();

}


/*
|--------------------------------------------------------------------------
| Get submitted data
|--------------------------------------------------------------------------
*/

$member_id = isset($_POST["member_id"])
    ? (int) $_POST["member_id"]
    : 0;

$membership_id = isset($_POST["membership_id"])
    ? (int) $_POST["membership_id"]
    : 0;

$amount = isset($_POST["amount"])
    ? (float) $_POST["amount"]
    : 0;

$payment_method =
    $_POST["payment_method"] ?? "";


/*
|--------------------------------------------------------------------------
| Basic validation
|--------------------------------------------------------------------------
*/

if (
    $member_id <= 0 ||
    $membership_id <= 0 ||
    $amount <= 0
) {

    die("Invalid payment information.");

}


if (
    $payment_method !== "cash" &&
    $payment_method !== "online"
) {

    die("Invalid payment method.");

}


/*
|--------------------------------------------------------------------------
| Find owner's gym
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

    die("Gym not found.");

}


$gym_id = $gym["gym_id"];


/*
|--------------------------------------------------------------------------
| Verify member belongs to owner's gym
|--------------------------------------------------------------------------
*/

$sql = "SELECT member_id
        FROM members
        WHERE member_id = ?
        AND gym_id = ?
        AND status = 'active'";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ii",
    $member_id,
    $gym_id
);

$stmt->execute();

$result = $stmt->get_result();

$member = $result->fetch_assoc();


if (!$member) {

    die("Invalid member.");

}


/*
|--------------------------------------------------------------------------
| Verify membership belongs to member
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            mm.membership_id,
            mp.price

        FROM member_memberships mm

        INNER JOIN membership_plans mp
            ON mm.plan_id = mp.plan_id

        WHERE mm.membership_id = ?

        AND mm.member_id = ?

        AND mm.status = 'active'

        AND mm.start_date <= CURDATE()

        AND mm.end_date >= CURDATE()

        LIMIT 1";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ii",
    $membership_id,
    $member_id
);

$stmt->execute();

$result = $stmt->get_result();

$membership = $result->fetch_assoc();


if (!$membership) {

    die("Invalid or expired membership.");

}


/*
|--------------------------------------------------------------------------
| Current month
|--------------------------------------------------------------------------
*/

$payment_for_month = date("Y-m-01");


/*
|--------------------------------------------------------------------------
| Check if already paid
|--------------------------------------------------------------------------
*/

$sql = "SELECT payment_id
        FROM payments
        WHERE member_id = ?
        AND payment_for_month = ?
        AND payment_status = 'paid'
        LIMIT 1";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "is",
    $member_id,
    $payment_for_month
);

$stmt->execute();

$result = $stmt->get_result();

$existing_payment = $result->fetch_assoc();


if ($existing_payment) {

    die(
        "This member has already paid for " .
        date(
            "F Y",
            strtotime($payment_for_month)
        ) .
        "."
    );

}


/*
|--------------------------------------------------------------------------
| Insert payment
|--------------------------------------------------------------------------
*/

$sql = "INSERT INTO payments (

            member_id,
            membership_id,
            amount,
            payment_for_month,
            payment_method,
            payment_status

        )

        VALUES (?, ?, ?, ?, ?, 'paid')";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "iidss",
    $member_id,
    $membership_id,
    $amount,
    $payment_for_month,
    $payment_method
);


if (!$stmt->execute()) {

    die(
        "Payment could not be recorded: " .
        $stmt->error
    );

}


/*
|--------------------------------------------------------------------------
| Redirect
|--------------------------------------------------------------------------
*/

$payment_id = $conn->insert_id;


header(
    "Location: ../payment_receipt.php?id=" .
    $payment_id
);

exit();