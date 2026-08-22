<?php

session_start();

require_once "backend/db.php";
require_once "vendor/autoload.php";

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;


if (!isset($_SESSION["owner_id"])) {

    header("Location: login.php");

    exit();

}


$owner_id = $_SESSION["owner_id"];


/*
|--------------------------------------------------------------------------
| Get gym
|--------------------------------------------------------------------------
*/

$sql = "SELECT gym_id, gym_name
        FROM gyms
        WHERE owner_id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $owner_id
);

$stmt->execute();

$result = $stmt->get_result();

$gym = $result->fetch_assoc();


if (!$gym) {

    die("Gym not found.");

}


$gym_id = $gym["gym_id"];


/*
|--------------------------------------------------------------------------
| Generate secure token
|--------------------------------------------------------------------------
*/

$token = bin2hex(
    random_bytes(32)
);


$expires_at = date(
    "Y-m-d H:i:s",
    time() + 60
);


/*
|--------------------------------------------------------------------------
| Delete old tokens
|--------------------------------------------------------------------------
*/

$sql = "DELETE FROM attendance_qr_tokens
        WHERE gym_id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "i",
    $gym_id
);

$stmt->execute();


/*
|--------------------------------------------------------------------------
| Save token
|--------------------------------------------------------------------------
*/

$sql = "INSERT INTO attendance_qr_tokens
        (gym_id, token, expires_at)

        VALUES (?, ?, ?)";

$stmt = $conn->prepare($sql);

$stmt->bind_param(
    "iss",
    $gym_id,
    $token,
    $expires_at
);

$stmt->execute();


/*
|--------------------------------------------------------------------------
| YOUR COMPUTER'S LOCAL IP
|--------------------------------------------------------------------------
|
| Change this to the IP that worked on your phone.
|
*/

$server_ip = "192.168.0.105";


$scan_url =
    "http://" .
    $server_ip .
    "/fitness-management/scan.php?token=" .
    urlencode($token);


/*
|--------------------------------------------------------------------------
| Generate QR locally
|--------------------------------------------------------------------------
*/

$qr_result = (new Builder(

    writer: new PngWriter(),

    writerOptions: [],

    validateResult: false,

    data: $scan_url,

    encoding: new Encoding("UTF-8"),

    errorCorrectionLevel:
        ErrorCorrectionLevel::High,

    size: 400,

    margin: 10,

    roundBlockSizeMode:
        RoundBlockSizeMode::Margin

))->build();


$qr_data_uri =
    $qr_result->getDataUri();


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
        Attendance Mode
    </title>


    <style>

        * {
            box-sizing: border-box;
        }


        body {

            margin: 0;

            font-family:
                Arial,
                Helvetica,
                sans-serif;

            background: #f4f6f8;

            color: #222;

        }


        .header {

            background: #111827;

            color: white;

            padding: 20px 30px;

            display: flex;

            justify-content:
                space-between;

            align-items: center;

        }


        .gym-name {

            font-size: 26px;

            font-weight: bold;

        }


        .attendance-mode {

            font-size: 14px;

            opacity: 0.8;

        }


        .container {

            max-width: 1200px;

            margin: auto;

            padding: 30px;

        }


        .main-grid {

            display: grid;

            grid-template-columns:
                1fr 1fr;

            gap: 30px;

        }


        .card {

            background: white;

            border-radius: 15px;

            padding: 30px;

            box-shadow:
                0 4px 15px
                rgba(0,0,0,0.08);

        }


        .qr-section {

            text-align: center;

        }


        .qr-section h1 {

            font-size: 30px;

            margin-top: 0;

        }


        .qr-image {

            width: 400px;

            max-width: 100%;

            height: auto;

        }


        .timer {

            margin-top: 15px;

            font-size: 20px;

        }


        #timer {

            font-weight: bold;

        }


        .total {

            margin-top: 25px;

            font-size: 22px;

            font-weight: bold;

        }


        .attendance-list {

            max-height: 500px;

            overflow-y: auto;

        }


        .attendance-row {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            padding: 15px;

            border-bottom:
                1px solid #eee;

        }


        .member-name {

            font-weight: bold;

        }


        .attendance-time {

            color: #666;

        }


        .present {

            color: green;

            font-weight: bold;

        }


        .exit-button {

            display: inline-block;

            margin-top: 30px;

            padding: 12px 25px;

            background: #111827;

            color: white;

            text-decoration: none;

            border-radius: 8px;

        }


        @media (max-width: 800px) {

            .main-grid {

                grid-template-columns: 1fr;

            }

        }

    </style>

</head>


<body>


<header class="header">

    <div class="gym-name">

        <?php

        echo htmlspecialchars(
            $gym["gym_name"]
        );

        ?>

    </div>


    <div class="attendance-mode">

        ATTENDANCE MODE

    </div>

</header>


<div class="container">


    <div class="main-grid">


        <!-- QR CARD -->

        <div class="card qr-section">


            <h1>

                Scan to Mark Attendance

            </h1>


            <p>

                Members can scan this QR
                using their phone.

            </p>


            <img

                class="qr-image"

                src="<?php

                echo $qr_data_uri;

                ?>"

                alt="Attendance QR Code"

            >


            <div class="timer">

                New QR in:

                <span id="timer">

                    60

                </span>

                seconds

            </div>


            <div class="total">

                👥 Today's Check-ins:

                <span id="total-attendance">

                    0

                </span>

            </div>


        </div>



        <!-- ATTENDANCE LIST -->

        <div class="card">


            <h2>

                Today's Check-ins

            </h2>


            <p>

                <?php

                echo date("d F Y");

                ?>

            </p>


            <div
                id="attendance-list"
                class="attendance-list"
            >

                <p>
                    Loading attendance...
                </p>

            </div>


        </div>


    </div>


    <a
        href="dashboard.php"
        class="exit-button"
    >

        ← Exit Attendance Mode

    </a>


</div>



<script>

/*
|--------------------------------------------------------------------------
| QR countdown
|--------------------------------------------------------------------------
*/

let seconds = 60;


const timer =
    document.getElementById("timer");


function countdown() {

    seconds--;

    timer.textContent =
        seconds;


    if (seconds <= 0) {

        window.location.reload();

    }

}


setInterval(
    countdown,
    1000
);



/*
|--------------------------------------------------------------------------
| Load today's attendance
|--------------------------------------------------------------------------
*/

function loadAttendance() {

    fetch(
        "backend/get_today_attendance.php"
    )

    .then(
        response =>
            response.json()
    )

    .then(
        data => {

            if (!data.success) {

                return;

            }


            /*
             * Update total
             */

            document
                .getElementById(
                    "total-attendance"
                )
                .textContent =
                    data.total;


            /*
             * Attendance list
             */

            const list =
                document.getElementById(
                    "attendance-list"
                );


            list.innerHTML = "";


            if (
                data.attendance.length === 0
            ) {

                list.innerHTML =
                    "<p>No check-ins yet.</p>";

                return;

            }


            data.attendance.forEach(
                member => {

                    const row =
                        document.createElement(
                            "div"
                        );


                    row.className =
                        "attendance-row";


                    row.innerHTML = `

                        <div>

                            <div
                                class="member-name"
                            >

                                ${escapeHtml(
                                    member.name
                                )}

                            </div>

                            <div
                                class="attendance-time"
                            >

                                ${member.time}

                            </div>

                        </div>


                        <div
                            class="present"
                        >

                            🟢 Present

                        </div>

                    `;


                    list.appendChild(row);

                }
            );

        }
    )

    .catch(
        error => {

            console.error(
                "Attendance error:",
                error
            );

        }
    );

}


/*
|--------------------------------------------------------------------------
| Prevent HTML injection
|--------------------------------------------------------------------------
*/

function escapeHtml(text) {

    const div =
        document.createElement("div");

    div.textContent = text;

    return div.innerHTML;

}


/*
|--------------------------------------------------------------------------
| Load immediately
|--------------------------------------------------------------------------
*/

loadAttendance();


/*
|--------------------------------------------------------------------------
| Refresh every 5 seconds
|--------------------------------------------------------------------------
*/

setInterval(
    loadAttendance,
    5000
);

</script>


</body>

</html>