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
| This file DOES NOT:
|
| - calculate subscription dates
| - create subscriptions
| - create payments
| - verify payments
| - modify subscription prices
|
| Subscription dates are created when the subscription is successfully
| created after payment verification.
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
| EXPIRE OLD ACTIVE SUBSCRIPTIONS
|--------------------------------------------------------------------------
|
| An active subscription remains active through its end_date.
|
| Example:
|
| start_date = 24 Aug
| end_date   = 23 Sep
|
| 23 Sep -> active
| 24 Sep -> expired
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
| FIND SCHEDULED SUBSCRIPTIONS
|--------------------------------------------------------------------------
|
| Only subscriptions whose start_date has arrived are considered.
|
| Example:
|
| start_date = 24 Sep
|
| 23 Sep -> scheduled
| 24 Sep -> eligible for activation
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

        ORDER BY
            s.start_date ASC,
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
| PROCESS EACH SCHEDULED SUBSCRIPTION
|--------------------------------------------------------------------------
*/

foreach (
    $scheduled_subscriptions
    as $scheduled
) {

    $processed++;


    $subscription_id =
        (int)
        $scheduled[
            "subscription_id"
        ];


    /*
    |--------------------------------------------------------------------------
    | Start transaction
    |--------------------------------------------------------------------------
    */

    $conn->begin_transaction();


    try {

        /*
        |--------------------------------------------------------------------------
        | STEP 3A
        |--------------------------------------------------------------------------
        | LOCK THE SCHEDULED SUBSCRIPTION
        |--------------------------------------------------------------------------
        |
        | This prevents two simultaneous cron requests from activating
        | the same subscription.
        |
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
        | Subscription was already processed
        |--------------------------------------------------------------------------
        */

        if (!$locked_scheduled) {

            $conn->commit();

            $skipped++;

            continue;

        }


        /*
        |--------------------------------------------------------------------------
        | Get owner ID from locked database record
        |--------------------------------------------------------------------------
        */

        $owner_id =
            (int)
            $locked_scheduled[
                "owner_id"
            ];


        $plan_id =
            (int)
            $locked_scheduled[
                "subscription_plan_id"
            ];


        /*
        |--------------------------------------------------------------------------
        | STEP 3B
        |--------------------------------------------------------------------------
        | LOCK ANY CURRENT ACTIVE SUBSCRIPTION
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

            ORDER BY
                end_date DESC,
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
        | STEP 3C
        |--------------------------------------------------------------------------
        | SAFETY CHECK
        |--------------------------------------------------------------------------
        |
        | If the owner still has a valid active subscription,
        | do NOT activate the scheduled one early.
        |
        */

        if ($active_subscription) {

            $active_end_date =
                new DateTime(
                    $active_subscription[
                        "end_date"
                    ]
                );


            $today_date =
                new DateTime($today);


            /*
            |--------------------------------------------------------------------------
            | Current subscription is still valid.
            |--------------------------------------------------------------------------
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
            | Current subscription has expired.
            |--------------------------------------------------------------------------
            |
            | Mark it expired inside this transaction.
            |
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
        | STEP 3D
        |--------------------------------------------------------------------------
        | VERIFY SUBSCRIPTION PLAN
        |--------------------------------------------------------------------------
        |
        | The plan must still exist.
        |
        */

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
        | Plan does not exist
        |--------------------------------------------------------------------------
        */

        if (!$plan) {

            throw new Exception(
                "The scheduled subscription plan no longer exists."
            );

        }


        /*
        |--------------------------------------------------------------------------
        | STEP 3E
        |--------------------------------------------------------------------------
        | RE-CHECK MEMBER COUNT
        |--------------------------------------------------------------------------
        |
        | The owner may have added members after purchasing/scheduling
        | the subscription.
        |
        */

        $sql = "
            SELECT
                COUNT(*) AS total

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
        | STEP 3F
        |--------------------------------------------------------------------------
        | MEMBER LIMIT VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            $plan["member_limit"] !== null
        ) {

            $member_limit =
                (int)
                $plan[
                    "member_limit"
                ];


            if (
                $total_members >
                $member_limit
            ) {

                /*
                |--------------------------------------------------------------------------
                | IMPORTANT
                |--------------------------------------------------------------------------
                |
                | Do not activate the scheduled subscription.
                |
                | Throwing an exception causes the transaction to rollback.
                |
                | Therefore:
                |
                | old subscription stays unchanged
                | scheduled subscription stays scheduled
                |
                */

                throw new Exception(
                    "The scheduled " .
                    $plan["plan_name"] .
                    " plan supports only " .
                    number_format($member_limit) .
                    " members, but this gym currently has " .
                    number_format($total_members) .
                    " members."
                );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | STEP 3G
        |--------------------------------------------------------------------------
        | FINAL DATE SAFETY CHECK
        |--------------------------------------------------------------------------
        |
        | The cron should never activate a malformed subscription.
        |
        */

        $start_date =
            $locked_scheduled[
                "start_date"
            ];


        $end_date =
            $locked_scheduled[
                "end_date"
            ];


        if (
            empty($start_date) ||
            empty($end_date)
        ) {

            throw new Exception(
                "Scheduled subscription has invalid dates."
            );

        }


        if (
            $end_date <
            $start_date
        ) {

            throw new Exception(
                "Scheduled subscription has an invalid date range."
            );

        }


        /*
        |--------------------------------------------------------------------------
        | STEP 3H
        |--------------------------------------------------------------------------
        | ACTIVATE SUBSCRIPTION
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | We only change status.
        |
        | We DO NOT change:
        |
        | start_date
        | end_date
        |
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
        | Confirm activation
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
        | ROLLBACK
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

http_response_code(200);


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