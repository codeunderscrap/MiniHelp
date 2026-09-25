<?php
$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}
file_put_contents($uploadDir . 'test.txt', 'hello world');
echo "File created at " . $uploadDir . 'test.txt';
?>
