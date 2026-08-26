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
|
| Keep the image large enough for handwriting OCR,
| but don't allow huge phone-camera images to
| consume Render's memory.
|
|--------------------------------------------------------------------------
*/

$max_dimension = 1800;


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
| LIGHT IMAGE PROCESSING
|--------------------------------------------------------------------------
|
| Do NOT aggressively alter the handwriting.
|
|--------------------------------------------------------------------------
*/

imagefilter(
    $processed,
    IMG_FILTER_GRAYSCALE
);


/*
|--------------------------------------------------------------------------
| SAVE OCR IMAGE
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
| RUN TESSERACT TSV
|--------------------------------------------------------------------------
*/

function runTesseractTSV(
    $tesseract,
    $image,
    $psm = 6
) {

    $command =
        escapeshellarg($tesseract)
        . " "
        . escapeshellarg($image)
        . " stdout"
        . " --psm "
        . intval($psm)
        . " -l eng"
        . " tsv"
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
| PARSE TSV WORDS
|--------------------------------------------------------------------------
*/

function parseTSVWords(
    array $lines
) {

    $words = [];


    foreach (
        $lines as $index => $line
    ) {

        /*
        | Skip TSV header.
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
        | Tesseract TSV has 12 columns.
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


        /*
        | Ignore extremely poor OCR.
        */

        if (
            $confidence < 5
        ) {

            continue;

        }


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


        $center_x =
            $left +
            (
                $word_width / 2
            );


        $center_y =
            $top +
            (
                $word_height / 2
            );


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

            "center_x" =>
                $center_x,

            "center_y" =>
                $center_y

        ];

    }


    return $words;

}


/*
|--------------------------------------------------------------------------
| GROUP WORDS INTO PHYSICAL ROWS
|--------------------------------------------------------------------------
*/

function groupWordsIntoRows(
    array $words,
    $tolerance = 45
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
    | Sort words from left to right.
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
| NORMALIZE OCR DIGITS
|--------------------------------------------------------------------------
|
| Handwritten digits are often interpreted as letters:
|
| O → 0
| I → 1
| l → 1
| S → 5
| Z → 2
| B → 8
|
|--------------------------------------------------------------------------
*/

function normalizePhoneOCR(
    $text
) {

    $text =
        strtoupper(
            trim($text)
        );


    $text =
        strtr(
            $text,
            [

                "O" => "0",
                "Q" => "0",

                "I" => "1",
                "L" => "1",

                "S" => "5",

                "Z" => "2"

            ]
        );


    /*
    | Keep digits only.
    */

    $digits =
        preg_replace(
            '/[^0-9]/',
            "",
            $text
        );


    /*
    | Sometimes Tesseract misses the leading 0.
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


    return $digits;

}


/*
|--------------------------------------------------------------------------
| CLEAN NAME
|--------------------------------------------------------------------------
*/

function cleanMemberName(
    $text
) {

    /*
    | Remove numbering at beginning.
    */

    $text =
        preg_replace(
            '/^\s*[\d\.\-\)\:\_\|]+\s*/',
            "",
            $text
        );


    /*
    | Remove obvious phone-like fragments.
    */

    $text =
        preg_replace(
            '/(?:03[\s\-]*)?\d[\d\s\-]{7,}/',
            "",
            $text
        );


    /*
    | Keep normal English name characters.
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
| IGNORE HEADER / NON-MEMBER WORDS
|--------------------------------------------------------------------------
*/

function isIgnoredText(
    $text
) {

    $normalized =
        strtolower(
            trim($text)
        );


    $ignored = [

        "member",
        "members",
        "member register",
        "members register",
        "register",
        "name",
        "names",
        "phone",
        "phone number",
        "mobile",
        "mobile number",
        "gym register",
        "gym members"

    ];


    return in_array(
        $normalized,
        $ignored,
        true
    );

}


/*
|--------------------------------------------------------------------------
| RUN OCR
|--------------------------------------------------------------------------
*/

$ocr_result =
    runTesseractTSV(
        $tesseract,
        $processed_path,
        6
    );


if (
    $ocr_result["return_code"] !== 0
) {

    @unlink($original_path);
    @unlink($processed_path);

    die(
        "Tesseract OCR failed."
    );

}


$words =
    parseTSVWords(
        $ocr_result["output"]
    );


unset($ocr_result);


/*
|--------------------------------------------------------------------------
| NO OCR WORDS
|--------------------------------------------------------------------------
*/

if (
    count($words) === 0
) {

    @unlink($original_path);
    @unlink($processed_path);

    die(
        "Tesseract could not recognize any text."
    );

}


/*
|--------------------------------------------------------------------------
| GROUP PHYSICAL ROWS
|--------------------------------------------------------------------------
*/

$rows =
    groupWordsIntoRows(
        $words,
        45
    );


unset($words);


/*
|--------------------------------------------------------------------------
| IMAGE COLUMN DIVISION
|--------------------------------------------------------------------------
|
| Your register has:
|
| LEFT  → names
| RIGHT → phone numbers
|
| We use approximately 55% as the divider.
|
|--------------------------------------------------------------------------
*/

$image_center =
    0;


/*
|--------------------------------------------------------------------------
| FIND APPROXIMATE IMAGE WIDTH
|--------------------------------------------------------------------------
*/

$info =
    @getimagesize(
        $processed_path
    );


if (
    $info &&
    isset($info[0])
) {

    $image_width =
        (int) $info[0];

} else {

    $image_width = 1800;

}


$column_divider =
    $image_width * 0.55;


/*
|--------------------------------------------------------------------------
| BUILD MEMBER RECORDS
|--------------------------------------------------------------------------
*/

$members = [];


foreach (
    $rows as $row
) {

    $name_parts = [];

    $phone_parts = [];

    $name_confidence = [];

    $phone_confidence = [];


    foreach (
        $row["words"] as $word
    ) {

        $text =
            trim(
                $word["text"]
            );


        if (
            $text === ""
        ) {

            continue;

        }


        /*
        | Skip heading-like rows.
        */

        if (
            isIgnoredText($text)
        ) {

            continue;

        }


        /*
        |--------------------------------------------------------------------------
        | PHONE SIDE
        |--------------------------------------------------------------------------
        */

        if (
            $word["center_x"]
            >=
            $column_divider
        ) {

            $phone_parts[] =
                $text;


            $phone_confidence[] =
                $word["confidence"];

        }


        /*
        |--------------------------------------------------------------------------
        | NAME SIDE
        |--------------------------------------------------------------------------
        */

        else {

            $name_parts[] =
                $text;


            $name_confidence[] =
                $word["confidence"];

        }

    }


    /*
    |--------------------------------------------------------------------------
    | BUILD NAME
    |--------------------------------------------------------------------------
    */

    $raw_name =
        trim(
            implode(
                " ",
                $name_parts
            )
        );


    $name =
        cleanMemberName(
            $raw_name
        );


    /*
    |--------------------------------------------------------------------------
    | BUILD PHONE
    |--------------------------------------------------------------------------
    */

    $raw_phone =
        trim(
            implode(
                "",
                $phone_parts
            )
        );


    $phone =
        normalizePhoneOCR(
            $raw_phone
        );


    /*
    |--------------------------------------------------------------------------
    | DETERMINE WHETHER THIS IS A REAL MEMBER ROW
    |--------------------------------------------------------------------------
    |
    | A row should contain at least a plausible name.
    |
    |--------------------------------------------------------------------------
    */

    if (
        $name === "" ||
        strlen($name) < 3
    ) {

        continue;

    }


    /*
    | Ignore obvious headers.
    */

    if (
        isIgnoredText($name)
    ) {

        continue;

    }


    /*
    |--------------------------------------------------------------------------
    | CONFIDENCE
    |--------------------------------------------------------------------------
    */

    if (
        count($name_confidence) > 0
    ) {

        $name_confidence_value =
            array_sum(
                $name_confidence
            )
            /
            count(
                $name_confidence
            );

    } else {

        $name_confidence_value = 0;

    }


    if (
        count($phone_confidence) > 0
    ) {

        $phone_confidence_value =
            array_sum(
                $phone_confidence
            )
            /
            count(
                $phone_confidence
            );

    } else {

        $phone_confidence_value = 0;

    }


    /*
    |--------------------------------------------------------------------------
    | STATUS
    |--------------------------------------------------------------------------
    */

    $status = "review";


    if (
        preg_match(
            '/^03\d{9}$/',
            $phone
        ) &&
        $name_confidence_value >= 40
    ) {

        $status = "good";

    }


    /*
    |--------------------------------------------------------------------------
    | ADD MEMBER
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
            $status,

        "name_confidence" =>
            round(
                $name_confidence_value,
                1
            ),

        "phone_confidence" =>
            round(
                $phone_confidence_value,
                1
            )

    ];

}


/*
|--------------------------------------------------------------------------
| REMOVE DUPLICATE MEMBERS
|--------------------------------------------------------------------------
*/

$unique_members = [];


$seen = [];


foreach (
    $members as $member
) {

    $key =
        strtolower(
            trim(
                $member["name"]
            )
        )
        .
        "|"
        .
        trim(
            $member["phone"]
        );


    if (
        isset(
            $seen[$key]
        )
    ) {

        continue;

    }


    $seen[$key] = true;


    $unique_members[] =
        $member;

}


$members =
    $unique_members;


unset($unique_members);


/*
|--------------------------------------------------------------------------
| SAVE DEBUG DATA
|--------------------------------------------------------------------------
|
| Keep this while developing the OCR.
|
|--------------------------------------------------------------------------
*/

$_SESSION["ocr_debug"] = [

    "row_count" =>
        count($rows),

    "members" =>
        $members,

    "image_width" =>
        $image_width,

    "column_divider" =>
        $column_divider

];


/*
|--------------------------------------------------------------------------
| SAVE MEMBERS TO SESSION
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
| NO MEMBERS
|--------------------------------------------------------------------------
*/

if (
    count($members) === 0
) {

    /*
    | Keep the processed image for debugging.
    */

    die(
        "No member rows could be detected. " .
        "Tesseract did not find usable name rows."
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