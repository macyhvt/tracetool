<?php
/* define application namespace */
namespace  \Service;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use finfo;
use Joomla\Utilities\ArrayHelper;
use JsonException;
use  \App;
use  \Helper\ImageHelper;
use  \Helper\LayoutHelper;
use  \Model;
use  \Service;
use  \Text;
use RuntimeException;
use stdclass;
use voku\helper\HtmlMin;

/**
 * Class description
 */
class Provide extends Service
{
	/**
	 * Class construct
	 *
	 * @param   array  $options  An array of instantiation options.
	 *
	 * @return  void
	 *
	 * @since   0.1
	 */
	public function __construct(array $options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($options);

		// Assign ref to HTTP Request object.
		$this->input = App::getInput();
	}

	public function provide($what = null, $format = null)
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$input    = App::getInput();
		$response = null;

		if (is_null($what))
		{
			$response = $what;
		}

		switch ($what)
		{
			/*case 'articles' :
				$response = Model::getInstance('articles', ['language' => $this->language])->getArticlesByLanguageOrName();	// get available articles for the dropdown list
			break;*/

			case 'articleProcessTree' :
//				$articleName = $this->input->get->getCmd('article') ?? null;
				$layoutFile  = $this->input->get->getCmd('layout') ?? 'process_tree';
				$layoutHtml  = LayoutHelper::render('forms.article.' . $layoutFile, new stdclass, ['language' => $this->language]);

				// Mandatory response header (For some reason it works in this case but not in general (line 88))
				if ($format === 'json')
				{
					// Minify output.
					if (class_exists('voku\helper\HtmlMin'))
					{
						$layoutHtml = (new HtmlMin())->minify($layoutHtml);
					}

					// Header is required for receiving script to properly decode base64-encoded content
					header("Content-type: application/json; charset=utf-8");
				}

				$response = ['html' => base64_encode($layoutHtml)];
			break;

			/*case 'layout' :
				$articleName = $this->input->get->getCmd('article') ?? null;
				$layoutFile  = $this->input->get->getCmd('layout')  ?? null;
				$layoutHtml  = LayoutHelper::render('forms.' . $layoutFile, new stdclass, ['language' => $this->language]);
				$response    = ['html' => base64_encode($layoutHtml)];
			break;*/

			/*case 'parts' :
				$response = Model::getInstance('parts', ['language' => $this->language])->getPartsByLanguageOrCode($this->language, null);
			break;*/

			case 'image.stream' :
				$response = [
					'REQUEST' => $this->input->request->getArray(),
					'FILES'   => $this->input->files->getArray()
				];
				
//				$post  = $this->input->post->getArray();
				$files = $this->input->files->getArray();

				if (isset($files) && is_countable($files))
				{
					$tmpFile = ArrayHelper::getValue($files, 'picture');

					// FIXME - Catch $tmpFile is empty.

					// thumbnail uploaded
					// Check $_FILES['upfile']['error'] value(s).
					// see: {@link https://www.php.net/manual/de/features.file-upload.errors.php}

					switch ($tmpFile['error'])
					{
						case UPLOAD_ERR_OK:
						break;

						case UPLOAD_ERR_INI_SIZE:    // Wert: 1; die hochgeladene Datei �berschreitet die in der Anweisung upload_max_filesize in php.ini festgelegte Gr��e.
							throw new RuntimeException(
								sprintf(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_INI_SIZE_TEXT'), ini_get('upload_max_filesize'))
							);

						case UPLOAD_ERR_FORM_SIZE:    // Wert: 2; die hochgeladene Datei �berschreitet die in dem HTML Formular mittels des versteckten Feldes MAX_FILE_SIZE angegebene maximale Dateigr��e.
							throw new RuntimeException(
								sprintf(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_FORM_SIZE_TEXT'), FTKRULE_UPLOAD_MAX_SIZE)
							);

						case UPLOAD_ERR_PARTIAL:    // Wert: 3; die Datei wurde nur teilweise hochgeladen.
							throw new RuntimeException(
								Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_PARTIAL_TEXT')
							);

						case UPLOAD_ERR_NO_FILE:    // Wert: 4; Es wurde keine Datei hochgeladen.
							// FIXME - wrong reference for index image upload
						break;    // do nothing, just leave drawing data object received as is - preserving pre-filled information

						case UPLOAD_ERR_NO_TMP_DIR:    // Wert: 6; Fehlender tempor�rer Ordner. Eingef�hrt in PHP 5.0.3.
							throw new RuntimeException(
								Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_NO_TMP_DIR_TEXT')
							);

						case UPLOAD_ERR_CANT_WRITE:    // Wert: 7; Speichern der Datei auf die Festplatte ist fehlgeschlagen. Eingef�hrt in PHP 5.1.0.
							throw new RuntimeException(
								Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_TMP_DIR_UNREADABLE_TEXT')
							);

						case UPLOAD_ERR_EXTENSION:    // Wert: 8; eine PHP Erweiterung hat den Upload der Datei gestoppt. Eingef�hrt in PHP 5.2.0.
							// PHP bietet keine M�glichkeit an, um festzustellen, welche Erweiterung das Hochladen der Datei gestoppt hat.
							// �berpr�fung aller geladenen Erweiterungen mittels phpinfo() k�nnte helfen.
							throw new RuntimeException(
								Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_INTERRUPTED_BY_PHP_EXTENSION_TEXT')
							);
					}

					// You should also check filesize here.
					// TODO - Check against defined max. upload file size
					if ($tmpFile['size'] > FTKRULE_UPLOAD_MAX_SIZE)
					{
						throw new RuntimeException(sprintf(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_INI_SIZE_TEXT'), FTKRULE_UPLOAD_MAX_SIZE));
					}

					// DO NOT TRUST $_FILES['upfile']['mime'] VALUE !!
					// ALWAYS Check MIME Type by yourself !!
					$finfo = new finfo(FILEINFO_MIME_TYPE);

					// Find extension of uploaded file in array of allowed file extensions.
					$mimes = [
						'jpg'  => 'image/jpeg',
						'jpeg' => 'image/jpeg',
						'png'  => 'image/png',
						'heic' => 'image/heic',
						'heif' => 'image/heif'
					];

					if (false === $ext = mb_strtolower(array_search($finfo->file(ArrayHelper::getValue($tmpFile, 'tmp_name')), $mimes, true)))
					{
						throw new RuntimeException(
							Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_INVALID_FILE_FORMAT_TEXT')
						);
					}

					// DO NOT USE $_FILES['upfile']['name'] WITHOUT ANY VALIDATION !!
					$fileName  = ArrayHelper::getValue($tmpFile, 'name');

					$storePath = FTKPATH_TEMP . DIRECTORY_SEPARATOR . 'upload-tmp-' . $this->input->server->get('REQUEST_TIME');
					$basename  = explode('.', $fileName);
					array_pop($basename);
					$basename = implode('.', $basename);

					// Check if store location exists or create it.
					if (!is_dir($storePath))
					{
						if (false === mkdir($storePath))
						{
							/*Messager::setMessage([
								'type' => 'error',
								'text' => Text::translate('Directory could not be created.')
							]);
							return;*/

							throw new RuntimeException(
								Text::translate('Directory could not be created.')
							);
						}
					}

					// Check if store location exists or create it.
					if (!is_readable($storePath))
					{
						/*Messager::setMessage([
							'type' => 'error',
							'text' => Text::translate('Asset directory created but not readable.')
						]);
						return;*/

						throw new RuntimeException(
							Text::translate('Asset directory created but not readable.')
						);
					}

					// Check if store location exists or create it.
					if (!is_writable($storePath))
					{
						/*Messager::setMessage([
							'type' => 'error',
							'text' => Text::translate('Asset directory created but not writable.')
						]);
						return;*/

						throw new RuntimeException(
							Text::translate('Asset directory created but not writable.')
						);
					}

					// Build store path (location + file)
					$filePath = sprintf('%s%s%s.%s', $storePath, DIRECTORY_SEPARATOR, $basename, $ext);

					// Name every file uniquely. In this example, obtain safe unique name from its binary data.
					if (!move_uploaded_file(ArrayHelper::getValue($tmpFile, 'tmp_name'), $filePath))  // save file
					{
						/*Messager::setMessage([
							'type' => 'error',
							'text' => Text::translate('Failed to save index file.')
						]);
						return;*/

						throw new RuntimeException(
							Text::translate('Failed to save temporary file.')
						);
					}

					$filePath = sprintf('%s/%s.%s', $storePath, $basename, $ext);

					// Upload successful. Prepare return value.
					$response = [
						'tmpFile'      => $tmpFile,
						'storePath'    => $storePath,
						'storePathRel' => str_ireplace(FTKPATH_BASE, '', $storePath),
						'fileName'     => $fileName,
						'filePath'     => $filePath,
						'filePathRel'  => str_ireplace(FTKPATH_BASE, '', $filePath),
						'stream'       => ImageHelper::toBase64($filePath)
					];
				}
			break;

			case 'session.lifetime' :
				$response = ini_get('session.gc_maxlifetime');
			break;

			case 'tparams' :
				$response = array_values( Model::getInstance('techparams', ['language' => $this->language])->getTechnicalParametersByLanguage($this->language) );
			break;
		}

		if ($format === 'json')
		{
			// header("Content-type: application/json; charset=utf-8");	// DO NOT USE this header here because it breaks parsing JSON in the related Javascript function for the following reason: Uncaught SyntaxError: Unexpected token B in JSON at position 0

			try
			{
				$response = json_encode($response, JSON_THROW_ON_ERROR);
			}
			catch (JsonException $e)
			{
				$response = $e->getMessage();
			}

			echo $response;

			exit;
		}

		return $response;
	}
}
