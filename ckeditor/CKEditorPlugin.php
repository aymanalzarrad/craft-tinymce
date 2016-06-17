<?php
namespace Craft;

/**
 * Class CKEditorPlugin
 *
 * Thank you for using CKEditor for Craft!
 * @see https://github.com/benjamminf/craft-ckeditor
 * @package Craft
 */
class CKEditorPlugin extends BasePlugin
{
	public function getName()
	{
		return "CKEditor";
	}

	public function getDescription()
	{
		return Craft::t("Adds CKEditor as a rich text field type to Craft");
	}

	public function getVersion()
	{
		return '0.1.0';
	}

	public function getCraftMinimumVersion()
	{
		return '2.5';
	}

	public function getPHPMinimumVersion()
	{
		return '5.4';
	}

	public function getSchemaVersion()
	{
		return '0.1.0';
	}

	public function getDeveloper()
	{
		return 'Benjamin Fleming';
	}

	public function getDeveloperUrl()
	{
		return 'http://benjamminf.github.io';
	}

	public function getDocumentationUrl()
	{
		return 'https://github.com/benjamminf/craft-ckeditor/wiki';
	}

	public function getReleaseFeedUrl()
	{
		return 'https://raw.githubusercontent.com/benjamminf/craft-ckeditor/master/releases.json';
	}

	public function isCraftRequiredVersion()
	{
		return version_compare(craft()->getVersion(), $this->getCraftMinimumVersion(), '>=');
	}

	public function isPHPRequiredVersion()
	{
		return version_compare(PHP_VERSION, $this->getPHPMinimumVersion(), '>=');
	}

	/**
	 * Checks for environment compatibility when installing.
	 *
	 * @return bool
	 */
	public function onBeforeInstall()
	{
		$craftCompatible = $this->isCraftRequiredVersion();
		$phpCompatible = $this->isPHPRequiredVersion();

		if(!$craftCompatible)
		{
			self::log(Craft::t("This plugin is not compatible with Craft {version} - requires Craft {required} or greater", [
				'version' => craft()->getVersion(),
				'required' => $this->getCraftMinimumVersion(),
			]), LogLevel::Error, true);
		}

		if(!$phpCompatible)
		{
			self::log(Craft::t("This plugin is not compatible with PHP {version} - requires PHP {required} or greater", [
				'version' => PHP_VERSION,
				'required' => $this->getPHPMinimumVersion(),
			]), LogLevel::Error, true);
		}

		return $craftCompatible && $phpCompatible;
	}
}
