<?php

function crop($source, $crop_width, $crop_height) {

    $image  = imagecreatefromjpeg($source);
    $width  = imagesx($image);
    $height = imagesy($image);
    $factor = 1;

    logg("Image resolution: {$width} x {$height}");
    logg("Crop size: {$crop_width} x {$crop_height}");

    if (min($width, $height) <= 0 || max($width, $height) > 10000) {
        throw new Exception('Invalid image size');
    }

    if ($width > 1000 || $height > 1000) {
        $factor     = $width > $height ? (1000 / $width) : (1000 / $height);
        $new_width  = round($width * $factor);
        $new_height = round($height * $factor);

        $clone1 = copy_resized($image, $new_width, $new_height);
        logg("Working copy resized to: {$new_width} x {$new_height}");

    } else {
        $clone1     = imagecreatefromjpeg($source);
        $new_height = $height;
        $new_width  = $width;
    }

    for ($i = 0; $i < 8; $i++) {
        imagefilter($clone1, IMG_FILTER_GAUSSIAN_BLUR);
    }

    $colors = colors($clone1, $new_width, $new_height);
    $step_x = max(1, floor(($new_width - $crop_width * $factor) / 3));
    $step_y = max(1, floor(($new_height - $crop_height * $factor) / 3));
    logg("Step: $step_x / $step_y");

    $entropy = entropy_grid($colors, 20, 20);

    list($target_x, $target_y) = max_entropy_segment($colors, $new_width, $new_height, $crop_width * $factor, $crop_height * $factor, $step_x, $step_y);
    
    $cropped = imagecrop(
        $image,
        ['x' => $target_x / $factor, 'y' => $target_y / $factor, 'width' => $crop_width, 'height' => $crop_height]
    );

    logg("Memory usage: " . memory_get_peak_usage(true)/1024/1024 . " MB");

    return $cropped;
}

function copy_resized($image, $new_width, $new_height) {
    
    $width  = imagesx($image);
    $height = imagesy($image);
    $clone  = imagecreatetruecolor($new_width, $new_height);
    imagecopyresampled($clone, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
    
    return $clone;
}

function entropy_grid($colors, $step_x, $step_y) {
    $entropy = [];

    for ($x = 0; $x + $step_x < count($colors); $x += $step_x) {
        $entropy[$x] = [];

        for ($y = 0; $y + $step_y < count($colors[$x]); $y += $step_y) {
            $current_entropy = entropy($colors, $x, $y, $step_x, $step_y);
            $entropy []= $current_entropy;
        }
    }

    return $entropy;
}


function max_entropy_segment($colors, $width, $height, $crop_width, $crop_height, $step_x, $step_y) {

    $max_entropy = -100000;
    $max_x = 0;
    $max_y = 0;

    for ($x = 0; $x < $width - $crop_width; $x += $step_x) {
        for ($y = 0; $y < $height - $crop_height; $y += $step_y) {
            
            $current_entropy = entropy($colors, $x, $y, $crop_width, $crop_height, 1, 1, 1);

            if ($current_entropy > $max_entropy) {
                $max_entropy = $current_entropy;
                $max_x = $x;
                $max_y = $y;
            }
        }
    }
    
    return [$max_x, $max_y, $max_entropy];
}

function colors($image, $width, $height) {

    $rgb = [];

    for ($x = 0; $x < $width; $x++) {
        $rgb[$x] = [];

        for ($y = 0; $y < $height; $y++) {
            $value = imagecolorat($image, $x, $y);
            $rgb[$x][$y] = $value;
        }
    }

    return $rgb;
}

function entropy($colors, $x_offset, $y_offset, $crop_width, $crop_height, $normalize_r = 1, $normalize_g = 1, $normalize_b = 1) {

    $levels_rgb  = array_fill(0, 768, 0);
   
    for ($x = 0; $x < $crop_width; $x++) {
        for ($y = 0; $y < $crop_height; $y++) {

            $rgb = $colors[$x + $x_offset][$y + $y_offset];
            list($r, $g, $b) = [($rgb >> 16) & 0xFF, ($rgb >> 8) & 0xFF, $rgb & 0xFF];

            $levels_rgb[$r]++;
            $levels_rgb[$g + 256]++;
            $levels_rgb[$b + 512]++;
        }
    }

    $entropy_rgb  = levels_to_entropy($levels_rgb);
    return $entropy_rgb;
}

function levels_to_entropy($levels) {
    $size = count($levels);
    $sum  = 0;

    for ($i = 0; $i < $size; $i++) {
        $sum += $levels[$i];
    }

    $entropy = 0;

    for ($i = 0; $i < $size; $i++) {
        if ($levels[$i] == 0) {
            continue;
        }

        $value = $levels[$i] / $sum;
        $entropy += $value * log($value, 2);
    }

    return -$entropy;
}

function rgb_to_hsv($r, $g, $b) {
    
    $r /= 255;
    $g /= 255;
    $b /= 255;
 
    $maxrgb = max($r, $g, $b);
    $minrgb = min($r, $g, $b);
    $chroma = $maxrgb - $minrgb;
 
    if ($chroma == 0) {
        return [0, 0, 100 * $maxrgb];
    }
 
    if ($minrgb == $r) {
        $h = 3 - (($g - $b) / $chroma);
    } else if ($minrgb == $b) {
        $h = 1 - (($r - $g) / $chroma);
    } else {
        $h = 5 - (($b - $r) / $chroma);
     }
 
    return [60 * $h, 100 * ($chroma / $maxrgb), 100 * $maxrgb];
}

function logg($line = "") {
    echo "[" . microtime(TRUE) . "] ".$line."\n";
}


/**
 * CLI
 */
if ($argv && count($argv)) {

    error_reporting(0);

    function shutdown_function() {
        $error = error_get_last();
        if (!empty($error['message'])) {
            fwrite(STDERR, "[" . microtime(TRUE) . "] [ERROR] ".$error['message']."\n");
        }
    };

    register_shutdown_function('shutdown_function');

    $USAGE = "\n\nentropy-cropper v0.0.9\n\nUsage:\nphp cropper.php -w [width] -h [height] [-q [quality]] -i [input path] -o [output path]\n\n";

    $options = getopt('w:h:q:i:o:');

    $width = isset($options['w']) ? (integer) $options['w'] : null;
    if (!is_numeric($width) || $width <= 0) {
        throw new Exception("Invalid crop width: \"$width\"$USAGE");
    }

    $height = isset($options['h']) ? (integer) $options['h'] : null;
    if (!is_numeric($height) || $height <= 0) {
        throw new Exception("Invalid crop height: \"height\"$USAGE");
    }
    
    $quality = isset($options['q']) ? $options['q'] : 90;
    if (!is_numeric($quality) || $quality <= 1) {
        throw new Exception("Invalid crop height: \"$quality\"$USAGE");
    }

    $source = isset($options['i']) ? $options['i'] : null;
    $destination = isset($options['o']) ? $options['o'] : null;

    if (empty($source) || !file_exists($source)) {
        throw new Exception("Invalid source path: \"$source\"$USAGE");
    }

    if (empty($destination) || !is_writable(dirname($destination))) {
        throw new Exception("Invalid destination path: \"$destination\"$USAGE");
    }

    $start = microtime(TRUE);
    logg("Cropping: $source");

    $cropped = crop($source, $width, $height);
    imagejpeg($cropped, $destination, $quality);
    
    logg("Saved to: $destination");
    logg("Total duration: " . round(microtime(TRUE) - $start, 3) . "s\n");
}
