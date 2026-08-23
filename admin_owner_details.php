<?php

session_start();

require_once "backend/db.php";


/*
|--------------------------------------------------------------------------
| Check admin login
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["admin_id"])) {

    header("Location: admin_login.php");
    exit();

}


/*
|--------------------------------------------------------------------------
| Check owner ID
|--------------------------------------------------------------------------
*/

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {

    die("Invalid owner ID.");

}

$owner_id = (int) $_GET["id"];


/*
|--------------------------------------------------------------------------
| Get owner and gym
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            go.owner_id,
            go.name AS owner_name,
            go.email AS owner_email,
            go.phone AS owner_phone,
            go.created_at AS owner_created_at,

            g.gym_id,
            g.gym_name,
            g.address,
            g.phone AS gym_phone

        FROM gym_owners go

        LEFT JOIN gyms g
            ON go.owner_id = g.owner_id

        WHERE go.owner_id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $owner_id
);

$stmt->execute();

$result = $stmt->get_result();

$owner = $result->fetch_assoc();


if (!$owner) {

    die("Gym owner not found.");

}


$gym_id = $owner["gym_id"];


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$total_members = 0;
$active_members = 0;
$total_payments = 0;
$total_revenue = 0;
$today_attendance = 0;


/*
|--------------------------------------------------------------------------
| Total members
|--------------------------------------------------------------------------
*/

if ($gym_id) {

    $sql = "SELECT COUNT(*) AS total
            FROM members
            WHERE gym_id = ?";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "i",
        $gym_id
    );

    $stmt->execute();

    $total_members =
        $stmt->get_result()->fetch_assoc()["total"];

}


/*
|--------------------------------------------------------------------------
| Active members
|--------------------------------------------------------------------------
*/

if ($gym_id) {

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

    $active_members =
        $stmt->get_result()->fetch_assoc()["total"];

}


/*
|--------------------------------------------------------------------------
| Payments
|--------------------------------------------------------------------------
*/

if ($gym_id) {

    $sql = "SELECT
                COUNT(*) AS total_payments,
                COALESCE(SUM(p.amount), 0) AS total_revenue

            FROM payments p

            INNER JOIN members m
                ON p.member_id = m.member_id

            WHERE m.gym_id = ?";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "i",
        $gym_id
    );

    $stmt->execute();

    $payment_stats =
        $stmt->get_result()->fetch_assoc();

    $total_payments =
        $payment_stats["total_payments"];

    $total_revenue =
        $payment_stats["total_revenue"];

}


/*
|--------------------------------------------------------------------------
| Today's attendance
|--------------------------------------------------------------------------
*/

if ($gym_id) {

    $today = date("Y-m-d");

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

    $today_attendance =
        $stmt->get_result()->fetch_assoc()["total"];

}


/*
|--------------------------------------------------------------------------
| Recent members
|--------------------------------------------------------------------------
*/

$recent_members = null;

if ($gym_id) {

    $sql = "SELECT
                member_id,
                name,
                phone,
                status

            FROM members

            WHERE gym_id = ?

            ORDER BY member_id DESC

            LIMIT 10";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "i",
        $gym_id
    );

    $stmt->execute();

    $recent_members =
        $stmt->get_result();

}


/*
|--------------------------------------------------------------------------
| Recent payments
|--------------------------------------------------------------------------
*/

$recent_payments = null;

if ($gym_id) {

    $sql = "SELECT
                p.amount,
                p.payment_status,
                p.payment_for_month,
                m.name AS member_name

            FROM payments p

            INNER JOIN members m
                ON p.member_id = m.member_id

            WHERE m.gym_id = ?

            ORDER BY p.payment_id DESC

            LIMIT 10";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "i",
        $gym_id
    );

    $stmt->execute();

    $recent_payments =
        $stmt->get_result();

}


/*
|--------------------------------------------------------------------------
| Recent attendance
|--------------------------------------------------------------------------
*/

$recent_attendance = null;

if ($gym_id) {

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
        Owner Details
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

            max-width: 1300px;

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

            margin: 5px 0 0;

            color: #6b7280;

        }


        .back {

            padding: 10px 18px;

            background: #111827;

            color: white;

            text-decoration: none;

            border-radius: 8px;

        }


        /* OWNER CARD */

        .owner-card {

            background: white;

            padding: 25px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

            margin-bottom: 25px;

        }


        .owner-card h2 {

            margin-top: 0;

        }


        .info-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 20px;

        }


        .info-item {

            background: #f8fafc;

            padding: 15px;

            border-radius: 8px;

        }


        .info-label {

            color: #6b7280;

            font-size: 13px;

            margin-bottom: 5px;

        }


        .info-value {

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


        .stat {

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

            font-size: 30px;

            font-weight: bold;

        }


        /* TWO COLUMNS */

        .two-column {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 25px;

        }


        .card {

            background: white;

            padding: 25px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

            margin-bottom: 25px;

            overflow-x: auto;

        }


        .card h2 {

            margin-top: 0;

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
                1px solid #e5e7eb;

        }


        th {

            background: #f8fafc;

        }


        .active {

            color: green;

            font-weight: bold;

        }


        .inactive {

            color: red;

            font-weight: bold;

        }


        .paid {

            color: green;

            font-weight: bold;

        }


        .empty {

            color: #6b7280;

        }


        @media (max-width: 900px) {

            .stats {

                grid-template-columns:
                    repeat(2, 1fr);

            }


            .info-grid {

                grid-template-columns:
                    1fr 1fr;

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


            .header {

                flex-direction:
                    column;

                align-items:
                    flex-start;

                gap: 15px;

            }


            .stats {

                grid-template-columns:
                    1fr;

            }


            .info-grid {

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
                Owner Details
            </h1>

            <p>
                <?php
                echo htmlspecialchars(
                    $owner["owner_name"]
                );
                ?>
            </p>

        </div>


        <a
            href="admin_owners.php"
            class="back"
        >

            ← Gym Owners

        </a>

    </div>



    <!-- OWNER INFORMATION -->

    <div class="owner-card">

        <h2>
            Owner Information
        </h2>


        <div class="info-grid">


            <div class="info-item">

                <div class="info-label">
                    Name
                </div>

                <div class="info-value">

                    <?php

                    echo htmlspecialchars(
                        $owner["owner_name"]
                    );

                    ?>

                </div>

            </div>


            <div class="info-item">

                <div class="info-label">
                    Email
                </div>

                <div class="info-value">

                    <?php

                    echo htmlspecialchars(
                        $owner["owner_email"]
                    );

                    ?>

                </div>

            </div>


            <div class="info-item">

                <div class="info-label">
                    Phone
                </div>

                <div class="info-value">

                    <?php

                    echo htmlspecialchars(
                        $owner["owner_phone"]
                        ?? "-"
                    );

                    ?>

                </div>

            </div>


            <div class="info-item">

                <div class="info-label">
                    Gym
                </div>

                <div class="info-value">

                    <?php

                    echo htmlspecialchars(
                        $owner["gym_name"]
                        ?? "No gym"
                    );

                    ?>

                </div>

            </div>


            <div class="info-item">

                <div class="info-label">
                    Gym Phone
                </div>

                <div class="info-value">

                    <?php

                    echo htmlspecialchars(
                        $owner["gym_phone"]
                        ?? "-"
                    );

                    ?>

                </div>

            </div>


            <div class="info-item">

                <div class="info-label">
                    Address
                </div>

                <div class="info-value">

                    <?php

                    echo htmlspecialchars(
                        $owner["address"]
                        ?? "-"
                    );

                    ?>

                </div>

            </div>


        </div>

    </div>



    <!-- STATISTICS -->

    <div class="stats">


        <div class="stat">

            <div class="stat-title">
                Total Members
            </div>

            <div class="stat-number">

                <?php
                echo $total_members;
                ?>

            </div>

        </div>


        <div class="stat">

            <div class="stat-title">
                Active Members
            </div>

            <div class="stat-number">

                <?php
                echo $active_members;
                ?>

            </div>

        </div>


        <div class="stat">

            <div class="stat-title">
                Total Payments
            </div>

            <div class="stat-number">

                <?php
                echo $total_payments;
                ?>

            </div>

        </div>


        <div class="stat">

            <div class="stat-title">
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
            Total Revenue
        </h2>

        <div class="stat-number">

            Rs.

            <?php

            echo number_format(
                $total_revenue,
                2
            );

            ?>

        </div>

    </div>



    <!-- MEMBERS + PAYMENTS -->

    <div class="two-column">


        <!-- MEMBERS -->

        <div class="card">

            <h2>
                Recent Members
            </h2>


            <?php if (
                $recent_members &&
                $recent_members->num_rows > 0
            ): ?>


                <table>

                    <tr>

                        <th>
                            Name
                        </th>

                        <th>
                            Phone
                        </th>

                        <th>
                            Status
                        </th>

                    </tr>


                    <?php while (
                        $member =
                        $recent_members->fetch_assoc()
                    ): ?>


                        <tr>

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $member["name"]
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $member["phone"]
                                    ?? "-"
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                if (
                                    $member["status"]
                                    === "active"
                                ) {

                                    echo '<span class="active">
                                            Active
                                          </span>';

                                } else {

                                    echo '<span class="inactive">
                                            ' .
                                            htmlspecialchars(
                                                $member["status"]
                                            ) .
                                          '</span>';

                                }

                                ?>

                            </td>

                        </tr>


                    <?php endwhile; ?>


                </table>


            <?php else: ?>


                <p class="empty">
                    No members found.
                </p>


            <?php endif; ?>


        </div>



        <!-- PAYMENTS -->

        <div class="card">

            <h2>
                Recent Payments
            </h2>


            <?php if (
                $recent_payments &&
                $recent_payments->num_rows > 0
            ): ?>


                <table>

                    <tr>

                        <th>
                            Member
                        </th>

                        <th>
                            Amount
                        </th>

                        <th>
                            Status
                        </th>

                    </tr>


                    <?php while (
                        $payment =
                        $recent_payments->fetch_assoc()
                    ): ?>


                        <tr>

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $payment[
                                        "member_name"
                                    ]
                                );

                                ?>

                            </td>


                            <td>

                                Rs.

                                <?php

                                echo number_format(
                                    $payment["amount"],
                                    2
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                if (
                                    $payment[
                                        "payment_status"
                                    ] === "paid"
                                ) {

                                    echo '<span class="paid">
                                            Paid
                                          </span>';

                                } else {

                                    echo htmlspecialchars(
                                        $payment[
                                            "payment_status"
                                        ]
                                    );

                                }

                                ?>

                            </td>

                        </tr>


                    <?php endwhile; ?>


                </table>


            <?php else: ?>


                <p class="empty">
                    No payments found.
                </p>


            <?php endif; ?>


        </div>


    </div>



    <!-- ATTENDANCE -->

    <div class="card">

        <h2>
            Recent Attendance
        </h2>


        <?php if (
            $recent_attendance &&
            $recent_attendance->num_rows > 0
        ): ?>


            <table>

                <tr>

                    <th>
                        Member
                    </th>

                    <th>
                        Date
                    </th>

                    <th>
                        Time
                    </th>

                </tr>


                <?php while (
                    $attendance =
                    $recent_attendance->fetch_assoc()
                ): ?>


                    <tr>

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $attendance["name"]
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $attendance[
                                    "attendance_date"
                                ]
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo date(
                                "h:i A",
                                strtotime(
                                    $attendance[
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
                No attendance records found.
            </p>


        <?php endif; ?>


    </div>


</div>


</body>

</html>