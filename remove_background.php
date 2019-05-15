<?php

function getCharacterOutline() {
    $url = 'http://www.imagemagick.org/Usage/masking/cyclops.png';

    $temp = tempnam(sys_get_temp_dir(), 'removed-') . '.png';

    file_put_contents($temp, file_get_contents($url));

    $imagick = new \Imagick($temp);


    $canvas->setFormat('png');
 
    return $canvas;
}

$canvas = getCharacterOutline();

// $leftEdgeKernel = \ImagickKernel::fromMatrix([[0, 1]], [1, 0]);
// $rightEdgeKernel = \ImagickKernel::fromMatrix([[1, 0]], [0, 0]);
// $leftEdgeKernel->addKernel($rightEdgeKernel);
// $canvas->morphology(\Imagick::MORPHOLOGY_THICKEN, 3, $leftEdgeKernel);

$kernel = \ImagickKernel::fromBuiltIn(\Imagick::KERNEL_DISK, "6");
$canvas->morphology(\Imagick::MORPHOLOGY_OPEN, 1, $kernel);
$canvas->negateImage(false);

header("Content-Type: image/png");
echo $canvas->getImageBlob();