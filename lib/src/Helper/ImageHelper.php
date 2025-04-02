<?php
/* define application namespace */
namespace Nematrack\Helper;

/* no direct script access */
defined ('_FTK_APP_') OR die('403 FORBIDDEN');

use Exception;
use Imagick;
use ImagickException;
use Joomla\Filesystem\File;
use Joomla\Filesystem\Path;
use Joomla\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Nematrack\Messager;
use Nematrack\Text;
use RuntimeException;
use function is_a;
use function is_file;
use function is_readable;

/**
 * Class description
 */
final class ImageHelper
{
	/**
	 * Private constructor. Class cannot be constructed.
     *
     * Only static calls are allowed.
	 */
	private function __construct()
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;
	}

	/**
	 * Create image file from image file data stream.
	 *
	 * @param   string      $data      The base64 encoded input stream including the header portion 'data:image/png;base64'.
	 * @param   boolean     $store     Flag indicating whether to store the data into a file.
	 * @param   string|null $fileName  The file name to use when data should be stored.
	 * @param   string|null $filePath  The file path to use when data should be stored. Note, this must be an absolute system path.
	 *
	 * @return  string The file path to the generated image.
	 *
	 * @link    https://github.com/joomla/joomla-cms/pull/4196
	 */
	public static function fromBase64(string $data, bool $store = false, string $fileName = null, string $filePath = null) : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		[$type, $stream] = explode(';', $data);

		$stream = str_ireplace('base64,', '', $stream);
		$stream = base64_decode($stream);
		$type   = explode('/', $type);
		$type   = end($type);

		if ($store)
		{
			if (!$fileName)
			{
				throw new RuntimeException(
					Text::translate('You selected to store the image file. However, you did not specify a file name.')
				);
			}

			if (!$filePath)
			{
				throw new RuntimeException(
					Text::translate('You selected to store the image file. However, you did not specify a target path.')
				);
			}
			else
			{
				// Fix slashes.
				$filePath = Path::clean($filePath);

				// It might be the file already includes the absolute path. Remove it.
				$filePath = str_ireplace(FTKPATH_BASE, '', $filePath);

				// The file path must be an absolute system path.
				$filePath = FTKPATH_BASE . DIRECTORY_SEPARATOR . ltrim($filePath, DIRECTORY_SEPARATOR);

				// Validate the file exists in the file system.
				if (!is_dir($filePath))
				{
					throw new RuntimeException(
						sprintf('You selected to store the image file. However, the specified target %s path does not exist.', $filePath)
					);
				}

				// Check whether the target path is writable.
				if (!is_writable($filePath))
				{
					throw new RuntimeException(
						sprintf('You selected to store the image file. However, the specified target %s path is not writable.', $filePath)
					);
				}
			}

			// Build save path.
			$type     = preg_match('/jpeg/i', $type) ? 'jpg' : $type;
			$fileName = File::stripext(basename(trim($fileName, DIRECTORY_SEPARATOR)));
			$filePath = !IS_WIN ? DIRECTORY_SEPARATOR . $filePath : $filePath;
			$fullpath = FilesystemHelper::fixPath($filePath . DIRECTORY_SEPARATOR . "$fileName.$type");

			// Attempt to save the file.
			try
			{
				file_put_contents($fullpath, $stream);

				// Check the generated file exists.
				if (!is_file($fullpath))
				{
					throw new RuntimeException(
						Text::translate('The image file was generated and written. However, it cannot be found in the file system.')
					);
				}
			}
			catch (Exception $e)
			{
				throw new RuntimeException(
					$e->getMessage()
				);
			}
		}

		return $fullpath;
	}

	/**
	 * Add description...
	 *
	 * @param   string $filePath
	 * @param   array  $options
	 *
	 * @return  array|null
	 *
	 * @throws ImagickException When Imagick fails.
	 *
	 * @todo - create class for upload processing
	 * @todo - create class for this stuff
	 */
	public static function makeImageFromPDF(string $filePath, array $options = []) : ?array
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$return = [];

		/* Upload einer Artikel-Zeichnung
		 *
		 * Nach Absprache mit Sebastian am 26.07.2019 in dessen Büro muss eine
		 * rekursive Verarbeitung aller Zeichnungen in einem Sammelverzeichnis nicht erfolgen,
		 * weil eine Synchronisation zwischen dem Render-Verzeichnis der F&E und einem Web-Verzeichnis
		 * nicht vorgesehen ist.
		 *
		 * Es soll im Zuge des Uploads generiert werden, was nötig ist, und diese Dateien
		 * entsprechend abgelegt werden - unabhängig von der F&E.
		 */

		if (!class_exists('Imagick'))
		{
			Messager::setMessage([
				'type' => 'warning',
				// TODO - translate
				'text' => Text::translate(
					sprintf('Thumbnail cannot be created. Required dependency is not available: <strong>%s</strong>', 'Imagick')
				)
			]);

			return null;
		}
		else
		{
			$create = (object) [
				'thumbnail' => (object) [
					'width'      => ArrayHelper::getValue($options, 'width',            '0',    'INT'),
					'height'     => ArrayHelper::getValue($options, 'height',           '0',    'INT'),	// width will be calculated to preserve correct aspect ratio - only if width is set to 0 !!!
					'resolution' => ArrayHelper::getValue($options, 'resolution',      '72',    'INT'),
					'suffix'     => ArrayHelper::getValue($options, 'suffix',     '__thumb', 'STRING'),
					'extension'  => ArrayHelper::getValue($options, 'extension', FTKRULE_DRAWING_THUMB_EXTENSION, 'STRING'),
					'path'       => FilesystemHelper::fixPath(
						pathinfo($filePath, PATHINFO_DIRNAME) .
						DIRECTORY_SEPARATOR .
						pathinfo($filePath, PATHINFO_FILENAME) .
						mb_strtolower(ArrayHelper::getValue($options, 'suffix', '__thumb', 'STRING')) . '.' .
						mb_strtolower(ArrayHelper::getValue($options, 'extension', FTKRULE_DRAWING_THUMB_EXTENSION,  'STRING'))
					)
				]
			];

			// GET instance of Imagick
			if (false === (($imagick = new Imagick) instanceof Imagick))
			{
				Messager::setMessage([
					'type' => 'error',
					'text' => Text::translate('Failed to instantiate Imagick.')
				]);

				throw new RuntimeException(
					Text::translate('Failed to instantiate Imagick.')
				);
			}

			foreach ($create as $props)
			{
				/* IMPROVE CLARITY by setting a higher resolution
				 * Required for better text readability.
				 * Must be called  BEFORE  reading the image, otherwise has no effect.
				 * 	density {$x_resolution}x{$y_resolution}
				 *
				 * Setting the density (which is the DPI)  BEFORE  reading in a PDF
				 * will pass the density to GhostScript underneath which rasterizes the PDF.
				 *
				 * To get a good result, supersample at double the density you require, and use resample to get back to the desired DPI. Remember to change the colorspace to RGB if you want an RGB JPEG.
				 */
				if (false === $imagick->setResolution($props->resolution, $props->resolution))
				{
					Messager::setMessage([
						'type' => 'error',
						'text' => Text::translate('Failed to set resolution.')
					]);

					throw new RuntimeException(
						Text::translate('Failed to set resolution.')
					);
				}

				/* LOAD resource
				 * $imagick->readImage($filePath);				// this will automatically render the last page in the PDF
				 * the number in the bracket indicates the number of the page to be rendered starting with 0
				 *
				 *   VERY TIME CONSUMING !!!
				 */
				if (false === $imagick->readImage($filePath . '[0]'))	// FIXME - Application dies here when using PHP >= 7.2 (on Windows only)
				{
					Messager::setMessage([
						'type' => 'error',
						'text' => Text::translate('Failed to read input file.')
					]);

					throw new RuntimeException(
						Text::translate('Failed to read input file.')
					);
				}

				/* SCALE image
				 * If 0 is provided as a width or height parameter,
				 * the other value is maintained by imagemagick to preserve the aspect ratio.
				 */
				// if (false === $imagick->thumbnailImage($props->width, $props->height))	// Much slower , but creates smaller files
				if (false === $imagick->scaleImage($props->width, $props->height))	// same as above , but Much faster , creates bigger files
				{
					Messager::setMessage([
						'type' => 'error',
						'text' => Text::translate('Failed to scale image(s).')
					]);

					throw new RuntimeException(
						Text::translate('Failed to scale image(s).')
					);
				}

				/* SET TYPE of target file
				 */
				if (false === $imagick->setImageFormat($props->extension))
				{
					Messager::setMessage([
						'type' => 'error',
						'text' => Text::translate('Failed to set output format.')
					]);

					throw new RuntimeException(
						Text::translate('Failed to set output format.')
					);
				}

				/* FLATTEN
				 * This is necessary for images with transparency,
				 * it will produce white background for transparent regions
				 */
				// No longer supported by ImageMagick

				/* WRITE output file
				 */
				if (false === $imagick->writeImage($props->path))
				{
					Messager::setMessage([
						'type' => 'error',
						'text' => Text::translate('Failed to store image(s).')
					]);

					throw new RuntimeException(
						Text::translate('Failed to store image(s).')
					);
				}

				/* VALIDATE output file
				 */
				if (false === is_file($props->path) || !is_readable($props->path))
				{
					Messager::setMessage([
						'type' => 'error',
						'text' => Text::translate('Failed to read generated image(s).')
					]);

					throw new RuntimeException(
						Text::translate('Failed to read generated image(s).')
					);
				}

				// FIXME - double check the image(s) have been created via is_file() + is_readable()
				$return[] = $props->path;
			}
		}

		// Free memory.
		if (is_a($imagick, 'Imagick'))
		{
			$imagick->clear();
			
			unset($imagick);
		}

		return $return;
	}

	/**
	 * Create base64 encoded data stream from image file.
	 *
	 * @param   string $filePath  The input file to use. Note, this must be an absolute system path.
	 *
	 * @return  string
	 * @link    https://github.com/joomla/joomla-cms/pull/4196
	 */
	public static function toBase64(string $filePath) : string
	{
		echo (FTK_PROFILING) ? '<pre style="color:crimson">' . print_r(__METHOD__ . '()', true) . '</pre>' : null;

		$return = '';

		if (!$filePath)
		{
			return $return;
		}

		// Sanitize input path.
		$filePath = new Uri($filePath);

		// We accept internal files only.
		$filePath = $filePath->getScheme() ? $filePath->getPath() : $filePath->toString();

		// Fix slashes.
		$filePath = Path::clean($filePath);

		// It might be the file already includes the absolute path. Remove it.
		$filePath = str_ireplace(FTKPATH_BASE, '', $filePath);

		// The file path must be an absolute system path.
		$filePath = FTKPATH_BASE . DIRECTORY_SEPARATOR . ltrim($filePath, DIRECTORY_SEPARATOR);

		// Validate the file exists in the file system.
		if (!is_file($filePath))
		{
			return $return;
		}

		// We don't trust the file extension. So we read the image type from its mime header.
		if (!($props = (object) getimagesize($filePath)))
		{
			return $return;
		}

		$type = $props->mime ?? null;

		// The file appears to be no image.
		if (!$type)
		{
			return $return;
		}

		// Extract the image type.
		$type = explode('/', $type);
		$type = end($type);

		// Load and convert the file.
		$data = base64_encode(file_get_contents($filePath));

		// Escape slashes as they break CSS parsing.
		$data = htmlentities($data);

		/* Return escaped string.
		 * NOTE:   Escaping is important as otherwise parsing the data might fail when it contains slashes.
		 */
		return sprintf('data:image/%s;base64,%s', $type, $data);
	}
}
