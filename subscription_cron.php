<?php

/*
|--------------------------------------------------------------------------
| SUBSCRIPTION CRON / AUTO-ACTIVATION
|--------------------------------------------------------------------------
|
| This file is intended to be called automatically by UptimeRobot.
|
| Example:
|
| https://your-domain.com/subscription_cron.php
|
| It performs two main tasks:
|
| 1. Expire active subscriptions whose end_date has passed.
|
| 2. Activate scheduled subscriptions whose start_date has arrived.
|
|--------------------------------------------------------------------------
*/

require_once "backend/db.php";


/*
|--------------------------------------------------------------------------
| RESPONSE TYPE
|--------------------------------------------------------------------------
*/

header("Content-Type: text/plain; charset=UTF-8");


/*
|--------------------------------------------------------------------------
| BASIC CONFIGURATION
|--------------------------------------------------------------------------
*/

$today = date("Y-m-d");

$processed = 0;
$expired = 0;
$activated = 0;
$skipped = 0;
$errors = [];


/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

function cronLog($message)
{
    echo $message . PHP_EOL;
}


/*
|--------------------------------------------------------------------------
| DATABASE CHECK
|--------------------------------------------------------------------------
*/

if (!isset($conn) || !$conn) {

    http_response_code(500);

    cronLog("ERROR: Database connection is unavailable.");

    exit();

}


/*
|--------------------------------------------------------------------------
| START TRANSACTION
|--------------------------------------------------------------------------
|
| We process subscription changes safely inside transactions.
|
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | STEP 1
    |--------------------------------------------------------------------------
    | Expire subscriptions whose end date has already passed.
    |
    | Example:
    |
    | end_date = 31 Aug
    | today    = 01 Sep
    |
    | active -> expired
    |
    |--------------------------------------------------------------------------
    */

    $sql = "
        UPDATE gym_owner_subscriptions

        SET status = 'expired'

        WHERE status = 'active'

        AND end_date < ?
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {

        throw new Exception(
            "Unable to prepare subscription expiration query."
        );

    }

    $stmt->bind_param(
        "s",
        $today
    );

    if (!$stmt->execute()) {

        throw new Exception(
            "Unable to expire old subscriptions."
        );

    }

    $expired = $stmt->affected_rows;

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | STEP 2
    |--------------------------------------------------------------------------
    | Find scheduled subscriptions whose start date has arrived.
    |
    | Example:
    |
    | start_date = 01 Sep
    | today      = 01 Sep
    |
    | scheduled -> active
    |
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT

            s.subscription_id,
            s.owner_id,
            s.subscription_plan_id,
            s.start_date,
            s.end_date,

            sp.plan_name,
            sp.member_limit

        FROM gym_owner_subscriptions s

        INNER JOIN subscription_plans sp
            ON s.subscription_plan_id =
               sp.subscription_plan_id

        WHERE s.status = 'scheduled'

        AND s.start_date <= ?

        ORDER BY s.start_date ASC,
                 s.subscription_id ASC
    ";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {

        throw new Exception(
            "Unable to prepare scheduled subscription query."
        );

    }

    $stmt->bind_param(
        "s",
        $today
    );

    if (!$stmt->execute()) {

        throw new Exception(
            "Unable to find scheduled subscriptions."
        );

    }

    $result = $stmt->get_result();

    $scheduled_subscriptions = [];

    while (
        $row = $result->fetch_assoc()
    ) {

        $scheduled_subscriptions[] = $row;

    }

    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | STEP 3
    |--------------------------------------------------------------------------
    | Process each scheduled subscription.
    |--------------------------------------------------------------------------
    */

    foreach (
        $scheduled_subscriptions
        as $scheduled
    ) {

        $processed++;

        $subscription_id =
            (int)
            $scheduled["subscription_id"];

        $owner_id =
            (int)
            $scheduled["owner_id"];


        /*
        |--------------------------------------------------------------------------
        | Start a transaction for this owner.
        |--------------------------------------------------------------------------
        */

        $conn->begin_transaction();

        try {

            /*
            |--------------------------------------------------------------------------
            | LOCK THE SCHEDULED SUBSCRIPTION
            |--------------------------------------------------------------------------
            |
            | Another request could theoretically process the same subscription
            | at the same time.
            |
            |--------------------------------------------------------------------------
            */

            $sql = "
                SELECT

                    s.subscription_id,
                    s.owner_id,
                    s.subscription_plan_id,
                    s.start_date,
                    s.end_date,
                    s.status

                FROM gym_owner_subscriptions s

                WHERE s.subscription_id = ?

                AND s.status = 'scheduled'

                AND s.start_date <= ?

                FOR UPDATE
            ";

            $lock_stmt =
                $conn->prepare($sql);

            if (!$lock_stmt) {

                throw new Exception(
                    "Unable to lock scheduled subscription."
                );

            }

            $lock_stmt->bind_param(
                "is",
                $subscription_id,
                $today
            );

            $lock_stmt->execute();

            $lock_result =
                $lock_stmt->get_result();

            $locked_scheduled =
                $lock_result->fetch_assoc();

            $lock_stmt->close();


            /*
            |--------------------------------------------------------------------------
            | It may have already been processed.
            |--------------------------------------------------------------------------
            */

            if (!$locked_scheduled) {

                $conn->commit();

                $skipped++;

                continue;

            }


            /*
            |--------------------------------------------------------------------------
            | LOCK CURRENT ACTIVE SUBSCRIPTION
            |--------------------------------------------------------------------------
            |
            | We check whether this owner still has an active subscription.
            |--------------------------------------------------------------------------
            */

            $sql = "
                SELECT

                    subscription_id,
                    subscription_plan_id,
                    start_date,
                    end_date,
                    status

                FROM gym_owner_subscriptions

                WHERE owner_id = ?

                AND status = 'active'

                ORDER BY end_date DESC,
                         subscription_id DESC

                LIMIT 1

                FOR UPDATE
            ";

            $active_stmt =
                $conn->prepare($sql);

            if (!$active_stmt) {

                throw new Exception(
                    "Unable to check current active subscription."
                );

            }

            $active_stmt->bind_param(
                "i",
                $owner_id
            );

            $active_stmt->execute();

            $active_result =
                $active_stmt->get_result();

            $active_subscription =
                $active_result->fetch_assoc();

            $active_stmt->close();


            /*
            |--------------------------------------------------------------------------
            | SAFETY CHECK
            |--------------------------------------------------------------------------
            |
            | Normally there should be NO active subscription at this point.
            |
            | However, if one still exists and its end date has not passed,
            | we do NOT activate the scheduled plan prematurely.
            |--------------------------------------------------------------------------
            */

            if ($active_subscription) {

                $active_end =
                    new DateTime(
                        $active_subscription["end_date"]
                    );

                $today_date =
                    new DateTime($today);


                if (
                    $active_end >= $today_date
                ) {

                    $conn->commit();

                    $skipped++;

                    continue;

                }


                /*
                |--------------------------------------------------------------------------
                | The active subscription has expired.
                |--------------------------------------------------------------------------
                */

                $sql = "
                    UPDATE gym_owner_subscriptions

                    SET status = 'expired'

                    WHERE subscription_id = ?

                    AND status = 'active'
                ";

                $expire_stmt =
                    $conn->prepare($sql);

                if (!$expire_stmt) {

                    throw new Exception(
                        "Unable to expire previous subscription."
                    );

                }

                $active_id =
                    (int)
                    $active_subscription[
                        "subscription_id"
                    ];

                $expire_stmt->bind_param(
                    "i",
                    $active_id
                );

                if (!$expire_stmt->execute()) {

                    throw new Exception(
                        "Unable to expire previous subscription."
                    );

                }

                $expire_stmt->close();

            }


            /*
            |--------------------------------------------------------------------------
            | ACTIVATE SCHEDULED SUBSCRIPTION
            |--------------------------------------------------------------------------
            */

            $sql = "
                UPDATE gym_owner_subscriptions

                SET status = 'active'

                WHERE subscription_id = ?

                AND status = 'scheduled'

                AND start_date <= ?
            ";

            $activate_stmt =
                $conn->prepare($sql);

            if (!$activate_stmt) {

                throw new Exception(
                    "Unable to prepare subscription activation."
                );

            }

            $activate_stmt->bind_param(
                "is",
                $subscription_id,
                $today
            );

            if (!$activate_stmt->execute()) {

                throw new Exception(
                    "Unable to activate scheduled subscription."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Confirm activation actually happened.
            |--------------------------------------------------------------------------
            */

            if (
                $activate_stmt->affected_rows === 1
            ) {

                $activated++;

            }

            $activate_stmt->close();


            /*
            |--------------------------------------------------------------------------
            | COMMIT OWNER TRANSACTION
            |--------------------------------------------------------------------------
            */

            $conn->commit();

        }
        catch (Exception $e) {

            $conn->rollback();

            $errors[] =
                "Subscription #" .
                $subscription_id .
                ": " .
                $e->getMessage();

        }

    }


    /*
    |--------------------------------------------------------------------------
    | SUCCESS RESPONSE
    |--------------------------------------------------------------------------
    */

    http_response_code(200);

    cronLog(
        "Subscription cron completed successfully."
    );

    cronLog(
        "Date: " .
        $today
    );

    cronLog(
        "Expired subscriptions: " .
        $expired
    );

    cronLog(
        "Scheduled subscriptions checked: " .
        $processed
    );

    cronLog(
        "Activated subscriptions: " .
        $activated
    );

    cronLog(
        "Skipped subscriptions: " .
        $skipped
    );


    /*
    |--------------------------------------------------------------------------
    | ERRORS
    |--------------------------------------------------------------------------
    */

    if (
        count($errors) > 0
    ) {

        cronLog("");

        cronLog(
            "Errors:"
        );

        foreach (
            $errors
            as $error
        ) {

            cronLog(
                "- " . $error
            );

        }

    }


}
catch (Exception $e) {

    /*
    |--------------------------------------------------------------------------
    | GLOBAL ERROR
    |--------------------------------------------------------------------------
    */

    if (
        $conn->errno
    ) {

        try {

            $conn->rollback();

        }
        catch (Exception $rollback_error) {

            // Ignore rollback errors.

        }

    }


    http_response_code(500);

    cronLog(
        "Subscription cron failed."
    );

    cronLog(
        "Error: " .
        $e->getMessage()
    );

    exit();

}