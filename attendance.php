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

// $server_ip = "192.168.0.105";


// $scan_url =
//     "http://" .
//     $server_ip .
//     "/fitness-management/scan.php?token=" .
//     urlencode($token);

/*
|--------------------------------------------------------------------------
| Generate scan URL
|--------------------------------------------------------------------------
|
| Use the current website URL automatically.
|
*/

$protocol = (
    (!empty($_SERVER["HTTPS"]) && $_SERVER["HTTPS"] !== "off")
    || ($_SERVER["SERVER_PORT"] ?? "") == 443
)
    ? "https://"
    : "http://";

$host = $_SERVER["HTTP_HOST"];

$base_path = rtrim(
    dirname($_SERVER["SCRIPT_NAME"]),
    "/\\"
);

$scan_url =
    $protocol .
    $host .
    $base_path .
    "/scan.php?token=" .
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

    <link rel = "stylesheet" href= "css/attendance.css">
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

                <img src = "images/group-users.png" class = "stat-icon" alt = "group">
            Today's Check-ins:

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

const timer = document.getElementById("timer");

const countdownInterval = setInterval(function () {

    seconds--;

    // Never allow the displayed value to go below 0
    if (seconds <= 0) {

        seconds = 0;

        timer.textContent = seconds;

        // Stop the countdown so it cannot go negative
        clearInterval(countdownInterval);

        // Reload the page to generate a completely new QR code
        window.location.reload();

        return;
    }

    timer.textContent = seconds;

}, 1000);



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