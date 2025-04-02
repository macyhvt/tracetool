<?php
// Register required libraries.
use  \Helper\FilesystemHelper;
use  \Helper\MediaHelper;
use  \Messager;
use  \Text;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN'); ?>
<?php /* Init vars */
$lang  = $this->get('language');
$view  = $this->__get('view');
$input = $view->get('input');
$user  = $view->get('user');

$pid   = $input->getInt('pid');
$fid   = base64_decode($input->getString('fid'));
?>
<?php /* Access check */
/*if (is_a(App::getAppUser(), ' \Entity\User')) :
	try
	{
		$formData = $user->__get('formData');
		$formData = (is_array($formData)) ? $formData : [];
	}
	catch (Exception $e)
	{
		$formData = null;
	}
endif;*/
?>
<?php /* Process form data */
?>
<?php /* Load view data */
$item  = $view->get('item');

// Block the attempt to open a non-existing part.
if (!is_a($item, ' \Entity\Part') || (is_a($item, ' \Entity\Part') && is_null($item->get('partID')))) :
    Messager::setMessage([
        'type' => 'notice',
        'text' => sprintf(Text::translate('COM_FTK_HINT_PART_HAVING_ID_X_NOT_FOUND_TEXT', $this->language), $item->get('partID'))
    ]);

    if (!headers_sent($filename, $linenum)) :
        header('Location: ' . View::getInstance('parts', ['language' => $lang])->getRoute());
		exit;
    endif;

    return false;
endif;
?>
<?php /* Display file */
$dirPath  = FilesystemHelper::fixPath(sprintf(implode(DIRECTORY_SEPARATOR, ['%s','%d']),
    MediaHelper::getMediafilesPathForPart($item->get('partID')),
	$pid
));
$filePath = sprintf('%s/%s', $dirPath, $fid);

// Ensure the requested file exists.
if (!file_exists($filePath)) :
	http_response_code('404');

	Messager::setMessage([
		'type' => 'error',
		'text' => Text::translate('COM_FTK_HINT_FILE_NOT_FOUND_TEXT', $lang)
	]);

	return false;
endif;

// Set appropriate response headers prior outputting the requested file.
header('Content-Description: Display image file');
header('Content-Type: ' . mime_content_type($filePath));
//header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');  // provides file as download
header('Expires: 0');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Cache-Control: post-check=0, pre-check=0', false);
header('Pragma: no-cache');
header('Content-Length: ' . filesize($filePath));

// Output the requested file.
if (false === readfile($filePath)) :
    http_response_code('500');

    Messager::setMessage([
        'type' => 'error',
        'text' => Text::translate('COM_FTK_ERROR_APPLICATION_READ_FILE_TEXT', $lang)
    ]);

    return false;
endif;

exit;
