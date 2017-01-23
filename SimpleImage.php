<?php
class SimpleImage {

    public $quality = 80;

    protected $image, $filename, $original_info, $width, $height, $imagestring, $mimetype;
    function __construct($filename = null, $width = null, $height = null, $color = null) {
        if ($filename) {
            $this->load($filename);
        } elseif ($width) {
            $this->create($width, $height, $color);
        }
        return $this;
    }

    function __destruct() {
        if( $this->image !== null && get_resource_type($this->image) === 'gd' ) {
            imagedestroy($this->image);
        }
    }

    function adaptive_resize($width, $height = null) {

        return $this->thumbnail($width, $height);

    }


    function auto_orient() {

        if(isset($this->original_info['exif']['Orientation'])) {
            switch ($this->original_info['exif']['Orientation']) {
                case 1:
                    // Do nothing
                    break;
                case 2:
                    // Flip horizontal
                    $this->flip('x');
                    break;
                case 3:
                    // Rotate 180 counterclockwise
                    $this->rotate(-180);
                    break;
                case 4:
                    // vertical flip
                    $this->flip('y');
                    break;
                case 5:
                    // Rotate 90 clockwise and flip vertically
                    $this->flip('y');
                    $this->rotate(90);
                    break;
                case 6:
                    // Rotate 90 clockwise
                    $this->rotate(90);
                    break;
                case 7:
                    // Rotate 90 clockwise and flip horizontally
                    $this->flip('x');
                    $this->rotate(90);
                    break;
                case 8:
                    // Rotate 90 counterclockwise
                    $this->rotate(-90);
                    break;
            }
        }

        return $this;

    }

    function best_fit($max_width, $max_height) {

        // If it already fits, there's nothing to do
        if ($this->width <= $max_width && $this->height <= $max_height) {
            return $this;
        }

        // Determine aspect ratio
        $aspect_ratio = $this->height / $this->width;

        // Make width fit into new dimensions
        if ($this->width > $max_width) {
            $width = $max_width;
            $height = $width * $aspect_ratio;
        } else {
            $width = $this->width;
            $height = $this->height;
        }

        // Make height fit into new dimensions
        if ($height > $max_height) {
            $height = $max_height;
            $width = $height / $aspect_ratio;
        }

        return $this->resize($width, $height);

    }

    function blur($type = 'selective', $passes = 1) {
        switch (strtolower($type)) {
            case 'gaussian':
                $type = IMG_FILTER_GAUSSIAN_BLUR;
                break;
            default:
                $type = IMG_FILTER_SELECTIVE_BLUR;
                break;
        }
        for ($i = 0; $i < $passes; $i++) {
            imagefilter($this->image, $type);
        }
        return $this;
    }

    function brightness($level) {
        imagefilter($this->image, IMG_FILTER_BRIGHTNESS, $this->keep_within($level, -255, 255));
        return $this;
    }

    function contrast($level) {
        imagefilter($this->image, IMG_FILTER_CONTRAST, $this->keep_within($level, -100, 100));
        return $this;
    }


    function colorize($color, $opacity) {
        $rgba = $this->normalize_color($color);
        $alpha = $this->keep_within(127 - (127 * $opacity), 0, 127);
        imagefilter($this->image, IMG_FILTER_COLORIZE, $this->keep_within($rgba['r'], 0, 255), $this->keep_within($rgba['g'], 0, 255), $this->keep_within($rgba['b'], 0, 255), $alpha);
        return $this;
    }


    function create($width, $height = null, $color = null) {

        $height = $height ?: $width;
        $this->width = $width;
        $this->height = $height;
        $this->image = imagecreatetruecolor($width, $height);
        $this->original_info = array(
            'width' => $width,
            'height' => $height,
            'orientation' => $this->get_orientation(),
            'exif' => null,
            'format' => 'png',
            'mime' => 'image/png'
        );

        if ($color) {
            $this->fill($color);
        }

        return $this;

    }


    function crop($x1, $y1, $x2, $y2) {

        // Determine crop size
        if ($x2 < $x1) {
            list($x1, $x2) = array($x2, $x1);
        }
        if ($y2 < $y1) {
            list($y1, $y2) = array($y2, $y1);
        }
        $crop_width = $x2 - $x1;
        $crop_height = $y2 - $y1;

        // Perform crop
        $new = imagecreatetruecolor($crop_width, $crop_height);
        imagealphablending($new, false);
        imagesavealpha($new, true);
        imagecopyresampled($new, $this->image, 0, 0, $x1, $y1, $crop_width, $crop_height, $crop_width, $crop_height);

        // Update meta data
        $this->width = $crop_width;
        $this->height = $crop_height;
        $this->image = $new;

        return $this;

    }

    function desaturate($percentage = 100) {

        // Determine percentage
        $percentage = $this->keep_within($percentage, 0, 100);

        if( $percentage === 100 ) {
            imagefilter($this->image, IMG_FILTER_GRAYSCALE);
        } else {
            // Make a desaturated copy of the image
            $new = imagecreatetruecolor($this->width, $this->height);
            imagealphablending($new, false);
            imagesavealpha($new, true);
            imagecopy($new, $this->image, 0, 0, 0, 0, $this->width, $this->height);
            imagefilter($new, IMG_FILTER_GRAYSCALE);

            // Merge with specified percentage
            $this->imagecopymerge_alpha($this->image, $new, 0, 0, 0, 0, $this->width, $this->height, $percentage);
            imagedestroy($new);

        }

        return $this;
    }


    function edges() {
        imagefilter($this->image, IMG_FILTER_EDGEDETECT);
        return $this;
    }


    function emboss() {
        imagefilter($this->image, IMG_FILTER_EMBOSS);
        return $this;
    }


    function fill($color = '#000000') {

        $rgba = $this->normalize_color($color);
        $fill_color = imagecolorallocatealpha($this->image, $rgba['r'], $rgba['g'], $rgba['b'], $rgba['a']);
        imagealphablending($this->image, false);
        imagesavealpha($this->image, true);
        imagefilledrectangle($this->image, 0, 0, $this->width, $this->height, $fill_color);

        return $this;

    }


    function fit_to_height($height) {

        $aspect_ratio = $this->height / $this->width;
        $width = $height / $aspect_ratio;

        return $this->resize($width, $height);

    }

    function fit_to_width($width) {

        $aspect_ratio = $this->height / $this->width;
        $height = $width * $aspect_ratio;

        return $this->resize($width, $height);

    }

    function flip($direction) {

        $new = imagecreatetruecolor($this->width, $this->height);
        imagealphablending($new, false);
        imagesavealpha($new, true);

        switch (strtolower($direction)) {
            case 'y':
                for ($y = 0; $y < $this->height; $y++) {
                    imagecopy($new, $this->image, 0, $y, 0, $this->height - $y - 1, $this->width, 1);
                }
                break;
            default:
                for ($x = 0; $x < $this->width; $x++) {
                    imagecopy($new, $this->image, $x, 0, $this->width - $x - 1, 0, 1, $this->height);
                }
                break;
        }

        $this->image = $new;

        return $this;

    }

    function get_height() {
        return $this->height;
    }

    function get_orientation() {

        if (imagesx($this->image) > imagesy($this->image)) {
            return 'landscape';
        }

        if (imagesx($this->image) < imagesy($this->image)) {
            return 'portrait';
        }

        return 'square';

    }

    function get_original_info() {
        return $this->original_info;
    }

    function get_width() {
        return $this->width;
    }

    function invert() {
        imagefilter($this->image, IMG_FILTER_NEGATE);
        return $this;
    }

    function load($filename) {

        // Require GD library
        if (!extension_loaded('gd')) {
            throw new Exception('Required extension GD is not loaded.');
        }
        $this->filename = $filename;
        return $this->get_meta_data();
    }


    function load_base64($base64string) {
        if (!extension_loaded('gd')) {
            throw new Exception('Required extension GD is not loaded.');
        }
        //remove data URI scheme and spaces from base64 string then decode it
        $this->imagestring = base64_decode(str_replace(' ', '+',preg_replace('#^data:image/[^;]+;base64,#', '', $base64string)));
        $this->image = imagecreatefromstring($this->imagestring);
        return $this->get_meta_data();
    }

    function mean_remove() {
        imagefilter($this->image, IMG_FILTER_MEAN_REMOVAL);
        return $this;
    }

    function opacity($opacity) {

        // Determine opacity
        $opacity = $this->keep_within($opacity, 0, 1) * 100;

        // Make a copy of the image
        $copy = imagecreatetruecolor($this->width, $this->height);
        imagealphablending($copy, false);
        imagesavealpha($copy, true);
        imagecopy($copy, $this->image, 0, 0, 0, 0, $this->width, $this->height);

        // Create transparent layer
        $this->create($this->width, $this->height, array(0, 0, 0, 127));

        // Merge with specified opacity
        $this->imagecopymerge_alpha($this->image, $copy, 0, 0, 0, 0, $this->width, $this->height, $opacity);
        imagedestroy($copy);

        return $this;

    }


    protected function generate($format = null, $quality = null) {

        // Determine quality
        $quality = $quality ?: $this->quality;

        // Determine mimetype
        switch (strtolower($format)) {
            case 'gif':
                $mimetype = 'image/gif';
                break;
            case 'jpeg':
            case 'jpg':
                imageinterlace($this->image, true);
                $mimetype = 'image/jpeg';
                break;
            case 'png':
                $mimetype = 'image/png';
                break;
            default:
                $info = (empty($this->imagestring)) ? getimagesize($this->filename) : getimagesizefromstring($this->imagestring);
                $mimetype = $info['mime'];
                unset($info);
                break;
        }

        // Sets the image data
        ob_start();
        switch ($mimetype) {
            case 'image/gif':
                imagegif($this->image);
                break;
            case 'image/jpeg':
                imagejpeg($this->image, null, round($quality));
                break;
            case 'image/png':
                imagepng($this->image, null, round(9 * $quality / 100));
                break;
            default:
                throw new Exception('Unsupported image format: '.$this->filename);
                break;
        }
        $imagestring = ob_get_contents();
        ob_end_clean();

        return array($mimetype, $imagestring);
    }


    function output($format = null, $quality = null) {

        list( $mimetype, $imagestring ) = $this->generate( $format, $quality );

        // Output the image
        header('Content-Type: '.$mimetype);
        echo $imagestring;
    }


    function output_base64($format = null, $quality = null) {

        list( $mimetype, $imagestring ) = $this->generate( $format, $quality );

        // Returns formatted string for img src
        return "data:{$mimetype};base64,".base64_encode($imagestring);

    }


    function overlay($overlay, $position = 'center', $opacity = 1, $x_offset = 0, $y_offset = 0) {

        // Load overlay image
        if( !($overlay instanceof SimpleImage) ) {
            $overlay = new SimpleImage($overlay);
        }

        // Convert opacity
        $opacity = $opacity * 100;

        // Determine position
        switch (strtolower($position)) {
            case 'top left':
                $x = 0 + $x_offset;
                $y = 0 + $y_offset;
                break;
            case 'top right':
                $x = $this->width - $overlay->width + $x_offset;
                $y = 0 + $y_offset;
                break;
            case 'top':
                $x = ($this->width / 2) - ($overlay->width / 2) + $x_offset;
                $y = 0 + $y_offset;
                break;
            case 'bottom left':
                $x = 0 + $x_offset;
                $y = $this->height - $overlay->height + $y_offset;
                break;
            case 'bottom right':
                $x = $this->width - $overlay->width + $x_offset;
                $y = $this->height - $overlay->height + $y_offset;
                break;
            case 'bottom':
                $x = ($this->width / 2) - ($overlay->width / 2) + $x_offset;
                $y = $this->height - $overlay->height + $y_offset;
                break;
            case 'left':
                $x = 0 + $x_offset;
                $y = ($this->height / 2) - ($overlay->height / 2) + $y_offset;
                break;
            case 'right':
                $x = $this->width - $overlay->width + $x_offset;
                $y = ($this->height / 2) - ($overlay->height / 2) + $y_offset;
                break;
            case 'center':
            default:
                $x = ($this->width / 2) - ($overlay->width / 2) + $x_offset;
                $y = ($this->height / 2) - ($overlay->height / 2) + $y_offset;
                break;
        }

        // Perform the overlay
        $this->imagecopymerge_alpha($this->image, $overlay->image, $x, $y, 0, 0, $overlay->width, $overlay->height, $opacity);

        return $this;

    }

    function pixelate($block_size = 10) {
        imagefilter($this->image, IMG_FILTER_PIXELATE, $block_size, true);
        return $this;
    }

    function resize($width, $height) {

        // Generate new GD image
        $new = imagecreatetruecolor($width, $height);

        if( $this->original_info['format'] === 'gif' ) {
            // Preserve transparency in GIFs
            $transparent_index = imagecolortransparent($this->image);
            $palletsize = imagecolorstotal($this->image);
            if ($transparent_index >= 0 && $transparent_index < $palletsize) {
                $transparent_color = imagecolorsforindex($this->image, $transparent_index);
                $transparent_index = imagecolorallocate($new, $transparent_color['red'], $transparent_color['green'], $transparent_color['blue']);
                imagefill($new, 0, 0, $transparent_index);
                imagecolortransparent($new, $transparent_index);
            }
        } else {
            // Preserve transparency in PNGs (benign for JPEGs)
            imagealphablending($new, false);
            imagesavealpha($new, true);
        }

        // Resize
        imagecopyresampled($new, $this->image, 0, 0, 0, 0, $width, $height, $this->width, $this->height);

        // Update meta data
        $this->width = $width;
        $this->height = $height;
        $this->image = $new;

        return $this;

    }

    function rotate($angle, $bg_color = '#000000') {

        // Perform the rotation
        $rgba = $this->normalize_color($bg_color);
        $bg_color = imagecolorallocatealpha($this->image, $rgba['r'], $rgba['g'], $rgba['b'], $rgba['a']);
        $new = imagerotate($this->image, -($this->keep_within($angle, -360, 360)), $bg_color);
        imagesavealpha($new, true);
        imagealphablending($new, true);

        // Update meta data
        $this->width = imagesx($new);
        $this->height = imagesy($new);
        $this->image = $new;

        return $this;

    }

    function save($filename = null, $quality = null, $format = null) {

        // Determine quality, filename, and format
        $filename = $filename ?: $this->filename;
        if( !$format )
            $format = $this->file_ext($filename) ?: $this->original_info['format'];

        list( $mimetype, $imagestring ) = $this->generate( $format, $quality );

        // Save the image
        $result = file_put_contents( $filename, $imagestring );
        if (!$result)
            throw new Exception('Unable to save image: ' . $filename);

        return $this;

    }

    function sepia() {
        imagefilter($this->image, IMG_FILTER_GRAYSCALE);
        imagefilter($this->image, IMG_FILTER_COLORIZE, 100, 50, 0);
        return $this;
    }

    function sketch() {
        imagefilter($this->image, IMG_FILTER_MEAN_REMOVAL);
        return $this;
    }

    function smooth($level) {
        imagefilter($this->image, IMG_FILTER_SMOOTH, $this->keep_within($level, -10, 10));
        return $this;
    }

    function text($text, $font_file, $font_size = 12, $color = '#000000', $position = 'center', $x_offset = 0, $y_offset = 0, $stroke_color = null, $stroke_size = null, $alignment = null, $letter_spacing = 0) {

        // todo - this method could be improved to support the text angle
        $angle = 0;

        // Determine text color
        if(is_array($color)) {
            foreach($color as $var) {
                $rgba = $this->normalize_color($var);
                $color_arr[] = imagecolorallocatealpha($this->image, $rgba['r'], $rgba['g'], $rgba['b'], $rgba['a']);
            }
        } else {
            $rgba = $this->normalize_color($color);
            $color_arr[] = imagecolorallocatealpha($this->image, $rgba['r'], $rgba['g'], $rgba['b'], $rgba['a']);
        }


        // Determine textbox size
        $box = imagettfbbox($font_size, $angle, $font_file, $text);
        if (!$box) {
            throw new Exception('Unable to load font: '.$font_file);
        }
        $box_width = abs($box[6] - $box[2]);
        $box_height = abs($box[7] - $box[1]);

        // Determine position
        switch (strtolower($position)) {
            case 'top left':
                $x = 0 + $x_offset;
                $y = 0 + $y_offset + $box_height;
                break;
            case 'top right':
                $x = $this->width - $box_width + $x_offset;
                $y = 0 + $y_offset + $box_height;
                break;
            case 'top':
                $x = ($this->width / 2) - ($box_width / 2) + $x_offset;
                $y = 0 + $y_offset + $box_height;
                break;
            case 'bottom left':
                $x = 0 + $x_offset;
                $y = $this->height - $box_height + $y_offset + $box_height;
                break;
            case 'bottom right':
                $x = $this->width - $box_width + $x_offset;
                $y = $this->height - $box_height + $y_offset + $box_height;
                break;
            case 'bottom':
                $x = ($this->width / 2) - ($box_width / 2) + $x_offset;
                $y = $this->height - $box_height + $y_offset + $box_height;
                break;
            case 'left':
                $x = 0 + $x_offset;
                $y = ($this->height / 2) - (($box_height / 2) - $box_height) + $y_offset;
                break;
            case 'right';
                $x = $this->width - $box_width + $x_offset;
                $y = ($this->height / 2) - (($box_height / 2) - $box_height) + $y_offset;
                break;
            case 'center':
            default:
                $x = ($this->width / 2) - ($box_width / 2) + $x_offset;
                $y = ($this->height / 2) - (($box_height / 2) - $box_height) + $y_offset;
                break;
        }

        if($alignment === "left") {
            // Left aligned text
            $x = -($x * 2);
        } else if($alignment === "right") {
            // Right aligned text
            $dimensions = imagettfbbox($font_size, $angle, $font_file, $text);
            $alignment_offset = abs($dimensions[4] - $dimensions[0]);
            $x = -(($x * 2) + $alignment_offset);
        }

        // Add the text
        imagesavealpha($this->image, true);
        imagealphablending($this->image, true);

        if(isset($stroke_color) && isset($stroke_size)) {

            // Text with stroke
            if(is_array($color) || is_array($stroke_color)) {
                // Multi colored text and/or multi colored stroke

                if(is_array($stroke_color)) {
                    foreach($stroke_color as $key => $var) {
                        $rgba = $this->normalize_color($stroke_color[$key]);
                        $stroke_color[$key] = imagecolorallocatealpha($this->image, $rgba['r'], $rgba['g'], $rgba['b'], $rgba['a']);
                    }
                } else {
                    $rgba = $this->normalize_color($stroke_color);
                    $stroke_color = imagecolorallocatealpha($this->image, $rgba['r'], $rgba['g'], $rgba['b'], $rgba['a']);
                }

                $array_of_letters = str_split($text, 1);

                foreach($array_of_letters as $key => $var) {

                    if($key > 0) {
                        $dimensions = imagettfbbox($font_size, $angle, $font_file, $array_of_letters[$key - 1]);
                        $x += abs($dimensions[4] - $dimensions[0]) + $letter_spacing;
                    }

                    // If the next letter is empty, we just move forward to the next letter
                    if($var !== " ") {
                        $this->imagettfstroketext($this->image, $font_size, $angle, $x, $y, current($color_arr), current($stroke_color), $stroke_size, $font_file, $var);

                       // #000 is 0, black will reset the array so we write it this way
                        if(next($color_arr) === false) {
                            reset($color_arr);
                        }

                        // #000 is 0, black will reset the array so we write it this way
                        if(next($stroke_color) === false) {
                            reset($stroke_color);
                        }
                    }
                }

            } else {
                $rgba = $this->normalize_color($stroke_color);
                $stroke_color = imagecolorallocatealpha($this->image, $rgba['r'], $rgba['g'], $rgba['b'], $rgba['a']);
                $this->imagettfstroketext($this->image, $font_size, $angle, $x, $y, $color_arr[0], $stroke_color, $stroke_size, $font_file, $text);
            }

        } else {

            // Text without stroke

            if(is_array($color)) {
                // Multi colored text

                $array_of_letters = str_split($text, 1);

                foreach($array_of_letters as $key => $var) {

                    if($key > 0) {
                        $dimensions = imagettfbbox($font_size, $angle, $font_file, $array_of_letters[$key - 1]);
                        $x += abs($dimensions[4] - $dimensions[0]) + $letter_spacing;
                    }

                    // If the next letter is empty, we just move forward to the next letter
                    if($var !== " ") {
                        imagettftext($this->image, $font_size, $angle, $x, $y, current($color_arr), $font_file, $var);

                        // #000 is 0, black will reset the array so we write it this way
                        if(next($color_arr) === false) {
                            reset($color_arr);
                        }
                    }
                }

            } else {
                imagettftext($this->image, $font_size, $angle, $x, $y, $color_arr[0], $font_file, $text);
            }
        }

        return $this;

    }

    public function thumbnail($width, $height = null, $focal = 'center') {

        // Determine height
        $height = $height ?: $width;

        // Determine aspect ratios
        $current_aspect_ratio = $this->height / $this->width;
        $new_aspect_ratio = $height / $width;

        // Fit to height/width
        if ($new_aspect_ratio > $current_aspect_ratio) {
            $this->fit_to_height($height);
        } else {
            $this->fit_to_width($width);
        }

        switch(strtolower($focal)) {
            case 'top':
                $left = floor(($this->width / 2) - ($width / 2));
                $right = $width + $left;
                $top = 0;
                $bottom = $height;
                break;
            case 'bottom':
                $left = floor(($this->width / 2) - ($width / 2));
                $right = $width + $left;
                $top = $this->height - $height;
                $bottom = $this->height;
                break;
            case 'left':
                $left = 0;
                $right = $width;
                $top = floor(($this->height / 2) - ($height / 2));
                $bottom = $height + $top;
                break;
            case 'right':
                $left = $this->width - $width;
                $right = $this->width;
                $top = floor(($this->height / 2) - ($height / 2));
                $bottom = $height + $top;
                break;
            case 'top left':
                $left = 0;
                $right = $width;
                $top = 0;
                $bottom = $height;
                break;
            case 'top right':
                $left = $this->width - $width;
                $right = $this->width;
                $top = 0;
                $bottom = $height;
                break;
            case 'bottom left':
                $left = 0;
                $right = $width;
                $top = $this->height - $height;
                $bottom = $this->height;
                break;
            case 'bottom right':
                $left = $this->width - $width;
                $right = $this->width;
                $top = $this->height - $height;
                $bottom = $this->height;
                break;
            case 'center': 
            default:
                $left = floor(($this->width / 2) - ($width / 2));
                $right = $width + $left;
                $top = floor(($this->height / 2) - ($height / 2));
                $bottom = $height + $top;
                break;
        }

        // Return trimmed image
        return $this->crop($left, $top, $right, $bottom);
    }

    protected function file_ext($filename) {

        if (!preg_match('/\./', $filename)) {
            return '';
        }

        return preg_replace('/^.*\./', '', $filename);

    }

    protected function get_meta_data() {
        //gather meta data
        if(empty($this->imagestring)) {
            $info = getimagesize($this->filename);

            switch ($info['mime']) {
                case 'image/gif':
                    $this->image = imagecreatefromgif($this->filename);
                    break;
                case 'image/jpeg':
                    $this->image = imagecreatefromjpeg($this->filename);
                    break;
                case 'image/png':
                    $this->image = imagecreatefrompng($this->filename);
                    break;
                default:
                    throw new Exception('Invalid image: '.$this->filename);
                    break;
            }
        } elseif (function_exists('getimagesizefromstring')) {
            $info = getimagesizefromstring($this->imagestring);
        } else {
            throw new Exception('PHP 5.4 is required to use method getimagesizefromstring');
        }

        $this->original_info = array(
            'width' => $info[0],
            'height' => $info[1],
            'orientation' => $this->get_orientation(),
            'exif' => function_exists('exif_read_data') && $info['mime'] === 'image/jpeg' && $this->imagestring === null ? $this->exif = @exif_read_data($this->filename) : null,
            'format' => preg_replace('/^image\//', '', $info['mime']),
            'mime' => $info['mime']
        );
        $this->width = $info[0];
        $this->height = $info[1];

        imagesavealpha($this->image, true);
        imagealphablending($this->image, true);

        return $this;

    }

    protected function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct) {

        // Get image width and height and percentage
        $pct /= 100;
        $w = imagesx($src_im);
        $h = imagesy($src_im);

        // Turn alpha blending off
        imagealphablending($src_im, false);

        // Find the most opaque pixel in the image (the one with the smallest alpha value)
        $minalpha = 127;
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $alpha = (imagecolorat($src_im, $x, $y) >> 24) & 0xFF;
                if ($alpha < $minalpha) {
                    $minalpha = $alpha;
                }
            }
        }

        // Loop through image pixels and modify alpha for each
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                // Get current alpha value (represents the TANSPARENCY!)
                $colorxy = imagecolorat($src_im, $x, $y);
                $alpha = ($colorxy >> 24) & 0xFF;
                // Calculate new alpha
                if ($minalpha !== 127) {
                    $alpha = 127 + 127 * $pct * ($alpha - 127) / (127 - $minalpha);
                } else {
                    $alpha += 127 * $pct;
                }
                // Get the color index with new alpha
                $alphacolorxy = imagecolorallocatealpha($src_im, ($colorxy >> 16) & 0xFF, ($colorxy >> 8) & 0xFF, $colorxy & 0xFF, $alpha);
                // Set pixel with the new color + opacity
                if (!imagesetpixel($src_im, $x, $y, $alphacolorxy)) {
                    return;
                }
            }
        }

        // Copy it
        imagesavealpha($dst_im, true);
        imagealphablending($dst_im, true);
        imagesavealpha($src_im, true);
        imagealphablending($src_im, true);
        imagecopy($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h);

    }

    protected function imagettfstroketext(&$image, $size, $angle, $x, $y, &$textcolor, &$strokecolor, $stroke_size, $fontfile, $text) {
        for( $c1 = ($x - abs($stroke_size)); $c1 <= ($x + abs($stroke_size)); $c1++ ) {
            for($c2 = ($y - abs($stroke_size)); $c2 <= ($y + abs($stroke_size)); $c2++) {
                $bg = imagettftext($image, $size, $angle, $c1, $c2, $strokecolor, $fontfile, $text);
            }
        }
        return imagettftext($image, $size, $angle, $x, $y, $textcolor, $fontfile, $text);
    }

    protected function keep_within($value, $min, $max) {

        if ($value < $min) {
            return $min;
        }

        if ($value > $max) {
            return $max;
        }

        return $value;

    }

    protected function normalize_color($color) {

        if (is_string($color)) {

            $color = trim($color, '#');

            if (strlen($color) == 6) {
                list($r, $g, $b) = array(
                    $color[0].$color[1],
                    $color[2].$color[3],
                    $color[4].$color[5]
                );
            } elseif (strlen($color) == 3) {
                list($r, $g, $b) = array(
                    $color[0].$color[0],
                    $color[1].$color[1],
                    $color[2].$color[2]
                );
            } else {
                return false;
            }
            return array(
                'r' => hexdec($r),
                'g' => hexdec($g),
                'b' => hexdec($b),
                'a' => 0
            );

        } elseif (is_array($color) && (count($color) == 3 || count($color) == 4)) {

            if (isset($color['r'], $color['g'], $color['b'])) {
                return array(
                    'r' => $this->keep_within($color['r'], 0, 255),
                    'g' => $this->keep_within($color['g'], 0, 255),
                    'b' => $this->keep_within($color['b'], 0, 255),
                    'a' => $this->keep_within(isset($color['a']) ? $color['a'] : 0, 0, 127)
                );
            } elseif (isset($color[0], $color[1], $color[2])) {
                return array(
                    'r' => $this->keep_within($color[0], 0, 255),
                    'g' => $this->keep_within($color[1], 0, 255),
                    'b' => $this->keep_within($color[2], 0, 255),
                    'a' => $this->keep_within(isset($color[3]) ? $color[3] : 0, 0, 127)
                );
            }

        }
        return false;
    }

}

?>