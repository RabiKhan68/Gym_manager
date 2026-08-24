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


function format_price($price)
{
    return number_format(
        (float) $price,
        2
    );
}


/*
|--------------------------------------------------------------------------
| GET CURRENT ACTIVE SUBSCRIPTION
|--------------------------------------------------------------------------
|
| Only a subscription that is:
|
| status = active
| start_date <= today
| end_date >= today
|
| is considered the owner's current subscription.
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

    ORDER BY s.end_date DESC,
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


$stmt->execute();


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
| Only a future scheduled subscription is considered upcoming.
|
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

    ORDER BY s.start_date ASC,
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


$stmt->execute();


$result =
    $stmt->get_result();


$upcoming_subscription =
    $result->fetch_assoc();


$stmt->close();


/*
|--------------------------------------------------------------------------
| GET ALL SUBSCRIPTION PLANS
|--------------------------------------------------------------------------
*/

$plans = [];


$sql = "
    SELECT

        subscription_plan_id,
        plan_name,
        price,
        member_limit,
        created_at

    FROM subscription_plans

    ORDER BY price ASC
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
| CURRENT PLAN ID
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
| UPCOMING PLAN ID
|--------------------------------------------------------------------------
*/

$upcoming_plan_id = null;


if ($upcoming_subscription) {

    $upcoming_plan_id =
        (int)
        $upcoming_subscription[
            "subscription_plan_id"
        ];

}


/*
|--------------------------------------------------------------------------
| CURRENT STATUS
|--------------------------------------------------------------------------
*/

$current_status = null;


if ($current_subscription) {

    $current_status =
        strtolower(
            $current_subscription["status"]
        );

}


/*
|--------------------------------------------------------------------------
| CHECK SUBSCRIPTION HISTORY
|--------------------------------------------------------------------------
|
| This tells us whether the owner has ever had a subscription.
|
*/

$has_subscription_history = false;


$sql = "
    SELECT subscription_id

    FROM gym_owner_subscriptions

    WHERE owner_id = ?

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
    "i",
    $owner_id
);


$stmt->execute();


$result =
    $stmt->get_result();


$has_subscription_history =
    $result->num_rows > 0;


$stmt->close();


/*
|--------------------------------------------------------------------------
| DETERMINE PAGE STATE
|--------------------------------------------------------------------------
*/

$has_current_subscription =
    $current_subscription !== null;


$has_upcoming_subscription =
    $upcoming_subscription !== null;


/*
|--------------------------------------------------------------------------
| PAGE MESSAGE
|--------------------------------------------------------------------------
*/

$page_message = "";
$page_message_class = "";


if ($has_current_subscription) {

    $page_message =
        "Your current subscription remains active until " .
        date(
            "d M Y",
            strtotime(
                $current_subscription["end_date"]
            )
        ) .
        ". Any new plan will require payment and will " .
        "start after your current subscription ends.";

    $page_message_class =
        "current-notice";

}
elseif ($has_upcoming_subscription) {

    $page_message =
        "You already have a plan change scheduled. " .
        "Complete or manage the existing payment before " .
        "creating another subscription change.";

    $page_message_class =
        "scheduled-notice";

}
elseif ($has_subscription_history) {

    $page_message =
        "Your previous subscription is no longer active. " .
        "Choose a plan below to start a new subscription.";

    $page_message_class =
        "expired-notice";

}
else {

    $page_message =
        "Choose a subscription plan for your gym. " .
        "Payment will be required before the subscription becomes active.";

    $page_message_class =
        "current-notice";

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


        /* NOTICE */

        .notice {

            padding: 18px 20px;

            border-radius: 10px;

            margin-bottom: 25px;

            line-height: 1.6;

        }


        .current-notice {

            background: #eff6ff;

            border: 1px solid #bfdbfe;

            color: #1e40af;

        }


        .scheduled-notice {

            background: #f0f9ff;

            border: 1px solid #bae6fd;

            color: #075985;

        }


        .expired-notice {

            background: #fff7ed;

            border: 1px solid #fed7aa;

            color: #9a3412;

        }


        .notice strong {

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

            min-height: 350px;

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

            background: #f8fbff;

        }


        .plan-card.upcoming {

            border:
                2px solid #60a5fa;

            background: #eff6ff;

        }


        .plan-card.disabled {

            opacity: 0.85;

        }


        /* BADGES */

        .plan-badge {

            position: absolute;

            top: 15px;

            right: 15px;

            padding: 5px 9px;

            border-radius: 15px;

            font-size: 11px;

            font-weight: bold;

        }


        .current-label {

            background: #dbeafe;

            color: #1d4ed8;

        }


        .upcoming-label {

            background: #e0f2fe;

            color: #0369a1;

        }


        /* PLAN NAME */

        .plan-name {

            font-size: 25px;

            font-weight: bold;

            margin-bottom: 15px;

            padding-right: 100px;

        }


        /* PRICE */

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


        /* DETAILS */

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

            border: none;

            cursor: pointer;

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


        .scheduled-button {

            background: #93c5fd;

            color: #1e3a8a;

            cursor: default;

        }


        .scheduled-button:hover {

            background: #93c5fd;

        }


        /* PAYMENT INFO */

        .payment-note {

            margin-top: 10px;

            color: #6b7280;

            font-size: 12px;

            line-height: 1.5;

            text-align: center;

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



    <!-- PAGE STATE NOTICE -->

    <div class="notice <?php echo e($page_message_class); ?>">

        <?php echo e($page_message); ?>

    </div>



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


                /*
                |--------------------------------------------------------------
                | Current Plan
                |--------------------------------------------------------------
                */

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


                /*
                |--------------------------------------------------------------
                | Upcoming Plan
                |--------------------------------------------------------------
                */

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


                /*
                |--------------------------------------------------------------
                | Card Class
                |--------------------------------------------------------------
                */

                $card_class = "";

                if ($is_current) {

                    $card_class = "current";

                }
                elseif ($is_upcoming) {

                    $card_class = "upcoming";

                }

                ?>


                <div
                    class="plan-card <?php echo e($card_class); ?>"
                >


                    <!-- BADGE -->

                    <?php if ($is_current): ?>


                        <div
                            class="plan-badge current-label"
                        >

                            CURRENT PLAN

                        </div>


                    <?php elseif ($is_upcoming): ?>


                        <div
                            class="plan-badge upcoming-label"
                        >

                            UPCOMING PLAN

                        </div>


                    <?php endif; ?>



                    <!-- PLAN NAME -->

                    <div class="plan-name">

                        <?php

                        echo e(
                            $plan["plan_name"]
                        );

                        ?>

                    </div>



                    <!-- PRICE -->

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

                                    echo number_format(
                                        (int)
                                        $plan[
                                            "member_limit"
                                        ]
                                    );

                                    echo " members";

                                }
                                else {

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

                                echo format_price(
                                    $plan["price"]
                                );

                                ?>

                            </div>

                        </div>



                        <div class="detail">

                            <div class="detail-label">

                                Payment

                            </div>


                            <div class="detail-value">

                                JazzCash

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


                    <?php elseif ($is_upcoming): ?>


                        <span
                            class="choose-button scheduled-button"
                        >

                            Already Scheduled

                        </span>


                    <?php else: ?>


                        <!--
                        |------------------------------------------------------
                        | IMPORTANT
                        |------------------------------------------------------
                        |
                        | We intentionally send the owner through
                        | subscription_change.php first.
                        |
                        | subscription_change.php will:
                        |
                        | 1. Validate the selected plan.
                        | 2. Check member limits.
                        | 3. Check existing subscriptions.
                        | 4. Send the owner to payment_checkout.php.
                        | 5. Subscription is NOT created until payment
                        |    has been verified.
                        |
                        -->

                        <a
                            href="subscription_change.php?plan_id=<?php echo $plan_id; ?>"
                            class="choose-button"
                        >

                            <?php

                            if ($current_subscription) {

                                echo "Choose Plan";

                            }
                            else {

                                echo "Start with This Plan";

                            }

                            ?>

                        </a>


                        <div class="payment-note">

                            Payment required before
                            the subscription becomes active.

                        </div>


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