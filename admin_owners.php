<?php

session_start();

require_once "backend/db.php";


/*
|--------------------------------------------------------------------------
| Check admin login
|--------------------------------------------------------------------------
|
| IMPORTANT:
| Use the same session variable that your working
| admin_dashboard.php uses.
|
*/

if (!isset($_SESSION["admin_id"])) {

    header("Location: admin_login.php");
    exit();

}


/*
|--------------------------------------------------------------------------
| Get all gym owners and their gyms
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            go.owner_id,
            go.name AS owner_name,
            go.email,
            go.phone AS owner_phone,
            go.created_at AS owner_created_at,

            g.gym_id,
            g.gym_name,
            g.address,
            g.phone AS gym_phone

        FROM gym_owners go

        LEFT JOIN gyms g
            ON go.owner_id = g.owner_id

        ORDER BY go.created_at DESC";


$result = $conn->query($sql);

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
        Gym Owners
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

            overflow-x: auto;

        }


        table {

            width: 100%;

            border-collapse:
                collapse;

            min-width: 900px;

        }


        th,
        td {

            padding: 14px;

            text-align: left;

            border-bottom:
                1px solid #e5e7eb;

        }


        th {

            background: #f8fafc;

            font-weight: bold;

        }


        tr:hover {

            background: #f9fafb;

        }


        .owner-id {

            font-weight: bold;

        }


        .gym-name {

            font-weight: bold;

        }


        .no-gym {

            color: #9ca3af;

            font-style: italic;

        }


        .email {

            color: #374151;

        }


        .date {

            white-space: nowrap;

            color: #6b7280;

        }


        .empty {

            text-align: center;

            padding: 40px;

            color: #6b7280;

        }


        @media (max-width: 700px) {

            .container {

                padding: 15px;

            }


            .header {

                align-items:
                    flex-start;

                gap: 15px;

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
                Gym Owners
            </h1>

            <p>
                Manage registered gym owners
            </p>

        </div>


        <a
            href="admin_dashboard.php"
            class="back"
        >

            ← Dashboard

        </a>

    </div>



    <!-- OWNER TABLE -->

    <div class="card">


        <?php if ($result->num_rows > 0): ?>


            <table>

                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Owner
                        </th>

                        <th>
                            Email
                        </th>

                        <th>
                            Owner Phone
                        </th>

                        <th>
                            Gym
                        </th>

                        <th>
                            Gym Phone
                        </th>

                        <th>
                            Address
                        </th>

                        <th>
                            Registered
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php while (
                    $owner =
                    $result->fetch_assoc()
                ): ?>


                    <tr>


                        <td class="owner-id">

                            <?php

                            echo htmlspecialchars(
                                $owner["owner_id"]
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $owner["owner_name"]
                            );

                            ?>

                        </td>


                        <td class="email">

                            <?php

                            echo htmlspecialchars(
                                $owner["email"]
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $owner["owner_phone"]
                                ?? "-"
                            );

                            ?>

                        </td>


                        <td class="gym-name">

                            <?php

                            if (
                                $owner["gym_name"]
                            ) {

                                echo htmlspecialchars(
                                    $owner["gym_name"]
                                );

                            } else {

                                echo '<span class="no-gym">
                                        No gym
                                      </span>';

                            }

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $owner["gym_phone"]
                                ?? "-"
                            );

                            ?>

                        </td>


                        <td>

                            <?php

                            echo htmlspecialchars(
                                $owner["address"]
                                ?? "-"
                            );

                            ?>

                        </td>


                        <td class="date">

                            <?php

                            echo date(
                                "d M Y",
                                strtotime(
                                    $owner[
                                        "owner_created_at"
                                    ]
                                )
                            );

                            ?>

                        </td>


                    </tr>


                <?php endwhile; ?>


                </tbody>

            </table>


        <?php else: ?>


            <div class="empty">

                No gym owners found.

            </div>


        <?php endif; ?>


    </div>


</div>


</body>

</html>