<?php
/**
 * Загружает файлы для импорта товаров
 *
 * Запускается раз в сутки
 */
ignore_user_abort(true);
ini_set('default_socket_timeout', 900);
ini_set('memory_limit', '256M');

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

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');

while (ob_get_level()) {
	ob_end_flush();
}

ini_set('display_errors', 1);
error_reporting(E_ALL & ~E_NOTICE);

set_time_limit(0);

$logger = new \Quetzal\Tools\Logger\EchoLogger();

$secretKey = EnvironmentHelper::getParam('importSecretKey');
$actions = EnvironmentHelper::getParam('importProductFeeds');

$dataFolder = realpath(__DIR__ . '/../../../data');

$ctx = stream_context_create(array('http' => array('timeout' => 900)));

foreach ($actions as $kAction => $arParams) {
	$logger->log(sprintf('Start "%s" downloading', $kAction));

	$url = sprintf('https://api.eksmo.ru/v2/?action=%s&key=%s', $arParams['action'], $secretKey);
	$file = $url . '&page=1';
	$file = file_get_contents($file, false, $ctx);

	$xmlFile = simplexml_load_string($file);
	$pagesCount = $xmlFile->pages->all;

	$downloadedCount = 0;

	if ($xmlFile && $pagesCount > 0) {
		for ($i = 1; $pagesCount >= $i; $i++) {
			$targetFileName = sprintf('%s%sproducts_%04d.xml', $dataFolder, $arParams['dir'], $i);
			$file = $url . '&page=' . $i;

			if (copy($file, $targetFileName)) {
				$downloadedCount++;

				$logger->log(sprintf('%d of %d', $i, $pagesCount));
			} else {
				$logger->log(sprintf('Unable to copy "%s"', $file));
			}
		}
	}

	$logger->log(sprintf('Downloaded %s files', $downloadedCount));
}

$logger->log('Downloading is finished');
