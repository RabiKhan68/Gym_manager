<?php

session_start();

require_once "backend/db.php";


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["owner_id"])) {

    header("Location: login.php");
    exit();

}

$owner_id = (int) $_SESSION["owner_id"];

$today = date("Y-m-d");


/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}


function formatDate($date)
{
    if (!$date) {
        return "-";
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return "-";
    }

    return date("d M Y", $timestamp);
}


/*
|--------------------------------------------------------------------------
| CALCULATE MONTHLY END DATE
|--------------------------------------------------------------------------
|
| Examples:
|
| 01 Aug -> 31 Aug
| 24 Aug -> 23 Sep
| 01 Sep -> 30 Sep
| 15 Jan -> 14 Feb
|
| This calculates one calendar month minus one day.
|
*/

function calculateMonthlyEndDate($start_date)
{
    try {

        $start = new DateTime($start_date);

        /*
        |--------------------------------------------------------------------------
        | Calculate the next month's same calendar date.
        |--------------------------------------------------------------------------
        |
        | Example:
        |
        | 23 Aug 2026
        |     ↓ +1 month
        | 23 Sep 2026
        |
        */

        $next = clone $start;

        /*
        |--------------------------------------------------------------------------
        | Handle month-end dates safely.
        |--------------------------------------------------------------------------
        |
        | PHP can behave unexpectedly when adding one month to dates such as
        | January 31.
        |
        | We therefore move to the first day of the current month, then move
        | to the first day of the next month and construct the target date.
        |
        */

        $original_day =
            (int) $start->format("d");

        $next_month =
            new DateTime(
                $start->format("Y-m-01")
            );

        $next_month->modify("+1 month");

        /*
        |--------------------------------------------------------------------------
        | Find the last valid day in the target month.
        |--------------------------------------------------------------------------
        */

        $days_in_target_month =
            (int) $next_month->format("t");

        /*
        |--------------------------------------------------------------------------
        | Preserve the original day when possible.
        |
        | Example:
        |
        | 15 Aug → 15 Sep
        |
        | But:
        |
        | 31 Jan → 28/29 Feb
        |
        |--------------------------------------------------------------------------
        */

        $target_day =
            min(
                $original_day,
                $days_in_target_month
            );

        /*
        |--------------------------------------------------------------------------
        | Build the same-day date in the following month.
        |--------------------------------------------------------------------------
        */

        $target_date =
            new DateTime(
                sprintf(
                    "%04d-%02d-%02d",
                    (int) $next_month->format("Y"),
                    (int) $next_month->format("m"),
                    $target_day
                )
            );

        /*
        |--------------------------------------------------------------------------
        | Subscription ends one day BEFORE the next month's start date.
        |--------------------------------------------------------------------------
        */

        $target_date->modify("-1 day");

        return $target_date->format("Y-m-d");

    }
    catch (Exception $e) {

        return null;

    }
}


/*
|--------------------------------------------------------------------------
| MESSAGES
|--------------------------------------------------------------------------
*/

$error = "";


/*
|--------------------------------------------------------------------------
| GET SELECTED PLAN ID
|--------------------------------------------------------------------------
*/

$plan_id =
    isset($_GET["plan_id"])
    ? (int) $_GET["plan_id"]
    : 0;


if ($plan_id <= 0) {

    header(
        "Location: my_subscription.php"
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| GET SELECTED PLAN
|--------------------------------------------------------------------------
|
| NEVER trust price or member_limit from the browser.
| Always get them from subscription_plans.
|
*/

$sql = "
    SELECT
        subscription_plan_id,
        plan_name,
        price,
        member_limit
    FROM subscription_plans
    WHERE subscription_plan_id = ?
    LIMIT 1
";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    die(
        "Database error: " .
        e($conn->error)
    );

}


$stmt->bind_param(
    "i",
    $plan_id
);


if (!$stmt->execute()) {

    $stmt->close();

    die(
        "Unable to load subscription plan."
    );

}


$result =
    $stmt->get_result();


$new_plan =
    $result->fetch_assoc();


$stmt->close();


if (!$new_plan) {

    die(
        "The selected subscription plan does not exist."
    );

}


/*
|--------------------------------------------------------------------------
| GET CURRENT ACTIVE SUBSCRIPTION
|--------------------------------------------------------------------------
|
| Only this qualifies as the current subscription:
|
| status = active
| start_date <= today
| end_date >= today
|
*/

$current_subscription = null;


$sql = "
    SELECT

        s.subscription_id,
        s.subscription_plan_id,
        s.start_date,
        s.end_date,
        s.status,
        s.created_at,

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
        "Database error: " .
        e($conn->error)
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
        "Unable to load current subscription."
    );

}


$result =
    $stmt->get_result();


$current_subscription =
    $result->fetch_assoc();


$stmt->close();


/*
|--------------------------------------------------------------------------
| GET UPCOMING SCHEDULED SUBSCRIPTION
|--------------------------------------------------------------------------
|
| Only future scheduled subscriptions are considered.
|
*/

$scheduled_subscription = null;


$sql = "
    SELECT

        s.subscription_id,
        s.subscription_plan_id,
        s.start_date,
        s.end_date,
        s.status,
        s.created_at,

        sp.plan_name,
        sp.price,
        sp.member_limit

    FROM gym_owner_subscriptions s

    INNER JOIN subscription_plans sp
        ON s.subscription_plan_id =
           sp.subscription_plan_id

    WHERE s.owner_id = ?

    AND s.status = 'scheduled'

    AND s.start_date > ?

    ORDER BY
        s.start_date ASC,
        s.subscription_id ASC

    LIMIT 1
";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    die(
        "Database error: " .
        e($conn->error)
    );

}


$stmt->bind_param(
    "is",
    $owner_id,
    $today
);


if (!$stmt->execute()) {

    $stmt->close();

    die(
        "Unable to check scheduled subscription."
    );

}


$result =
    $stmt->get_result();


$scheduled_subscription =
    $result->fetch_assoc();


$stmt->close();


/*
|--------------------------------------------------------------------------
| GET TOTAL MEMBERS
|--------------------------------------------------------------------------
*/

$total_members = 0;


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

    die(
        "Database error: " .
        e($conn->error)
    );

}


$stmt->bind_param(
    "i",
    $owner_id
);


if (!$stmt->execute()) {

    $stmt->close();

    die(
        "Unable to calculate member count."
    );

}


$result =
    $stmt->get_result();


$row =
    $result->fetch_assoc();


$total_members =
    (int) ($row["total"] ?? 0);


$stmt->close();


/*
|--------------------------------------------------------------------------
| CALCULATE PROPOSED DATES
|--------------------------------------------------------------------------
*/

$new_start_date = null;

$new_end_date = null;


/*
|--------------------------------------------------------------------------
| EXISTING ACTIVE SUBSCRIPTION
|--------------------------------------------------------------------------
|
| New subscription begins immediately after current subscription.
|
*/

if ($current_subscription) {

    try {

        $new_start =
            new DateTime(
                $current_subscription["end_date"]
            );

        $new_start->modify("+1 day");

        $new_start_date =
            $new_start->format("Y-m-d");

    }
    catch (Exception $e) {

        $error =
            "Unable to calculate the new subscription start date.";

    }

}


/*
|--------------------------------------------------------------------------
| FIRST SUBSCRIPTION
|--------------------------------------------------------------------------
*/

else {

    $new_start_date =
        $today;

}


/*
|--------------------------------------------------------------------------
| CALCULATE END DATE
|--------------------------------------------------------------------------
*/

if ($new_start_date !== null) {

    $new_end_date =
        calculateMonthlyEndDate(
            $new_start_date
        );

}


/*
|--------------------------------------------------------------------------
| SAME PLAN CHECK
|--------------------------------------------------------------------------
*/

$is_same_plan = false;


if ($current_subscription) {

    $is_same_plan =
        (
            (int)
            $current_subscription[
                "subscription_plan_id"
            ]
            ===
            $plan_id
        );

}


/*
|--------------------------------------------------------------------------
| ALREADY SCHEDULED CHECK
|--------------------------------------------------------------------------
*/

$is_already_scheduled = false;


if ($scheduled_subscription) {

    $is_already_scheduled =
        (
            (int)
            $scheduled_subscription[
                "subscription_plan_id"
            ]
            ===
            $plan_id
        );

}


/*
|--------------------------------------------------------------------------
| MEMBER LIMIT VALIDATION
|--------------------------------------------------------------------------
*/

$limit_error = "";


if (
    $new_plan["member_limit"] !== null
) {

    $new_member_limit =
        (int)
        $new_plan["member_limit"];


    if (
        $total_members >
        $new_member_limit
    ) {

        $limit_error =
            "Your gym currently has " .
            number_format($total_members) .
            " members, but the " .
            $new_plan["plan_name"] .
            " plan supports only " .
            number_format($new_member_limit) .
            " members.";

    }

}


/*
|--------------------------------------------------------------------------
| HANDLE POST
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST"
) {

    /*
    |--------------------------------------------------------------------------
    | Validate Submitted Plan
    |--------------------------------------------------------------------------
    */

    $submitted_plan_id =
        isset($_POST["plan_id"])
        ? (int) $_POST["plan_id"]
        : 0;


    if (
        $submitted_plan_id <= 0 ||
        $submitted_plan_id !== $plan_id
    ) {

        $error =
            "Invalid subscription plan.";

    }


    /*
    |--------------------------------------------------------------------------
    | SAME PLAN
    |--------------------------------------------------------------------------
    */

    elseif ($is_same_plan) {

        $error =
            "You are already using the " .
            $current_subscription["plan_name"] .
            " plan.";

    }


    /*
    |--------------------------------------------------------------------------
    | EXISTING SCHEDULED PLAN
    |--------------------------------------------------------------------------
    */

    elseif ($scheduled_subscription) {

        if ($is_already_scheduled) {

            $error =
                "The " .
                $scheduled_subscription["plan_name"] .
                " plan is already scheduled to start on " .
                formatDate(
                    $scheduled_subscription["start_date"]
                ) .
                ".";

        }
        else {

            $error =
                "You already have a subscription change scheduled for " .
                formatDate(
                    $scheduled_subscription["start_date"]
                ) .
                ". Please wait for that scheduled change to complete.";

        }

    }


    /*
    |--------------------------------------------------------------------------
    | MEMBER LIMIT
    |--------------------------------------------------------------------------
    */

    elseif ($limit_error !== "") {

        $error =
            $limit_error;

    }


    /*
    |--------------------------------------------------------------------------
    | DATE VALIDATION
    |--------------------------------------------------------------------------
    */

    elseif (
        !$new_start_date ||
        !$new_end_date
    ) {

        $error =
            "Unable to calculate the subscription dates.";

    }


    /*
    |--------------------------------------------------------------------------
    | CREATE SUBSCRIPTION
    |--------------------------------------------------------------------------
    */

    else {

        $conn->begin_transaction();


        try {

            /*
            |--------------------------------------------------------------------------
            | LOCK CURRENT ACTIVE SUBSCRIPTION
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

                AND start_date <= ?

                AND end_date >= ?

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
                    "Unable to verify your current subscription."
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

                throw new Exception(
                    "Unable to verify your current subscription."
                );

            }


            $result =
                $stmt->get_result();


            $locked_current =
                $result->fetch_assoc();


            $stmt->close();


            /*
            |--------------------------------------------------------------------------
            | LOCK EXISTING FUTURE SCHEDULE
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

                AND status = 'scheduled'

                AND start_date > ?

                ORDER BY
                    start_date ASC,
                    subscription_id ASC

                LIMIT 1

                FOR UPDATE
            ";


            $stmt =
                $conn->prepare($sql);


            if (!$stmt) {

                throw new Exception(
                    "Unable to verify scheduled subscription."
                );

            }


            $stmt->bind_param(
                "is",
                $owner_id,
                $today
            );


            if (!$stmt->execute()) {

                $stmt->close();

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
            | STOP MULTIPLE SCHEDULED CHANGES
            |--------------------------------------------------------------------------
            */

            if ($locked_scheduled) {

                throw new Exception(
                    "You already have a subscription change scheduled for " .
                    formatDate(
                        $locked_scheduled["start_date"]
                    ) .
                    "."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | LOCK SELECTED PLAN
            |--------------------------------------------------------------------------
            */

            $sql = "
                SELECT

                    subscription_plan_id,
                    plan_name,
                    price,
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
                    "Unable to verify the selected plan."
                );

            }


            $stmt->bind_param(
                "i",
                $plan_id
            );


            if (!$stmt->execute()) {

                $stmt->close();

                throw new Exception(
                    "Unable to verify the selected plan."
                );

            }


            $result =
                $stmt->get_result();


            $locked_plan =
                $result->fetch_assoc();


            $stmt->close();


            if (!$locked_plan) {

                throw new Exception(
                    "The selected subscription plan no longer exists."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | RECHECK MEMBER COUNT
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
                    "Unable to verify current member count."
                );

            }


            $stmt->bind_param(
                "i",
                $owner_id
            );


            if (!$stmt->execute()) {

                $stmt->close();

                throw new Exception(
                    "Unable to verify current member count."
                );

            }


            $result =
                $stmt->get_result();


            $row =
                $result->fetch_assoc();


            $locked_member_count =
                (int)
                ($row["total"] ?? 0);


            $stmt->close();


            /*
            |--------------------------------------------------------------------------
            | FINAL MEMBER LIMIT CHECK
            |--------------------------------------------------------------------------
            */

            if (
                $locked_plan["member_limit"] !== null
            ) {

                $locked_limit =
                    (int)
                    $locked_plan[
                        "member_limit"
                    ];


                if (
                    $locked_member_count >
                    $locked_limit
                ) {

                    throw new Exception(
                        "Your gym currently has " .
                        number_format($locked_member_count) .
                        " members, but the " .
                        $locked_plan["plan_name"] .
                        " plan supports only " .
                        number_format($locked_limit) .
                        " members."
                    );

                }

            }


            /*
            |--------------------------------------------------------------------------
            | EXISTING ACTIVE SUBSCRIPTION
            |--------------------------------------------------------------------------
            */

            if ($locked_current) {

                /*
                |--------------------------------------------------------------------------
                | SAME PLAN RECHECK
                |--------------------------------------------------------------------------
                */

                if (
                    (int)
                    $locked_current[
                        "subscription_plan_id"
                    ]
                    ===
                    $plan_id
                ) {

                    throw new Exception(
                        "You are already using this subscription plan."
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | CALCULATE SCHEDULED START
                |--------------------------------------------------------------------------
                */

                $scheduled_start =
                    new DateTime(
                        $locked_current["end_date"]
                    );


                $scheduled_start->modify(
                    "+1 day"
                );


                $scheduled_start_date =
                    $scheduled_start->format(
                        "Y-m-d"
                    );


                /*
                |--------------------------------------------------------------------------
                | CALCULATE SCHEDULED END
                |--------------------------------------------------------------------------
                */

                $scheduled_end_date =
                    calculateMonthlyEndDate(
                        $scheduled_start_date
                    );


                if (!$scheduled_end_date) {

                    throw new Exception(
                        "Unable to calculate the new subscription end date."
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | INSERT SCHEDULED SUBSCRIPTION
                |--------------------------------------------------------------------------
                |
                | IMPORTANT:
                |
                | We DO NOT modify the current active subscription.
                |
                | The current row remains:
                |
                | active
                |
                | until its original end_date.
                |
                | The new row becomes:
                |
                | scheduled
                |
                | subscription_cron.php will later:
                |
                | old active -> expired
                | scheduled -> active
                |
                */

                $sql = "
                    INSERT INTO gym_owner_subscriptions
                    (
                        owner_id,
                        subscription_plan_id,
                        start_date,
                        end_date,
                        status,
                        created_at
                    )

                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        'scheduled',
                        NOW()
                    )
                ";


                $stmt =
                    $conn->prepare($sql);


                if (!$stmt) {

                    throw new Exception(
                        "Unable to prepare subscription change."
                    );

                }


                $stmt->bind_param(
                    "iiss",
                    $owner_id,
                    $plan_id,
                    $scheduled_start_date,
                    $scheduled_end_date
                );


                if (!$stmt->execute()) {

                    $stmt->close();

                    throw new Exception(
                        "Unable to schedule the subscription change."
                    );

                }


                $stmt->close();


                /*
                |--------------------------------------------------------------------------
                | COMMIT
                |--------------------------------------------------------------------------
                */

                $conn->commit();


                /*
                |--------------------------------------------------------------------------
                | SUCCESS
                |--------------------------------------------------------------------------
                */

                $_SESSION[
                    "subscription_change_success"
                ] =
                    "Your " .
                    $locked_plan["plan_name"] .
                    " plan has been scheduled successfully. " .
                    "Your current subscription remains active until " .
                    formatDate(
                        $locked_current["end_date"]
                    ) .
                    ". The new plan will start on " .
                    formatDate(
                        $scheduled_start_date
                    ) .
                    ".";


                /*
                |--------------------------------------------------------------------------
                | Better success message using current plan name
                |--------------------------------------------------------------------------
                */

                /*
                | We intentionally redirect after committing.
                | my_subscription.php will display the upcoming plan.
                */


                header(
                    "Location: my_subscription.php"
                );

                exit();

            }


            /*
            |--------------------------------------------------------------------------
            | NO CURRENT ACTIVE SUBSCRIPTION
            |--------------------------------------------------------------------------
            |
            | This is the first subscription.
            |
            | It becomes active immediately.
            |
            */

            else {

                /*
                |--------------------------------------------------------------------------
                | FIRST SUBSCRIPTION DATES
                |--------------------------------------------------------------------------
                */

                $first_start_date =
                    $today;


                $first_end_date =
                    calculateMonthlyEndDate(
                        $first_start_date
                    );


                if (!$first_end_date) {

                    throw new Exception(
                        "Unable to calculate the subscription end date."
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | INSERT ACTIVE SUBSCRIPTION
                |--------------------------------------------------------------------------
                */

                $sql = "
                    INSERT INTO gym_owner_subscriptions
                    (
                        owner_id,
                        subscription_plan_id,
                        start_date,
                        end_date,
                        status,
                        created_at
                    )

                    VALUES
                    (
                        ?,
                        ?,
                        ?,
                        ?,
                        'active',
                        NOW()
                    )
                ";


                $stmt =
                    $conn->prepare($sql);


                if (!$stmt) {

                    throw new Exception(
                        "Unable to prepare the subscription."
                    );

                }


                $stmt->bind_param(
                    "iiss",
                    $owner_id,
                    $plan_id,
                    $first_start_date,
                    $first_end_date
                );


                if (!$stmt->execute()) {

                    $stmt->close();

                    throw new Exception(
                        "Unable to create the subscription."
                    );

                }


                $stmt->close();


                /*
                |--------------------------------------------------------------------------
                | COMMIT
                |--------------------------------------------------------------------------
                */

                $conn->commit();


                /*
                |--------------------------------------------------------------------------
                | SUCCESS MESSAGE
                |--------------------------------------------------------------------------
                */

                $_SESSION[
                    "subscription_change_success"
                ] =
                    "Your " .
                    $locked_plan["plan_name"] .
                    " subscription is now active until " .
                    formatDate(
                        $first_end_date
                    ) .
                    ".";


                header(
                    "Location: my_subscription.php"
                );

                exit();

            }

        }
        catch (Exception $e) {

            $conn->rollback();

            $error =
                $e->getMessage();

        }

    }

}


/*
|--------------------------------------------------------------------------
| RECALCULATE DISPLAY DATA AFTER POST ERROR
|--------------------------------------------------------------------------
|
| If validation failed before transaction, the variables above are
| already available.
|
*/

?>


<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Change Subscription
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f6f8;

            color: #1f2937;

        }


        .container {

            max-width: 950px;

            margin: auto;

            padding: 30px;

        }


        .header {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            gap: 20px;

            margin-bottom: 25px;

        }


        .header h1 {

            margin: 0;

            font-size: 30px;

        }


        .header p {

            margin: 6px 0 0;

            color: #6b7280;

        }


        .back {

            display: inline-block;

            padding: 11px 18px;

            background: #111827;

            color: white;

            text-decoration: none;

            border-radius: 8px;

            font-weight: bold;

            white-space: nowrap;

        }


        .back:hover {

            opacity: .85;

        }


        .card {

            background: white;

            padding: 30px;

            border-radius: 14px;

            box-shadow:
                0 4px 18px
                rgba(0,0,0,.06);

        }


        .comparison {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 20px;

            margin-top: 25px;

        }


        .plan {

            border: 1px solid #e5e7eb;

            border-radius: 12px;

            padding: 25px;

        }


        .plan.current {

            background: #f9fafb;

        }


        .plan.new {

            border:
                2px solid #2563eb;

            background: #eff6ff;

        }


        .label {

            color: #6b7280;

            font-size: 13px;

            font-weight: bold;

            margin-bottom: 10px;

        }


        .plan h2 {

            margin: 0 0 12px;

            font-size: 27px;

        }


        .price {

            font-size: 25px;

            font-weight: bold;

            margin-bottom: 18px;

        }


        .price span {

            font-size: 14px;

            color: #6b7280;

            font-weight: normal;

        }


        .feature {

            padding: 11px 0;

            border-bottom:
                1px solid #e5e7eb;

        }


        .feature:last-child {

            border-bottom: none;

        }


        .notice {

            padding: 16px;

            border-radius: 9px;

            margin-top: 20px;

            line-height: 1.5;

        }


        .notice-warning {

            background: #fffbeb;

            border:
                1px solid #fde68a;

            color: #92400e;

        }


        .notice-error {

            background: #fee2e2;

            border:
                1px solid #fecaca;

            color: #991b1b;

        }


        .notice-success {

            background: #dcfce7;

            border:
                1px solid #bbf7d0;

            color: #166534;

        }


        .member-info {

            background: #f8fafc;

            border:
                1px solid #e5e7eb;

            padding: 16px;

            border-radius: 9px;

            margin-top: 20px;

            line-height: 1.6;

        }


        .actions {

            display: flex;

            gap: 10px;

            margin-top: 25px;

            flex-wrap: wrap;

        }


        .button {

            display: inline-block;

            padding: 12px 20px;

            border: none;

            border-radius: 8px;

            text-decoration: none;

            font-weight: bold;

            cursor: pointer;

            font-size: 15px;

        }


        .button-primary {

            background: #2563eb;

            color: white;

        }


        .button-cancel {

            background: #e5e7eb;

            color: #374151;

        }


        .button:hover {

            opacity: .85;

        }


        .important {

            font-weight: bold;

        }


        @media (max-width: 700px) {

            .container {

                padding: 15px;

            }


            .header {

                flex-direction: column;

                align-items: flex-start;

            }


            .comparison {

                grid-template-columns: 1fr;

            }

        }

    </style>

</head>


<body>


<div class="container">


    <!-- HEADER -->

    <div class="header">

        <div>

            <h1>
                Change Subscription
            </h1>

            <p>
                Review your subscription change
                before confirming.
            </p>

        </div>


        <a
            href="my_subscription.php"
            class="back"
        >
            ← My Subscription
        </a>

    </div>


    <?php if ($error !== ""): ?>


        <!-- ERROR -->

        <div class="card">

            <div class="notice notice-error">

                <strong>
                    Unable to change subscription
                </strong>

                <br><br>

                <?php
                echo e($error);
                ?>

            </div>


            <div class="actions">

                <a
                    href="my_subscription.php"
                    class="button button-cancel"
                >
                    ← Back to My Subscription
                </a>

            </div>

        </div>


    <?php else: ?>


        <!-- MAIN CARD -->

        <div class="card">


            <h2>
                Review Plan Change
            </h2>


            <?php if ($current_subscription): ?>


                <p>

                    Your current subscription will remain
                    active until its expiry date.

                    The new plan will start immediately
                    after the current subscription ends.

                </p>


            <?php else: ?>


                <p>

                    This will be your first subscription.

                    It will become active immediately
                    after confirmation.

                </p>


            <?php endif; ?>



            <!-- PLAN COMPARISON -->

            <div class="comparison">


                <!-- CURRENT PLAN -->

                <div class="plan current">


                    <div class="label">
                        CURRENT PLAN
                    </div>


                    <?php if ($current_subscription): ?>


                        <h2>

                            <?php

                            echo e(
                                $current_subscription[
                                    "plan_name"
                                ]
                            );

                            ?>

                        </h2>


                        <div class="price">

                            Rs.

                            <?php

                            echo number_format(
                                (float)
                                $current_subscription[
                                    "price"
                                ],
                                2
                            );

                            ?>

                            <span>
                                / month
                            </span>

                        </div>


                        <div class="feature">

                            <strong>
                                Current Members:
                            </strong>

                            <?php

                            echo number_format(
                                $total_members
                            );

                            ?>

                        </div>


                        <div class="feature">

                            <strong>
                                Member Limit:
                            </strong>

                            <?php

                            if (
                                $current_subscription[
                                    "member_limit"
                                ] !== null
                            ) {

                                echo number_format(
                                    (int)
                                    $current_subscription[
                                        "member_limit"
                                    ]
                                );

                            }
                            else {

                                echo "Unlimited";

                            }

                            ?>

                        </div>


                        <div class="feature">

                            <strong>
                                Active Until:
                            </strong>

                            <?php

                            echo formatDate(
                                $current_subscription[
                                    "end_date"
                                ]
                            );

                            ?>

                        </div>


                    <?php else: ?>


                        <h2>
                            No Active Subscription
                        </h2>


                        <div class="feature">

                            This will be your first
                            subscription.

                        </div>


                    <?php endif; ?>


                </div>



                <!-- NEW PLAN -->

                <div class="plan new">


                    <div class="label">
                        NEW PLAN
                    </div>


                    <h2>

                        <?php

                        echo e(
                            $new_plan[
                                "plan_name"
                            ]
                        );

                        ?>

                    </h2>


                    <div class="price">

                        Rs.

                        <?php

                        echo number_format(
                            (float)
                            $new_plan[
                                "price"
                            ],
                            2
                        );

                        ?>

                        <span>
                            / month
                        </span>

                    </div>


                    <div class="feature">

                        <strong>
                            Member Limit:
                        </strong>

                        <?php

                        if (
                            $new_plan[
                                "member_limit"
                            ] !== null
                        ) {

                            echo number_format(
                                (int)
                                $new_plan[
                                    "member_limit"
                                ]
                            );

                        }
                        else {

                            echo "Unlimited";

                        }

                        ?>

                    </div>


                    <div class="feature">

                        <strong>
                            Starts:
                        </strong>

                        <?php

                        echo formatDate(
                            $new_start_date
                        );

                        ?>

                    </div>


                    <div class="feature">

                        <strong>
                            Ends:
                        </strong>

                        <?php

                        echo formatDate(
                            $new_end_date
                        );

                        ?>

                    </div>


                </div>


            </div>



            <!-- CURRENT PLAN WARNING -->

            <?php if ($current_subscription): ?>


                <div class="notice notice-warning">

                    <strong>
                        Your current plan will NOT be
                        cancelled immediately.
                    </strong>

                    <br><br>

                    Your current

                    <strong>

                        <?php

                        echo e(
                            $current_subscription[
                                "plan_name"
                            ]
                        );

                        ?>

                    </strong>

                    subscription remains active until

                    <strong>

                        <?php

                        echo formatDate(
                            $current_subscription[
                                "end_date"
                            ]
                        );

                        ?>

                    </strong>.

                    <br><br>

                    Your new

                    <strong>

                        <?php

                        echo e(
                            $new_plan[
                                "plan_name"
                            ]
                        );

                        ?>

                    </strong>

                    plan will start on

                    <strong>

                        <?php

                        echo formatDate(
                            $new_start_date
                        );

                        ?>

                    </strong>.

                    <br><br>

                    You will not lose the remaining time
                    on your current subscription.

                </div>


            <?php else: ?>


                <div class="notice notice-success">

                    <strong>
                        First subscription
                    </strong>

                    <br><br>

                    Your

                    <strong>

                        <?php

                        echo e(
                            $new_plan[
                                "plan_name"
                            ]
                        );

                        ?>

                    </strong>

                    plan will become active immediately
                    after confirmation.

                </div>


            <?php endif; ?>



            <!-- MEMBER USAGE -->

            <div class="member-info">

                <strong>
                    Member Usage
                </strong>

                <br><br>

                Your gym currently has

                <strong>

                    <?php

                    echo number_format(
                        $total_members
                    );

                    ?>

                </strong>

                members.

                <br><br>

                <?php if (
                    $new_plan["member_limit"] !== null
                ): ?>


                    The selected plan allows

                    <strong>

                        <?php

                        echo number_format(
                            (int)
                            $new_plan[
                                "member_limit"
                            ]
                        );

                        ?>

                    </strong>

                    members.


                <?php else: ?>


                    The selected plan allows

                    <strong>
                        unlimited members
                    </strong>.


                <?php endif; ?>


            </div>



            <!-- MEMBER LIMIT ERROR -->

            <?php if ($limit_error !== ""): ?>


                <div class="notice notice-error">

                    <strong>
                        This plan cannot be selected.
                    </strong>

                    <br><br>

                    <?php

                    echo e(
                        $limit_error
                    );

                    ?>

                    <br><br>

                    Please choose a plan with a higher
                    member limit or reduce your gym's
                    member count before downgrading.

                </div>


            <!-- EXISTING SCHEDULE -->

            <?php elseif ($scheduled_subscription): ?>


                <div class="notice notice-warning">

                    <strong>
                        A plan change is already scheduled.
                    </strong>

                    <br><br>

                    You already have

                    <strong>

                        <?php

                        echo e(
                            $scheduled_subscription[
                                "plan_name"
                            ]
                        );

                        ?>

                    </strong>

                    scheduled to start on

                    <strong>

                        <?php

                        echo formatDate(
                            $scheduled_subscription[
                                "start_date"
                            ]
                        );

                        ?>

                    </strong>.

                    <br><br>

                    You must wait for the existing
                    scheduled subscription to become active
                    before making another plan change.

                </div>


            <!-- CONFIRM FORM -->

            <?php else: ?>


                <form
                    method="POST"
                    action="subscription_change.php?plan_id=<?php echo $plan_id; ?>"
                    onsubmit="return confirmSubscription();"
                >

                    <input
                        type="hidden"
                        name="plan_id"
                        value="<?php echo $plan_id; ?>"
                    >


                    <div class="actions">


                        <button
                            type="submit"
                            class="button button-primary"
                        >

                            <?php

                            if ($current_subscription) {

                                echo "Schedule Plan Change";

                            }
                            else {

                                echo "Activate Plan";

                            }

                            ?>

                        </button>


                        <a
                            href="my_subscription.php"
                            class="button button-cancel"
                        >
                            Cancel
                        </a>


                    </div>


                </form>


            <?php endif; ?>


        </div>


    <?php endif; ?>


</div>

<script>

function confirmSubscription()
{
    <?php if ($current_subscription): ?>

        return confirm(
            "Are you sure you want to schedule this plan change?\n\n" +
            "Your current subscription will remain active " +
            "until <?php echo e(formatDate($current_subscription["end_date"])); ?>.\n\n" +
            "The new plan will start on " +
            "<?php echo e(formatDate($new_start_date)); ?>."
        );

    <?php else: ?>

        return confirm(
            "Are you sure you want to activate this subscription?"
        );

    <?php endif; ?>
}

</script>


</body>

</html>