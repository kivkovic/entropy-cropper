# entropy-cropper

A PHP script for smart image cropping. Uses entropy analysis to find the area of interest in an image (the "foreground"), which will be centered in the cropping rectangle.

## Usage

```
php cropper.php -w [width] -h [height] [-q [quality]] -i [input path] -o [output path]
```


Requires php-gd