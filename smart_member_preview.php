<?php

session_start();

if (!isset($_SESSION["owner_id"])) {

    header("Location: login.php");

    exit();

}


$members =
    $_SESSION["ocr_members"] ?? [];


if (count($members) === 0) {

    die(
        "No members were detected. " .
        "Please upload a clearer image."
    );

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

        .review-card {

            background: white;

            border:
                1px solid #e5e7eb;

            border-radius: 14px;

            padding: 25px;

            box-shadow:
                0 4px 18px
                rgba(15,23,42,.05);

        }


        .review-header {

            display: flex;

            justify-content: space-between;

            align-items: center;

            gap: 20px;

            margin-bottom: 20px;

        }


        .review-header h1 {

            margin: 0;

            font-size: 26px;

        }


        .count {

            background: #eff6ff;

            color: #1d4ed8;

            padding: 8px 12px;

            border-radius: 20px;

            font-size: 13px;

            font-weight: bold;

        }


        .table-wrapper {

            width: 100%;

            overflow-x: auto;

        }


        table {

            width: 100%;

            border-collapse: collapse;

            min-width: 650px;

        }


        th,
        td {

            padding: 12px;

            border-bottom:
                1px solid #e5e7eb;

            text-align: left;

        }


        th {

            background: #f8fafc;

            color: #64748b;

            font-size: 12px;

            text-transform: uppercase;

        }


        td input {

            width: 100%;

            padding: 10px;

            border:
                1px solid #d1d5db;

            border-radius: 7px;

            font-size: 14px;

        }


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

            padding: 12px 18px;

            border: none;

            border-radius: 8px;

            font-size: 14px;

            font-weight: bold;

            text-decoration: none;

            cursor: pointer;

        }


        .primary {

            background: #2563eb;

            color: white;

        }


        .gray {

            background: #e5e7eb;

            color: #374151;

        }


        @media (max-width: 700px) {

            .review-card {

                padding: 18px;

            }


            .review-header {

                align-items: flex-start;

                flex-direction: column;

            }


            .actions {

                flex-direction: column;

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


        <div class="review-warning">

            <strong>
                Please check every record.
            </strong>

            Tesseract can make mistakes when
            reading handwriting, especially phone
            numbers. Correct anything that is wrong
            before importing.

        </div>


        <form
            action="backend/ocr_import.php"
            method="POST"
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
                        $members as $index => $member
                    ): ?>


                        <tr>


                            <td>

                                <?php
                                echo $index + 1;
                                ?>

                            </td>


                            <td>

                                <input
                                    type="text"
                                    name="members[<?php echo $index; ?>][name]"
                                    value="<?php
                                        echo htmlspecialchars(
                                            $member["name"]
                                        );
                                    ?>"
                                    required
                                >

                            </td>


                            <td>

                                <input
                                    type="text"
                                    name="members[<?php echo $index; ?>][phone]"
                                    value="<?php
                                        echo htmlspecialchars(
                                            $member["phone"]
                                        );
                                    ?>"
                                    placeholder="03XXXXXXXXX"
                                >

                            </td>


                            <td>

                                <input
                                    type="date"
                                    name="members[<?php echo $index; ?>][joining_date]"
                                    value="<?php
                                        echo htmlspecialchars(
                                            $member["joining_date"]
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



            <div class="actions">


                <button
                    type="submit"
                    class="button primary"
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


</body>

</html>