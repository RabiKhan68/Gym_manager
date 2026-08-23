<?php

session_start();

require_once "backend/db.php";


/*
|--------------------------------------------------------------------------
| Check gym owner login
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["owner_id"])) {

    header("Location: login.php");
    exit();

}

$owner_id = (int) $_SESSION["owner_id"];


/*
|--------------------------------------------------------------------------
| Get current subscription
|--------------------------------------------------------------------------
*/

$sql = "SELECT

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

        ORDER BY s.subscription_id DESC

        LIMIT 1";


$stmt = $conn->prepare($sql);

if (!$stmt) {

    die(
        "Database error: " .
        htmlspecialchars($conn->error)
    );

}

$stmt->bind_param(
    "i",
    $owner_id
);

$stmt->execute();

$result = $stmt->get_result();

$current_subscription =
    $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Get all available subscription plans
|--------------------------------------------------------------------------
*/

$sql = "SELECT

            subscription_plan_id,
            plan_name,
            price,
            member_limit

        FROM subscription_plans

        ORDER BY price ASC";


$result = $conn->query($sql);

if (!$result) {

    die(
        "Database error: " .
        htmlspecialchars($conn->error)
    );

}


$plans = [];

while ($row = $result->fetch_assoc()) {

    $plans[] = $row;

}


/*
|--------------------------------------------------------------------------
| Current member count
|--------------------------------------------------------------------------
|
| Used to show how many members the gym owner currently has.
|
*/

$total_members = 0;

$sql = "SELECT COUNT(*) AS total

        FROM members m

        INNER JOIN gyms g
            ON m.gym_id = g.gym_id

        WHERE g.owner_id = ?";


$stmt = $conn->prepare($sql);

if ($stmt) {

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

}


/*
|--------------------------------------------------------------------------
| Calculate subscription progress
|--------------------------------------------------------------------------
*/

$member_limit = null;
$member_percentage = 0;

if ($current_subscription) {

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
                ($total_members /
                 $member_limit) * 100;

            if ($member_percentage > 100) {

                $member_percentage = 100;

            }

        }

    }

}


/*
|--------------------------------------------------------------------------
| Calculate remaining days
|--------------------------------------------------------------------------
*/

$remaining_days = null;

if ($current_subscription) {

    $today =
        new DateTime(
            date("Y-m-d")
        );

    $end_date =
        new DateTime(
            $current_subscription[
                "end_date"
            ]
        );

    if ($end_date >= $today) {

        $remaining_days =
            $today->diff(
                $end_date
            )->days;

    } else {

        $remaining_days = 0;

    }

}


/*
|--------------------------------------------------------------------------
| Status class
|--------------------------------------------------------------------------
*/

$status_class = "";

if ($current_subscription) {

    $status =
        strtolower(
            $current_subscription[
                "status"
            ]
        );

    if ($status === "active") {

        $status_class =
            "status-active";

    }

    elseif ($status === "expired") {

        $status_class =
            "status-expired";

    }

    elseif ($status === "cancelled") {

        $status_class =
            "status-cancelled";

    }

    else {

        $status_class =
            "status-default";

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

        }


        .header h1 {

            margin: 0;

            font-size: 28px;

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

        }


        .back:hover {

            opacity: 0.85;

        }


        /* CARD */

        .card {

            background: white;

            border-radius: 12px;

            padding: 25px;

            margin-bottom: 25px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

        }


        .card h2 {

            margin-top: 0;

        }


        /* CURRENT SUBSCRIPTION */

        .current-plan {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 25px;

        }


        .plan-main {

            border: 1px solid #e5e7eb;

            border-radius: 12px;

            padding: 25px;

        }


        .plan-label {

            color: #6b7280;

            font-size: 14px;

            margin-bottom: 8px;

        }


        .plan-name {

            font-size: 32px;

            font-weight: bold;

            margin-bottom: 10px;

        }


        .price {

            font-size: 24px;

            font-weight: bold;

            margin-bottom: 20px;

        }


        .price span {

            font-size: 14px;

            font-weight: normal;

            color: #6b7280;

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

            border-radius: 8px;

        }


        .detail-label {

            color: #6b7280;

            font-size: 13px;

            margin-bottom: 5px;

        }


        .detail-value {

            font-weight: bold;

        }


        /* MEMBER USAGE */

        .usage {

            margin-top: 25px;

        }


        .usage-header {

            display: flex;

            justify-content:
                space-between;

            margin-bottom: 8px;

            font-size: 14px;

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

        }


        .unlimited {

            color: #166534;

            font-weight: bold;

        }


        /* ACTIONS */

        .actions {

            margin-top: 25px;

            display: flex;

            gap: 10px;

            flex-wrap: wrap;

        }


        .button {

            display: inline-block;

            padding: 11px 18px;

            border-radius: 8px;

            text-decoration: none;

            font-weight: bold;

            color: white;

        }


        .button-primary {

            background: #2563eb;

        }


        .button-green {

            background: #16a34a;

        }


        .button:hover {

            opacity: 0.85;

        }


        /* NO SUBSCRIPTION */

        .no-subscription {

            text-align: center;

            padding: 35px;

        }


        .no-subscription h2 {

            margin-bottom: 10px;

        }


        .no-subscription p {

            color: #6b7280;

        }


        /* AVAILABLE PLANS */

        .plans {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 20px;

        }


        .plan-card {

            border: 1px solid #e5e7eb;

            border-radius: 12px;

            padding: 22px;

            display: flex;

            flex-direction: column;

        }


        .plan-card.current {

            border: 2px solid #2563eb;

        }


        .current-label {

            display: inline-block;

            background: #dbeafe;

            color: #1d4ed8;

            padding: 5px 9px;

            border-radius: 15px;

            font-size: 11px;

            font-weight: bold;

            margin-bottom: 10px;

        }


        .plan-card h3 {

            margin: 0 0 10px;

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

            text-align: center;

        }


        .current-button {

            background: #9ca3af;

            cursor: default;

        }


        /* NOTICE */

        .notice {

            background: #fffbeb;

            border: 1px solid #fde68a;

            color: #92400e;

            padding: 15px;

            border-radius: 8px;

            margin-top: 20px;

        }


        /* MOBILE */

        @media (max-width: 1000px) {

            .plans {

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

                grid-template-columns:
                    1fr;

            }


            .plans {

                grid-template-columns:
                    1fr;

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



    <!-- CURRENT SUBSCRIPTION -->

    <div class="card">


        <h2>
            Current Subscription
        </h2>


        <?php if ($current_subscription): ?>


            <div class="current-plan">


                <!-- PLAN -->

                <div class="plan-main">

                    <div class="plan-label">
                        Your Current Plan
                    </div>


                    <div class="plan-name">

                        <?php

                        echo htmlspecialchars(
                            $current_subscription[
                                "plan_name"
                            ]
                        );

                        ?>

                    </div>


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


                    <span
                        class="status <?php echo $status_class; ?>"
                    >

                        <?php

                        echo htmlspecialchars(
                            $current_subscription[
                                "status"
                            ]
                        );

                        ?>

                    </span>


                    <div class="actions">

                        <?php if (
                            strtolower(
                                $current_subscription[
                                    "status"
                                ]
                            ) === "active"
                        ): ?>

                            <a
                                href="subscription_plans.php"
                                class="button button-primary"
                            >
                                Change Plan
                            </a>

                            <a
                                href="subscription_renew.php"
                                class="button button-green"
                            >
                                Renew Subscription
                            </a>

                        <?php else: ?>

                            <a
                                href="subscription_plans.php"
                                class="button button-primary"
                            >
                                Choose a Plan
                            </a>

                        <?php endif; ?>

                    </div>

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

                                echo date(
                                    "d M Y",
                                    strtotime(
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

                                echo date(
                                    "d M Y",
                                    strtotime(
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

                                    echo $remaining_days;

                                    echo
                                        $remaining_days === 1
                                        ? " day"
                                        : " days";

                                } else {

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

                                    echo $member_limit;

                                } else {

                                    echo "Unlimited";

                                }

                                ?>

                            </div>

                        </div>


                    </div>



                    <!-- MEMBER USAGE -->

                    <div class="usage">


                        <?php if (
                            $member_limit !== null
                        ): ?>


                            <div class="usage-header">

                                <span>
                                    Member Usage
                                </span>

                                <strong>

                                    <?php

                                    echo $total_members;

                                    ?>

                                    /

                                    <?php

                                    echo $member_limit;

                                    ?>

                                </strong>

                            </div>


                            <div class="progress">

                                <div
                                    class="progress-bar"
                                    style="width: <?php echo $member_percentage; ?>%;"
                                ></div>

                            </div>


                            <?php if (
                                $total_members >=
                                $member_limit
                            ): ?>

                                <div class="notice">

                                    Your gym has reached the
                                    member limit for this plan.
                                    Upgrade your subscription to
                                    add more members.

                                </div>

                            <?php elseif (
                                $member_percentage >= 80
                            ): ?>

                                <div class="notice">

                                    You are approaching your
                                    plan's member limit.

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


            <div class="no-subscription">

                <h2>
                    No Active Subscription
                </h2>

                <p>
                    You do not currently have a subscription.
                    Choose a plan below to get started.
                </p>


                <div class="actions">

                    <a
                        href="#available-plans"
                        class="button button-primary"
                    >
                        View Available Plans
                    </a>

                </div>

            </div>


        <?php endif; ?>


    </div>



    <!-- AVAILABLE PLANS -->

    <div
        class="card"
        id="available-plans"
    >

        <h2>
            Available Subscription Plans
        </h2>

        <p style="color:#6b7280;">

            Choose the plan that best fits your gym.

        </p>


        <?php if (
            count($plans) > 0
        ): ?>


            <div class="plans">


                <?php foreach (
                    $plans as $plan
                ): ?>


                    <?php

                    $is_current =
                        $current_subscription &&
                        (int)
                        $current_subscription[
                            "subscription_plan_id"
                        ] ===
                        (int)
                        $plan[
                            "subscription_plan_id"
                        ];

                    ?>


                    <div
                        class="plan-card <?php
                            echo $is_current
                                ? "current"
                                : "";
                        ?>"
                    >


                        <?php if (
                            $is_current
                        ): ?>

                            <span class="current-label">

                                CURRENT PLAN

                            </span>

                        <?php endif; ?>


                        <h3>

                            <?php

                            echo htmlspecialchars(
                                $plan[
                                    "plan_name"
                                ]
                            );

                            ?>

                        </h3>


                        <div class="plan-price">

                            Rs.

                            <?php

                            echo number_format(
                                $plan[
                                    "price"
                                ],
                                2
                            );

                            ?>

                            <span>
                                / month
                            </span>

                        </div>


                        <div class="plan-limit">

                            <?php if (
                                $plan[
                                    "member_limit"
                                ] !== null
                            ): ?>

                                Up to

                                <strong>

                                    <?php

                                    echo (int)
                                        $plan[
                                            "member_limit"
                                        ];

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


                        <?php if (
                            $is_current
                        ): ?>

                            <span
                                class="button current-button"
                            >

                                Current Plan

                            </span>

                        <?php else: ?>

                            <a
                                href="subscription_change.php?plan_id=<?php echo (int)$plan["subscription_plan_id"]; ?>"
                                class="button button-primary"
                            >

                                Choose Plan

                            </a>

                        <?php endif; ?>


                    </div>


                <?php endforeach; ?>


            </div>


        <?php else: ?>


            <div class="no-subscription">

                <h3>
                    No Plans Available
                </h3>

                <p>
                    No subscription plans have been created
                    by the administrator yet.
                </p>

            </div>


        <?php endif; ?>


    </div>


</div>


</body>

</html>