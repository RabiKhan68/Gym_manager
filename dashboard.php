<?php

date_default_timezone_set("Asia/Karachi");

session_start();

require_once "backend/db.php";


/*
|--------------------------------------------------------------------------
| Check login
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["owner_id"])) {

    header("Location: login.php");
    exit();

}

$owner_id = $_SESSION["owner_id"];


/*
|--------------------------------------------------------------------------
| Find owner's gym
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            gym_id,
            gym_name,
            phone
        FROM gyms
        WHERE owner_id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $owner_id
);

$stmt->execute();

$result = $stmt->get_result();

$gym = $result->fetch_assoc();


if (!$gym) {

    header("Location: create_gym.php");
    exit();

}

$gym_id = $gym["gym_id"];

/*
|--------------------------------------------------------------------------
| CHECK OWNER SUBSCRIPTION
|--------------------------------------------------------------------------
|
| The owner must have an ACTIVE subscription whose dates include today.
|
| If the subscription is:
| - deleted
| - expired
| - cancelled
| - not created
| - scheduled for the future
|
| the dashboard is locked.
|
|--------------------------------------------------------------------------
*/

$subscription_active = false;
$subscription_status = "none";
$subscription_end_date = null;
$subscription_plan_name = null;


/*
|--------------------------------------------------------------------------
| Find current active subscription
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        s.subscription_id,
        s.start_date,
        s.end_date,
        s.status,
        sp.plan_name

    FROM gym_owner_subscriptions s

    INNER JOIN subscription_plans sp
        ON s.subscription_plan_id =
           sp.subscription_plan_id

    WHERE s.owner_id = ?

    ORDER BY s.subscription_id DESC

    LIMIT 1
";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $owner_id
);

$stmt->execute();

$result = $stmt->get_result();

$subscription = $result->fetch_assoc();

$stmt->close();


/*
|--------------------------------------------------------------------------
| Check subscription dates/status
|--------------------------------------------------------------------------
*/

if ($subscription) {

    $subscription_status =
        strtolower(
            trim(
                (string) $subscription["status"]
            )
        );

    $subscription_end_date =
        $subscription["end_date"];

    $subscription_plan_name =
        $subscription["plan_name"];


    /*
    |--------------------------------------------------------------------------
    | Current date is already Asia/Karachi
    |--------------------------------------------------------------------------
    */

    $today_timestamp =
        strtotime($today);

    $start_timestamp =
        strtotime(
            $subscription["start_date"]
        );

    $end_timestamp =
        strtotime(
            $subscription["end_date"]
        );


    /*
    |--------------------------------------------------------------------------
    | Subscription is usable only when:
    |
    | status = active
    | start_date <= today
    | end_date >= today
    |--------------------------------------------------------------------------
    */

    if (
        $subscription_status === "active" &&
        $start_timestamp !== false &&
        $end_timestamp !== false &&
        $start_timestamp <= $today_timestamp &&
        $end_timestamp >= $today_timestamp
    ) {

        $subscription_active = true;

    }

}


/*
|--------------------------------------------------------------------------
| LOCK DASHBOARD IF SUBSCRIPTION IS NOT ACTIVE
|--------------------------------------------------------------------------
*/

if (!$subscription_active) {

    /*
    |--------------------------------------------------------------------------
    | Determine friendly message
    |--------------------------------------------------------------------------
    */

    if (!$subscription) {

        $subscription_message =
            "Your gym does not currently have an active subscription.";

    }
    elseif (
        $subscription_status === "expired"
    ) {

        $subscription_message =
            "Your subscription has expired.";

    }
    elseif (
        $subscription_status === "cancelled"
    ) {

        $subscription_message =
            "Your subscription has been cancelled.";

    }
    elseif (
        $subscription_status === "active" &&
        $subscription_end_date &&
        strtotime($subscription_end_date) < $today_timestamp
    ) {

        $subscription_message =
            "Your subscription has expired.";

    }
    elseif (
        $subscription_status === "active" &&
        $subscription &&
        strtotime($subscription["start_date"]) > $today_timestamp
    ) {

        $subscription_message =
            "Your subscription has not started yet.";

    }
    else {

        $subscription_message =
            "Your subscription is not currently active.";

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
            Subscription Required
        </title>

        <style>

            * {
                box-sizing: border-box;
            }

            body {

                margin: 0;

                min-height: 100vh;

                display: flex;

                align-items: center;

                justify-content: center;

                font-family:
                    Arial,
                    Helvetica,
                    sans-serif;

                background: #f4f6f8;

                color: #1f2937;

                padding: 20px;

            }

            .lock-card {

                width: 100%;

                max-width: 550px;

                background: white;

                padding: 40px;

                border-radius: 16px;

                text-align: center;

                box-shadow:
                    0 5px 20px
                    rgba(0,0,0,0.08);

            }

            .lock-icon {

                font-size: 55px;

                margin-bottom: 15px;

            }

            h1 {

                margin: 0 0 10px;

                font-size: 28px;

            }

            .message {

                color: #6b7280;

                line-height: 1.6;

                margin-bottom: 25px;

            }

            .subscription-info {

                background: #f8fafc;

                border-radius: 10px;

                padding: 15px;

                margin-bottom: 25px;

                text-align: left;

            }

            .subscription-info strong {

                color: #111827;

            }

            .buttons {

                display: flex;

                justify-content: center;

                gap: 10px;

                flex-wrap: wrap;

            }

            .button {

                display: inline-block;

                padding: 12px 20px;

                border-radius: 8px;

                text-decoration: none;

                font-weight: bold;

            }

            .renew {

                background: #2563eb;

                color: white;

            }

            .logout {

                background: #e5e7eb;

                color: #374151;

            }

            .button:hover {

                opacity: .85;

            }

        </style>

    </head>

    <body>

        <div class="lock-card">

            <div class="lock-icon">
                🔒
            </div>

            <h1>
                Subscription Required
            </h1>

            <p class="message">

                <?php
                echo htmlspecialchars(
                    $subscription_message
                );
                ?>

                <br>

                Please activate or renew your subscription
                to continue using the gym management system.

            </p>


            <?php if ($subscription): ?>

                <div class="subscription-info">

                    <?php if ($subscription_plan_name): ?>

                        <div>

                            <strong>
                                Plan:
                            </strong>

                            <?php
                            echo htmlspecialchars(
                                $subscription_plan_name
                            );
                            ?>

                        </div>

                    <?php endif; ?>


                    <?php if ($subscription_end_date): ?>

                        <div style="margin-top:8px;">

                            <strong>
                                Expiry:
                            </strong>

                            <?php

                            $expiry_timestamp =
                                strtotime(
                                    $subscription_end_date
                                );

                            echo $expiry_timestamp
                                ? date(
                                    "d M Y",
                                    $expiry_timestamp
                                )
                                : "-";

                            ?>

                        </div>

                    <?php endif; ?>

                </div>

            <?php endif; ?>


            <div class="buttons">

                <a
                    href="my_subscription.php"
                    class="button renew"
                >
                    View / Renew Subscription
                </a>


                <a
                    href="logout.php"
                    class="button logout"
                >
                    Logout
                </a>

            </div>

        </div>

    </body>

    </html>

    <?php

    exit();

}

/*
|--------------------------------------------------------------------------
| Current month
|--------------------------------------------------------------------------
*/

$current_month = date("Y-m-01");

$today = date("Y-m-d");


/*
|--------------------------------------------------------------------------
| Total members
|--------------------------------------------------------------------------
*/

$sql = "SELECT COUNT(*) AS total
        FROM members
        WHERE gym_id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $gym_id
);

$stmt->execute();

$result = $stmt->get_result();

$total_members =
    $result->fetch_assoc()["total"];


/*
|--------------------------------------------------------------------------
| Active members
|--------------------------------------------------------------------------
*/

$sql = "SELECT COUNT(*) AS total
        FROM members
        WHERE gym_id = ?
        AND status = 'active'";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $gym_id
);

$stmt->execute();

$result = $stmt->get_result();

$active_members =
    $result->fetch_assoc()["total"];


/*
|--------------------------------------------------------------------------
| Paid members this month
|--------------------------------------------------------------------------
*/

$sql = "SELECT COUNT(DISTINCT p.member_id) AS total

        FROM payments p

        INNER JOIN members m
            ON p.member_id = m.member_id

        WHERE m.gym_id = ?

        AND p.payment_for_month = ?

        AND p.payment_status = 'paid'";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "is",
    $gym_id,
    $current_month
);

$stmt->execute();

$result = $stmt->get_result();

$paid_members =
    $result->fetch_assoc()["total"];


/*
|--------------------------------------------------------------------------
| Unpaid members
|--------------------------------------------------------------------------
*/

$unpaid_members =
    $active_members - $paid_members;

if ($unpaid_members < 0) {

    $unpaid_members = 0;

}


/*
|--------------------------------------------------------------------------
| Today's attendance
|--------------------------------------------------------------------------
*/

$sql = "SELECT COUNT(*) AS total

        FROM attendance a

        INNER JOIN members m
            ON a.member_id = m.member_id

        WHERE m.gym_id = ?

        AND a.attendance_date = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "is",
    $gym_id,
    $today
);

$stmt->execute();

$result = $stmt->get_result();

$today_attendance =
    $result->fetch_assoc()["total"];


/*
|--------------------------------------------------------------------------
| Monthly revenue
|--------------------------------------------------------------------------
*/

$sql = "SELECT COALESCE(
            SUM(p.amount),
            0
        ) AS total

        FROM payments p

        INNER JOIN members m
            ON p.member_id = m.member_id

        WHERE m.gym_id = ?

        AND p.payment_for_month = ?

        AND p.payment_status = 'paid'";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "is",
    $gym_id,
    $current_month
);

$stmt->execute();

$result = $stmt->get_result();

$monthly_revenue =
    $result->fetch_assoc()["total"];


/*
|--------------------------------------------------------------------------
| Recent attendance
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            m.name,
            a.attendance_date,
            a.attendance_time

        FROM attendance a

        INNER JOIN members m
            ON a.member_id = m.member_id

        WHERE m.gym_id = ?

        ORDER BY
            a.attendance_date DESC,
            a.attendance_time DESC

        LIMIT 10";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $gym_id
);

$stmt->execute();

$recent_attendance =
    $stmt->get_result();


/*
|--------------------------------------------------------------------------
| Unpaid members
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            m.member_id,
            m.name,
            m.phone,

            mm.membership_id,

            mp.plan_name,
            mp.price

        FROM members m

        LEFT JOIN member_memberships mm

            ON m.member_id =
               mm.member_id

            AND mm.status = 'active'

            AND mm.start_date <= LAST_DAY(?)

            AND mm.end_date >= ?

        LEFT JOIN membership_plans mp

            ON mm.plan_id =
               mp.plan_id

        LEFT JOIN payments p

            ON mm.membership_id =
               p.membership_id

            AND p.payment_for_month = ?

            AND p.payment_status = 'paid'

        WHERE m.gym_id = ?

        AND m.status = 'active'

        AND p.payment_id IS NULL

        ORDER BY m.name ASC";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "sssi",
    $current_month,
    $current_month,
    $current_month,
    $gym_id
);

$stmt->execute();

$unpaid_result =
    $stmt->get_result();

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
        <?php
        echo htmlspecialchars(
            $gym["gym_name"]
        );
        ?>
        Dashboard
    </title>

    <link rel = "stylesheet" href = "css/dashboard.css">
</head>


<body>


<div class="container">


    <!-- HEADER -->

    <div class="header">

        <div>

            <h1>

                <img src = "images/gym.png" class="gym-icon" alt="Gym">

                <?php

                echo htmlspecialchars(
                    $gym["gym_name"]
                );

                ?>

            </h1>

            <p>

                Gym Management Dashboard

            </p>

        </div>

        <a
            href="manual.html"
            class="logout"
        >

            Documentation

        </a>

        <a
            href="my_subscription.php"
            class="action"
        >
            My Subscription
        </a>


        <a
            href="logout.php"
            class="logout"
            onclick = "return confirm('Are you sure you want to logout?');"
        >

            Logout

        </a>

        <a
            href="owner_members_import.php"
            class="button"
        >
            Import Members
        </a>

    </div>



    <!-- STATS -->

    <div class="stats">


        <div class="stat-card">

            <div class="stat-title">

                <img src = "images/group-users.png" class="stat-icon" alt="Gym">
            Total Members

            </div>

            <div class="stat-number">

                <?php
                echo $total_members;
                ?>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">

            <img src = "images/circle.png" class="stat-icon" alt="Gym">
                Paid This Month

            </div>

            <div class="stat-number paid">

                <?php
                echo $paid_members;
                ?>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">

            <img src = "images/delete.png" class="stat-icon" alt="Gym">
                Unpaid This Month

            </div>

            <div class="stat-number unpaid">

                <?php
                echo $unpaid_members;
                ?>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">

                <img src = "images/calendar.png" class="stat-icon" alt="Gym">
            Today's Attendance

            </div>

            <div class="stat-number">

                <?php
                echo $today_attendance;
                ?>

            </div>

        </div>


    </div>



    <!-- REVENUE -->

    <div class="card">

        <h2>

            <img src = "images/money.png" class="revenue-icon" alt="Gym">
        This Month's Revenue

        </h2>


        <div class="stat-number">

            Rs.

            <?php

            echo number_format(
                $monthly_revenue,
                2
            );

            ?>

        </div>

    </div>



    <!-- QUICK ACTIONS -->

    <div class="card">

        <h2>

            <img src = "images/sparkling.png" class="action-icon" alt="Gym">
        Quick Actions

        </h2>


        <div class="actions">


            <a
                href="members.php"
                class="action"
            >

                <img src = "images/group-users.png" class="stat-icon" alt="Gym">
            Members

            </a>


            <a
                href="add_member.php"
                class="action"
            >

                <img src = "images/plus.png" class="stat-icon" alt="Gym">
            Add Member

            </a>


            <a
                href="attendance.php"
                class="action"
            >

                <img src = "images/qr-code.png" class="stat-icon" alt="Gym">
            Attendance QR

            </a>


            <a
                href="add_plan.php"
                class="action"
            >

                <img src = "images/clipboard.png" class="stat-icon" alt="Gym">
            Membership Plans

            </a>


            <a
                href="payments.php"
                class="action"
            >

                <img src = "images/debit-card.png" class="stat-icon" alt="Gym">
            Payments

            </a>


        </div>

    </div>



    <!-- TWO COLUMN -->

    <div class="two-column">


        <!-- UNPAID MEMBERS -->

        <div class="card">

            <h2>

                <img src = "images/delete.png" class="stat-icon" alt="Gym">
            Unpaid Members

            </h2>


            <?php if (
                $unpaid_result->num_rows > 0
            ): ?>


                <table>

                    <tr>

                        <th>
                            Member
                        </th>

                        <th>
                            Plan
                        </th>

                        <th>
                            Amount
                        </th>

                    </tr>


                    <?php while (
                        $unpaid =
                        $unpaid_result->fetch_assoc()
                    ): ?>


                        <tr>

                            <td>

                                <a
                                    href="member_details.php?id=<?php
                                        echo $unpaid[
                                            "member_id"
                                        ];
                                    ?>"
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        $unpaid[
                                            "name"
                                        ]
                                    );

                                    ?>

                                </a>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $unpaid[
                                        "plan_name"
                                    ] ?? "-"
                                );

                                ?>

                            </td>


                            <td class="unpaid">

                                Rs.

                                <?php

                                echo number_format(
                                    $unpaid[
                                        "price"
                                    ] ?? 0,
                                    2
                                );

                                ?>

                            </td>

                        </tr>


                    <?php endwhile; ?>


                </table>


            <?php else: ?>


                <p class="empty">

                    <img src = "images/confetti.png" class="stat-icon" alt="Gym">
                Everyone has paid
                    this month!

                </p>


            <?php endif; ?>


        </div>



        <!-- RECENT ATTENDANCE -->

        <div class="card">

            <h2>

                <img src = "images/calendar.png" class="stat-icon" alt="Gym">
            Recent Attendance

            </h2>


            <?php if (
                $recent_attendance->num_rows > 0
            ): ?>


                <table>

                    <tr>

                        <th>
                            Member
                        </th>

                        <th>
                            Time
                        </th>

                    </tr>


                    <?php while (
                        $record =
                        $recent_attendance->fetch_assoc()
                    ): ?>


                        <tr>

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $record[
                                        "name"
                                    ]
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo date(
                                    "h:i A",
                                    strtotime(
                                        $record[
                                            "attendance_time"
                                        ]
                                    )
                                );

                                ?>

                            </td>

                        </tr>


                    <?php endwhile; ?>


                </table>


            <?php else: ?>


                <p class="empty">

                    No attendance yet.

                </p>


            <?php endif; ?>


        </div>


    </div>


</div>


</body>

</html>