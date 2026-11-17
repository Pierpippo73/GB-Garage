<?php

namespace RegularLabs\Scoped\Intervention\Image\Gd\Commands;

use RegularLabs\Scoped\Intervention\Image\Commands\AbstractCommand;
use RegularLabs\Scoped\Intervention\Image\Image;
class BackupCommand extends AbstractCommand
{
    /**
     * Saves a backups of current state of image core
     *
     * @param Image $image
     *
     * @return boolean
     */
    public function execute($image)
    {
        $backupName = $this->argument(0)->value();
        // clone current image resource
        $clone = clone $image;
        $image->setBackup($clone->getCore(), $backupName);
        return \true;
    }
}
