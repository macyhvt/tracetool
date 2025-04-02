<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Helper\LayoutHelper;
use Nematrack\Helper\StringHelper;
use Nematrack\Helper\UriHelper;
use Nematrack\Model;
use Nematrack\Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

// Define upload directory (relative to your Joomla root) - MAKE SURE THIS DIRECTORY EXISTS AND IS WRITABLE
$baseUploadDir = JPATH_ROOT . '/assets/dropbox/';

// Error handling
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // Retrieve the Article ID and Process ID
    $artID = isset($_POST['artid']) ? (int)$_POST['artid'] : 0;
    $procID = isset($_POST['pid']) ? (int)$_POST['pid'] : 0;
    $fileInfoType = isset($_POST['fileinfo']) ? $_POST['fileinfo'] : 0;

    // Build the specific upload directory path
     $artDir = $baseUploadDir . $artID . '/';
     $procDir = $artDir . $procID . '/';
    $dbDirPath = '/assets/dropbox/'.$artID. '/'.$procID. '/';

    // **Directory Creation Logic - Moved to the beginning and checking for existing errors**
    if (empty($errors) && !is_dir($artDir)) { // Check for errors before creating
        if (!mkdir($artDir, 0755, true)) {
            $errors[] = 'Failed to create article directory: ' . $artDir;
        }
    }

    if (empty($errors) && !is_dir($procDir)) { // Check for errors before creating
        if (!mkdir($procDir, 0755, true)) {
            $errors[] = 'Failed to create process directory: ' . $procDir;
        }
    }

    // Check if file was uploaded
    if (empty($errors) && isset($_FILES['file'])) {
        $fileTmpPath = $_FILES['file']['tmp_name'];
        $fileName = $_FILES['file']['name'];
        $fileType = $_FILES['file']['type']; // MIME type
        $fileNameCmps = explode(".", $fileName);
        $fileExtension = strtolower(end($fileNameCmps));


        // Sanitize the file name (remove potentially harmful characters)
        $newFileName = round(microtime(true)) . '.' . $fileExtension;  // Unique name

         //$destFilePath = $procDir . $newFileName;;
        // Validate file type (example: allow only images or PDFs)
        $allowedFileExtensions = array('pdf'); // More restrictive

        if (in_array($fileExtension, $allowedFileExtensions)) {
            // Validate file size (example: limit to 2MB)

                // Create the full path to save the file
                $destFilePath = $procDir . $newFileName;//exit();
                $fileInfo = $fileInfoType;
                // Move the uploaded file to the destination directory
                if (move_uploaded_file($fileTmpPath, $destFilePath)) {
                    // Store file information in database (example)
                    // IMPORTANT:  Use prepared statements to prevent SQL injection!

                    $relativeFilePath = $dbDirPath.$newFileName;

                    try {
                        $measuredData = Model::getInstance('article')->uploadDropBoxFiles($artID, $procID, $fileInfo, $fileName, $relativeFilePath);
                    } catch (Exception $e) {
                        $errors[] = "Database error: " . $e->getMessage();
                        // Optionally, delete the uploaded file if the database insertion fails:
                        unlink($destFilePath);
                    }

                    $responseData = [
                        'success' => true,
                        'message' => 'File uploaded successfully!',
                    ];
                } else {
                    $errors[] = 'Failed to move uploaded file.';
                }

        } else {
            $errors[] = 'Invalid file type. Allowed types: jpg, jpeg, png, gif, pdf.';
        }
    } else {
        $errors[] = 'Please select a file to upload.';
    }
} else {
    $errors[] = 'Invalid request.';
}

// Prepare JSON response
if (!empty($errors)) {
    $responseData = [
        'success' => false,
        'message' => implode('<br>', $errors),
    ];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($responseData);
exit();
?>