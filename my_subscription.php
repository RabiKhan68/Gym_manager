<?php

date_default_timezone_set("Asia/Karachi");

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

/*
|--------------------------------------------------------------------------
| APPLICATION TIMEZONE
|--------------------------------------------------------------------------
|
| The application should use Pakistan time regardless of the
| Render/PHP server timezone.
|
*/

$app_timezone = new DateTimeZone("Asia/Karachi");

$today = new DateTime(
    "now",
    $app_timezone
);

$today = $today->format("Y-m-d");

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


function format_date($date)
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


function format_price($price)
{
    return number_format(
        (float) $price,
        2
    );
}


/*
|--------------------------------------------------------------------------
| SESSION MESSAGES
|--------------------------------------------------------------------------
*/

$success_message = "";

$payment_message = "";

$payment_error = "";


/*
|--------------------------------------------------------------------------
| GENERAL SUBSCRIPTION SUCCESS
|--------------------------------------------------------------------------
*/

if (
    isset(
        $_SESSION["subscription_change_success"]
    )
) {

    $success_message =
        $_SESSION[
            "subscription_change_success"
        ];

    unset(
        $_SESSION[
            "subscription_change_success"
        ]
    );

}


/*
|--------------------------------------------------------------------------
| PAYMENT SUCCESS
|--------------------------------------------------------------------------
*/

if (
    isset(
        $_SESSION["payment_success"]
    )
) {

    $payment_message =
        $_SESSION["payment_success"];

    unset(
        $_SESSION["payment_success"]
    );

}


/*
|--------------------------------------------------------------------------
| PAYMENT ERROR
|--------------------------------------------------------------------------
*/

if (
    isset(
        $_SESSION["payment_error"]
    )
) {

    $payment_error =
        $_SESSION["payment_error"];

    unset(
        $_SESSION["payment_error"]
    );

}


/*
|--------------------------------------------------------------------------
| IMPORTANT
|--------------------------------------------------------------------------
|
| THIS PAGE IS READ-ONLY.
|
| It does NOT:
|
| - create subscriptions
| - activate subscriptions
| - expire subscriptions
| - cancel subscriptions
| - change subscription status
|
| Subscription activation should be handled by:
|
|     subscription_cron.php
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| GET CURRENT ACTIVE SUBSCRIPTION
|--------------------------------------------------------------------------
|
| A subscription is considered CURRENT when:
|
| status      = active
| start_date <= today
| end_date   >= today
|
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

    ORDER BY
        s.end_date DESC,
        s.subscription_id DESC

    LIMIT 1
";


$stmt = $conn->prepare($sql);


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
| Only FUTURE scheduled subscriptions are displayed.
|
| The earliest scheduled subscription is the next subscription.
|
|--------------------------------------------------------------------------
*/

$upcoming_subscription = null;


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


$stmt = $conn->prepare($sql);


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
        "Unable to load upcoming subscription."
    );

}


$result =
    $stmt->get_result();


$upcoming_subscription =
    $result->fetch_assoc();


$stmt->close();


/*
|--------------------------------------------------------------------------
| GET AVAILABLE SUBSCRIPTION PLANS
|--------------------------------------------------------------------------
*/

$plans = [];


$sql = "
    SELECT

        subscription_plan_id,
        plan_name,
        price,
        member_limit

    FROM subscription_plans

    ORDER BY
        price ASC,
        subscription_plan_id ASC
";


$result =
    $conn->query($sql);


if (!$result) {

    die(
        "Database error: " .
        e($conn->error)
    );

}


while (
    $row =
    $result->fetch_assoc()
) {

    $plans[] = $row;

}


/*
|--------------------------------------------------------------------------
| GET TOTAL MEMBERS
|--------------------------------------------------------------------------
*/

$total_members = 0;


$sql = "
    SELECT
        COUNT(*) AS total

    FROM members m

    INNER JOIN gyms g
        ON m.gym_id = g.gym_id

    WHERE g.owner_id = ?
";


$stmt = $conn->prepare($sql);


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
    (int) (
        $row["total"] ?? 0
    );


$stmt->close();


/*
|--------------------------------------------------------------------------
| CURRENT PLAN CALCULATIONS
|--------------------------------------------------------------------------
*/

$member_limit = null;

$member_percentage = 0;

$remaining_days = null;


if ($current_subscription) {


    /*
    |--------------------------------------------------------------------------
    | MEMBER LIMIT
    |--------------------------------------------------------------------------
    */

    if (
        $current_subscription[
            "member_limit"
        ] !== null
    ) {

        $member_limit =
            (int)
            $current_subscription[
                "member_limit"
            ];


        if ($member_limit > 0) {

            $member_percentage =
                (
                    $total_members /
                    $member_limit
                ) * 100;


            if (
                $member_percentage > 100
            ) {

                $member_percentage = 100;

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | REMAINING DAYS
    |--------------------------------------------------------------------------
    */

    try {

        $today_date =
            new DateTime($today);


        $end_date =
            new DateTime(
                $current_subscription[
                    "end_date"
                ]
            );


        if (
            $end_date >=
            $today_date
        ) {

            /*
            | +1 means both today and the expiry
            | date are counted.
            */

            $remaining_days =
                $today_date->diff(
                    $end_date
                )->days + 1;

        }
        else {

            $remaining_days = 0;

        }

    }
    catch (Exception $e) {

        $remaining_days = null;

    }

}


/*
|--------------------------------------------------------------------------
| STATUS CLASS
|--------------------------------------------------------------------------
|
| Because the current subscription query only returns active
| subscriptions, this will normally be status-active.
|
|--------------------------------------------------------------------------
*/

$status_class = "status-default";


if ($current_subscription) {

    switch (
        strtolower(
            $current_subscription["status"]
        )
    ) {

        case "active":

            $status_class =
                "status-active";

            break;


        case "expired":

            $status_class =
                "status-expired";

            break;


        case "cancelled":

            $status_class =
                "status-cancelled";

            break;

    }

}


/*
|--------------------------------------------------------------------------
| UPCOMING MEMBER LIMIT WARNING
|--------------------------------------------------------------------------
*/

$upcoming_member_warning = false;


if (
    $upcoming_subscription &&
    $upcoming_subscription[
        "member_limit"
    ] !== null
) {

    $upcoming_limit =
        (int)
        $upcoming_subscription[
            "member_limit"
        ];


    if (
        $total_members >
        $upcoming_limit
    ) {

        $upcoming_member_warning = true;

    }

}


/*
|--------------------------------------------------------------------------
| CAN RENEW
|--------------------------------------------------------------------------
|
| Renewal is allowed when:
|
| - current subscription exists
| - no upcoming subscription exists
|
|--------------------------------------------------------------------------
*/

$can_renew = (
    $current_subscription &&
    !$upcoming_subscription
);


/*
|--------------------------------------------------------------------------
| CAN CHANGE PLAN
|--------------------------------------------------------------------------
|
| Plan change is allowed when:
|
| - current subscription exists
| - no upcoming subscription exists
|
|--------------------------------------------------------------------------
*/

$can_change_plan = (
    $current_subscription &&
    !$upcoming_subscription
);


/*
|--------------------------------------------------------------------------
| HAS SUBSCRIPTION HISTORY
|--------------------------------------------------------------------------
*/

$has_subscription_history = false;


$sql = "
    SELECT
        subscription_id

    FROM gym_owner_subscriptions

    WHERE owner_id = ?

    LIMIT 1
";


$stmt = $conn->prepare($sql);


if ($stmt) {

    $stmt->bind_param(
        "i",
        $owner_id
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    $has_subscription_history =
        $result->num_rows > 0;


    $stmt->close();

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
        My Subscription
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

            max-width: 1200px;

            margin: auto;

            padding: 30px;

        }


        /* HEADER */

        .header {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            margin-bottom: 25px;

            gap: 20px;

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

            padding: 10px 18px;

            background: #111827;

            color: white;

            text-decoration: none;

            border-radius: 8px;

            font-weight: bold;

            white-space: nowrap;

        }


        .back:hover {

            opacity: .9;

        }


        /* CARD */

        .card {

            background: white;

            border-radius: 14px;

            padding: 25px;

            margin-bottom: 25px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,.06);

        }


        .card h2 {

            margin-top: 0;

            margin-bottom: 8px;

        }


        .muted {

            color: #6b7280;

        }


        /* NOTICES */

        .notice {

            padding: 15px;

            border-radius: 9px;

            margin-top: 20px;

            line-height: 1.5;

        }


        .notice-success {

            background: #dcfce7;

            border:
                1px solid #bbf7d0;

            color: #166534;

        }


        .notice-warning {

            background: #fffbeb;

            border:
                1px solid #fde68a;

            color: #92400e;

        }


        .notice-danger {

            background: #fee2e2;

            border:
                1px solid #fecaca;

            color: #991b1b;

        }


        .notice-info {

            background: #eff6ff;

            border:
                1px solid #bfdbfe;

            color: #1e40af;

        }


        /* CURRENT SUBSCRIPTION */

        .current-plan {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 25px;

            margin-top: 20px;

        }


        .plan-main {

            border:
                1px solid #e5e7eb;

            border-radius: 12px;

            padding: 25px;

        }


        .plan-label {

            color: #6b7280;

            font-size: 13px;

            font-weight: bold;

            margin-bottom: 8px;

        }


        .plan-name {

            font-size: 32px;

            font-weight: bold;

            margin-bottom: 10px;

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


        /* STATUS */

        .status {

            display: inline-block;

            padding: 7px 13px;

            border-radius: 20px;

            font-size: 13px;

            font-weight: bold;

            text-transform: capitalize;

        }


        .status-active {

            background: #dcfce7;

            color: #166534;

        }


        .status-expired {

            background: #fee2e2;

            color: #991b1b;

        }


        .status-cancelled {

            background: #e5e7eb;

            color: #374151;

        }


        .status-default {

            background: #e5e7eb;

            color: #374151;

        }


        /* DETAILS */

        .details {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 15px;

        }


        .detail {

            background: #f8fafc;

            padding: 15px;

            border-radius: 9px;

        }


        .detail-label {

            color: #6b7280;

            font-size: 13px;

            margin-bottom: 5px;

        }


        .detail-value {

            font-weight: bold;

        }


        /* USAGE */

        .usage {

            margin-top: 25px;

        }


        .usage-header {

            display: flex;

            justify-content:
                space-between;

            margin-bottom: 8px;

        }


        .progress {

            width: 100%;

            height: 12px;

            background: #e5e7eb;

            border-radius: 20px;

            overflow: hidden;

        }


        .progress-bar {

            height: 100%;

            background: #2563eb;

            border-radius: 20px;

            transition:
                width .3s ease;

        }


        .unlimited {

            color: #166534;

            font-weight: bold;

        }


        /* BUTTONS */

        .actions {

            display: flex;

            gap: 10px;

            flex-wrap: wrap;

            margin-top: 25px;

        }


        .button {

            display: inline-block;

            padding: 11px 18px;

            border-radius: 8px;

            text-decoration: none;

            font-weight: bold;

            border: none;

            cursor: pointer;

            text-align: center;

        }


        .button-primary {

            background: #2563eb;

            color: white;

        }


        .button-green {

            background: #16a34a;

            color: white;

        }


        .button-gray {

            background: #e5e7eb;

            color: #374151;

        }


        .button:hover {

            opacity: .88;

        }


        /* UPCOMING */

        .upcoming {

            background: #eff6ff;

            border:
                1px solid #bfdbfe;

            border-radius: 12px;

            padding: 20px;

        }


        .upcoming-header {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            margin-bottom: 18px;

            gap: 15px;

        }


        .upcoming-header h3 {

            margin: 0;

        }


        .scheduled {

            background: #dbeafe;

            color: #1d4ed8;

            padding: 6px 10px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: bold;

            white-space: nowrap;

        }


        .upcoming-grid {

            display: grid;

            grid-template-columns:
                repeat(5, 1fr);

            gap: 12px;

        }


        .upcoming-item {

            background: white;

            padding: 14px;

            border-radius: 8px;

        }


        .upcoming-label {

            color: #6b7280;

            font-size: 12px;

            margin-bottom: 5px;

        }


        .upcoming-value {

            font-weight: bold;

        }


        /* PLANS */

        .plans {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 20px;

            margin-top: 20px;

        }


        .plan-card {

            border:
                1px solid #e5e7eb;

            border-radius: 12px;

            padding: 22px;

            display: flex;

            flex-direction: column;

            min-height: 250px;

        }


        .plan-card.current {

            border:
                2px solid #2563eb;

            background: #f8fbff;

        }


        .plan-card.upcoming {

            border:
                2px solid #60a5fa;

            background: #eff6ff;

        }


        .plan-badge {

            display: inline-block;

            width: fit-content;

            padding: 5px 9px;

            border-radius: 20px;

            font-size: 11px;

            font-weight: bold;

            margin-bottom: 12px;

        }


        .badge-current {

            background: #dbeafe;

            color: #1d4ed8;

        }


        .badge-upcoming {

            background: #e0f2fe;

            color: #0369a1;

        }


        .plan-card h3 {

            margin:
                0 0 10px;

            font-size: 22px;

        }


        .plan-price {

            font-size: 24px;

            font-weight: bold;

            margin-bottom: 15px;

        }


        .plan-price span {

            font-size: 13px;

            color: #6b7280;

            font-weight: normal;

        }


        .plan-limit {

            color: #4b5563;

            margin-bottom: 20px;

        }


        .plan-card .button {

            margin-top: auto;

        }


        /* EMPTY */

        .empty {

            text-align: center;

            padding: 40px 20px;

        }


        .empty h2 {

            margin-bottom: 10px;

        }


        /* RESPONSIVE */

        @media (max-width: 1050px) {

            .plans {

                grid-template-columns:
                    repeat(2, 1fr);

            }


            .upcoming-grid {

                grid-template-columns:
                    repeat(2, 1fr);

            }

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


            .current-plan {

                grid-template-columns: 1fr;

            }


            .details {

                grid-template-columns: 1fr;

            }


            .plans {

                grid-template-columns: 1fr;

            }


            .upcoming-grid {

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
                My Subscription
            </h1>

            <p>
                Manage your gym platform subscription
            </p>

        </div>


        <a
            href="dashboard.php"
            class="back"
        >
            ← Dashboard
        </a>

    </div>


    <!-- SUCCESS MESSAGE -->

    <?php if ($success_message !== ""): ?>

        <div class="notice notice-success">

            <?php
            echo e($success_message);
            ?>

        </div>

    <?php endif; ?>


    <!-- PAYMENT SUCCESS -->

    <?php if ($payment_message !== ""): ?>

        <div class="notice notice-success">

            <?php
            echo e($payment_message);
            ?>

        </div>

    <?php endif; ?>


    <!-- PAYMENT ERROR -->

    <?php if ($payment_error !== ""): ?>

        <div class="notice notice-danger">

            <?php
            echo e($payment_error);
            ?>

        </div>

    <?php endif; ?>


    <!-- CURRENT SUBSCRIPTION -->

    <div class="card">

        <h2>
            Current Subscription
        </h2>

        <p class="muted">

            Your currently active subscription
            and member usage.

        </p>


        <?php if ($current_subscription): ?>


            <div class="current-plan">


                <!-- PLAN SUMMARY -->

                <div class="plan-main">

                    <div class="plan-label">
                        CURRENT PLAN
                    </div>


                    <div class="plan-name">

                        <?php
                        echo e(
                            $current_subscription[
                                "plan_name"
                            ]
                        );
                        ?>

                    </div>


                    <div class="price">

                        Rs.

                        <?php
                        echo format_price(
                            $current_subscription[
                                "price"
                            ]
                        );
                        ?>

                        <span>
                            / month
                        </span>

                    </div>


                    <span
                        class="status <?php
                        echo e($status_class);
                        ?>"
                    >

                        <?php
                        echo e(
                            $current_subscription[
                                "status"
                            ]
                        );
                        ?>

                    </span>


                    <!-- ACTIONS -->

                    <div class="actions">


                        <?php if ($can_change_plan): ?>

                            <a
                                href="#available-plans"
                                class="button button-primary"
                            >
                                Change Plan
                            </a>

                        <?php endif; ?>


                        <?php if ($can_renew): ?>

                            <a
                                href="subscription_renew.php"
                                class="button button-green"
                            >
                                Renew Subscription
                            </a>

                        <?php endif; ?>


                    </div>


                    <?php if ($upcoming_subscription): ?>

                        <div class="notice notice-info">

                            You already have an upcoming
                            subscription scheduled.

                            Your current plan will remain
                            active until its expiry date.

                        </div>

                    <?php endif; ?>


                </div>


                <!-- DETAILS -->

                <div>

                    <div class="details">


                        <div class="detail">

                            <div class="detail-label">
                                Start Date
                            </div>

                            <div class="detail-value">

                                <?php
                                echo e(
                                    format_date(
                                        $current_subscription[
                                            "start_date"
                                        ]
                                    )
                                );
                                ?>

                            </div>

                        </div>


                        <div class="detail">

                            <div class="detail-label">
                                Expiry Date
                            </div>

                            <div class="detail-value">

                                <?php
                                echo e(
                                    format_date(
                                        $current_subscription[
                                            "end_date"
                                        ]
                                    )
                                );
                                ?>

                            </div>

                        </div>


                        <div class="detail">

                            <div class="detail-label">
                                Days Remaining
                            </div>

                            <div class="detail-value">

                                <?php

                                if (
                                    $remaining_days !== null
                                ) {

                                    echo number_format(
                                        $remaining_days
                                    );

                                    echo (
                                        $remaining_days === 1
                                        ? " day"
                                        : " days"
                                    );

                                }
                                else {

                                    echo "-";

                                }

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
                                    $member_limit !== null
                                ) {

                                    echo number_format(
                                        $member_limit
                                    );

                                }
                                else {

                                    echo "Unlimited";

                                }

                                ?>

                            </div>

                        </div>


                    </div>


                    <!-- MEMBER USAGE -->

                    <div class="usage">


                        <?php if (
                            $member_limit !== null &&
                            $member_limit > 0
                        ): ?>


                            <div class="usage-header">

                                <span>
                                    Member Usage
                                </span>

                                <strong>

                                    <?php
                                    echo number_format(
                                        $total_members
                                    );
                                    ?>

                                    /

                                    <?php
                                    echo number_format(
                                        $member_limit
                                    );
                                    ?>

                                </strong>

                            </div>


                            <div class="progress">

                                <div
                                    class="progress-bar"
                                    style="width: <?php echo (float) $member_percentage; ?>%;"
                                ></div>

                            </div>


                            <?php if (
                                $total_members >=
                                $member_limit
                            ): ?>


                                <div class="notice notice-danger">

                                    <strong>
                                        Member limit reached.
                                    </strong>

                                    Your gym currently has

                                    <?php
                                    echo number_format(
                                        $total_members
                                    );
                                    ?>

                                    members, which is at or
                                    above your plan limit.

                                </div>


                            <?php elseif (
                                $member_percentage >= 80
                            ): ?>


                                <div class="notice notice-warning">

                                    <strong>
                                        You are approaching
                                        your member limit.
                                    </strong>

                                    You are currently using

                                    <?php
                                    echo number_format(
                                        $total_members
                                    );
                                    ?>

                                    of

                                    <?php
                                    echo number_format(
                                        $member_limit
                                    );
                                    ?>

                                    available member slots.

                                </div>


                            <?php endif; ?>


                        <?php else: ?>


                            <div class="unlimited">

                                ✓ Unlimited members

                            </div>


                        <?php endif; ?>


                    </div>


                </div>


            </div>


        <?php else: ?>


            <div class="empty">

                <h2>
                    No Active Subscription
                </h2>

                <p class="muted">

                    You currently do not have an active
                    subscription.

                    <?php if (
                        $upcoming_subscription
                    ): ?>

                        Your upcoming subscription is
                        shown below.

                    <?php else: ?>

                        Choose a plan below to get started.

                    <?php endif; ?>

                </p>


                <?php if (
                    !$upcoming_subscription
                ): ?>

                    <div class="actions">

                        <a
                            href="#available-plans"
                            class="button button-primary"
                        >
                            View Available Plans
                        </a>

                    </div>

                <?php endif; ?>


            </div>


        <?php endif; ?>


    </div>


    <!-- UPCOMING SUBSCRIPTION -->

    <?php if ($upcoming_subscription): ?>


        <div class="card">

            <h2>
                Upcoming Subscription
            </h2>

            <p class="muted">

                This subscription will automatically
                become active on its scheduled start date.

            </p>


            <div class="upcoming">


                <div class="upcoming-header">

                    <h3>

                        <?php
                        echo e(
                            $upcoming_subscription[
                                "plan_name"
                            ]
                        );
                        ?>

                    </h3>


                    <span class="scheduled">
                        SCHEDULED
                    </span>

                </div>


                <div class="upcoming-grid">


                    <div class="upcoming-item">

                        <div class="upcoming-label">
                            Price
                        </div>

                        <div class="upcoming-value">

                            Rs.

                            <?php
                            echo format_price(
                                $upcoming_subscription[
                                    "price"
                                ]
                            );
                            ?>

                            / month

                        </div>

                    </div>


                    <div class="upcoming-item">

                        <div class="upcoming-label">
                            Starts
                        </div>

                        <div class="upcoming-value">

                            <?php
                            echo e(
                                format_date(
                                    $upcoming_subscription[
                                        "start_date"
                                    ]
                                )
                            );
                            ?>

                        </div>

                    </div>


                    <div class="upcoming-item">

                        <div class="upcoming-label">
                            Ends
                        </div>

                        <div class="upcoming-value">

                            <?php
                            echo e(
                                format_date(
                                    $upcoming_subscription[
                                        "end_date"
                                    ]
                                )
                            );
                            ?>

                        </div>

                    </div>


                    <div class="upcoming-item">

                        <div class="upcoming-label">
                            Member Limit
                        </div>

                        <div class="upcoming-value">

                            <?php

                            if (
                                $upcoming_subscription[
                                    "member_limit"
                                ] !== null
                            ) {

                                echo number_format(
                                    (int)
                                    $upcoming_subscription[
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


                    <div class="upcoming-item">

                        <div class="upcoming-label">
                            Current Members
                        </div>

                        <div class="upcoming-value">

                            <?php
                            echo number_format(
                                $total_members
                            );
                            ?>

                        </div>

                    </div>


                </div>


                <?php if (
                    $upcoming_member_warning
                ): ?>


                    <div class="notice notice-danger">

                        <strong>
                            Member limit warning.
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

                        members, but the upcoming

                        <strong>
                            <?php
                            echo e(
                                $upcoming_subscription[
                                    "plan_name"
                                ]
                            );
                            ?>
                        </strong>

                        plan allows only

                        <strong>
                            <?php
                            echo number_format(
                                (int)
                                $upcoming_subscription[
                                    "member_limit"
                                ]
                            );
                            ?>
                        </strong>

                        members.

                        <br><br>

                        The subscription activation process
                        should verify the member limit again
                        before activating this plan.

                    </div>


                <?php else: ?>


                    <div class="notice notice-info">

                        <?php if (
                            $current_subscription
                        ): ?>

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

                            subscription remains active
                            until

                            <strong>
                                <?php
                                echo e(
                                    format_date(
                                        $current_subscription[
                                            "end_date"
                                        ]
                                    )
                                );
                                ?>
                            </strong>.

                            The scheduled plan will then
                            become active automatically.

                        <?php else: ?>

                            Your scheduled subscription
                            will become active on

                            <strong>
                                <?php
                                echo e(
                                    format_date(
                                        $upcoming_subscription[
                                            "start_date"
                                        ]
                                    )
                                );
                                ?>
                            </strong>.

                        <?php endif; ?>

                    </div>


                <?php endif; ?>


            </div>

        </div>


    <?php endif; ?>


    <!-- AVAILABLE PLANS -->

    <div
        class="card"
        id="available-plans"
    >

        <h2>
            Available Subscription Plans
        </h2>


        <p class="muted">

            <?php if (
                $current_subscription
            ): ?>

                Choose another plan to schedule a
                plan change after your current
                subscription expires.

            <?php elseif (
                $upcoming_subscription
            ): ?>

                You already have a subscription scheduled.

            <?php else: ?>

                Choose the plan that best fits your gym.

            <?php endif; ?>

        </p>


        <?php if (count($plans) > 0): ?>


            <div class="plans">


                <?php foreach (
                    $plans as $plan
                ): ?>


                    <?php

                    $plan_id =
                        (int)
                        $plan[
                            "subscription_plan_id"
                        ];


                    $is_current =
                        $current_subscription &&
                        (
                            (int)
                            $current_subscription[
                                "subscription_plan_id"
                            ]
                            ===
                            $plan_id
                        );


                    $is_upcoming =
                        $upcoming_subscription &&
                        (
                            (int)
                            $upcoming_subscription[
                                "subscription_plan_id"
                            ]
                            ===
                            $plan_id
                        );

                    ?>


                    <div
                        class="plan-card <?php

                            if ($is_current) {

                                echo "current";

                            }
                            elseif ($is_upcoming) {

                                echo "upcoming";

                            }

                        ?>"
                    >


                        <?php if ($is_current): ?>


                            <span
                                class="plan-badge badge-current"
                            >
                                CURRENT PLAN
                            </span>


                        <?php elseif ($is_upcoming): ?>


                            <span
                                class="plan-badge badge-upcoming"
                            >
                                UPCOMING PLAN
                            </span>


                        <?php endif; ?>


                        <h3>

                            <?php
                            echo e(
                                $plan["plan_name"]
                            );
                            ?>

                        </h3>


                        <div class="plan-price">

                            Rs.

                            <?php
                            echo format_price(
                                $plan["price"]
                            );
                            ?>

                            <span>
                                / month
                            </span>

                        </div>


                        <div class="plan-limit">

                            <?php if (
                                $plan["member_limit"]
                                !== null
                            ): ?>

                                Up to

                                <strong>

                                    <?php
                                    echo number_format(
                                        (int)
                                        $plan[
                                            "member_limit"
                                        ]
                                    );
                                    ?>

                                </strong>

                                members

                            <?php else: ?>

                                <strong>
                                    Unlimited
                                </strong>

                                members

                            <?php endif; ?>

                        </div>


                        <!-- CURRENT PLAN -->

                        <?php if ($is_current): ?>


                            <span
                                class="button button-gray"
                            >
                                Current Plan
                            </span>


                        <!-- UPCOMING PLAN -->

                        <?php elseif ($is_upcoming): ?>


                            <span
                                class="button button-gray"
                            >
                                Already Scheduled
                            </span>


                        <!-- ACTIVE SUBSCRIPTION -->

                        <?php elseif (
                            $current_subscription
                        ): ?>


                            <?php if (
                                $can_change_plan
                            ): ?>


                                <a
                                    href="subscription_change.php?plan_id=<?php echo $plan_id; ?>"
                                    class="button button-primary"
                                >
                                    Schedule Plan
                                </a>


                            <?php endif; ?>


                        <!-- NO ACTIVE SUBSCRIPTION -->

                        <?php elseif (
                            !$upcoming_subscription
                        ): ?>


                            <a
                                href="subscription_change.php?plan_id=<?php echo $plan_id; ?>"
                                class="button button-primary"
                            >
                                Choose Plan
                            </a>


                        <?php endif; ?>


                    </div>


                <?php endforeach; ?>


            </div>


        <?php else: ?>


            <div class="empty">

                <h3>
                    No Plans Available
                </h3>

                <p class="muted">

                    No subscription plans have been created
                    by the administrator yet.

                </p>

            </div>


        <?php endif; ?>


    </div>


</div>


</body>

</html>