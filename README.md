WP2MODX
======================

This is a simple tool to import Wordpress posts into a MODx site. It takes an XML file from a Wordpress export and uses it to add new rows to the site_content table of your MODx database. It also imports images.

Installation
-------------------------
- Install PHP and add it to your path.
- Set your MODx database config in config/db.php.
- From the application base path, run: $ yii wp-to-modx <xml-file-path> 

Usage
-------------------------
Example:

$ yii wp-to-modx/index 'aux-resources/mysite.wordpress.2015-10-03.xml' --deletePrevious=true --imagesPath=../mhh/wwwroot/assets/images/news/ --imagesLocalUrl=assets/images/news/ --modxTemplate=12