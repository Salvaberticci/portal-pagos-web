<?php
$f = 'src/Services/WispHubClient.php';
$content = file_get_contents($f);
if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
    file_put_contents($f, substr($content, 3));
    echo "BOM removed from $f\n";
} else {
    echo "No BOM found in $f\n";
}
