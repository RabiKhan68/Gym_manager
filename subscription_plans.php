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
| Get all subscription plans
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            subscription_plan_id,
            plan_name,
            price,
            member_limit,
            created_at

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
| Current plan ID
|--------------------------------------------------------------------------
*/

$current_plan_id = null;

if ($current_subscription) {

    $current_plan_id =
        (int)
        $current_subscription[
            "subscription_plan_id"
        ];

}


/*
|--------------------------------------------------------------------------
| Current subscription status
|--------------------------------------------------------------------------
*/

$current_status = null;

if ($current_subscription) {

    $current_status =
        strtolower(
            $current_subscription["status"]
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
        Subscription Plans
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

            margin-bottom: 30px;

        }


        .header h1 {

            margin: 0;

            font-size: 30px;

        }


        .header p {

            margin: 7px 0 0;

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

            opacity: 0.85;

        }


        /* CURRENT PLAN NOTICE */

        .current-notice {

            background: #eff6ff;

            border: 1px solid #bfdbfe;

            color: #1e40af;

            padding: 18px 20px;

            border-radius: 10px;

            margin-bottom: 25px;

        }


        .current-notice strong {

            font-weight: bold;

        }


        /* PLANS */

        .plans {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 20px;

        }


        .plan-card {

            background: white;

            border-radius: 14px;

            padding: 25px;

            border: 1px solid #e5e7eb;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

            display: flex;

            flex-direction: column;

            min-height: 330px;

            position: relative;

            transition:
                transform 0.2s,
                box-shadow 0.2s;

        }


        .plan-card:hover {

            transform:
                translateY(-3px);

            box-shadow:
                0 8px 20px
                rgba(0,0,0,0.10);

        }


        .plan-card.current {

            border:
                2px solid #2563eb;

        }


        /* CURRENT LABEL */

        .current-label {

            position: absolute;

            top: 15px;

            right: 15px;

            background: #dbeafe;

            color: #1d4ed8;

            padding: 5px 9px;

            border-radius: 15px;

            font-size: 11px;

            font-weight: bold;

        }


        .plan-name {

            font-size: 25px;

            font-weight: bold;

            margin-bottom: 15px;

            padding-right: 80px;

        }


        .plan-price {

            font-size: 30px;

            font-weight: bold;

            margin-bottom: 20px;

        }


        .plan-price span {

            font-size: 14px;

            color: #6b7280;

            font-weight: normal;

        }


        .plan-details {

            margin-bottom: 25px;

        }


        .detail {

            padding: 12px 0;

            border-bottom:
                1px solid #e5e7eb;

        }


        .detail:last-child {

            border-bottom: none;

        }


        .detail-label {

            color: #6b7280;

            font-size: 13px;

            margin-bottom: 4px;

        }


        .detail-value {

            font-weight: bold;

            font-size: 16px;

        }


        /* BUTTON */

        .choose-button {

            display: block;

            width: 100%;

            padding: 12px;

            margin-top: auto;

            border-radius: 8px;

            background: #2563eb;

            color: white;

            text-decoration: none;

            text-align: center;

            font-weight: bold;

        }


        .choose-button:hover {

            background: #1d4ed8;

        }


        .current-button {

            background: #9ca3af;

            cursor: default;

        }


        .current-button:hover {

            background: #9ca3af;

        }


        /* EXPIRED */

        .expired-notice {

            background: #fff7ed;

            border: 1px solid #fed7aa;

            color: #9a3412;

            padding: 18px 20px;

            border-radius: 10px;

            margin-bottom: 25px;

        }


        /* EMPTY */

        .empty {

            background: white;

            border-radius: 12px;

            padding: 50px;

            text-align: center;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

        }


        .empty h2 {

            margin-top: 0;

        }


        .empty p {

            color: #6b7280;

        }


        /* MOBILE */

        @media (max-width: 1000px) {

            .plans {

                grid-template-columns:
                    repeat(2, 1fr);

            }

        }


        @media (max-width: 650px) {

            .container {

                padding: 15px;

            }


            .header {

                flex-direction: column;

                align-items: flex-start;

                gap: 15px;

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
                Subscription Plans
            </h1>

            <p>
                Choose the subscription plan
                that fits your gym.
            </p>

        </div>


        <a
            href="my_subscription.php"
            class="back"
        >

            ← My Subscription

        </a>

    </div>



    <!-- CURRENT SUBSCRIPTION -->

    <?php if ($current_subscription): ?>


        <?php if ($current_status === "active"): ?>

            <div class="current-notice">

                You are currently subscribed to

                <strong>
                    <?php

                    echo htmlspecialchars(
                        $current_subscription[
                            "plan_name"
                        ]
                    );

                    ?>
                </strong>

                at

                <strong>

                    Rs.

                    <?php

                    echo number_format(
                        $current_subscription[
                            "price"
                        ],
                        2
                    );

                    ?>

                    / month

                </strong>.

                Choose another plan below
                to change your subscription.

            </div>


        <?php elseif ($current_status === "expired"): ?>


            <div class="expired-notice">

                Your current subscription has expired.

                Please choose a plan below
                to continue using the platform.

            </div>


        <?php elseif ($current_status === "cancelled"): ?>


            <div class="expired-notice">

                Your previous subscription was cancelled.

                Please choose a new plan below.

            </div>


        <?php endif; ?>


    <?php endif; ?>



    <!-- PLANS -->

    <?php if (count($plans) > 0): ?>


        <div class="plans">


            <?php foreach ($plans as $plan): ?>


                <?php

                $plan_id =
                    (int)
                    $plan[
                        "subscription_plan_id"
                    ];


                $is_current =
                    $current_plan_id !== null &&
                    $current_plan_id === $plan_id &&
                    $current_status === "active";

                ?>


                <div
                    class="plan-card <?php
                        echo $is_current
                            ? "current"
                            : "";
                    ?>"
                >


                    <?php if ($is_current): ?>

                        <div class="current-label">

                            CURRENT PLAN

                        </div>

                    <?php endif; ?>


                    <!-- PLAN NAME -->

                    <div class="plan-name">

                        <?php

                        echo htmlspecialchars(
                            $plan["plan_name"]
                        );

                        ?>

                    </div>


                    <!-- PRICE -->

                    <div class="plan-price">

                        Rs.

                        <?php

                        echo number_format(
                            (float)
                            $plan["price"],
                            2
                        );

                        ?>

                        <span>
                            / month
                        </span>

                    </div>


                    <!-- DETAILS -->

                    <div class="plan-details">


                        <div class="detail">

                            <div class="detail-label">

                                Member Limit

                            </div>


                            <div class="detail-value">

                                <?php

                                if (
                                    $plan[
                                        "member_limit"
                                    ] !== null
                                ) {

                                    echo "Up to ";

                                    echo (int)
                                        $plan[
                                            "member_limit"
                                        ];

                                    echo " members";

                                } else {

                                    echo "Unlimited members";

                                }

                                ?>

                            </div>

                        </div>


                        <div class="detail">

                            <div class="detail-label">

                                Monthly Price

                            </div>


                            <div class="detail-value">

                                Rs.

                                <?php

                                echo number_format(
                                    (float)
                                    $plan["price"],
                                    2
                                );

                                ?>

                            </div>

                        </div>


                    </div>


                    <!-- ACTION -->

                    <?php if ($is_current): ?>


                        <span
                            class="choose-button current-button"
                        >

                            Current Plan

                        </span>


                    <?php else: ?>


                        <a
                            href="subscription_change.php?plan_id=<?php echo $plan_id; ?>"
                            class="choose-button"
                        >

                            <?php

                            if ($current_subscription) {

                                echo "Choose Plan";

                            } else {

                                echo "Start with This Plan";

                            }

                            ?>

                        </a>


                    <?php endif; ?>


                </div>


            <?php endforeach; ?>


        </div>


    <?php else: ?>


        <div class="empty">

            <h2>
                No Subscription Plans Available
            </h2>

            <p>

                The administrator has not created
                any subscription plans yet.

            </p>

        </div>


    <?php endif; ?>


</div>


</body>

</html>