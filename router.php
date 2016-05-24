<?php

require_once __DIR__.'/controllers/access-key.php';
require_once __DIR__.'/controllers/ice-auth.php';
require_once __DIR__.'/controllers/post.php';
require_once __DIR__.'/controllers/request-key.php';
require_once __DIR__.'/controllers/user.php';

function indexPage ($req, $res, $container)
{
	$res->getBody()->write('Frost API Server');
	return $res;
}

$routes = [
	['method'=>'get',  'endpoint'=>'/',                                     'is-login'=>false, 'indexPage'],
	['method'=>'post', 'endpoint'=>'/internal/application/create',          'is-login'=>true,  'is-internal'=>true, 'Application::create'],
	['method'=>'post', 'endpoint'=>'/internal/application/regenerate-key',  'is-login'=>true,  'is-internal'=>true, 'indexPage'],
	['method'=>'get',  'endpoint'=>'/internal/application/application-key', 'is-login'=>true,  'is-internal'=>true, 'indexPage'],
	['method'=>'get',  'endpoint'=>'/internal/request-key',                 'is-login'=>false, 'is-internal'=>true, 'indexPage'],
	['method'=>'get',  'endpoint'=>'/internal/ice-auth/access-key',         'is-login'=>true,  'is-internal'=>true, 'indexPage'],
	['method'=>'post', 'endpoint'=>'/internal/account/create',              'is-login'=>false, 'is-internal'=>true, 'indexPage'],
	['method'=>'get',  'endpoint'=>'/ice-auth/authorize',                   'is-login'=>false, 'indexPage'],
	['method'=>'post', 'endpoint'=>'/ice-auth/authorize',                   'is-login'=>false, 'indexPage'],
	['method'=>'post', 'endpoint'=>'/post/create',                          'is-login'=>true,  'Post::create']
];

foreach ($routes as $route)
{
	$method = $route['method'];
	$endPoint = $route['endpoint'];

	$app->$method($endPoint, function ($req, $res, $args) use($routes, $route, $endPoint)
	{
		$isInternal = isset($route['is-internal']) ? $route['is-internal'] : false;
		$isLogin = isset($route['is-login']) ? $route['is-login'] : false;

		if(!is_callable(current(array_slice($route, -1, 1))))
			throw new Exception("last item of route was non-callable (endpoint: $endPoint)");
		$callable = current(array_slice($route, -1, 1));

		$controllerArgs = [$req, $res, $this];

		if ($isInternal)
		{
			if (!RequestKey::validate($req->getParams()['request-key'], $container->config, $container->dbManager))
				return withFailure($res, 'request-key is invalid. this endpoint is web only');
		}

		if ($isLogin)
		{
			
			$controllerArgs += $user;
		}

		return call_user_func_array($callable, $controllerArgs);
	});
}
