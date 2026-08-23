<?php

session_start();

require_once "backend/db.php";


/*
|--------------------------------------------------------------------------
| Gym Owner Authentication
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
| Helper Functions
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

    return date(
        "d M Y",
        $timestamp
    );
}


/*
|--------------------------------------------------------------------------
| Calculate Monthly End Date
|--------------------------------------------------------------------------
|
| Examples:
|
| 01 Aug -> 31 Aug
| 01 Sep -> 30 Sep
| 01 Jan -> 31 Jan
|
| We always calculate from the first day of the month.
|
*/

function calculateMonthlyEndDate($start_date)
{
    $start =
        new DateTime($start_date);

    $next_month =
        new DateTime(
            $start->format("Y-m-01")
        );

    $next_month->modify("+1 month");

    $next_month->modify("-1 day");

    return $next_month->format("Y-m-d");
}


/*
|--------------------------------------------------------------------------
| Messages
|--------------------------------------------------------------------------
*/

$error = "";

$success = "";


/*
|--------------------------------------------------------------------------
| Get Selected Plan ID
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
| Get Selected Plan
|--------------------------------------------------------------------------
|
| Never trust price/member_limit from the browser.
| Always retrieve them from the database.
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


$stmt->execute();


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
| Activate Due Scheduled Subscription
|--------------------------------------------------------------------------
|
| Normally this is handled by my_subscription.php when the owner
| opens the subscription page.
|
| We also handle it here because an owner could directly open:
|
| subscription_change.php?plan_id=X
|
| after a scheduled plan's start date.
|
*/

$conn->begin_transaction();


try {

    /*
    |--------------------------------------------------------------------------
    | Find scheduled subscriptions whose start date has arrived
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT

            subscription_id,
            subscription_plan_id,
            start_date,
            end_date

        FROM gym_owner_subscriptions

        WHERE owner_id = ?

        AND status = 'scheduled'

        AND start_date <= ?

        ORDER BY start_date ASC,
                 subscription_id ASC

        LIMIT 1

        FOR UPDATE
    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        throw new Exception(
            "Unable to check scheduled subscription."
        );

    }


    $stmt->bind_param(
        "is",
        $owner_id,
        $today
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    $due_subscription =
        $result->fetch_assoc();


    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | If a scheduled plan is due, activate it
    |--------------------------------------------------------------------------
    */

    if ($due_subscription) {

        /*
        |--------------------------------------------------------------------------
        | Find any active subscription
        |--------------------------------------------------------------------------
        */

        $sql = "
            SELECT

                subscription_id

            FROM gym_owner_subscriptions

            WHERE owner_id = ?

            AND status = 'active'

            LIMIT 1

            FOR UPDATE
        ";


        $stmt =
            $conn->prepare($sql);


        if (!$stmt) {

            throw new Exception(
                "Unable to verify active subscription."
            );

        }


        $stmt->bind_param(
            "i",
            $owner_id
        );


        $stmt->execute();


        $result =
            $stmt->get_result();


        $old_active =
            $result->fetch_assoc();


        $stmt->close();


        /*
        |--------------------------------------------------------------------------
        | Expire old active subscription if necessary
        |--------------------------------------------------------------------------
        */

        if ($old_active) {

            $old_active_id =
                (int)
                $old_active[
                    "subscription_id"
                ];


            $sql = "
                UPDATE gym_owner_subscriptions

                SET status = 'expired'

                WHERE subscription_id = ?

                AND status = 'active'
            ";


            $stmt =
                $conn->prepare($sql);


            if (!$stmt) {

                throw new Exception(
                    "Unable to close previous subscription."
                );

            }


            $stmt->bind_param(
                "i",
                $old_active_id
            );


            if (!$stmt->execute()) {

                throw new Exception(
                    "Unable to close previous subscription."
                );

            }


            $stmt->close();

        }


        /*
        |--------------------------------------------------------------------------
        | Activate scheduled subscription
        |--------------------------------------------------------------------------
        */

        $due_subscription_id =
            (int)
            $due_subscription[
                "subscription_id"
            ];


        $sql = "
            UPDATE gym_owner_subscriptions

            SET status = 'active'

            WHERE subscription_id = ?

            AND status = 'scheduled'
        ";


        $stmt =
            $conn->prepare($sql);


        if (!$stmt) {

            throw new Exception(
                "Unable to activate scheduled subscription."
            );

        }


        $stmt->bind_param(
            "i",
            $due_subscription_id
        );


        if (!$stmt->execute()) {

            throw new Exception(
                "Unable to activate scheduled subscription."
            );

        }


        $stmt->close();

    }


    $conn->commit();

}

catch (Exception $e) {

    $conn->rollback();

    die(
        "Subscription state error: " .
        e($e->getMessage())
    );

}


/*
|--------------------------------------------------------------------------
| Get Current Active Subscription
|--------------------------------------------------------------------------
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

    ORDER BY s.end_date DESC,
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


$stmt->execute();


$result =
    $stmt->get_result();


$current_subscription =
    $result->fetch_assoc();


$stmt->close();


/*
|--------------------------------------------------------------------------
| Get Existing Future Scheduled Subscription
|--------------------------------------------------------------------------
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

    ORDER BY s.start_date ASC,
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


$stmt->execute();


$result =
    $stmt->get_result();


$scheduled_subscription =
    $result->fetch_assoc();


$stmt->close();


/*
|--------------------------------------------------------------------------
| Get Current Member Count
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


$stmt->execute();


$result =
    $stmt->get_result();


$row =
    $result->fetch_assoc();


$total_members =
    (int) ($row["total"] ?? 0);


$stmt->close();


/*
|--------------------------------------------------------------------------
| Calculate Proposed Subscription Dates
|--------------------------------------------------------------------------
*/

$new_start_date = null;

$new_end_date = null;


if ($current_subscription) {

    /*
    |--------------------------------------------------------------------------
    | Existing active plan
    |--------------------------------------------------------------------------
    */

    $new_start =
        new DateTime(
            $current_subscription[
                "end_date"
            ]
        );


    $new_start->modify("+1 day");


    $new_start_date =
        $new_start->format(
            "Y-m-d"
        );

}
else {

    /*
    |--------------------------------------------------------------------------
    | First subscription
    |--------------------------------------------------------------------------
    */

    $new_start_date =
        $today;

}


/*
|--------------------------------------------------------------------------
| Calculate New End Date
|--------------------------------------------------------------------------
*/

$new_end_date =
    calculateMonthlyEndDate(
        $new_start_date
    );


/*
|--------------------------------------------------------------------------
| Same Plan Check
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
| Existing Scheduled Plan Check
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
| Member Limit Validation
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
            $total_members .
            " members, but the " .
            $new_plan["plan_name"] .
            " plan supports only " .
            $new_member_limit .
            " members.";

    }

}


/*
|--------------------------------------------------------------------------
| Handle POST
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
        $submitted_plan_id !==
        $plan_id
    ) {

        $error =
            "Invalid subscription plan.";

    }


    /*
    |--------------------------------------------------------------------------
    | Same Plan
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
    | Existing Scheduled Change
    |--------------------------------------------------------------------------
    */

    elseif ($scheduled_subscription) {

        $error =
            "You already have a subscription change scheduled for " .
            formatDate(
                $scheduled_subscription[
                    "start_date"
                ]
            ) .
            ".";

    }


    /*
    |--------------------------------------------------------------------------
    | Member Limit
    |--------------------------------------------------------------------------
    */

    elseif ($limit_error !== "") {

        $error =
            $limit_error;

    }


    /*
    |--------------------------------------------------------------------------
    | Create Subscription / Schedule Change
    |--------------------------------------------------------------------------
    */

    else {

        $conn->begin_transaction();


        try {

            /*
            |--------------------------------------------------------------------------
            | Lock Current Active Subscription
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

                ORDER BY end_date DESC,
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


            $stmt->execute();


            $result =
                $stmt->get_result();


            $locked_current =
                $result->fetch_assoc();


            $stmt->close();


            /*
            |--------------------------------------------------------------------------
            | Lock Existing Scheduled Subscription
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

                ORDER BY start_date ASC,
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


            $stmt->execute();


            $result =
                $stmt->get_result();


            $locked_scheduled =
                $result->fetch_assoc();


            $stmt->close();


            /*
            |--------------------------------------------------------------------------
            | Prevent Duplicate Scheduled Changes
            |--------------------------------------------------------------------------
            */

            if ($locked_scheduled) {

                throw new Exception(
                    "You already have a subscription change scheduled for " .
                    formatDate(
                        $locked_scheduled[
                            "start_date"
                        ]
                    ) .
                    "."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Re-check Selected Plan
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


            $stmt->execute();


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
            | Re-check Member Count
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


            $stmt->execute();


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
            | Final Member Limit Validation
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
                        $locked_member_count .
                        " members, but the " .
                        $locked_plan["plan_name"] .
                        " plan supports only " .
                        $locked_limit .
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
                | Same Plan Re-check
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
                | New Plan Starts After Current Plan
                |--------------------------------------------------------------------------
                */

                $scheduled_start =
                    new DateTime(
                        $locked_current[
                            "end_date"
                        ]
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
                | Calculate Scheduled End Date
                |--------------------------------------------------------------------------
                */

                $scheduled_end_date =
                    calculateMonthlyEndDate(
                        $scheduled_start_date
                    );


                /*
                |--------------------------------------------------------------------------
                | Insert Scheduled Subscription
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

                    throw new Exception(
                        "Unable to schedule the subscription change."
                    );

                }


                $stmt->close();


                /*
                |--------------------------------------------------------------------------
                | Commit
                |--------------------------------------------------------------------------
                */

                $conn->commit();


                /*
                |--------------------------------------------------------------------------
                | Success Message
                |--------------------------------------------------------------------------
                */

                $_SESSION[
                    "subscription_change_success"
                ] =
                    "Your " .
                    $locked_plan["plan_name"] .
                    " plan has been scheduled. " .
                    "Your current subscription remains active until " .
                    formatDate(
                        $locked_current[
                            "end_date"
                        ]
                    ) .
                    ". The new plan will start on " .
                    formatDate(
                        $scheduled_start_date
                    ) .
                    ".";


                /*
                |--------------------------------------------------------------------------
                | Redirect
                |--------------------------------------------------------------------------
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
            | First subscription becomes active immediately.
            |
            */

            else {

                /*
                |--------------------------------------------------------------------------
                | Start Today
                |--------------------------------------------------------------------------
                */

                $first_start_date =
                    $today;


                /*
                |--------------------------------------------------------------------------
                | Calculate End Date
                |--------------------------------------------------------------------------
                */

                $first_end_date =
                    calculateMonthlyEndDate(
                        $first_start_date
                    );


                /*
                |--------------------------------------------------------------------------
                | Insert Active Subscription
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
                        "Unable to prepare subscription."
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

                    throw new Exception(
                        "Unable to create subscription."
                    );

                }


                $stmt->close();


                /*
                |--------------------------------------------------------------------------
                | Commit
                |--------------------------------------------------------------------------
                */

                $conn->commit();


                /*
                |--------------------------------------------------------------------------
                | Success Message
                |--------------------------------------------------------------------------
                */

                $_SESSION[
                    "subscription_change_success"
                ] =
                    "Your " .
                    $locked_plan["plan_name"] .
                    " subscription is now active.";


                /*
                |--------------------------------------------------------------------------
                | Redirect
                |--------------------------------------------------------------------------
                */

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
| Success Message From Previous Request
|--------------------------------------------------------------------------
*/

if (
    isset(
        $_SESSION[
            "subscription_change_success"
        ]
    )
) {

    $success =
        $_SESSION[
            "subscription_change_success"
        ];

    unset(
        $_SESSION[
            "subscription_change_success"
        ]
    );

}

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

            border: 2px solid #2563eb;

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

            border: 1px solid #fde68a;

            color: #92400e;

        }


        .notice-error {

            background: #fee2e2;

            border: 1px solid #fecaca;

            color: #991b1b;

        }


        .notice-success {

            background: #dcfce7;

            border: 1px solid #bbf7d0;

            color: #166534;

        }


        .member-info {

            background: #f8fafc;

            border: 1px solid #e5e7eb;

            padding: 16px;

            border-radius: 9px;

            margin-top: 20px;

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


        @media (max-width: 700px) {

            .container {

                padding: 15px;

            }


            .header {

                flex-direction: column;

                align-items: flex-start;

                gap: 15px;

            }


            .comparison {

                grid-template-columns: 1fr;

            }

        }

    </style>

</head>


<body>


<div class="container">


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


        <div class="card">

            <h2>
                Review Plan Change
            </h2>


            <p>

                <?php if ($current_subscription): ?>

                    Your current subscription will remain active
                    until its expiry date. The new plan will begin
                    immediately after the current billing period.

                <?php else: ?>

                    This will be your first subscription and will
                    become active immediately after confirmation.

                <?php endif; ?>

            </p>


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
                                Members:
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



            <?php if ($current_subscription): ?>


                <div class="notice notice-warning">

                    <strong>
                        Your current plan will remain active.
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

                    plan will begin on

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
                        Your first subscription
                    </strong>

                    will become active immediately
                    after confirmation.

                </div>


            <?php endif; ?>


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

                    You must wait for the existing scheduled
                    change to complete before scheduling
                    another plan.

                </div>


            <?php else: ?>


                <form
                    method="POST"
                    action="subscription_change.php?plan_id=<?php echo $plan_id; ?>"
                    onsubmit="return confirm('Are you sure you want to schedule this plan change?');"
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

                            echo $current_subscription
                                ? "Schedule Plan Change"
                                : "Activate Plan";

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


</body>

</html>