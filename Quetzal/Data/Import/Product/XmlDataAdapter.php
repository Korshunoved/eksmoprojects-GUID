<?php

namespace Quetzal\Data\Import\Product;

/**
 * Преобразователь данных из XML в формат, с которым работает импорт
 *
 * Class XmlDataAdapter
 *
 * @package Quetzal\Data\Import\Product
 */
class XmlDataAdapter
{
	/**
	 * Преобразует данные одного элемента в формат, понятный импорту
	 *
	 * @param \SimpleXMLElement $item
	 *
	 * @return array
	 */
	public function convertItem(\SimpleXMLElement $item)
	{
		$arItem = array(
			'_extra'         => array(
				'authrs' => array(),
			),
			'tt'             => (string)$item->tt,
			'di'             => (string)$item->di,
			'name'           => (string)$item->name,
			'xml_id'         => (string)$item->xml_id,
			'guid'           => (string)$item->guid,
			'zapret'         => intval(trim($item->zapret)),
			'prodcode'       => (string)$item->prodcode,
			'prodtext'       => \StringHelper::replacesHyphenation($item->prodtext),
			'sort'           => (string)$item->sort,
			'source_cover4'  => (string)$item->source_cover4,
			'source_spine'   => (string)$item->source_spine,
			'source_picture' => (string)$item->source_picture,
			'detail_picture' => (string)$item->detail_picture,
			'detail_text'    => (string)$item->detail_text,
			'authrs'         => array(),
			'cover_authors'  => (string)$item->cover_authors,
			'price_authors'  => (string)$item->price_authors,
			'serie'          => (string)$item->serie,
			'isbnn'          => trim($item->isbnn),
			'brgew'          => (string)$item->brgew,// float
			'edful'          => (string)$item->edful,// int
			'cover'          => (string)$item->cover,
			'publi'          => (string)$item->publi,
			'price'          => floatval($item->price),
			'price_base'     => floatval($item->price_base),
			'pirce_vat'      => (string)$item->pirce_vat, // float
			'qtypg'          => (string)$item->qtypg, // int
			'divid'          => (string)$item->divid,
			'formt'          => (string)$item->formt,
			'formt_posle'    => (string)$item->formt_posle,
			'sbjct'          => (string)$item->sbjct,
			'niche'          => (string)$item->niche,
			'categ'          => (string)$item->categ,
			'sgmnt'          => (string)$item->sgmnt,
			'scovr'          => (string)$item->scovr,
			'remainder'      => (string)$item->remainder, //int
			'shop_remainder' => (string)$item->shop_remainder, //int
			'sdate_d'        => (string)$item->sdate_d,
			'ldate_d'        => (string)$item->ldate_d,
			'video'          => (string)$item->video,
			'appstore'       => (string)$item->appstore,
			'focus'          => (string)$item->focus,
			'width'          => (string)$item->width, // float
			'height'         => (string)$item->height, // float
			'nomcode'        => (string)$item->nomcode,
			'pdf'            => (string)$item->pdf,
			'bumaga'         => (string)$item->bumaga,
			'proizvedeniya'  => array(),
			'age_limit'      => (string)$item->age_limit,
			//edu_props
		);

		if (!$arItem['sort']) {
			$arItem['sort'] = 500;
		}

		foreach ($item->authrs->authr as $author) {
			$arItem['authrs'][] = (string)$author['ID'];
			$arItem['_extra']['authrs'][] = array(
				'value'  => (string)$author,
				'name'   => (string)$author['name'],
				'xml_id' => (string)$author['xml_id'],
			);
		}

		$arItem['_extra']['serie'] = array(
			'value'  => (string)$item->serie,
			'name'   => (string)$item->serie['name'],
			'xml_id' => (string)$item->serie['xml_id'],
		);

		$arItem['_extra']['cover'] = array(
			'value'  => (string)$item->cover,
			'name'   => (string)$item->cover['name'],
			'guid'   => (string)$item->cover['guid'],
			'xml_id' => (string)$item->cover['xml_id'],
		);

		$arItem['_extra']['publi'] = array(
			'value'  => (string)$item->publi,
			'name'   => (string)$item->publi['name'],
			'xml_id' => (string)$item->publi['xml_id'],
		);

		$arItem['_extra']['divid'] = array(
			'value'  => (string)$item->divid,
			'name'   => (string)$item->divid['name'],
			'xml_id' => (string)$item->divid['xml_id'],
		);

		$arItem['_extra']['formt'] = array(
			'value'  => (string)$item->formt,
			'name'   => (string)$item->formt['name'],
			'xml_id' => (string)$item->formt['xml_id'],
		);

		$arItem['_extra']['sbjct'] = array(
			'value'  => (string)$item->sbjct,
			'name'   => (string)$item->sbjct['name'],
			'xml_id' => (string)$item->sbjct['xml_id'],
		);

		$arItem['_extra']['niche'] = array(
			'value'  => (string)$item->niche,
			'name'   => (string)$item->niche['name'],
			'xml_id' => (string)$item->niche['xml_id'],
		);

		$arItem['_extra']['categ'] = array(
			'value'  => (string)$item->categ,
			'name'   => (string)$item->categ['name'],
			'xml_id' => (string)$item->categ['xml_id'],
		);

		$arItem['_extra']['sgmnt'] = array(
			'value'  => (string)$item->sgmnt,
			'name'   => (string)$item->sgmnt['name'],
			'xml_id' => (string)$item->sgmnt['xml_id'],
		);

		foreach ($item->proizvedeniya->proizvedenie as $item) {
			$arItem['proizvedeniya'][] = (string)$item['guid'];
			$arItem['_extra']['proizvedeniya'][] = array(
				'value' => (string)$item,
				'guid'  => (string)$item['guid'],
				'vid'   => (string)$item['vid'],
			);
		}

		return $arItem;
	}
}
