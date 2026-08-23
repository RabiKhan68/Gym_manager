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
| Check subscription ID
|--------------------------------------------------------------------------
*/

if (
    !isset($_GET["id"]) ||
    !is_numeric($_GET["id"])
) {

    die("Invalid subscription ID.");

}

$subscription_id = (int) $_GET["id"];


/*
|--------------------------------------------------------------------------
| Get subscription details
|--------------------------------------------------------------------------
*/

$sql = "SELECT

            s.subscription_id,
            s.start_date,
            s.end_date,
            s.status,

            o.owner_id,
            o.name AS owner_name,
            o.email AS owner_email,

            sp.plan_name,
            sp.price,

            g.gym_name

        FROM gym_owner_subscriptions s

        INNER JOIN gym_owners o
            ON s.owner_id = o.owner_id

        INNER JOIN subscription_plans sp
            ON s.subscription_plan_id =
               sp.subscription_plan_id

        LEFT JOIN gyms g
            ON o.owner_id = g.owner_id

        WHERE s.subscription_id = ?";


$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $subscription_id
);

$stmt->execute();

$result = $stmt->get_result();

$subscription = $result->fetch_assoc();


if (!$subscription) {

    die("Subscription not found.");

}


/*
|--------------------------------------------------------------------------
| Delete subscription
|--------------------------------------------------------------------------
*/

$error = "";


if ($_SERVER["REQUEST_METHOD"] === "POST") {

    /*
    |--------------------------------------------------------------------------
    | Confirm deletion
    |--------------------------------------------------------------------------
    */

    if (
        !isset($_POST["confirm_delete"]) ||
        $_POST["confirm_delete"] !== "yes"
    ) {

        $error =
            "Deletion was not confirmed.";

    }


    /*
    |--------------------------------------------------------------------------
    | Start transaction
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $conn->begin_transaction();


        try {

            /*
            |--------------------------------------------------------------------------
            | Delete subscription payments
            |--------------------------------------------------------------------------
            */

            $sql =
                "DELETE FROM
                    gym_owner_subscription_payments

                 WHERE subscription_id = ?";


            $stmt =
                $conn->prepare($sql);


            if (!$stmt) {

                throw new Exception(
                    $conn->error
                );

            }


            $stmt->bind_param(
                "i",
                $subscription_id
            );


            if (!$stmt->execute()) {

                throw new Exception(
                    $stmt->error
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Delete subscription
            |--------------------------------------------------------------------------
            */

            $sql =
                "DELETE FROM
                    gym_owner_subscriptions

                 WHERE subscription_id = ?";


            $stmt =
                $conn->prepare($sql);


            if (!$stmt) {

                throw new Exception(
                    $conn->error
                );

            }


            $stmt->bind_param(
                "i",
                $subscription_id
            );


            if (!$stmt->execute()) {

                throw new Exception(
                    $stmt->error
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Check whether subscription was deleted
            |--------------------------------------------------------------------------
            */

            if ($stmt->affected_rows === 0) {

                throw new Exception(
                    "Subscription could not be deleted."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | Commit
            |--------------------------------------------------------------------------
            */

            $conn->commit();


            /*
            |--------------------------------------------------------------------------
            | Redirect
            |--------------------------------------------------------------------------
            */

            header(
                "Location: admin_subscriptions.php?deleted=1"
            );

            exit();

        }

        catch (Exception $e) {

            $conn->rollback();

            $error =
                "Failed to delete subscription: " .
                $e->getMessage();

        }

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
        Delete Subscription
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

            max-width: 700px;

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

            padding: 30px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

        }


        .warning {

            background: #fee2e2;

            color: #991b1b;

            padding: 16px;

            border-radius: 8px;

            margin-bottom: 25px;

            line-height: 1.5;

        }


        .error {

            background: #fee2e2;

            color: #991b1b;

            padding: 14px;

            border-radius: 8px;

            margin-bottom: 20px;

        }


        .details {

            margin-bottom: 25px;

        }


        .detail {

            display: flex;

            justify-content:
                space-between;

            gap: 20px;

            padding: 13px 0;

            border-bottom:
                1px solid #e5e7eb;

        }


        .detail:last-child {

            border-bottom: none;

        }


        .label {

            color: #6b7280;

        }


        .value {

            font-weight: bold;

            text-align: right;

        }


        .danger-text {

            color: #b91c1c;

            font-weight: bold;

        }


        .actions {

            display: flex;

            gap: 10px;

            margin-top: 25px;

        }


        .button {

            padding: 12px 20px;

            border: none;

            border-radius: 8px;

            font-size: 15px;

            text-decoration: none;

            cursor: pointer;

        }


        .delete-button {

            background: #dc2626;

            color: white;

        }


        .cancel-button {

            background: #e5e7eb;

            color: #111827;

        }


        .button:hover {

            opacity: 0.85;

        }


        @media (max-width: 600px) {

            .container {

                padding: 15px;

            }


            .header {

                flex-direction: column;

                align-items: flex-start;

                gap: 15px;

            }


            .detail {

                flex-direction: column;

                gap: 5px;

            }


            .value {

                text-align: left;

            }


            .actions {

                flex-direction: column;

            }

        }

    </style>

</head>


<body>


<div class="container">


    <!-- HEADER -->

    <div class="header">

        <h1>
            Delete Subscription
        </h1>


        <a
            href="admin_subscriptions.php"
            class="back"
        >

            ← Subscriptions

        </a>

    </div>



    <div class="card">


        <!-- WARNING -->

        <div class="warning">

            <strong>
                Warning:
            </strong>

            You are about to permanently delete this
            gym owner's subscription.

            Any subscription payment records connected
            to this subscription will also be deleted.

            This action cannot be undone.

        </div>



        <!-- ERROR -->

        <?php if ($error !== ""): ?>

            <div class="error">

                <?php

                echo htmlspecialchars(
                    $error
                );

                ?>

            </div>

        <?php endif; ?>



        <!-- DETAILS -->

        <div class="details">


            <div class="detail">

                <span class="label">
                    Subscription ID
                </span>

                <span class="value">

                    #

                    <?php

                    echo (int)
                        $subscription[
                            "subscription_id"
                        ];

                    ?>

                </span>

            </div>



            <div class="detail">

                <span class="label">
                    Gym Owner
                </span>

                <span class="value">

                    <?php

                    echo htmlspecialchars(
                        $subscription[
                            "owner_name"
                        ]
                    );

                    ?>

                </span>

            </div>



            <div class="detail">

                <span class="label">
                    Email
                </span>

                <span class="value">

                    <?php

                    echo htmlspecialchars(
                        $subscription[
                            "owner_email"
                        ]
                    );

                    ?>

                </span>

            </div>



            <div class="detail">

                <span class="label">
                    Gym
                </span>

                <span class="value">

                    <?php

                    echo htmlspecialchars(
                        $subscription[
                            "gym_name"
                        ] ?? "No gym"
                    );

                    ?>

                </span>

            </div>



            <div class="detail">

                <span class="label">
                    Package
                </span>

                <span class="value">

                    <?php

                    echo htmlspecialchars(
                        $subscription[
                            "plan_name"
                        ]
                    );

                    ?>

                </span>

            </div>



            <div class="detail">

                <span class="label">
                    Price
                </span>

                <span class="value">

                    Rs.

                    <?php

                    echo number_format(
                        $subscription[
                            "price"
                        ],
                        2
                    );

                    ?>

                </span>

            </div>



            <div class="detail">

                <span class="label">
                    Start Date
                </span>

                <span class="value">

                    <?php

                    echo date(
                        "d M Y",
                        strtotime(
                            $subscription[
                                "start_date"
                            ]
                        )
                    );

                    ?>

                </span>

            </div>



            <div class="detail">

                <span class="label">
                    End Date
                </span>

                <span class="value">

                    <?php

                    echo date(
                        "d M Y",
                        strtotime(
                            $subscription[
                                "end_date"
                            ]
                        )
                    );

                    ?>

                </span>

            </div>



            <div class="detail">

                <span class="label">
                    Status
                </span>

                <span class="value danger-text">

                    <?php

                    echo htmlspecialchars(
                        ucfirst(
                            $subscription[
                                "status"
                            ]
                        )
                    );

                    ?>

                </span>

            </div>


        </div>



        <!-- CONFIRM FORM -->

        <form
            method="POST"
            action="admin_subscription_delete.php?id=<?php echo (int)$subscription_id; ?>"
            onsubmit="return confirm('Are you absolutely sure you want to permanently delete this subscription?');"
        >


            <input
                type="hidden"
                name="confirm_delete"
                value="yes"
            >


            <div class="actions">


                <button
                    type="submit"
                    class="button delete-button"
                >

                    Delete Subscription

                </button>


                <a
                    href="admin_subscription_details.php?id=<?php echo (int)$subscription_id; ?>"
                    class="button cancel-button"
                >

                    Cancel

                </a>


            </div>


        </form>


    </div>


</div>


</body>

</html>
