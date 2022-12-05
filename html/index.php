<?php

require_once ( __DIR__ . '/backup.config.php' );

/**
 * Return the error description of the uploaded file
 * 
 * @param string $path
 * 
 * @return string|false The function returns the error description or false on failure.
 */
function getUploadErrorDescription ($uploadErrorCode) {
    $phpFileUploadErrors = array(
        //0 => 'There is no error, the file uploaded with success',
        1 => 'The uploaded file exceeds the upload_max_filesize directive in php.ini',
        2 => 'The uploaded file exceeds the MAX_FILE_SIZE directive that was specified in the HTML form',
        3 => 'The uploaded file was only partially uploaded',
        4 => 'No file was uploaded',
        6 => 'Missing a temporary folder',
        7 => 'Failed to write file to disk.',
        8 => 'A PHP extension stopped the file upload.',
    );

    if (isset($uploadErrorCode[$phpFileUploadErrors])) {
        return $uploadErrorCode[$phpFileUploadErrors];
    }
    return false;
}

?><!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8"/>
    <title><?php echo $SBackup->getAdapterName(); ?> - Authentication</title>
</head>
<body>
    <div style="padding: 50px; text-align: center; max-width: 500px; margin: auto;">
        <h1 style="padding: 20px;">SBackup <small>Testing upload to Dropbox</small></h1>

<?php

if (isset($_POST["doupload"]) && $_POST["doupload"] == "YES") {
    
    // echo "<pre>";
    // print_r ($_FILES);
    // echo "</pre>";

    $filename = null;
    $filePath = null;
    if (isset($_FILES['file']) && isset($_FILES['file']['name'])) {
        $attach = $_FILES['file'];

        if (!empty($attach['name'])) {
            $errorCode = isset($attach["error"]) ? $attach["error"] : 0;
            if ($errorCode !== UPLOAD_ERR_OK) {
                echo "Error uploading file: " . getUploadErrorDescription($errorCode);
            } else {
                $filename = $attach['name'];
                $filePath = $attach['tmp_name'];
            }
        }
    }
    if (!empty($filePath)) {
        /**
         * @var genilto\sbackup\SBackup $SBackup
         */
        try {
            $uploadedFileName = $SBackup->upload($filePath, "/", $filename, false);
            echo "File <b>$uploadedFileName</b> uploaded to Dropbox!";
        } catch (Exception $e) {
            echo $e->getMessage();
        }
    } else {
        echo "File must be informed!";
    }
}

?>      <div style="padding: 20px;">
            <form name="dropbox-upload" action="" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="doupload" value="YES">
                File: <input name="file" type="file" value="" />
                <button type="submit">Upload</button>
            </form>
        </div>
    </div>
</body>
</html>