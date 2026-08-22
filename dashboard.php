<?php

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


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family:
                Arial,
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

            font-size: 28px;

        }


        .header p {

            margin: 5px 0 0;

            color: #6b7280;

        }


        .logout {

            text-decoration: none;

            color: #dc2626;

            font-weight: bold;

        }


        /* STATS */

        .stats {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 20px;

            margin-bottom: 25px;

        }


        .stat-card {

            background: white;

            padding: 22px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

        }


        .stat-title {

            color: #6b7280;

            font-size: 14px;

            margin-bottom: 10px;

        }


        .stat-number {

            font-size: 32px;

            font-weight: bold;

        }


        /* QUICK ACTIONS */

        .card {

            background: white;

            padding: 25px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

            margin-bottom: 25px;

        }


        .card h2 {

            margin-top: 0;

        }


        .actions {

            display: flex;

            flex-wrap: wrap;

            gap: 12px;

        }


        .action {

            display: inline-block;

            padding: 12px 18px;

            background: #111827;

            color: white;

            text-decoration: none;

            border-radius: 8px;

        }


        .action:hover {

            opacity: 0.85;

        }


        /* TWO COLUMN */

        .two-column {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 25px;

        }


        /* TABLE */

        table {

            width: 100%;

            border-collapse:
                collapse;

        }


        th,
        td {

            padding: 12px;

            text-align: left;

            border-bottom:
                1px solid #eee;

        }


        th {

            background:
                #f8fafc;

        }


        .paid {

            color: green;

            font-weight: bold;

        }


        .unpaid {

            color: red;

            font-weight: bold;

        }


        .whatsapp {

            display: inline-block;

            padding: 7px 10px;

            background: #25D366;

            color: white;

            text-decoration: none;

            border-radius: 6px;

            font-size: 13px;

        }


        .empty {

            color: #777;

        }


        /* MOBILE */

        @media (max-width: 900px) {

            .stats {

                grid-template-columns:
                    repeat(2, 1fr);

            }


            .two-column {

                grid-template-columns:
                    1fr;

            }

        }


        @media (max-width: 600px) {

            .container {

                padding: 15px;

            }


            .stats {

                grid-template-columns:
                    1fr;

            }


            .header {

                align-items:
                    flex-start;

                gap: 15px;

            }


            table {

                font-size: 13px;

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

                🏋️

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
            href="logout.php"
            class="logout"
            onclick = "return confirm('Are you sure you want to logout?');"
        >

            Logout

        </a>

    </div>



    <!-- STATS -->

    <div class="stats">


        <div class="stat-card">

            <div class="stat-title">

                👥 Total Members

            </div>

            <div class="stat-number">

                <?php
                echo $total_members;
                ?>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">

                🟢 Paid This Month

            </div>

            <div class="stat-number paid">

                <?php
                echo $paid_members;
                ?>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">

                🔴 Unpaid This Month

            </div>

            <div class="stat-number unpaid">

                <?php
                echo $unpaid_members;
                ?>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">

                📅 Today's Attendance

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

            💰 This Month's Revenue

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

            ⚡ Quick Actions

        </h2>


        <div class="actions">


            <a
                href="members.php"
                class="action"
            >

                👥 Members

            </a>


            <a
                href="add_member.php"
                class="action"
            >

                ➕ Add Member

            </a>


            <a
                href="attendance.php"
                class="action"
            >

                📷 Attendance QR

            </a>


            <a
                href="add_plan.php"
                class="action"
            >

                📋 Membership Plans

            </a>


            <a
                href="payments.php"
                class="action"
            >

                💳 Payments

            </a>


        </div>

    </div>



    <!-- TWO COLUMN -->

    <div class="two-column">


        <!-- UNPAID MEMBERS -->

        <div class="card">

            <h2>

                🔴 Unpaid Members

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

                    🎉 Everyone has paid
                    this month!

                </p>


            <?php endif; ?>


        </div>



        <!-- RECENT ATTENDANCE -->

        <div class="card">

            <h2>

                📅 Recent Attendance

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