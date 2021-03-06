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
use app\components\AttachmentImporter;
use yii\base\Exception;
use Faker\Provider\DateTime;

/**
 * Migrates all posts from a Wordpress export XML file to resources in a MODx database
 */
class WpToModxController extends Controller
{
	public $deletePrevious = false;
	public $importImages = true;
	public $parallelImageDownloads = 3;
	public $imagesPath = '/aux-resources/images/';
	public $replaceImages = false;
	public $imagesLocalUrl = 'assets/images/';
	public $modxTemplate = null;
	public $aliasPrefix = '';
	public $aliasSuffix = '';
	public $uriPrefix = '';
	public $uriSuffix = '';
	public $resetCounter = false;
	public $freezeUri = false;

	public function options($actionID)
	{
		return [
			'deletePrevious',
			'importImages',
			'imagesPath',
			'imagesLocalUrl',
			'modxTemplate',
			'aliasPrefix',
			'aliasSuffix',
			'uriPrefix',
			'uriSufix',
			'resetCounter',
			'freezeUri'
		];
	}

	/**
	 * Performs the migration
	 *
	 * @param string $path
	 *        	the location of the XML file.
	 */
	public function actionIndex($path = null, $parentAlias = 'news-fr')
	{
		$output = '';
		
		$fullXml = simplexml_load_file($path);
		$xml = $fullXml->channel;
		
		echo $xml->title[0] . "\n";
		
		// Get news parent.
		$parentResource = ModxSiteContent::getResourceByAlias($parentAlias);
		if ($parentResource == null)
		{
			throw new \Exception('News parent resource not found.');
		}
		
		// Delete previous news.
		if ($this->deletePrevious)
		{
			$parentResource->deleteChildren();
			$output .= Yii::t('app', 'Previous resources have been deleted.') . "\n";
			
			if ($this->resetCounter) {
				ModxSiteContent::resetCounter();
				$output .= Yii::t('app', 'Site content id auto-increment has been reseted.') . "\n";
			}
		}
		
		$imagesUrls = $this->getAttachmentsUrls($xml);
		
		$entriesCount = 0;
		foreach ($xml->item as $entryIndex => $entry)
		{
			$entryOut = '';
			$knownType = true;
			
			// Get the namespaces.
			$namespaces = $entry->getNameSpaces(true);
			$wp = $entry->children($namespaces['wp']);
			$entryContent = $entry->children($namespaces['content']);
			
			// var_export($wp->post_type);
			
			switch ($wp->post_type[0]) {
				case 'post':
					
					// Get data from the xml entry.
					$modxResource = new ModxSiteContent();
					$wpTitle = (string) $entry->title[0];
					$wpDate = (string) $wp->post_date[0];
					$pubDate = new \DateTime($wpDate);
					$entryContentText = (string) $entryContent;
					$postName = (string) $wp->post_name[0];
					
					// Replace urls.
					foreach ($imagesUrls as $imageUrl)
					{
						$imageUrlNoProtocol = preg_replace('(^https?://)', '', $imageUrl);
						$entryContentText = preg_replace(
								'(https?://' . preg_quote($imageUrlNoProtocol) . ')', 
								$this->imagesLocalUrl . basename($imageUrl), 
								$entryContentText);
					}
					
					// Set paragraphs.
					$entryContentParagraphs = explode("\n", $entryContentText);
					$entryContentMulitParagraph = '<p>' . implode('</p><p>', $entryContentParagraphs) . '</p>';
					
					// Output to console.
					// $entryOut .= Yii::t('console', 'Title') . ': ' . $wpTitle . "\n";
					// $entryOut .= Yii::t('console', 'Date') . ': ' . $pubDate->getTimestamp() . "\n";
					// $entryOut .= Yii::t('console', 'Content') . ': ' . $entryContent . "\n";
					
					// Set modx resource.
					$modxResource->parent = $parentResource->id;
					$modxResource->pagetitle = $wpTitle;
					$modxResource->publishedon = $pubDate->getTimestamp();
					$modxResource->published = '1';
					$modxResource->content = $entryContentMulitParagraph;
					$modxResource->hidemenu = '1';
					$modxResource->alias = $this->aliasPrefix . $postName . $this->aliasSuffix;
					$modxResource->template = isset($this->modxTemplate) ? $this->modxTemplate : $parentResource->template;
					if ($this->freezeUri)
					{
						$modxResource->uri_override = 1;
						$modxResource->uri = $this->uriPrefix . $postName . $this->uriSuffix;
					}
					
					if ($modxResource->save())
					{
						$entriesCount ++;
					} else
					{
						throw new \Exception('Could not save content ' . print_r($modxResource->errors, true));
					}
					
					break;
				
				default:
					$knownType = false;
					break;
			}
			
			if ($knownType)
			{
				$output .= $entryOut . (strlen($entryOut) > 0 ? "\n" : '');
			}
		}
		
		// Import images.
		if ($this->importImages)
		{
			$attachmentImporter = new AttachmentImporter([
				'parallelDownloads' => $this->parallelImageDownloads,
				'imagesPath' => $this->imagesPath,
				'replaceFiles' => $this->replaceImages
			]);
			$attachmentImporter->importImages($imagesUrls);
		}
		
		$output .= Yii::t('app', 'Entries count: {entriesCount}', [
			'entriesCount' => $entriesCount
		]) . "\n";
		
		echo $output;
	}

	/**
	 * Searches for attachments in the xml and returns the urls.
	 * 
	 * @param \SimpleXmlElement $xml
	 *        	The XML.
	 * @return array An array with all the attachment's urls.
	 */
	public function getAttachmentsUrls($xml)
	{
		$urls = [];
		
		foreach ($xml->item as $entryIndex => $entry)
		{
			// Get the namespaces.
			$namespaces = $entry->getNameSpaces(true);
			$wp = $entry->children($namespaces['wp']);
			$entryContent = $entry->children($namespaces['content']);
			
			switch ($wp->post_type[0]) {
				case 'attachment':
					$imageUrl = (string) $wp->attachment_url[0];
					$urls[] = $imageUrl;
					break;
			}
		}
		
		return $urls;
	}

}
