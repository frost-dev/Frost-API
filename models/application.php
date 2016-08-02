<?php

// APIにアクセスするためのアプリケーションを管理します
class ApplicationModel
{
	// 操作対象のApplicationレコード
	private $applicationData;

	// コンテナー
	private $container;

	// ユーザーid
	private $userId;

	/*
	クラスの新しいインスタンスを初期化します
	userIdに関しては特殊なアクセスのためにnullを許容します
	*/
	public function __construct($applicationData, $container, $userId = null)
	{
		if (!$applicationData || !$container)
			throw new Exception('some arguments are empty');

		$this->container = $container;
		$this->applicationData = $applicationData;
	}

	// データベースのレコードを作成し、インスタンスを取得します
	public static function createRecord($userId, $name, $description, $requestedPermissions, $container)
	{
		if (!\Utility\Regex::isMatch('/^[a-z,-]+$/', $requestedPermissions))
			throw new \Utility\ApiException('format of permissions parameter is invalid', ['detail'=>'it is required to be constructed in "a" to "z", and ","']);

		$splitedPermissionsArray = explode(',', $requestedPermissions);

		$app = Model::factory('ApplicationData')->create();

		$timestamp = time();
		$isPermissionError = false;
		$invalidPermissionNames = [];
		$permissions = [];

		foreach ($requestedPermissions as $requestedPermission)
		{
			$isFound = false;

			foreach (self::$permissionTypes as $permissionType)
			{
				if($requestedPermission === $permissionType)
				{
					$isFound = true;

					if (in_array($requestedPermission, $permissions))
						throw new \Utility\ApiException('permissions is duplicate');

					array_push($permissions, $requestedPermission);
					break;
				}
			}

			if (!$isFound)
			{
				$isPermissionError = true;
				$invalidPermissionNames += $requestedPermission;
			}
		}

		if ($isPermissionError)
			throw new \Utility\ApiException('unknown permissions', $invalidPermissionNames);

		if (!Model::factory('ApplicationData')->where('name', $name)->find_many())
			throw new \Utility\ApiException('already exists.');

		$app->created_at = $timestamp;
		$app->creator_id = $userId;
		$app->name = $name;
		$app->description = $description;
		$app->permissions = implode(',', $permissions);

		$app->save();

		return new ApplicationModel($app, $container);
	}

	// 権限一覧
	public static $permissionTypes = [
		'ice-auth-host',       // 認証のホスト権限
		'application',         // 連携アプリ操作
		'application-special', // 連携アプリ特殊操作
		'account-read',        // アカウント情報の取得
		'account-write',       // アカウント情報の変更
		'account-special',     // アカウント情報の特殊操作
		'user-read',           // ユーザー情報の取得
		'user-write',          // ユーザーのフォロー等のアクション
		'post-read',           // 投稿の取得
		'post-write',          // 投稿の作成や削除等のアクション
	];

	/*
	アプリケーションキーを生成し、ハッシュを更新します
	データベースへのsaveはされません
	*/
	public function generateKey()
	{
		// 自分のアプリケーションのキー以外は拒否
		if ($this->userId !== null && $this->applicationData->creator_id !== $this->userId)
			throw new \Utility\ApiException('this key is managed by other user');

		$managementCode = rand(1, 99999);
		$key = self::buildKey($this->applicationData, $userId, $managementCode, $this->container);
		$keyHash = strtoupper(hash('sha256', $key));

		$this->applicationData->key_hash = $keyHash;
		$this->applicationData->management_code = $managementCode;

		return $key;
	}

	/*
	アプリケーションキーをデータベースから取得します
	*/
	public function getKey()
	{
		// 自分のアプリケーションのキー以外は拒否
		if ($this->userId !== null && $this->applicationData->creator_id !== $this->userId)
			throw new \Utility\ApiException('this key is managed by other user');

		if ($this->applicationData->key_hash === null)
			throw new \Utility\ApiException('key is empty');

		return self::buildKey($this->applicationData->id, $this->applicationData->creator_id, $this->applicationData->management_code, $this->container);
	}

	// キーに含まれるハッシュを構築します
	public static function buildHash($id, $userId, $managementCode, $container)
	{
		return strtoupper(hash('sha256', "{$container->config['application-key-base']}/{$userId}/{$id}/{$managementCode}"));
	}

	// キーを構築します
	public static function buildKey($id, $userId, $managementCode, $container)
	{
		$hash = buildHash($id, $userId, $managementCode, $container);
		return "{$id}-{$hash}.{$managementCode}";
	}

	// アプリケーションキーを検証します
	public static function validateKey($applicationKey, $container)
	{
		$match = \Utility\Regex::match('/([^-]+)-([^-]{64}).([^-]+)/', $applicationKey);

		if ($match === null)
			return false;

		$id = $match[1];
		$hash = $match[2];
		$managementCode = $match[3];

		$app = Model::factory('ApplicationData')->find_one($id);

		if (!$app)
			return false;

		// ハッシュを作ってみる
		$correctHash = buildHash($id, $app->creator_id, $managementCode, $container);

		// management_codeが一致していて且つハッシュ値が正しいかどうか
		$isPassed = $managementCode === $app->management_code && $hash === $correctHash;

		return $isPassed;
	}

	// レスポンス向けの配列データに変換します
	public function toArrayResponse()
	{
		$app = $this->applicationData;
		$data = [
			'created_at' => $app->created_at,
			'creator_id' => $app->creator_id,
			'name' => $app->name,
			'description' => $app->description,
			'permissions' => $app->permissions
		];

		return $data;
	}
}
