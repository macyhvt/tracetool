<?php
// Register required libraries.
use Joomla\Registry\Registry;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use  \Helper\LayoutHelper;
use  \Helper\StringHelper;
use  \Helper\UriHelper;
use  \Model;
use  \Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

// Define upload directory (relative to your Joomla root) - MAKE SURE THIS DIRECTORY EXISTS AND IS WRITABLE
    function getYouTubeVideoId($url) {
        $videoId = '';
        parse_str(parse_url($url, PHP_URL_QUERY), $params);
        if (isset($params['v'])) {
            $videoId = $params['v'];
        }
        return $videoId;
    }

    $responseData = ['success' => false, 'message' => 'Invalid request.']; // Default response

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {

        // Get form data
        $artID = isset($_POST['artid']) ? (int)$_POST['artid'] : 0;
        $procID = isset($_POST['pid']) ? (int)$_POST['pid'] : 0;
        $fileInfoType = isset($_POST['fileinfo']) ? $_POST['fileinfo'] : 0;

        $videoUrl = isset($_POST['videoUrl']) ? trim($_POST['videoUrl']) : '';
         // Extract video ID
        $youtubeId = getYouTubeVideoId($videoUrl);

        // Validate video ID
        if (empty($youtubeId)) {
            $responseData = ['success' => false, 'message' => 'Invalid or missing YouTube URL.'];
        } elseif ($artID <= 0 || $procID <= 0 || empty($fileInfoType)) {
            $responseData = ['success' => false, 'message' => 'Missing required article, process, or file type information.'];
        } else {
            try {
                // Make sure the model method exists and works as expected
                $model = Model::getInstance('article');
                if (method_exists($model, 'insetDropBoxVideo')) {
                    $insertResult = $model->insetDropBoxVideo($artID, $procID, $fileInfoType, $youtubeId);

                    if ($insertResult) { // Assuming the method returns true on success
                        $responseData = [
                            'success' => true,
                            'message' => 'Link Inserted Successfully!'
                        ];
                    } else {
                        // Check if the model method provides more specific error info
                        $errorMessage = 'Failed to insert link into database. Model returned false.';
                        // Example: if ($model->getError()) { $errorMessage = $model->getError(); }
                        $responseData = [
                            'success' => false,
                            'message' => $errorMessage
                        ];
                    }
                } else {
                    $responseData = [
                        'success' => false,
                        'message' => 'Error: Model method insetDropBoxVideo not found.'
                    ];
                }

            } catch (Exception $e) {
                // Log the detailed error for debugging on the server side if possible
                // error_log("Error adding video: " . $e->getMessage());
                $responseData = [
                    'success' => false,
                    // Be cautious about sending detailed exception messages to the client
                    'message' => "A server error occurred while adding the video. Please contact support if the problem persists."
                    // 'message' => "Error adding video: " . $e->getMessage() // Use for debugging only
                ];
            }
        }
    } else {
        $responseData = ['success' => false, 'message' => 'Invalid request method. Only POST is allowed.'];
    }

// --- IMPORTANT: Send the JSON response ---
header('Content-Type: application/json; charset=utf-8'); // Set the correct header
echo json_encode($responseData);                          // Encode the array to JSON and output it
exit();
?>