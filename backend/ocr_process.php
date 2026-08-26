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

$max_size = 10 * 1024 * 1024; // 10 MB

if ($file["size"] > $max_size) {

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
| LOAD ORIGINAL IMAGE
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
| UPSCALE
|--------------------------------------------------------------------------
|
| Handwritten text benefits from being enlarged before OCR.
|
|--------------------------------------------------------------------------
*/

$scale = 3;

$new_width =
    $width * $scale;

$new_height =
    $height * $scale;


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
    $width,
    $height
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


imagepng(
    $processed,
    $processed_path,
    6
);


/*
|--------------------------------------------------------------------------
| CREATE LEFT / RIGHT CROPS
|--------------------------------------------------------------------------
|
| Your register has:
|
| LEFT  = names
| RIGHT = phone numbers
|
| We use a small overlap so characters near the boundary
| aren't unnecessarily cut.
|
|--------------------------------------------------------------------------
*/

$left_crop_width =
    (int) ($new_width * 0.60);

$right_start =
    (int) ($new_width * 0.40);

$right_crop_width =
    $new_width - $right_start;


/*
|--------------------------------------------------------------------------
| LEFT IMAGE
|--------------------------------------------------------------------------
*/

$left_image =
    imagecreatetruecolor(
        $left_crop_width,
        $new_height
    );


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


/*
|--------------------------------------------------------------------------
| RIGHT IMAGE
|--------------------------------------------------------------------------
*/

$right_image =
    imagecreatetruecolor(
        $right_crop_width,
        $new_height
    );


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


imagedestroy($source);
imagedestroy($processed);
imagedestroy($left_image);
imagedestroy($right_image);


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
|
| TSV gives us:
|
| text
| confidence
| x position
| y position
| width
| height
|
| This allows us to match names and phone numbers by row.
|
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

        "output" => $output,

        "return_code" => $return_code

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


    foreach ($lines as $index => $line) {

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
        | Tesseract TSV has 12 columns
        */

        if (count($columns) < 12) {
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
            (float) $columns[10];


        $left =
            (int) $columns[6];

        $top =
            (int) $columns[7];

        $width =
            (int) $columns[8];

        $height =
            (int) $columns[9];


        /*
        | Ignore extremely low-confidence garbage.
        |
        | We keep a fairly low threshold because handwriting
        | can naturally receive lower OCR confidence.
        */

        if ($confidence < 5) {
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
                $width,

            "height" =>
                $height,

            "center_y" =>
                $top + ($height / 2)

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
    $tolerance = 35
) {

    usort(
        $words,
        function ($a, $b) {

            return
                $a["center_y"]
                <=>
                $b["center_y"];

        }
    );


    $rows = [];


    foreach ($words as $word) {

        $placed = false;


        foreach ($rows as &$row) {

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
                        $row["center_y"]
                        * ($count - 1)
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
    | Sort words inside every row
    */

    foreach ($rows as &$row) {

        usort(
            $row["words"],
            function ($a, $b) {

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

    die(
        "Name OCR failed."
    );

}


$name_words =
    parseTSV(
        $name_result["output"]
    );


$name_rows =
    groupIntoRows(
        $name_words,
        40
    );


/*
|--------------------------------------------------------------------------
| PHONE OCR
|--------------------------------------------------------------------------
|
| IMPORTANT:
|
| Only digits are allowed.
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

    die(
        "Phone OCR failed."
    );

}


$phone_words =
    parseTSV(
        $phone_result["output"]
    );


$phone_rows =
    groupIntoRows(
        $phone_words,
        40
    );


/*
|--------------------------------------------------------------------------
| BUILD NAME ROWS
|--------------------------------------------------------------------------
*/

$name_records = [];


foreach ($name_rows as $row) {

    $text_parts = [];

    $confidence_total = 0;

    $confidence_count = 0;


    foreach ($row["words"] as $word) {

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
    | Remove leading numbering
    */

    $name =
        preg_replace(
            '/^\s*[\d\.\-\)\:\_]+\s*/',
            "",
            $name
        );


    /*
    | Keep letters and spaces.
    | This removes obvious OCR garbage.
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
    | Ignore headings
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


/*
|--------------------------------------------------------------------------
| BUILD PHONE ROWS
|--------------------------------------------------------------------------
*/

$phone_records = [];


foreach ($phone_rows as $row) {

    $digits = "";

    $confidence_total = 0;

    $confidence_count = 0;


    foreach ($row["words"] as $word) {

        $digits .=
            $word["text"];


        $confidence_total +=
            $word["confidence"];


        $confidence_count++;

    }


    /*
    | Keep digits only
    */

    $digits =
        preg_replace(
            '/\D/',
            "",
            $digits
        );


    /*
    | Handle OCR missing the leading 0.
    |
    | We only do this if the number looks like
    | a 10-digit Pakistani mobile number.
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
    | Validate Pakistani mobile number.
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


/*
|--------------------------------------------------------------------------
| MATCH PHONE TO NAME BY VERTICAL POSITION
|--------------------------------------------------------------------------
*/

$members = [];


foreach ($name_records as $name_record) {

    $best_phone = null;

    $best_distance = PHP_INT_MAX;


    foreach ($phone_records as $phone_record) {

        $distance =
            abs(
                $name_record["y"]
                -
                $phone_record["y"]
            );


        if (
            $distance < $best_distance
        ) {

            $best_distance =
                $distance;

            $best_phone =
                $phone_record;

        }

    }


    /*
    | 120 pixels is a reasonable maximum after
    | our 3x enlargement.
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
    | OVERALL STATUS
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
| SAVE SESSION
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
| DEBUG OCR TEXT
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
| CLEAN TEMP CROPS
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

    die(

        "No members could be detected. "
        .
        "Please upload a clearer image."

    );

}


/*
|--------------------------------------------------------------------------
| GO TO REVIEW
|--------------------------------------------------------------------------
*/

header(
    "Location: ../smart_member_preview.php"
);

exit();