<?php

session_start();


/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["owner_id"])) {

    header("Location: login.php");

    exit();

}


/*
|--------------------------------------------------------------------------
| GET OCR MEMBERS
|--------------------------------------------------------------------------
*/

$members =
    $_SESSION["ocr_members"] ?? [];


/*
|--------------------------------------------------------------------------
| NO MEMBERS
|--------------------------------------------------------------------------
*/

if (
    !is_array($members) ||
    count($members) === 0
) {

    die(
        "No members were detected. " .
        "Please upload a clearer image."
    );

}


/*
|--------------------------------------------------------------------------
| COUNT STATUS
|--------------------------------------------------------------------------
*/

$good_count = 0;
$review_count = 0;


foreach ($members as $member) {

    if (
        ($member["status"] ?? "review")
        ===
        "good"
    ) {

        $good_count++;

    } else {

        $review_count++;

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
        Review OCR Members
    </title>


    <link
        rel="stylesheet"
        href="css/smart_member_import.css"
    >


    <style>

        /*
        |--------------------------------------------------------------------------
        | REVIEW CARD
        |--------------------------------------------------------------------------
        */

        .review-card {

            background: white;

            border:
                1px solid #e5e7eb;

            border-radius: 14px;

            padding: 25px;

            box-shadow:
                0 4px 18px
                rgba(15, 23, 42, .05);

        }


        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        .review-header {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            gap: 20px;

            margin-bottom: 20px;

        }


        .review-header h1 {

            margin: 0;

            font-size: 26px;

            color: #111827;

        }


        /*
        |--------------------------------------------------------------------------
        | COUNT
        |--------------------------------------------------------------------------
        */

        .count {

            background: #eff6ff;

            color: #1d4ed8;

            padding: 8px 12px;

            border-radius: 20px;

            font-size: 13px;

            font-weight: bold;

            white-space: nowrap;

        }


        /*
        |--------------------------------------------------------------------------
        | STATUS SUMMARY
        |--------------------------------------------------------------------------
        */

        .status-summary {

            display: flex;

            gap: 10px;

            flex-wrap: wrap;

            margin-bottom: 20px;

        }


        .status-box {

            padding:
                9px 13px;

            border-radius: 8px;

            font-size: 13px;

            font-weight: bold;

        }


        .status-good {

            background: #dcfce7;

            color: #166534;

        }


        .status-review {

            background: #fef3c7;

            color: #92400e;

        }


        /*
        |--------------------------------------------------------------------------
        | WARNING
        |--------------------------------------------------------------------------
        */

        .review-warning {

            margin-bottom: 20px;

            padding: 15px;

            background: #fff7ed;

            border:
                1px solid #fed7aa;

            color: #9a3412;

            border-radius: 9px;

            line-height: 1.5;

        }


        /*
        |--------------------------------------------------------------------------
        | TABLE
        |--------------------------------------------------------------------------
        */

        .table-wrapper {

            width: 100%;

            overflow-x: auto;

            -webkit-overflow-scrolling:
                touch;

            border:
                1px solid #e5e7eb;

            border-radius: 10px;

        }


        table {

            width: 100%;

            border-collapse:
                collapse;

            min-width: 720px;

        }


        th,
        td {

            padding: 12px;

            border-bottom:
                1px solid #e5e7eb;

            text-align: left;

            vertical-align: top;

        }


        tbody tr:last-child td {

            border-bottom: none;

        }


        tbody tr:hover {

            background: #f8fafc;

        }


        th {

            background: #f8fafc;

            color: #64748b;

            font-size: 12px;

            text-transform:
                uppercase;

            letter-spacing:
                .04em;

            white-space: nowrap;

        }


        td:first-child {

            width: 50px;

            text-align: center;

            font-weight: bold;

            color: #64748b;

        }


        /*
        |--------------------------------------------------------------------------
        | INPUTS
        |--------------------------------------------------------------------------
        */

        td input {

            width: 100%;

            padding: 10px;

            border:
                1px solid #d1d5db;

            border-radius: 7px;

            font-size: 14px;

            background: white;

            color: #111827;

            outline: none;

        }


        td input:focus {

            border-color:
                #2563eb;

            box-shadow:
                0 0 0 3px
                rgba(37, 99, 235, .12);

        }


        /*
        |--------------------------------------------------------------------------
        | INVALID / REVIEW INPUT
        |--------------------------------------------------------------------------
        */

        .input-review {

            border-color:
                #f59e0b !important;

            background:
                #fffbeb !important;

        }


        /*
        |--------------------------------------------------------------------------
        | GOOD INPUT
        |--------------------------------------------------------------------------
        */

        .input-good {

            border-color:
                #86efac !important;

        }


        /*
        |--------------------------------------------------------------------------
        | STATUS MESSAGE
        |--------------------------------------------------------------------------
        */

        .record-status {

            display: block;

            margin-top: 6px;

            font-size: 12px;

            font-weight: 600;

        }


        .record-status.good {

            color: #15803d;

        }


        .record-status.review {

            color: #b45309;

        }


        /*
        |--------------------------------------------------------------------------
        | CONFIDENCE
        |--------------------------------------------------------------------------
        */

        .confidence {

            display: block;

            margin-top: 3px;

            font-size: 11px;

            color: #6b7280;

        }


        /*
        |--------------------------------------------------------------------------
        | ACTIONS
        |--------------------------------------------------------------------------
        */

        .actions {

            display: flex;

            gap: 10px;

            flex-wrap: wrap;

            margin-top: 25px;

        }


        .button {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            padding:
                12px 18px;

            border: none;

            border-radius: 8px;

            font-size: 14px;

            font-weight: bold;

            text-decoration: none;

            cursor: pointer;

            transition:
                .2s ease;

        }


        .button:hover {

            transform:
                translateY(-1px);

        }


        .primary {

            background: #2563eb;

            color: white;

        }


        .primary:hover {

            background: #1d4ed8;

        }


        .gray {

            background: #e5e7eb;

            color: #374151;

        }


        .gray:hover {

            background: #d1d5db;

        }


        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 700px) {

            .review-card {

                padding: 16px;

                border-radius: 10px;

            }


            .review-header {

                align-items:
                    flex-start;

                flex-direction:
                    column;

                gap: 10px;

            }


            .review-header h1 {

                font-size: 22px;

            }


            .count {

                white-space:
                    normal;

            }


            .status-summary {

                flex-direction:
                    column;

            }


            .status-box {

                width: 100%;

            }


            .review-warning {

                font-size: 14px;

                padding: 13px;

            }


            /*
            | Keep table horizontally scrollable.
            */

            .table-wrapper {

                margin-left: 0;

                margin-right: 0;

            }


            table {

                min-width: 680px;

            }


            .actions {

                flex-direction:
                    column;

            }


            .button {

                width: 100%;

            }

        }

    </style>

</head>


<body>


<div class="container">


    <div class="review-card">


        <!-- HEADER -->

        <div class="review-header">


            <h1>
                🔍 Review OCR Results
            </h1>


            <span class="count">

                <?php

                echo count($members);

                ?>

                members detected

            </span>


        </div>



        <!-- STATUS SUMMARY -->

        <div class="status-summary">


            <div class="status-box status-good">

                ✓

                <?php

                echo $good_count;

                ?>

                records look good

            </div>


            <div class="status-box status-review">

                ⚠

                <?php

                echo $review_count;

                ?>

                records need review

            </div>


        </div>



        <!-- WARNING -->

        <div class="review-warning">

            <strong>
                Please check every record.
            </strong>

            <br>

            Tesseract OCR can make mistakes when
            reading handwriting, especially phone
            numbers.

            <br>

            Correct anything that is wrong before
            importing members.

        </div>



        <!-- FORM -->

        <form
            action="backend/ocr_import.php"
            method="POST"
            id="ocrReviewForm"
        >


            <div class="table-wrapper">


                <table>


                    <thead>

                        <tr>

                            <th>
                                #
                            </th>

                            <th>
                                Full Name
                            </th>

                            <th>
                                Phone
                            </th>

                            <th>
                                Joining Date
                            </th>

                        </tr>

                    </thead>


                    <tbody>


                    <?php foreach (
                        $members
                        as $index => $member
                    ): ?>


                        <?php

                        $status =
                            $member["status"]
                            ??
                            "review";


                        $is_good =
                            $status === "good";


                        $name_confidence =
                            $member[
                                "name_confidence"
                            ]
                            ??
                            null;


                        $phone_confidence =
                            $member[
                                "phone_confidence"
                            ]
                            ??
                            null;


                        $name_class =
                            $is_good
                                ? "input-good"
                                : "input-review";


                        $phone_class =
                            (
                                !empty(
                                    $member["phone"]
                                )
                            )
                            ? (
                                $is_good
                                    ? "input-good"
                                    : "input-review"
                            )
                            : "input-review";

                        ?>


                        <tr>


                            <!-- NUMBER -->

                            <td>

                                <?php

                                echo
                                    $index + 1;

                                ?>

                            </td>



                            <!-- NAME -->

                            <td>


                                <input
                                    type="text"
                                    class="<?php
                                        echo $name_class;
                                    ?>"
                                    name="members[<?php
                                        echo $index;
                                    ?>][name]"
                                    value="<?php
                                        echo htmlspecialchars(
                                            $member["name"]
                                            ?? ""
                                        );
                                    ?>"
                                    required
                                    autocomplete="off"
                                >


                                <?php if ($is_good): ?>

                                    <span
                                        class="record-status good"
                                    >
                                        ✓ Looks good
                                    </span>

                                <?php else: ?>

                                    <span
                                        class="record-status review"
                                    >
                                        ⚠ Please verify
                                    </span>

                                <?php endif; ?>


                                <?php if (
                                    $name_confidence !== null
                                ): ?>

                                    <span
                                        class="confidence"
                                    >

                                        OCR confidence:

                                        <?php
                                        echo htmlspecialchars(
                                            (string)
                                            $name_confidence
                                        );
                                        ?>%

                                    </span>

                                <?php endif; ?>


                            </td>



                            <!-- PHONE -->

                            <td>


                                <input
                                    type="text"
                                    class="<?php
                                        echo $phone_class;
                                    ?>"
                                    name="members[<?php
                                        echo $index;
                                    ?>][phone]"
                                    value="<?php
                                        echo htmlspecialchars(
                                            $member["phone"]
                                            ?? ""
                                        );
                                    ?>"
                                    placeholder="03XXXXXXXXX"
                                    pattern="03[0-9]{9}"
                                    title="Enter an 11-digit Pakistani mobile number starting with 03"
                                    inputmode="numeric"
                                    autocomplete="off"
                                >


                                <?php if (
                                    !empty(
                                        $member["phone"]
                                    )
                                ): ?>


                                    <?php if (
                                        $is_good
                                    ): ?>

                                        <span
                                            class="record-status good"
                                        >
                                            ✓ Looks good
                                        </span>

                                    <?php else: ?>

                                        <span
                                            class="record-status review"
                                        >
                                            ⚠ Please verify
                                        </span>

                                    <?php endif; ?>


                                <?php else: ?>

                                    <span
                                        class="record-status review"
                                    >
                                        ⚠ Phone not detected
                                    </span>

                                <?php endif; ?>


                                <?php if (
                                    $phone_confidence !== null
                                ): ?>

                                    <span
                                        class="confidence"
                                    >

                                        OCR confidence:

                                        <?php
                                        echo htmlspecialchars(
                                            (string)
                                            $phone_confidence
                                        );
                                        ?>%

                                    </span>

                                <?php endif; ?>


                            </td>



                            <!-- JOINING DATE -->

                            <td>


                                <input
                                    type="date"
                                    name="members[<?php
                                        echo $index;
                                    ?>][joining_date]"
                                    value="<?php
                                        echo htmlspecialchars(
                                            $member[
                                                "joining_date"
                                            ]
                                            ??
                                            date(
                                                "Y-m-d"
                                            )
                                        );
                                    ?>"
                                    required
                                >


                            </td>


                        </tr>


                    <?php endforeach; ?>


                    </tbody>


                </table>


            </div>



            <!-- ACTIONS -->

            <div class="actions">


                <button
                    type="submit"
                    class="button primary"
                    id="importButton"
                >

                    ✓ Confirm & Import Members

                </button>


                <a
                    href="smart_member_import.php"
                    class="button gray"
                >

                    Cancel

                </a>


            </div>


        </form>


    </div>


</div>



<script>

/*
|--------------------------------------------------------------------------
| PREVENT ACCIDENTAL DOUBLE SUBMISSION
|--------------------------------------------------------------------------
*/

document
    .getElementById("ocrReviewForm")
    .addEventListener(
        "submit",
        function () {

            const button =
                document.getElementById(
                    "importButton"
                );


            button.disabled = true;

            button.style.opacity =
                "0.7";

            button.style.cursor =
                "not-allowed";

            button.innerHTML =
                "⏳ Importing Members...";

        }
    );


/*
|--------------------------------------------------------------------------
| PHONE INPUT
|--------------------------------------------------------------------------
|
| Allow only digits while typing/pasting.
|
|--------------------------------------------------------------------------
*/

document
    .querySelectorAll(
        'input[name*="[phone]"]'
    )
    .forEach(
        function (input) {

            input.addEventListener(
                "input",
                function () {

                    this.value =
                        this.value.replace(
                            /[^0-9]/g,
                            ""
                        );

                }
            );

        }
    );

</script>


</body>

</html>