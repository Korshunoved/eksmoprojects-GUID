<?php

namespace Quetzal\Data\Import\Product;

use PhpAmqpLib\Message\AMQPMessage;
use Quetzal\Exception;
use Quetzal\Tools\LoggerInterface;

/**
 * Воркер для обработки заданий по импорту товаров из XML-файлов
 *
 * Class FileTaskWorker
 *
 * @package Quetzal\Data\Import\Product
 */
class FileTaskWorker
{
	/**
	 * Имя, которым помечаются задачи для этого воркера в очереди заданий
	 */
	const TASK_NAME = 'productsFileImport';

	/**
	 * @var LoggerInterface
	 */
	private $logger;

	/**
	 * @var XmlFileProcessor
	 */
	protected $processor;

	/**
	 * Подготавливает воркер для работы
	 *
	 * @param LoggerInterface $logger
	 */
	public function __construct(LoggerInterface $logger)
	{
		$this->logger = $logger;
		$this->processor = new XmlFileProcessor($logger);
	}

	/**
	 * @param $message
	 */
	protected function log($message)
	{
		$this->logger->log($message);
	}

	/**
	 * Метод, который передается в качестве callback'а в методы
	 * обработки очереди заданий
	 *
	 * @param AMQPMessage $message
	 *
	 * @return bool
	 */
	public function processTask(AMQPMessage $message)
	{
		$this->log(sprintf('Processing task "%s" with data "%s"', self::TASK_NAME, $message->body));

		$data = json_decode($message->body, true);

		if (is_array($data) && isset($data['action']) && isset($data['file'])) {
			try {
				$this->processor->processFile($data['file'], $data['action']);
				unlink($data['file']);
			} catch (\Exception $e) {
				$this->log($e->getMessage());
			}

			$message->delivery_info['channel']->basic_ack($message->delivery_info['delivery_tag']);

			return true;
		}

		$this->log(sprintf('Task (%s) data is invalid: "%s"', self::TASK_NAME, $message->body));

		return false;
	}
}
