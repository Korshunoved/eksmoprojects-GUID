<?php

namespace Quetzal\Data\Import\Product;

use Quetzal\Data\Common\Import\AbstractImporter;
use Quetzal\Service\Catalog\Mapping;
use Quetzal\Service\Import\DictionariesProvider;
use Quetzal\Tools\LoggerInterface;

/**
 * Class ProductImporter
 *
 * @package Quetzal\Data\Import
 */
class ProductImporter extends AbstractImporter
{
	/**
	 * @var int
	 */
	protected $defaultSection;

	/**
	 * @var string
	 */
	protected $action;

	/**
	 * @var Mapping
	 */
	protected $mapper;

	/**
	 * @var DictionariesProvider
	 */
	protected $dictionaries;

	/**
	 * @param LoggerInterface $logger
	 */
	public function __construct(LoggerInterface $logger)
	{
		parent::__construct($logger);

		$this->defaultSection = 7398;
		$this->mapper = Mapping::getInstance();
		$this->dictionaries = DictionariesProvider::getInstance();
	}

	/**
	 * @param int $value
	 */
	public function setDefaultSection($value)
	{
		$this->defaultSection = $value;
	}

	/**
	 * @param $entityName
	 */
	public function setAction($entityName)
	{
		$this->action = $entityName;
	}

	/**
	 * @param array $arItem
	 *
	 * @return bool
	 */
	protected function canItemImport(array $arItem)
	{
		if (empty($arItem['xml_id'])) {
			$this->log(sprintf('Error: empty XML_ID of "%s"', $arItem['xml_id']));

			return false;
		}

		if (empty($arItem['guid'])) {
			$this->log(sprintf('Error: empty GUID of "%s"', $arItem['guid']));

			return false;
		}

		if (empty($arItem['nomcode'])) {
			$this->log(sprintf('Error: empty NOMCODE of "%s"', $arItem['nomcode']));

			return false;
		}

		return true;
	}

	/**
	 * @param string $itemName
	 *
	 * @return string
	 */
	protected function makeItemCode($itemName)
	{
		return \Cutil::translit($itemName, 'ru', array('replace_space' => '-', 'replace_other' => '-'));
	}

	/**
	 * @param array $arItem
	 *
	 * @return array
	 */
	protected function getItemSections(array $arItem)
	{
		// Select sections for element
		$sections = array($this->defaultSection);

		// Drofa to school books only
		if (strpos($arItem['xml_id'], 'drf') === false) {
			$sectionsCriteria = array();

			if ($arItem['authrs']) {
				foreach ($arItem['authrs'] as $author) {
					$sectionsCriteria[] = array(
						'code' => $this->getItemIdByXmlId($author['xml_id']),
						'type' => Mapping::RULE_TYPE_AUTHOR,
					);
				}
			}

			if ($arItem['serie']) {
				$sectionsCriteria[] = array(
					'code' => $this->getItemIdByXmlId($arItem['serie']),
					'type' => Mapping::RULE_TYPE_SERIE,
				);
			}

			if ($arItem['sbjct']) {
				$sectionsCriteria[] = array(
					'code' => $this->getItemIdByXmlId($arItem['sbjct']),
					'type' => Mapping::RULE_TYPE_SUBJECT,
				);
			}

			if ($arItem['niche']) {
				$sectionsCriteria[] = array(
					'code' => $this->getItemIdByXmlId($arItem['niche']),
					'type' => Mapping::RULE_TYPE_NICHE,
				);
			}

			if ($arItem['nomcode']) {
				$sectionsCriteria[] = array(
					'code' => $this->getItemIdByXmlId($arItem['nomcode']),
					'type' => Mapping::RULE_TYPE_NOMCODE,
				);
			}

			if (count($sectionsCriteria) > 0) {
				$sectionsByCriteriaList = $this->mapper->findSectionByCriteriaList($sectionsCriteria);
				$sections = empty($sectionsByCriteriaList) ? $sections : $sectionsByCriteriaList;
			}
		}

		return $sections;
	}

	/**
	 * @param string $xmlId
	 *
	 * @return int|null
	 */
	protected function getItemIdByXmlId($xmlId)
	{
		return array_search($xmlId, $this->dictionaries->getDictionary('elements'));
	}

	/**
	 * @param string $serieId
	 *
	 * @return bool
	 */
	protected function isUMKSerie($serieId)
	{
		return $this->dictionaries->isUmkSerieExists($serieId);
	}

	/**
	 * @param array $arItem
	 */
	protected function processCover(array $arItem)
	{
		// Если переплета еще нет в справочниках, то добавляем его
		if (!$this->dictionaries->isCoverExists($arItem['cover'])) {
			$iBEGateway = new \CIBlockElement();

			$coverId = $iBEGateway->Add(
				array(
					'ACTIVE'          => 'Y',
					'NAME'            => $arItem['_extra']['cover']['name'],
					'IBLOCK_ID'       => \EnvironmentHelper::getParam('coverIBlockId'),
					'xml_id'          => $arItem['_extra']['cover']['xml_id'],
					'PROPERTY_VALUES' => array(
						'GUID'     => $arItem['_extra']['cover']['guid'],
						'PEREPLET' => '',
					),
				)
			);

			if ($coverId) {
				$this->log(sprintf('Add new cover "%s" (%s)', $coverId, $arItem['_extra']['cover']['xml_id']));
				$this->dictionaries->addToDictionary(
					DictionariesProvider::DIC_COVERS_NAME,
					$arItem['cover'],
					array('xml_id' => $coverId, 'XML_ID' => $arItem['cover'])
				);
			} else {
				$this->log(
					sprintf(
						'Unable to add cover "%s" ("%s", "%s")',
						$coverId,
						$arItem['_extra']['cover']['guid'],
						$arItem['_extra']['cover']['name']
					)
				);
			}
		}
	}

	/**
	 * Проверяет, что необходимо обрабатывать данные по товары
	 *
	 * @param int $id
	 * @param array $arItem
	 *
	 * @return bool
	 */
	protected function isProductProcessingNeeded($id, array $arItem)
	{
		$lastImport = $this->dictionaries->getDictionaryValue(DictionariesProvider::DIC_LAST_IMPORT_NAME, $id);

		$this->log(sprintf('Stored date: "%s". Di: "%s"', $lastImport, $arItem['di']));

		return $lastImport < $arItem['di'];
	}

	/**
	 * @param array $arItem
	 *
	 * @return bool
	 */
	protected function isExclusiveBook(array $arItem)
	{
		return $arItem['serie'] == '33f16a47-2d28-11e1-93a1-5ef3fc5021a7'
			|| $arItem['serie'] == '583d38ee-4f03-11e0-a01d-001a64231342'
			|| $arItem['price'] >= 5000;
	}

	/**
	 * @param array $arItem
	 *
	 * @return bool
	 */
	public function import(array $arItem)
	{
		$vatRates = \EnvironmentHelper::getParam('vat');

		if (!$this->canItemImport($arItem)) {
			return false;
		}

		$code = $this->makeItemCode($arItem['name']);
		$sections = $this->getItemSections($arItem);

		if (count($sections) > 0) {
			$key = $this->getItemIdByXmlId($arItem['xml_id']);

			if ($key > 0 && !$this->isProductProcessingNeeded($key, $arItem)) {
				$this->log(
					sprintf('Item "%s" (%s) processing skipped - old import date', $arItem['xml_id'], $key)
				);

				return true;
			}

			$properties = array();

			// Установка свойства УМК. Если в названии серии есть слово "УМК", то ставим флаг
			if ($this->isUMKSerie($arItem['serie'])) {
				$properties['UMK'] = \EnvironmentHelper::getParam('umkPropertyValueId');
			}

			// @todo add properties mapping
			$properties['GUID'] = $arItem['guid'];
			$properties['AUTHR'] = $arItem['authrs'];
			//$properties['AUTHORS'] = $authors['GUID']; // XML has no guids
			$properties['LITERARY_WORKS'] = $arItem['proizvedeniya'];
			$properties['SERIE'] = $arItem['serie'];
			$properties['ISBN'] = $arItem['isbnn'];
			$properties['BRGEW'] = $arItem['brgew'];
			$properties['EDFUL'] = $arItem['edful'];

			if (!empty($arItem['cover'])) {
				$properties['COVER'] = $arItem['cover'];

				// Если переплета еще нет в справочниках, то добавляем его
				$this->processCover($arItem);
			}

			$properties['AGE_LIMIT'] = $arItem['age_limit'];
			$properties['PUBLI'] = $arItem['publi'];
			$properties['PRICE'] = $arItem['price']; // needed?
			$properties['PRICE_VAT'] = $arItem['pirce_vat']; // needed?
			$properties['QTYPG'] = $arItem['qtypg'];
			$properties['FORMT'] = $arItem['formt'];
			$properties['SBJCT'] = $arItem['sbjct'];
			$properties['NICHE'] = $arItem['niche'];
			$properties['CATEG'] = $arItem['categ'];
			$properties['SGMNT'] = $arItem['sgmnt'];
			$properties['SCOVER'] = $arItem['scovr'];
			$properties['REMAINDER'] = $arItem['remainder']; // needed?
			$properties['SDATE_D'] = $arItem['sdate_d'];
			$properties['LDATE_D'] = $arItem['ldate_d'];

			$properties['ISSU'] = $arItem['issuu_id'];
			$properties['APPSTORE'] = $arItem['appstore'];

			if (strpos($arItem['xml_id'], 'ast') === false) {
				$properties['VIDEO'] = $arItem['video'];
			}

			$properties['WIDTH'] = $arItem['width'];
			$properties['HEIGHT'] = $arItem['height'];
			$properties['NOMCODE'] = $arItem['nomcode'];
			$properties['FOCUS'] = $arItem['focus'];

			$properties['PAPER'] = $arItem['bumaga'];

			$properties['PROD_TEXT'] = array('VALUE' => array('TEXT' => $arItem['prodtext'], 'TYPE' => 'html'));
			$properties['LAST_IMPORT'] = date('Y-m-d H:i:s');

			if (strlen($arItem['price_authors']) > 0) {
				$properties['PRICE_AUTHORS'] = $arItem['price_authors'];
			}

			if (strpos($arItem['price_authors'], 'не указан') !== false) {
				$properties['PRICE_AUTHORS'] = '';
				$properties['AUTHR'] = '';
			}

			// новинки
			$properties['NEW_ITEM'] = '';

			$days60 = 60 * 60 * 24 * 60;
			$now = getmicrotime();

			if (!empty($arItem['ldate_d'])) {
				$stamp = MakeTimeStamp($arItem['ldate_d'], 'DD.MM.YYYY');

				if (($now - $stamp) <= $days60) {
					$properties['NEW_ITEM'] = 10; // Y
				}
			}

			$arLoadProductArray = array(
				'MODIFIED_BY' => 1,
				'IBLOCK_ID'   => \CatalogHelper::IBLOCK_ID,
				'NAME'        => $arItem['name'],
				'ACTIVE'      => 'Y',
				'xml_id'      => $arItem['xml_id'],
				'SORT'        => $arItem['sort']
			);

			if (strpos($arItem['xml_id'], 'ast') !== false) {
				// Если AST то сортировка по умолчанию
				$arLoadProductArray['SORT'] = 500;
				$properties['SORT_AST'] = $arItem['sort'];
			}

			$properties['SORT_DF'] = strpos($arItem['xml_id'], 'drf') === false ? 1000 : ($arItem['sort'] ?: 100);

			if ($this->isZapret($key, $arItem)) {
				$arLoadProductArray['ACTIVE'] = 'N';

				$this->log(sprintf('! ZAPRET: %s - %s', $key, $arItem['xml_id']));
			}

			// эксклюзивные книги
			if ($this->isExclusiveBook($arItem)) {
				$PROP['GIFT_EXCL'] = '11'; // Y

				$this->log(sprintf('EXCLUSIVE - %s', $arItem['guid']));
			}

			$el = new \CIBlockElement();

			if ($key > 0) {
				$isUpdateExcluded = $this->dictionaries->isDictionaryKeyExists(
					DictionariesProvider::DIC_EXCLUDED_NAME,
					$arItem['isbnn']
				);

				$excludingRule = $this->dictionaries->getDictionaryValue(
					DictionariesProvider::DIC_EXCLUDED_NAME,
					$arItem['isbnn']
				);

				$isElementDisabled = $this->dictionaries->isDictionaryKeyExists(
					DictionariesProvider::DIC_DISABLED_NAME,
					$key
				);

				if ($isUpdateExcluded) {
					// Если элемент стоит в запрете обновлений и он не активен, то его нельзя активировать
					if ($isElementDisabled) {
						$arLoadProductArray['ACTIVE'] = 'N';

						$this->log(sprintf('Element "%s" activating excluded', $key));
					} elseif (is_array($excludingRule) && in_array('ACTIVE', $excludingRule)) {
						unset($arLoadProductArray['ACTIVE']);

						$this->log(sprintf('Element "%s" activating change excluded', $key));
					}
				}

				$arLoadProductArray['CODE'] = $code . '-' . substr(
						preg_replace("/[^a-zA-Z0-9_-]/", "", $arItem['isbnn']),
						-3,
						3
					);

				if ($this->action == 'eksmo' || $this->action == 'ast') {
					if ($arItem['detail_text']) {
						$arLoadProductArray['DETAIL_TEXT'] = $arItem['detail_text'];
					}
				}

				$storedSection = $this->dictionaries->getDictionaryValue(DictionariesProvider::DIC_SECTIONS_NAME, $key);

				// Защита от перезаписи SECTION_ID для книг из учебной литературы
				if ($storedSection == \EnvironmentHelper::getParam('schoolSectionId')) {
					unset($arLoadProductArray['IBLOCK_SECTION']);
					unset($arLoadProductArray['IBLOCK_SECTION_ID']);
				}

				if ($PRODUCT_ID = $el->Update($key, $arLoadProductArray, false, true, false)) {
					// Если разделы не менялись, то метод вернет false. Поэтому его нельзя добавлять в условие
					/** @noinspection PhpDynamicAsStaticMethodCallInspection */
					\CIBlockElement::SetElementSection($key, $sections, false, \CatalogHelper::IBLOCK_ID);

					$this->log(
						sprintf(
							'Update ID: %s, XML_ID: %s, SECTION: (%s), PRICE: %s',
							$key,
							$arItem['xml_id'],
							implode(', ', $sections),
							$arItem['price']
						)
					);
				} else {
					$this->log(
						sprintf(
							'! ERROR update ID: %s, XML_ID: %s',
							$key,
							$arItem['xml_id']
						)
					);
				}

				\CIBlockElement::SetPropertyValuesEx($key, \CatalogHelper::IBLOCK_ID, $properties);

				$weight = $arItem['brgew'] * 1000;

				$catalogProp = array(
					'xml_id'             => $key,
					'WEIGHT'         => $weight,
					'QUANTITY_TRACE' => 'Y',
				);

				// Учитываем ставку НДС, если она пришла
				if ($arItem['pirce_vat']) {
					$vatId = isset($vatRates[$arItem['pirce_vat']]) ? $vatRates[$arItem['pirce_vat']] : 0;

					if ($vatId) {
						$catalogProp['VAT_ID'] = $vatId;
						$catalogProp['VAT_INCLUDED'] = 'Y';
					}
				}

				/** @noinspection PhpDynamicAsStaticMethodCallInspection */
				\CCatalogProduct::Add($catalogProp);
			} else {
				// Deny to add doubles of elements
				if ($this->dictionaries->isDictionaryKeyExists(DictionariesProvider::DIC_NOMCODES_NAME, $arItem['nomcode'])) {
					$this->log(sprintf('Skip adding "%s": nomcode "%s" already exists', $arItem['xml_id'], $arItem['nomcode']));
				}

				$arLoadProductArray['CODE'] = $code . '-' . substr($arItem['isbnn'], -3, 3);

				if ($arItem['detail_text']) {
					$arLoadProductArray['DETAIL_TEXT'] = $arItem['detail_text'];
				}

				if ($PRODUCT_ID = $el->Add($arLoadProductArray, false, true, false)) {
					/** @noinspection PhpDynamicAsStaticMethodCallInspection */
					\CIBlockElement::SetElementSection($PRODUCT_ID, $sections, true, \CatalogHelper::IBLOCK_ID);
					\CIBlockElement::SetPropertyValuesEx($PRODUCT_ID, \CatalogHelper::IBLOCK_ID, $properties);

					$weight = $arItem['brgew'] * 1000;

					$catalogProp = array(
						'xml_id'             => $PRODUCT_ID,
						'WEIGHT'         => $weight,
						'QUANTITY_TRACE' => 'Y'
					);

					// Учитываем ставку НДС, если она пришла
					if ($arItem['pirce_vat']) {
						$vatId = isset($vatRates[$arItem['pirce_vat']]) ? $vatRates[$arItem['pirce_vat']] : 0;

						if ($vatId) {
							$catalogProp['VAT_ID'] = $vatId;
							$catalogProp['VAT_INCLUDED'] = 'Y';
						}
					}

					/** @noinspection PhpDynamicAsStaticMethodCallInspection */
					\CCatalogProduct::Add($catalogProp);

					$this->log(
						sprintf(
							'New ID: %s, XML_ID: %s, PRICE: %s, SECTION: (%s)',
							$PRODUCT_ID,
							$arItem['xml_id'],
							$arItem['price'],
							implode(', ', $sections)
						)
					);

					$elements[$PRODUCT_ID] = $arItem['xml_id'];
				} else {
					$this->log(sprintf('Error: %s', $el->LAST_ERROR));
				}
			}

			return true;
		}

		$this->log(sprintf('Error: no sections for "%s"', $arItem['xml_id']));

		return false;
	}

	/**
	 * @param int $key
	 * @param array $arItem
	 *
	 * @return bool
	 */
	protected function isZapret($key, array $arItem)
	{
		$forceZapret = array('AST000000000140330');

		// $zapret=1 - значит запрет продаж, если 0 или пусто, то разрешено
		return ($arItem['zapret'] > 0 || in_array($arItem['nomcode'], $forceZapret))
			&& !$this->dictionaries->isDictionaryKeyExists(DictionariesProvider::DIC_PREORDER_NAME, $key);
	}
}
