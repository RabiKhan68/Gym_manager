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

$admin_name = $_SESSION["admin_name"] ?? "Administrator";


/*
|--------------------------------------------------------------------------
| Total gym owners
|--------------------------------------------------------------------------
*/

$sql = "SELECT COUNT(*) AS total
        FROM gym_owners";

$result = $conn->query($sql);

$total_owners =
    $result->fetch_assoc()["total"];


/*
|--------------------------------------------------------------------------
| Total gyms
|--------------------------------------------------------------------------
*/

$sql = "SELECT COUNT(*) AS total
        FROM gyms";

$result = $conn->query($sql);

$total_gyms =
    $result->fetch_assoc()["total"];


/*
|--------------------------------------------------------------------------
| Total members
|--------------------------------------------------------------------------
*/

$sql = "SELECT COUNT(*) AS total
        FROM members";

$result = $conn->query($sql);

$total_members =
    $result->fetch_assoc()["total"];


/*
|--------------------------------------------------------------------------
| Total membership plans
|--------------------------------------------------------------------------
*/

$sql = "SELECT COUNT(*) AS total
        FROM membership_plans";

$result = $conn->query($sql);

$total_plans =
    $result->fetch_assoc()["total"];


/*
|--------------------------------------------------------------------------
| Recent gym owners
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            owner_id,
            name,
            email
        FROM gym_owners
        ORDER BY owner_id DESC
        LIMIT 10";

$owners_result =
    $conn->query($sql);

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
        Admin Dashboard
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


        /* HEADER */

        .header {

            background: #111827;

            color: white;

            padding: 20px 30px;

            display: flex;

            justify-content:
                space-between;

            align-items: center;

        }


        .header h1 {

            margin: 0;

            font-size: 24px;

        }


        .admin-name {

            margin-top: 5px;

            color: #d1d5db;

            font-size: 14px;

        }


        .logout {

            color: white;

            text-decoration: none;

            background: #dc2626;

            padding: 10px 16px;

            border-radius: 7px;

            font-weight: bold;

        }


        .logout:hover {

            opacity: 0.85;

        }


        /* CONTAINER */

        .container {

            max-width: 1200px;

            margin: auto;

            padding: 30px;

        }


        .welcome {

            margin-bottom: 25px;

        }


        .welcome h2 {

            margin: 0 0 5px;

        }


        .welcome p {

            margin: 0;

            color: #6b7280;

        }


        /* STAT CARDS */

        .stats {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            gap: 20px;

            margin-bottom: 30px;

        }


        .stat-card {

            background: white;

            padding: 25px;

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


        /* CARD */

        .card {

            background: white;

            padding: 25px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

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

            padding: 13px;

            text-align: left;

            border-bottom:
                1px solid #eee;

        }


        th {

            background: #f8fafc;

        }


        .empty {

            color: #777;

        }


        /* RESPONSIVE */

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


            .header {

                padding: 15px;

            }


            .stats {

                grid-template-columns:
                    1fr;

            }


            table {

                font-size: 13px;

            }

        }

    </style>

</head>


<body>


<header class="header">

    <div>

        <h1>
            Admin Dashboard
        </h1>

        <div class="admin-name">

            Logged in as:

            <?php

            echo htmlspecialchars(
                $admin_name
            );

            ?>

        </div>

    </div>


    <a
        href="admin_logout.php"
        class="logout"
        onclick="return confirm('Are you sure you want to logout?');"
    >

        Logout

    </a>

</header>


<div class="container">


    <!-- WELCOME -->

    <div class="welcome">

        <h2>
            System Overview
        </h2>

        <p>
            Manage and monitor your gym management system.
        </p>

    </div>


    <!-- STATISTICS -->

    <div class="stats">


        <div class="stat-card">

            <div class="stat-title">
                Gym Owners
            </div>

            <div class="stat-number">

                <?php
                echo $total_owners;
                ?>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                Gyms
            </div>

            <div class="stat-number">

                <?php
                echo $total_gyms;
                ?>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                Members
            </div>

            <div class="stat-number">

                <?php
                echo $total_members;
                ?>

            </div>

        </div>


        <div class="stat-card">

            <div class="stat-title">
                Membership Plans
            </div>

            <div class="stat-number">

                <?php
                echo $total_plans;
                ?>

            </div>

        </div>


    </div>

    <div style="margin-bottom: 25px;">

    <a
        href="admin_owners.php"
        style="
            display:inline-block;
            padding:12px 20px;
            background:#111827;
            color:white;
            text-decoration:none;
            border-radius:8px;
        "
    >
        Manage Gym Owners
    </a>

    <a href="admin_payments.php">
    Payments
    </a>

    <a href="admin_subscriptions.php">
    Subscriptions
    </a>

</div>


    <!-- GYM OWNERS -->

    <div class="card">

        <h2>
            Recent Gym Owners
        </h2>


        <?php if (
            $owners_result &&
            $owners_result->num_rows > 0
        ): ?>


            <table>

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Name
                        </th>

                        <th>
                            Email
                        </th>

                    </tr>

                </thead>


                <tbody>


                    <?php while (
                        $owner =
                            $owners_result->fetch_assoc()
                    ): ?>

                        <tr>

                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $owner["owner_id"]
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $owner["name"]
                                );

                                ?>

                            </td>


                            <td>

                                <?php

                                echo htmlspecialchars(
                                    $owner["email"]
                                );

                                ?>

                            </td>

                        </tr>

                    <?php endwhile; ?>


                </tbody>

            </table>


        <?php else: ?>


            <p class="empty">

                No gym owners found.

            </p>


        <?php endif; ?>


    </div>


</div>


</body>

</html>