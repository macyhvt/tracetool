<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\StringHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Helper\DatabaseHelper;
use Nematrack\Model;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Get the file ID from the POST data
 $fileId = isset($_POST['fileId']) ? (int)$_POST['fileId'] : 0; // Validate as integer

// Security: Check if the fileId is valid and not zero
if ($fileId <= 0) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid file ID.']);
    exit;
}
    $filePath = Model::getInstance('article')->checkDropBoxVideo($fileId);

// Implement your file deletion logic and check for any errors
if (!empty($filePath)) {

            try {
                $fDelete = Model::getInstance('article')->deleteDropBoxVideo($fileId);
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'message' => 'File deleted successfully!']);
                exit;
            } catch (Exception $e) {
                //Log::addLogger(['text_file' => 'com_yourcomponent.error.php'], Log::ERROR, 'com_yourcomponent');
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Database error during deletion: ' . $e->getMessage()]);
                exit;
            }

    }

?>