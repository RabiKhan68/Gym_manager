<?php

session_start();

require_once __DIR__ . "/backend/db.php";
require_once __DIR__ . "/vendor/autoload.php";

use Google\Cloud\Vision\V1\Client\ImageAnnotatorClient;
use Google\Cloud\Vision\V1\AnnotateImageRequest;
use Google\Cloud\Vision\V1\BatchAnnotateImagesRequest;
use Google\Cloud\Vision\V1\Image;
use Google\Cloud\Vision\V1\Feature;
use Google\Cloud\Vision\V1\Feature\Type;

/*
|--------------------------------------------------------------------------
| HELPER
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
| CHECK OWNER LOGIN
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["owner_id"])) {

    header("Location: login.php");
    exit();

}

$owner_id = (int) $_SESSION["owner_id"];


/*
|--------------------------------------------------------------------------
| FIND OWNER'S GYM
|--------------------------------------------------------------------------
*/

$sql = "
    SELECT
        gym_id,
        gym_name
    FROM gyms
    WHERE owner_id = ?
    LIMIT 1
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    die("Database error: " . e($conn->error));
}

$stmt->bind_param(
    "i",
    $owner_id
);

$stmt->execute();

$result = $stmt->get_result();

$gym = $result->fetch_assoc();

$stmt->close();


if (!$gym) {

    die("No gym is associated with your account.");

}


$gym_id = (int) $gym["gym_id"];


/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$error = "";
$success = "";

$ocr_text = "";

$preview_members = [];


/*
|--------------------------------------------------------------------------
| IMPORT MEMBERS
|--------------------------------------------------------------------------
|
| This happens AFTER the owner reviews the OCR results.
|
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["import_members"])
) {


    $names =
        $_POST["member_name"] ?? [];

    $phones =
        $_POST["member_phone"] ?? [];


    if (
        !is_array($names) ||
        !is_array($phones)
    ) {

        $error =
            "Invalid member data.";

    }
    else {


        $imported = 0;

        $skipped = 0;

        $failed = 0;


        /*
        |--------------------------------------------------------------------------
        | Prepare INSERT
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | gym_id comes from the logged-in owner's session.
        |
        | The browser cannot choose another gym_id.
        |
        |--------------------------------------------------------------------------
        */

        $insert_sql = "
            INSERT INTO members
            (
                gym_id,
                name,
                phone,
                status
            )
            VALUES
            (?, ?, ?, 'active')
        ";


        $insert_stmt =
            $conn->prepare(
                $insert_sql
            );


        if (!$insert_stmt) {

            $error =
                "Database error: " .
                $conn->error;

        }
        else {


            /*
            |--------------------------------------------------------------------------
            | Process each row
            |--------------------------------------------------------------------------
            */

            foreach ($names as $index => $name) {


                $name =
                    trim(
                        (string) $name
                    );


                $phone =
                    isset($phones[$index])
                    ? trim(
                        (string)
                        $phones[$index]
                    )
                    : "";


                /*
                |--------------------------------------------------------------------------
                | Skip empty names
                |--------------------------------------------------------------------------
                */

                if ($name === "") {

                    $skipped++;

                    continue;

                }


                /*
                |--------------------------------------------------------------------------
                | Check duplicate phone inside this gym
                |--------------------------------------------------------------------------
                |
                | Only do this when a phone number was provided.
                |
                |--------------------------------------------------------------------------
                */

                if ($phone !== "") {


                    $duplicate_sql = "
                        SELECT member_id
                        FROM members
                        WHERE gym_id = ?
                        AND phone = ?
                        LIMIT 1
                    ";


                    $duplicate_stmt =
                        $conn->prepare(
                            $duplicate_sql
                        );


                    if ($duplicate_stmt) {

                        $duplicate_stmt->bind_param(
                            "is",
                            $gym_id,
                            $phone
                        );

                        $duplicate_stmt->execute();

                        $duplicate_result =
                            $duplicate_stmt->get_result();


                        if (
                            $duplicate_result->num_rows > 0
                        ) {

                            $skipped++;

                            $duplicate_stmt->close();

                            continue;

                        }


                        $duplicate_stmt->close();

                    }

                }


                /*
                |--------------------------------------------------------------------------
                | Insert
                |--------------------------------------------------------------------------
                */

                $insert_stmt->bind_param(
                    "iss",
                    $gym_id,
                    $name,
                    $phone
                );


                if (
                    $insert_stmt->execute()
                ) {

                    $imported++;

                }
                else {

                    $failed++;

                }

            }


            $insert_stmt->close();


            /*
            |--------------------------------------------------------------------------
            | Result message
            |--------------------------------------------------------------------------
            */

            if ($failed > 0) {

                $success =
                    "Imported " .
                    $imported .
                    " member(s). " .
                    $skipped .
                    " skipped. " .
                    $failed .
                    " failed.";

            }
            else {

                $success =
                    "Successfully imported " .
                    $imported .
                    " member(s). " .
                    $skipped .
                    " skipped.";

            }

        }

    }

}


/*
|--------------------------------------------------------------------------
| OCR UPLOAD
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["run_ocr"])
) {


    /*
    |--------------------------------------------------------------------------
    | Check upload
    |--------------------------------------------------------------------------
    */

    if (
        !isset($_FILES["register_image"]) ||
        $_FILES["register_image"]["error"] !== UPLOAD_ERR_OK
    ) {

        $error =
            "Please select a register image.";

    }
    else {


        $file =
            $_FILES["register_image"];


        /*
        |--------------------------------------------------------------------------
        | Maximum file size
        |--------------------------------------------------------------------------
        |
        | 10 MB
        |
        |--------------------------------------------------------------------------
        */

        if (
            $file["size"] >
            10 * 1024 * 1024
        ) {

            $error =
                "The image must be smaller than 10 MB.";

        }
        else {


            /*
            |--------------------------------------------------------------------------
            | Validate MIME type
            |--------------------------------------------------------------------------
            */

            $finfo =
                new finfo(
                    FILEINFO_MIME_TYPE
                );


            $mime =
                $finfo->file(
                    $file["tmp_name"]
                );


            $allowed_types = [
                "image/jpeg",
                "image/png",
                "image/webp"
            ];


            if (
                !in_array(
                    $mime,
                    $allowed_types,
                    true
                )
            ) {

                $error =
                    "Only JPG, PNG, and WEBP images are allowed.";

            }
            else {


                /*
                |--------------------------------------------------------------------------
                | Get Google credentials
                |--------------------------------------------------------------------------
                */

                $credentials_json =
                    getenv(
                        "GOOGLE_APPLICATION_CREDENTIALS_JSON"
                    );


                if (
                    !$credentials_json ||
                    trim($credentials_json) === ""
                ) {

                    $error =
                        "Google OCR credentials are not configured.";

                }
                else {


                    /*
                    |--------------------------------------------------------------------------
                    | Validate credentials JSON
                    |--------------------------------------------------------------------------
                    */

                    $credentials =
                        json_decode(
                            $credentials_json,
                            true
                        );


                    if (
                        !is_array(
                            $credentials
                        )
                    ) {

                        $error =
                            "Google OCR credentials contain invalid JSON.";

                    }
                    else {


                        /*
                        |--------------------------------------------------------------------------
                        | Temporary credentials file
                        |--------------------------------------------------------------------------
                        */

                        $credentials_file =
                            sys_get_temp_dir() .
                            DIRECTORY_SEPARATOR .
                            "google-vision-" .
                            uniqid() .
                            ".json";


                        if (
                            file_put_contents(
                                $credentials_file,
                                $credentials_json
                            ) === false
                        ) {

                            $error =
                                "Unable to create temporary OCR credentials.";

                        }
                        else {


                            try {


                                /*
                                |--------------------------------------------------------------------------
                                | Configure Google credentials
                                |--------------------------------------------------------------------------
                                */

                                putenv(
                                    "GOOGLE_APPLICATION_CREDENTIALS=" .
                                    $credentials_file
                                );


                                /*
                                |--------------------------------------------------------------------------
                                | Create Vision client
                                |--------------------------------------------------------------------------
                                */

                                $vision =
                                    new ImageAnnotatorClient();


                                /*
                                |--------------------------------------------------------------------------
                                | Read image
                                |--------------------------------------------------------------------------
                                */

                                $image_data =
                                    file_get_contents(
                                        $file["tmp_name"]
                                    );


                                if (
                                    $image_data === false
                                ) {

                                    throw new Exception(
                                        "Unable to read uploaded image."
                                    );

                                }


                                /*
                                |--------------------------------------------------------------------------
                                | Run DOCUMENT_TEXT_DETECTION
                                |--------------------------------------------------------------------------
                                |
                                | This is more suitable for documents/registers
                                | than a simple sparse text detector.
                                |
                                |--------------------------------------------------------------------------
                                */

                                /*
                                
                                /*
|--------------------------------------------------------------------------
| Create Vision Image
|--------------------------------------------------------------------------
*/

$image = new Image();

$image->setContent(
    $image_data
);


/*
|--------------------------------------------------------------------------
| Create OCR Feature
|--------------------------------------------------------------------------
*/

$feature = new Feature();

$feature->setType(
    Type::DOCUMENT_TEXT_DETECTION
);


/*
|--------------------------------------------------------------------------
| Create Annotation Request
|--------------------------------------------------------------------------
*/

$annotation_request =
    new AnnotateImageRequest();

$annotation_request->setImage(
    $image
);

$annotation_request->setFeatures([
    $feature
]);


/*
|--------------------------------------------------------------------------
| Create Batch Request
|--------------------------------------------------------------------------
*/

$batch_request =
    new BatchAnnotateImagesRequest();

$batch_request->setRequests([
    $annotation_request
]);


/*
|--------------------------------------------------------------------------
| Send OCR request
|--------------------------------------------------------------------------
*/

$response =
    $vision->batchAnnotateImages(
        $batch_request
    );


/*
|--------------------------------------------------------------------------
| Get image responses
|--------------------------------------------------------------------------
*/

$responses =
    $response->getResponses();


if (
    count($responses) === 0
) {

    throw new Exception(
        "Google Vision returned no response."
    );

}


$annotation_response =
    $responses[0];


/*
|--------------------------------------------------------------------------
| Check Google Vision error
|--------------------------------------------------------------------------
*/

$vision_error =
    $annotation_response->getError();


if (
    $vision_error &&
    $vision_error->getMessage() !== ""
) {

    throw new Exception(
        "Google Vision error: " .
        $vision_error->getMessage()
    );

}


/*
|--------------------------------------------------------------------------
| Get full OCR annotation
|--------------------------------------------------------------------------
*/

$full_text_annotation =
    $annotation_response->getFullTextAnnotation();


if (
    $full_text_annotation
) {

    $ocr_text =
        $full_text_annotation->getText();

}

                                /*
                                |--------------------------------------------------------------------------
                                | Close Vision client
                                |--------------------------------------------------------------------------
                                */

                                $vision->close();


                                /*
                                |--------------------------------------------------------------------------
                                | Remove credentials
                                |--------------------------------------------------------------------------
                                */

                                if (
                                    file_exists(
                                        $credentials_file
                                    )
                                ) {

                                    unlink(
                                        $credentials_file
                                    );

                                }


                                /*
                                |--------------------------------------------------------------------------
                                | Check result
                                |--------------------------------------------------------------------------
                                */

                                if (
                                    trim($ocr_text) === ""
                                ) {

                                    $error =
                                        "Google Vision could not detect any text. " .
                                        "Try taking a clearer, closer photo.";

                                }
                                else {


                                    /*
                                    |--------------------------------------------------------------------------
                                    | Parse OCR text
                                    |--------------------------------------------------------------------------
                                    |
                                    | The first version intentionally keeps parsing
                                    | conservative.
                                    |
                                    | We show the OCR text to the owner and attempt
                                    | to identify name/phone pairs.
                                    |
                                    |--------------------------------------------------------------------------
                                    */

                                    $lines =
                                        preg_split(
                                            "/\r\n|\r|\n/",
                                            $ocr_text
                                        );


                                    foreach (
                                        $lines as $line
                                    ) {


                                        $line =
                                            trim(
                                                $line
                                            );


                                        if (
                                            $line === ""
                                        ) {

                                            continue;

                                        }


                                        /*
                                        |--------------------------------------------------------------------------
                                        | Try to find Pakistani-style phone number
                                        |--------------------------------------------------------------------------
                                        */

                                        $phone = "";


                                        if (
                                            preg_match(
                                                '/(?:\+92|0092|0)?\s*3\d{2}[\s-]?\d{3}[\s-]?\d{4}/',
                                                $line,
                                                $phone_match
                                            )
                                        ) {

                                            $phone =
                                                trim(
                                                    $phone_match[0]
                                                );


                                            /*
                                            | Remove phone from name text.
                                            */

                                            $name =
                                                trim(
                                                    preg_replace(
                                                        '/(?:\+92|0092|0)?\s*3\d{2}[\s-]?\d{3}[\s-]?\d{4}/',
                                                        "",
                                                        $line
                                                    )
                                                );

                                        }
                                        else {

                                            $name =
                                                $line;

                                        }


                                        /*
                                        |--------------------------------------------------------------------------
                                        | Don't create obviously useless rows
                                        |--------------------------------------------------------------------------
                                        */

                                        if (
                                            $name === ""
                                        ) {

                                            continue;

                                        }


                                        /*
                                        |--------------------------------------------------------------------------
                                        | Avoid very short noise
                                        |--------------------------------------------------------------------------
                                        */

                                        if (
                                            mb_strlen(
                                                $name
                                            ) < 2
                                        ) {

                                            continue;

                                        }


                                        $preview_members[] = [
                                            "name" =>
                                                $name,

                                            "phone" =>
                                                $phone
                                        ];

                                    }

                                }


                            }
                            catch (
                                Throwable $e
                            ) {


                                /*
                                |--------------------------------------------------------------------------
                                | Remove temporary credentials
                                |--------------------------------------------------------------------------
                                */

                                if (
                                    file_exists(
                                        $credentials_file
                                    )
                                ) {

                                    unlink(
                                        $credentials_file
                                    );

                                }


                                $error =
                                    "OCR failed: " .
                                    $e->getMessage();

                            }

                        }

                    }

                }

            }

        }

    }

}


/*
|--------------------------------------------------------------------------
| If OCR produced no parsed rows but text exists
|--------------------------------------------------------------------------
|
| We still display the OCR text so the owner can see what Google Vision
| actually recognized.
|--------------------------------------------------------------------------
*/

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
        OCR Member Import
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

            color: #1f2937;

        }


        .container {

            max-width: 1200px;

            margin: auto;

            padding: 30px;

        }


        .header {

            display: flex;

            justify-content:
                space-between;

            align-items: center;

            gap: 20px;

            margin-bottom: 25px;

        }


        .header h1 {

            margin: 0;

            font-size: 28px;

        }


        .header p {

            margin: 6px 0 0;

            color: #6b7280;

        }


        .back {

            background: #111827;

            color: white;

            text-decoration: none;

            padding: 10px 18px;

            border-radius: 8px;

            font-weight: bold;

            white-space: nowrap;

        }


        .card {

            background: white;

            border-radius: 12px;

            padding: 25px;

            margin-bottom: 25px;

            box-shadow:
                0 3px 12px
                rgba(0, 0, 0, .06);

        }


        .card h2 {

            margin-top: 0;

        }


        .instructions {

            color: #6b7280;

            line-height: 1.6;

        }


        .instructions ul {

            padding-left: 20px;

        }


        .upload-area {

            border:
                2px dashed #cbd5e1;

            border-radius: 10px;

            padding: 30px;

            text-align: center;

            background: #f8fafc;

        }


        input[type="file"] {

            width: 100%;

            max-width: 500px;

            padding: 12px;

            background: white;

            border:
                1px solid #d1d5db;

            border-radius: 8px;

        }


        .button {

            display: inline-block;

            border: none;

            padding: 12px 20px;

            border-radius: 8px;

            color: white;

            font-size: 15px;

            font-weight: bold;

            cursor: pointer;

            text-decoration: none;

            margin-top: 15px;

        }


        .ocr-button {

            background: #7c3aed;

        }


        .import-button {

            background: #16a34a;

        }


        .back-button {

            background: #111827;

        }


        .button:hover {

            opacity: .85;

        }


        .error {

            background: #fee2e2;

            color: #991b1b;

            border:
                1px solid #fecaca;

            padding: 14px;

            border-radius: 8px;

            margin-bottom: 20px;

        }


        .success {

            background: #dcfce7;

            color: #166534;

            border:
                1px solid #bbf7d0;

            padding: 14px;

            border-radius: 8px;

            margin-bottom: 20px;

        }


        .notice {

            background: #eff6ff;

            color: #1e40af;

            border:
                1px solid #bfdbfe;

            padding: 14px;

            border-radius: 8px;

            margin-bottom: 20px;

            line-height: 1.5;

        }


        table {

            width: 100%;

            border-collapse: collapse;

            margin-top: 15px;

        }


        th,
        td {

            padding: 12px;

            border-bottom:
                1px solid #e5e7eb;

            text-align: left;

            vertical-align: middle;

        }


        th {

            background: #f8fafc;

        }


        table input {

            width: 100%;

            padding: 9px;

            border:
                1px solid #d1d5db;

            border-radius: 6px;

        }


        .ocr-text {

            width: 100%;

            min-height: 220px;

            padding: 15px;

            border:
                1px solid #d1d5db;

            border-radius: 8px;

            font-family: monospace;

            line-height: 1.5;

            resize: vertical;

        }


        .gym-info {

            background: #f8fafc;

            padding: 12px 15px;

            border-radius: 8px;

            margin-bottom: 20px;

        }


        .empty {

            color: #6b7280;

            padding: 20px 0;

        }


        @media (max-width: 700px) {

            .container {

                padding: 15px;

            }


            .header {

                flex-direction: column;

                align-items: flex-start;

            }


            .card {

                padding: 18px;

            }


            table {

                min-width: 650px;

            }


            .table-wrapper {

                overflow-x: auto;

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
                OCR Member Import
            </h1>

            <p>
                Import members from a handwritten register
            </p>

        </div>


        <a
            href="members.php"
            class="back"
        >

            ← Members

        </a>

    </div>


    <!-- GYM -->

    <div class="gym-info">

        <strong>
            Gym:
        </strong>

        <?php

        echo e(
            $gym["gym_name"]
        );

        ?>

    </div>


    <?php if ($error !== ""): ?>

        <div class="error">

            <?php
            echo e($error);
            ?>

        </div>

    <?php endif; ?>


    <?php if ($success !== ""): ?>

        <div class="success">

            <?php
            echo e($success);
            ?>

        </div>

        <a
            href="members.php"
            class="button back-button"
        >

            View Members

        </a>

    <?php endif; ?>


    <!-- INSTRUCTIONS -->

    <div class="card">

        <h2>
            How it works
        </h2>


        <div class="instructions">

            <p>
                Take a clear photo of your handwritten member
                register and upload it below.
            </p>


            <ul>

                <li>
                    Keep the page flat.
                </li>

                <li>
                    Make sure the handwriting is visible.
                </li>

                <li>
                    Use good lighting.
                </li>

                <li>
                    Avoid shadows over the register.
                </li>

                <li>
                    Try to photograph one page at a time.
                </li>

                <li>
                    Review the OCR results before importing.
                </li>

            </ul>


            <div class="notice">

                <strong>
                    Important:
                </strong>

                Handwritten OCR is not always perfect.
                You will be able to correct names and phone
                numbers before they are added to your members.

            </div>

        </div>

    </div>


    <!-- UPLOAD -->

    <div class="card">

        <h2>
            Upload Register
        </h2>


        <form
            method="POST"
            enctype="multipart/form-data"
        >


            <div class="upload-area">

                <p>
                    Select a JPG, PNG, or WEBP image.
                </p>


                <input
                    type="file"
                    name="register_image"
                    accept="image/jpeg,image/png,image/webp"
                    required
                >


                <br>


                <button
                    type="submit"
                    name="run_ocr"
                    class="button ocr-button"
                >

                    Scan Register with OCR

                </button>

            </div>


        </form>

    </div>


    <?php if (
        count($preview_members) > 0
    ): ?>


        <!-- OCR RESULTS -->

        <div class="card">

            <h2>
                Review OCR Results
            </h2>


            <div class="notice">

                Check every row before importing.
                You can edit the detected name and phone number.

            </div>


            <form
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
                                    Member Name
                                </th>

                                <th>
                                    Phone
                                </th>

                            </tr>

                        </thead>


                        <tbody>


                        <?php foreach (
                            $preview_members
                            as $index => $member
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
                                        name="member_name[]"
                                        value="<?php
                                            echo e(
                                                $member["name"]
                                            );
                                        ?>"
                                        required
                                    >

                                </td>


                                <td>

                                    <input
                                        type="text"
                                        name="member_phone[]"
                                        value="<?php
                                            echo e(
                                                $member["phone"]
                                            );
                                        ?>"
                                    >

                                </td>

                            </tr>


                        <?php endforeach; ?>


                        </tbody>

                    </table>

                </div>


                <button
                    type="submit"
                    name="import_members"
                    class="button import-button"
                    onclick="
                        return confirm(
                            'Import these members into your gym?'
                        );
                    "
                >

                    Import Members

                </button>


            </form>

        </div>


    <?php endif; ?>


    <?php if (
        $ocr_text !== ""
    ): ?>


        <!-- RAW OCR -->

        <div class="card">

            <h2>
                Raw OCR Text
            </h2>


            <p class="instructions">

                This is the text Google Vision detected
                from your register.

            </p>


            <textarea
                class="ocr-text"
                readonly
            ><?php

                echo e(
                    $ocr_text
                );

            ?></textarea>

        </div>


    <?php endif; ?>


</div>


</body>

</html>