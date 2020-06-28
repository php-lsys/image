# 实现对图片的简单操作

使用示例:
```
use LSYS\Image;
//压缩图片
Image::factory('test.png')->resize(100)->save();
//裁剪图片
Image::factory('test.png')->crop($y=100,$w=100,$x=1,$y=1)->save('./crop.png');
```