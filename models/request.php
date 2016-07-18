<?php
namespace Models;

// Webの認証ページの有効期限を管理する
class Request
{
	public static function create($applicationKey, $container)
	{
		$config = $container->config;
		$db = $container->dbManager;

		$num = rand(1, 99999);
		$time = time();

		$key = $time.'-'.$num.'-'.hash('sha256', $config['request-key-base'].$applicationKey.$time.$num);

		try
		{
			$db->executeQuery('insert into frost_request (created_at, application_key, key) values(?, ?, ?)', [$time, $applicationKey, $key]);
		}
		catch(PDOException $e)
		{
			throw new ApiException('faild to create database record', ['request-key']);
		}

		$request = fetchByKey($key, $db);

		return $request;
	}

	public static function fetchByKey($requestKey, $container)
	{
		$config = $container->config;
		$db = $container->dbManager;

		try
		{
			$requests = $db->executeQuery('select * from frost_request where key = ?', [$requestKey])->fetch();
		}
		catch(PDOException $e)
		{
			throw new ApiException('faild to fetch request');
		}

		if (count($requests) === 0)
			throw new ApiException('request not found');

		return $requests[0];
	}

	public static function validate($requestKey, $container)
	{
		$config = $container->config;
		$db = $container->dbManager;

		try
		{
			$request = self::fetchByKey($requestKey, $db);
		}
		catch(Exception $e)
		{
			return false;
		}

		return abs(time() - $request['created_at']) < $config['request-key-expire-sec'];
	}

	public static function destroy($requestKey, $container)
	{
		$config = $container->config;
		$db = $container->dbManager;

		try
		{
			$db->executeQuery('delete from frost_request where key = ?', [$requestKey]);
		}
		catch(PDOException $e)
		{
			throw new ApiException('faild to destroy database record');
		}

		return true;
	}
}
