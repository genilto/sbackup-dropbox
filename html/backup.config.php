<?php

require_once ( __DIR__ . '/../vendor/autoload.php' );

use \genilto\sbackup\SBackup;
use \genilto\sbackup\adapters\SBackupDropbox;
use \genilto\sbackup\store\FileDataStore;
use \genilto\sbackup\logger\SBLogger;

use \Analog\Analog;
use \Analog\Logger;

// Defines the default timezone
Analog::$timezone = 'America/Sao_Paulo';
date_default_timezone_set(Analog::$timezone);

/**
 * Creates the default logger
 * 
 * @return SBLogger
 */
if (!function_exists('createDefaultLogger')) {
    function createDefaultSBackupLogger () {
        // Creates the Default Logger
        $currentDate = date("Y-m-d");
        $logger = new Logger();
        $logger->handler (__DIR__ . "/logs/$currentDate-sbackup.log");
        return new SBLogger($logger, 3); // 3 - Full Logging
    }
}

define("DROPBOX_CLIENT_ID", getenv("DROPBOX_CLIENT_ID"));
define("DROPBOX_CLIENT_SECRET", getenv("DROPBOX_CLIENT_SECRET"));

$SBDataStore = new FileDataStore(__DIR__ . "/config/dropbox-config");
$SBLogger = createDefaultSBackupLogger();
$SBUploader = new SBackupDropbox($SBDataStore, $SBLogger, DROPBOX_CLIENT_ID, DROPBOX_CLIENT_SECRET);
$SBackup = new SBackup($SBUploader, $SBLogger);
