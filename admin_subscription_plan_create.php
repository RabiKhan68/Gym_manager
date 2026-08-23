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
| Variables
|--------------------------------------------------------------------------
*/

$error = "";

$plan_name = "";
$price = "";
$member_limit = "";


/*
|--------------------------------------------------------------------------
| Create subscription plan
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $plan_name = trim(
        $_POST["plan_name"] ?? ""
    );

    $price = trim(
        $_POST["price"] ?? ""
    );

    $member_limit = trim(
        $_POST["member_limit"] ?? ""
    );


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if ($plan_name === "") {

        $error =
            "Please enter a subscription plan name.";

    }

    elseif (
        $price === "" ||
        !is_numeric($price) ||
        (float)$price < 0
    ) {

        $error =
            "Please enter a valid price.";

    }

    elseif (
        $member_limit !== "" &&
        (
            !ctype_digit($member_limit) ||
            (int)$member_limit <= 0
        )
    ) {

        $error =
            "Member limit must be a positive whole number or left empty for unlimited.";

    }


    /*
    |--------------------------------------------------------------------------
    | Insert plan
    |--------------------------------------------------------------------------
    */

    if ($error === "") {

        /*
        | Empty member limit = NULL
        */

        if ($member_limit === "") {

            $member_limit_value = null;

        } else {

            $member_limit_value =
                (int)$member_limit;

        }


        $price_value =
            (float)$price;


        $stmt = $conn->prepare(
            "INSERT INTO subscription_plans
            (
                plan_name,
                price,
                member_limit
            )
            VALUES (?, ?, ?)"
        );


        if (!$stmt) {

            $error =
                "Database error: " .
                htmlspecialchars(
                    $conn->error
                );

        } else {

            /*
            |--------------------------------------------------------------------------
            | Bind values
            |--------------------------------------------------------------------------
            */

            $stmt->bind_param(
                "sdi",
                $plan_name,
                $price_value,
                $member_limit_value
            );


            if ($stmt->execute()) {

                $stmt->close();


                /*
                |--------------------------------------------------------------------------
                | Redirect after successful creation
                |--------------------------------------------------------------------------
                */

                header(
                    "Location: admin_subscription_plans.php"
                );

                exit();

            } else {

                $error =
                    "Failed to create subscription plan: " .
                    htmlspecialchars(
                        $stmt->error
                    );

                $stmt->close();

            }

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
        Create Subscription Plan
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

            max-width: 750px;

            margin: auto;

            padding: 30px;

        }


        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        .header {

            margin-bottom: 25px;

        }


        .header h1 {

            margin: 0;

            font-size: 28px;

        }


        .header p {

            margin: 7px 0 0;

            color: #6b7280;

        }


        /*
        |--------------------------------------------------------------------------
        | CARD
        |--------------------------------------------------------------------------
        */

        .card {

            background: white;

            padding: 30px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

        }


        /*
        |--------------------------------------------------------------------------
        | ERROR
        |--------------------------------------------------------------------------
        */

        .error {

            background: #fee2e2;

            color: #991b1b;

            padding: 12px 15px;

            border-radius: 8px;

            margin-bottom: 20px;

            font-size: 14px;

        }


        /*
        |--------------------------------------------------------------------------
        | FORM
        |--------------------------------------------------------------------------
        */

        .form-group {

            margin-bottom: 20px;

        }


        label {

            display: block;

            font-weight: bold;

            margin-bottom: 8px;

        }


        .required {

            color: #dc2626;

        }


        input {

            width: 100%;

            padding: 12px 14px;

            border: 1px solid #d1d5db;

            border-radius: 8px;

            font-size: 15px;

            outline: none;

        }


        input:focus {

            border-color: #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37,99,235,0.10);

        }


        .help {

            margin-top: 6px;

            font-size: 13px;

            color: #6b7280;

        }


        /*
        |--------------------------------------------------------------------------
        | BUTTONS
        |--------------------------------------------------------------------------
        */

        .actions {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            margin-top: 30px;

            gap: 10px;

        }


        .button {

            display: inline-block;

            padding: 11px 18px;

            border: none;

            border-radius: 8px;

            text-decoration: none;

            font-size: 14px;

            font-weight: bold;

            cursor: pointer;

        }


        .cancel-button {

            background: #e5e7eb;

            color: #374151;

        }


        .create-button {

            background: #16a34a;

            color: white;

        }


        .button:hover {

            opacity: 0.85;

        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 600px) {

            .container {

                padding: 15px;

            }


            .card {

                padding: 20px;

            }


            .actions {

                flex-direction: column-reverse;

                align-items: stretch;

            }


            .button {

                text-align: center;

                width: 100%;

            }

        }

    </style>

</head>


<body>


<div class="container">


    <!-- HEADER -->

    <div class="header">

        <h1>
            Create Subscription Plan
        </h1>

        <p>
            Create a new package for gym owners.
        </p>

    </div>



    <!-- CARD -->

    <div class="card">


        <?php if ($error !== ""): ?>

            <div class="error">

                <?php

                echo $error;

                ?>

            </div>

        <?php endif; ?>



        <form
            method="POST"
            action=""
        >


            <!-- PLAN NAME -->

            <div class="form-group">

                <label for="plan_name">

                    Plan Name

                    <span class="required">
                        *
                    </span>

                </label>


                <input
                    type="text"
                    id="plan_name"
                    name="plan_name"
                    value="<?php echo htmlspecialchars($plan_name); ?>"
                    placeholder="Example: Basic"
                    required
                >

            </div>



            <!-- PRICE -->

            <div class="form-group">

                <label for="price">

                    Price

                    <span class="required">
                        *
                    </span>

                </label>


                <input
                    type="number"
                    id="price"
                    name="price"
                    value="<?php echo htmlspecialchars($price); ?>"
                    placeholder="Example: 2000"
                    min="0"
                    step="0.01"
                    required
                >


                <div class="help">

                    Enter the subscription price in Pakistani Rupees.

                </div>

            </div>



            <!-- MEMBER LIMIT -->

            <div class="form-group">

                <label for="member_limit">

                    Member Limit

                </label>


                <input
                    type="number"
                    id="member_limit"
                    name="member_limit"
                    value="<?php echo htmlspecialchars($member_limit); ?>"
                    placeholder="Example: 50"
                    min="1"
                    step="1"
                >


                <div class="help">

                    Leave this empty if the plan should allow unlimited members.

                </div>

            </div>



            <!-- ACTIONS -->

            <div class="actions">


                <a
                    href="admin_subscription_plans.php"
                    class="button cancel-button"
                >

                    Cancel

                </a>


                <button
                    type="submit"
                    class="button create-button"
                >

                    Create Subscription Plan

                </button>


            </div>


        </form>


    </div>


</div>


</body>

</html>