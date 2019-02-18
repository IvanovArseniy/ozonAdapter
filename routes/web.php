<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It is a breeze. Simply tell Lumen the URIs it should respond to
| and give it the Closure to call when that URI is requested.
|
*/

$router->get('/', function () use ($router) {
    return $router->app->version();
});

$router->get('foo', function() {
    return 'Hello world!!@!';
});

$router->group(['prefix' => 'test'], function() use ($router) {
});

$router->group(['prefix' => 'products', 'middleware' => 'auth'], function() use ($router) {
    $router->get('/', 'ProductController@getProductList');
    $router->post('/', 'ProductController@createProduct');
});
