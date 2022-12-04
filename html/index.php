<?php

require_once ( __DIR__ . '/backup.config.php' );

$filename = 'DJI_0053.DNG';

/**
 * @var genilto\sbackup\SBackup $SBackup
 */
try {
    $SBackup->upload(__DIR__ . "/toUpload/$filename", "/", $filename, false);
    echo "Success!";
} catch (Exception $e) {
    echo $e->getMessage();
}
