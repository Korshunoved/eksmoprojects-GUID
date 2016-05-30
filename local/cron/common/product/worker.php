<?php
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

set_time_limit(0);

while (ob_get_level()) {
	ob_end_flush();
}

ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_NOTICE & ~E_STRICT);

$modules = array('iblock', 'catalog');

foreach ($modules as $module) {
	if (!CModule::IncludeModule($module)) {
		die(sprintf('Unable to include module "%s"', $module));
	}
}

$logger = new \Quetzal\Tools\Logger\EchoLogger();
$worker = new \Quetzal\Data\Import\Product\FileTaskWorker($logger);

$logger->log('Worker has been started');

$taskManager = \Quetzal\Service\Task\QueueManager::getInstance();

$taskManager->executeTask(\Quetzal\Data\Import\Product\FileTaskWorker::TASK_NAME, array($worker, 'processTask'));
