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

$max_file_size = 10 * 1024 * 1024; // 10 MB

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
|
| We do NOT enlarge the image 3x.
|
| Instead, we make sure the longest side is
| no larger than 2000 pixels.
|
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
| CREATE RESIZED IMAGE
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
| COPY / RESIZE
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
| CREATE LEFT / RIGHT CROPS
|--------------------------------------------------------------------------
|
| Your register is approximately:
|
| LEFT  = names
| RIGHT = phone numbers
|
| We deliberately overlap the two areas.
|
|--------------------------------------------------------------------------
*/

$left_crop_width =
    (int) round(
        $new_width * 0.60
    );


$right_start =
    (int) round(
        $new_width * 0.40
    );


$right_crop_width =
    $new_width -
    $right_start;


/*
|--------------------------------------------------------------------------
| LEFT / NAME CROP
|--------------------------------------------------------------------------
*/

$left_image =
    imagecreatetruecolor(
        $left_crop_width,
        $new_height
    );


if (!$left_image) {

    imagedestroy($processed);

    @unlink($original_path);

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


if (
    !imagepng(
        $left_image,
        $left_path,
        6
    )
) {

    imagedestroy($left_image);
    imagedestroy($processed);

    @unlink($original_path);

    die(
        "Could not create name OCR image."
    );

}


/*
|--------------------------------------------------------------------------
| DESTROY LEFT GD IMAGE
|--------------------------------------------------------------------------
*/

imagedestroy($left_image);

unset($left_image);


/*
|--------------------------------------------------------------------------
| RIGHT / PHONE CROP
|--------------------------------------------------------------------------
*/

$right_image =
    imagecreatetruecolor(
        $right_crop_width,
        $new_height
    );


if (!$right_image) {

    imagedestroy($processed);

    @unlink($original_path);
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


if (
    !imagepng(
        $right_image,
        $right_path,
        6
    )
) {

    imagedestroy($right_image);
    imagedestroy($processed);

    @unlink($original_path);
    @unlink($left_path);

    die(
        "Could not create phone OCR image."
    );

}


/*
|--------------------------------------------------------------------------
| DESTROY ALL GD IMAGES
|--------------------------------------------------------------------------
*/

imagedestroy($right_image);

unset($right_image);

imagedestroy($processed);

unset($processed);


/*
|--------------------------------------------------------------------------
| FORCE GARBAGE COLLECTION
|--------------------------------------------------------------------------
*/

gc_collect_cycles();


/*
|--------------------------------------------------------------------------
| TESSERACT
|--------------------------------------------------------------------------
*/

$tesseract = "tesseract";


/*
|--------------------------------------------------------------------------
| RUN TESSERACT TSV
|--------------------------------------------------------------------------
*/

function runTesseractTSV(
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

        "output" =>
            $output,

        "return_code" =>
            $return_code

    ];

}


/*
|--------------------------------------------------------------------------
| PARSE TSV
|--------------------------------------------------------------------------
*/

function parseTSV(
    array $lines
) {

    $words = [];


    foreach (
        $lines as $index => $line
    ) {

        /*
        | Skip TSV header
        */

        if ($index === 0) {

            continue;

        }


        $columns =
            str_getcsv(
                $line,
                "\t"
            );


        /*
        | Tesseract TSV contains 12 columns.
        */

        if (
            count($columns) < 12
        ) {

            continue;

        }


        $text =
            trim(
                $columns[11]
            );


        if ($text === "") {

            continue;

        }


        $confidence =
            (float)
            $columns[10];


        $left =
            (int)
            $columns[6];


        $top =
            (int)
            $columns[7];


        $word_width =
            (int)
            $columns[8];


        $word_height =
            (int)
            $columns[9];


        /*
        | Ignore extremely low-confidence OCR.
        */

        if (
            $confidence < 5
        ) {

            continue;

        }


        $words[] = [

            "text" =>
                $text,

            "confidence" =>
                $confidence,

            "left" =>
                $left,

            "top" =>
                $top,

            "width" =>
                $word_width,

            "height" =>
                $word_height,

            "center_y" =>
                $top +
                (
                    $word_height / 2
                )

        ];

    }


    return $words;

}


/*
|--------------------------------------------------------------------------
| GROUP WORDS INTO ROWS
|--------------------------------------------------------------------------
*/

function groupIntoRows(
    array $words,
    $tolerance = 30
) {

    usort(
        $words,
        function (
            $a,
            $b
        ) {

            return
                $a["center_y"]
                <=>
                $b["center_y"];

        }
    );


    $rows = [];


    foreach (
        $words as $word
    ) {

        $placed = false;


        foreach (
            $rows as &$row
        ) {

            if (
                abs(
                    $row["center_y"]
                    -
                    $word["center_y"]
                )
                <=
                $tolerance
            ) {

                $row["words"][] =
                    $word;


                $count =
                    count(
                        $row["words"]
                    );


                $row["center_y"] =
                    (
                        (
                            $row["center_y"]
                            *
                            ($count - 1)
                        )
                        +
                        $word["center_y"]
                    )
                    /
                    $count;


                $placed = true;

                break;

            }

        }


        unset($row);


        if (!$placed) {

            $rows[] = [

                "center_y" =>
                    $word["center_y"],

                "words" =>
                    [$word]

            ];

        }

    }


    /*
    | Sort words horizontally.
    */

    foreach (
        $rows as &$row
    ) {

        usort(
            $row["words"],
            function (
                $a,
                $b
            ) {

                return
                    $a["left"]
                    <=>
                    $b["left"];

            }
        );

    }


    unset($row);


    return $rows;

}


/*
|--------------------------------------------------------------------------
| NAME OCR
|--------------------------------------------------------------------------
*/

$name_result =
    runTesseractTSV(
        $tesseract,
        $left_path,
        6
    );


if (
    $name_result["return_code"] !== 0
) {

    @unlink($original_path);
    @unlink($left_path);
    @unlink($right_path);

    die(
        "Name OCR failed."
    );

}


$name_words =
    parseTSV(
        $name_result["output"]
    );


unset($name_result);


/*
|--------------------------------------------------------------------------
| GROUP NAME ROWS
|--------------------------------------------------------------------------
*/

$name_rows =
    groupIntoRows(
        $name_words,
        30
    );


unset($name_words);


/*
|--------------------------------------------------------------------------
| PHONE OCR
|--------------------------------------------------------------------------
|
| Only numeric characters are allowed.
|
|--------------------------------------------------------------------------
*/

$phone_result =
    runTesseractTSV(
        $tesseract,
        $right_path,
        6,
        "-c tessedit_char_whitelist=0123456789"
    );


if (
    $phone_result["return_code"] !== 0
) {

    @unlink($original_path);
    @unlink($left_path);
    @unlink($right_path);

    die(
        "Phone OCR failed."
    );

}


$phone_words =
    parseTSV(
        $phone_result["output"]
    );


unset($phone_result);


/*
|--------------------------------------------------------------------------
| GROUP PHONE ROWS
|--------------------------------------------------------------------------
*/

$phone_rows =
    groupIntoRows(
        $phone_words,
        30
    );


unset($phone_words);


/*
|--------------------------------------------------------------------------
| BUILD NAME RECORDS
|--------------------------------------------------------------------------
*/

$name_records = [];


foreach (
    $name_rows as $row
) {

    $text_parts = [];

    $confidence_total = 0;

    $confidence_count = 0;


    foreach (
        $row["words"] as $word
    ) {

        $text_parts[] =
            $word["text"];


        $confidence_total +=
            $word["confidence"];


        $confidence_count++;

    }


    $name =
        trim(
            implode(
                " ",
                $text_parts
            )
        );


    /*
    | Remove numbering.
    */

    $name =
        preg_replace(
            '/^\s*[\d\.\-\)\:\_]+\s*/',
            "",
            $name
        );


    /*
    | Keep normal name characters.
    */

    $name =
        preg_replace(
            '/[^A-Za-z\s\'\-]/',
            "",
            $name
        );


    $name =
        trim(
            preg_replace(
                '/\s{2,}/',
                " ",
                $name
            )
        );


    /*
    | Ignore headings.
    */

    $ignored = [

        "member register",
        "members register",
        "member",
        "members",
        "name",
        "phone",
        "phone number"

    ];


    if (
        $name === "" ||
        strlen($name) < 3 ||
        in_array(
            strtolower($name),
            $ignored,
            true
        )
    ) {

        continue;

    }


    $average_confidence =
        $confidence_count > 0
            ? $confidence_total /
              $confidence_count
            : 0;


    $name_records[] = [

        "name" =>
            $name,

        "y" =>
            $row["center_y"],

        "confidence" =>
            round(
                $average_confidence,
                1
            )

    ];

}


unset($name_rows);


/*
|--------------------------------------------------------------------------
| BUILD PHONE RECORDS
|--------------------------------------------------------------------------
*/

$phone_records = [];


foreach (
    $phone_rows as $row
) {

    $digits = "";

    $confidence_total = 0;

    $confidence_count = 0;


    foreach (
        $row["words"] as $word
    ) {

        $digits .=
            $word["text"];


        $confidence_total +=
            $word["confidence"];


        $confidence_count++;

    }


    /*
    | Digits only.
    */

    $digits =
        preg_replace(
            '/\D/',
            "",
            $digits
        );


    /*
    | If Tesseract misses the leading zero,
    | restore it when the number starts with 3.
    */

    if (
        strlen($digits) === 10 &&
        str_starts_with(
            $digits,
            "3"
        )
    ) {

        $digits =
            "0" . $digits;

    }


    /*
    | Pakistani mobile number validation.
    */

    if (
        !preg_match(
            '/^03\d{9}$/',
            $digits
        )
    ) {

        continue;

    }


    $average_confidence =
        $confidence_count > 0
            ? $confidence_total /
              $confidence_count
            : 0;


    $phone_records[] = [

        "phone" =>
            $digits,

        "y" =>
            $row["center_y"],

        "confidence" =>
            round(
                $average_confidence,
                1
            )

    ];

}


unset($phone_rows);


/*
|--------------------------------------------------------------------------
| MATCH NAME + PHONE BY VERTICAL POSITION
|--------------------------------------------------------------------------
*/

$members = [];


foreach (
    $name_records as $name_record
) {

    $best_phone = null;

    $best_distance =
        PHP_INT_MAX;


    foreach (
        $phone_records as $phone_record
    ) {

        $distance =
            abs(
                $name_record["y"]
                -
                $phone_record["y"]
            );


        if (
            $distance <
            $best_distance
        ) {

            $best_distance =
                $distance;

            $best_phone =
                $phone_record;

        }

    }


    /*
    | Match only if the phone is reasonably
    | close to the name's row.
    */

    if (
        $best_phone !== null &&
        $best_distance <= 120
    ) {

        $phone =
            $best_phone["phone"];

        $phone_confidence =
            $best_phone["confidence"];

    } else {

        $phone = "";

        $phone_confidence = 0;

    }


    /*
    |--------------------------------------------------------------------------
    | RECORD STATUS
    |--------------------------------------------------------------------------
    */

    $status = "review";


    if (
        $name_record["confidence"] >= 50 &&
        $phone !== "" &&
        $phone_confidence >= 50
    ) {

        $status = "good";

    }


    $members[] = [

        "name" =>
            $name_record["name"],

        "phone" =>
            $phone,

        "joining_date" =>
            date("Y-m-d"),

        "status" =>
            $status,

        "name_confidence" =>
            $name_record["confidence"],

        "phone_confidence" =>
            $phone_confidence

    ];

}


/*
|--------------------------------------------------------------------------
| SAVE OCR DATA TO SESSION
|--------------------------------------------------------------------------
*/

$_SESSION["ocr_members"] =
    $members;


$_SESSION["ocr_original"] =
    $original_path;


/*
|--------------------------------------------------------------------------
| SAVE DEBUG INFORMATION
|--------------------------------------------------------------------------
*/

$_SESSION["ocr_debug"] = [

    "name_records" =>
        $name_records,

    "phone_records" =>
        $phone_records

];


/*
|--------------------------------------------------------------------------
| DELETE TEMP CROPS
|--------------------------------------------------------------------------
|
| The original uploaded image is kept for now.
| The temporary OCR crops are deleted.
|
|--------------------------------------------------------------------------
*/

@unlink($left_path);

@unlink($right_path);


/*
|--------------------------------------------------------------------------
| NO RESULTS
|--------------------------------------------------------------------------
*/

if (
    count($members) === 0
) {

    @unlink($original_path);

    die(
        "No members could be detected. " .
        "Please upload a clearer image."
    );

}


/*
|--------------------------------------------------------------------------
| REDIRECT TO REVIEW PAGE
|--------------------------------------------------------------------------
*/

header(
    "Location: ../smart_member_preview.php"
);

exit();