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

    return date(
        "d M Y",
        $timestamp
    );
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
| 24 Sep -> 23 Oct
| 31 Jan -> 28/29 Feb
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
| ERROR / SUCCESS
|--------------------------------------------------------------------------
*/

$error = "";

$success = "";


/*
|--------------------------------------------------------------------------
| GET CURRENT ACTIVE SUBSCRIPTION
|--------------------------------------------------------------------------
|
| A subscription is current only when:
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
| IF THERE IS NO ACTIVE SUBSCRIPTION
|--------------------------------------------------------------------------
|
| Renewal only works for an existing active subscription.
|
*/

if (!$current_subscription) {

    header(
        "Location: my_subscription.php"
    );

    exit();

}


/*
|--------------------------------------------------------------------------
| GET UPCOMING SCHEDULED SUBSCRIPTION
|--------------------------------------------------------------------------
|
| A future scheduled subscription prevents another
| renewal from being created.
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
| GET CURRENT MEMBER COUNT
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
| CALCULATE RENEWAL DATES
|--------------------------------------------------------------------------
|
| Renewal starts immediately after the current subscription.
|
| Example:
|
| Current:
| 24 Aug -> 23 Sep
|
| Renewal:
| 24 Sep -> 23 Oct
|
*/

$renewal_start_date = null;

$renewal_end_date = null;


try {

    $renewal_start =
        new DateTime(
            $current_subscription["end_date"]
        );

    $renewal_start->modify(
        "+1 day"
    );


    $renewal_start_date =
        $renewal_start->format(
            "Y-m-d"
        );


    $renewal_end_date =
        calculateMonthlyEndDate(
            $renewal_start_date
        );

}
catch (Exception $e) {

    $error =
        "Unable to calculate the renewal dates.";

}


/*
|--------------------------------------------------------------------------
| MEMBER LIMIT VALIDATION
|--------------------------------------------------------------------------
|
| Renewal uses the SAME plan.
|
| If the owner has exceeded the plan's member limit,
| do not allow renewal.
|
*/

$limit_error = "";


if (
    $current_subscription["member_limit"] !== null
) {

    $member_limit =
        (int)
        $current_subscription[
            "member_limit"
        ];


    if (
        $total_members >
        $member_limit
    ) {

        $limit_error =
            "Your gym currently has " .
            number_format($total_members) .
            " members, but your " .
            $current_subscription["plan_name"] .
            " plan supports only " .
            number_format($member_limit) .
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
    | Make sure submitted subscription ID matches
    |--------------------------------------------------------------------------
    */

    $submitted_subscription_id =
        isset($_POST["subscription_id"])
        ? (int) $_POST["subscription_id"]
        : 0;


    if (
        $submitted_subscription_id !==
        (int)
        $current_subscription[
            "subscription_id"
        ]
    ) {

        $error =
            "Invalid subscription request.";

    }


    /*
    |--------------------------------------------------------------------------
    | Existing Scheduled Subscription
    |--------------------------------------------------------------------------
    */

    elseif ($scheduled_subscription) {

        $error =
            "You already have a subscription scheduled for " .
            formatDate(
                $scheduled_subscription["start_date"]
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
    | Date Validation
    |--------------------------------------------------------------------------
    */

    elseif (
        !$renewal_start_date ||
        !$renewal_end_date
    ) {

        $error =
            "Unable to calculate the renewal dates.";

    }


    /*
    |--------------------------------------------------------------------------
    | CREATE RENEWAL
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

                    s.subscription_id,
                    s.subscription_plan_id,
                    s.start_date,
                    s.end_date,
                    s.status

                FROM gym_owner_subscriptions s

                WHERE s.owner_id = ?

                AND s.status = 'active'

                AND s.start_date <= ?

                AND s.end_date >= ?

                ORDER BY
                    s.end_date DESC,
                    s.subscription_id DESC

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
            | Current Subscription Must Still Exist
            |--------------------------------------------------------------------------
            */

            if (!$locked_current) {

                throw new Exception(
                    "Your current subscription is no longer active."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | LOCK FUTURE SCHEDULE
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
            | PREVENT DUPLICATE RENEWAL
            |--------------------------------------------------------------------------
            */

            if ($locked_scheduled) {

                throw new Exception(
                    "You already have a subscription scheduled for " .
                    formatDate(
                        $locked_scheduled["start_date"]
                    ) .
                    "."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | LOCK CURRENT PLAN
            |--------------------------------------------------------------------------
            |
            | We use the plan attached to the active subscription.
            | We do NOT trust a plan ID from the browser.
            |
            */

            $locked_plan_id =
                (int)
                $locked_current[
                    "subscription_plan_id"
                ];


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
                    "Unable to verify your current subscription plan."
                );

            }


            $stmt->bind_param(
                "i",
                $locked_plan_id
            );


            if (!$stmt->execute()) {

                $stmt->close();

                throw new Exception(
                    "Unable to verify your current subscription plan."
                );

            }


            $result =
                $stmt->get_result();


            $locked_plan =
                $result->fetch_assoc();


            $stmt->close();


            if (!$locked_plan) {

                throw new Exception(
                    "Your current subscription plan no longer exists."
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
                        number_format(
                            $locked_member_count
                        ) .
                        " members, but your " .
                        $locked_plan["plan_name"] .
                        " plan supports only " .
                        number_format(
                            $locked_limit
                        ) .
                        " members."
                    );

                }

            }


            /*
            |--------------------------------------------------------------------------
            | RE-CALCULATE DATES FROM LOCKED SUBSCRIPTION
            |--------------------------------------------------------------------------
            |
            | This prevents the browser/display values from being
            | used for the actual database operation.
            |
            */

            $locked_start =
                new DateTime(
                    $locked_current["end_date"]
                );


            $locked_start->modify(
                "+1 day"
            );


            $final_start_date =
                $locked_start->format(
                    "Y-m-d"
                );


            $final_end_date =
                calculateMonthlyEndDate(
                    $final_start_date
                );


            if (!$final_end_date) {

                throw new Exception(
                    "Unable to calculate the renewal end date."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | INSERT RENEWAL AS SCHEDULED
            |--------------------------------------------------------------------------
            |
            | IMPORTANT:
            |
            | The current subscription remains untouched.
            |
            | Example:
            |
            | ID 1 | Basic   | 24 Aug -> 23 Sep | active
            | ID 2 | Basic   | 24 Sep -> 23 Oct | scheduled
            |
            | subscription_cron.php will later change:
            |
            | ID 1 -> expired
            | ID 2 -> active
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
                    "Unable to prepare the renewal."
                );

            }


            $stmt->bind_param(
                "iiss",
                $owner_id,
                $locked_plan_id,
                $final_start_date,
                $final_end_date
            );


            if (!$stmt->execute()) {

                $stmt->close();

                throw new Exception(
                    "Unable to create the renewal."
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
                " subscription has been renewed successfully. " .
                "Your current subscription remains active until " .
                formatDate(
                    $locked_current["end_date"]
                ) .
                ". Your renewed subscription will start on " .
                formatDate(
                    $final_start_date
                ) .
                ".";


            /*
            |--------------------------------------------------------------------------
            | REDIRECT
            |--------------------------------------------------------------------------
            */

            header(
                "Location: my_subscription.php"
            );

            exit();

        }
        catch (Exception $e) {

            $conn->rollback();

            $error =
                $e->getMessage();

        }

    }

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
        Renew Subscription
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

            max-width: 850px;

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


        .plan {

            border:
                2px solid #16a34a;

            background: #f0fdf4;

            border-radius: 12px;

            padding: 25px;

            margin-top: 25px;

        }


        .label {

            color: #166534;

            font-size: 13px;

            font-weight: bold;

            margin-bottom: 10px;

        }


        .plan h2 {

            margin: 0 0 12px;

            font-size: 28px;

        }


        .price {

            font-size: 25px;

            font-weight: bold;

            margin-bottom: 20px;

        }


        .price span {

            font-size: 14px;

            color: #6b7280;

            font-weight: normal;

        }


        .details {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 12px;

            margin-top: 20px;

        }


        .detail {

            background: white;

            padding: 15px;

            border-radius: 9px;

            border:
                1px solid #e5e7eb;

        }


        .detail-label {

            color: #6b7280;

            font-size: 13px;

            margin-bottom: 5px;

        }


        .detail-value {

            font-weight: bold;

        }


        .notice {

            padding: 16px;

            border-radius: 9px;

            margin-top: 20px;

            line-height: 1.6;

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

            flex-wrap: wrap;

            margin-top: 25px;

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

            background: #16a34a;

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

            }


            .details {

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
                Renew Subscription
            </h1>

            <p>
                Review your renewal before confirming.
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
                    Unable to renew subscription
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
                Review Renewal
            </h2>


            <p>

                Your current subscription will remain
                active until its existing expiry date.

                The renewal will begin immediately after
                the current subscription ends.

            </p>



            <!-- RENEWAL PLAN -->

            <div class="plan">


                <div class="label">
                    RENEWING PLAN
                </div>


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


                <div class="details">


                    <div class="detail">

                        <div class="detail-label">
                            Current Subscription Ends
                        </div>

                        <div class="detail-value">

                            <?php

                            echo formatDate(
                                $current_subscription[
                                    "end_date"
                                ]
                            );

                            ?>

                        </div>

                    </div>


                    <div class="detail">

                        <div class="detail-label">
                            Renewal Starts
                        </div>

                        <div class="detail-value">

                            <?php

                            echo formatDate(
                                $renewal_start_date
                            );

                            ?>

                        </div>

                    </div>


                    <div class="detail">

                        <div class="detail-label">
                            Renewal Ends
                        </div>

                        <div class="detail-value">

                            <?php

                            echo formatDate(
                                $renewal_end_date
                            );

                            ?>

                        </div>

                    </div>


                    <div class="detail">

                        <div class="detail-label">
                            Member Limit
                        </div>

                        <div class="detail-value">

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

                    </div>


                </div>


            </div>



            <!-- IMPORTANT INFORMATION -->

            <div class="notice notice-warning">

                <strong>
                    Your current subscription will not be
                    changed immediately.
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

                plan remains active until

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

                The renewed subscription will start on

                <strong>

                    <?php

                    echo formatDate(
                        $renewal_start_date
                    );

                    ?>

                </strong>.

                <br><br>

                You will not lose any remaining time
                from your current subscription.

            </div>



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

                <?php if (
                    $current_subscription[
                        "member_limit"
                    ] !== null
                ): ?>


                    The renewed plan allows

                    <strong>

                        <?php

                        echo number_format(
                            (int)
                            $current_subscription[
                                "member_limit"
                            ]
                        );

                        ?>

                    </strong>

                    members.


                <?php else: ?>


                    The renewed plan allows

                    <strong>
                        unlimited members
                    </strong>.


                <?php endif; ?>

            </div>



            <!-- MEMBER LIMIT ERROR -->

            <?php if ($limit_error !== ""): ?>


                <div class="notice notice-error">

                    <strong>
                        Renewal cannot be completed.
                    </strong>

                    <br><br>

                    <?php

                    echo e(
                        $limit_error
                    );

                    ?>

                    <br><br>

                    Please reduce your gym's member count
                    before renewing this plan.

                </div>


            <!-- EXISTING SCHEDULE -->

            <?php elseif ($scheduled_subscription): ?>


                <div class="notice notice-warning">

                    <strong>
                        A subscription is already scheduled.
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

                    You cannot create another renewal
                    while a future subscription is already
                    scheduled.

                </div>


            <!-- CONFIRM FORM -->

            <?php else: ?>


                <form
                    method="POST"
                    action="subscription_renew.php"
                    onsubmit="return confirmRenewal();"
                >

                    <input
                        type="hidden"
                        name="subscription_id"
                        value="<?php
                        echo (int)
                            $current_subscription[
                                "subscription_id"
                            ];
                        ?>"
                    >


                    <div class="actions">


                        <button
                            type="submit"
                            class="button button-primary"
                        >
                            Confirm Renewal
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

function confirmRenewal()
{
    return confirm(
        "Are you sure you want to renew your " +
        <?php
        echo json_encode(
            $current_subscription["plan_name"]
        );
        ?> +
        " subscription?\n\n" +

        "Your current subscription will remain active " +
        "until " +
        <?php
        echo json_encode(
            formatDate(
                $current_subscription["end_date"]
            )
        );
        ?> +
        ".\n\n" +

        "The renewed subscription will start on " +
        <?php
        echo json_encode(
            formatDate(
                $renewal_start_date
            )
        );
        ?> +
        "."
    );
}

</script>


</body>

</html>