<?php

namespace RegularLabs\Scoped\Intervention\Image\Filters;

use RegularLabs\Scoped\Intervention\Image\Image;
interface FilterInterface
{
    /**
     * Applies filter to given image
     *
     * @param Image $image
     *
     * @return Image
     */
    public function applyFilter(Image $image);
}
