<?php

namespace Quetzal\Service\Import;

use Quetzal\Data\Bitrix\IBlockElementRepository;
use Quetzal\Data\Common\Import\AbstractDictionaryProvider;
use Quetzal\Pattern\SingletonInterface;

/**
 * Хранилище данных для справочников (для их импорта)
 *
 * Class DictionariesDictionaryProvider
 *
 * @package Quetzal\Service\Import
 */
class DictionariesDictionaryProvider extends AbstractDictionaryProvider implements SingletonInterface
{
	/**
	 * @var self
	 */
	protected static $instance = null;

	/**
	 * @return self
	 */
	public static function getInstance()
	{
		if (is_null(self::$instance)) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 */
	protected function __construct()
	{
		$this->fillDictionaries();
	}

	private function __clone()
	{}

	/**
	 * Подготавливает справочники
	 */
	protected function fillDictionaries()
	{
		$repository = new IBlockElementRepository(new \CIBlockElement());

		$settings = \EnvironmentHelper::getParam('importDictionariesFeeds');

		foreach ($settings as $key => $params) {
			$this->dictionaries[$key] = array();

			if ($iBlockId = \EnvironmentHelper::getParam($params['iBlockId'])) {
				$dbItems = $repository->rawFindBy(
					array(
						'IBLOCK_ID' => $iBlockId,
					),
					array(
						'id' => 'asc',
					),
					array(
						'ID',
						'XML_ID',
					)
				);

				while ($arItem = $dbItems->Fetch()) {
					if ($arItem['XML_ID']) {
						$this->dictionaries[$key][$arItem['XML_ID']] = $arItem['ID'];
					}
				}
			}
		}
	}
}
