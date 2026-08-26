<?php

echo "<h1>Tesseract Server Test</h1>";

exec(
    "tesseract --version 2>&1",
    $output,
    $return_code
);

echo "<pre>";

echo htmlspecialchars(
    implode(PHP_EOL, $output)
);

echo "</pre>";

if ($return_code === 0) {

    echo "<h2>✅ Tesseract is available on Render.</h2>";

} else {

    echo "<h2>❌ Tesseract is NOT available.</h2>";

}