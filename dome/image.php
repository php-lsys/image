<?php
use LSYS\Image;
include __DIR__."/Bootstarp.php";
Image::factory('test.png')->resize(100)->save();