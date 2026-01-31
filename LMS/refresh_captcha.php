<?php
session_start();

// Generate simple math CAPTCHA
$num1 = rand(1, 10);
$num2 = rand(1, 10);
$answer = $num1 + $num2;

// Store in session
$_SESSION['captcha_num1'] = $num1;
$_SESSION['captcha_num2'] = $num2;
$_SESSION['captcha_answer'] = $answer;

// Return JSON with question as HTML
header('Content-Type: application/json');
echo json_encode([
    'question' => "$num1 + $num2 = <input type=\"number\" name=\"captcha\" class=\"captcha-input-inline\" id=\"captcha-input\" placeholder=\"\" required min=\"0\" max=\"20\">",
    'num1' => $num1,
    'num2' => $num2,
    'success' => true
]);
?>