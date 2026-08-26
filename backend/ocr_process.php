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


$file = $_FILES["register_image"];


/*
|--------------------------------------------------------------------------
| FILE SIZE
|--------------------------------------------------------------------------
*/

$max_file_size = 10 * 1024 * 1024;

if ($file["size"] > $max_file_size) {

    die(
        "Image is too large. Maximum allowed size is 10 MB."
    );

}


/*
|--------------------------------------------------------------------------
| MIME TYPE
|--------------------------------------------------------------------------
*/

$finfo = new finfo(FILEINFO_MIME_TYPE);

$mime = $finfo->file(
    $file["tmp_name"]
);


$allowed = [
    "image/jpeg",
    "image/png"
];


if (!in_array($mime, $allowed, true)) {

    die(
        "Only JPG, JPEG and PNG images are allowed."
    );

}


/*
|--------------------------------------------------------------------------
| OCR DIRECTORY
|--------------------------------------------------------------------------
*/

$upload_dir =
    dirname(__DIR__) .
    DIRECTORY_SEPARATOR .
    "uploads" .
    DIRECTORY_SEPARATOR .
    "ocr";


if (!is_dir($upload_dir)) {

    if (
        !mkdir(
            $upload_dir,
            0755,
            true
        )
    ) {

        die(
            "Could not create OCR upload directory."
        );

    }

}


/*
|--------------------------------------------------------------------------
| RANDOM FILE NAME
|--------------------------------------------------------------------------
*/

$random_name =
    bin2hex(
        random_bytes(16)
    );


$extension =
    ($mime === "image/png")
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

    die(
        "Could not save uploaded image."
    );

}


/*
|--------------------------------------------------------------------------
| LOAD IMAGE
|--------------------------------------------------------------------------
*/

if ($mime === "image/jpeg") {

    $source =
        @imagecreatefromjpeg(
            $original_path
        );

} else {

    $source =
        @imagecreatefrompng(
            $original_path
        );

}


if (!$source) {

    @unlink($original_path);

    die(
        "Could not read the uploaded image."
    );

}


$width =
    imagesx($source);

$height =
    imagesy($source);


/*
|--------------------------------------------------------------------------
| MEMORY-SAFE RESIZE
|--------------------------------------------------------------------------
*/

$max_dimension = 2000;


$resize_ratio =
    min(
        1,
        $max_dimension /
        max(
            $width,
            $height
        )
    );


$new_width =
    max(
        1,
        (int) round(
            $width *
            $resize_ratio
        )
    );


$new_height =
    max(
        1,
        (int) round(
            $height *
            $resize_ratio
        )
    );


/*
|--------------------------------------------------------------------------
| CREATE PROCESSED IMAGE
|--------------------------------------------------------------------------
*/

$processed =
    imagecreatetruecolor(
        $new_width,
        $new_height
    );


if (!$processed) {

    imagedestroy($source);

    @unlink($original_path);

    die(
        "Could not allocate image memory."
    );

}


/*
|--------------------------------------------------------------------------
| RESIZE
|--------------------------------------------------------------------------
*/

imagecopyresampled(
    $processed,
    $source,
    0,
    0,
    0,
    0,
    $new_width,
    $new_height,
    $width,
    $height
);


/*
|--------------------------------------------------------------------------
| DESTROY ORIGINAL
|--------------------------------------------------------------------------
*/

imagedestroy($source);

unset($source);


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


/*
|--------------------------------------------------------------------------
| SAVE FULL PROCESSED IMAGE
|--------------------------------------------------------------------------
*/

$processed_path =
    $upload_dir .
    DIRECTORY_SEPARATOR .
    $random_name .
    "_processed.png";


if (
    !imagepng(
        $processed,
        $processed_path,
        6
    )
) {

    imagedestroy($processed);

    @unlink($original_path);

    die(
        "Could not save processed image."
    );

}


/*
|--------------------------------------------------------------------------
| CREATE NAME CROP
|--------------------------------------------------------------------------
*/

$left_crop_width =
    (int) round(
        $new_width * 0.70
    );


$left_image =
    imagecreatetruecolor(
        $left_crop_width,
        $new_height
    );


if (!$left_image) {

    imagedestroy($processed);

    @unlink($original_path);
    @unlink($processed_path);

    die(
        "Could not allocate name crop."
    );

}


imagecopy(
    $left_image,
    $processed,
    0,
    0,
    0,
    0,
    $left_crop_width,
    $new_height
);


$left_path =
    $upload_dir .
    DIRECTORY_SEPARATOR .
    $random_name .
    "_names.png";


imagepng(
    $left_image,
    $left_path,
    6
);


imagedestroy($left_image);

unset($left_image);


/*
|--------------------------------------------------------------------------
| CREATE PHONE CROP
|--------------------------------------------------------------------------
*/

$right_start =
    (int) round(
        $new_width * 0.35
    );


$right_crop_width =
    $new_width -
    $right_start;


$right_image =
    imagecreatetruecolor(
        $right_crop_width,
        $new_height
    );


if (!$right_image) {

    imagedestroy($processed);

    @unlink($original_path);
    @unlink($processed_path);
    @unlink($left_path);

    die(
        "Could not allocate phone crop."
    );

}


imagecopy(
    $right_image,
    $processed,
    0,
    0,
    $right_start,
    0,
    $right_crop_width,
    $new_height
);


$right_path =
    $upload_dir .
    DIRECTORY_SEPARATOR .
    $random_name .
    "_phones.png";


imagepng(
    $right_image,
    $right_path,
    6
);


imagedestroy($right_image);

unset($right_image);


/*
|--------------------------------------------------------------------------
| DESTROY GD IMAGE
|--------------------------------------------------------------------------
*/

imagedestroy($processed);

unset($processed);

gc_collect_cycles();


/*
|--------------------------------------------------------------------------
| TESSERACT
|--------------------------------------------------------------------------
*/

$tesseract = "tesseract";


/*
|--------------------------------------------------------------------------
| RUN TESSERACT
|--------------------------------------------------------------------------
*/

function runTesseract(
    $tesseract,
    $image,
    $psm,
    $extra = ""
) {

    $command =
        escapeshellarg($tesseract)
        . " "
        . escapeshellarg($image)
        . " stdout"
        . " --psm "
        . intval($psm)
        . " -l eng "
        . $extra
        . " 2>/dev/null";


    $output = [];

    $return_code = 0;


    exec(
        $command,
        $output,
        $return_code
    );


    return [

        "text" =>
            trim(
                implode(
                    PHP_EOL,
                    $output
                )
            ),

        "return_code" =>
            $return_code

    ];

}


/*
|--------------------------------------------------------------------------
| RUN FULL IMAGE OCR
|--------------------------------------------------------------------------
*/

$full_result =
    runTesseract(
        $tesseract,
        $processed_path,
        6
    );


$full_text =
    $full_result["text"];


unset($full_result);


/*
|--------------------------------------------------------------------------
| RUN NAME OCR
|--------------------------------------------------------------------------
*/

$name_result =
    runTesseract(
        $tesseract,
        $left_path,
        6
    );


$name_text =
    $name_result["text"];


unset($name_result);


/*
|--------------------------------------------------------------------------
| RUN PHONE OCR
|--------------------------------------------------------------------------
*/

$phone_result =
    runTesseract(
        $tesseract,
        $right_path,
        6,
        "-c tessedit_char_whitelist=0123456789"
    );


$phone_text =
    $phone_result["text"];


unset($phone_result);


/*
|--------------------------------------------------------------------------
| CLEAN OCR LINES
|--------------------------------------------------------------------------
*/

function cleanOCRLines(
    $text
) {

    if (
        trim($text) === ""
    ) {

        return [];

    }


    $lines =
        preg_split(
            "/\R/",
            $text
        );


    $clean = [];


    foreach (
        $lines as $line
    ) {

        $line =
            trim($line);


        if ($line === "") {

            continue;

        }


        /*
        | Ignore Tesseract diagnostics.
        */

        if (
            stripos(
                $line,
                "image too small"
            ) !== false
        ) {

            continue;

        }


        if (
            stripos(
                $line,
                "line cannot be recognized"
            ) !== false
        ) {

            continue;

        }


        $clean[] =
            $line;

    }


    return $clean;

}


/*
|--------------------------------------------------------------------------
| PHONE EXTRACTION
|--------------------------------------------------------------------------
*/

function extractPhones(
    $text
) {

    $phones = [];


    /*
    | Remove spaces, dashes and common separators
    | around phone numbers.
    */

    $normalized =
        preg_replace(
            '/(?<=\d)[\s\-]+(?=\d)/',
            "",
            $text
        );


    /*
    | Find Pakistani mobile numbers.
    */

    preg_match_all(
        '/(?:03\d{9}|3\d{9})/',
        $normalized,
        $matches
    );


    if (
        !empty($matches[0])
    ) {

        foreach (
            $matches[0] as $phone
        ) {

            $phone =
                preg_replace(
                    '/\D/',
                    "",
                    $phone
                );


            /*
            | Restore leading zero.
            */

            if (
                strlen($phone) === 10 &&
                str_starts_with(
                    $phone,
                    "3"
                )
            ) {

                $phone =
                    "0" . $phone;

            }


            if (
                preg_match(
                    '/^03\d{9}$/',
                    $phone
                )
            ) {

                $phones[] =
                    $phone;

            }

        }

    }


    return array_values(
        array_unique(
            $phones
        )
    );

}


/*
|--------------------------------------------------------------------------
| NAME CLEANING
|--------------------------------------------------------------------------
*/

function cleanName(
    $text
) {

    /*
    | Remove leading numbering.
    */

    $text =
        preg_replace(
            '/^\s*[\d\.\-\)\:\_]+\s*/',
            "",
            $text
        );


    /*
    | Remove phone numbers.
    */

    $text =
        preg_replace(
            '/03[\d\s\-]{9,15}/',
            "",
            $text
        );


    /*
    | Keep letters, spaces, apostrophes and hyphens.
    */

    $text =
        preg_replace(
            '/[^A-Za-z\s\'\-]/',
            "",
            $text
        );


    $text =
        trim(
            preg_replace(
                '/\s{2,}/',
                " ",
                $text
            )
        );


    return $text;

}


/*
|--------------------------------------------------------------------------
| EXTRACT NAMES FROM FULL OCR
|--------------------------------------------------------------------------
*/

$full_lines =
    cleanOCRLines(
        $full_text
    );


$name_lines =
    cleanOCRLines(
        $name_text
    );


/*
|--------------------------------------------------------------------------
| COMBINE NAME SOURCES
|--------------------------------------------------------------------------
*/

$possible_names = [];


/*
| First use the name crop.
*/

foreach (
    $name_lines as $line
) {

    $name =
        cleanName(
            $line
        );


    if (
        strlen($name) >= 3
    ) {

        $possible_names[] =
            $name;

    }

}


/*
| Then use full-image OCR as fallback/additional source.
*/

foreach (
    $full_lines as $line
) {

    $name =
        cleanName(
            $line
        );


    if (
        strlen($name) >= 3
    ) {

        $possible_names[] =
            $name;

    }

}


/*
|--------------------------------------------------------------------------
| REMOVE OBVIOUS HEADINGS
|--------------------------------------------------------------------------
*/

$ignored_names = [

    "member",
    "members",
    "member register",
    "members register",
    "name",
    "names",
    "phone",
    "phone number",
    "mobile",
    "mobile number",
    "gym register",
    "gym members",
    "register"

];


$final_names = [];


foreach (
    $possible_names as $name
) {

    $lower =
        strtolower(
            trim($name)
        );


    if (
        in_array(
            $lower,
            $ignored_names,
            true
        )
    ) {

        continue;

    }


    /*
    | Ignore lines that are mostly too short.
    */

    if (
        strlen($name) < 3
    ) {

        continue;

    }


    /*
    | Avoid duplicate names.
    */

    $already_exists = false;


    foreach (
        $final_names as $existing
    ) {

        if (
            strtolower($existing)
            ===
            strtolower($name)
        ) {

            $already_exists = true;

            break;

        }

    }


    if (!$already_exists) {

        $final_names[] =
            $name;

    }

}


/*
|--------------------------------------------------------------------------
| EXTRACT PHONES
|--------------------------------------------------------------------------
*/

$phones_from_phone_crop =
    extractPhones(
        $phone_text
    );


$phones_from_full_image =
    extractPhones(
        $full_text
    );


$phones =
    array_values(
        array_unique(
            array_merge(
                $phones_from_phone_crop,
                $phones_from_full_image
            )
        )
    );


/*
|--------------------------------------------------------------------------
| BUILD MEMBERS
|--------------------------------------------------------------------------
|
| For now we pair names and phone numbers by their
| detected order.
|
| The review screen lets the owner correct anything.
|
|--------------------------------------------------------------------------
*/

$members = [];


$name_count =
    count(
        $final_names
    );


$phone_count =
    count(
        $phones
    );


$member_count =
    max(
        $name_count,
        $phone_count
    );


for (
    $i = 0;
    $i < $member_count;
    $i++
) {

    $name =
        $final_names[$i]
        ??
        "";


    $phone =
        $phones[$i]
        ??
        "";


    /*
    | A record is good only if both fields exist.
    */

    $status =
        (
            $name !== "" &&
            $phone !== ""
        )
        ? "good"
        : "review";


    $members[] = [

        "name" =>
            $name,

        "phone" =>
            $phone,

        "joining_date" =>
            date("Y-m-d"),

        "status" =>
            $status,

        /*
        | We don't have reliable TSV confidence
        | in this fallback approach.
        */

        "name_confidence" =>
            0,

        "phone_confidence" =>
            0

    ];

}


/*
|--------------------------------------------------------------------------
| SAVE DEBUG INFORMATION
|--------------------------------------------------------------------------
*/

$_SESSION["ocr_debug"] = [

    "full_text" =>
        $full_text,

    "name_text" =>
        $name_text,

    "phone_text" =>
        $phone_text,

    "names" =>
        $final_names,

    "phones" =>
        $phones

];


/*
|--------------------------------------------------------------------------
| SAVE MEMBERS
|--------------------------------------------------------------------------
*/

$_SESSION["ocr_members"] =
    $members;


$_SESSION["ocr_original"] =
    $original_path;


$_SESSION["ocr_processed"] =
    $processed_path;


/*
|--------------------------------------------------------------------------
| DELETE TEMP CROPS
|--------------------------------------------------------------------------
*/

@unlink($left_path);

@unlink($right_path);


/*
|--------------------------------------------------------------------------
| NO MEMBERS
|--------------------------------------------------------------------------
*/

if (
    count($members) === 0
) {

    /*
    | Keep the processed image temporarily so
    | we can inspect/debug it.
    */

    die(
        "No members could be detected. " .
        "Tesseract could not extract usable " .
        "member information from this image."
    );

}


/*
|--------------------------------------------------------------------------
| REDIRECT TO REVIEW
|--------------------------------------------------------------------------
*/

header(
    "Location: ../smart_member_preview.php"
);

exit();