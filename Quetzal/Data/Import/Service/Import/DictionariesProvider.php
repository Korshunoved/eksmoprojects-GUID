<?php

namespace Quetzal\Service\Import;

use Quetzal\Pattern\SingletonInterface;
use Quetzal\Service\Data\CacheManager;

/**
 * Хранилище данных различных справочников
 *
 * Class DictionariesProvider
 *
 * @package Quetzal\Service\Import
 */
class DictionariesProvider implements SingletonInterface
{
	const DIC_NOMCODES_NAME = 'nomcodes';
	const DIC_COVERS_NAME = 'covers';
	const DIC_PREORDER_NAME = 'preOrder';
	const DIC_SECTIONS_NAME = 'sections';
	const DIC_DISABLED_NAME = 'disabled';
	const DIC_EXCLUDED_NAME = 'excluded';
	const DIC_LAST_IMPORT_NAME = 'lastImport';

	/**
	 * @var self
	 */
	protected static $instance = null;

	/**
	 * @var CacheManager
	 */
	private $cache;

	/**
	 * @var array
	 */
	private $dictionaries;

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
		$this->cache = new CacheManager();
		$this->dictionaries = array(
			'nomcodes' => array(),
			'elements' => array(),
			'preOrder' => array(),
			'sections' => array(),
			'disabled' => array(),
			'umkSeries' => array(),
			'covers' => array(),
			'excluded' => array(),
			'lastImport' => array(),
		);

		$this->fillDictionaries();
	}

	private function __clone()
	{
	}

	/**
	 */
	public function refresh()
	{
		$this->fillDictionaries();
	}

	/**
	 * Подготавливает справочники
	 */
	protected function fillDictionaries()
	{
		$arFilter = array(
			'IBLOCK_ID' => \CatalogHelper::IBLOCK_ID,
		);

		$arSelect = array(
			'ID',
			'ACTIVE',
			'XML_ID',
			'IBLOCK_SECTION_ID',
			'PROPERTY_DENY_IMAGE_UPDATE_THROUGH_IMPORT',
			'PROPERTY_PREDZAKAZ',
			'PROPERTY_NOMCODE',
			'PROPERTY_LAST_IMPORT',
		);

		/** @noinspection PhpDynamicAsStaticMethodCallInspection */
		$dbItems = \CIBlockElement::GetList(array('ID' => 'ASC'), $arFilter, false, false, $arSelect);

		while ($arItem = $dbItems->GetNext()) {
			if ($arItem['PROPERTY_NOMCODE_VALUE']) {
				$this->dictionaries['nomcodes'][$arItem['PROPERTY_NOMCODE_VALUE']] = $arItem['PROPERTY_NOMCODE_VALUE'];
			}

			if (stripos($arItem['XML_ID'], 'origami') !== false && $arItem['ACTIVE'] != 'Y') {
				continue;
			}

			$this->dictionaries['elements'][$arItem['ID']] = $arItem['XML_ID'];
			$this->dictionaries['sections'][$arItem['ID']] = $arItem['IBLOCK_SECTION_ID'];

			if ($arItem['PROPERTY_PREDZAKAZ_VALUE'] == 'Да') {
				$this->dictionaries['preOrder'][$arItem['ID']] = $arItem['ID'];
			}

			if ($arItem['ACTIVE'] == 'N') {
				$this->dictionaries['disabled'][$arItem['ID']] = $arItem['ID'];
			}

			$this->dictionaries['lastImport'][$arItem['ID']] = $arItem['PROPERTY_LAST_IMPORT_VALUE'];
		}

		/** Справочник серий в названии которых есть слово "УМК" */
		/** @noinspection PhpDynamicAsStaticMethodCallInspection */
		$dbSeries = \CIBlockElement::GetList(
			array(),
			array(
				'ACTIVE'    => 'Y',
				'IBLOCK_ID' => \EnvironmentHelper::getParam('seriesIblockId'),
				'%NAME'     => 'УМК',
			),
			false,
			false,
			array('XML_ID')
		);

		while ($arSerie = $dbSeries->Fetch()) {
			$this->dictionaries['umkSeries'][$arSerie['XML_ID']] = $arSerie['XML_ID'];
		}

		$dbCovers = \Quetzal\Service\EntityManager::getInstance()->rawFindBy(
			array(
				'IBLOCK_ID' => \EnvironmentHelper::getParam('coverIBlockId'),
			),
			array(
				'id' => 'asc',
			),
			array(
				'ID',
				'XML_ID',
			)
		);

		while ($arCover = $dbCovers->Fetch()) {
			$this->dictionaries['covers'][$arCover['XML_ID']] = $arCover;
		}

		/**
		 * Массив исключенных книг
		 */
		$this->dictionaries['excluded'] = array(
			'978-5-4438-0215-2' => 'ALL',
			'978-5-4438-0101-8' => 'ALL',
			'978-5-699-64327-1' => 'ALL',
			'978-5-699-77121-9' => 'ALL',
			'978-5-699-62849-0' => 'ALL',
			'978-5-699-62851-3' => 'ALL',
			'978-5-699-64750-7' => 'ALL',
			'978-5-699-63860-4' => 'ALL',
			'978-5-699-62852-0' => 'ALL',
			'978-5-699-62848-3' => 'ALL',
			'978-5-699-64749-1' => 'ALL',
			'978-5-699-77118-9' => 'ALL',
			'978-5-699-63937-3' => 'ALL',
			'978-5-699-77117-2' => 'ALL',
			'978-5-699-77119-6' => 'ALL',
			'978-5-699-70774-4' => 'ALL',
			'978-5-699-70777-5' => 'ALL',
			'978-5-699-70770-6' => 'ALL',
			'978-5-699-70768-3' => 'ALL',
			'978-5-17-046915-4' => 'ALL',
			'978-5-699-60905-5' => 'ALL',
			'978-5-699-49095-0' => 'ALL',
			'978-5-699-62972-5' => 'ALL'
		);

		/*
		 * Дополняем массив исключенных книг. Данные берутся из инфоблока "Запрет обновления".
		 * Данные по книге представлены в виде списка параметров, которые не надо обновлять,
		 * либо одним значением ALL - которое означает, все поля этой книги не обновляются.
		 */
		/** @noinspection PhpDynamicAsStaticMethodCallInspection */
		$res = \CIBlockElement::GetList(
			array(),
			array('IBLOCK_ID'=>\EnvironmentHelper::getParam('excludedFromImportBooksIdIBlock')),
			false,
			false,
			array('ID', 'PROPERTY_BOOK', 'PROPERTY_BOOK.PROPERTY_ISBN', 'PROPERTY_NOT_UPDATE', 'PROPERTY_NOT_UPDATE_ENUM_ID')
		);

		/**
		 * @var array $xmlIdList - Вспомогательный массив, который хранит список значений XML_ID опций
		 *                       XML_ID - содержит код параметра элемента
		 */
		$xmlIdList = array();

		while ($e = $res->GetNext()) {
			/**
			 * Важно! Это работает при условии, что способ хранения значений свойств инфоблока "Запрет обновления",
			 * останется как и раньше: в общей таблице (по умолчанию).
			 */
			$optionDataPropertyNotUpdate = \CIBlockPropertyEnum::GetByID($e['PROPERTY_NOT_UPDATE_ENUM_ID']);
			if (isset($optionDataPropertyNotUpdate['XML_ID']) && ($optionDataPropertyNotUpdate['XML_ID'] != 'ALL') && ($xmlIdList[$e['PROPERTY_BOOK_PROPERTY_ISBN_VALUE']] != 'ALL')) {
				if (is_array($xmlIdList[$e['PROPERTY_BOOK_PROPERTY_ISBN_VALUE']])) {
					$xmlIdList[$e['PROPERTY_BOOK_PROPERTY_ISBN_VALUE']][] = $optionDataPropertyNotUpdate['XML_ID'];
				} else {
					$xmlIdList[$e['PROPERTY_BOOK_PROPERTY_ISBN_VALUE']] = array();
					$xmlIdList[$e['PROPERTY_BOOK_PROPERTY_ISBN_VALUE']][] = $optionDataPropertyNotUpdate['XML_ID'];
				}
			} else {
				$xmlIdList[$e['PROPERTY_BOOK_PROPERTY_ISBN_VALUE']] = 'ALL';
			}
		}

		$this->dictionaries['excluded'] = array_merge($this->dictionaries['excluded'], $xmlIdList);
	}

	/**
	 * Получает справочник по его имени
	 *
	 * @param $name
	 *
	 * @return mixed
	 */
	public function getDictionary($name)
	{
		return isset($this->dictionaries[$name]) ? $this->dictionaries[$name] : null;
	}

	/**
	 * Проверяет наличие ключа в справочнике
	 *
	 * @param string $dictionaryName
	 * @param string $key
	 *
	 * @return bool
	 */
	public function isDictionaryKeyExists($dictionaryName, $key)
	{
		return isset($this->dictionaries[$dictionaryName][$key]);
	}

	/**
	 * Провеят существование в справочнике серии из группы УМК
	 *
	 * @param string $xmlId
	 *
	 * @return bool
	 */
	public function isUmkSerieExists($xmlId)
	{
		return isset($this->dictionaries['umkSeries'][$xmlId]);
	}

	/**
	 * Проверяет существование переплета в справочнике
	 *
	 * @param string $xmlId
	 *
	 * @return bool
	 */
	public function isCoverExists($xmlId)
	{
		return isset($this->dictionaries['covers'][$xmlId]);
	}

	/**
	 * Добавляет в указанный справочник значение по ключу
	 *
	 * @param string $dictionaryName
	 * @param string $key
	 * @param mixed $value
	 */
	public function addToDictionary($dictionaryName, $key, $value)
	{
		$this->dictionaries[$dictionaryName][$key] = $value;
	}

	/**
	 * Получает значение по ключу в справочнике
	 *
	 * @param string $dictionaryName
	 * @param string $key
	 *
	 * @return mixed
	 */
	public function getDictionaryValue($dictionaryName, $key)
	{
		return $this->isDictionaryKeyExists($dictionaryName, $key) ? $this->dictionaries[$dictionaryName][$key] : null;
	}
}
