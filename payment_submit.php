<?php

session_start();

require_once "backend/db.php";


/*
|--------------------------------------------------------------------------
| SUBSCRIPTION PAYMENT SUBMISSION
|--------------------------------------------------------------------------
|
| This file ONLY submits a payment for administrator verification.
|
| IMPORTANT:
|
| pending
|    ↓
| submitted
|    ↓
| administrator verifies payment
|    ↓
| paid
|    ↓
| subscription is created / activated / scheduled
|
| This file MUST NOT:
|
| - activate subscriptions
| - create subscriptions
| - change subscription dates
| - mark payments as paid
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| AUTHENTICATION
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["owner_id"])) {

    header("Location: login.php");
    exit();

}

$owner_id = (int) $_SESSION["owner_id"];


/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
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
| REDIRECT TO SUBSCRIPTION PAGE
|--------------------------------------------------------------------------
*/

function redirectToSubscription()
{
    header("Location: my_subscription.php");
    exit();
}


/*
|--------------------------------------------------------------------------
| REDIRECT TO PAYMENT CHECKOUT
|--------------------------------------------------------------------------
*/

function redirectToCheckout($plan_id = 0)
{
    if ((int) $plan_id > 0) {

        header(
            "Location: payment_checkout.php?plan_id=" .
            (int) $plan_id
        );

    }
    else {

        header(
            "Location: my_subscription.php"
        );

    }

    exit();
}


/*
|--------------------------------------------------------------------------
| ONLY POST REQUESTS ARE ALLOWED
|--------------------------------------------------------------------------
*/

if ($_SERVER["REQUEST_METHOD"] !== "POST") {

    redirectToSubscription();

}


/*
|--------------------------------------------------------------------------
| GET PAYMENT ID
|--------------------------------------------------------------------------
*/

$payment_id =
    isset($_POST["payment_id"])
    ? (int) $_POST["payment_id"]
    : 0;


/*
|--------------------------------------------------------------------------
| GET GATEWAY TRANSACTION REFERENCE
|--------------------------------------------------------------------------
*/

$gateway_transaction_id =
    isset($_POST["gateway_transaction_id"])
    ? trim($_POST["gateway_transaction_id"])
    : "";


/*
|--------------------------------------------------------------------------
| BASIC PAYMENT ID VALIDATION
|--------------------------------------------------------------------------
*/

if ($payment_id <= 0) {

    $_SESSION["payment_error"] =
        "Invalid payment request.";

    redirectToSubscription();

}


/*
|--------------------------------------------------------------------------
| BASIC TRANSACTION REFERENCE VALIDATION
|--------------------------------------------------------------------------
*/

if ($gateway_transaction_id === "") {

    $_SESSION["payment_error"] =
        "Please enter your JazzCash/Raast transaction reference.";

    redirectToSubscription();

}


/*
|--------------------------------------------------------------------------
| REMOVE UNNECESSARY WHITESPACE
|--------------------------------------------------------------------------
|
| A transaction reference should not contain leading/trailing spaces.
|
| We already used trim() above.
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| TRANSACTION REFERENCE LENGTH
|--------------------------------------------------------------------------
*/

$reference_length =
    strlen($gateway_transaction_id);


if ($reference_length < 3) {

    $_SESSION["payment_error"] =
        "The transaction reference appears to be invalid.";

    redirectToSubscription();

}


if ($reference_length > 150) {

    $_SESSION["payment_error"] =
        "The transaction reference is too long.";

    redirectToSubscription();

}


/*
|--------------------------------------------------------------------------
| TRANSACTION REFERENCE CHARACTER VALIDATION
|--------------------------------------------------------------------------
|
| Allow:
|
| - letters
| - numbers
| - spaces
| - hyphens
| - underscores
| - slash
| - dot
|
| This prevents unexpected HTML/control characters from being
| stored in the database.
|
|--------------------------------------------------------------------------
*/

if (
    !preg_match(
        '/^[A-Za-z0-9 ._\/-]+$/',
        $gateway_transaction_id
    )
) {

    $_SESSION["payment_error"] =
        "The transaction reference contains invalid characters.";

    redirectToSubscription();

}


/*
|--------------------------------------------------------------------------
| START DATABASE TRANSACTION
|--------------------------------------------------------------------------
*/

$transaction_started = false;


try {

    $conn->begin_transaction();

    $transaction_started = true;


    /*
    |--------------------------------------------------------------------------
    | GET AND LOCK PAYMENT
    |--------------------------------------------------------------------------
    |
    | We verify:
    |
    | - payment exists
    | - payment belongs to this owner
    | - payment plan still exists
    | - payment is currently pending
    |
    | FOR UPDATE prevents two requests from submitting the same
    | payment simultaneously.
    |
    |--------------------------------------------------------------------------
    */

    $sql = "
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

            sp.plan_name,
            sp.price,
            sp.member_limit

        FROM owner_subscription_payments p

        INNER JOIN subscription_plans sp
            ON p.subscription_plan_id =
               sp.subscription_plan_id

        WHERE p.payment_id = ?

        AND p.owner_id = ?

        LIMIT 1

        FOR UPDATE
    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        throw new Exception(
            "Unable to verify the payment request."
        );

    }


    $stmt->bind_param(
        "ii",
        $payment_id,
        $owner_id
    );


    if (!$stmt->execute()) {

        $stmt->close();

        throw new Exception(
            "Unable to verify the payment request."
        );

    }


    $result =
        $stmt->get_result();


    $payment =
        $result->fetch_assoc();


    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | PAYMENT NOT FOUND
    |--------------------------------------------------------------------------
    */

    if (!$payment) {

        throw new Exception(
            "Payment request was not found."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | SAVE PLAN ID FOR ERROR REDIRECT
    |--------------------------------------------------------------------------
    */

    $plan_id =
        (int)
        $payment[
            "subscription_plan_id"
        ];


    /*
    |--------------------------------------------------------------------------
    | VERIFY PAYMENT OWNER
    |--------------------------------------------------------------------------
    |
    | The SQL query already verifies this.
    |
    | This additional check makes the logic explicit.
    |
    |--------------------------------------------------------------------------
    */

    if (
        (int)
        $payment["owner_id"]
        !==
        $owner_id
    ) {

        throw new Exception(
            "You are not authorized to submit this payment."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | PAYMENT STATUS CHECK
    |--------------------------------------------------------------------------
    */

    $payment_status =
        strtolower(
            trim(
                (string)
                $payment["payment_status"]
            )
        );


    /*
    |--------------------------------------------------------------------------
    | ALREADY PAID
    |--------------------------------------------------------------------------
    */

    if (
        $payment_status === "paid"
    ) {

        throw new Exception(
            "This payment has already been verified."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | ALREADY SUBMITTED
    |--------------------------------------------------------------------------
    */

    if (
        $payment_status === "submitted"
    ) {

        throw new Exception(
            "This payment has already been submitted and is waiting for administrator verification."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | PAYMENT MUST BE PENDING
    |--------------------------------------------------------------------------
    */

    if (
        $payment_status !== "pending"
    ) {

        throw new Exception(
            "This payment request is no longer available for submission."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | PAYMENT METHOD CHECK
    |--------------------------------------------------------------------------
    |
    | payment_checkout.php currently creates JazzCash payments.
    |
    |--------------------------------------------------------------------------
    */

    $payment_method =
        strtolower(
            trim(
                (string)
                $payment["payment_method"]
            )
        );


    if (
        $payment_method !== "jazzcash"
    ) {

        throw new Exception(
            "Invalid payment method for this payment."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | VERIFY PAYMENT PLAN
    |--------------------------------------------------------------------------
    |
    | The INNER JOIN above already confirms that the plan exists.
    |
    |--------------------------------------------------------------------------
    */

    if (
        empty($payment["plan_name"])
    ) {

        throw new Exception(
            "The subscription plan associated with this payment is invalid."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | PREVENT DUPLICATE GATEWAY TRANSACTION REFERENCES
    |--------------------------------------------------------------------------
    |
    | A single JazzCash/Raast transaction reference must not be used
    | for multiple subscription payments.
    |
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT

            payment_id,
            owner_id,
            payment_status

        FROM owner_subscription_payments

        WHERE gateway_transaction_id = ?

        AND payment_id <> ?

        LIMIT 1

        FOR UPDATE
    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        throw new Exception(
            "Unable to validate the transaction reference."
        );

    }


    $stmt->bind_param(
        "si",
        $gateway_transaction_id,
        $payment_id
    );


    if (!$stmt->execute()) {

        $stmt->close();

        throw new Exception(
            "Unable to validate the transaction reference."
        );

    }


    $result =
        $stmt->get_result();


    $existing_transaction =
        $result->fetch_assoc();


    $stmt->close();


    if ($existing_transaction) {

        throw new Exception(
            "This JazzCash/Raast transaction reference has already been submitted."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | EXTRA DUPLICATE CHECK
    |--------------------------------------------------------------------------
    |
    | If the database uses a case-sensitive collation, the query above
    | may treat ABC123 and abc123 as different values.
    |
    | This check compares the normalized value using LOWER().
    |
    |--------------------------------------------------------------------------
    */

    $sql = "
        SELECT

            payment_id,
            owner_id,
            payment_status

        FROM owner_subscription_payments

        WHERE LOWER(
            TRIM(
                gateway_transaction_id
            )
        ) = LOWER(
            TRIM(?)
        )

        AND payment_id <> ?

        LIMIT 1

        FOR UPDATE
    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        throw new Exception(
            "Unable to perform transaction reference validation."
        );

    }


    $stmt->bind_param(
        "si",
        $gateway_transaction_id,
        $payment_id
    );


    if (!$stmt->execute()) {

        $stmt->close();

        throw new Exception(
            "Unable to perform transaction reference validation."
        );

    }


    $result =
        $stmt->get_result();


    $duplicate_reference =
        $result->fetch_assoc();


    $stmt->close();


    if ($duplicate_reference) {

        throw new Exception(
            "This JazzCash/Raast transaction reference has already been submitted."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | OPTIONAL: VERIFY PAYMENT AMOUNT
    |--------------------------------------------------------------------------
    |
    | We verify that the payment amount stored when the payment was
    | created still matches the current subscription plan price.
    |
    | We do NOT trust an amount supplied by the browser.
    |
    |--------------------------------------------------------------------------
    */

    $payment_amount =
        (float)
        $payment["amount"];


    $plan_price =
        (float)
        $payment["price"];


    /*
    |--------------------------------------------------------------------------
    | PRICE VALIDATION
    |--------------------------------------------------------------------------
    |
    | Currency values should normally be stored as DECIMAL.
    | Rounding to two decimal places prevents floating-point noise.
    |
    |--------------------------------------------------------------------------
    */

    if (
        round(
            $payment_amount,
            2
        )
        !==
        round(
            $plan_price,
            2
        )
    ) {

        throw new Exception(
            "The payment amount does not match the current subscription plan price. Please create a new payment request."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE PAYMENT
    |--------------------------------------------------------------------------
    |
    | IMPORTANT:
    |
    | pending → submitted
    |
    | We DO NOT change it to paid.
    |
    | We DO NOT create a subscription here.
    |
    |--------------------------------------------------------------------------
    */

    $sql = "
        UPDATE owner_subscription_payments

        SET

            payment_status = 'submitted',

            gateway_transaction_id = ?

        WHERE payment_id = ?

        AND owner_id = ?

        AND payment_status = 'pending'
    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        throw new Exception(
            "Unable to prepare payment submission."
        );

    }


    $stmt->bind_param(
        "sii",
        $gateway_transaction_id,
        $payment_id,
        $owner_id
    );


    if (!$stmt->execute()) {

        $stmt->close();

        throw new Exception(
            "Unable to submit the payment."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | CONFIRM UPDATE
    |--------------------------------------------------------------------------
    */

    if (
        $stmt->affected_rows !== 1
    ) {

        $stmt->close();

        throw new Exception(
            "The payment could not be submitted. It may have already been processed."
        );

    }


    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $conn->commit();

    $transaction_started = false;


    /*
    |--------------------------------------------------------------------------
    | SUCCESS MESSAGE
    |--------------------------------------------------------------------------
    */

    $_SESSION["payment_success"] =
        "Your payment has been submitted successfully. " .
        "The administrator will verify your JazzCash/Raast transaction. " .
        "Your subscription will only be activated or scheduled after the payment is verified.";


    /*
    |--------------------------------------------------------------------------
    | REDIRECT
    |--------------------------------------------------------------------------
    */

    redirectToSubscription();


}
catch (Exception $e) {

    /*
    |--------------------------------------------------------------------------
    | ROLLBACK
    |--------------------------------------------------------------------------
    */

    if ($transaction_started) {

        $conn->rollback();

    }


    /*
    |--------------------------------------------------------------------------
    | SAFE ERROR MESSAGE
    |--------------------------------------------------------------------------
    |
    | We intentionally show the application-level error generated above.
    | We do not expose raw SQL/database errors to the user.
    |
    |--------------------------------------------------------------------------
    */

    $_SESSION["payment_error"] =
        $e->getMessage();


    /*
    |--------------------------------------------------------------------------
    | RETURN TO CHECKOUT
    |--------------------------------------------------------------------------
    |
    | If we successfully loaded the payment before the error occurred,
    | return to its checkout page.
    |
    |--------------------------------------------------------------------------
    */

    if (
        isset($plan_id) &&
        (int) $plan_id > 0
    ) {

        redirectToCheckout(
            (int) $plan_id
        );

    }


    redirectToSubscription();

}

?>