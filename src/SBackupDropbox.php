<?php

namespace genilto\sbackup\adapters;

use \Exception;
use \genilto\sbackup\UploaderInterface;
use \genilto\sbackup\store\DataStoreInterface;
use \genilto\sbackup\logger\SBLogger;
use \genilto\sbackup\SBackupException;

use \genilto\sbackup\adapters\models\DropboxTokenInfo;

use \Kunnu\Dropbox\Dropbox;
use \Kunnu\Dropbox\DropboxApp;
use \Kunnu\Dropbox\Authentication\DropboxAuthHelper;
use \Kunnu\Dropbox\DropboxFile;
use \Kunnu\Dropbox\Models\AccessToken;

class SBackupDropbox implements UploaderInterface {

    private const INFORMATION_INDEX = "dropbox_token_info";

    /**
     * @var string $clientId
     */
    private $clientId;
    /**
     * @var string $clientSecret
     */
    private $clientSecret;

    /**
     * @var ?DropboxTokenInfo $tokenInfo
     */
    private $tokenInfo;

    /**
     * @var Dropbox $dropbox;
     */
    private $dropbox;

    /**
     * @var DropboxAuthHelper $authHelper;
     */
    private $authHelper;
    
    /**
     * Logging adapter
     * 
     * @var SBLogger $logger
     */
    private $logger;

    /**
     * The data store for tokens
     * 
     * @var DataStoreInterface $dataStore
     */
    private $dataStore;

    /**
     * Creates the Instance
     * 
     * @param DataStoreInterface $dataStore
     * @param string $clientId
     * @param string $clientSecret
     */
    public function __construct(DataStoreInterface $dataStore, SBLogger $logger, string $clientId, string $clientSecret)
    {
        $this->dataStore = $dataStore;
        $this->logger = $logger;

        $this->clientId = $clientId;
        $this->clientSecret = $clientSecret;
        $this->tokenInfo = $this->getDropboxTokenInfo ();

        $this->initDropbox();
    }

    /**
     * Init the dropbox variables
     * 
     * @param bool Indicate if must get a refreshed token when current token is expired
     */
    private function initDropbox ($tokenMustBeValid = false) {
        $accessToken = $this->getToken($tokenMustBeValid);
        
        // Configure Dropbox Application
        $dropboxApp = new DropboxApp($this->clientId, $this->clientSecret, $accessToken);

        // Configure Dropbox service
        $this->dropbox = new Dropbox($dropboxApp);

        // DropboxAuthHelper
        $this->authHelper = $this->dropbox->getAuthHelper();
    }

    /**
     * Get a Valid Token
     * 
     * @param bool Indicate if must get a refreshed token when current token is expired
     * 
     * @return string token if exists and is valid or a refreshed token if expired
     */
    private function getToken($mustBeValid) {
        if (!$this->isAuthorized()) {
            return null;
        }
        if ($mustBeValid && $this->tokenInfo->isTokenExpired()) {
            $this->getAndSaveRefreshedAccessToken();
        }
        return $this->tokenInfo->getAccessToken()->getToken();
    }

    /**
     * Get new Access Token by using the refresh token
     */
    private function getAndSaveRefreshedAccessToken() {

        $this->logger->logInfo ('getAndSaveRefreshedAccessToken', "Getting refreshed access token");

        $accessToken = $this->tokenInfo->getAccessToken();

        // Configure Dropbox Application
        $dropboxApp = new DropboxApp($this->clientId, $this->clientSecret, $accessToken->getToken());

        // Configure Dropbox service
        $dropbox = new Dropbox($dropboxApp);

        // DropboxAuthHelper
        $authHelper = $dropbox->getAuthHelper();

        try {
            // Refreshing access token
            $newAccessToken = $authHelper->getRefreshedAccessToken($accessToken);
            $this->saveDropboxTokenInfo($newAccessToken);
        } catch (Exception $e) {
            $errorMessage = "Error getting refreshed token. Check the logs for more details.";
            $this->logger->logError ('getAndSaveRefreshedAccessToken', "Error getting refreshed token: " . $e->getMessage());
            
            // It could retry getting the refresh token
            throw new SBackupException($errorMessage, true);
        }
    }

    public function getAdapterName() {
        return "Dropbox";
    }

    /**
     * Print the date at a specific format
     * @param int $timestamp
     */
    private function printDate ($timestamp) {
        echo date( 'l jS \of F Y h:i:s A', $timestamp );
    }

    public function authorizationFlow() {
        
        // Process the code sent by the form
        $this->processReturningCode();

        if ($this->isAuthorized()) {
            ?>
            
            <div style="padding: 20px; color: #008000;">
                SBackup is Authorized to Dropbox!<br><br>
                Token created in: <b><?php $this->printDate($this->tokenInfo->getCreationTime());  ?></b><br>
                Expiration date is: <b><?php $this->printDate($this->tokenInfo->getExpirationTime());  ?></b><br><br>
                Token is: <?php
                    if ($this->tokenInfo->isTokenExpired()) {
                        echo '<b color="red">Expired!</b>';
                    } else {
                        echo '<b>Valid!</b>';
                    }
                ?>
            </div>
            <div style="padding: 20px;">
                <form name="dropbox-auth" action="" method="POST">
                    <input type="hidden" name="cleandropboxauth" value="YES">
                    <button type="submit">Unauthorize</button>
                </form>
            </div>
            
            <?php
        } else  {
            $this->displayAuthForm ();
        }
    }

    /**
     * Displays the authentication form
     */
    private function displayAuthForm () {

        // Additional user provided parameters to pass in the request
        $params = [];
                
        // Url State - Additional User provided state data
        $urlState = null;

        // Token Access Type
        $tokenAccessType = "offline";

        // Fetch the Authorization/Login URL
        $authUrl = $this->authHelper->getAuthUrl(null, $params, $urlState, $tokenAccessType);

        ?>
            <div style="padding: 20px;"><a href="<?php echo $authUrl; ?>" target="_blank">Get Dropbox Access Code</a></div>

            <form name="dropbox-auth" action="" method="POST">
                <div>
                    <span>Dropbox Access Code: </span>
                    <input type="text" name="dropboxcode" required style="width: 200px;">
                    <button type="submit">Confirm</button>
                </div>
            </form>
            <small style="display: block; padding: 50px;">
                SBackup will have full read and write access to your entire Dropbox. <br>
                You can specify your backup destination wherever you want, just be aware that ANY files or folders inside of your 
                Dropbox can be overridden or deleted by SBackup.
            </small>
    <?php
    }

    /**
     * Verify and process the returning code from dropbox
     */
    private function processReturningCode () {
        if ($this->isAuthorized()) {
            if (isset($_POST['cleandropboxauth']) && $_POST['cleandropboxauth'] == "YES") {
                
                $this->logger->logInfo ('processReturningCode', "Revoking Access Token...");

                $this->dataStore->clear(self::INFORMATION_INDEX);
                $this->tokenInfo = null;
                try {
                    $this->authHelper->revokeAccessToken();
                } catch (Exception $e) {
                    $this->logger->logError ('processReturningCode', "Error revoking Access Token: " . $e->getMessage());
                    ?>
                    <div style="color: red; border: 1px solid red;">
                        <b>Access token was erased but there was an error when revoking the access token in Dropbox. Check the logs for more details.</b>
                    </div>
                    <?php
                }
            }
            return;
        }
        if (isset($_POST['dropboxcode'])) {
            $dropboxCode = htmlspecialchars( $_POST['dropboxcode'] );
            
            if (empty($dropboxCode)) {
                $this->logger->logError ('processReturningCode', "Dropbox code not informed!");
                return;
            }

            // Fetch the AccessToken
            try {
                $accessToken = $this->authHelper->getAccessToken($dropboxCode);
                $this->saveDropboxTokenInfo($accessToken);
            } catch (Exception $e) {
                $this->logger->logError ('processReturningCode', "Error getting new Access Token from dropbox code: " . $e->getMessage());
                ?>
                <div style="color: red; border: 1px solid red;">
                    <b>Error when getting the access token.</b> Check the logs for more details.
                </div>
                <?php
            }
        }
    }

    /**
     * Save the access token 
     * 
     * @param AccessToken $accessToken
     */
    private function saveDropboxTokenInfo(AccessToken $accessToken) {
        // Create the Token Infor Instance
        $tokenInfo = new DropboxTokenInfo($accessToken);

        // Store the token
        $this->dataStore->set(self::INFORMATION_INDEX, $tokenInfo);
        $this->tokenInfo = $tokenInfo;

        $this->logger->logInfo ('saveDropboxTokenInfo', "New Access Token Successfully saved", ["expirationTime" => $this->tokenInfo->getExpirationTime()]);
    }

    /**
     * Get the saved access token 
     * 
     * @return DropboxTokenInfo
     */
    private function getDropboxTokenInfo () {
        return $this->dataStore->get(self::INFORMATION_INDEX);
    }

    /**
     * Get the saved access token
     * 
     * @return boolean
     */
    public function isAuthorized() {
        return !empty($this->tokenInfo);
    }

    /**
     * Validate if Dropbox is connected and if the token is expired
     * When token is expired, it will refresh it with a new one
     */
    private function validateAuthorization () {
        if (!$this->isAuthorized()) {
            throw new SBackupException( "You need to Authorize SBackup to Dropbox first" );
        }
        if ($this->tokenInfo->isTokenExpired()) {
            $this->initDropbox(true);
        }
    }
    
    public function upload( string $filesrc, string $folderId, string $filename ) {
        $this->validateAuthorization ();
        
        $dropboxFile = null;
        try {
            $mode = DropboxFile::MODE_READ;
            $dropboxFile = DropboxFile::createByPath($filesrc, $mode);
        } catch (Exception $e) {
            throw new SBackupException($e->getMessage());
        }

        try {
            $file = $this->dropbox->upload($dropboxFile, $folderId.$filename, ['autorename' => true]);
            return $file->getName();
        } catch (Exception $e) {
            // In this case, SBackup could try again the upload
            throw new SBackupException($e->getMessage(), true);
        }
    }
}

?>