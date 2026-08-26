<?php

session_start();

require_once "backend/db.php";


/*
|--------------------------------------------------------------------------
| OWNER LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["owner_id"])) {

    header("Location: login.php");
    exit();

}


if (!isset($_GET["id"])) {

    header("Location: members.php");
    exit();

}


$member_id =
    (int) $_GET["id"];


$owner_id =
    (int) $_SESSION["owner_id"];


/*
|--------------------------------------------------------------------------
| GET MEMBER
|--------------------------------------------------------------------------
|
| Verify that the member belongs to the logged-in
| owner's gym.
|
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        m.member_id,
        m.member_number,
        m.name,
        m.phone,
        m.email,
        m.joining_date,
        m.status

    FROM members m

    INNER JOIN gyms g
        ON m.gym_id = g.gym_id

    WHERE m.member_id = ?

    AND g.owner_id = ?

    LIMIT 1
";


$stmt =
    $conn->prepare($sql);


if (!$stmt) {

    die(
        "Database error: " .
        htmlspecialchars(
            $conn->error
        )
    );

}


$stmt->bind_param(
    "ii",
    $member_id,
    $owner_id
);


if (!$stmt->execute()) {

    $stmt->close();

    die(
        "Unable to load member."
    );

}


$result =
    $stmt->get_result();


$member =
    $result->fetch_assoc();


$stmt->close();


if (!$member) {

    die(
        "Member not found."
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
        Edit Member
    </title>

    <link
        rel="stylesheet"
        href="css/edit_member.css"
    >

</head>


<body>


<div class="container">


    <div class="card">


        <h1>
            ✏️ Edit Member
        </h1>


        <!--
        |--------------------------------------------------------------------------
        | MEMBER NUMBER
        |--------------------------------------------------------------------------
        |
        | This is the gym-specific member number.
        |
        | It is displayed but cannot be edited.
        |
        |--------------------------------------------------------------------------
        -->

        <div
            class="member-number"
            style="
                margin-bottom:20px;
                padding:12px 15px;
                background:#f8fafc;
                border:1px solid #e2e8f0;
                border-radius:8px;
            "
        >

            <strong>
                Member #
            </strong>

            <?php

            echo htmlspecialchars(
                $member["member_number"]
            );

            ?>

        </div>


        <!-- CURRENT STATUS -->

        <div class="status">

            <strong>
                Member Status:
            </strong>


            <?php if (
                $member["status"] === "active"
            ): ?>

                <span class="active">
                    🟢 Active
                </span>

            <?php else: ?>

                <span class="inactive">
                    🔴 Inactive
                </span>

            <?php endif; ?>

        </div>


        <form
            action="backend/update_member.php"
            method="POST"
        >


            <!--
            |--------------------------------------------------------------------------
            | INTERNAL MEMBER ID
            |--------------------------------------------------------------------------
            |
            | This is required by the backend to identify
            | the database record.
            |
            | It is NOT the member number shown to the owner.
            |
            |--------------------------------------------------------------------------
            -->

            <input
                type="hidden"
                name="member_id"
                value="<?php
                    echo htmlspecialchars(
                        $member["member_id"]
                    );
                ?>"
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
                    value="<?php
                        echo htmlspecialchars(
                            $member["name"]
                        );
                    ?>"
                    maxlength="100"
                    required
                >

            </div>


            <!-- PHONE -->

            <div class="form-group">

                <label for="phone">

                    Phone

                </label>


                <input
                    type="text"
                    id="phone"
                    name="phone"
                    value="<?php
                        echo htmlspecialchars(
                            $member["phone"] ?? ""
                        );
                    ?>"
                    maxlength="20"
                >

            </div>


            <!-- EMAIL -->

            <div class="form-group">

                <label for="email">

                    Email

                </label>


                <input
                    type="email"
                    id="email"
                    name="email"
                    value="<?php
                        echo htmlspecialchars(
                            $member["email"] ?? ""
                        );
                    ?>"
                    maxlength="150"
                >

                <span class="hint">

                    Optional.

                </span>

            </div>


            <!-- JOINING DATE -->

            <div class="form-group">

                <label for="joining_date">

                    Joining Date

                </label>


                <input
                    type="date"
                    id="joining_date"
                    name="joining_date"
                    value="<?php
                        echo htmlspecialchars(
                            $member["joining_date"] ?? ""
                        );
                    ?>"
                >

                <span class="hint">

                    Optional. Enter the original joining
                    date if it is known.

                </span>

            </div>


            <!-- SAVE -->

            <button type="submit">

                💾 Save Changes

            </button>


        </form>


        <div class="links">


            <a href="members.php">

                ← Back to Members

            </a>


            <a href="member_details.php?id=<?php
                echo $member["member_id"];
            ?>">

                View Member

            </a>


        </div>


    </div>


</div>


</body>

</html>