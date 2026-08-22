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
            gym_name
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

    die("Gym not found.");

}


$gym_id = $gym["gym_id"];


/*
|--------------------------------------------------------------------------
| Get membership plans
|--------------------------------------------------------------------------
|
| COUNT() tells us how many members are currently
| assigned to each plan.
|
|--------------------------------------------------------------------------
*/

$sql = "SELECT

            mp.plan_id,
            mp.plan_name,
            mp.price,
            mp.duration_months,
            mp.description,

            COUNT(mm.membership_id) AS member_count

        FROM membership_plans mp

        LEFT JOIN member_memberships mm
            ON mp.plan_id = mm.plan_id

        WHERE mp.gym_id = ?

        GROUP BY
            mp.plan_id,
            mp.plan_name,
            mp.price,
            mp.duration_months,
            mp.description

        ORDER BY mp.plan_id DESC";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $gym_id
);

$stmt->execute();

$plans = $stmt->get_result();

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
        Membership Plans
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

            max-width: 1100px;

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


        .gym-name {

            color: #2563eb;

            margin-top: 5px;

        }


        .add-button {

            display: inline-block;

            padding: 11px 18px;

            background: #2563eb;

            color: white;

            text-decoration: none;

            border-radius: 7px;

            font-weight: bold;

        }


        .add-button:hover {

            background: #1d4ed8;

        }


        .card {

            background: white;

            border-radius: 12px;

            padding: 25px;

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

            padding: 14px;

            text-align: left;

            border-bottom:
                1px solid #eee;

        }


        th {

            background: #f8fafc;

            font-size: 14px;

        }


        td {

            vertical-align: middle;

        }


        .price {

            font-weight: bold;

        }


        .duration {

            white-space: nowrap;

        }


        .members {

            font-weight: bold;

        }


        .description {

            max-width: 250px;

            color: #666;

        }


        .actions {

            white-space: nowrap;

        }


        .edit {

            color: #2563eb;

            text-decoration: none;

            margin-right: 10px;

        }


        .delete {

            color: #dc2626;

            text-decoration: none;

        }


        .empty {

            text-align: center;

            padding: 40px;

            color: #777;

        }


        .back {

            display: inline-block;

            margin-top: 20px;

            color: #2563eb;

            text-decoration: none;

        }


        @media (max-width: 700px) {

            .container {

                padding: 15px;

            }


            .header {

                flex-direction: column;

                align-items: flex-start;

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
                Membership Plans
            </h1>

            <div class="gym-name">

                <?php

                echo htmlspecialchars(
                    $gym["gym_name"]
                );

                ?>

            </div>

        </div>


        <a
            href="add_plan.php"
            class="add-button"
        >

            + Create New Plan

        </a>

    </div>



    <!-- PLANS -->

    <div class="card">


        <?php if ($plans->num_rows > 0): ?>


            <table>

                <thead>

                    <tr>

                        <th>
                            Plan
                        </th>

                        <th>
                            Price
                        </th>

                        <th>
                            Duration
                        </th>

                        <th>
                            Members
                        </th>

                        <th>
                            Description
                        </th>

                        <th>
                            Actions
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php while (
                    $plan =
                    $plans->fetch_assoc()
                ): ?>


                    <tr>


                        <!-- PLAN -->

                        <td>

                            <strong>

                                <?php

                                echo htmlspecialchars(
                                    $plan["plan_name"]
                                );

                                ?>

                            </strong>

                        </td>



                        <!-- PRICE -->

                        <td class="price">

                            Rs.

                            <?php

                            echo number_format(
                                $plan["price"],
                                2
                            );

                            ?>

                        </td>



                        <!-- DURATION -->

                        <td class="duration">

                            <?php

                            echo $plan[
                                "duration_months"
                            ];

                            ?>

                            month<?php

                            echo
                                $plan[
                                    "duration_months"
                                ] == 1
                                ? ""
                                : "s";

                            ?>

                        </td>



                        <!-- MEMBERS -->

                        <td class="members">

                            <?php

                            echo $plan[
                                "member_count"
                            ];

                            ?>

                        </td>



                        <!-- DESCRIPTION -->

                        <td class="description">

                            <?php

                            if (
                                !empty(
                                    $plan[
                                        "description"
                                    ]
                                )
                            ) {

                                echo htmlspecialchars(
                                    $plan[
                                        "description"
                                    ]
                                );

                            } else {

                                echo "-";

                            }

                            ?>

                        </td>



                        <!-- ACTIONS -->

                        <td class="actions">

                            <a
                                href="edit_plan.php?id=<?php
                                    echo $plan["plan_id"];
                                ?>"
                                class="edit"
                            >

                                Edit

                            </a>


                            <?php if (
                                $plan["member_count"] == 0
                            ): ?>

                                <a
                                    href="backend/delete_plan.php?id=<?php
                                        echo $plan["plan_id"];
                                    ?>"
                                    class="delete"
                                    onclick="return confirm(
                                        'Are you sure you want to delete this plan?'
                                    );"
                                >

                                    Delete

                                </a>

                            <?php else: ?>

                                <span
                                    title="This plan is being used by members."
                                    style="color:#999;"
                                >

                                    Delete

                                </span>

                            <?php endif; ?>

                        </td>


                    </tr>


                <?php endwhile; ?>


                </tbody>

            </table>


        <?php else: ?>


            <div class="empty">

                <h3>
                    No Membership Plans
                </h3>

                <p>
                    Create your first membership plan
                    to start assigning memberships.
                </p>


                <a
                    href="add_plan.php"
                    class="add-button"
                >

                    + Create First Plan

                </a>

            </div>


        <?php endif; ?>


    </div>



    <a
        href="dashboard.php"
        class="back"
    >

        ← Back to Dashboard

    </a>


</div>


</body>

</html>