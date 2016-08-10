<?php

/**
 * アプリケーションによるAPIアクセスのインスタンスを管理します
 */
class ApplicationAccessFactory
{
	private $database;
	private $config;
	private $regex;
	private $helper;

	public function __construct(DatabaseAccess $database, $config, \Utility\Regex $regex, ApplicationAccessHelper $helper)
	{
		if ($config === null)
			throw new \Exception('argument is empty');

		$this->atabase = $database;
		$this->config = $config;
		$this->regex = $regex;
		$this->helper = $helper;
	}

	/**
	 * データベースのレコードを作成し、インスタンスを取得します
	 */
	public function create($applicationId, $userId, $applicationModel, $userModel)
	{
		if ($applicationId === null || $userId === null)
			throw new Exception('some arguments are empty');

		$record = $this->database->create($this->config['db']['table-names']['application-access'], [
			'created_at' => time(),
			'user_id' => $userId,
			'application_id' => $applicationId
		]);
		$record->save();

		return new ApplicationAccessData($this, $applicationModel, $userModel, $this->config, $this->helper, $record);
	}

	/**
	 * 条件によってレコードを検索してインスタンスを取得します
	 *
	 * @param array $wheres 条件の連想配列(where句)
	 * @throws \Exception
	 * @throws \Utility\ApiException
	 * @return ApplicationData インスタンス
	 */
	public function findOneWithFilters(array $wheres, $applicationModel, $userModel)
	{
		if ($wheres === null)
			throw new \Exception('argument is empty');

		$record = $this->database->findOneWithFilters($this->config['db']['table-names']['application'], $wheres);

		if (!$record)
			throw new \Utility\ApiException('application not found', [], 404);

		return new ApplicationData($this, $applicationModel, $userModel, $this->config, $this->helper, $record);
	}

	/**
	 * 条件によってレコードを検索して複数のインスタンスを取得します
	 *
	 * @param array $wheres 条件の連想配列(where句)
	 * @throws \Exception
	 * @throws \Utility\ApiException
	 * @return array ApplicationDataの配列
	 */
	public function findManyWithFilters(array $wheres, $applicationModel, $userModel)
	{
		if ($wheres === null)
			throw new \Exception('argument is empty');

		$records = $this->database->findManyWithFilters($this->config['db']['table-names']['application'], $wheres);

		if (count($records) === 0)
			throw new \Utility\ApiException('application not found', [], 404);

		foreach($records as $record)
			array_push($instances, new ApplicationData($this, $applicationModel, $userModel, $this->config, $this->helper, $record));

		return $instances;
	}

	/**
	 * アクセスキーを構成するために必要なハッシュを構築します
	 *
	 * @param int $applicationId アプリケーションID
	 * @param int $userId ユーザーID
	 * @param int $keyCode キーの管理コード
	 * @throws \Exception
	 * @return string アクセスキーを構成するために必要なハッシュ
	 */
	private function buildKeyHash($applicationId, $userId, $keyCode)
	{
		if ($applicationId === null || $userId === null || $keyCode === null)
			throw new \Exception('argument is empty');

		return strtoupper(hash('sha256', $this->config['access-key-base'].'/'.$applicationId.'/'.$userId.'/'.$keyCode));
	}

	/**
	 * アクセスキーを構築します
	 *
	 * @param int $applicationId アプリケーションID
	 * @param int $userId ユーザーID
	 * @param int $keyCode キーの管理コード
	 * @throws \Exception
	 * @return string アプリケーションキー
	 */
	private function buildKey($applicationId, $userId, $keyCode)
	{
		if ($applicationId === null || $userId === null || $keyCode === null)
			throw new \Exception('argument is empty');

		$hash = $this->buildKeyHash($applicationId, $userId, $keyCode);
		$accessKey = $userId.'-'.$hash.'.'.$keyCode;

		return $accessKey;
	}

	/**
	 * アクセスキーを配列に展開します
	 *
	 * @param string $accessKey アクセスキー
	 * @throws \Exception
	 * @throws \Utility\ApiException
	 * @return array id,hash,keyCodeの格納された配列
	 */
	public function parseKeyToArray($accessKey)
	{
		if ($accessKey === null)
			throw new \Exception('argument is empty');

		$match = $this->regex->match('/([^-]+)-([^-]{64}).([^-]+)/', $accessKey);

		if ($match === null)
			throw new \Utility\ApiException('access-key is invalid format');

		return [$match[1],$match[2],$match[3],'id'=>$match[1],'hash'=>$match[2],'keyCode'=>$match[3]];
	}

	/**
	 * アクセスキーを検証します
	 *
	 * @param string $accessKey アクセスキー
	 * @throws \Exception
	 * @return bool キーが有効であるかどうか
	 */
	public function verifyKey($accessKey)
	{
		if ($accessKey === null)
			throw new \Exception('argument is empty');

		$parseResult = $this->parseKeyToArray($accessKey);
		$accessModel = $this->findOneWithFilters([
			'user_id' => $parseResult['id'],
			'key_code' => $parseResult['keyCode']
		]);

		if (!$accessModel)
			return false;

		$correctHash = $this->buildHash($accessModel->record->application_id, $parseResult['id'], $parseResult['keyCode']);
		$isPassed = $parseResult['keyCode'] === $accessModel->record->key_code && $parseResult['hash'] === $correctHash;

		return $isPassed;
	}
}
