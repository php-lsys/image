<?php
/**
 * lsys image
 * @author     Lonely <shan.liu@msn.com>
 * @copyright  (c) 2017 Lonely <shan.liu@msn.com>
 * @copyright  (c) 2007-2012 Kohana Team
 * @license    http://www.apache.org/licenses/LICENSE-2.0
 * @license    http://kohanaframework.org/license
 */
namespace LSYS;
use function LSYS\Image\__;
abstract class Image{
	
	// Resizing contraints
	const NONE    = 0x01;
	const WIDTH   = 0x02;
	const HEIGHT  = 0x03;
	const AUTO    = 0x04;
	const INVERSE = 0x05;
	const REMOVE  = 0x06;
	const FILL  = 0x07;
	const TOP_REMOVE=0x08;

	// Flipping directions
	const HORIZONTAL = 0x11;
	const VERTICAL   = 0x12;


	/**
	 * @var  string  default driver: GD etc
	 */
	public static $default_driver = 'GD';

	// Status of the driver check
	protected static $_checked = FALSE;

	/**
	 * Loads an image and prepares it for manipulation.
	 *
	 *     $image = Image::factory('upload/test.jpg');
	 *
	 * @param   string   image file path
	 * @param   string   driver type: GD, ImageMagick, etc
	 * @return  Image
	 * @uses    Image::$default_driver
	 */
	public static function factory(string $file, $driver = NULL)
	{
		if ($driver === NULL)
		{
			// Use the default driver
			$driver = Image::$default_driver;
		}

		// Set the class name
		$class = '\LSYS\Image\\'.$driver;

		return new $class($file);
	}

	/**
	 * @var  string  image file path
	 */
	public $file;

	/**
	 * @var  integer  image width
	 */
	public $width;

	/**
	 * @var  integer  image height
	 */
	public $height;

	/**
	 * @var  integer  one of the IMAGETYPE_* constants
	 */
	public $type;

	/**
	 * Loads information about the image. Will throw an exception if the image
	 * does not exist or is not an image.
	 *
	 * @param   string   image file path
	 * @return  void
	 * @throws  Exception
	 */
	public function __construct(string $file)
	{
		try
		{
			// Get the real path to the file
			$file = realpath(trim($file));
			// Get the image information
			$info = getimagesize($file);
		}
		catch (\Exception $e)
		{
			// Ignore all errors while reading the image
		}

		if (empty($file) OR empty($info))
		{
			throw new Exception(__('Not an image or invalid image: :file',array(":file"=>$file)));
		}

		// Store the image information
		$this->file   = $file;
		$this->width  = $info[0];
		$this->height = $info[1];
		$this->type   = $info[2];
		$this->mime   = image_type_to_mime_type($this->type);
	}

	/**
	 * Render the current image.
	 *
	 *     echo $image;
	 *
	 * [!!] The output of this function is binary and must be rendered with the
	 * appropriate Content-Type header or it will not be displayed correctly!
	 *
	 * @return  string
	 */
	public function __toString()
	{
		try
		{
			// Render the current image
			return $this->render();
		}
		catch (\Exception $e)
		{
			// Showing any kind of error will be "inside" image data
			return '';
		}
	}

	/**
	 * Resize the image to the given size. Either the width or the height can
	 * be omitted and the image will be resized proportionally.
	 *
	 *     // Resize to 200 pixels on the shortest side
	 *     $image->resize(200, 200);
	 *
	 *     // Resize to 200x200 pixels, keeping aspect ratio
	 *     $image->resize(200, 200, Image::INVERSE);
	 *
	 *     // Resize to 500 pixel width, keeping aspect ratio
	 *     $image->resize(500, NULL);
	 *
	 *     // Resize to 500 pixel height, keeping aspect ratio
	 *     $image->resize(NULL, 500);
	 *
	 *     // Resize to 200x500 pixels, ignoring aspect ratio
	 *     $image->resize(200, 500, Image::NONE);
	 *
	 * @param   integer  new width
	 * @param   integer  new height
	 * @param   integer  master dimension
	 * @return  $this
	 * @uses    Image::_do_resize
	 */
	public function resize(int $width = NULL,int $height = NULL,int  $master = NULL)
	{
		if ($master==Image::TOP_REMOVE){
			if ($this->width/$width>$this->height/$height){
				$this->resize($width,$height,Image::HEIGHT);
				$this->crop($width, $height,0,0);
			}else{
				$this->resize($width,$height,Image::WIDTH);
				$offy=(int)($this->height-$height)/2;
				$this->crop($width, $height,0,$offy);
			}
			return $this;
		}
		if ($master==Image::REMOVE){
			if ($this->width/$width>$this->height/$height){
				$this->resize($width,$height,Image::HEIGHT);
				$offx=(int)($this->width-$width)/2;
				$this->crop($width, $height,$offx,0);
			}else{
				$this->resize($width,$height,Image::WIDTH);
				$offy=(int)($this->height-$height)/2;
				$this->crop($width, $height,0,$offy);
			}
			return $this;
		}

		if ($master==Image::FILL){
			$image = clone $this;
			$image->resize($width,$height,Image::AUTO);
			$this->resize($width,$height,Image::NONE);
			$this->clear();
			$this->watermark($image);
			return $this;
		}

		if ($master === NULL)
		{
			// Choose the master dimension automatically
			$master = Image::AUTO;
		}
		// Image::WIDTH and Image::HEIGHT depricated. You can use it in old projects,
		// but in new you must pass empty value for non-master dimension
		elseif ($master == Image::WIDTH AND ! empty($width))
		{
			$master = Image::AUTO;

			// Set empty height for backvard compatibility
			$height = NULL;
		}
		elseif ($master == Image::HEIGHT AND ! empty($height))
		{
			$master = Image::AUTO;

			// Set empty width for backvard compatibility
			$width = NULL;
		}

		if (empty($width))
		{
			if ($master === Image::NONE)
			{
				// Use the current width
				$width = $this->width;
			}
			else
			{
				// If width not set, master will be height
				$master = Image::HEIGHT;
			}
		}

		if (empty($height))
		{
			if ($master === Image::NONE)
			{
				// Use the current height
				$height = $this->height;
			}
			else
			{
				// If height not set, master will be width
				$master = Image::WIDTH;
			}
		}

		switch ($master)
		{
			case Image::AUTO:
				// Choose direction with the greatest reduction ratio
				$master = ($this->width / $width) > ($this->height / $height) ? Image::WIDTH : Image::HEIGHT;
				break;
			case Image::INVERSE:
				// Choose direction with the minimum reduction ratio
				$master = ($this->width / $width) > ($this->height / $height) ? Image::HEIGHT : Image::WIDTH;
				break;
		}

		switch ($master)
		{
			case Image::WIDTH:
				// Recalculate the height based on the width proportions
				$height = $this->height * $width / $this->width;
				break;
			case Image::HEIGHT:
				// Recalculate the width based on the height proportions
				$width = $this->width * $height / $this->height;
				break;
		}

		// Convert the width and height to integers
		$width  = round($width);
		$height = round($height);

		$this->_doResize($width, $height);

		return $this;
	}

	/**
	 * Crop an image to the given size. Either the width or the height can be
	 * omitted and the current width or height will be used.
	 *
	 * If no offset is specified, the center of the axis will be used.
	 * If an offset of TRUE is specified, the bottom of the axis will be used.
	 *
	 *     // Crop the image to 200x200 pixels, from the center
	 *     $image->crop(200, 200);
	 *
	 * @param   integer  new width
	 * @param   integer  new height
	 * @param   mixed    offset from the left
	 * @param   mixed    offset from the top
	 * @return  $this
	 * @uses    Image::_do_crop
	 */
	public function crop(int $width, int $height, int $offset_x = NULL, int $offset_y = NULL)
	{
		if ($width > $this->width)
		{
			// Use the current width
			$width = $this->width;
		}

		if ($height > $this->height)
		{
			// Use the current height
			$height = $this->height;
		}

		if ($offset_x === NULL)
		{
			// Center the X offset
			$offset_x = round(($this->width - $width) / 2);
		}
		elseif ($offset_x === TRUE)
		{
			// Bottom the X offset
			$offset_x = $this->width - $width;
		}
		elseif ($offset_x < 0)
		{
			// Set the X offset from the right
			$offset_x = $this->width - $width + $offset_x;
		}

		if ($offset_y === NULL)
		{
			// Center the Y offset
			$offset_y = round(($this->height - $height) / 2);
		}
		elseif ($offset_y === TRUE)
		{
			// Bottom the Y offset
			$offset_y = $this->height - $height;
		}
		elseif ($offset_y < 0)
		{
			// Set the Y offset from the bottom
			$offset_y = $this->height - $height + $offset_y;
		}

		// Determine the maximum possible width and height
		$max_width  = $this->width  - $offset_x;
		$max_height = $this->height - $offset_y;

		if ($width > $max_width)
		{
			// Use the maximum available width
			$width = $max_width;
		}

		if ($height > $max_height)
		{
			// Use the maximum available height
			$height = $max_height;
		}

		$this->_doCrop($width, $height, $offset_x, $offset_y);

		return $this;
	}

	/**
	 * Rotate the image by a given amount.
	 *
	 *     // Rotate 45 degrees clockwise
	 *     $image->rotate(45);
	 *
	 *     // Rotate 90% counter-clockwise
	 *     $image->rotate(-90);
	 *
	 * @param   integer   degrees to rotate: -360-360
	 * @return  $this
	 * @uses    Image::_do_rotate
	 */
	public function rotate(int $degrees)
	{
		// Make the degrees an integer
		$degrees = (int) $degrees;

		if ($degrees > 180)
		{
			do
			{
				// Keep subtracting full circles until the degrees have normalized
				$degrees -= 360;
			}
			while($degrees > 180);
		}

		if ($degrees < -180)
		{
			do
			{
				// Keep adding full circles until the degrees have normalized
				$degrees += 360;
			}
			while($degrees < -180);
		}

		$this->_doRotate($degrees);

		return $this;
	}

	/**
	 * Flip the image along the horizontal or vertical axis.
	 *
	 *     // Flip the image from top to bottom
	 *     $image->flip(Image::HORIZONTAL);
	 *
	 *     // Flip the image from left to right
	 *     $image->flip(Image::VERTICAL);
	 *
	 * @param   integer  direction: Image::HORIZONTAL, Image::VERTICAL
	 * @return  $this
	 * @uses    Image::_do_flip
	 */
	public function flip(int $direction)
	{
		if ($direction !== Image::HORIZONTAL)
		{
			// Flip vertically
			$direction = Image::VERTICAL;
		}

		$this->_doFlip($direction);

		return $this;
	}

	/**
	 * Sharpen the image by a given amount.
	 *
	 *     // Sharpen the image by 20%
	 *     $image->sharpen(20);
	 *
	 * @param   integer  amount to sharpen: 1-100
	 * @return  $this
	 * @uses    Image::_do_sharpen
	 */
	public function sharpen(int  $amount)
	{
		// The amount must be in the range of 1 to 100
		$amount = min(max($amount, 1), 100);

		$this->_doSharpen($amount);

		return $this;
	}

	/**
	 * Add a reflection to an image. The most opaque part of the reflection
	 * will be equal to the opacity setting and fade out to full transparent.
	 * Alpha transparency is preserved.
	 *
	 *     // Create a 50 pixel reflection that fades from 0-100% opacity
	 *     $image->reflection(50);
	 *
	 *     // Create a 50 pixel reflection that fades from 100-0% opacity
	 *     $image->reflection(50, 100, TRUE);
	 *
	 *     // Create a 50 pixel reflection that fades from 0-60% opacity
	 *     $image->reflection(50, 60, TRUE);
	 *
	 * [!!] By default, the reflection will be go from transparent at the top
	 * to opaque at the bottom.
	 *
	 * @param   integer   reflection height
	 * @param   integer   reflection opacity: 0-100
	 * @param   boolean   TRUE to fade in, FALSE to fade out
	 * @return  $this
	 * @uses    Image::_do_reflection
	 */
	public function reflection(int $height = NULL, int $opacity = 100, bool $fade_in = FALSE)
	{
		if ($height === NULL OR $height > $this->height)
		{
			// Use the current height
			$height = $this->height;
		}

		// The opacity must be in the range of 0 to 100
		$opacity = min(max($opacity, 0), 100);

		$this->_doReflection($height, $opacity, $fade_in);

		return $this;
	}

	/**
	 * Add a watermark to an image with a specified opacity. Alpha transparency
	 * will be preserved.
	 *
	 * If no offset is specified, the center of the axis will be used.
	 * If an offset of TRUE is specified, the bottom of the axis will be used.
	 *
	 *     // Add a watermark to the bottom right of the image
	 *     $mark = Image::factory('upload/watermark.png');
	 *     $image->watermark($mark, TRUE, TRUE);
	 *
	 * @param   object   watermark Image instance
	 * @param   integer  offset from the left
	 * @param   integer  offset from the top
	 * @param   integer  opacity of watermark: 1-100
	 * @return  $this
	 * @uses    Image::_do_watermark
	 */
	public function watermark(Image $watermark,int  $offset_x = NULL,int  $offset_y = NULL, int $opacity = 100)
	{
		if ($offset_x === NULL)
		{
			// Center the X offset
			$offset_x = round(($this->width - $watermark->width) / 2);
		}
		elseif ($offset_x === TRUE)
		{
			// Bottom the X offset
			$offset_x = $this->width - $watermark->width;
		}
		elseif ($offset_x < 0)
		{
			// Set the X offset from the right
			$offset_x = $this->width - $watermark->width + $offset_x;
		}

		if ($offset_y === NULL)
		{
			// Center the Y offset
			$offset_y = round(($this->height - $watermark->height) / 2);
		}
		elseif ($offset_y === TRUE)
		{
			// Bottom the Y offset
			$offset_y = $this->height - $watermark->height;
		}
		elseif ($offset_y < 0)
		{
			// Set the Y offset from the bottom
			$offset_y = $this->height - $watermark->height + $offset_y;
		}

		// The opacity must be in the range of 1 to 100
		$opacity = min(max($opacity, 1), 100);

		$this->_doWatermark($watermark, $offset_x, $offset_y, $opacity);

		return $this;
	}

	/**
	 * Set the background color of an image. This is only useful for images
	 * with alpha transparency.
	 *
	 *     // Make the image background black
	 *     $image->background('#000');
	 *
	 *     // Make the image background black with 50% opacity
	 *     $image->background('#000', 50);
	 *
	 * @param   string   hexadecimal color value
	 * @param   integer  background opacity: 0-100
	 * @return  $this
	 * @uses    Image::_do_background
	 */
	public function background($color,int  $opacity = 100)
	{
		if ($color[0] === '#')
		{
			// Remove the pound
			$color = substr($color, 1);
		}

		if (strlen($color) === 3)
		{
			// Convert shorthand into longhand hex notation
			$color = preg_replace('/./', '$0$0', $color);
		}

		// Convert the hex into RGB values
		list ($r, $g, $b) = array_map('hexdec', str_split($color, 2));

		// The opacity must be in the range of 0 to 100
		$opacity = min(max($opacity, 0), 100);

		$this->_doBackground($r, $g, $b, $opacity);

		return $this;
	}

	/**
	 * Save the image. If the filename is omitted, the original image will
	 * be overwritten.
	 *
	 *     // Save the image as a PNG
	 *     $image->save('saved/cool.png');
	 *
	 *     // Overwrite the original image
	 *     $image->save();
	 *
	 * [!!] If the file exists, but is not writable, an exception will be thrown.
	 *
	 * [!!] If the file does not exist, and the directory is not writable, an
	 * exception will be thrown.
	 *
	 * @param   string   new image path
	 * @param   integer  quality of image: 1-100
	 * @return  boolean
	 * @uses    Image::_save
	 * @throws  Exception
	 */
	public function save(?string $file = NULL, $quality = 100):bool
	{
		if ($file === NULL)
		{
			// Overwrite the file
			$file = $this->file;
		}

		if(empty($file)){
			throw new Exception('Need to file name');
		}

		if (is_file($file))
		{
			if ( ! is_writable($file))
			{
				throw new Exception(__('File must be writable: :file',array(":file"=>$file)));
			}
		}
		else
		{
			// Get the directory of the file
			$directory = realpath(pathinfo($file, PATHINFO_DIRNAME));

			if ( ! is_dir($directory) OR ! is_writable($directory))
			{
				throw new Exception(__('Directory must be writable:  :directory',array(":directory"=>$directory)));
			}
		}

		// The quality must be in the range of 1 to 100
		$quality = min(max($quality, 1), 100);

		return $this->_doSave($file, $quality);
	}

	/**
	 * Render the image and return the binary string.
	 *
	 *     // Render the image at 50% quality
	 *     $data = $image->render(NULL, 50);
	 *
	 *     // Render the image as a PNG
	 *     $data = $image->render('png');
	 *
	 * @param   string   image type to return: png, jpg, gif, etc
	 * @param   integer  quality of image: 1-100
	 * @return  string
	 * @uses    Image::_do_render
	 */
	public function render(?string $type = NULL, $quality = 100):string
	{
		if ($type === NULL)
		{
			// Use the current image type
			$type = image_type_to_extension($this->type, FALSE);
		}

		return $this->_doRender($type, $quality);
	}
	/**
	 * Execute a resize.
	 *
	 * @param   integer  new width
	 * @param   integer  new height
	 * @return  void
	 */
	abstract public function clear():void;
	/**
	 * Execute a resize.
	 *
	 * @param   integer  new width
	 * @param   integer  new height
	 * @return  void
	 */
	abstract protected function _doResize(int $width,int  $height);

	/**
	 * Execute a crop.
	 *
	 * @param   integer  new width
	 * @param   integer  new height
	 * @param   integer  offset from the left
	 * @param   integer  offset from the top
	 * @return  void
	 */
	abstract protected function _doCrop(int $width, int $height,int  $offset_x, int $offset_y);

	/**
	 * Execute a rotation.
	 *
	 * @param   integer  degrees to rotate
	 * @return  void
	 */
	abstract protected function _doRotate(int $degrees);

	/**
	 * Execute a flip.
	 *
	 * @param   integer  direction to flip
	 * @return  void
	 */
	abstract protected function _doFlip(int $direction);

	/**
	 * Execute a sharpen.
	 *
	 * @param   integer  amount to sharpen
	 * @return  void
	 */
	abstract protected function _doSharpen(int $amount);

	/**
	 * Execute a reflection.
	 *
	 * @param   integer   reflection height
	 * @param   integer   reflection opacity
	 * @param   boolean   TRUE to fade out, FALSE to fade in
	 * @return  void
	 */
	abstract protected function _doReflection(int $height,int  $opacity, bool $fade_in);

	/**
	 * Execute a watermarking.
	 *
	 * @param   object   watermarking Image
	 * @param   integer  offset from the left
	 * @param   integer  offset from the top
	 * @param   integer  opacity of watermark
	 * @return  void 
	*/
	abstract protected function _doWatermark(Image $image, int $offset_x, int $offset_y,int  $opacity);
	
	
	/**
	 * Execute a background.
	 *
	 * @param   integer  red
	 * @param   integer  green
	 * @param   integer  blue
	 * @param   integer  opacity
	 * @return void
	 */
	abstract protected function _doBackground(int $r, int $g, int $b,int  $opacity);

	/**
	 * Execute a save.
	 *
	 * @param   string   new image filename
	 * @param   integer  quality
	 * @return  boolean
	 */
	abstract protected function _doSave(string $file,int $quality):bool;

	/**
	 * Execute a render.
	 *
	 * @param   string    image type: png, jpg, gif, etc
	 * @param   integer   quality
	 * @return  string
	 */
	abstract protected function _doRender(string $type,int $quality):string;

} // End Image
