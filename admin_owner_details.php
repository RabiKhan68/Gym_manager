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

if (
    !isset($_GET["id"]) ||
    !is_numeric($_GET["id"])
) {

    die("Invalid owner ID.");

}


$owner_id = (int) $_GET["id"];


/*
|--------------------------------------------------------------------------
| Get owner and gym information
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
            g.address AS gym_address,
            g.phone AS gym_phone,
            g.created_at AS gym_created_at

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


/*
|--------------------------------------------------------------------------
| Owner not found
|--------------------------------------------------------------------------
*/

if (!$owner) {

    die("Gym owner not found.");

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

            max-width: 1000px;

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


        .grid {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 20px;

        }


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

            margin-bottom: 20px;

        }


        .info {

            margin-bottom: 16px;

        }


        .label {

            font-size: 13px;

            color: #6b7280;

            margin-bottom: 4px;

        }


        .value {

            font-size: 16px;

            font-weight: 500;

            word-break: break-word;

        }


        .owner-id {

            display: inline-block;

            padding: 5px 9px;

            background: #f3f4f6;

            border-radius: 6px;

            font-size: 13px;

        }


        .no-gym {

            color: #9ca3af;

            font-style: italic;

        }


        @media (max-width: 700px) {

            .container {

                padding: 15px;

            }


            .header {

                align-items:
                    flex-start;

                gap: 15px;

                flex-direction:
                    column;

            }


            .grid {

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
                Gym owner information
            </p>

        </div>


        <a
            href="admin_owners.php"
            class="back"
        >

            ← Gym Owners

        </a>

    </div>



    <div class="grid">


        <!-- OWNER INFORMATION -->

        <div class="card">

            <h2>
                Owner Information
            </h2>


            <div class="info">

                <div class="label">
                    Owner ID
                </div>

                <div class="value">

                    <span class="owner-id">

                        <?php

                        echo (int)
                            $owner["owner_id"];

                        ?>

                    </span>

                </div>

            </div>


            <div class="info">

                <div class="label">
                    Name
                </div>

                <div class="value">

                    <?php

                    echo htmlspecialchars(
                        $owner["owner_name"]
                    );

                    ?>

                </div>

            </div>


            <div class="info">

                <div class="label">
                    Email
                </div>

                <div class="value">

                    <?php

                    echo htmlspecialchars(
                        $owner["owner_email"]
                    );

                    ?>

                </div>

            </div>


            <div class="info">

                <div class="label">
                    Phone
                </div>

                <div class="value">

                    <?php

                    echo htmlspecialchars(
                        $owner["owner_phone"] ?? "-"
                    );

                    ?>

                </div>

            </div>


            <div class="info">

                <div class="label">
                    Registered
                </div>

                <div class="value">

                    <?php

                    echo date(
                        "d F Y, h:i A",
                        strtotime(
                            $owner[
                                "owner_created_at"
                            ]
                        )
                    );

                    ?>

                </div>

            </div>

        </div>



        <!-- GYM INFORMATION -->

        <div class="card">

            <h2>
                Gym Information
            </h2>


            <?php if ($owner["gym_id"]): ?>


                <div class="info">

                    <div class="label">
                        Gym ID
                    </div>

                    <div class="value">

                        <?php

                        echo (int)
                            $owner["gym_id"];

                        ?>

                    </div>

                </div>


                <div class="info">

                    <div class="label">
                        Gym Name
                    </div>

                    <div class="value">

                        <?php

                        echo htmlspecialchars(
                            $owner["gym_name"]
                        );

                        ?>

                    </div>

                </div>


                <div class="info">

                    <div class="label">
                        Phone
                    </div>

                    <div class="value">

                        <?php

                        echo htmlspecialchars(
                            $owner["gym_phone"] ?? "-"
                        );

                        ?>

                    </div>

                </div>


                <div class="info">

                    <div class="label">
                        Address
                    </div>

                    <div class="value">

                        <?php

                        echo htmlspecialchars(
                            $owner["gym_address"] ?? "-"
                        );

                        ?>

                    </div>

                </div>


                <div class="info">

                    <div class="label">
                        Gym Created
                    </div>

                    <div class="value">

                        <?php

                        echo date(
                            "d F Y, h:i A",
                            strtotime(
                                $owner[
                                    "gym_created_at"
                                ]
                            )
                        );

                        ?>

                    </div>

                </div>


            <?php else: ?>


                <p class="no-gym">

                    This owner has not created a gym yet.

                </p>


            <?php endif; ?>


        </div>


    </div>


</div>


</body>

</html>