<?php
/* define application namespace */
namespace Nematrack\Model;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Exception;
use Joomla\Image\Image as JImage;	// \Joomla\Image\Image is @deprecated and discontinued
use Joomla\Utilities\ArrayHelper;
use LogicException;
use Nematrack\App;
use Nematrack\Entity\Image;	// \Joomla\Image\Image is @deprecated and discontinued
use Nematrack\Helper\FilesystemHelper;
use Nematrack\Helper\MediaHelper;
use Nematrack\Messager;
use Nematrack\Model;
use Nematrack\Text;
use Nematrack\Utility\Math;
use RuntimeException;
use Symfony\Component\Validator\Constraints\Image as ImageValidator;
use Symfony\Component\Validator\Validation;

/**
 * Class to provide tools for admins and developers that must not be provided to the regular user.
 */
class Tools extends Model
{
	/**
	 * Class construct
	 *
	 * @param   array  $options  An array of instantiation options.
	 *
	 * @return  void
	 *
	 * @since   1.1
	 */
	public function __construct($options = [])
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		parent::__construct($options);
	}

	/**
	 * Add description...
	 *
	 * @return  array  Empty array or list of items that failed to upload.
	 *
	 * @throws  RuntimeException
	 *
	 * @uses   {@link Image}
	 * @uses   {@link ImageValidator}
	 * @uses   {@link Validation}
	 *
	 * called in:
	 *  \Nematrack\View\Tools
	 */
	public function batchUploadProcessImages(): array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		/*// FIXME
		$trace = debug_backtrace();
		trigger_error(
			'Class <em>Joomla\Image\Image</em> is deprecated and must be substituted in ' . $trace[0]['file'] . ' in line ' . $trace[0]['line'],
		);
		return [];*/

		// Get current user object.
		$user   = App::getAppUser();
//		$userID = $user->get('userID');

		// @debug
		$debug  = $user->isProgrammer();
		$debug  = false;

		/*// @debug
		if ($debug) :
//			echo '<pre style="color:blue">' . print_r(__METHOD__, true) . '</pre>';
//			die;
		endif;*/

		// Get additional function args.
		$args   = func_get_args();
		$args   = (array) array_shift($args);








		// There may be arguments to this function.
//		$filter = ArrayHelper::getValue($args, 'filter', ListModel::FILTER_ALL);
		$path   = ArrayHelper::getValue($args, 'path', null, 'STRING');
		$abbr   = ArrayHelper::getValue($args, 'abbreviation', null, 'STRING');
		$files  = ArrayHelper::getValue($args, 'files', [], 'ARRAY');
		$failed = [];

		/*// @debug
		if ($debug) :
//			echo '<pre>files: ' . print_r($files, true) . '</pre>';
//			die;
		endif;*/

		// Get required model(s).
//		$languageModel = $this->getInstance('language', ['language' => $this->language]);
		$partModel     = $this->getInstance('part',     ['language' => $this->language]);
		$processModel  = $this->getInstance('process',  ['language' => $this->language]);

		/* Get ID of selected language.
		 * IMPORTANT:   It turned out on 2022-06-03 that using lngID over language prevents the necessity of grouping the query resultset.
		 *              While testing the query there were duplicate rows when using the term <em>am.language = 'tag'</em>, whereas there
		 *              were no duplicates when using the term <em>am.lngID = ID</em> - both used without GROUP BY.
		 */
		/*$lngTag = ArrayHelper::getValue($args, 'language', $this->language, 'STRING');
		$lngID  = ArrayHelper::getValue($args, 'lngID');
		$lngID  = is_int($lngID)
			? $lngID
			: ArrayHelper::getValue($languageModel->getLanguageByTag($lngTag), 'lngID', 'INT');*/

		$process  = $processModel->getProcessByAbbreviation($abbr);
//		$finfo    = new finfo(FILEINFO_MIME_TYPE);

		/*// @debug
		if ($debug) :
//			echo '<pre>ini_get_all: '         . print_r(ini_get_all(), true) . '</pre>';				// debugs complete params ini-file
//			echo '<pre>ini_get_all: '         . print_r(ini_get_all('pcre'), true) . '</pre>';			// debugs complete pcre-related params in ini-file
//			echo '<pre>ini_get_all: '         . print_r(ini_get_all('session'), true) . '</pre>';		// debugs complete session-related params in ini-file
//			echo '<pre>post_max_size: '       . print_r(ini_get('post_max_size'), true) . '</pre>';
//			echo '<pre>max_file_uploads: '    . print_r(ini_get('max_file_uploads'), true) . '</pre>';
//			echo '<pre>upload_max_filesize: ' . print_r(ini_get('upload_max_filesize'), true) . '</pre>';
//			echo '<pre>ini_parse_quantity: '  . print_r(ini_parse_quantity('5M'), true) . '</pre>';	// available as of PHP 8.2
//		    die;
		endif;*/

		// Define common configuration.
		$defaults = [
			'uploadPath'      => null,
			'storePath'       => null,
			'originalsPath'   => null,
			'thumbsPath'      => null,
			'dirPermissions'  => 0750,
			'filePermissions' => 0640,
			'font'            => sprintf('%s/font/arial.ttf', FTKPATH_ASSETS),
			'color'           => ['R' => 255, 'G' =>   0, 'B' =>   0],
			'shadow'          => ['R' => 255, 'G' => 255, 'B' => 255],
			'resolution'      => 72,     // Display resolution. 72 is common in digital space. If not set PHP falls back to 96dpi.
			'opacity'         => 110,    // The transparency level. A value between  0 and 127.   0 indicates completely opaque while 127 indicates completely transparent.
			'imageQuality'    => 90,     // The compression level.  A value between 70 and 100. 100 indicates no compression while 70 is lower boundary for acceptable quality.
			'thumbsQuality'   => 80,     // The compression level.  A value between 70 and 100. 100 indicates no compression while 70 is lower boundary for acceptable quality.
			'watermark'       => sprintf('Copyright %d nematrack.com. All rights reserved.', date('Y')),
			'maxFileSize'     => FTKRULE_UPLOAD_MAX_SIZE ?? ini_get('upload_max_filesize'), // 52428800 B => 50 MB
			'dimension'       => [
				'16:9' => [
					'ratio'     =>  1.78,	// 16:9
					'minHeight' =>   720,
					'minWidth'  =>  1280,	// = height x ratio
					'maxHeight' =>  8629,	// e.g. 1080
					'maxWidth'  => 15360,	// e.g. 1920
				],
				'4:3'  => [
					'ratio'     =>  1.33,	// 4:3
					'minHeight' =>   756,	// fraction of maxHeight
					'minWidth'  =>  1005,	// fraction of maxWidth
					'maxHeight' => 12096,	// e.g. 3024 for Samsung Galaxy S7 (see: https://www.gsmchoice.com/de/katalog/samsung/galaxys7)
					'maxWidth'  => 16128,	// e.g. 4032 for Samsung Galaxy S7 (see: https://www.gsmchoice.com/de/katalog/samsung/galaxys7)
				],
				// Fallback for unexpected/unusual aspect ratios
				'0:0'  => [
					'minHeight' =>   756,	// fraction of maxHeight
					'minWidth'  =>  1005,	// fraction of maxWidth
					'maxHeight' => 12096,	// e.g. 3024 for Samsung Galaxy S7 (see: https://www.gsmchoice.com/de/katalog/samsung/galaxys7)
					'maxWidth'  => 16128,	// e.g. 4032 for Samsung Galaxy S7 (see: https://www.gsmchoice.com/de/katalog/samsung/galaxys7)
				]
			]
		];

		/*// @debug
		if ($debug) :
//			echo '<pre>defaults: ' . print_r($defaults, true) . '</pre>';
//		    die;
		endif;*/

		// Process files.
		foreach ($files as $fileName)
		{
			// Extract part code and article number from file name.
			[$code, $artNum] = explode('@', '' . $fileName);

			// Extract article number from file name.
			$artNum = explode('.', '' . $artNum);
			$ext    = array_pop($artNum);
			$artNum = implode('.', $artNum);

			// Get part.
			$part   = $partModel->getItemByCode($code);

			/*// @debug
			if ($debug) :
//				echo '<pre>part: ' . print_r($part, true) . '</pre>';
//			    die;
			endif;*/

			// If no part is no part entity then most likely the code is bad (typo).
			if (!is_a($part, 'Nematrack\Entity\Part'))
			{
				$failed[] = $fileName; continue;
			}

			// If $part is an empty part entity then most likely there is no part for that specific code.
			if (!$part->get($part->getPrimaryKeyName()))
			{
				$failed[] = $fileName; continue;
			}

			// Update defaults with part specific information.
			$defaults['uploadPath']    = MediaHelper::getUploadPathForPart($part->get('partID'));
			$defaults['storePath']     = FilesystemHelper::fixPath(
				sprintf(implode(DIRECTORY_SEPARATOR, ['%s', '%d']),
					MediaHelper::getMediafilesPathForPart($part->get($part->getPrimaryKeyName())),
					$process->get($process->getPrimaryKeyName())
				)
			);
			$defaults['originalsPath'] = sprintf('%s/originals', $defaults['storePath']);
			$defaults['thumbsPath']    = sprintf('%s/thumbs',    $defaults['storePath']);

			/*// @debug
			if ($debug) :
//				echo '<pre>> ' . print_r('Create directories...', true) . '</pre>';
//				die;
			endif;*/

			try
			{
				// 1.1. Create directories.

				// Create upload directory if not exists.
				if (!is_dir($defaults['uploadPath']))
				{
					// Arg 1:  The actual path.
					// Arg 2:  The octal value of the directory mode flag (default: 0777) - ignored on Windows.
					// Arg 3:  The recursion flag - allows the creation of nested directories specified in the pathname.
					if (false === mkdir($defaults['uploadPath'], $defaults['dirPermissions'], true))
					{
						throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_TMP_DIR_CREATION_FAILED_TEXT', $this->language));
					}
				}

				// Check the directory has been created.
				if (!is_dir($defaults['uploadPath']))
				{
					throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_TMP_DIR_NOT_FOUND_AFTER_CREATING_TEXT', $this->language));
				}

				// Create target directory if not exists.
				if (!is_dir($defaults['storePath']))
				{
					// Arg 1:  The actual path.
					// Arg 2:  The octal value of the directory mode flag (default: 0777) - ignored on Windows.
					// Arg 3:  The recursion flag - allows the creation of nested directories specified in the pathname.
					if (false === mkdir($defaults['storePath'], $defaults['dirPermissions'], true))
					{
						throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_TMP_DIR_CREATION_FAILED_TEXT', $this->language));
					}
				}

				// Check the directory has been created.
				if (!is_dir($defaults['storePath']))
				{
					throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_TARGET_DIR_NOT_FOUND_AFTER_CREATING_TEXT', $this->language));
				}

				// Create thumbnails directory if not exists.
				if (!is_dir($defaults['originalsPath']))
				{
					// Arg 1:  The actual path.
					// Arg 2:  The octal value of the directory mode flag (default: 0777) - ignored on Windows.
					// Arg 3:  The recursion flag - allows the creation of nested directories specified in the pathname.
					if (false === mkdir($defaults['originalsPath'], $defaults['dirPermissions'], true))
					{
						throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_RAW_FILES_DIR_CREATION_FAILED_TEXT', $this->language));
					}
				}

				// Check the directory has been created.
				if (!is_dir($defaults['originalsPath']))
				{
					throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_RAW_FILES_DIR_NOT_FOUND_AFTER_CREATING_TEXT', $this->language));
				}

				// Create thumbnails directory if not exists.
				if (!is_dir($defaults['thumbsPath']))
				{
					// Arg 1:  The actual path.
					// Arg 2:  The octal value of the directory mode flag (default: 0777) - ignored on Windows.
					// Arg 3:  The recursion flag - allows the creation of nested directories specified in the pathname.
					if (false === mkdir($defaults['thumbsPath'], $defaults['dirPermissions'], true))
					{
						throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_TMP_THUMBNAILS_DIR_CREATION_FAILED_TEXT', $this->language));
					}
				}

				// Check the directory has been created.
				if (!is_dir($defaults['thumbsPath']))
				{
					throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_TMP_THUMBNAILS_DIR_NOT_FOUND_AFTER_CREATING_TEXT', $this->language));
				}

				/*// @debug
				if ($debug) :
//					echo '<pre style="font-weight:bold; color:green">' . print_r('Directories created.', true) . '</pre>';
//					die;
				endif;*/

				// 1.2. Process image data.

				if (!preg_match('/^.*_(\d{10,})\.[a-z]{3}$/i', $fileName))
				{
					$tmpFileName  = sprintf('%s@%s_%s.%s',
						mb_strtoupper($code),
						mb_strtoupper($artNum),
						hrtime(true),    // unique 16-digit number like 3080760748900229 to be used as unique id and for sorting (sys time doesn't work for 1+ uploaded files)
						$ext
					);
				}
				else
				{
					$tmpFileName  = sprintf('%s@%s.%s',
						mb_strtoupper($code),
						mb_strtoupper($artNum),
						$ext
					);
				}

				$tmpFilePath  = FilesystemHelper::fixPath(sprintf('%s%s%s', $path,                      DIRECTORY_SEPARATOR, $tmpFileName));
				$dataFilePath = FilesystemHelper::fixPath(sprintf('%s%s%s', $defaults['originalsPath'], DIRECTORY_SEPARATOR, $tmpFileName));
				$imgFilePath  = FilesystemHelper::fixPath(sprintf('%s%s%s', $defaults['storePath'],     DIRECTORY_SEPARATOR, $tmpFileName));

				/*// @debug
				if ($debug) :
//					echo '<pre>> ' . print_r('Inject hrtime...', true) . '</pre>';
//					die;
				endif;*/

				//-> BEGiN: NEW
				// Inject hrtime into file name to make pic unique.
				// D I F F E R E N C E ! ! !
				// In part model the base64 data stream is dumped into an image file, whereas here we already have an image file on the server.

				if (preg_match('/^.*_(\d{10,})\.[a-z]{3}$/i', $fileName))
				{
					/*// @debug
					if ($debug) :
//						echo '<pre>> ' . print_r('... not necessary.', true) . '</pre>';
//						die;
					endif;*/
				}
				else
				{
					$oldFilePath = $path . DIRECTORY_SEPARATOR . $fileName;
					$newFilePath = &$tmpFilePath;

					if (!is_file($newFilePath))
					{
						$renamed = rename($oldFilePath, $newFilePath);

						if (!$renamed)
						{
							throw new LogicException('hrtime-injection failed.');
						}

						if (!is_file($newFilePath))
						{
							throw new LogicException('Input file not found after renaming.');
						}
					}

					// Update reference.
					$fileName = basename($newFilePath);

					/*// @debug
					if ($debug) :
//						echo '<pre style="font-weight:bold; color:green">' . print_r('hrtime injected', true) . '</pre>';
//						die;
					endif;*/
				}
				//<- END: NEW

				// 2.2. Dump base64-image data into PNG file.
				// D I F F E R E N C E ! ! !
				// This is the next step in part model, which is not relevant here.
//				file_put_contents($tmpFilePath, base64_decode($groups[4]));

				/*// @debug
				if ($debug) :
//					echo '<pre>> ' . print_r('Load into Image object...', true) . '</pre>';
//					echo '<pre>   path: ' . print_r($tmpFilePath, true) . '</pre>';
//					die;
				endif;*/

				// 2.4. Generate Image object instance from dumped PNG file to generate JPEGs.
				// D I F F E R E N C E ! ! ! ... moved here to be able to detect the aspect ratio.
				$image = new Image($tmpFilePath);
//				$image = new Image(imagecreatefromstring(base64_decode($groups[4])));   // works but causes issues on thumbs-generation because of the missing 'path' info

				/*// @debug
				if ($debug) :
//					echo '<pre style="font-weight:bold; color:green">' . print_r('Image created.', true) . '</pre>';
//					die;
				endif;*/

				/*// @debug
				if ($debug) :
//					echo '<pre>> ' . print_r('Detect Image dimension...', true) . '</pre>';
//					die;
				endif;*/

				// Detect ratio to refer to the corresponding default dimensions.

				// 2.3 Validate dumped input file using Symfony Constraints to check if satisfies requirements.
				// see {@link https://symfony.com/doc/current/components/validator.html}
				// and {@link https://symfony.com/doc/current/validation/translations.html}
				$dimension  = current(ArrayHelper::getColumn($defaults, $image->getDimension()));

				if (!$dimension)
				{
					throw new RuntimeException(Text::translate('The uploaded image has an unexpected aspect ratio.', $this->language));    // TODO - translate
				}

				/*// @debug
				if ($debug) :
//					echo '<pre style="font-weight:bold; color:green">' . print_r('Image dimension detected.', true) . '</pre>';
//					die;
				endif;*/

				/*// @debug
				if ($debug) :
//					echo '<pre>> ' . print_r('Define validation rules...', true) . '</pre>';
//					die;
				endif;*/

				// Create Symfony/Validation object.
				// This validation object requires to be passed a specific validator like the ImageValidator in line 467.
				$validator  = Validation::createValidator();
				// Define validation rules.
				$rules      = [
					// The array keys MUST match the {@see \Symfony\Component\Validator\Constraints\Image} class properties !!!
					'allowLandscape'  => true,
					'allowPortrait'   => false, // FIXME - allow portrait and rotate to landscape
					'allowSquare'     => false,
					'detectCorrupted' => true,
					'mimeTypes'       => ['image/jpeg', 'image/pjpeg', 'image/png'],
					'maxSize'         => $defaults['maxFileSize'],
//					'minRatio'        => $dimension['ratio'],
//					'maxRatio'        => $dimension['ratio'],
					'minWidth'        => $dimension['minWidth'],
					'maxWidth'        => $dimension['maxWidth'],
					'minHeight'       => $dimension['minHeight'],
					'maxHeight'       => $dimension['maxHeight']
				];

				// Free memory.
//				unset($dimension);

				/*// @debug
				if ($debug) :
//					echo '<pre style="font-weight:bold; color:green">' . print_r('Validation rules defined.', true) . '</pre>';
//					die;
				endif;*/

				/*// @debug
				if ($debug) :
//					echo '<pre>> ' . print_r('Validate image...', true) . '</pre>';
//					die;
				endif;*/

				// Validate image file.
				$violations = $validator->validate($tmpFilePath, [new ImageValidator($rules)]); // FIXME - This causes unreported processing interruption.

				// Handle potential validation errors.
				if (0 !== count($violations))
				{
					/*// @debug
					if ($debug) :
//						echo '<pre>Validation result: ' . print_r($violations, true) . '</pre>';
//						die;
					endif;*/

					// Render violation message(s).
					// see: https://stackoverflow.com/a/54219981
					array_walk($violations, function (array $violationList) use (&$failed, &$fileName)
					{
						$messages = [];

						array_walk($violationList, function ($violation) use (&$messages)
						{
							$messages[] = $violation->getMessage();        // FIXME - how to bind these to our languages?

							return true;
						});

						Messager::setMessage([
							'type' => 'error',
							'text' => Text::translate(implode('<br/>', $messages), $this->language)
						]);

						throw new Exception;
					});
				}
				else
				{
					/*// @debug
					if ($debug) :
//						echo '<pre style="font-weight:bold; color:green">' . print_r('Image is valid.', true) . '</pre>';
//						die;
					endif;*/
				}

				/*// @debug
				if ($debug) :
//					echo '<pre>> ' . print_r('Check the Image object is a Resource object...', true) . '</pre>';
//					die;
				endif;*/

				// 2.4.1 Validate we have a proper image resource object.
				if (!is_a($image->getHandle(), 'GdImage') && !is_resource($image->getHandle()))
				{
					throw new LogicException('No valid image was uploaded.', $this->language);    // TODO - translate
				}

				/*// @debug
				if ($debug) :
//					echo '<pre style="font-weight:bold; color:green">' . print_r('Image object is a Resource object.', true) . '</pre>';
//					die;
				endif;*/

				/*// @debug
				if ($debug) :
//					echo '<pre>> ' . print_r('Validate the file Mime is allowed...', true) . '</pre>';
//					die;
				endif;*/

				// DO NOT TRUST $_FILES['upfile']['mime'] VALUE !!
				// ALWAYS Check MIME Type by yourself !!

				// 2.5 Validate mime type to catch potential security threat.
				if (false === $ext = mb_strtolower(array_search($mime = $image->getMime(), ['png' => 'image/png'], true)))
				{
					throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_INVALID_FILE_FORMAT_TEXT', $this->language));
				}

				/*// @debug
				if ($debug) :
//					echo '<pre style="font-weight:bold; color:green">' . print_r('File Mime is OK.', true) . '</pre>';
//					die;
				endif;*/

				/*// @debug
				if ($debug) :
//					echo '<pre>> ' . print_r('Validate the file extension is allowed...', true) . '</pre>';
//					die;
				endif;*/

				// Validate the uploaded file's extension is in the allowed file extensions list.
				if (false === $ext)
				{
					throw new RuntimeException(Text::translate('COM_FTK_ERROR_FILE_UPLOAD_ERR_INVALID_FILE_FORMAT_TEXT', $this->language));
				}

				/*// @debug
				if ($debug) :
//					echo '<pre style="font-weight:bold; color:green">' . print_r('File extension is OK.', true) . '</pre>';
//					die;
				endif;*/

				/*// @debug
				if ($debug) :
//					echo '<pre>> ' . print_r('Force image file resolution of 72 dpi...', true) . '</pre>';
//					die;
				endif;*/

				// 2.6 Ensure image resolution is screen resolution, which is 72dpi (PHP default is 96dpi if not expl. applied).
				imageresolution($image->getHandle(), $defaults['resolution'], $defaults['resolution']);

				/*// @debug
				if ($debug) :
//					echo '<pre style="font-weight:bold; color:green">' . print_r('Image file resolution set.', true) . '</pre>';
//					die;
				endif;*/

				// 2.7 Generate thumbnails first (they must not be watermarked).
				$image->setThumbnailGenerate($defaults['thumbsQuality']);

				/*// @debug
				if ($debug) :
//					echo '<pre>> ' . print_r('Create thumbnails...', true) . '</pre>';
//					die;
				endif;*/

				array_map(function ($thumb) use (&$debug, &$defaults, &$user)
				{
					// Since the dumped PNG file hase no extension we must add file extensions to the generated thumbnails.
//					if (!in_array(mb_strtolower(ArrayHelper::getValue($pathinfo, 'extension')), ['jpg','jpeg','png','bmp']))
					if (!in_array(@pathinfo($thumb->getPath(), PATHINFO_EXTENSION), ['jpg', 'jpeg', 'png', 'bmp']))
					{
						$pathinfo = @pathinfo($thumb->getPath());

						$old = FilesystemHelper::fixPath(sprintf('%s/%s', $defaults['thumbsPath'], ArrayHelper::getValue($pathinfo, 'basename')));
						// Move position of dimension tag in file name.
						$new = preg_replace('/^(.*)_(\d{2,4}x\d{2,4})\.(000)_(.*)$/', '$1.$3_$2_$4', ArrayHelper::getValue($pathinfo, 'basename'));
						// Add extension.
						$new = FilesystemHelper::fixPath(sprintf('%s/%s.jpg', $defaults['thumbsPath'], $new));

						rename($old, $new);

						return $thumb;
					}

					// Fix CHMOD.
					chmod($thumb->getPath(), $defaults['filePermissions']);  // octal; correct value of mode

					/*// @debug
					if ($debug) :
//						echo '<pre>   thumbnail path' . print_r($thumb, true) . '</pre>';
//						die;
					endif;*/

					// 16:9 landscape dimensions ['200x113','150x84','120x68','100x56']
					//          cubic dimensions ['113x113','150x84','120x68', '56x56']

					return true;
				}, $image->createThumbs(['200x113'], JImage::CROP_RESIZE, $defaults['thumbsPath']));	// \Joomla\Image\Image is @deprecated and discontinued

				/*// @debug
				if ($debug) :
//					echo '<pre style="font-weight:bold; color:green">' . print_r('Thumbnails created.', true) . '</pre>';
//					die;
				endif;*/

				/*// @debug
				if ($debug) :
//					echo '<pre>> ' . print_r('Calculate overlay text position...', true) . '</pre>';
//					die;
				endif;*/

				// Calculate the fullsize image diagonal length and watermark rotation angle.
				$anKathete    = Math::pixelToCentimeters($image->getWidth());
				$gegenKathete = Math::pixelToCentimeters($image->getHeight());
				$fontSize     = Math::pixelToCentimeters($image->getWidth() >= $dimension['maxWidth'] ? 50 : 45);
//				$hypotenuse   = Math::pythagoras($anKathete, $gegenKathete);
//				$asin         = asin($gegenKathete / $anKathete);
//				$sinAlpha     = ($asin / pi()) * 180;  // see {@link https://www.php.net/manual/en/function.asin.php}
				$atan     = atan(($gegenKathete - ($fontSize / 2)) / $anKathete);
				$tanAlpha = ($atan / pi()) * 180;  // see {@link https://www.php.net/manual/en/function.asin.php}

				// Define watermark props.
				$watermark  = $defaults['watermark'];
				$txt        = sprintf('%s %s %s %s', $watermark, $watermark, $watermark, $watermark);
				$txtSize    = ($image->getWidth() >= $dimension['maxWidth'] ? 66 : 45);
				$txtColor   = imagecolorallocatealpha($image->getHandle(), $defaults['color']['R'],  $defaults['color']['G'],  $defaults['color']['B'],  $defaults['opacity']);
				$txtShadow  = imagecolorallocatealpha($image->getHandle(), $defaults['shadow']['R'], $defaults['shadow']['G'], $defaults['shadow']['B'], $defaults['opacity']);
				/*// D I F F E R E N C E ! ! !   ...   Watermark now starts from outermost frame border and ends at outermost frame border.
				// Because of the unpredictable input file sizes there is no longer a reliable way to calculate the perfect font size.
				// Hence, it was decided to repeat the watermark until it covers a full diagonal.
				$txtOffsetX = $txtSize;
				$txtOffsetY = $image->getHeight() - ($txtSize / 2);*/
				$txtOffsetX = 0;
				$txtOffsetY = $image->getHeight();
				$txtAngle   = $tanAlpha;    // Calculated via Pythagorean Theorem

				/*// @debug
				if ($debug) :
//					echo '<pre style="font-weight:bold; color:green">' . print_r('Text position calculated.', true) . '</pre>';
//					die;
				endif;*/

				// 2.8 Add watermark (the text itself and every effect are added as individual layers).

				/*// @debug
				if ($debug) :
//					echo '<pre>> ' . print_r('Add overlay text...', true) . '</pre>';
//					die;
				endif;*/

				// Add text shadow layer first.
				ImageTTFText($image->getHandle(), $txtSize, $txtAngle, $txtOffsetX + 1, $txtOffsetY + 1, $txtShadow, $defaults['font'], $txt);
				// Add text layer next.
				ImageTTFText($image->getHandle(), $txtSize, $txtAngle, $txtOffsetX, $txtOffsetY, $txtColor, $defaults['font'], $txt);

				/*// @debug
				if ($debug) :
//					echo '<pre style="font-weight:bold; color:green">' . print_r('Overlay text added.', true) . '</pre>';
//					die;
				endif;*/

				/*// @debug
				if ($debug) :
//					echo '<pre>> ' . print_r('Scale image.', true) . '</pre>';
//					die;
				endif;*/

				// 2.9 Scale image if necessary.
				if ($image->getWidth() > $dimension['maxWidth'])
				{
					$image->resize($dimension['maxWidth'], $dimension['maxHeight'], false, JImage::SCALE_FIT);	// \Joomla\Image\Image is @deprecated and discontinued
				}

				/*// @debug
				if ($debug) :
//					echo '<pre style="font-weight:bold; color:green">' . print_r('Image scaled.', true) . '</pre>';
//					die;
				endif;*/

				/*// @debug
				if ($debug) :
//					echo '<pre>> ' . print_r('Define target file type and quality...', true) . '</pre>';
//					die;
				endif;*/

				// Define image type and quality to apply.
				switch ($mime)
				{
					case 'image/gif' :
						$imageType    = IMAGETYPE_GIF;
						$imageQuality = null;
					break;

					case 'image/png' :
						$imageType    = IMAGETYPE_PNG;
						$imageQuality = 9;  // Compression levels for imagepng: 0 (no compression) to 9.
					break;

					default :
					case 'image/jpg' :
					case 'image/jpeg' :
					case 'image/pjpeg' :
						$imageType    = IMAGETYPE_JPEG;
						$imageQuality = 80;  // Compression levels for imagejpeg: 0 (worst, smaller file) to 100 (best, biggest file). Default is 75.
					break;
				}

				/*// @debug
				if ($debug) :
//					echo '<pre style="font-weight:bold; color:green">' . print_r('Target file type and quality defined.', true) . '</pre>';
//					die;
				endif;*/

				/*// @debug
				if ($debug) :
//					echo '<pre>> ' . print_r('Write target file...', true) . '</pre>';
//					die;
				endif;*/

				// 2.10 Write out to generated image.
				$image->toFile($imgFilePath, $imageType, ['quality' => $imageQuality]);
				chmod($imgFilePath, $defaults['filePermissions']);  // octal; correct value of mode

				/*// @debug
				if ($debug) :
//					echo '<pre style="font-weight:bold; color:green">' . print_r('Target file written.', true) . '</pre>';
//					die;
				endif;*/

				/*// @debug
				if ($debug) :
//					echo '<pre>> ' . print_r('Rename target file...', true) . '</pre>';
//					die;
				endif;*/

				// 2.11 Move original image data file to target directory.
				rename($tmpFilePath, $dataFilePath);

				/*// @debug
				if ($debug) :
//					echo '<pre style="font-weight:bold; color:green">' . print_r('Target file renamed.', true) . '</pre>';
//					die;
				endif;*/

				/*// @debug
				if ($debug) :
//					echo '<pre>> ' . print_r('Set target file permissions...', true) . '</pre>';
//					die;
				endif;*/

				chmod($dataFilePath, $defaults['filePermissions']);  // octal; correct value of mode

				/*// @debug
				if ($debug) :
//					echo '<pre style="font-weight:bold; color:green">' . print_r('Target file permissions set.', true) . '</pre>';
//					die;
				endif;*/
			}
			catch (Exception $e)
			{
				/*// @debug
				if ($debug) :
//					echo '<pre>Error: ' . print_r($e->getMessage(), true) . '</pre>';
//					die;
				endif;*/

				// Log error.
				$this->logger->log('error', __METHOD__, ['error' => $e->getMessage()]);

				// Dump failed item.
				$failed[] = $fileName;

				// TODO - log error

				Messager::setMessage([
					'type' => 'error',
					'text' => Text::translate($e->getMessage(), $this->language)
				]);

				continue;
			}
		}

		/*// @debug
		if ($debug) :
			if (count($failed)) :
//				echo '<pre>' . sprintf('%d pictures skipped: ', count($failed)) . print_r($failed, true) . '</pre>';
			endif;

//			die('Done!');
		endif;*/

		return $failed;
	}
}
