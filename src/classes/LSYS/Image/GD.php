<?php
/**
 * lsys image
 * @author     Lonely <shan.liu@msn.com>
 * @copyright  (c) 2017 Lonely <shan.liu@msn.com>
 * @license    http://www.apache.org/licenses/LICENSE-2.0
 */
namespace LSYS\Image;
use LSYS\Image;
use LSYS\Exception;
class GD extends Image {
	
	// Is GD bundled or separate?
	protected static $_bundled;
	
	/**
	 * Checks if GD is enabled and bundled. Bundled GD is required for some
	 * methods to work. Exceptions will be thrown from those methods when GD is
	 * not bundled.
	 *
	 * @return  boolean
	 */
	public static function check()
	{
		if ( ! function_exists('gd_info'))
		{
			throw new Exception(__('GD is either not installed or not enabled, check your configuration'));
		}
	
		if (defined('GD_BUNDLED'))
		{
			// Get the version via a constant, available in PHP 5.
			self::$_bundled = GD_BUNDLED;
		}
		else
		{
			// Get the version information
			$info = gd_info();
	
			// Extract the bundled status
			self::$_bundled = (bool) preg_match('/\bbundled\b/i', $info['GD Version']);
		}
	
		if (defined('GD_VERSION'))
		{
			// Get the version via a constant, available in PHP 5.2.4+
			$version = GD_VERSION;
		}
		else
		{
			// Get the version information
			$info = gd_info();
	
			// Extract the version number
			preg_match('/\d+\.\d+(?:\.\d+)?/', $info['GD Version'], $matches);
	
			// Get the major version
			$version = $matches[0];
		}
	
		if ( ! version_compare($version, '2.0.1', '>='))
		{
			throw new Exception(__('self requires GD version :version or greater, you have 2.0.1',array("version"=>$version)));
		}
	
		return self::$_checked = TRUE;
	}
	
	// Temporary image resource
	protected $_image;
	
	// Function name to open Image
	protected $_create_function;
	
	/**
	 * Runs [self::check] and loads the image.
	 *
	 * @return  void
	 * @throws  Exception
	 */
	public function __construct($file)
	{
		if ( ! self::$_checked)
		{
			// Run the install check
			self::check();
		}
	
		parent::__construct($file);
	
		// Set the image creation function name
		switch ($this->type)
		{
			case IMAGETYPE_JPEG:
				$create = 'imagecreatefromjpeg';
				break;
			case IMAGETYPE_GIF:
				$create = 'imagecreatefromgif';
				break;
			case IMAGETYPE_PNG:
				$create = 'imagecreatefrompng';
				break;
		}
	
		if ( ! isset($create) OR ! function_exists($create))
		{
			throw new Exception(__('Installed GD does not support :type images',array("type"=>image_type_to_extension($this->type, FALSE))));
		}
	
		// Save function for future use
		$this->_create_function = $create;
	
		// Save filename for lazy loading
		$this->_image = $this->file;
	}
	
	/**
	 * Destroys the loaded image to free up resources.
	 *
	 * @return  void
	 */
	public function __destruct()
	{
		if (is_resource($this->_image))
		{
			// Free all resources
			imagedestroy($this->_image);
		}
	}
	public function clear(){
		$this->_loadImage();
		imagefilledrectangle($this->_image,0,0,$this->width,$this->height,0xffffff);
		imagecolorallocatealpha($this->_image,255,255,255,100);
	}
	/**
	 * Loads an image into GD.
	 *
	 * @return  void
	 */
	protected function _loadImage()
	{
		if ( ! is_resource($this->_image))
		{
			// Gets create function
			$create = $this->_create_function;
	
			// Open the temporary image
			$this->_image = $create($this->file);
	
			// Preserve transparency when saving
			imagesavealpha($this->_image, TRUE);
		}
	}
	
	protected function _doResize($width, $height)
	{
		// Presize width and height
		$pre_width = $this->width;
		$pre_height = $this->height;
	
		// Loads image if not yet loaded
		$this->_loadImage();
	
		// Test if we can do a resize without resampling to speed up the final resize
		if ($width > ($this->width / 2) AND $height > ($this->height / 2))
		{
			// The maximum reduction is 10% greater than the final size
			$reduction_width  = round($width  * 1.1);
			$reduction_height = round($height * 1.1);
	
			while ($pre_width / 2 > $reduction_width AND $pre_height / 2 > $reduction_height)
			{
				// Reduce the size using an O(2n) algorithm, until it reaches the maximum reduction
				$pre_width /= 2;
				$pre_height /= 2;
			}
	
			// Create the temporary image to copy to
			$image = $this->_create($pre_width, $pre_height);
	
			if (imagecopyresized($image, $this->_image, 0, 0, 0, 0, $pre_width, $pre_height, $this->width, $this->height))
			{
				// Swap the new image for the old one
				imagedestroy($this->_image);
				$this->_image = $image;
			}
		}
	
		// Create the temporary image to copy to
		$image = $this->_create($width, $height);
	
		// Execute the resize
		if (imagecopyresampled($image, $this->_image, 0, 0, 0, 0, $width, $height, $pre_width, $pre_height))
		{
			// Swap the new image for the old one
			imagedestroy($this->_image);
			$this->_image = $image;
	
			// Reset the width and height
			$this->width  = imagesx($image);
			$this->height = imagesy($image);
		}
	}
	
	protected function _doCrop($width, $height, $offset_x, $offset_y)
	{
		// Create the temporary image to copy to
		$image = $this->_create($width, $height);
	
		// Loads image if not yet loaded
		$this->_loadImage();
	
		// Execute the crop
		if (imagecopyresampled($image, $this->_image, 0, 0, $offset_x, $offset_y, $width, $height, $width, $height))
		{
			// Swap the new image for the old one
			imagedestroy($this->_image);
			$this->_image = $image;
	
			// Reset the width and height
			$this->width  = imagesx($image);
			$this->height = imagesy($image);
		}
	}
	
	protected function _doRotate($degrees)
	{
		if ( ! self::$_bundled)
		{
			throw new Exception(__('This method requires imagerotate, which is only available in the bundled version of GD'));
		}
	
		// Loads image if not yet loaded
		$this->_loadImage();
	
		// Transparent black will be used as the background for the uncovered region
		$transparent = imagecolorallocatealpha($this->_image, 0, 0, 0, 127);
	
		// Rotate, setting the transparent color
		$image = imagerotate($this->_image, 360 - $degrees, $transparent, 1);
	
		// Save the alpha of the rotated image
		imagesavealpha($image, TRUE);
	
		// Get the width and height of the rotated image
		$width  = imagesx($image);
		$height = imagesy($image);
	
		if (imagecopymerge($this->_image, $image, 0, 0, 0, 0, $width, $height, 100))
		{
			// Swap the new image for the old one
			imagedestroy($this->_image);
			$this->_image = $image;
	
			// Reset the width and height
			$this->width  = $width;
			$this->height = $height;
		}
	}
	
	protected function _doFlip($direction)
	{
		// Create the flipped image
		$flipped = $this->_create($this->width, $this->height);
	
		// Loads image if not yet loaded
		$this->_loadImage();
	
		if ($direction === $this::HORIZONTAL)
		{
			for ($x = 0; $x < $this->width; $x++)
			{
			// Flip each row from top to bottom
			imagecopy($flipped, $this->_image, $x, 0, $this->width - $x - 1, 0, 1, $this->height);
			}
		}
		else
		{
			for ($y = 0; $y < $this->height; $y++)
			{
			// Flip each column from left to right
				imagecopy($flipped, $this->_image, 0, $y, 0, $this->height - $y - 1, $this->width, 1);
			}
			}
	
			// Swap the new image for the old one
			imagedestroy($this->_image);
			$this->_image = $flipped;
	
			// Reset the width and height
			$this->width  = imagesx($flipped);
			$this->height = imagesy($flipped);
	}
	
	protected function _doSharpen($amount)
	{
	if ( ! self::$_bundled)
	{
		throw new Exception(__('This method requires imageconvolution, which is only available in the bundled version of GD'));
	}
	
	// Loads image if not yet loaded
	$this->_loadImage();
	
	// Amount should be in the range of 18-10
	$amount = round(abs(-18 + ($amount * 0.08)), 2);
	
	// Gaussian blur matrix
	$matrix = array
	(
			array(-1,   -1,    -1),
			array(-1, $amount, -1),
			array(-1,   -1,    -1),
			);
	
			// Perform the sharpen
			if (imageconvolution($this->_image, $matrix, $amount - 8, 0))
			{
			// Reset the width and height
			$this->width  = imagesx($this->_image);
			$this->height = imagesy($this->_image);
	}
	}
	
	protected function _doReflection($height, $opacity, $fade_in)
	{
	if ( ! self::$_bundled)
	{
	throw new Exception(__('This method requires imagefilter, which is only available in the bundled version of GD'));
	}
	
	// Loads image if not yet loaded
	$this->_loadImage();
	
	// Convert an opacity range of 0-100 to 127-0
	$opacity = round(abs(($opacity * 127 / 100) - 127));
	
	if ($opacity < 127)
	{
	// Calculate the opacity stepping
	$stepping = (127 - $opacity) / $height;
	}
	else
	{
	// Avoid a "divide by zero" error
	$stepping = 127 / $height;
	}
	
	// Create the reflection image
		$reflection = $this->_create($this->width, $this->height + $height);
	
		// Copy the image to the reflection
		imagecopy($reflection, $this->_image, 0, 0, 0, 0, $this->width, $this->height);
	
		for ($offset = 0; $height >= $offset; $offset++)
		{
		// Read the next line down
		$src_y = $this->height - $offset - 1;
	
		// Place the line at the bottom of the reflection
		$dst_y = $this->height + $offset;
	
		if ($fade_in === TRUE)
		{
		// Start with the most transparent line first
		$dst_opacity = round($opacity + ($stepping * ($height - $offset)));
	}
		else
		{
		// Start with the most opaque line first
		$dst_opacity = round($opacity + ($stepping * $offset));
	}
	
	// Create a single line of the image
		$line = $this->_create($this->width, 1);
	
		// Copy a single line from the current image into the line
		imagecopy($line, $this->_image, 0, 0, 0, $src_y, $this->width, 1);
	
		// Colorize the line to add the correct alpha level
		imagefilter($line, IMG_FILTER_COLORIZE, 0, 0, 0, $dst_opacity);
	
		// Copy a the line into the reflection
		imagecopy($reflection, $line, 0, $dst_y, 0, 0, $this->width, 1);
	}
	
	// Swap the new image for the old one
		imagedestroy($this->_image);
		$this->_image = $reflection;
	
		// Reset the width and height
		$this->width  = imagesx($reflection);
		$this->height = imagesy($reflection);
	}
	
	protected function _doWatermark(Image $watermark, $offset_x, $offset_y, $opacity)
	{
	if ( ! self::$_bundled)
	{
	throw new Exception(__('This method requires imagelayereffect, which is only available in the bundled version of GD'));
	}
	
	// Loads image if not yet loaded
	$this->_loadImage();
	
	// Create the watermark image resource
	$overlay = imagecreatefromstring($watermark->render());
	
	// Get the width and height of the watermark
	$width  = imagesx($overlay);
	$height = imagesy($overlay);
	
	if ($opacity < 100)
		{
	// Convert an opacity range of 0-100 to 127-0
	$opacity = round(abs(($opacity * 127 / 100) - 127));
	
	// Allocate transparent white
	$color = imagecolorallocatealpha($overlay, 255, 255, 255, $opacity);
	
		// The transparent image will overlay the watermark
		imagelayereffect($overlay, IMG_EFFECT_OVERLAY);
	
	// Fill the background with transparent white
	imagefilledrectangle($overlay, 0, 0, $width, $height, $color);
	}
	
	// Alpha blending must be enabled on the background!
	imagealphablending($this->_image, TRUE);
	
	if (imagecopy($this->_image, $overlay, $offset_x, $offset_y, 0, 0, $width, $height))
		{
	// Destroy the overlay image
	imagedestroy($overlay);
	}
	}
	
	protected function _doBackground($r, $g, $b, $opacity)
	{
	// Loads image if not yet loaded
	$this->_loadImage();
	
	// Convert an opacity range of 0-100 to 127-0
		$opacity = round(abs(($opacity * 127 / 100) - 127));
	
	// Create a new background
	$background = $this->_create($this->width, $this->height);
	
		// Allocate the color
		$color = imagecolorallocatealpha($background, $r, $g, $b, $opacity);
	
	// Fill the image with white
	imagefilledrectangle($background, 0, 0, $this->width, $this->height, $color);
	
	// Alpha blending must be enabled on the background!
	imagealphablending($background, TRUE);
	
	// Copy the image onto a white background to remove all transparency
	if (imagecopy($background, $this->_image, 0, 0, 0, 0, $this->width, $this->height))
	{
	// Swap the new image for the old one
	imagedestroy($this->_image);
	$this->_image = $background;
	}
	}
	
	protected function _doSave($file, $quality)
	{
	// Loads image if not yet loaded
	$this->_loadImage();
	
	// Get the extension of the file
	$extension = pathinfo($file, PATHINFO_EXTENSION);
	
		// Get the save function and IMAGETYPE
		list($save, $type) = $this->_saveFunction($extension, $quality);
	
		// Save the image to a file
		$status = isset($quality) ? $save($this->_image, $file, $quality) : $save($this->_image, $file);
	
		if ($status === TRUE AND $type !== $this->type)
		{
	// Reset the image type and mime type
	$this->type = $type;
	$this->mime = image_type_to_mime_type($type);
	}
	
	return TRUE;
	}
	
	protected function _doRender($type, $quality)
	{
	// Loads image if not yet loaded
	$this->_loadImage();
	
	// Get the save function and IMAGETYPE
	list($save, $type) = $this->_saveFunction($type, $quality);
	
	// Capture the output
	ob_start();
	
	// Render the image
	$status = isset($quality) ? $save($this->_image, NULL, $quality) : $save($this->_image, NULL);
	
		if ($status === TRUE AND $type !== $this->type)
		{
		// Reset the image type and mime type
		$this->type = $type;
		$this->mime = image_type_to_mime_type($type);
	}
	
		return ob_get_clean();
	}
	
	/**
	* Get the GD saving function and image type for this extension.
	* Also normalizes the quality setting
	*
	* @param   string   image type: png, jpg, etc
	* @param   integer  image quality
		* @return  array    save function, IMAGETYPE_* constant
		* @throws  Exception
		*/
		protected function _saveFunction($extension, & $quality)
		{
		switch (strtolower($extension))
		{
		case 'jpg':
		case 'jpeg':
		// Save a JPG file
		$save = 'imagejpeg';
		$type = IMAGETYPE_JPEG;
		break;
		case 'gif':
		// Save a GIF file
		$save = 'imagegif';
		$type = IMAGETYPE_GIF;
	
		// GIFs do not a quality setting
		$quality = NULL;
		break;
		case 'png':
		// Save a PNG file
		$save = 'imagepng';
		$type = IMAGETYPE_PNG;
	
		// Use a compression level of 9 (does not affect quality!)
		$quality = 9;
		break;
		default:
		throw new Exception(__('Installed GD does not support :ext images',array("ext"=>$extension)));
		break;
		}
	
		return array($save, $type);
		}
	
		/**
		* Create an empty image with the given width and height.
		*
		* @param   integer   image width
		* @param   integer   image height
		* @return  resource
		*/
		protected function _create($width, $height)
		{
		// Create an empty image
		$image = imagecreatetruecolor($width, $height);
	
		// Do not apply alpha blending
		imagealphablending($image, FALSE);
	
		// Save alpha levels
		imagesavealpha($image, TRUE);
	
		return $image;
		}
	

} // End self::
