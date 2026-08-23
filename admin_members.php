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
| Search
|--------------------------------------------------------------------------
*/

$search = trim($_GET["search"] ?? "");


/*
|--------------------------------------------------------------------------
| Get all members
|--------------------------------------------------------------------------
*/

$sql = "SELECT
            m.member_id,
            m.name AS member_name,
            m.phone,
            m.email,
            m.status,
            m.created_at,

            g.gym_id,
            g.gym_name,

            mp.plan_name

        FROM members m

        INNER JOIN gyms g
            ON m.gym_id = g.gym_id

        LEFT JOIN member_memberships mm
            ON m.member_id = mm.member_id
            AND mm.status = 'active'

        LEFT JOIN membership_plans mp
            ON mm.plan_id = mp.plan_id";


/*
|--------------------------------------------------------------------------
| Search condition
|--------------------------------------------------------------------------
*/

if ($search !== "") {

    $sql .= " WHERE
                m.name LIKE ?
                OR m.phone LIKE ?
                OR m.email LIKE ?
                OR g.gym_name LIKE ?";

}


$sql .= " ORDER BY m.member_id DESC";


$stmt = $conn->prepare($sql);


if ($search !== "") {

    $search_value = "%" . $search . "%";

    $stmt->bind_param(
        "ssss",
        $search_value,
        $search_value,
        $search_value,
        $search_value
    );

}


$stmt->execute();

$result = $stmt->get_result();

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
        All Members
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

            max-width: 1400px;

            margin: auto;

            padding: 30px;

        }


        /* HEADER */

        .header {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            margin-bottom: 25px;

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

        }


        .back:hover {

            opacity: 0.85;

        }


        /* SEARCH */

        .search-card {

            background: white;

            padding: 20px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

            margin-bottom: 20px;

        }


        .search-form {

            display: flex;

            gap: 10px;

        }


        .search-input {

            flex: 1;

            padding: 12px;

            border: 1px solid #d1d5db;

            border-radius: 8px;

            font-size: 15px;

        }


        .search-button {

            padding: 12px 20px;

            border: none;

            background: #111827;

            color: white;

            border-radius: 8px;

            cursor: pointer;

            font-size: 15px;

        }


        .clear-button {

            display: inline-flex;

            align-items: center;

            padding: 12px 18px;

            background: #e5e7eb;

            color: #111827;

            text-decoration: none;

            border-radius: 8px;

        }


        /* CARD */

        .card {

            background: white;

            padding: 25px;

            border-radius: 12px;

            box-shadow:
                0 3px 12px
                rgba(0,0,0,0.06);

            overflow-x: auto;

        }


        /* TABLE */

        table {

            width: 100%;

            border-collapse:
                collapse;

            min-width: 1100px;

        }


        th,
        td {

            padding: 14px;

            text-align: left;

            border-bottom:
                1px solid #e5e7eb;

        }


        th {

            background: #f8fafc;

            font-weight: bold;

            white-space: nowrap;

        }


        tr:hover {

            background: #f9fafb;

        }


        .member-id {

            font-weight: bold;

        }


        .member-name {

            font-weight: bold;

        }


        .gym-name {

            font-weight: bold;

        }


        .plan {

            color: #374151;

        }


        .status-active {

            color: green;

            font-weight: bold;

        }


        .status-inactive {

            color: red;

            font-weight: bold;

        }


        .date {

            white-space: nowrap;

            color: #6b7280;

        }


        .view-button {

            display: inline-block;

            padding: 8px 12px;

            background: #111827;

            color: white;

            text-decoration: none;

            border-radius: 6px;

            font-size: 13px;

            white-space: nowrap;

        }


        .empty {

            text-align: center;

            padding: 40px;

            color: #6b7280;

        }


        .count {

            margin-bottom: 15px;

            color: #6b7280;

        }


        /* MOBILE */

        @media (max-width: 700px) {

            .container {

                padding: 15px;

            }


            .header {

                flex-direction: column;

                align-items: flex-start;

                gap: 15px;

            }


            .search-form {

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
                All Members
            </h1>

            <p>
                Manage members across all gyms
            </p>

        </div>


        <a
            href="admin_dashboard.php"
            class="back"
        >

            ← Dashboard

        </a>


    </div>



    <!-- SEARCH -->

    <div class="search-card">


        <form
            method="GET"
            class="search-form"
        >


            <input
                type="text"
                name="search"
                class="search-input"
                placeholder="Search by name, phone, email or gym..."
                value="<?php echo htmlspecialchars($search); ?>"
            >


            <button
                type="submit"
                class="search-button"
            >

                Search

            </button>


            <?php if ($search !== ""): ?>

                <a
                    href="admin_members.php"
                    class="clear-button"
                >

                    Clear

                </a>

            <?php endif; ?>


        </form>


    </div>



    <!-- MEMBERS TABLE -->

    <div class="card">


        <div class="count">

            <?php

            echo $result->num_rows;

            ?>

            member(s) found.

        </div>


        <?php if ($result->num_rows > 0): ?>


            <table>


                <thead>

                    <tr>

                        <th>
                            ID
                        </th>

                        <th>
                            Member
                        </th>

                        <th>
                            Phone
                        </th>

                        <th>
                            Email
                        </th>

                        <th>
                            Gym
                        </th>

                        <th>
                            Membership
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            Registered
                        </th>

                        <th>
                            Action
                        </th>

                    </tr>

                </thead>


                <tbody>


                <?php while (
                    $member =
                    $result->fetch_assoc()
                ): ?>


                    <tr>


                        <!-- ID -->

                        <td class="member-id">

                            <?php

                            echo (int)
                                $member["member_id"];

                            ?>

                        </td>



                        <!-- NAME -->

                        <td class="member-name">

                            <?php

                            echo htmlspecialchars(
                                $member["member_name"]
                            );

                            ?>

                        </td>



                        <!-- PHONE -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $member["phone"]
                                ?? "-"
                            );

                            ?>

                        </td>



                        <!-- EMAIL -->

                        <td>

                            <?php

                            echo htmlspecialchars(
                                $member["email"]
                                ?? "-"
                            );

                            ?>

                        </td>



                        <!-- GYM -->

                        <td class="gym-name">

                            <?php

                            echo htmlspecialchars(
                                $member["gym_name"]
                            );

                            ?>

                        </td>



                        <!-- PLAN -->

                        <td class="plan">

                            <?php

                            echo htmlspecialchars(
                                $member["plan_name"]
                                ?? "-"
                            );

                            ?>

                        </td>



                        <!-- STATUS -->

                        <td>

                            <?php

                            if (
                                $member["status"]
                                === "active"
                            ) {

                                echo '<span class="status-active">
                                        Active
                                      </span>';

                            } else {

                                echo '<span class="status-inactive">' .
                                    htmlspecialchars(
                                        $member["status"]
                                    ) .
                                    '</span>';

                            }

                            ?>

                        </td>



                        <!-- REGISTERED -->

                        <td class="date">

                            <?php

                            if (
                                !empty(
                                    $member["created_at"]
                                )
                            ) {

                                echo date(
                                    "d M Y",
                                    strtotime(
                                        $member["created_at"]
                                    )
                                );

                            } else {

                                echo "-";

                            }

                            ?>

                        </td>



                        <!-- ACTION -->

                        <td>

                            <a
                                href="admin_member_details.php?id=<?php echo (int)$member["member_id"]; ?>"
                                class="view-button"
                            >

                                View Details

                            </a>

                        </td>


                    </tr>


                <?php endwhile; ?>


                </tbody>


            </table>


        <?php else: ?>


            <div class="empty">

                <?php if ($search !== ""): ?>

                    No members found for:

                    <strong>
                        <?php
                        echo htmlspecialchars(
                            $search
                        );
                        ?>
                    </strong>

                <?php else: ?>

                    No members found.

                <?php endif; ?>

            </div>


        <?php endif; ?>


    </div>


</div>


</body>

</html>