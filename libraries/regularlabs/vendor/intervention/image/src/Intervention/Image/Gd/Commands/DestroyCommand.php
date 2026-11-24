<?php

namespace RegularLabs\Scoped\Intervention\Image\Gd\Commands;

use RegularLabs\Scoped\Intervention\Image\Commands\AbstractCommand;
use RegularLabs\Scoped\Intervention\Image\Image;
class DestroyCommand extends AbstractCommand
{
    /**
     * Destroys current image core and frees up memory
     *
     * @param Image $image
     *
     * @return boolean
     */
    public function execute($image)
    {
        // destroy image core
        //        imagedestroy($image->getCore());
        // destroy backups
        //        foreach ($image->getBackups() as $backup) {
        //            imagedestroy($backup);
        //        }
        return \true;
    }
}
