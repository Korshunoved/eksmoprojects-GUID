<?php

namespace Quetzal\Data\Import\Product;

use Quetzal\Exception;
use Quetzal\Service\Import\DictionariesProvider;
use Quetzal\Tools\LoggerInterface;

/**
 * Обработчик XML-файла с товарами
 *
 * Class XmlFileProcessor
 *
 * @package Quetzal\Data\Import\Product
 */
class XmlFileProcessor
{
	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var ProductImporter
	 */
	protected $importer;

	/**
	 * @var XmlDataAdapter
	 */
	protected $dataConverter;

	/**
	 * @var int
	 */
	protected $defaultSection;

	/**
	 * Подготавливает процессор для работы
	 *
	 * @param LoggerInterface $logger
	 * @param int $defaultSection
	 */
	public function __construct(LoggerInterface $logger, $defaultSection = null)
	{
		$this->logger = $logger;
		$this->importer = new ProductImporter($logger);
		$this->dataConverter = new XmlDataAdapter();
		$this->defaultSection = $defaultSection;
	}

	/**
	 * @param $message
	 */
	protected function log($message)
	{
		$this->logger->log($message);
	}

	/**
	 * @param string $fileName
	 * @param string $feedName
	 */
	protected function checkParams($fileName, $feedName)
	{
		if (!$fileName || !file_exists($fileName)) {
			throw new \InvalidArgumentException(sprintf('File "%s" not found', $fileName));
		}

		if (strlen($feedName) == 0) {
			throw new \InvalidArgumentException('Feed name not specified');
		}
	}

	/**
	 * Обрабатывает указанный файл в соотвествии с правилами для фида
	 *
	 * @param string $fileName Путь к обрабатываемому файлу
	 * @param string $feedName Название фида (вроде "eksmo", "kanc" и т.д.)
	 *
	 * @return bool
	 *
	 * @throws Exception
	 */
	public function processFile($fileName, $feedName)
	{
		$this->checkParams($fileName, $feedName);

		$this->importer->setAction($feedName);

		if ($this->defaultSection) {
			$defaultSection = $this->defaultSection;
		} else {
			$settings = \EnvironmentHelper::getParam('importProductFeeds');

			$defaultSection = isset($settings[$feedName])
				? $settings[$feedName]['default_section']
				: $settings['eksmo']['default_section'];
		}

		$this->importer->setDefaultSection($defaultSection);

		$xmlFile = simplexml_load_file($fileName);

		if (!$xmlFile) {
			throw new Exception(sprintf('Unable to read xml in file "%s"', $fileName));
		}

		foreach ($xmlFile->products->product as $product) {
			$productData = $this->dataConverter->convertItem($product);

			$this->importer->import($productData);
		}

		DictionariesProvider::getInstance()->refresh();

		return true;
	}
}
