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