<?php
namespace Craft;

class CKEditorController extends BaseController
{
	public function actionConfig()
	{
		$configFile = craft()->request->getSegment(-1);
		$configPath = craft()->path->getConfigPath() . 'ckeditor/' . $configFile;
		$config = IOHelper::getFileContents($configPath);

		HeaderHelper::setContentTypeByExtension('js');

		echo $config;

		craft()->end();
	}
}
