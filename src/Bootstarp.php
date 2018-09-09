<?php
/**
 * lsys image
 * @author     Lonely <shan.liu@msn.com>
 * @copyright  (c) 2017 Lonely <shan.liu@msn.com>
 * @license    http://www.apache.org/licenses/LICENSE-2.0
 */
namespace LSYS\Image{
	function __($string, array $values = NULL, $domain = "default")
	{
		$i18n=\LSYS\I18n\DI::get()->i18n(__DIR__."/I18n/");
		return $i18n->__($string,  $values , $domain );
	}
}

