<?php
$message = "والد محترم، سلام
دانش‌آموز {name} در کلاس {class} امروز {date} غایب بوده است.
";

echo "پیام پیش‌فرض:<br><pre>" . htmlspecialchars($message) . "</pre><br><br>";
echo "طول کاراکترها:<br>";
echo "strlen(): " . strlen($message) . "<br>";
echo "mb_strlen(): " . mb_strlen($message, 'UTF-8') . "<br>";
echo "تعداد خطوط: " . substr_count($message, "\n") . "<br>";
