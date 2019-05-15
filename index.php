<?php

function removeWhiteBackground($imagick, $fillPixelHoles = false) {
    $backgroundColor = "rgb(255, 255, 255)";
    $fuzzFactor = 0.1;
 
    // Create a copy of the image, and paint all the pixels that
    // are the background color to be transparent
    $outlineImagick = clone $imagick;
    $outlineImagick->transparentPaintImage(
        $backgroundColor, 0, $fuzzFactor * \Imagick::getQuantum(), false
    );
     
    // Copy the input image
    $mask = clone $imagick;
    // Deactivate the alpha channel if the image has one, as later in the process
    // we want the mask alpha to be copied from the colour channel to the src
    // alpha channel. If the mask image has an alpha channel, it would be copied
    // from that instead of from the colour channel.
    $mask->setImageAlphaChannel(\Imagick::ALPHACHANNEL_DEACTIVATE);
    //Convert to gray scale to make life simpler
    $mask->transformImageColorSpace(\Imagick::COLORSPACE_GRAY);
 
    // DstOut does a "cookie-cutter" it leaves the shape remaining after the
    // outlineImagick image, is cut out of the mask.
    $mask->compositeImage(
        $outlineImagick,
        \Imagick::COMPOSITE_DSTOUT,
        0, 0
    );
     
    // The mask is now black where the objects are in the image and white
    // where the background is.
    // Negate the image, to have white where the objects are and black for
    // the background
    $mask->negateImage(false);

    if ($fillPixelHoles) {
        // If your image has pixel sized holes in it, you will want to fill them
        // in. This will however also make any acute corners in the image not be
        // transparent.
         
        // Fill holes - any black pixel that is surrounded by white will become
        // white
        $mask->blurimage(2, 1);
        $mask->whiteThresholdImage("rgb(10, 10, 10)");
 
        // Thinning - because the previous step made the outline thicker, we
        // attempt to make it thinner by an equivalent amount.
        $mask->blurimage(2, 1);
        $mask->blackThresholdImage("rgb(255, 255, 255)");
    }
 
    //Soften the edge of the mask to prevent jaggies on the outline.
    $mask->blurimage(2, 2);
 
    // We want the mask to go from full opaque to fully transparent quite quickly to
    // avoid having too many semi-transparent pixels. sigmoidalContrastImage does this
    // for us. Values to use were determined empirically.
    $contrast = 15;
    $midpoint = 0.7 * \Imagick::getQuantum();
    $mask->sigmoidalContrastImage(true, $contrast, $midpoint);
 
    // Copy the mask into the opacity channel of the original image.
    // You are probably done here if you just want the background removed.
    $imagick->compositeImage(
        $mask,
        \Imagick::COMPOSITE_COPYOPACITY,
        0, 0
    );

    return $imagick;
}

function generatePseudoImage($imagick, $color) {
    $canvas = new \Imagick();
    $canvas->newPseudoImage(
        $imagick->getImageWidth(),
        $imagick->getImageHeight(),
        $color
    );

    return $canvas;
}

function applyMorphology($type, $originalCanvas) {
    $canvas = clone $originalCanvas;

    if ($type === \Imagick::MORPHOLOGY_THICKEN) {
        $rightEdgeKernel = \ImagickKernel::fromMatrix([[1, 0]], [0, 0]);

        $leftEdgeKernel = \ImagickKernel::fromMatrix([[0, 1]], [1, 0]);
        $leftEdgeKernel->addKernel($rightEdgeKernel);
    
        $canvas->morphology(\Imagick::MORPHOLOGY_THICKEN, 3, $leftEdgeKernel);
    } else if ($type === \Imagick::MORPHOLOGY_OPEN) {
        $kernel = \ImagickKernel::fromBuiltIn(\Imagick::KERNEL_DISK, '6');
        $canvas->morphology(\Imagick::MORPHOLOGY_OPEN, 1, $kernel);
    } else if ($type === \Imagick::MORPHOLOGY_ERODE) {
        $kernel = \ImagickKernel::fromBuiltIn(\Imagick::KERNEL_OCTAGON, '3');
        $canvas->morphology(\Imagick::MORPHOLOGY_ERODE, 2, $kernel);
    } else if ($type === \Imagick::MORPHOLOGY_SMOOTH) {
        $kernel = \ImagickKernel::fromBuiltIn(\Imagick::KERNEL_OCTAGON, '3');
        $canvas->morphology(\Imagick::MORPHOLOGY_SMOOTH, 1, $kernel);
    }

    return $canvas;
}

function getCharacterOutline() {
    // $url = 'https://phpimagick.com/imageOriginal/Tutorial/backgroundMasking';
    $url = 'https://image.freepik.com/free-photo/guy-with-open-hand-white-background_1149-64.jpg';
    $url = 'https://cdn.fstoppers.com/styles/full/s3/media/2015/12/07/white_background_model_after.jpg';

    $temp = tempnam(sys_get_temp_dir(), 'silhouette-') . '.jpg';

    file_put_contents($temp, file_get_contents($url));

    $originalCanvas = removeWhiteBackground(new \Imagick($temp));

    $whiteCanvas = generatePseudoImage($originalCanvas, 'canvas:white');
    $whiteCanvas->compositeImage($originalCanvas, \Imagick::COMPOSITE_COPYOPACITY, 0, 0);

    $blackCanvas = generatePseudoImage($originalCanvas, 'canvas:black');
    $blackCanvas->compositeImage($whiteCanvas, \Imagick::COMPOSITE_ATOP, 0, 0);

    $normalizedCanvas = applyMorphology(\Imagick::MORPHOLOGY_ERODE, $blackCanvas);
    $normalizedCanvas->compositeImage($blackCanvas, \Imagick::COMPOSITE_BLEND, 0, 0);
    $normalizedCanvas->negateImage(false);

    $output = new \Imagick();
    $output->newPseudoImage(
        $originalCanvas->getImageWidth() * 2,
        $originalCanvas->getImageHeight(),
        'canvas:white'
    );
    $output->compositeImage($originalCanvas, \Imagick::COMPOSITE_ATOP, 0, 0);
    $output->compositeImage($normalizedCanvas, \Imagick::COMPOSITE_ATOP, $originalCanvas->getImageWidth(), 0);
    $output->setFormat('png');
 
    return $output;
}

$output = getCharacterOutline();

header("Content-Type: image/png");
echo $output->getImageBlob();