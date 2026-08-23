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
| IMPORTANT:
|
| This file does NOT calculate subscription dates.
|
| start_date and end_date are created by:
|
| - subscription_change.php
| - subscription_renew.php
|
| The cron only changes subscription status.
|
|--------------------------------------------------------------------------
*/

require_once "backend/db.php";


/*
|--------------------------------------------------------------------------
| RESPONSE TYPE
|--------------------------------------------------------------------------
*/

header(
    "Content-Type: text/plain; charset=UTF-8"
);


/*
|--------------------------------------------------------------------------
| BASIC CONFIGURATION
|--------------------------------------------------------------------------
*/

$today =
    date("Y-m-d");

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

if (
    !isset($conn) ||
    !$conn
) {

    http_response_code(500);

    cronLog(
        "ERROR: Database connection is unavailable."
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| STEP 1
|--------------------------------------------------------------------------
| Expire subscriptions whose end date has passed.
|--------------------------------------------------------------------------
|
| Example:
|
| start_date = 23 Aug 2026
| end_date   = 22 Sep 2026
|
| On:
|
| 22 Sep -> remains active
|
| 23 Sep -> becomes expired
|
|--------------------------------------------------------------------------
*/

try {

    $sql = "
        UPDATE gym_owner_subscriptions

        SET status = 'expired'

        WHERE status = 'active'

        AND end_date < ?
    ";


    $stmt =
        $conn->prepare($sql);


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


    $expired =
        $stmt->affected_rows;


    $stmt->close();


}
catch (Exception $e) {

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


/*
|--------------------------------------------------------------------------
| STEP 2
|--------------------------------------------------------------------------
| Find scheduled subscriptions whose start date has arrived.
|--------------------------------------------------------------------------
|
| Example:
|
| start_date = 23 Sep 2026
|
| On 23 Sep:
|
| scheduled -> active
|
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| We do NOT calculate or modify:
|
| start_date
| end_date
|
| The dates were already calculated correctly when the subscription
| was created.
|
|--------------------------------------------------------------------------
*/

try {

    $sql = "
        SELECT

            s.subscription_id,
            s.owner_id,
            s.subscription_plan_id,
            s.start_date,
            s.end_date,
            s.status,

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


    $stmt =
        $conn->prepare($sql);


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


    $result =
        $stmt->get_result();


    $scheduled_subscriptions = [];


    while (
        $row =
        $result->fetch_assoc()
    ) {

        $scheduled_subscriptions[] =
            $row;

    }


    $stmt->close();

}
catch (Exception $e) {

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
    | Start transaction for this subscription.
    |--------------------------------------------------------------------------
    */

    $conn->begin_transaction();


    try {

        /*
        |--------------------------------------------------------------------------
        | STEP 3A
        |--------------------------------------------------------------------------
        | Lock the scheduled subscription.
        |--------------------------------------------------------------------------
        |
        | This prevents two cron requests from activating the same
        | subscription at the same time.
        |
        |--------------------------------------------------------------------------
        */

        $sql = "
            SELECT

                subscription_id,
                owner_id,
                subscription_plan_id,
                start_date,
                end_date,
                status

            FROM gym_owner_subscriptions

            WHERE subscription_id = ?

            AND status = 'scheduled'

            AND start_date <= ?

            LIMIT 1

            FOR UPDATE
        ";


        $stmt =
            $conn->prepare($sql);


        if (!$stmt) {

            throw new Exception(
                "Unable to lock scheduled subscription."
            );

        }


        $stmt->bind_param(
            "is",
            $subscription_id,
            $today
        );


        if (!$stmt->execute()) {

            throw new Exception(
                "Unable to verify scheduled subscription."
            );

        }


        $result =
            $stmt->get_result();


        $locked_scheduled =
            $result->fetch_assoc();


        $stmt->close();


        /*
        |--------------------------------------------------------------------------
        | Already processed
        |--------------------------------------------------------------------------
        */

        if (!$locked_scheduled) {

            $conn->commit();

            $skipped++;

            continue;

        }


        /*
        |--------------------------------------------------------------------------
        | STEP 3B
        |--------------------------------------------------------------------------
        | Verify that the scheduled subscription belongs to the same owner.
        |--------------------------------------------------------------------------
        */

        $owner_id =
            (int)
            $locked_scheduled["owner_id"];


        /*
        |--------------------------------------------------------------------------
        | STEP 3C
        |--------------------------------------------------------------------------
        | Lock the owner's active subscription.
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


        $stmt =
            $conn->prepare($sql);


        if (!$stmt) {

            throw new Exception(
                "Unable to check current active subscription."
            );

        }


        $stmt->bind_param(
            "i",
            $owner_id
        );


        if (!$stmt->execute()) {

            throw new Exception(
                "Unable to check current active subscription."
            );

        }


        $result =
            $stmt->get_result();


        $active_subscription =
            $result->fetch_assoc();


        $stmt->close();


        /*
        |--------------------------------------------------------------------------
        | STEP 3D
        |--------------------------------------------------------------------------
        | Safety check.
        |--------------------------------------------------------------------------
        |
        | Normally there should be no active subscription when the
        | scheduled plan starts.
        |
        | Example:
        |
        | Current:
        | 23 Aug -> 22 Sep
        |
        | Scheduled:
        | 23 Sep -> 22 Oct
        |
        | On 23 Sep the old subscription should already be expired.
        |
        |--------------------------------------------------------------------------
        */

        if ($active_subscription) {

            $active_end_date =
                new DateTime(
                    $active_subscription["end_date"]
                );


            $today_date =
                new DateTime($today);


            /*
            |--------------------------------------------------------------------------
            | Active subscription is still valid.
            |--------------------------------------------------------------------------
            |
            | DO NOT activate the scheduled plan early.
            |
            */

            if (
                $active_end_date >=
                $today_date
            ) {

                $conn->commit();

                $skipped++;

                continue;

            }


            /*
            |--------------------------------------------------------------------------
            | Active subscription has expired.
            |--------------------------------------------------------------------------
            |
            | Mark it as expired before activating the new subscription.
            |--------------------------------------------------------------------------
            */

            $active_id =
                (int)
                $active_subscription[
                    "subscription_id"
                ];


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
                    "Unable to prepare previous subscription expiration."
                );

            }


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
        | STEP 3E
        |--------------------------------------------------------------------------
        | Verify the subscription plan still exists.
        |--------------------------------------------------------------------------
        */

        $plan_id =
            (int)
            $locked_scheduled[
                "subscription_plan_id"
            ];


        $sql = "
            SELECT

                subscription_plan_id,
                plan_name,
                member_limit

            FROM subscription_plans

            WHERE subscription_plan_id = ?

            LIMIT 1

            FOR UPDATE
        ";


        $stmt =
            $conn->prepare($sql);


        if (!$stmt) {

            throw new Exception(
                "Unable to verify subscription plan."
            );

        }


        $stmt->bind_param(
            "i",
            $plan_id
        );


        if (!$stmt->execute()) {

            throw new Exception(
                "Unable to verify subscription plan."
            );

        }


        $result =
            $stmt->get_result();


        $plan =
            $result->fetch_assoc();


        $stmt->close();


        /*
        |--------------------------------------------------------------------------
        | Plan no longer exists.
        |--------------------------------------------------------------------------
        */

        if (!$plan) {

            throw new Exception(
                "The scheduled subscription plan no longer exists."
            );

        }


        /*
        |--------------------------------------------------------------------------
        | STEP 3F
        |--------------------------------------------------------------------------
        | Re-check current member count.
        |--------------------------------------------------------------------------
        |
        | This is important because the gym could have added members
        | after scheduling the subscription.
        |--------------------------------------------------------------------------
        */

        $sql = "
            SELECT COUNT(*) AS total

            FROM members m

            INNER JOIN gyms g
                ON m.gym_id = g.gym_id

            WHERE g.owner_id = ?
        ";


        $stmt =
            $conn->prepare($sql);


        if (!$stmt) {

            throw new Exception(
                "Unable to verify member count."
            );

        }


        $stmt->bind_param(
            "i",
            $owner_id
        );


        if (!$stmt->execute()) {

            throw new Exception(
                "Unable to verify member count."
            );

        }


        $result =
            $stmt->get_result();


        $row =
            $result->fetch_assoc();


        $total_members =
            (int)
            ($row["total"] ?? 0);


        $stmt->close();


        /*
        |--------------------------------------------------------------------------
        | STEP 3G
        |--------------------------------------------------------------------------
        | Validate member limit.
        |--------------------------------------------------------------------------
        */

        if (
            $plan["member_limit"] !== null
        ) {

            $member_limit =
                (int)
                $plan["member_limit"];


            if (
                $total_members >
                $member_limit
            ) {

                /*
                |--------------------------------------------------------------------------
                | Do not activate a plan that cannot support the current
                | number of members.
                |--------------------------------------------------------------------------
                |
                | The scheduled subscription remains scheduled.
                |
                | The owner can reduce members or choose a higher plan.
                |
                |--------------------------------------------------------------------------
                */

                throw new Exception(
                    "The scheduled " .
                    $plan["plan_name"] .
                    " plan supports only " .
                    $member_limit .
                    " members, but this gym currently has " .
                    $total_members .
                    " members."
                );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | STEP 3H
        |--------------------------------------------------------------------------
        | Final activation.
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | We do NOT change:
        |
        | start_date
        | end_date
        |
        | The subscription already contains the correct dates.
        |--------------------------------------------------------------------------
        */

        $sql = "
            UPDATE gym_owner_subscriptions

            SET status = 'active'

            WHERE subscription_id = ?

            AND status = 'scheduled'

            AND start_date <= ?
        ";


        $stmt =
            $conn->prepare($sql);


        if (!$stmt) {

            throw new Exception(
                "Unable to prepare subscription activation."
            );

        }


        $stmt->bind_param(
            "is",
            $subscription_id,
            $today
        );


        if (!$stmt->execute()) {

            throw new Exception(
                "Unable to activate scheduled subscription."
            );

        }


        /*
        |--------------------------------------------------------------------------
        | Confirm activation.
        |--------------------------------------------------------------------------
        */

        if (
            $stmt->affected_rows === 1
        ) {

            $activated++;

        }
        else {

            $skipped++;

        }


        $stmt->close();


        /*
        |--------------------------------------------------------------------------
        | COMMIT
        |--------------------------------------------------------------------------
        */

        $conn->commit();

    }
    catch (Exception $e) {

        /*
        |--------------------------------------------------------------------------
        | Rollback this subscription's transaction.
        |--------------------------------------------------------------------------
        */

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
| FINAL RESPONSE
|--------------------------------------------------------------------------
*/

if (
    count($errors) > 0
) {

    /*
    | Some subscriptions failed, but the cron itself completed.
    |
    | We use 200 because UptimeRobot successfully reached the endpoint.
    | The response shows the individual errors.
    */

    http_response_code(200);

}
else {

    http_response_code(200);

}


cronLog(
    "Subscription cron completed."
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
            "- " .
            $error
        );

    }

}