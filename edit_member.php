<?php

session_start();

require_once "backend/db.php";

if (!isset($_SESSION["owner_id"])) {
    header("Location: login.php");
    exit();
}

if (!isset($_GET["id"])) {
    header("Location: members.php");
    exit();
}

$member_id = (int) $_GET["id"];
$owner_id = $_SESSION["owner_id"];


/*
|--------------------------------------------------------------------------
| Get member
|--------------------------------------------------------------------------
|
| We also verify the member belongs to the
| logged-in owner's gym.
|
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            m.member_id,
            m.name,
            m.phone,
            m.email,
            m.joining_date,
            m.status
        FROM members m

        INNER JOIN gyms g
            ON m.gym_id = g.gym_id

        WHERE m.member_id = ?
        AND g.owner_id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "ii",
    $member_id,
    $owner_id
);

$stmt->execute();

$result = $stmt->get_result();

$member = $result->fetch_assoc();


if (!$member) {
    die("Member not found.");
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
    
    <link rel = "stylesheet" href = "css/edit_member.css">

</head>


<body>


<div class="container">


    <div class="card">


        <h1>
            ✏️ Edit Member
        </h1>


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


            <!-- MEMBER ID -->

            <input
                type="hidden"
                name="member_id"
                value="<?php
                    echo $member["member_id"];
                ?>"
            >



            <!-- NAME -->

            <div class="form-group">

                <label for="name">
                    Full Name
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
                >

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
                            $member["joining_date"]
                        );
                    ?>"
                    required
                >

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