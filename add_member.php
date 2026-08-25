<?php

session_start();

require_once "backend/check_subscription.php";


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

    <link rel = "stylesheet" href="css/add_member.css">

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