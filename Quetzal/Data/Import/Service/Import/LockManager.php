<?php

namespace Quetzal\Service\Import;

use Quetzal\Pattern\SingletonInterface;

/**
 * Менеджер зависимых блокировок фоновых заданий (импорт/экспорт)
 *
 * Class LockManager
 *
 * @package Quetzal\Service\Import
 *
 * @author Grigory Bychek <gbychek@gmail.com>
 */
class LockManager implements SingletonInterface
{
	const GROUP_EXPORT = 'export';
	const GROUP_IMPORT = 'import';

	const DEFAULT_LOCK_TIME = 14400;

	const STATE_RUN = 'run';
	const STATE_STOP = 'stop';

	const LAUNCH_NOT_SCHEDULED = 0;
	const LAUNCH_SCHEDULED = 1;

	const STORAGE_TABLE_NAME = 'q_tasks_locks';

	/**
	 * @var self
	 */
	protected static $instance = null;

	/**
	 * @var \CDatabase
	 */
	protected $db;

	/**
	 * Известные диспетчеру задания
	 *
	 * @var array
	 */
	protected $registeredTasks = array(
		self::GROUP_EXPORT => array(
			'price_city_yml.php',
			's1_price_rec.php',
			'as_price_rec.php',
			'partner_xml.php',
		),
		self::GROUP_IMPORT => array(
			'inc.php',
			'price_import.php',
			'getRemainder.php',
		),
	);

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
	 * Подготавливает файл для хранения статуса блокировок
	 */
	protected function __construct()
	{
		global $DB;

		$this->db = $DB;
	}

	private function __clone()
	{
	}

	/**
	 * Получает данные из файла-хранилища
	 *
	 * @return array
	 */
	protected function load()
	{
		$result = $this->createStorageLayout();

		$dbItems = $this->db->Query(sprintf('SELECT * FROM %s', self::STORAGE_TABLE_NAME));

		while ($arItem = $dbItems->Fetch()) {
			$result[$arItem['group']][$arItem['name']] = array(
				'id'              => $arItem['id'],
				'state'           => $arItem['state'],
				'lastRun'         => $arItem['lastRun'],
				'maxLockInterval' => $arItem['maxLockInterval'],
				'launchScheduled' => $arItem['launchScheduled'],
			);
		}

		return $result;
	}

	/**
	 * Сохраняет данные в файл-хранилище
	 *
	 * @param array $data
	 */
	protected function save(array $data)
	{
		foreach ($data as $groupId => $tasks) {
			foreach ($tasks as $taskId => $task) {
				$arFields = array(
					'group'           => '\'' . $groupId . '\'',
					'name'            => '\'' . $taskId . '\'',
					'state'           => '\'' . $task['state'] . '\'',
					'launchScheduled' => '\'' . $task['launchScheduled'] . '\'',
				);

				if ($task['lastRun']) {
					$arFields['lastRun'] = '\'' . ($task['lastRun']) . '\'';
				}

				if ($task['maxLockInterval']) {
					$arFields['maxLockInterval'] = '\'' . ($task['maxLockInterval']) . '\'';
				}

				if (isset($task['id'])) {
					$this->db->Update(self::STORAGE_TABLE_NAME, $arFields, sprintf('WHERE id=\'%s\'', $task['id']));
				} else {
					$this->db->Insert(self::STORAGE_TABLE_NAME, $arFields);
				}
			}
		}
	}

	/**
	 * Разблокирует зависшие задачи, если таковые имеются
	 *
	 * @param array $data
	 *
	 * @return array
	 */
	private function unlockFrozenTasks(array $data)
	{
		$hasFrozenTasks = false;

		foreach ($data as $groupId => $tasks) {
			foreach ($tasks as $taskId => $task) {
				if ($task['state'] == self::STATE_RUN && ($task['lastRun'] + $task['maxLockInterval'] < time())) {
					$hasFrozenTasks = true;

					$task['state'] = self::STATE_STOP;

					$data[$groupId][$taskId] = $task;
				}
			}
		}

		if ($hasFrozenTasks) {
			$this->save($data);
		}

		return $data;
	}

	/**
	 * Проверяет может ли текущее задание запуститься
	 *
	 * @param string $taskName
	 * @param string $group
	 *
	 * @return bool
	 */
	public function canTaskRun($taskName, $group = self::GROUP_EXPORT)
	{
		if (isset($this->registeredTasks[$group]) && in_array($taskName, $this->registeredTasks[$group])) {
			$currentState = $this->load();
			$currentState = $this->unlockFrozenTasks($currentState);

			// Запрет параллельного запуска себя же
			if ($currentState[$group][$taskName]['state'] == self::STATE_RUN) {
				return false;
			// Запрет запуска экспорта, пока не отработал импорт
			} elseif ($group == self::GROUP_EXPORT && $this->isImportRun($currentState)) {
//				return false;
			}
		}

		return true;
	}

	/**
	 * Получает время последнего запуска задачи
	 *
	 * @param string $taskName
	 * @param string $group
	 *
	 * @return null
	 */
	public function getTaskLastRunTimestamp($taskName, $group = self::GROUP_EXPORT)
	{
		if (isset($this->registeredTasks[$group]) && in_array($taskName, $this->registeredTasks[$group])) {
			$currentState = $this->load();

			return isset($currentState[$group][$taskName]['lastRun']) ? $currentState[$group][$taskName]['lastRun'] : null;
		}

		return null;
	}

	/**
	 * Проверяет, запущен ли какой-то из скриптов импорта
	 *
	 * @param array $currentState
	 *
	 * @return bool
	 */
	private function isImportRun(array $currentState)
	{
		foreach ($currentState[self::GROUP_IMPORT] as $taskInfo) {
			if ($taskInfo['state'] == self::STATE_RUN) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Создает каркас для хранилища
	 *
	 * @return array
	 */
	protected function createStorageLayout()
	{
		$layout = array();

		foreach ($this->registeredTasks as $group => $tasks) {
			$layout[$group] = array();

			foreach ($tasks as $task) {
				$layout[$group][$task] = array(
					'state'           => self::STATE_STOP,
					'lastRun'         => null,
					'maxLockInterval' => self::DEFAULT_LOCK_TIME,
					'launchScheduled' => self::LAUNCH_NOT_SCHEDULED,
				);
			}
		}

		return $layout;
	}

	/**
	 * Обновляет информацию о задаче в хранилище
	 *
	 * @param string $task
	 * @param string $group
	 * @param string $state
	 * @param int $lastRun
	 */
	public function updateState($task, $group, $state, $lastRun = null)
	{
		$currentState = $this->load();

		if ($currentState && isset($currentState[$group][$task])) {
			$currentState[$group][$task]['state'] = $state;

			if ($lastRun) {
				$currentState[$group][$task]['lastRun'] = $lastRun;
			}

			if ($state == self::STATE_RUN) {
				$currentState[$group][$task]['launchScheduled'] = self::LAUNCH_NOT_SCHEDULED;
			}

			$this->save($currentState);
		}
	}

	/**
	 * Проверяет запланирован ли запуск указанной задачи
	 *
	 * @param string $taskName
	 * @param string $group
	 *
	 * @return bool
	 */
	public function isLaunchScheduled($taskName, $group = self::GROUP_EXPORT)
	{
		if (isset($this->registeredTasks[$group]) && in_array($taskName, $this->registeredTasks[$group])) {
			$currentState = $this->load();

			return $currentState[$group][$taskName]['launchScheduled'] == self::LAUNCH_SCHEDULED;
		}

		return true;
	}

	/**
	 * Обновляет информацию о запланированности запуска задачи
	 *
	 * @param string $task
	 * @param string $group
	 * @param int $state
	 */
	public function setLaunchScheduled($task, $group = self::GROUP_EXPORT, $state = self::LAUNCH_NOT_SCHEDULED)
	{
		$currentState = $this->load();

		if ($currentState && isset($currentState[$group][$task])) {
			$currentState[$group][$task]['launchScheduled'] = $state;

			// Нельзя запланировать запуск уже выполняющейся задачи (защита от "накладок")
			if ($currentState[$group][$task]['state'] == self::STATE_RUN) {
				$currentState[$group][$task]['launchScheduled'] = self::LAUNCH_NOT_SCHEDULED;
			}

			$this->save($currentState);
		}
	}

	/**
	 * Получает информацию по заданиям с запланированным запуском
	 *
	 * @return array
	 */
	public function getScheduledTasks()
	{
		$result = array();

		$currentState = $this->load();

		foreach ($currentState as $groupId => $tasks) {
			foreach ($tasks as $taskId => $task) {
				if ($task['launchScheduled'] == self::LAUNCH_SCHEDULED) {
					$result[$groupId][$taskId] = $task;
				}
			}
		}

		return $result;
	}
}
