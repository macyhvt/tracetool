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
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang   = $this->get('language');
$view   = $this->__get('view');
$input  = $view->get('input');
$return = $view->getReturnPage();	// Browser back-link required for back-button.
$model  = $view->get('model');
$user   = $view->get('user');

$layout = $input->getCmd('layout');
$task   = $input->getWord('task');

 $aid    = $input->post->getString('artid') ?? ($input->getString('artid') ?? null);	// search term
 $proid    = $input->post->getString('pid') ?? ($input->getString('pid') ?? null);	// search term
$file_type = 'workInstructions';


//echo "<pre>";print_r($this->orgabbr);
?>
<?php /* Access check */
// TODO
?>


    <style>




        /* Custom CSS for modal body and footer */
        .modal-body-custom {
            padding: 20px; /* Adjust as needed */
        }

        .modal-footer-custom {
            padding: 15px; /* Adjust as needed */
            text-align: right; /* or left, center, etc. */
        }
        .dropFiles{
            width: 200px;
            height: 155px;
            border: 1px solid #000;
            color: #000;
            padding: 0 5px;
            display: flex; /* Use flexbox */
            justify-content: center; /* Center horizontally */
            align-items: center; /* Center vertically */
            text-align: center; /* For older browsers */
            overflow: hidden; /* Hide overflowing content */
            background-color: #b3cae9;
        }
        .file-list {
            list-style: none; /* Remove default list styling */
            padding-left: 0; /* Remove default padding */
        }

        .file-items{
            margin-bottom: 5px; /* Add spacing between file list items */
            display: grid; /* Use flexbox for layout */
            align-items: center; /* Vertically align items */

            margin: 10px; /* Add spacing around each file item */
            text-align: center; /* Center the content horizontally */
        }
        .file-item {
            margin-bottom: 5px; /* Add spacing between file list items */
            display: grid; /* Use flexbox for layout */
            align-items: center; /* Vertically align items */
        }

        .file-item i {`
            margin-right: 5px; /* Add spacing between icon and filename */
        }

        .file-item a {
            text-decoration: none; /* Remove underline from links */
        }


        .file-container {
            display: flex; /* Arrange items in a row */
            flex-wrap: wrap; /* Allow items to wrap to the next line if needed */
            justify-content: flex-start; /* Align items to the start of the container */
        }

        .file-item {
            width: 200px; /* Set a fixed width for each file item */
            margin: 10px; /* Add spacing around each file item */
            text-align: center; /* Center the content horizontally */
        }

        .file-item a {
            display: block; /* Make the link take up the full width */
            word-break: break-word; /* Break long words */
            margin-bottom: 5px; /* Add spacing between the link and the button */
            padding: 10px; /* Add padding to the link */
            border: 1px solid #ccc; /* Add a border */
            text-decoration: none; /* Remove underline */
            color: #333; /* Set text color */
        }

        .file-item button {
            display: block; /* Make the button take up the full width */
            width: 100%; /* Button takes the width of item*/
            background-color: #830707;
            color: #dbdbdb;
        }
    </style>
    <div class="row">
        <div class="col">
            <h1 class="h3 viewTitle d-inline-block my-0 mr-3">
                <?php echo Text::translate(mb_strtoupper(sprintf('COM_FTK_LABEL_%s_TEXT', $view->get('name'))), $lang); ?>
            </h1>
            <!-- Add button and Modal -->
            <button type="button" class="btn btn-primary" data-toggle="modal" data-target="#uploadModal">
                Add work instruction
            </button>

            <button type="button" class="btn btn-info" data-toggle="modal" data-target="#uploadEVModal">
                Add embedded video
            </button>

            <!-- Modal For the pdf file-->
            <div class="modal fade" id="uploadModal" tabindex="-1" role="dialog" aria-labelledby="uploadModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="uploadModalLabel">Upload File</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <div class="modal-body-custom"> <!-- Replaced modal-body -->
                            <form id="uploadForm" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="fileInput">Choose File</label>
                                    <input required type="file" class="form-control-file" id="fileInput" name="file">
                                    <input type="hidden" name="artid" value="<?php echo $aid; ?>">
                                    <input type="hidden" name="pid" value="<?php echo $proid; ?>">
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer-custom"> <!-- Replaced modal-footer -->
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="uploadButton">Upload</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Modal -->

            <!-- Modal for the YouTube link parsing-->
            <div class="modal fade" id="uploadEVModal" tabindex="-1" role="dialog" aria-labelledby="uploadEVModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="uploadEVModalLabel">Paste Down the embedded video link</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">×</span>
                            </button>
                        </div>
                        <div class="modal-body-custom"> <!-- Replaced modal-body -->
                            <form id="uploadEVForm" enctype="multipart/form-data">
                                <div class="form-group">
                                    <label for="videoUrl">YouTube Video URL:</label>
                                    <input type="url" class="form-control" id="videoUrl" name="videoUrl" required placeholder="https://www.youtube.com/watch?v=dQw4w9WgXcQ">
                                    <small class="form-text text-muted">Paste the embed code from YouTube.</small>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer-custom"> <!-- Replaced modal-footer -->
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="uploadEVButton">Add</button>
                        </div>
                    </div>
                </div>
            </div>
            <!-- End Modal -->
        </div>
    </div>

    <hr>

    <!-- Display Uploaded Files -->
    <h2> work instructions</h2>
    <div id="fileList">
        <?php
        //$view->saveProstat($aid,$proid, $said );
        $getAlreadyUploadedFiles = Model::getInstance('article')->getDropBoxFiles($aid, $proid, $file_type);
        //print_r($getAlreadyUploadedFiles);
        if (!empty($getAlreadyUploadedFiles)) {
            echo '<div class="file-container">'; // Use a div for the container
            foreach ($getAlreadyUploadedFiles as $file) {
                // Adjust these keys to match the actual keys in your $file object/array
                $fileName = isset($file['file_name']) ? htmlspecialchars($file['file_name']) : '';
                $filePath = isset($file['file_path']) ? htmlspecialchars($file['file_path']) : '';
                $fileType = isset($file['file_type']) ? htmlspecialchars($file['file_type']) : '';
                $fileId   = isset($file['drID']) ? (int)$file['drID'] : 0;

                // Determine the icon based on file type
                $iconClass = 'far fa-file'; // Default icon

                echo '<div class="file-item">'; // Use a div for each file
                echo '<a href="' . $filePath . '" target="_blank" class="dropFiles" rel="noopener noreferrer">' . $fileName . '</a>';
                echo ' <button class="delete-file" data-file-id="' . $fileId . '"><i class="fas fa-trash-alt"></i> Delete</button>';
                echo '</div>'; // End the file item
            }
            echo '</div>'; // End the file container
        } else {
            echo '<p>No files available.</p>';
        }
        ?>
    </div>

    <hr>
    <!-- Display parsed YouTube videos -->
    <h2> YouTube work Instructions</h2>
    <div id="videoList">
        <?php
        //$view->saveProstat($aid,$proid, $said );
        $getAlreadyUploadedVideos  = Model::getInstance('article')->getDropBoxVideos($aid, $proid, $file_type);
        //print_r($getAlreadyUploadedFiles);
        if (!empty($getAlreadyUploadedVideos )) {
            echo '<div class="file-container">'; // Use a div for the container
            foreach ($getAlreadyUploadedVideos as $video_data) {
                // Adjust these keys to match the actual keys in your $file object/array
                $youtubeId = isset($video_data['embed_link']) ? htmlspecialchars($video_data['embed_link']) : '';
                $videoRecordId = isset($video_data['evID']) ? (int)$video_data['evID'] : 0;


        if (!empty($youtubeId) && $videoRecordId > 0) {
        $safeYoutubeId = htmlspecialchars($youtubeId, ENT_QUOTES, 'UTF-8');
        $embedUrl = "https://www.youtube.com/embed/" . $safeYoutubeId;
        // Create a unique ID for the wrapper element for easier JS targeting
        $wrapperItemId = 'video-item-' . $videoRecordId;

        // --- Start file-item wrapper for this video ---
        echo '<div class="file-items" id="' . htmlspecialchars($wrapperItemId) . '">';

            // --- Responsive Video Container ---
            echo '<div class="video-container">';
                echo '<iframe ';
                // Remove fixed width/height, let CSS handle it
                // width="300" height="280"
                echo ' src="' . $embedUrl . '" ';
                echo ' title="YouTube video player" ';
                echo ' frameborder="0" ';
                echo ' allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" ';
                echo ' referrerpolicy="strict-origin-when-cross-origin" ';
                echo ' allowfullscreen ';
                echo ' loading="lazy"> '; // Added lazy loading
                echo '</iframe>';
                echo '</div>'; // End video-container

            // --- Delete Button (directly below video container) ---
            echo '<button type="button" ';
            // Use consistent button classes, maybe match file delete button style?
            echo ' class="btn btn-danger btn-sm delete-youtube-video-btn" ';
            // *** IMPORTANT: Use the unique video record ID (evID) for deletion ***
            echo ' data-file-id="' . $videoRecordId . '" ';
            // Include other data if needed by JS/Backend (though fileId is usually enough for delete)
            echo ' data-artid="' . (int)$aid . '" ';
            echo ' data-procid="' . (int)$proid . '" ';
            echo ' data-fileinfotype="' . htmlspecialchars($file_type, ENT_QUOTES, 'UTF-8') . '" ';
            echo ' data-videoid="' . $safeYoutubeId . '" '; // YouTube ID for confirmation msg
            echo ' data-wrapper-id="' . htmlspecialchars($wrapperItemId) . '" '; // Pass the wrapper ID to JS
            // Style to make it full width like the file delete button
            echo ' style="width: 100%; margin-top: 5px; background-color: #830707; color: #dbdbdb;" >';
            echo '<i class="fas fa-trash-alt"></i> Delete'; // Added icon like file delete
            echo '</button>';

            echo '</div>'; // --- End file-item wrapper ---

        } // End if !empty($youtubeId)

        } // End foreach

        echo '</div>'; // End file-container

    } else {
    echo '<p>No embedded videos available.</p>';
    }
    ?>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>

    <script>
        $(document).ready(function() {
            // Handle file upload
            $('#uploadButton').click(function() {
                var formData = new FormData($('#uploadForm')[0]);

                formData.append('artid', "<?php echo $aid; ?>");
                formData.append('pid', "<?php echo $proid; ?>");
                formData.append('fileinfo', "<?php echo $file_type; ?>");
                $.ajax({
                    url: 'index.php?hl=<?php echo $lang; ?>&view=<?php echo $view->get('name'); ?>&layout=upwi', // Replace with your actual URL
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(response) {
                        if (response.success) {
                            $('#uploadModal').modal('hide'); // Close the modal
                            $('#uploadForm')[0].reset(); // Clear the form
                            //alert('File uploaded successfully!');
                            window.location.reload();
                        } else {
                            alert('Error uploading file: ' + response.message);
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("AJAX Error:", status, error);
                        alert('AJAX Error: ' + error);
                    }
                });
            });

            $('#uploadEVButton').click(function() {
                var formData = new FormData($('#uploadEVForm')[0]);

                formData.append('artid', "<?php echo $aid; ?>");
                formData.append('pid', "<?php echo $proid; ?>");
                formData.append('fileinfo', "<?php echo $file_type; ?>");
                $.ajax({
                    url: 'index.php?hl=<?php echo $lang; ?>&view=<?php echo $view->get('name'); ?>&layout=embedvideodrop', // Replace with your actual URL
                    type: 'POST',
                    data: formData,
                    contentType: false,
                    processData: false,
                    success: function(response) {
                        console.log('AJAX Success - Raw Response:', response); // DEBUG: Log the raw response

                        // Check if the response is an object and has the 'success' property
                        if (response && typeof response === 'object' && response.hasOwnProperty('success')) {
                            if (response.success) {
                                // Success Case
                                console.log('Success:', response.message);
                                $('#uploadEVModal').modal('hide'); // Close the modal
                                $('#uploadEVForm')[0].reset(); // Clear the form
                                //alert('Link inserted successfully!'); // Give success feedback
                                window.location.reload(); // Reload page
                            } else {
                                // Failure Case (success: false was explicitly returned by PHP)
                                console.error('Server reported failure:', response.message);
                                alert('Error saving link: ' + response.message);
                            }
                        } else {
                            // Response was not the expected JSON object format
                            console.error('Invalid response format received:', response);
                            alert('Error: Received an unexpected response from the server. Please check console.');
                        }
                    },
                    error: function(xhr, status, error) {
                        // Network error, server error (500, 404 etc.), or JSON parsing error
                        console.error("AJAX Error - Status:", status);
                        console.error("AJAX Error - Error:", error);
                        console.error("AJAX Error - Response Text:", xhr.responseText); // See the raw server output
                        alert('AJAX Communication Error: ' + error + '. Please check console for details.');
                    }
                });
            });

            $('.delete-file').click(function() {
                var fileId = $(this).data('file-id');
                var listItem = $(this).closest('.file-item'); // Store the list item

                if (confirm('Are you sure you want to delete this file?')) {
                    $.ajax({
                        url: 'index.php?hl=<?php echo $lang; ?>&view=<?php echo $view->get('name'); ?>&layout=delete_dropbox', // Replace with your actual URL
                        type: 'POST',
                        data: { fileId: fileId },
                        dataType: 'json', // Expect a JSON response
                        success: function(response) {
                            if (response.success) {
                                //alert('File deleted successfully!');
                                listItem.remove(); // Remove the list item from the DOM
                            } else {
                                alert('Error deleting file: ' + response.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error("AJAX Error:", status, error);
                            alert('AJAX Error: ' + error);
                        }
                    });
                }
            });

            $('.delete-youtube-video-btn').click(function() {
                var fileIds = $(this).data('file-id');
                var listItem = $(this).closest('.file-items'); // Store the list item

                if (confirm('Are you sure you want to delete this file?')) {
                    $.ajax({
                        url: 'index.php?hl=<?php echo $lang; ?>&view=<?php echo $view->get('name'); ?>&layout=delete_dropbox_video', // Replace with your actual URL
                        type: 'POST',
                        data: { fileId: fileIds },
                        dataType: 'json', // Expect a JSON response
                        success: function(response) {
                            if (response.success) {
                                //alert('File deleted successfully!');
                                listItem.remove(); // Remove the list item from the DOM
                            } else {
                                alert('Error deleting file: ' + response.message);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error("AJAX Error:", status, error);
                            alert('AJAX Error: ' + error);
                        }
                    });
                }
            });
        });
    </script>


<?php

