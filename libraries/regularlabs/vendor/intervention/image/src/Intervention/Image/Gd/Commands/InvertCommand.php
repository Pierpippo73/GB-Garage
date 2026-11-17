<?php

namespace RegularLabs\Scoped\Intervention\Image\Gd\Commands;

use RegularLabs\Scoped\Intervention\Image\Commands\AbstractCommand;
use RegularLabs\Scoped\Intervention\Image\Image;
class InvertCommand extends AbstractCommand
{
    /**
     * Inverts colors of an image
     *
     * @param Image $image
     *
     * @return boolean
     */
    public function execute($image)
    {
        return imagefilter($image->getCore(), \IMG_FILTER_NEGATE);
    }
}
