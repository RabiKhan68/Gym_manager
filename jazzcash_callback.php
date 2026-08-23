<?php

session_start();

require_once "backend/db.php";
require_once "backend/jazzcash_config.php";


/*
|--------------------------------------------------------------------------
| JAZZCASH CALLBACK
|--------------------------------------------------------------------------
|
| JazzCash sends the payment result to this page using HTTP POST.
|
| This file:
|
| 1. Receives JazzCash response
| 2. Validates the secure hash
| 3. Finds our pending payment
| 4. Verifies the amount
| 5. Prevents duplicate processing
| 6. Marks payment as paid/failed
| 7. Creates the subscription after successful payment
|
|--------------------------------------------------------------------------
*/


/*
|--------------------------------------------------------------------------
| RESPONSE TYPE
|--------------------------------------------------------------------------
*/

header(
    "Content-Type: text/html; charset=UTF-8"
);


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
| FORMAT DATE
|--------------------------------------------------------------------------
*/

function formatDate($date)
{
    if (!$date) {

        return "-";

    }

    $timestamp =
        strtotime($date);

    if (!$timestamp) {

        return "-";

    }

    return date(
        "d M Y",
        $timestamp
    );
}


/*
|--------------------------------------------------------------------------
| CALCULATE MONTHLY END DATE
|--------------------------------------------------------------------------
|
| Example:
|
| 23 Aug -> 22 Sep
| 23 Sep -> 22 Oct
|
|--------------------------------------------------------------------------
*/

function calculateMonthlyEndDate($start_date)
{
    try {

        $start =
            new DateTime(
                $start_date
            );


        $original_day =
            (int)
            $start->format("d");


        /*
        |----------------------------------------------------------------------
        | Move to first day of next month.
        |----------------------------------------------------------------------
        */

        $next_month =
            new DateTime(
                $start->format("Y-m-01")
            );


        $next_month->modify(
            "+1 month"
        );


        /*
        |----------------------------------------------------------------------
        | Number of days in target month.
        |----------------------------------------------------------------------
        */

        $days_in_target_month =
            (int)
            $next_month->format("t");


        /*
        |----------------------------------------------------------------------
        | Preserve original starting day where possible.
        |----------------------------------------------------------------------
        */

        $target_day =
            min(
                $original_day,
                $days_in_target_month
            );


        $target_date =
            new DateTime(
                sprintf(
                    "%04d-%02d-%02d",
                    (int)
                    $next_month->format("Y"),

                    (int)
                    $next_month->format("m"),

                    $target_day
                )
            );


        /*
        |----------------------------------------------------------------------
        | End date = one day before next billing date.
        |----------------------------------------------------------------------
        */

        $target_date->modify(
            "-1 day"
        );


        return $target_date->format(
            "Y-m-d"
        );

    }
    catch (Exception $e) {

        return null;

    }
}


/*
|--------------------------------------------------------------------------
| GET JAZZCASH RESPONSE
|--------------------------------------------------------------------------
*/

$response = $_POST;


/*
|--------------------------------------------------------------------------
| Make sure JazzCash actually sent something.
|--------------------------------------------------------------------------
*/

if (
    empty($response)
) {

    http_response_code(400);

    echo "
        <h2>Invalid Payment Response</h2>
        <p>No payment response was received.</p>
    ";

    exit();

}


/*
|--------------------------------------------------------------------------
| REQUIRED FIELDS
|--------------------------------------------------------------------------
*/

$transaction_reference =
    trim(
        $_POST["pp_TxnRefNo"] ?? ""
    );


$response_code =
    trim(
        $_POST["pp_ResponseCode"] ?? ""
    );


$response_message =
    trim(
        $_POST["pp_ResponseMessage"] ?? ""
    );


$received_hash =
    trim(
        $_POST["pp_SecureHash"] ?? ""
    );


$received_amount =
    trim(
        $_POST["pp_Amount"] ?? ""
    );


$gateway_transaction_id =
    trim(
        $_POST[
            "pp_RetreivalReferenceNo"
        ] ?? ""
    );


$auth_code =
    trim(
        $_POST[
            "pp_AuthCode"
        ] ?? ""
    );


/*
|--------------------------------------------------------------------------
| Validate basic fields.
|--------------------------------------------------------------------------
*/

if (
    $transaction_reference === ""
) {

    http_response_code(400);

    echo "
        <h2>Invalid Payment Response</h2>
        <p>Transaction reference is missing.</p>
    ";

    exit();

}


if (
    $received_hash === ""
) {

    http_response_code(400);

    echo "
        <h2>Invalid Payment Response</h2>
        <p>Secure hash is missing.</p>
    ";

    exit();

}


/*
|--------------------------------------------------------------------------
| VERIFY JAZZCASH SECURE HASH
|--------------------------------------------------------------------------
|
| JazzCash documentation:
|
| - Take the PP fields
| - Exclude pp_SecureHash itself
| - Sort fields alphabetically
| - Concatenate their values with &
| - Prepend Integrity Salt
| - Generate HMAC-SHA256
|
|--------------------------------------------------------------------------
*/

$hash_data = [];


foreach (
    $_POST as $key => $value
) {

    /*
    |--------------------------------------------------------------------------
    | Only JazzCash PP fields participate.
    |--------------------------------------------------------------------------
    */

    if (
        strpos(
            $key,
            "pp_"
        ) === 0
    ) {

        /*
        |--------------------------------------------------------------------------
        | Do not include the received hash itself.
        |--------------------------------------------------------------------------
        */

        if (
            $key === "pp_SecureHash"
        ) {

            continue;

        }


        $hash_data[$key] =
            (string)
            $value;

    }

}


/*
|--------------------------------------------------------------------------
| Sort fields alphabetically.
|--------------------------------------------------------------------------
*/

ksort(
    $hash_data,
    SORT_STRING
);


/*
|--------------------------------------------------------------------------
| Build hash message.
|--------------------------------------------------------------------------
*/

$hash_values = [];


foreach (
    $hash_data as $value
) {

    $hash_values[] =
        $value;

}


$hash_string =
    JAZZCASH_INTEGRITY_SALT .
    "&" .
    implode(
        "&",
        $hash_values
    );


/*
|--------------------------------------------------------------------------
| Calculate expected hash.
|--------------------------------------------------------------------------
*/

$expected_hash =
    hash_hmac(
        "sha256",
        $hash_string,
        JAZZCASH_INTEGRITY_SALT
    );


/*
|--------------------------------------------------------------------------
| Secure comparison.
|--------------------------------------------------------------------------
*/

if (
    !hash_equals(
        strtolower($expected_hash),
        strtolower($received_hash)
    )
) {

    http_response_code(400);

    echo "
        <h2>Payment Verification Failed</h2>

        <p>
            The payment response could not be
            cryptographically verified.
        </p>

        <p>
            No subscription has been activated.
        </p>
    ";

    exit();

}


/*
|--------------------------------------------------------------------------
| DATABASE CHECK
|--------------------------------------------------------------------------
*/

if (
    !isset($conn) ||
    !$conn
) {

    http_response_code(500);

    echo "
        <h2>Payment Processing Error</h2>
        <p>Database connection is unavailable.</p>
    ";

    exit();

}


/*
|--------------------------------------------------------------------------
| FIND PAYMENT
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT

        payment_id,
        owner_id,
        subscription_plan_id,
        subscription_id,
        amount,
        payment_status,
        transaction_reference,
        gateway_transaction_id

    FROM owner_subscription_payments

    WHERE transaction_reference = ?

    LIMIT 1

    FOR UPDATE
";


/*
|--------------------------------------------------------------------------
| Start transaction.
|--------------------------------------------------------------------------
*/

$conn->begin_transaction();


try {

    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        throw new Exception(
            "Unable to find payment transaction."
        );

    }


    $stmt->bind_param(
        "s",
        $transaction_reference
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    $payment =
        $result->fetch_assoc();


    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Payment must exist.
    |--------------------------------------------------------------------------
    */

    if (!$payment) {

        throw new Exception(
            "Payment transaction was not found."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Prevent duplicate successful processing.
    |--------------------------------------------------------------------------
    */

    if (
        $payment["payment_status"] === "paid"
    ) {

        $conn->commit();


        echo "
            <h2>Payment Already Processed</h2>

            <p>
                This payment has already been processed.
            </p>

            <p>
                Transaction:
                <strong>" .
                e(
                    $transaction_reference
                ) .
                "</strong>
            </p>

            <p>
                <a href=\"my_subscription.php\">
                    Return to My Subscription
                </a>
            </p>
        ";

        exit();

    }


    /*
    |--------------------------------------------------------------------------
    | Verify JazzCash amount.
    |--------------------------------------------------------------------------
    |
    | Database amount is stored in rupees.
    | JazzCash sends amount in paisa.
    |
    |--------------------------------------------------------------------------
    */

    $expected_gateway_amount =
        (string)
        round(
            (float)
            $payment["amount"] * 100
        );


    if (
        $received_amount !==
        $expected_gateway_amount
    ) {

        /*
        |--------------------------------------------------------------------------
        | Mark suspicious transaction as failed.
        |--------------------------------------------------------------------------
        */

        $sql = "
            UPDATE owner_subscription_payments

            SET
                payment_status = 'failed',
                gateway_transaction_id = ?,
                paid_at = NULL

            WHERE payment_id = ?

            AND payment_status = 'pending'
        ";


        $stmt =
            $conn->prepare($sql);


        if (!$stmt) {

            throw new Exception(
                "Unable to update payment status."
            );

        }


        $payment_id =
            (int)
            $payment["payment_id"];


        $stmt->bind_param(
            "si",
            $gateway_transaction_id,
            $payment_id
        );


        $stmt->execute();

        $stmt->close();


        $conn->commit();


        echo "
            <h2>Payment Verification Failed</h2>

            <p>
                The payment amount does not match
                the subscription amount.
            </p>

            <p>
                No subscription was activated.
            </p>

            <p>
                <a href=\"my_subscription.php\">
                    Return to My Subscription
                </a>
            </p>
        ";

        exit();

    }


    /*
    |--------------------------------------------------------------------------
    | PAYMENT FAILED
    |--------------------------------------------------------------------------
    */

    if (
        $response_code !== "000"
    ) {

        $sql = "
            UPDATE owner_subscription_payments

            SET

                payment_status = 'failed',

                gateway_transaction_id = ?

            WHERE payment_id = ?

            AND payment_status = 'pending'
        ";


        $stmt =
            $conn->prepare($sql);


        if (!$stmt) {

            throw new Exception(
                "Unable to update failed payment."
            );

        }


        $payment_id =
            (int)
            $payment["payment_id"];


        $stmt->bind_param(
            "si",
            $gateway_transaction_id,
            $payment_id
        );


        $stmt->execute();

        $stmt->close();


        $conn->commit();


        echo "

            <h2>Payment Failed</h2>

            <p>
                JazzCash could not complete your payment.
            </p>

            <p>

                <strong>
                    Response:
                </strong>

                " .
                e(
                    $response_message
                ) .
                "

            </p>

            <p>

                Transaction:

                <strong>" .
                e(
                    $transaction_reference
                ) .
                "</strong>

            </p>

            <p>

                <a href=\"my_subscription.php\">
                    Return to My Subscription
                </a>

            </p>

        ";

        exit();

    }


    /*
    |--------------------------------------------------------------------------
    | SUCCESSFUL PAYMENT
    |--------------------------------------------------------------------------
    */

    /*
    |--------------------------------------------------------------------------
    | Lock current active subscription.
    |--------------------------------------------------------------------------
    */

    $owner_id =
        (int)
        $payment["owner_id"];


    $plan_id =
        (int)
        $payment[
            "subscription_plan_id"
        ];


    $sql = "

        SELECT

            subscription_id,
            subscription_plan_id,
            start_date,
            end_date,
            status

        FROM gym_owner_subscriptions

        WHERE owner_id = ?

        AND status = 'active'

        AND start_date <= CURDATE()

        AND end_date >= CURDATE()

        ORDER BY end_date DESC,
                 subscription_id DESC

        LIMIT 1

        FOR UPDATE

    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        throw new Exception(
            "Unable to verify current subscription."
        );

    }


    $stmt->bind_param(
        "i",
        $owner_id
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    $current_subscription =
        $result->fetch_assoc();


    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Check existing scheduled subscription.
    |--------------------------------------------------------------------------
    */

    $sql = "

        SELECT

            subscription_id,
            subscription_plan_id,
            start_date,
            end_date,
            status

        FROM gym_owner_subscriptions

        WHERE owner_id = ?

        AND status = 'scheduled'

        AND start_date > CURDATE()

        ORDER BY start_date ASC,
                 subscription_id ASC

        LIMIT 1

        FOR UPDATE

    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        throw new Exception(
            "Unable to check scheduled subscription."
        );

    }


    $stmt->bind_param(
        "i",
        $owner_id
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    $scheduled_subscription =
        $result->fetch_assoc();


    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | Do not create duplicate future subscription.
    |--------------------------------------------------------------------------
    */

    if (
        $scheduled_subscription
    ) {

        throw new Exception(
            "This owner already has a subscription scheduled."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Get selected plan.
    |--------------------------------------------------------------------------
    */

    $sql = "

        SELECT

            subscription_plan_id,
            plan_name,
            price,
            member_limit

        FROM subscription_plans

        WHERE subscription_plan_id = ?

        LIMIT 1

        FOR UPDATE

    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        throw new Exception(
            "Unable to verify subscription plan."
        );

    }


    $stmt->bind_param(
        "i",
        $plan_id
    );


    $stmt->execute();


    $result =
        $stmt->get_result();


    $plan =
        $result->fetch_assoc();


    $stmt->close();


    if (!$plan) {

        throw new Exception(
            "The subscription plan no longer exists."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | Verify database amount against current plan price.
    |--------------------------------------------------------------------------
    */

    if (
        (float)
        $payment["amount"]
        !=
        (float)
        $plan["price"]
    ) {

        throw new Exception(
            "Payment amount does not match the selected plan."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | MEMBER LIMIT CHECK
    |--------------------------------------------------------------------------
    */

    if (
        $plan["member_limit"] !== null
    ) {

        $member_limit =
            (int)
            $plan["member_limit"];


        $sql = "

            SELECT COUNT(*) AS total

            FROM members m

            INNER JOIN gyms g
                ON m.gym_id = g.gym_id

            WHERE g.owner_id = ?

        ";


        $stmt =
            $conn->prepare($sql);


        if (!$stmt) {

            throw new Exception(
                "Unable to verify member count."
            );

        }


        $stmt->bind_param(
            "i",
            $owner_id
        );


        $stmt->execute();


        $result =
            $stmt->get_result();


        $row =
            $result->fetch_assoc();


        $stmt->close();


        $total_members =
            (int)
            ($row["total"] ?? 0);


        if (
            $total_members >
            $member_limit
        ) {

            throw new Exception(
                "Your gym currently has " .
                $total_members .
                " members, but this plan supports only " .
                $member_limit .
                " members."
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | CREATE SUBSCRIPTION
    |--------------------------------------------------------------------------
    */

    if (
        $current_subscription
    ) {

        /*
        |----------------------------------------------------------------------
        | Existing subscription.
        |
        | New subscription starts the day after current expiry.
        |----------------------------------------------------------------------
        */

        $start_date =
            new DateTime(
                $current_subscription[
                    "end_date"
                ]
            );


        $start_date->modify(
            "+1 day"
        );


        $new_start_date =
            $start_date->format(
                "Y-m-d"
            );


        $new_end_date =
            calculateMonthlyEndDate(
                $new_start_date
            );


        if (
            !$new_end_date
        ) {

            throw new Exception(
                "Unable to calculate subscription end date."
            );

        }


        /*
        |----------------------------------------------------------------------
        | Scheduled subscription.
        |----------------------------------------------------------------------
        */

        $subscription_status =
            "scheduled";

    }
    else {

        /*
        |----------------------------------------------------------------------
        | First subscription.
        |----------------------------------------------------------------------
        */

        $new_start_date =
            date(
                "Y-m-d"
            );


        $new_end_date =
            calculateMonthlyEndDate(
                $new_start_date
            );


        if (
            !$new_end_date
        ) {

            throw new Exception(
                "Unable to calculate subscription end date."
            );

        }


        $subscription_status =
            "active";

    }


    /*
    |--------------------------------------------------------------------------
    | INSERT SUBSCRIPTION
    |--------------------------------------------------------------------------
    */

    $sql = "

        INSERT INTO gym_owner_subscriptions
        (
            owner_id,
            subscription_plan_id,
            start_date,
            end_date,
            status,
            created_at
        )

        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            ?,
            NOW()
        )

    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        throw new Exception(
            "Unable to create subscription."
        );

    }


    $stmt->bind_param(
        "iisss",
        $owner_id,
        $plan_id,
        $new_start_date,
        $new_end_date,
        $subscription_status
    );


    if (
        !$stmt->execute()
    ) {

        $stmt->close();

        throw new Exception(
            "Unable to create subscription."
        );

    }


    $subscription_id =
        $stmt->insert_id;


    $stmt->close();


    /*
    |--------------------------------------------------------------------------
    | UPDATE PAYMENT
    |--------------------------------------------------------------------------
    */

    $sql = "

        UPDATE owner_subscription_payments

        SET

            subscription_id = ?,

            payment_status = 'paid',

            gateway_transaction_id = ?,

            paid_at = NOW()

        WHERE payment_id = ?

        AND payment_status = 'pending'

    ";


    $stmt =
        $conn->prepare($sql);


    if (!$stmt) {

        throw new Exception(
            "Unable to update payment record."
        );

    }


    $payment_id =
        (int)
        $payment["payment_id"];


    $stmt->bind_param(
        "isi",
        $subscription_id,
        $gateway_transaction_id,
        $payment_id
    );


    if (
        !$stmt->execute()
    ) {

        $stmt->close();

        throw new Exception(
            "Unable to mark payment as paid."
        );

    }


    $updated =
        $stmt->affected_rows;


    $stmt->close();


    if (
        $updated !== 1
    ) {

        throw new Exception(
            "Payment was already processed or could not be updated."
        );

    }


    /*
    |--------------------------------------------------------------------------
    | COMMIT EVERYTHING
    |--------------------------------------------------------------------------
    */

    $conn->commit();


    /*
    |--------------------------------------------------------------------------
    | SUCCESS MESSAGE
    |--------------------------------------------------------------------------
    */

    if (
        $subscription_status ===
        "active"
    ) {

        $message =
            "Your " .
            $plan["plan_name"] .
            " subscription is now active.";

    }
    else {

        $message =
            "Your " .
            $plan["plan_name"] .
            " subscription has been scheduled. " .

            "Your current subscription remains active until " .

            formatDate(
                $current_subscription[
                    "end_date"
                ]
            ) .

            ". The new plan will start on " .

            formatDate(
                $new_start_date
            ) .

            ".";

    }


    /*
    |--------------------------------------------------------------------------
    | Save success message.
    |--------------------------------------------------------------------------
    */

    $_SESSION[
        "subscription_payment_success"
    ] =
        $message;


    /*
    |--------------------------------------------------------------------------
    | Redirect.
    |--------------------------------------------------------------------------
    */

    header(
        "Location: my_subscription.php"
    );

    exit();

}
catch (Exception $e) {

    /*
    |--------------------------------------------------------------------------
    | ROLLBACK
    |--------------------------------------------------------------------------
    */

    $conn->rollback();


    /*
    |--------------------------------------------------------------------------
    | Record failure for pending payment where possible.
    |--------------------------------------------------------------------------
    */

    /*
    | We deliberately don't automatically change every exception
    | to "failed", because some errors are internal database errors.
    |
    | The transaction remains pending and can be investigated safely.
    */


    http_response_code(500);


    echo "

        <div style=\"
            max-width:700px;
            margin:60px auto;
            padding:30px;
            font-family:Arial,Helvetica,sans-serif;
            background:#ffffff;
            border-radius:12px;
            box-shadow:0 4px 20px rgba(0,0,0,.08);
        \">

            <h2>
                Payment Processing Error
            </h2>

            <p>
                Your JazzCash payment response was received,
                but we could not complete the subscription processing.
            </p>

            <p>
                <strong>
                    Transaction:
                </strong>

                " .
                e(
                    $transaction_reference
                ) .
                "
            </p>

            <p>
                Please contact the administrator if the amount
                was deducted from your JazzCash account.
            </p>

            <p>

                <a href=\"my_subscription.php\">
                    Return to My Subscription
                </a>

            </p>

        </div>

    ";

    exit();

}