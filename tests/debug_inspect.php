<?php

$pngPath = 'tests/Fixtures/sample_no_exif.png';
$data = file_get_contents($pngPath);
$res = @imagecreatefromstring($data);
var_dump($res);
if ($res === false) {
    echo "GD failed to decode PNG string!\n";
} else {
    echo 'GD decoded PNG OK: '.imagesx($res).'x'.imagesy($res)."\n";
}
