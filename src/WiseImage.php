<?php

namespace wise\orm;

use Intervention\Image\ImageManager;

class WiseImage
{
    public $ImageManager;
    public $imageOriginal;
    public $imageInvert;
    public $mapJson;
    public $marked;
    public $percentCheck;

    public function __construct($imagePath = null)
    {
        $this->ImageManager = new ImageManager(array('driver' => 'gd'));
        $this->imagePath = $imagePath;
    }

    public function setImage($imagePath)
    {
        $this->imagePath = $imagePath;
    }

    public function make()
    {
        $this->imageOriginal = $this->ImageManager->make($this->imagePath);
    }

    public function filters()
    {
        $this->imageOriginal->contrast(100);
    }

    public function makeInvert()
    {
        $this->imageInvert = $this->ImageManager->make($this->imagePath)->invert();
    }

    public function makeCrop($width, $height, $x = 0, $y = 0)
    {
        $imageCrop = $this->ImageManager->make($this->imagePath);
        $imageCrop->crop($width, $height, $x, $y);

        $imageName = $imageCrop->filename .'.comentario.'. $x.'-'.$y .'.'. $imageCrop->extension;
        $imageCrop->save($imageName);

        return $imageName;
    }

    public function map($jsonPath)
    {
        $mapJson = file_get_contents($jsonPath);
        $mapObj = json_decode($mapJson);

        $this->mapJson = $mapObj;

        return $this->mapJson;
    }

    public function adjustments()
    {
        $adjustments = new stdClass();
       
        /*
         * Check map
         */
        $topRightMap = $this->mapJson->limits->topRight;
        $bottomLeftMap = $this->mapJson->limits->bottomLeft;
        
        $angleMap = $this->anglePoints($topRightMap, $bottomLeftMap);
        $distanceCornersMap = $this->distancePoints($topRightMap, $bottomLeftMap);

        /*
         * Check image
         */
        $topRightImage = $this->topRight($topRightMap);
        $bottomLeftImage = $this->bottomLeft($bottomLeftMap);

        /*
         * Ajust angle image
         */
        $angleImage = $this->anglePoints($topRightImage, $bottomLeftImage);
        // $this->ajustRotate($angleMap - $angleImage);
        
        /*
         * Check image again
         */
        $topRightImage = $this->topRight($topRightMap);
        $bottomLeftImage = $this->bottomLeft($bottomLeftMap);

        /*
         * Ajust size image
         */
        $distanceCornersImage = $this->distancePoints($topRightImage, $bottomLeftImage);
        $p = 100 - ((100 * $distanceCornersImage) / $distanceCornersMap);
        $this->ajustSize($p);

        /*
         * Check image again
         */
        $topRightImage = $this->topRight($topRightMap);
        $bottomLeftImage = $this->bottomLeft($bottomLeftMap);

        $adjustments->ajustX = $topRightImage->x - $topRightMap->x;
        // $adjustments->ajustX = 0;
        $adjustments->ajustY = $bottomLeftImage->y - $bottomLeftMap->y;
        // $adjustments->ajustY = 0;

        return $adjustments;
    }

    public function rectangle($x1, $y1, $x2, $y2)
    {
        $this->imageOriginal->rectangle($x1, $y1, $x2, $y2, function($rectangle) {
            $rectangle->border(1, '#ff0000'); // Red

            $hexcolor = array();
            $black = 0;
            $white = 0;
            $total = 0;
            
            for ($y = $rectangle->y1; $y <= $rectangle->y2; $y++) { 
                for ($x = $rectangle->x1; $x <= $rectangle->x2; $x++) { 
                    // $this->imageOriginal->pixel('#ffff00', $x, $y); // Yellow debug
                    // print '<pre>';
                    // print_r($y);
                    // print '-';
                    // print_r($x);
                    // print '</>';

                    $hexcolor[$y][$x] = $this->imageOriginal->pickColor($x, $y, 'hex');

                    if ($hexcolor[$y][$x] === '#000000') {
                        // print '<pre>';
                        // print_r($y .'-'. $x .':');
                        // print_r($hexcolor[$y][$x]);
                        // print '</pre>';
                        $black++;
                    } else {
                        $white++;
                    }

                    $total++;
                }
            }

            $percentual = (($black / $total) * 100);

            // Se for a marcada, seta como marcada e muda a cor da borda
            if ($percentual > 70) {
                $rectangle->border(1, '#0085ff'); // Blue

                $this->marked = true; // Marcada como verdadeiro
            } else {
                $this->marked = false; // Marcada como falso
            }

            $this->percentCheck = $percentual;
            
            // print '<pre>';
            // print_r($total .': '. $black .'/'. $white .' - '. $percentual .' | check: ' . $this->percentCheck);
            // print '</pre>';

            // print '<pre>';
            // print_r($hexcolor);
            // print '</pre>';

            // print '<pre>';
            // print_r($rectangle);
            // print '</pre>';
        });

        return $this->marked;
    }

    public function text($x1, $y1, $x2, $y2)
    {
        // Cortar a imagem para salvar
        $width = $x2 - $x1;
        $height = $y2 - $y1;

        $this->imageOriginal->rectangle($x1, $y1, $x2, $y2, function($rectangle) {
            $rectangle->border(1, '#ffff00'); // Yellow
        });

        return $this->makeCrop($width, $height, $x1, $y1);
    }

    public function save()
    {
        $image = $this->imageOriginal->filename . '.debug.' . $this->imageOriginal->extension;
        // $imageInvert = $this->imageOriginal->filename . '.invert.debug.' . $this->imageOriginal->extension;

        // $this->imageInvert->save($imageInvert);
        $this->imageOriginal->save($image);
    }

    /**
     * Calculates angle between two points
     *
     * @param Object $a
     * @param Object $b
     * @return float
     */
    protected function anglePoints($a, $b)
    {
        $diffX = $b->x - $a->x;
        $diffY = $b->y - $a->y;

        return rad2deg(atan($diffY/$diffX));
    }

    /**
     * Calculates distance between two points
     *
     * @param Object $a
     * @param Object $b
     * @return float
     */
    protected function distancePoints($a, $b)
    {
        $diffX = $b->x - $a->x;
        $diffY = $b->y - $a->y;

        return sqrt(pow($diffX, 2) + pow($diffY, 2));
    }

    /**
     * Most point to the top/right
     *
     * @return Object
     */
    protected function topRight($near)
    {
        $first = new stdClass();
        $first->x = $near->x - 200;
        $first->y = $near->y - 100;

        $last = new stdClass();
        $last->x = $near->x + 100;
        $last->y = $near->y + 200;

        $point = new stdClass();
        $point->x = $first->x;
        $point->y = $last->y;

        // Add draw debug
        $this->imageOriginal->rectangle($first->x, $first->y, $last->x, $last->y, function($rectangle) {
            $rectangle->border(2, '#00CC00'); // Green debug
        });

        for ($y = $first->y; $y != $last->y; $y++) {
            for ($x = $first->x; $x != $last->x; $x++) {
                $color = $this->imageOriginal->pickColor($x, $y, 'array');

                if ($color[0] <= 5 && $color[1] <= 5 && $color[2] <= 5) {
                    if ($x >= $point->x) {
                        $point->x = $x;
                    }
                    if ($y <= $point->x) {
                        $point->y = $y;
                    }
                }
            }
        }

        // Debug draw
        $this->imageOriginal->pixel('#00CC00', $point->x, $point->y); // Green debug

        return $point;
    }

    /**
     * Most point to the bottom/left
     *
     * @return Object
     */
    protected function bottomLeft($near)
    {
        $first = new stdClass();
        $first->x = $near->x - 100;
        $first->y = $near->y - 200;

        $last = new stdClass();
        $last->x = $near->x + 200;
        $last->y = $near->y + 100;

        $point = new stdClass();
        $point->x = $last->x;
        $point->y = $first->y;

        // Add draw debug
        $this->imageOriginal->rectangle($first->x, $first->y, $last->x, $last->y, function($rectangle) {
            $rectangle->border(2, '#00CC00'); // Green debug
        });

        for ($y = $first->y; $y != $last->y; $y++) {
            for ($x = $first->x; $x != $last->x; $x++) {
                $color = $this->imageOriginal->pickColor($x, $y, 'array');

                if ($color[0] <= 5 && $color[1] <= 5 && $color[2] <= 5) {
                    if ($x <= $point->x) {
                        $point->x = $x;
                    }
                    if ($y >= $point->y) {
                        $point->y = $y;
                    }
                }
            }
        }

        // Debug draw
        $this->imageOriginal->pixel('#00CC00', $point->x, $point->y); // Green debug

        return $point;
    }

    /**
     * Rotate image
     *
     * @param float $degrees
     */
    protected function ajustRotate($degrees)
    {
        if ($degrees < 0 ) {
            $degrees = 360 + $degrees;
        }

        $originalWidth = $this->imageOriginal->width();
        $originalHeight = $this->imageOriginal->height();

        $this->imageOriginal->rotate($degrees);
        $this->imageOriginal->crop($originalWidth, $originalHeight, round(($this->imageOriginal->width() - $originalWidth) / 2), round(($this->imageOriginal->height() - $originalHeight) / 2));
    }

    /**
     * Increases or decreases image size
     *
     * @param float $percent
     */
    protected function ajustSize($percent)
    {
        $widthAjusted = $this->imageOriginal->width() + (($this->imageOriginal->width() * $percent) / 100);
        $heightAjust = $this->imageOriginal->height() + (($this->imageOriginal->height() * $percent) / 100);

        $this->imageOriginal->resize($widthAjusted, $heightAjust);
    }
}
