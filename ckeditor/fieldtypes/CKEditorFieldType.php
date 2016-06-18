<?php
namespace Craft;

/**
 * Class CKEditorFieldType
 *
 * @package Craft
 */
class CKEditorFieldType extends BaseFieldType
{
	// Private properties

	private static $_ckeditorLang = 'en';


	// Public methods

	/**
	 * @inheritDoc IComponentType::getName()
	 *
	 * @return string
	 */
	public function getName()
	{
		return Craft::t("Rich Text (CKEditor)");
	}

	/**
	 * @inheritDoc ISavableComponentType::getSettingsHtml()
	 *
	 * @return string|null
	 */
	public function getSettingsHtml()
	{
		$configOptions = ['' => Craft::t("Default")];
		$sourceOptions = [];
		$transformOptions = [];

		$configPath = craft()->path->getConfigPath() . 'ckeditor/';

		if(IOHelper::folderExists($configPath))
		{
			$configFiles = IOHelper::getFolderContents($configPath, false, '\.js(on)?$');

			if(is_array($configFiles))
			{
				foreach($configFiles as $file)
				{
					$fileName = IOHelper::getFileName($file);
					$configOptions[$fileName] = IOHelper::getFileName($file, false);
				}
			}
		}

		$columns = [
			'text' => Craft::t("Text (stores about 64K)"),
			'mediumtext' => Craft::t("MediumText (stores about 4GB)"),
		];

		foreach(craft()->assetSources->getPublicSources() as $source)
		{
			$sourceOptions[] = [
				'label' => $source->name,
				'value' => $source->id,
			];
		}

		foreach(craft()->assetTransforms->getAllTransforms() as $transform)
		{
			$transformOptions[] = [
				'label' => $transform->name,
				'value' => $transform->id,
			];
		}

		return craft()->templates->render('ckeditor/_fieldtype/settings', [
			'settings' => $this->getSettings(),
			'configOptions' => $configOptions,
			'assetSourceOptions' => $sourceOptions,
			'transformOptions' => $transformOptions,
			'columns' => $columns,
			'existing' => !empty($this->model->id),
		]);
	}

	/**
	 * @inheritDoc IFieldType::defineContentAttribute()
	 *
	 * @return array
	 */
	public function defineContentAttribute()
	{
		return [AttributeType::String, 'column' => $this->getSettings()->columnType];
	}

	/**
	 * @inheritDoc IFieldType::prepValue()
	 *
	 * @param mixed $value
	 * @return RichTextData|null
	 */
	public function prepValue($value)
	{
		if($value)
		{
			// Prevent everyone from having to use the |raw filter when outputting RTE content
			$charset = craft()->templates->getTwig()->getCharset();
			return new RichTextData($value, $charset);
		}

		return null;
	}

	/**
	 * @inheritDoc IFieldType::prepValueFromPost()
	 *
	 * @param string $value
	 * @return string
	 */
	public function prepValueFromPost($value)
	{
		if($value)
		{
			if($this->getSettings()->purifyHtml)
			{
				$purifier = new \CHtmlPurifier();
				$purifier->setOptions([
					'Attr.AllowedFrameTargets' => ['_blank'],
				]);
				$value = $purifier->purify($value);
			}

			if($this->getSettings()->cleanupHtml)
			{
				// Remove <span> and <font> tags
				$value = preg_replace('/<(?:span|font)\b[^>]*>/', '', $value);
				$value = preg_replace('/<\/(?:span|font)>/', '', $value);

				// Remove inline styles
				$value = preg_replace('/(<(?:h1|h2|h3|h4|h5|h6|p|div|blockquote|pre|strong|em|b|i|u|a)\b[^>]*)\s+style="[^"]*"/', '$1', $value);

				// Remove empty tags
				$value = preg_replace('/<(h1|h2|h3|h4|h5|h6|p|div|blockquote|pre|strong|em|a|b|i|u)\s*><\/\1>/', '', $value);
			}
		}

		// Find any element URLs and swap them with ref tags
		$pattern = '/(href=|src=)([\'"])[^\'"#]+?(#[^\'"#]+)?#(\w+):(\d+)(:' . HandleValidator::$handlePattern . ')?\2/';
		$value = preg_replace_callback($pattern, function($matches)
		{
			return $matches[1] . $matches[2] .
				'{' . $matches[4] . ':' . $matches[5] . (!empty($matches[6]) ? $matches[6] : ':url') . '}' .
				(!empty($matches[3]) ? $matches[3] : '') .
				$matches[2];
		}, $value);

		return $value;
	}

	/**
	 * @inheritDoc BaseFieldType::validate()
	 *
	 * @param string $value
	 * @return true|string
	 */
	public function validate($value)
	{
		$settings = $this->getSettings();

		$postContentSize = strlen($value);
		$maxDbColumnSize = DbHelper::getTextualColumnStorageCapacity($settings->columnType);

		// Give ourselves 10% wiggle room.
		$maxDbColumnSize = ceil($maxDbColumnSize * 0.9);

		if($postContentSize > $maxDbColumnSize)
		{
			return Craft::t("{attribute} is too long.", [
				'attribute' => Craft::t($this->model->name),
			]);
		}

		return true;
	}

	public function getInputHtml($name, $value)
	{
		$configJson = $this->_getConfigJson();
		$config = JsonHelper::decode(JsonHelper::removeComments($configJson));

		$id = craft()->templates->formatInputId($name);
		$localeId = (isset($this->element) ? $this->element->locale : craft()->language);

		$settings = [
			'id' => craft()->templates->namespaceInputId($id),
			'name' => $name,
			'linkOptions' => $this->_getLinkOptions(),
			'assetSources' => $this->_getAssetSources(),
			'transforms' => $this->_getTransforms(),
			'elementLocale' => $localeId,
			'ckedtorConfig' => $config,
			'ckeditorLang' => static::$_ckeditorLang,
		];

		if(isset($this->model) && $this->model->translatable)
		{
			// Explicitly set the text direction
			$locale = craft()->i18n->getLocaleData($localeId);
			$settings['direction'] = $locale->getOrientation();
		}

		if($value instanceof RichTextData)
		{
			$value = $value->getRawContent();
		}

		if(strpos($value, '{') !== false)
		{
			// Preserve the ref tags with hashes {type:id:url} => {type:id:url}#type:id
			$pattern = '/(href=|src=)([\'"])(\{(\w+\:\d+\:' . HandleValidator::$handlePattern . ')\})(#[^\'"#]+)?\2/';
			$value = preg_replace_callback($pattern, function($matches)
			{
				return $matches[1] . $matches[2] . $matches[3] .
					(!empty($matches[5]) ? $matches[5] : '') .
					'#' . $matches[4] . $matches[2];
			}, $value);

			// Now parse 'em
			$value = craft()->elements->parseRefs($value);
		}

		$settings['value'] = $value;

		return craft()->templates->render('ckeditor/_fieldtype/input', $settings);
	}

	/**
	 * @inheritDoc BaseFieldType::getStaticHtml()
	 *
	 * @param string $value
	 * @return string
	 */
	public function getStaticHtml($value)
	{
		return '<div class="text">' . ($value ? $value : '&nbsp;') . '</div>';
	}


	// Protected methods

	/**
	 * @inheritDoc BaseSavableComponentType::defineSettings()
	 *
	 * @return array
	 */
	protected function defineSettings()
	{
		return [
			'configFile' => AttributeType::String,
			'cleanupHtml' => [AttributeType::Bool, 'default' => true],
			'purifyHtml' => [AttributeType::Bool, 'default' => false],
			'columnType' => [AttributeType::String],
			'availableAssetSources' => AttributeType::Mixed,
			'availableTransforms' => AttributeType::Mixed,
		];
	}


	// Private methods

	/**
	 * Returns the link options available to the field.
	 *
	 * Each link option is represented by an array with the following keys:
	 *
	 * - `optionTitle` (required) – the user-facing option title that appears in the Link dropdown menu
	 * - `elementType` (required) – the element type class that the option should be linking to
	 * - `sources` (optional) – the sources that the user should be able to select elements from
	 * - `criteria` (optional) – any specific element criteria parameters that should limit which elements the user can select
	 * - `storageKey` (optional) – the localStorage key that should be used to store the element selector modal state (defaults to RichTextFieldType.LinkTo[ElementType])
	 *
	 * @return array
	 */
	private function _getLinkOptions()
	{
		$linkOptions = [];
		$sectionSources = $this->_getSectionSources();
		$categorySources = $this->_getCategorySources();

		if($sectionSources)
		{
			$linkOptions[] = [
				'optionTitle' => Craft::t("Link to an entry"),
				'elementType' => 'Entry',
				'sources' => $sectionSources,
			];
		}

		if($categorySources)
		{
			$linkOptions[] = [
				'optionTitle' => Craft::t("Link to a category"),
				'elementType' => 'Category',
				'sources' => $categorySources,
			];
		}

		// Give plugins a chance to add their own
		$allPluginLinkOptions = craft()->plugins->call('addRichTextLinkOptions', [], true);
		foreach($allPluginLinkOptions as $pluginLinkOptions)
		{
			$linkOptions = array_merge($linkOptions, $pluginLinkOptions);
		}

		return $linkOptions;
	}

	/**
	 * Get available section sources.
	 *
	 * @return array
	 */
	private function _getSectionSources()
	{
		$sources = [];
		$sections = craft()->sections->getAllSections();
		$showSingles = false;

		foreach($sections as $section)
		{
			if($section->type == SectionType::Single)
			{
				$showSingles = true;
			}
			else if($section->hasUrls)
			{
				$sources[] = 'section:' . $section->id;
			}
		}

		if($showSingles)
		{
			array_unshift($sources, 'singles');
		}

		return $sources;
	}

	/**
	 * Get available category sources.
	 *
	 * @return array
	 */
	private function _getCategorySources()
	{
		$sources = [];
		$categoryGroups = craft()->categories->getAllGroups();

		foreach($categoryGroups as $categoryGroup)
		{
			if($categoryGroup->hasUrls)
			{
				$sources[] = 'group:' . $categoryGroup->id;
			}
		}

		return $sources;
	}

	/**
	 * Get available Asset sources.
	 *
	 * @return array
	 */
	private function _getAssetSources()
	{
		$sources = [];
		$assetSourceIds = $this->getSettings()->availableAssetSources;

		if(!$assetSourceIds)
		{
			$assetSourceIds = craft()->assetSources->getPublicSourceIds();
		}

		$folders = craft()->assets->findFolders([
			'sourceId' => $assetSourceIds,
			'parentId' => ':empty:',
		]);

		foreach($folders as $folder)
		{
			$sources[] = 'folder:' . $folder->id;
		}

		return $sources;
	}

	/**
	 * Get available Transforms.
	 *
	 * @return array
	 */
	private function _getTransforms()
	{
		$transforms = craft()->assetTransforms->getAllTransforms('id');
		$settings = $this->getSettings();
		$transformIds = !empty($settings->availableTransforms) && is_array($settings->availableTransforms) ?
			array_flip($settings->availableTransforms) : [];

		if(!empty($transformIds))
		{
			$transforms = array_intersect_key($transforms, $transformIds);
		}

		$transformList = [];
		foreach($transforms as $transform)
		{
			$transformList[] = (object) [
				'handle' => HtmlHelper::encode($transform->handle),
				'name' => HtmlHelper::encode($transform->name),
			];
		}

		return $transformList;
	}

	/**
	 * Returns the CKEditor config JSON used by this field.
	 *
	 * @return string
	 */
	private function _getConfigJson()
	{
		if($this->getSettings()->configFile)
		{
			$configPath = craft()->path->getConfigPath() . 'ckeditor/' . $this->getSettings()->configFile;
			$json = IOHelper::getExtension($configPath) == 'js' ?
				'{"customConfig": "' . $configPath . '"}':
				IOHelper::getFileContents($configPath);
		}

		return empty($json) ? '{}' : $json;
	}
}
