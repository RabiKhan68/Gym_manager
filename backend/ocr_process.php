<?php

session_start();

require_once "db.php";


/*
|--------------------------------------------------------------------------
| LOGIN CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION["owner_id"])) {

    header("Location: ../login.php");

    exit();

}

$owner_id = (int) $_SESSION["owner_id"];


/*
|--------------------------------------------------------------------------
| CHECK UPLOAD
|--------------------------------------------------------------------------
*/

if (
    !isset($_FILES["register_image"]) ||
    $_FILES["register_image"]["error"] !== UPLOAD_ERR_OK
) {

    die("No valid image was uploaded.");

}


/*
|--------------------------------------------------------------------------
| FILE INFORMATION
|--------------------------------------------------------------------------
*/

$file = $_FILES["register_image"];

$max_size = 10 * 1024 * 1024; // 10 MB

if ($file["size"] > $max_size) {

    die("The image is too large. Maximum size is 10 MB.");

}


$finfo = new finfo(FILEINFO_MIME_TYPE);

$mime = $finfo->file(
    $file["tmp_name"]
);


$allowed = [

    "image/jpeg",
    "image/png"

];


if (!in_array($mime, $allowed, true)) {

    die("Only JPG, JPEG and PNG images are allowed.");

}


/*
|--------------------------------------------------------------------------
| CREATE OCR UPLOAD DIRECTORY
|--------------------------------------------------------------------------
*/

$upload_dir =
    dirname(__DIR__) .
    DIRECTORY_SEPARATOR .
    "uploads" .
    DIRECTORY_SEPARATOR .
    "ocr";


if (!is_dir($upload_dir)) {

    mkdir(
        $upload_dir,
        0755,
        true
    );

}


/*
|--------------------------------------------------------------------------
| RANDOM FILE NAME
|--------------------------------------------------------------------------
*/

$random_name =
    bin2hex(random_bytes(16));


$extension =

    $mime === "image/png"
        ? ".png"
        : ".jpg";


$original_path =
    $upload_dir .
    DIRECTORY_SEPARATOR .
    $random_name .
    $extension;


if (
    !move_uploaded_file(
        $file["tmp_name"],
        $original_path
    )
) {

    die("Could not save the uploaded image.");

}


/*
|--------------------------------------------------------------------------
| PREPROCESS IMAGE
|--------------------------------------------------------------------------
|
| We upscale the image and convert it to grayscale.
| This helps Tesseract with handwritten registers.
|
|--------------------------------------------------------------------------
*/

$processed_path =
    $upload_dir .
    DIRECTORY_SEPARATOR .
    $random_name .
    "_processed.png";


$source = null;


if ($mime === "image/jpeg") {

    $source =
        @imagecreatefromjpeg(
            $original_path
        );

} elseif ($mime === "image/png") {

    $source =
        @imagecreatefrompng(
            $original_path
        );

}


if (!$source) {

    unlink($original_path);

    die("Could not process the image.");

}


$original_width =
    imagesx($source);

$original_height =
    imagesy($source);


/*
|--------------------------------------------------------------------------
| UPSCALE 2X
|--------------------------------------------------------------------------
*/

$new_width =
    $original_width * 2;

$new_height =
    $original_height * 2;


$processed =
    imagecreatetruecolor(
        $new_width,
        $new_height
    );


imagecopyresampled(
    $processed,
    $source,
    0,
    0,
    0,
    0,
    $new_width,
    $new_height,
    $original_width,
    $original_height
);


/*
|--------------------------------------------------------------------------
| GRAYSCALE
|--------------------------------------------------------------------------
*/

imagefilter(
    $processed,
    IMG_FILTER_GRAYSCALE
);


/*
|--------------------------------------------------------------------------
| CONTRAST
|--------------------------------------------------------------------------
*/

imagefilter(
    $processed,
    IMG_FILTER_CONTRAST,
    -20
);


imagepng(
    $processed,
    $processed_path,
    6
);


imagedestroy($source);

imagedestroy($processed);


/*
|--------------------------------------------------------------------------
| TESSERACT
|--------------------------------------------------------------------------
*/

$tesseract = "tesseract";


$input =
    escapeshellarg(
        $processed_path
    );


$command =

    $tesseract .
    " " .
    $input .
    " stdout" .
    " --psm 6" .
    " -l eng" .
    " 2>&1";


$output = [];

$return_code = 0;


exec(
    $command,
    $output,
    $return_code
);


if ($return_code !== 0) {

    @unlink($original_path);

    @unlink($processed_path);

    die(
        "Tesseract OCR failed. " .
        "Please make sure Tesseract is installed correctly."
    );

}


/*
|--------------------------------------------------------------------------
| RAW OCR TEXT
|--------------------------------------------------------------------------
*/

$ocr_text =
    trim(
        implode(
            PHP_EOL,
            $output
        )
    );


if ($ocr_text === "") {

    @unlink($original_path);

    @unlink($processed_path);

    die(
        "No readable text was detected. " .
        "Please upload a clearer image."
    );

}


/*
|--------------------------------------------------------------------------
| PARSE OCR TEXT
|--------------------------------------------------------------------------
*/

$lines =
    preg_split(
        "/\R/",
        $ocr_text
    );


$members = [];


foreach ($lines as $line) {

    $line =
        trim($line);


    if ($line === "") {

        continue;

    }


    /*
    |--------------------------------------------------------------------------
    | FIND PHONE NUMBER
    |--------------------------------------------------------------------------
    |
    | OCR can insert spaces between digits.
    |
    |--------------------------------------------------------------------------
    */

    $phone = "";


    if (
        preg_match(
            '/(03[\d\sOIl]{8,})/i',
            $line,
            $phone_match
        )
    ) {

        $phone =
            $phone_match[1];


        /*
        | Normalize common OCR mistakes
        */

        $phone =
            str_ireplace(
                [
                    "O",
                    "I",
                    "l"
                ],
                "0",
                $phone
            );


        /*
        | Keep digits only
        */

        $phone =
            preg_replace(
                '/\D/',
                "",
                $phone
            );


        /*
        | Only accept Pakistani 11-digit format
        */

        if (
            !preg_match(
                '/^03\d{9}$/',
                $phone
            )
        ) {

            $phone = "";

        }

    }


    /*
    |--------------------------------------------------------------------------
    | REMOVE PHONE FROM LINE
    |--------------------------------------------------------------------------
    */

    $name =
        $line;


    if ($phone !== "") {

        $name =
            preg_replace(
                '/03[\d\sOIl]{8,}/i',
                "",
                $name
            );

    }


    /*
    |--------------------------------------------------------------------------
    | REMOVE ROW NUMBER
    |--------------------------------------------------------------------------
    */

    $name =
        preg_replace(
            '/^\s*[\d\.\-\)\:]+\s*/',
            "",
            $name
        );


    /*
    |--------------------------------------------------------------------------
    | CLEAN NAME
    |--------------------------------------------------------------------------
    */

    $name =
        trim(
            preg_replace(
                '/\s{2,}/',
                " ",
                $name
            )
        );


    /*
    |--------------------------------------------------------------------------
    | IGNORE OBVIOUS HEADINGS
    |--------------------------------------------------------------------------
    */

    $ignored = [

        "member register",
        "member register:",
        "name",
        "phone",
        "phone number",
        "members"

    ];


    if (
        in_array(
            strtolower($name),
            $ignored,
            true
        )
    ) {

        continue;

    }


    /*
    |--------------------------------------------------------------------------
    | REQUIRE A REASONABLE NAME
    |--------------------------------------------------------------------------
    */

    if (
        strlen($name) < 2
    ) {

        continue;

    }


    /*
    |--------------------------------------------------------------------------
    | STORE RESULT
    |--------------------------------------------------------------------------
    */

    $members[] = [

        "name" =>
            $name,

        "phone" =>
            $phone,

        "joining_date" =>
            date("Y-m-d"),

        "status" =>
            "active"

    ];

}


/*
|--------------------------------------------------------------------------
| SAVE OCR RESULTS IN SESSION
|--------------------------------------------------------------------------
*/

$_SESSION["ocr_members"] =
    $members;


/*
|--------------------------------------------------------------------------
| SAVE OCR TEXT FOR DEBUGGING / REVIEW
|--------------------------------------------------------------------------
*/

$_SESSION["ocr_text"] =
    $ocr_text;


/*
|--------------------------------------------------------------------------
| SAVE FILE PATHS
|--------------------------------------------------------------------------
*/

$_SESSION["ocr_original"] =
    $original_path;

$_SESSION["ocr_processed"] =
    $processed_path;


/*
|--------------------------------------------------------------------------
| GO TO REVIEW PAGE
|--------------------------------------------------------------------------
*/

header(
    "Location: ../smart_member_preview.php"
);

exit();