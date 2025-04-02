<?php
/* define application namespace */
namespace Nematrack\Entity;

/* no direct script access */
defined('_FTK_APP_') or die('403 FORBIDDEN');

use finfo;

/**
 * Class description
 *
 * @depends {@see \Joomla\Image\Image}
 */
class Image extends \Joomla\Image\Image
{
	/**
	 * Returns the aspect ratio of the currently referenced image resource.
	 *
	 * @return	float
	 *
	 * @since	2.10.1
	 */
	public function getAspectRatio()
	{
		return $this->getWidth() / $this->getHeight();
	}

	/**
	 * Returns the dimension of the currently referenced image resource.
	 *
	 * @return	string
	 *
	 * @since	2.10.1
	 */
	public function getDimension() : ?string
	{
		$aspectRatio = $this->getAspectRatio();

		switch (number_format($aspectRatio, 2))
		{
			case '1.32' :
			case '1.33' :
			case '1.34' :
				return  '4:3';

			case '1.77' :
			case '1.78' :
			case '1.79' :
				return '16:9';
		}

		return '0:0';
	}

	/**
	 * Returns information about an image file.
	 *
	 * This function extends the {@see \Joomla\Image\Image::getImageFileProperties()} method to return further information.
	 *
	 * While the parent class' function returns values for width, height, type, attributes, bits, channels, mime type,
	 * filesize and orientation, this function additionally returns the aspect ratio.
	 *
	 * @param   string  $path  The filesystem path to the image for which to get properties.
	 *
	 * @return  array
	 *
	 * @since   2.10.1
	 */
	public function getFileProperties(string $path) : array
	{
		$properties = (array) static::getImageFileProperties($path);

		if ($this->isLoaded())
		{
			$properties['dimension'] = $this->getDimension();
			$properties['ratio']     = $this->getAspectRatio();
		}

		return $properties;
	}

	/**
	 * Add description
	 *
	 * @return  string
	 */
	public function getMime() : string
	{
		$finfo = new finfo(FILEINFO_MIME_TYPE);

		$mime  = $finfo->file($this->getPath());

		unset($finfo);

		return $mime;
	}
}
