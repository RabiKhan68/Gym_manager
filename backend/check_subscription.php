<?php

date_default_timezone_set("Asia/Karachi");


/*
|--------------------------------------------------------------------------
| OWNER SUBSCRIPTION CHECK
|--------------------------------------------------------------------------
|
| Protects owner-only management pages.
|
| Requirements:
|
| - Owner must be logged in.
| - Owner must have an active subscription.
| - Subscription must have started.
| - Subscription must not have expired.
|
| If no valid subscription exists:
|
|     Redirect to my_subscription.php
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| SESSION
|--------------------------------------------------------------------------
*/

if (session_status() === PHP_SESSION_NONE) {

    session_start();

}


/*
|--------------------------------------------------------------------------
| OWNER LOGIN
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
| DATABASE
|--------------------------------------------------------------------------
*/

require_once __DIR__ . "/db.php";


/*
|--------------------------------------------------------------------------
| TODAY
|--------------------------------------------------------------------------
*/

$today =
    date("Y-m-d");


/*
|--------------------------------------------------------------------------
| FIND CURRENT VALID SUBSCRIPTION
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| We do NOT simply select the latest subscription.
|
| We specifically search for a subscription that is:
|
|     status = active
|     start_date <= today
|     end_date >= today
|
| This means a scheduled future subscription will NOT
| incorrectly lock the owner while the current subscription
| is still active.
|
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        s.subscription_id,
        s.subscription_plan_id,
        s.start_date,
        s.end_date,
        s.status,

        sp.plan_name,
        sp.price,
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
        "Subscription check failed: " .
        htmlspecialchars(
            $conn->error
        )
    );

}


$stmt->bind_param(
    "iss",
    $owner_id,
    $today,
    $today
);


if (!$stmt->execute()) {

    $stmt->close();

    die(
        "Subscription check failed."
    );

}


$result =
    $stmt->get_result();


$subscription =
    $result->fetch_assoc();


$stmt->close();


/*
|--------------------------------------------------------------------------
| NO VALID SUBSCRIPTION
|--------------------------------------------------------------------------
*/

if (!$subscription) {

    header(
        "Location: my_subscription.php?subscription=required"
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| VALID SUBSCRIPTION
|--------------------------------------------------------------------------
|
| The protected page continues normally.
|
|--------------------------------------------------------------------------
*/