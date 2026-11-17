<?php

namespace RegularLabs\Scoped\Intervention\Image\Gd\Commands;

use RegularLabs\Scoped\Intervention\Image\Commands\AbstractCommand;
use RegularLabs\Scoped\Intervention\Image\Gd\Color;
use RegularLabs\Scoped\Intervention\Image\Image;
class PixelCommand extends AbstractCommand
{
    /**
     * Draws one pixel to a given image
     *
     * @param Image $image
     *
     * @return boolean
     */
    public function execute($image)
    {
        $color = $this->argument(0)->required()->value();
        $color = new Color($color);
        $x = $this->argument(1)->type('digit')->required()->value();
        $y = $this->argument(2)->type('digit')->required()->value();
        return imagesetpixel($image->getCore(), $x, $y, $color->getInt());
    }
}
