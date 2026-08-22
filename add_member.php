<?php

session_start();


/*
|--------------------------------------------------------------------------
| Check login
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["owner_id"])) {

    header("Location: login.php");
    exit();

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
        Add Member
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

            max-width: 650px;

            margin: 50px auto;

            padding: 20px;

        }


        .card {

            background: white;

            padding: 30px;

            border-radius: 12px;

            box-shadow:
                0 4px 15px
                rgba(0, 0, 0, 0.08);

        }


        h1 {

            margin-top: 0;

            margin-bottom: 8px;

        }


        .subtitle {

            color: #666;

            margin-bottom: 25px;

        }


        .form-group {

            margin-bottom: 20px;

        }


        label {

            display: block;

            margin-bottom: 7px;

            font-weight: bold;

        }


        input {

            width: 100%;

            padding: 12px;

            border: 1px solid #d1d5db;

            border-radius: 7px;

            font-size: 15px;

            outline: none;

        }


        input:focus {

            border-color: #2563eb;

            box-shadow:
                0 0 0 2px
                rgba(37, 99, 235, 0.1);

        }


        .hint {

            display: block;

            margin-top: 5px;

            color: #777;

            font-size: 13px;

        }


        .buttons {

            display: flex;

            gap: 10px;

            margin-top: 25px;

        }


        button {

            border: none;

            padding: 12px 20px;

            border-radius: 7px;

            background: #2563eb;

            color: white;

            font-size: 15px;

            cursor: pointer;

        }


        button:hover {

            background: #1d4ed8;

        }


        .back {

            display: inline-flex;

            align-items: center;

            padding: 12px 20px;

            border-radius: 7px;

            background: #e5e7eb;

            color: #222;

            text-decoration: none;

        }


        .back:hover {

            background: #d1d5db;

        }


        .required {

            color: #dc2626;

        }


        @media (max-width: 600px) {

            .container {

                margin: 20px auto;

            }

            .card {

                padding: 20px;

            }

            .buttons {

                flex-direction: column;

            }

            button,
            .back {

                width: 100%;

                text-align: center;

                justify-content: center;

            }

        }

    </style>

</head>


<body>


<div class="container">


    <div class="card">


        <h1>
            Add New Member
        </h1>


        <p class="subtitle">

            Enter the member's basic information.

            You can assign their membership plan
            after creating the member.

        </p>


        <form
            action="backend/add_member.php"
            method="POST"
        >


            <!-- NAME -->

            <div class="form-group">

                <label for="name">

                    Full Name

                    <span class="required">
                        *
                    </span>

                </label>


                <input
                    type="text"
                    id="name"
                    name="name"
                    placeholder="Enter member's full name"
                    maxlength="100"
                    required
                >

            </div>



            <!-- PHONE -->

            <div class="form-group">

                <label for="phone">

                    Phone Number

                </label>


                <input
                    type="tel"
                    id="phone"
                    name="phone"
                    placeholder="03XXXXXXXXX"
                    maxlength="11"
                >


                <span class="hint">

                    Used for contacting the member
                    and future WhatsApp reminders.

                </span>

            </div>



            <!-- EMAIL -->

            <div class="form-group">

                <label for="email">

                    Email Address

                </label>


                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="member@example.com"
                    maxlength="150"
                >

            </div>



            <!-- JOINING DATE -->

            <div class="form-group">

                <label for="joining_date">

                    Joining Date

                    <span class="required">
                        *
                    </span>

                </label>


                <input
                    type="date"
                    id="joining_date"
                    name="joining_date"
                    value="<?php echo date('Y-m-d'); ?>"
                    required
                >


                <span class="hint">

                    The date the member joined the gym.

                </span>

            </div>



            <!-- BUTTONS -->

            <div class="buttons">


                <button type="submit">

                    + Add Member

                </button>


                <a
                    href="members.php"
                    class="back"
                >

                    Cancel

                </a>


            </div>


        </form>


    </div>


</div>


</body>

</html>