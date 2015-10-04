<?php

/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */
namespace app\commands;

use yii\console\Controller;
use app\models\ModxSiteContent;
use Yii;
use yii\base\Object;
use yii\base\Exception;
use Faker\Provider\DateTime;

/**
 * Migrates all posts from a Wordpress export XML files to resources in a MODx database
 */
class WpToModxController extends Controller
{
	public $deletePrevious = false;
	
	public function options($actionID)
	{
		return [
			'deletePrevious'
		];
	}
	
	/**
	 * Performs the migration
	 *
	 * @param string $path
	 *        	the location of the XML file.
	 */
	public function actionIndex(
			$path = '/../blog-resources/blogmarbellahillshomes-franais.wordpress.2015-10-03.xml', 
			$importImages = true,
			$parentResourceAlias = 'news-fr')
	{
		$fullPath = Yii::$app->basePath . $path;
		
		$fullXml = simplexml_load_file($fullPath);
		$xml = $fullXml->channel;
		
		echo $xml->title[0] . "\n";
		
		// Get news parent.
		$parentResource = ModxSiteContent::getResourceByAlias($parentResourceAlias);
		if ($parentResource == null)
		{
			throw new \Exception('News parent resource not found.');
		}
		
		// Delete previous news.
		if ($this->deletePrevious)
		{
			$parentResource->deleteChildren();
		}
		
		foreach ($xml->item as $entryIndex => $entry)
		{
			$entryOut = '';
			$knownType = true;
			
			// Get the namespaces.
			$namespaces = $entry->getNameSpaces(true);
			$wp = $entry->children($namespaces['wp']);
			
			// var_export($wp->post_type);
			
			switch ($wp->post_type[0]) {
				case 'post' :
					
					// Get data from the xml entry.
					$modxContent = new ModxSiteContent();
					$wpTitle = (string) $entry->title[0];
					$wpDate = (string) $wp->post_date[0];
					$pubDate = new \DateTime($wpDate);

					// Output to console.
					$entryOut .= Yii::t('console', 'Title') . ': ' . $wpTitle . "\n";
					$entryOut .= Yii::t('console', 'Date') . ': ' . $wpDate . "\n";
					$entryOut .= Yii::t('console', 'Content') . ': ' . $wpDate . "\n";
					
					// Set modx resource.
					$modxContent->parent = $parentResource->id;
					$modxContent->pagetitle = $wpTitle;
					$modxContent->pub_date = $pubDate->getTimestamp();
					$modxContent->published = '1';
					
					
					if (!$modxContent->save())
					{
						throw new \Exception('Could not save content ' . print_r($modxContent->errors, true));
					}
					
					break;
				
				case 'image' :
					$entryOut .= $entry->title[0];
					break;
				
				default :
					$knownType = false;
					break;
			}
			
			if ($knownType)
			{
				echo $entryOut . "\n";
			}
		}
	}
}
