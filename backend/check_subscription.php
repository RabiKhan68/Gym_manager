<?php

/*
|--------------------------------------------------------------------------
| OWNER SUBSCRIPTION CHECK
|--------------------------------------------------------------------------
|
| This file protects owner-only management pages.
|
| Requirements:
|
| - Owner must be logged in.
| - Owner must have a subscription.
| - Subscription must be active.
| - Today's date must be between start_date and end_date.
|
| If the subscription is not valid, the owner is redirected to
| my_subscription.php.
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| Make sure session exists
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {

    session_start();

}


/*
|--------------------------------------------------------------------------
| Owner login check
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["owner_id"])) {

    header("Location: login.php");

    exit();

}


$owner_id =
    (int) $_SESSION["owner_id"];


/*
|--------------------------------------------------------------------------
| Database connection
|--------------------------------------------------------------------------
|
| This file is inside:
|
| backend/check_subscription.php
|
| db.php is in the same directory.
|
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/db.php";


/*
|--------------------------------------------------------------------------
| Pakistan timezone
|--------------------------------------------------------------------------
*/

date_default_timezone_set(
    "Asia/Karachi"
);


/*
|--------------------------------------------------------------------------
| Today's date
|--------------------------------------------------------------------------
*/

$today =
    date("Y-m-d");


/*
|--------------------------------------------------------------------------
| Find owner's latest subscription
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        s.subscription_id,
        s.subscription_plan_id,
        s.start_date,
        s.end_date,
        s.status,

        sp.plan_name

    FROM gym_owner_subscriptions s

    INNER JOIN subscription_plans sp
        ON s.subscription_plan_id =
           sp.subscription_plan_id

    WHERE s.owner_id = ?

    ORDER BY
        s.subscription_id DESC

    LIMIT 1
";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    die(
        "Subscription check failed: " .
        htmlspecialchars(
            $conn->error
        )
    );

}


$stmt->bind_param(
    "i",
    $owner_id
);


$stmt->execute();


$result =
    $stmt->get_result();


$subscription =
    $result->fetch_assoc();


$stmt->close();


/*
|--------------------------------------------------------------------------
| No subscription
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
| Subscription information
|--------------------------------------------------------------------------
*/

$status =
    strtolower(
        trim(
            (string)
            $subscription["status"]
        )
    );


$start_date =
    $subscription["start_date"];


$end_date =
    $subscription["end_date"];


/*
|--------------------------------------------------------------------------
| Check dates
|--------------------------------------------------------------------------
*/

$subscription_valid = (

    $status === "active"

    &&

    !empty($start_date)

    &&

    !empty($end_date)

    &&

    $start_date <= $today

    &&

    $end_date >= $today

);


/*
|--------------------------------------------------------------------------
| Subscription is NOT valid
|--------------------------------------------------------------------------
*/

if (!$subscription_valid) {

    header(
        "Location: ../my_subscription.php?subscription=required"
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| Subscription is valid
|--------------------------------------------------------------------------
|
| Nothing happens here.
|
| The protected page continues normally.
|
|--------------------------------------------------------------------------
*/