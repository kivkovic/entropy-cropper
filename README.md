# entropy-cropper

A PHP script for smart image cropping. Uses entropy analysis to find the area of interest in an image (the "foreground"), which will be centered in the cropping rectangle.

![example](https://i.imgur.com/1tfEK9q.png "example")

## Usage

```
php cropper.php -w [width] -h [height] [-q [quality]] -i [input path] -o [output path]
```

Parameters:

 - `width` - crop window width (in pixels)
 - `height` - crop window height (in pixels)
 - `quality` - jpeg output quality (0-100)
 - `input_path` - path to input image (will throw exception if it doesn't exist)
 - `output_path` - path to output image (including filename; will throw exception if directory is not writable)

## Requirements

- php >= 5.3
- php-gd
