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
| Current month
|--------------------------------------------------------------------------
*/

$current_month = date("Y-m-01");

$today = date("Y-m-d");


/*
|--------------------------------------------------------------------------
| Get memberships
|--------------------------------------------------------------------------
|
| We calculate membership status using end_date.
|
|--------------------------------------------------------------------------
*/

$sql = "SELECT

            mm.membership_id,
            mm.member_id,
            mm.start_date,
            mm.end_date,

            m.name AS member_name,
            m.phone,

            mp.plan_name,
            mp.price,

            CASE

                WHEN mm.end_date < ? THEN 'expired'

                WHEN DATEDIFF(mm.end_date, ?) <= 7
                    THEN 'expiring'

                ELSE 'active'

            END AS membership_status,

            DATEDIFF(
                mm.end_date,
                ?
            ) AS days_remaining,

            CASE

                WHEN EXISTS (

                    SELECT 1

                    FROM payments p

                    WHERE p.membership_id =
                          mm.membership_id

                    AND p.payment_for_month = ?

                    AND p.payment_status = 'paid'

                )

                THEN 'paid'

                ELSE 'unpaid'

            END AS payment_status

        FROM member_memberships mm

        INNER JOIN members m
            ON mm.member_id = m.member_id

        INNER JOIN membership_plans mp
            ON mm.plan_id = mp.plan_id

        INNER JOIN gyms g
            ON m.gym_id = g.gym_id

        WHERE g.owner_id = ?

        ORDER BY
            CASE

                WHEN mm.end_date < ?
                    THEN 3

                WHEN DATEDIFF(mm.end_date, ?) <= 7
                    THEN 2

                ELSE 1

            END,

            m.name";


$stmt = $conn->prepare($sql);


$stmt->bind_param(
    "ssssiss",
    $today,
    $today,
    $today,
    $current_month,
    $owner_id,
    $today,
    $today
);


$stmt->execute();


$memberships = $stmt->get_result();


/*
|--------------------------------------------------------------------------
| Statistics
|--------------------------------------------------------------------------
*/

$total_members = 0;
$paid_count = 0;
$unpaid_count = 0;
$expired_count = 0;
$expiring_count = 0;
$active_count = 0;


/*
|--------------------------------------------------------------------------
| Store rows
|--------------------------------------------------------------------------
*/

$rows = [];


while (
    $member =
    $memberships->fetch_assoc()
) {

    $rows[] = $member;


    $total_members++;


    if (
        $member["payment_status"]
        === "paid"
    ) {

        $paid_count++;

    } else {

        $unpaid_count++;

    }


    if (
        $member["membership_status"]
        === "expired"
    ) {

        $expired_count++;

    }

    elseif (
        $member["membership_status"]
        === "expiring"
    ) {

        $expiring_count++;

    }

    else {

        $active_count++;

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
        Payment Status
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

            color: #222;

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

        }


        .month {

            color: #666;

            margin-top: 5px;

        }


        .stats {

            display: grid;

            grid-template-columns:
                repeat(5, 1fr);

            gap: 15px;

            margin-bottom: 25px;

        }


        .stat-card {

            background: white;

            padding: 20px;

            border-radius: 10px;

            box-shadow:
                0 3px 10px
                rgba(0,0,0,0.06);

        }


        .stat-title {

            color: #666;

            font-size: 14px;

        }


        .stat-number {

            font-size: 30px;

            font-weight: bold;

            margin-top: 8px;

        }


        .card {

            background: white;

            padding: 25px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

            overflow-x: auto;

        }


        table {

            width: 100%;

            border-collapse:
                collapse;

        }


        th,
        td {

            padding: 13px;

            text-align: left;

            border-bottom:
                1px solid #eee;

        }


        th {

            background: #f8fafc;

            white-space: nowrap;

        }


        .paid {

            color: green;

            font-weight: bold;

        }


        .unpaid {

            color: red;

            font-weight: bold;

        }


        .active {

            color: green;

            font-weight: bold;

        }


        .expiring {

            color: #d97706;

            font-weight: bold;

        }


        .expired {

            color: red;

            font-weight: bold;

        }


        .days {

            font-weight: bold;

        }


        .actions {

            margin-top: 20px;

        }


        .button {

            display: inline-block;

            padding: 10px 16px;

            background: #111827;

            color: white;

            text-decoration: none;

            border-radius: 7px;

            margin-right: 8px;

        }


        .button:hover {

            background: #374151;

        }


        .empty {

            text-align: center;

            padding: 40px;

            color: #777;

        }


        @media (max-width: 900px) {

            .stats {

                grid-template-columns:
                    repeat(2, 1fr);

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

                flex-direction: column;

                align-items: flex-start;

                gap: 10px;

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
                Payment Status
            </h1>

            <div class="month">

                <?php

                echo date(
                    "F Y"
                );

                ?>

            </div>

        </div>

    </div>



    <!-- STATISTICS -->

    <div class="stats">


        <div class="stat-card">

            <div class="stat-title">
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
                Paid
            </div>

            <div
                class="stat-number"
                style="color:green;"
            >

                <?php
                echo $paid_count;
                ?>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                Unpaid
            </div>

            <div
                class="stat-number"
                style="color:red;"
            >

                <?php
                echo $unpaid_count;
                ?>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                Expiring Soon
            </div>

            <div
                class="stat-number"
                style="color:#d97706;"
            >

                <?php
                echo $expiring_count;
                ?>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                Expired
            </div>

            <div
                class="stat-number"
                style="color:red;"
            >

                <?php
                echo $expired_count;
                ?>

            </div>

        </div>


    </div>



    <!-- PAYMENT TABLE -->

    <div class="card">


        <?php if (
            count($rows) > 0
        ): ?>


            <table>


                <thead>

                    <tr>

                        <th>
                            Member
                        </th>

                        <th>
                            Phone
                        </th>

                        <th>
                            Plan
                        </th>

                        <th>
                            Fee
                        </th>

                        <th>
                            Membership
                        </th>

                        <th>
                            Expiry
                        </th>

                        <th>
                            Days
                        </th>

                        <th>
                            Payment
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php foreach (
                    $rows as $member
                ): ?>


                    <tr>


                        <!-- MEMBER -->

                        <td>

                            <strong>

                                <?php

                                echo htmlspecialchars(
                                    $member[
                                        "member_name"
                                    ]
                                );

                                ?>

                            </strong>

                        </td>



                        <!-- PHONE -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $member[
                                    "phone"
                                ] ?? "-"
                            );

                            ?>

                        </td>



                        <!-- PLAN -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $member[
                                    "plan_name"
                                ]
                            );

                            ?>

                        </td>



                        <!-- FEE -->

                        <td>

                            Rs.

                            <?php

                            echo number_format(
                                $member[
                                    "price"
                                ],
                                2
                            );

                            ?>

                        </td>



                        <!-- MEMBERSHIP STATUS -->

                        <td>


                            <?php if (
                                $member[
                                    "membership_status"
                                ]
                                === "active"
                            ): ?>

                                <span class="active">

                                    🟢 Active

                                </span>


                            <?php elseif (
                                $member[
                                    "membership_status"
                                ]
                                === "expiring"
                            ): ?>

                                <span class="expiring">

                                    🟠 Expiring Soon

                                </span>


                            <?php else: ?>

                                <span class="expired">

                                    🔴 Expired

                                </span>

                            <?php endif; ?>


                        </td>



                        <!-- EXPIRY -->

                        <td>

                            <?php

                            echo date(
                                "d M Y",
                                strtotime(
                                    $member[
                                        "end_date"
                                    ]
                                )
                            );

                            ?>

                        </td>



                        <!-- DAYS -->

                        <td class="days">


                            <?php if (
                                $member[
                                    "membership_status"
                                ]
                                === "expired"
                            ): ?>

                                <span
                                    class="expired"
                                >

                                    Expired

                                </span>


                            <?php else: ?>

                                <?php

                                echo
                                    $member[
                                        "days_remaining"
                                    ];

                                ?>

                                days

                            <?php endif; ?>


                        </td>



                        <!-- PAYMENT -->

                        <td>


                            <?php if (
                                $member[
                                    "payment_status"
                                ]
                                === "paid"
                            ): ?>

                                <span class="paid">

                                    🟢 PAID

                                </span>


                            <?php else: ?>

                                <span class="unpaid">

                                    🔴 UNPAID

                                </span>

                            <?php endif; ?>


                        </td>


                    </tr>


                <?php endforeach; ?>


                </tbody>


            </table>


        <?php else: ?>


            <div class="empty">

                <h3>
                    No Memberships Found
                </h3>

                <p>
                    There are currently no memberships
                    assigned to members.
                </p>

            </div>


        <?php endif; ?>


    </div>



    <!-- ACTIONS -->

    <div class="actions">


        <a
            href="make_payment.php"
            class="button"
        >

            💰 Record Payment

        </a>


        <a
            href="payments.php"
            class="button"
        >

            📋 Payment History

        </a>


        <a
            href="dashboard.php"
            class="button"
        >

            ← Dashboard

        </a>


    </div>


</div>


</body>

</html>