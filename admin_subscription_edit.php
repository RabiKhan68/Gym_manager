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
| Variables
|--------------------------------------------------------------------------
*/

$error = "";


/*
|--------------------------------------------------------------------------
| Get existing subscription
|--------------------------------------------------------------------------
*/

$sql = "SELECT

            subscription_id,
            owner_id,
            subscription_plan_id,
            start_date,
            end_date,
            status

        FROM gym_owner_subscriptions

        WHERE subscription_id = ?";


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
| Get gym owners
|--------------------------------------------------------------------------
*/

$owners_sql = "SELECT
                    owner_id,
                    name,
                    email
               FROM gym_owners
               ORDER BY name ASC";


$owners_result =
    $conn->query($owners_sql);


if (!$owners_result) {

    die(
        "Database error: " .
        htmlspecialchars($conn->error)
    );

}


/*
|--------------------------------------------------------------------------
| Get subscription plans
|--------------------------------------------------------------------------
*/

$plans_sql = "SELECT

                    subscription_plan_id,
                    plan_name,
                    price,
                    member_limit

              FROM subscription_plans

              ORDER BY price ASC";


$plans_result =
    $conn->query($plans_sql);


if (!$plans_result) {

    die(
        "Database error: " .
        htmlspecialchars($conn->error)
    );

}


/*
|--------------------------------------------------------------------------
| Update subscription
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $owner_id =
        isset($_POST["owner_id"])
        ? (int) $_POST["owner_id"]
        : 0;

    $subscription_plan_id =
        isset($_POST["subscription_plan_id"])
        ? (int) $_POST["subscription_plan_id"]
        : 0;

    $start_date =
        trim($_POST["start_date"] ?? "");

    $end_date =
        trim($_POST["end_date"] ?? "");

    $status =
        trim($_POST["status"] ?? "");


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (
        $owner_id <= 0 ||
        $subscription_plan_id <= 0 ||
        $start_date === "" ||
        $end_date === ""
    ) {

        $error =
            "Please fill in all required fields.";

    }

    elseif ($end_date < $start_date) {

        $error =
            "End date cannot be before the start date.";

    }

    elseif (
        !in_array(
            $status,
            ["active", "expired", "cancelled"],
            true
        )
    ) {

        $error =
            "Invalid subscription status.";

    }


    /*
    |--------------------------------------------------------------------------
    | Check owner
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $check_owner_sql =
            "SELECT owner_id
             FROM gym_owners
             WHERE owner_id = ?";


        $stmt =
            $conn->prepare(
                $check_owner_sql
            );


        $stmt->bind_param(
            "i",
            $owner_id
        );


        $stmt->execute();


        $owner_check =
            $stmt->get_result();


        if ($owner_check->num_rows === 0) {

            $error =
                "Selected gym owner does not exist.";

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Check subscription plan
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $check_plan_sql =
            "SELECT subscription_plan_id
             FROM subscription_plans
             WHERE subscription_plan_id = ?";


        $stmt =
            $conn->prepare(
                $check_plan_sql
            );


        $stmt->bind_param(
            "i",
            $subscription_plan_id
        );


        $stmt->execute();


        $plan_check =
            $stmt->get_result();


        if ($plan_check->num_rows === 0) {

            $error =
                "Selected subscription plan does not exist.";

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Update database
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        $update_sql =
            "UPDATE gym_owner_subscriptions

             SET
                owner_id = ?,
                subscription_plan_id = ?,
                start_date = ?,
                end_date = ?,
                status = ?

             WHERE subscription_id = ?";


        $stmt =
            $conn->prepare(
                $update_sql
            );


        if (!$stmt) {

            $error =
                "Database error: " .
                $conn->error;

        } else {

            $stmt->bind_param(
                "iisssi",
                $owner_id,
                $subscription_plan_id,
                $start_date,
                $end_date,
                $status,
                $subscription_id
            );


            if ($stmt->execute()) {

                header(
                    "Location: admin_subscription_details.php?id=" .
                    $subscription_id .
                    "&updated=1"
                );

                exit();

            } else {

                $error =
                    "Failed to update subscription: " .
                    $stmt->error;

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | Keep submitted values if validation fails
    |--------------------------------------------------------------------------
    */

    $subscription["owner_id"] =
        $owner_id;

    $subscription["subscription_plan_id"] =
        $subscription_plan_id;

    $subscription["start_date"] =
        $start_date;

    $subscription["end_date"] =
        $end_date;

    $subscription["status"] =
        $status;

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
        Edit Subscription
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

            max-width: 800px;

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

            padding: 30px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

        }


        .subscription-id {

            background: #f8fafc;

            padding: 12px;

            border-radius: 8px;

            margin-bottom: 25px;

            color: #6b7280;

        }


        .subscription-id strong {

            color: #111827;

        }


        .form-group {

            margin-bottom: 20px;

        }


        label {

            display: block;

            margin-bottom: 7px;

            font-weight: bold;

        }


        .required {

            color: #dc2626;

        }


        input,
        select {

            width: 100%;

            padding: 12px;

            border: 1px solid #d1d5db;

            border-radius: 8px;

            font-size: 15px;

            background: white;

        }


        input:focus,
        select:focus {

            outline: none;

            border-color: #111827;

        }


        .help {

            margin-top: 5px;

            color: #6b7280;

            font-size: 13px;

        }


        .error {

            background: #fee2e2;

            color: #991b1b;

            padding: 14px;

            border-radius: 8px;

            margin-bottom: 20px;

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

            cursor: pointer;

            text-decoration: none;

        }


        .save-button {

            background: #111827;

            color: white;

        }


        .cancel-button {

            background: #e5e7eb;

            color: #111827;

        }


        .save-button:hover,
        .cancel-button:hover {

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


            .card {

                padding: 20px;

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

        <div>

            <h1>
                Edit Subscription
            </h1>

            <p>
                Update gym owner subscription
            </p>

        </div>


        <a
            href="admin_subscriptions.php"
            class="back"
        >

            ← Subscriptions

        </a>

    </div>



    <!-- FORM -->

    <div class="card">


        <div class="subscription-id">

            Subscription ID:

            <strong>

                #<?php

                echo (int)
                    $subscription_id;

                ?>

            </strong>

        </div>



        <?php if ($error !== ""): ?>

            <div class="error">

                <?php

                echo htmlspecialchars(
                    $error
                );

                ?>

            </div>

        <?php endif; ?>



        <form
            method="POST"
            action="admin_subscription_edit.php?id=<?php echo (int)$subscription_id; ?>"
        >


            <!-- OWNER -->

            <div class="form-group">

                <label for="owner_id">

                    Gym Owner
                    <span class="required">*</span>

                </label>


                <select
                    name="owner_id"
                    id="owner_id"
                    required
                >

                    <option value="">
                        Select Gym Owner
                    </option>


                    <?php while (
                        $owner =
                        $owners_result->fetch_assoc()
                    ): ?>

                        <option
                            value="<?php echo (int)$owner["owner_id"]; ?>"
                            <?php

                            if (
                                (int)$subscription[
                                    "owner_id"
                                ]
                                ===
                                (int)$owner[
                                    "owner_id"
                                ]
                            ) {

                                echo "selected";

                            }

                            ?>
                        >

                            <?php

                            echo htmlspecialchars(
                                $owner["name"]
                            );

                            ?>

                            —

                            <?php

                            echo htmlspecialchars(
                                $owner["email"]
                            );

                            ?>

                        </option>

                    <?php endwhile; ?>

                </select>

                <div class="help">
                    Select the gym owner associated with this subscription.
                </div>

            </div>



            <!-- PLAN -->

            <div class="form-group">

                <label for="subscription_plan_id">

                    Subscription Plan
                    <span class="required">*</span>

                </label>


                <select
                    name="subscription_plan_id"
                    id="subscription_plan_id"
                    required
                >

                    <option value="">
                        Select Subscription Plan
                    </option>


                    <?php while (
                        $plan =
                        $plans_result->fetch_assoc()
                    ): ?>

                        <option
                            value="<?php echo (int)$plan["subscription_plan_id"]; ?>"
                            <?php

                            if (
                                (int)$subscription[
                                    "subscription_plan_id"
                                ]
                                ===
                                (int)$plan[
                                    "subscription_plan_id"
                                ]
                            ) {

                                echo "selected";

                            }

                            ?>
                        >

                            <?php

                            echo htmlspecialchars(
                                $plan["plan_name"]
                            );

                            ?>

                            —

                            Rs.

                            <?php

                            echo number_format(
                                $plan["price"],
                                2
                            );

                            ?>

                            —

                            <?php

                            if (
                                $plan["member_limit"]
                                !== null
                            ) {

                                echo (int)
                                    $plan[
                                        "member_limit"
                                    ];

                                echo " members";

                            } else {

                                echo "Unlimited members";

                            }

                            ?>

                        </option>

                    <?php endwhile; ?>

                </select>

                <div class="help">
                    Select the subscription package.
                </div>

            </div>



            <!-- START DATE -->

            <div class="form-group">

                <label for="start_date">

                    Start Date
                    <span class="required">*</span>

                </label>


                <input
                    type="date"
                    name="start_date"
                    id="start_date"
                    value="<?php

                        echo htmlspecialchars(
                            $subscription[
                                "start_date"
                            ]
                        );

                    ?>"
                    required
                >

            </div>



            <!-- END DATE -->

            <div class="form-group">

                <label for="end_date">

                    End Date
                    <span class="required">*</span>

                </label>


                <input
                    type="date"
                    name="end_date"
                    id="end_date"
                    value="<?php

                        echo htmlspecialchars(
                            $subscription[
                                "end_date"
                            ]
                        );

                    ?>"
                    required
                >

            </div>



            <!-- STATUS -->

            <div class="form-group">

                <label for="status">

                    Status
                    <span class="required">*</span>

                </label>


                <select
                    name="status"
                    id="status"
                    required
                >


                    <option
                        value="active"
                        <?php

                        if (
                            $subscription[
                                "status"
                            ]
                            === "active"
                        ) {

                            echo "selected";

                        }

                        ?>
                    >

                        Active

                    </option>


                    <option
                        value="expired"
                        <?php

                        if (
                            $subscription[
                                "status"
                            ]
                            === "expired"
                        ) {

                            echo "selected";

                        }

                        ?>
                    >

                        Expired

                    </option>


                    <option
                        value="cancelled"
                        <?php

                        if (
                            $subscription[
                                "status"
                            ]
                            === "cancelled"
                        ) {

                            echo "selected";

                        }

                        ?>
                    >

                        Cancelled

                    </option>


                </select>

            </div>



            <!-- ACTIONS -->

            <div class="actions">


                <button
                    type="submit"
                    class="button save-button"
                >

                    Save Changes

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
