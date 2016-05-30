<?php
/**
 * Планировщик заданий импорта товаров.
 *
 * Проверяет файлы, загруженные из МК и добавляет
 * в очередь задания для их обработки
 */
ignore_user_abort(true);
ini_set('memory_limit', '320M');

if (empty($_SERVER['DOCUMENT_ROOT'])) {
	$_SERVER['HTTP_HOST'] = 'fiction.eksmo.ru';
	$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../../../../fiction.eksmo.ru');
}

define('BX_BUFFER_USED', true);
define('NO_KEEP_STATISTIC', true);
define('NOT_CHECK_PERMISSIONS', true);
define('NO_AGENT_STATISTIC', true);
define('STOP_STATISTICS', true);
define('BX_CRONTAB_SUPPORT', true);
define('SITE_ID', 's1');

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

set_time_limit(3600);

while (ob_get_level()) {
	ob_end_flush();
}

ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_NOTICE);

$dataFolder = realpath(__DIR__ . '/../../../data');

$actions = EnvironmentHelper::getParam('importProductFeeds');

$logger = new \Quetzal\Tools\Logger\EchoLogger();

$logger->log('Start files analysing');

$queue = \Quetzal\Service\Task\QueueManager::getInstance();

foreach ($actions as $kAction => $arParams) {
	$logger->log(sprintf('Start %s analysing', $kAction));

	$dir = $dataFolder . $arParams['dir'];
	$pages = scandir($dir);

	foreach ($pages as $file) {
		$fileName = $dir . $file;

		if (is_file($fileName)) {
			$queue->registerTask(
				\Quetzal\Data\Import\Product\FileTaskWorker::TASK_NAME,
				json_encode(
					array(
						'action' => $kAction,
						'file'   => $fileName,
					)
				)
			);

			$logger->log(sprintf('Add file "%s" to task', $fileName));
		}
	}

	$logger->log(sprintf('Complete %s analysing', $kAction));
}

$logger->log('Complete files analysing');
