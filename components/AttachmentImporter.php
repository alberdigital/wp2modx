<?php

namespace app\components;

use yii\base\Component;
use Yii;

class AttachmentImporter extends Component
{
	public $parallelDownloads = 3;
	public $imagesPath = 'images/';
	public $replaceFiles = true;

	public function importImages($urls)
	{
		// Notify the number of images to import.
		echo Yii::t('app', 'Importing {numImages} images...', [
			'numImages' => count($urls)
		]) . "\n";
		
		$urlSet = [];
		foreach ($urls as $url)
		{
			// Add the file to the next parallel loading.
			$filePath = $this->getFilePath($url);
			if ($this->replaceFiles || !file_exists($filePath))
			{
				$urlSet[] = $url;
			}
			
			// Init a parallel loading.
			if (count($urlSet) >= $this->parallelDownloads)
			{
				$this->multiImportImagesSet($urlSet);
				$urlSet = [];
			}
		}
		// Init the last incomplete set loading.
		if (count($urlSet) > 0)
		{
			$this->multiImportImagesSet($urlSet);
		}
	}

	/**
	 * Import a single image.
	 *
	 * @param string $url
	 *        	the image url.
	 */
	public function importImage($url)
	{
		$fileName = basename($url);
		$success = true;
		
		$ch = curl_init($url);
		$filePath = $this->imagesPath . $fileName;
		$fp = fopen($filePath, 'wb');
		curl_setopt($ch, CURLOPT_FILE, $fp);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		if (!$result = curl_exec($ch))
		{
			echo curl_error($ch) . ' ' . $url . "\n";
			$success = false;
		}
		curl_close($ch);
		fclose($fp);
		
		// Delete file if something failed.
		if ($success)
		{
			echo Yii::t('app', '{fileId} imported', [
				'fileId' => $fileName
			]) . "\n";
		} elseif (file_exists($filePath))
		{
			unlink($filePath);
		}
	}

	private function getFilePath($url)
	{
		$fileName = basename($url);
		$filePath = $this->imagesPath . $fileName;
		
		return $filePath;
	}
	
	/**
	 * Import a set of images in parallel.
	 *
	 * @param array $urls
	 *        	an array of urls to load in parallel.
	 */
	private function multiImportImagesSet($urls)
	{
		
		$chs = [];
		$chIndex = 0;
		foreach ($urls as $url)
		{
			$filePath = $this->getFilePath($url);
			
			echo $url . "\n";
			
			$fp = fopen($filePath, 'wb');
			
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_FILE, $fp);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
			
			$chs[] = $ch;
		}
		
		if (count($chs) > 0)
		{
			// Crea el recurso cURL múltiple
			$mh = curl_multi_init();
			
			// Añade los dos recursos
			foreach ($chs as $ch)
			{
				curl_multi_add_handle($mh, $ch);
			}
			
			$active = null;
			// Ejecuta los recursos
			do
			{
				$mrc = curl_multi_exec($mh, $active);
			} while ($active > 0);
			
			while ($active && $mrc == CURLM_OK)
			{
				if (curl_multi_select($mh) != -1)
				{
					do
					{
						$mrc = curl_multi_exec($mh, $active);
					} while ($active > 0);
				}
			}
			
			// Cierra los recursos
			foreach ($chs as $ch)
			{
				curl_multi_remove_handle($mh, $ch);
			}
			curl_multi_close($mh);
		}
	}

}

?>