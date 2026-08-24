<?php

session_start();

require_once "backend/db.php";


/*
|--------------------------------------------------------------------------
| HELPER FUNCTION
|--------------------------------------------------------------------------
*/

function e($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}


/*
|--------------------------------------------------------------------------
| CHECK ADMIN LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["admin_id"])) {

    header("Location: admin_login.php");
    exit();

}


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$error = "";

$success = "";

$payment_id = isset($_GET["payment_id"])
    ? (int) $_GET["payment_id"]
    : (int) ($_POST["payment_id"] ?? 0);

$payment = null;

$is_payment_verification = false;


/*
|--------------------------------------------------------------------------
| LOAD PAYMENT IF payment_id WAS PROVIDED
|--------------------------------------------------------------------------
|
| Example:
|
| admin_subscription_create.php?payment_id=1
|
| The payment provides:
|
| owner_id
| subscription_plan_id
| amount
| payment_status
| transaction_reference
|
|--------------------------------------------------------------------------
*/

if ($payment_id > 0) {

    $payment_sql = "
        SELECT

            p.payment_id,
            p.owner_id,
            p.subscription_plan_id,
            p.subscription_id,
            p.amount,
            p.payment_method,
            p.payment_status,
            p.transaction_reference,
            p.gateway_transaction_id,
            p.created_at,

            o.name AS owner_name,
            o.email AS owner_email,
            o.phone AS owner_phone,

            sp.plan_name,
            sp.price,
            sp.member_limit

        FROM owner_subscription_payments p

        INNER JOIN gym_owners o
            ON p.owner_id = o.owner_id

        INNER JOIN subscription_plans sp
            ON p.subscription_plan_id =
               sp.subscription_plan_id

        WHERE p.payment_id = ?

        LIMIT 1
    ";


    $stmt =
        $conn->prepare(
            $payment_sql
        );


    if (!$stmt) {

        die(
            "Database error: " .
            e($conn->error)
        );

    }


    $stmt->bind_param(
        "i",
        $payment_id
    );


    if (!$stmt->execute()) {

        $stmt->close();

        die(
            "Unable to load payment."
        );

    }


    $result =
        $stmt->get_result();


    $payment =
        $result->fetch_assoc();


    $stmt->close();


    if (!$payment) {

        $error =
            "The selected payment does not exist.";

    }
    else {

        $is_payment_verification = true;


        /*
        |--------------------------------------------------------------------------
        | PAYMENT ALREADY LINKED
        |--------------------------------------------------------------------------
        */

        if (
            !empty(
                $payment["subscription_id"]
            )
        ) {

            $error =
                "This payment is already linked to subscription ID " .
                (int)
                $payment["subscription_id"] .
                ".";

        }


        /*
        |--------------------------------------------------------------------------
        | PAYMENT MUST BE SUBMITTED
        |--------------------------------------------------------------------------
        */

        elseif (
            strtolower(
                trim(
                    (string)
                    $payment["payment_status"]
                )
            ) !== "submitted"
        ) {

            $error =
                "This payment cannot be verified because its current status is '" .
                e(
                    $payment["payment_status"]
                ) .
                "'. Only submitted payments can be verified.";

        }

    }

}


/*
|--------------------------------------------------------------------------
| DEFAULT FORM VALUES
|--------------------------------------------------------------------------
*/

$form_owner_id =
    $payment
    ? (int) $payment["owner_id"]
    : (int) ($_POST["owner_id"] ?? 0);


$form_plan_id =
    $payment
    ? (int) $payment["subscription_plan_id"]
    : (int) ($_POST["subscription_plan_id"] ?? 0);


$form_start_date =
    $_POST["start_date"] ??
    date("Y-m-d");


$form_end_date =
    $_POST["end_date"] ??
    date(
        "Y-m-d",
        strtotime("+30 days")
    );


$form_status =
    $_POST["status"] ??
    "active";


/*
|--------------------------------------------------------------------------
| GET GYM OWNERS
|--------------------------------------------------------------------------
*/

$owners = [];


$owners_sql = "
    SELECT
        owner_id,
        name,
        email
    FROM gym_owners
    ORDER BY name ASC
";


$owners_result =
    $conn->query(
        $owners_sql
    );


if (!$owners_result) {

    die(
        "Database error: " .
        e($conn->error)
    );

}


while (
    $owner =
    $owners_result->fetch_assoc()
) {

    $owners[] =
        $owner;

}


/*
|--------------------------------------------------------------------------
| GET SUBSCRIPTION PLANS
|--------------------------------------------------------------------------
*/

$plans = [];


$plans_sql = "
    SELECT
        subscription_plan_id,
        plan_name,
        price,
        member_limit
    FROM subscription_plans
    ORDER BY price ASC
";


$plans_result =
    $conn->query(
        $plans_sql
    );


if (!$plans_result) {

    die(
        "Database error: " .
        e($conn->error)
    );

}


while (
    $plan =
    $plans_result->fetch_assoc()
) {

    $plans[] =
        $plan;

}


/*
|--------------------------------------------------------------------------
| CREATE SUBSCRIPTION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    $error === ""
) {


    /*
    |--------------------------------------------------------------------------
    | PAYMENT VERIFICATION MODE
    |--------------------------------------------------------------------------
    |
    | When payment_id exists, NEVER trust owner_id or plan_id
    | submitted by the browser.
    |
    | We use the values stored in the payment record.
    |
    |--------------------------------------------------------------------------
    */

    if (
        $is_payment_verification &&
        $payment
    ) {

        $owner_id =
            (int)
            $payment["owner_id"];


        $subscription_plan_id =
            (int)
            $payment[
                "subscription_plan_id"
            ];

    }
    else {

        $owner_id =
            isset(
                $_POST["owner_id"]
            )
            ? (int)
                $_POST["owner_id"]
            : 0;


        $subscription_plan_id =
            isset(
                $_POST[
                    "subscription_plan_id"
                ]
            )
            ? (int)
                $_POST[
                    "subscription_plan_id"
                ]
            : 0;

    }


    /*
    |--------------------------------------------------------------------------
    | FORM VALUES
    |--------------------------------------------------------------------------
    */

    $start_date =
        trim(
            $_POST[
                "start_date"
            ] ?? ""
        );


    $end_date =
        trim(
            $_POST[
                "end_date"
            ] ?? ""
        );


    $status =
        trim(
            $_POST[
                "status"
            ] ?? "active"
        );


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
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


    elseif (
        $end_date < $start_date
    ) {

        $error =
            "End date cannot be before the start date.";

    }


    elseif (
        !in_array(
            $status,
            [
                "active",
                "expired",
                "cancelled"
            ],
            true
        )
    ) {

        $error =
            "Invalid subscription status.";

    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY OWNER
    |--------------------------------------------------------------------------
    */

    if (
        $error === ""
    ) {

        $check_owner_sql = "
            SELECT
                owner_id
            FROM gym_owners
            WHERE owner_id = ?
            LIMIT 1
        ";


        $stmt =
            $conn->prepare(
                $check_owner_sql
            );


        if (!$stmt) {

            $error =
                "Database error: " .
                $conn->error;

        }
        else {

            $stmt->bind_param(
                "i",
                $owner_id
            );


            $stmt->execute();


            $owner_check =
                $stmt->get_result();


            if (
                $owner_check->num_rows === 0
            ) {

                $error =
                    "Selected gym owner does not exist.";

            }


            $stmt->close();

        }

    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY PLAN
    |--------------------------------------------------------------------------
    */

    if (
        $error === ""
    ) {

        $check_plan_sql = "
            SELECT
                subscription_plan_id,
                plan_name,
                price,
                member_limit
            FROM subscription_plans
            WHERE subscription_plan_id = ?
            LIMIT 1
        ";


        $stmt =
            $conn->prepare(
                $check_plan_sql
            );


        if (!$stmt) {

            $error =
                "Database error: " .
                $conn->error;

        }
        else {

            $stmt->bind_param(
                "i",
                $subscription_plan_id
            );


            $stmt->execute();


            $plan_check =
                $stmt->get_result();


            $selected_plan =
                $plan_check->fetch_assoc();


            if (!$selected_plan) {

                $error =
                    "Selected subscription plan does not exist.";

            }


            $stmt->close();

        }

    }


    /*
    |--------------------------------------------------------------------------
    | PAYMENT RE-CHECK
    |--------------------------------------------------------------------------
    |
    | This is important.
    |
    | Someone could leave the page open and another admin could
    | process the payment before this form is submitted.
    |
    |--------------------------------------------------------------------------
    */

    if (
        $error === "" &&
        $is_payment_verification
    ) {

        $payment_check_sql = "
            SELECT

                payment_id,
                owner_id,
                subscription_plan_id,
                subscription_id,
                payment_status

            FROM owner_subscription_payments

            WHERE payment_id = ?

            LIMIT 1
        ";


        $stmt =
            $conn->prepare(
                $payment_check_sql
            );


        if (!$stmt) {

            $error =
                "Database error: " .
                $conn->error;

        }
        else {

            $stmt->bind_param(
                "i",
                $payment_id
            );


            $stmt->execute();


            $payment_check =
                $stmt->get_result();


            $latest_payment =
                $payment_check->fetch_assoc();


            $stmt->close();


            if (!$latest_payment) {

                $error =
                    "The payment no longer exists.";

            }
            elseif (
                !empty(
                    $latest_payment[
                        "subscription_id"
                    ]
                )
            ) {

                $error =
                    "This payment has already been linked to subscription ID " .
                    (int)
                    $latest_payment[
                        "subscription_id"
                    ] .
                    ".";

            }
            elseif (
                strtolower(
                    trim(
                        (string)
                        $latest_payment[
                            "payment_status"
                        ]
                    )
                ) !== "submitted"
            ) {

                $error =
                    "This payment is no longer in submitted status.";

            }
            elseif (
                (int)
                $latest_payment[
                    "owner_id"
                ] !== $owner_id
            ) {

                $error =
                    "Payment owner verification failed.";

            }
            elseif (
                (int)
                $latest_payment[
                    "subscription_plan_id"
                ] !==
                $subscription_plan_id
            ) {

                $error =
                    "Payment plan verification failed.";

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | CREATE SUBSCRIPTION
    |--------------------------------------------------------------------------
    */

    if (
        $error === ""
    ) {

        /*
        |--------------------------------------------------------------------------
        | START DATABASE TRANSACTION
        |--------------------------------------------------------------------------
        */

        $conn->begin_transaction();


        try {


            /*
            |--------------------------------------------------------------------------
            | INSERT SUBSCRIPTION
            |--------------------------------------------------------------------------
            */

            $insert_sql = "
                INSERT INTO gym_owner_subscriptions
                (
                    owner_id,
                    subscription_plan_id,
                    start_date,
                    end_date,
                    status
                )
                VALUES
                (?, ?, ?, ?, ?)
            ";


            $stmt =
                $conn->prepare(
                    $insert_sql
                );


            if (!$stmt) {

                throw new Exception(
                    "Unable to prepare subscription creation."
                );

            }


            $stmt->bind_param(
                "iisss",
                $owner_id,
                $subscription_plan_id,
                $start_date,
                $end_date,
                $status
            );


            if (!$stmt->execute()) {

                throw new Exception(
                    "Failed to create subscription: " .
                    $stmt->error
                );

            }


            /*
            |--------------------------------------------------------------------------
            | GET NEW SUBSCRIPTION ID
            |--------------------------------------------------------------------------
            */

            $new_subscription_id =
                (int)
                $conn->insert_id;


            $stmt->close();


            if (
                $new_subscription_id <= 0
            ) {

                throw new Exception(
                    "Subscription was created but its ID could not be obtained."
                );

            }


            /*
            |--------------------------------------------------------------------------
            | LINK PAYMENT TO SUBSCRIPTION
            |--------------------------------------------------------------------------
            */

            if (
                $is_payment_verification
            ) {

                $update_payment_sql = "
                    UPDATE owner_subscription_payments

                    SET
                        subscription_id = ?,
                        payment_status = 'paid'

                    WHERE payment_id = ?

                    AND subscription_id IS NULL

                    AND payment_status = 'submitted'
                ";


                $stmt =
                    $conn->prepare(
                        $update_payment_sql
                    );


                if (!$stmt) {

                    throw new Exception(
                        "Unable to prepare payment update."
                    );

                }


                $stmt->bind_param(
                    "ii",
                    $new_subscription_id,
                    $payment_id
                );


                if (!$stmt->execute()) {

                    throw new Exception(
                        "Failed to link payment to subscription: " .
                        $stmt->error
                    );

                }


                /*
                |--------------------------------------------------------------------------
                | MAKE SURE EXACTLY ONE PAYMENT WAS UPDATED
                |--------------------------------------------------------------------------
                */

                if (
                    $stmt->affected_rows !== 1
                ) {

                    $stmt->close();


                    throw new Exception(
                        "The payment could not be linked. It may have already been processed."
                    );

                }


                $stmt->close();

            }


            /*
            |--------------------------------------------------------------------------
            | COMMIT
            |--------------------------------------------------------------------------
            */

            $conn->commit();


            /*
            |--------------------------------------------------------------------------
            | SUCCESS REDIRECT
            |--------------------------------------------------------------------------
            */

            if (
                $is_payment_verification
            ) {

                header(
                    "Location: admin_subscription_payments.php?verified=1"
                );

                exit();

            }


            header(
                "Location: admin_subscriptions.php?created=1"
            );

            exit();


        }
        catch (
            Exception $e
        ) {

            /*
            |--------------------------------------------------------------------------
            | ROLLBACK
            |--------------------------------------------------------------------------
            */

            $conn->rollback();


            $error =
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

        <?php

        echo $is_payment_verification
            ? "Verify Subscription Payment"
            : "Create Subscription";

        ?>

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

            max-width: 850px;

            margin: auto;

            padding: 30px;

        }


        .header {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            margin-bottom: 25px;

            gap: 20px;

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

            font-weight: bold;

            white-space: nowrap;

        }


        .card {

            background: white;

            padding: 30px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0, 0, 0, 0.06);

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

            border:
                1px solid #d1d5db;

            border-radius: 8px;

            font-size: 15px;

            background: white;

        }


        input:focus,
        select:focus {

            outline: none;

            border-color: #111827;

        }


        input:disabled,
        select:disabled {

            background: #f3f4f6;

            color: #4b5563;

            cursor: not-allowed;

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

            line-height: 1.5;

        }


        .payment-box {

            background: #eff6ff;

            border:
                1px solid #bfdbfe;

            padding: 20px;

            border-radius: 10px;

            margin-bottom: 25px;

        }


        .payment-box h2 {

            margin:
                0 0 15px;

            font-size: 20px;

        }


        .payment-grid {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 15px;

        }


        .payment-item {

            background: white;

            padding: 14px;

            border-radius: 8px;

        }


        .payment-label {

            color: #6b7280;

            font-size: 12px;

            margin-bottom: 5px;

            text-transform: uppercase;

            font-weight: bold;

        }


        .payment-value {

            font-weight: bold;

        }


        .payment-status {

            display: inline-block;

            background: #dbeafe;

            color: #1d4ed8;

            padding: 5px 9px;

            border-radius: 20px;

            font-size: 12px;

        }


        .warning {

            background: #fffbeb;

            border:
                1px solid #fde68a;

            color: #92400e;

            padding: 14px;

            border-radius: 8px;

            margin-bottom: 20px;

            line-height: 1.5;

        }


        .actions {

            display: flex;

            gap: 10px;

            margin-top: 25px;

            flex-wrap: wrap;

        }


        .button {

            padding: 12px 20px;

            border: none;

            border-radius: 8px;

            font-size: 15px;

            cursor: pointer;

            text-decoration: none;

            font-weight: bold;

        }


        .save-button {

            background: #16a34a;

            color: white;

        }


        .normal-save-button {

            background: #111827;

            color: white;

        }


        .cancel-button {

            background: #e5e7eb;

            color: #111827;

        }


        .save-button:hover,
        .normal-save-button:hover,
        .cancel-button:hover {

            opacity: 0.85;

        }


        @media (max-width: 650px) {

            .container {

                padding: 15px;

            }


            .header {

                flex-direction: column;

                align-items: flex-start;

            }


            .payment-grid {

                grid-template-columns: 1fr;

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

                <?php

                echo $is_payment_verification
                    ? "Verify Subscription Payment"
                    : "Create Subscription";

                ?>

            </h1>


            <p>

                <?php

                if (
                    $is_payment_verification
                ) {

                    echo
                        "Verify the submitted payment and create its subscription.";

                }
                else {

                    echo
                        "Create a subscription for a gym owner.";

                }

                ?>

            </p>

        </div>


        <a
            href="<?php

                echo $is_payment_verification
                    ? "admin_subscription_payments.php"
                    : "admin_subscriptions.php";

            ?>"
            class="back"
        >

            ← Back

        </a>


    </div>



    <!-- CARD -->

    <div class="card">


        <?php if (
            $error !== ""
        ): ?>


            <div class="error">

                <?php

                echo e(
                    $error
                );

                ?>

            </div>


        <?php endif; ?>



        <?php if (
            $is_payment_verification &&
            $payment
        ): ?>


            <!-- PAYMENT INFORMATION -->

            <div class="payment-box">


                <h2>
                    Payment to Verify
                </h2>


                <div class="payment-grid">


                    <div class="payment-item">

                        <div class="payment-label">
                            Payment ID
                        </div>

                        <div class="payment-value">

                            #<?php

                            echo (int)
                                $payment[
                                    "payment_id"
                                ];

                            ?>

                        </div>

                    </div>



                    <div class="payment-item">

                        <div class="payment-label">
                            Payment Status
                        </div>

                        <div class="payment-value">

                            <span class="payment-status">

                                <?php

                                echo e(
                                    ucfirst(
                                        $payment[
                                            "payment_status"
                                        ]
                                    )
                                );

                                ?>

                            </span>

                        </div>

                    </div>



                    <div class="payment-item">

                        <div class="payment-label">
                            Gym Owner
                        </div>

                        <div class="payment-value">

                            <?php

                            echo e(
                                $payment[
                                    "owner_name"
                                ]
                            );

                            ?>

                        </div>

                    </div>



                    <div class="payment-item">

                        <div class="payment-label">
                            Email
                        </div>

                        <div class="payment-value">

                            <?php

                            echo e(
                                $payment[
                                    "owner_email"
                                ]
                            );

                            ?>

                        </div>

                    </div>



                    <div class="payment-item">

                        <div class="payment-label">
                            Plan
                        </div>

                        <div class="payment-value">

                            <?php

                            echo e(
                                $payment[
                                    "plan_name"
                                ]
                            );

                            ?>

                        </div>

                    </div>



                    <div class="payment-item">

                        <div class="payment-label">
                            Amount
                        </div>

                        <div class="payment-value">

                            Rs.

                            <?php

                            echo number_format(
                                (float)
                                $payment[
                                    "amount"
                                ],
                                2
                            );

                            ?>

                        </div>

                    </div>



                    <div class="payment-item">

                        <div class="payment-label">
                            Payment Method
                        </div>

                        <div class="payment-value">

                            <?php

                            echo e(
                                ucwords(
                                    str_replace(
                                        "_",
                                        " ",
                                        $payment[
                                            "payment_method"
                                        ]
                                    )
                                )
                            );

                            ?>

                        </div>

                    </div>



                    <div class="payment-item">

                        <div class="payment-label">
                            Transaction Reference
                        </div>

                        <div class="payment-value">

                            <?php

                            echo e(
                                $payment[
                                    "transaction_reference"
                                ] ??
                                "-"
                            );

                            ?>

                        </div>

                    </div>


                </div>


                <?php if (
                    !empty(
                        $payment[
                            "gateway_transaction_id"
                        ]
                    )
                ): ?>


                    <div
                        class="help"
                        style="margin-top: 15px;"
                    >

                        Gateway Transaction ID:

                        <strong>

                            <?php

                            echo e(
                                $payment[
                                    "gateway_transaction_id"
                                ]
                            );

                            ?>

                        </strong>

                    </div>


                <?php endif; ?>


            </div>


            <div class="warning">

                <strong>
                    Verify the payment before continuing.
                </strong>

                Creating this subscription will mark
                payment

                <strong>
                    #<?php echo (int)$payment_id; ?>
                </strong>

                as

                <strong>
                    Paid
                </strong>

                and permanently link it to the new
                subscription.

            </div>


        <?php endif; ?>



        <!-- FORM -->

        <form
            method="POST"
            action=""
        >


            <?php if (
                $is_payment_verification
            ): ?>


                <input
                    type="hidden"
                    name="payment_id"
                    value="<?php
                        echo (int)
                            $payment_id;
                    ?>"
                >


            <?php endif; ?>



            <!-- OWNER -->

            <div class="form-group">


                <label for="owner_id">

                    Gym Owner
                    <span class="required">*</span>

                </label>


                <?php if (
                    $is_payment_verification &&
                    $payment
                ): ?>


                    <input
                        type="text"
                        value="<?php
                            echo e(
                                $payment[
                                    "owner_name"
                                ]
                            );
                        ?>"
                        disabled
                    >


                    <div class="help">

                        Owner is taken directly from
                        payment #<?php echo (int)$payment_id; ?>.

                    </div>


                <?php else: ?>


                    <select
                        name="owner_id"
                        id="owner_id"
                        required
                    >

                        <option value="">
                            Select Gym Owner
                        </option>


                        <?php foreach (
                            $owners as $owner
                        ): ?>


                            <option
                                value="<?php
                                    echo (int)
                                        $owner[
                                            "owner_id"
                                        ];
                                ?>"
                                <?php

                                if (
                                    $form_owner_id ===
                                    (int)
                                    $owner[
                                        "owner_id"
                                    ]
                                ) {

                                    echo "selected";

                                }

                                ?>
                            >

                                <?php

                                echo e(
                                    $owner[
                                        "name"
                                    ]
                                );

                                ?>

                                —

                                <?php

                                echo e(
                                    $owner[
                                        "email"
                                    ]
                                );

                                ?>

                            </option>


                        <?php endforeach; ?>


                    </select>


                <?php endif; ?>


            </div>



            <!-- PLAN -->

            <div class="form-group">


                <label for="subscription_plan_id">

                    Subscription Plan
                    <span class="required">*</span>

                </label>


                <?php if (
                    $is_payment_verification &&
                    $payment
                ): ?>


                    <input
                        type="text"
                        value="<?php

                            echo e(
                                $payment[
                                    "plan_name"
                                ]
                            );

                            echo
                                " — Rs. ";

                            echo number_format(
                                (float)
                                $payment[
                                    "amount"
                                ],
                                2
                            );

                        ?>"
                        disabled
                    >


                    <div class="help">

                        Plan is taken directly from
                        payment #<?php echo (int)$payment_id; ?>.

                    </div>


                <?php else: ?>


                    <select
                        name="subscription_plan_id"
                        id="subscription_plan_id"
                        required
                    >

                        <option value="">
                            Select Subscription Plan
                        </option>


                        <?php foreach (
                            $plans as $plan
                        ): ?>


                            <option
                                value="<?php
                                    echo (int)
                                        $plan[
                                            "subscription_plan_id"
                                        ];
                                ?>"
                                <?php

                                if (
                                    $form_plan_id ===
                                    (int)
                                    $plan[
                                        "subscription_plan_id"
                                    ]
                                ) {

                                    echo "selected";

                                }

                                ?>
                            >

                                <?php

                                echo e(
                                    $plan[
                                        "plan_name"
                                    ]
                                );

                                ?>

                                —

                                Rs.

                                <?php

                                echo number_format(
                                    (float)
                                    $plan[
                                        "price"
                                    ],
                                    2
                                );

                                ?>

                                —

                                <?php

                                if (
                                    $plan[
                                        "member_limit"
                                    ] !== null
                                ) {

                                    echo number_format(
                                        (int)
                                        $plan[
                                            "member_limit"
                                        ]
                                    );

                                    echo " members";

                                }
                                else {

                                    echo "Unlimited members";

                                }

                                ?>

                            </option>


                        <?php endforeach; ?>


                    </select>


                <?php endif; ?>


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

                        echo e(
                            $form_start_date
                        );

                    ?>"
                    required
                >


                <div class="help">

                    Enter the date on which the subscription
                    should become active.

                </div>


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

                        echo e(
                            $form_end_date
                        );

                    ?>"
                    required
                >


                <div class="help">

                    Enter the subscription expiry date.

                </div>


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
                            $form_status ===
                            "active"
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
                            $form_status ===
                            "expired"
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
                            $form_status ===
                            "cancelled"
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
                    class="
                        button
                        <?php

                        echo $is_payment_verification
                            ? "save-button"
                            : "normal-save-button";

                        ?>
                    "
                    <?php

                    if (
                        $is_payment_verification &&
                        !$payment
                    ) {

                        echo "disabled";

                    }

                    ?>
                >

                    <?php

                    echo $is_payment_verification
                        ? "✓ Verify Payment & Create Subscription"
                        : "Create Subscription";

                    ?>

                </button>


                <a
                    href="<?php

                        echo $is_payment_verification
                            ? "admin_subscription_payments.php"
                            : "admin_subscriptions.php";

                    ?>"
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