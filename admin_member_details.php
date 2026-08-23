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
| Check member ID
|--------------------------------------------------------------------------
*/

if (!isset($_GET["id"]) || !is_numeric($_GET["id"])) {

    die("Invalid member ID.");

}

$member_id = (int) $_GET["id"];


/*
|--------------------------------------------------------------------------
| Get member + gym
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            m.member_id,
            m.name AS member_name,
            m.phone,
            m.email,
            m.joining_date,
            m.status,

            g.gym_id,
            g.gym_name,
            g.address,
            g.phone AS gym_phone,

            go.name AS owner_name,
            go.email AS owner_email

        FROM members m

        INNER JOIN gyms g
            ON m.gym_id = g.gym_id

        LEFT JOIN gym_owners go
            ON g.owner_id = go.owner_id

        WHERE m.member_id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $member_id
);

$stmt->execute();

$result = $stmt->get_result();

$member = $result->fetch_assoc();


if (!$member) {

    die("Member not found.");

}


$gym_id = $member["gym_id"];


/*
|--------------------------------------------------------------------------
| Membership
|--------------------------------------------------------------------------
*/

$current_membership = null;

$sql = "SELECT
            mm.membership_id,
            mm.start_date,
            mm.end_date,
            mm.status,
            mp.plan_name

        FROM member_memberships mm

        LEFT JOIN membership_plans mp
            ON mm.plan_id = mp.plan_id

        WHERE mm.member_id = ?

        ORDER BY mm.membership_id DESC

        LIMIT 1";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $member_id
);

$stmt->execute();

$current_membership =
    $stmt->get_result()->fetch_assoc();


/*
|--------------------------------------------------------------------------
| Payment history
|--------------------------------------------------------------------------
*/

$payments = null;

$sql = "SELECT
            payment_id,
            amount,
            payment_status,
            payment_for_month

        FROM payments

        WHERE member_id = ?

        ORDER BY payment_id DESC

        LIMIT 20";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $member_id
);

$stmt->execute();

$payments = $stmt->get_result();


/*
|--------------------------------------------------------------------------
| Attendance history
|--------------------------------------------------------------------------
*/

$attendance = null;

$sql = "SELECT
            attendance_date,
            attendance_time

        FROM attendance

        WHERE member_id = ?

        ORDER BY
            attendance_date DESC,
            attendance_time DESC

        LIMIT 20";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $member_id
);

$stmt->execute();

$attendance = $stmt->get_result();

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
        Member Details
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

            display: inline-block;

            padding: 10px 18px;

            background: #111827;

            color: white;

            text-decoration: none;

            border-radius: 8px;

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


        .info-grid {

            display: grid;

            grid-template-columns:
                repeat(3, 1fr);

            gap: 15px;

        }


        .info-item {

            background: #f8fafc;

            padding: 15px;

            border-radius: 8px;

        }


        .info-label {

            font-size: 13px;

            color: #6b7280;

            margin-bottom: 6px;

        }


        .info-value {

            font-weight: bold;

            word-break: break-word;

        }


        .status-active {

            color: green;

            font-weight: bold;

        }


        .status-inactive {

            color: red;

            font-weight: bold;

        }


        .status-expired {

            color: #b45309;

            font-weight: bold;

        }


        .two-column {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 25px;

        }


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

            white-space: nowrap;

        }


        .empty {

            color: #6b7280;

            padding: 15px 0;

        }


        .paid {

            color: green;

            font-weight: bold;

        }


        .pending {

            color: #b45309;

            font-weight: bold;

        }


        @media (max-width: 800px) {

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
                Member Details
            </h1>

            <p>
                <?php
                echo htmlspecialchars(
                    $member["member_name"]
                );
                ?>
            </p>

        </div>


        <a
            href="admin_members.php"
            class="back"
        >

            ← All Members

        </a>

    </div>



    <!-- MEMBER INFORMATION -->

    <div class="card">

        <h2>
            Member Information
        </h2>


        <div class="info-grid">


            <div class="info-item">

                <div class="info-label">
                    Member ID
                </div>

                <div class="info-value">

                    <?php
                    echo (int)$member["member_id"];
                    ?>

                </div>

            </div>


            <div class="info-item">

                <div class="info-label">
                    Name
                </div>

                <div class="info-value">

                    <?php
                    echo htmlspecialchars(
                        $member["member_name"]
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
                        $member["phone"] ?? "-"
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
                        $member["email"] ?? "-"
                    );
                    ?>

                </div>

            </div>


            <div class="info-item">

                <div class="info-label">
                    Joining Date
                </div>

                <div class="info-value">

                    <?php

                    echo date(
                        "d M Y",
                        strtotime(
                            $member["joining_date"]
                        )
                    );

                    ?>

                </div>

            </div>


            <div class="info-item">

                <div class="info-label">
                    Status
                </div>

                <div class="info-value">

                    <?php

                    if (
                        $member["status"] === "active"
                    ) {

                        echo '<span class="status-active">
                                Active
                              </span>';

                    } else {

                        echo '<span class="status-inactive">' .
                            htmlspecialchars(
                                $member["status"]
                            ) .
                            '</span>';

                    }

                    ?>

                </div>

            </div>


        </div>

    </div>



    <!-- GYM INFORMATION -->

    <div class="card">

        <h2>
            Gym Information
        </h2>


        <div class="info-grid">


            <div class="info-item">

                <div class="info-label">
                    Gym
                </div>

                <div class="info-value">

                    <?php
                    echo htmlspecialchars(
                        $member["gym_name"]
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
                        $member["gym_phone"] ?? "-"
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
                        $member["address"] ?? "-"
                    );
                    ?>

                </div>

            </div>


            <div class="info-item">

                <div class="info-label">
                    Gym Owner
                </div>

                <div class="info-value">

                    <?php
                    echo htmlspecialchars(
                        $member["owner_name"] ?? "-"
                    );
                    ?>

                </div>

            </div>


            <div class="info-item">

                <div class="info-label">
                    Owner Email
                </div>

                <div class="info-value">

                    <?php
                    echo htmlspecialchars(
                        $member["owner_email"] ?? "-"
                    );
                    ?>

                </div>

            </div>


        </div>

    </div>



    <!-- MEMBERSHIP -->

    <div class="card">

        <h2>
            Current Membership
        </h2>


        <?php if ($current_membership): ?>


            <div class="info-grid">


                <div class="info-item">

                    <div class="info-label">
                        Plan
                    </div>

                    <div class="info-value">

                        <?php

                        echo htmlspecialchars(
                            $current_membership["plan_name"]
                            ?? "-"
                        );

                        ?>

                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        Start Date
                    </div>

                    <div class="info-value">

                        <?php

                        echo date(
                            "d M Y",
                            strtotime(
                                $current_membership[
                                    "start_date"
                                ]
                            )
                        );

                        ?>

                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        End Date
                    </div>

                    <div class="info-value">

                        <?php

                        echo date(
                            "d M Y",
                            strtotime(
                                $current_membership[
                                    "end_date"
                                ]
                            )
                        );

                        ?>

                    </div>

                </div>


                <div class="info-item">

                    <div class="info-label">
                        Membership Status
                    </div>

                    <div class="info-value">

                        <?php

                        $membership_status =
                            $current_membership["status"];

                        if (
                            $membership_status === "active"
                        ) {

                            echo '<span class="status-active">
                                    Active
                                  </span>';

                        } elseif (
                            $membership_status === "expired"
                        ) {

                            echo '<span class="status-expired">
                                    Expired
                                  </span>';

                        } else {

                            echo htmlspecialchars(
                                $membership_status
                            );

                        }

                        ?>

                    </div>

                </div>


            </div>


        <?php else: ?>


            <p class="empty">
                No membership record found.
            </p>


        <?php endif; ?>


    </div>



    <!-- PAYMENTS + ATTENDANCE -->

    <div class="two-column">


        <!-- PAYMENTS -->

        <div class="card">

            <h2>
                Payment History
            </h2>


            <?php if (
                $payments &&
                $payments->num_rows > 0
            ): ?>


                <table>

                    <tr>

                        <th>
                            Amount
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Month
                        </th>

                    </tr>


                    <?php while (
                        $payment =
                        $payments->fetch_assoc()
                    ): ?>


                        <tr>

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

                                } elseif (
                                    $payment[
                                        "payment_status"
                                    ] === "pending"
                                ) {

                                    echo '<span class="pending">
                                            Pending
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


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $payment[
                                        "payment_for_month"
                                    ] ?? "-"
                                );

                                ?>

                            </td>

                        </tr>


                    <?php endwhile; ?>


                </table>


            <?php else: ?>


                <p class="empty">
                    No payment records found.
                </p>


            <?php endif; ?>


        </div>



        <!-- ATTENDANCE -->

        <div class="card">

            <h2>
                Attendance History
            </h2>


            <?php if (
                $attendance &&
                $attendance->num_rows > 0
            ): ?>


                <table>

                    <tr>

                        <th>
                            Date
                        </th>

                        <th>
                            Time
                        </th>

                    </tr>


                    <?php while (
                        $record =
                        $attendance->fetch_assoc()
                    ): ?>


                        <tr>

                            <td>

                                <?php

                                echo date(
                                    "d M Y",
                                    strtotime(
                                        $record[
                                            "attendance_date"
                                        ]
                                    )
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
                    No attendance records found.
                </p>


            <?php endif; ?>


        </div>


    </div>


</div>


</body>

</html>