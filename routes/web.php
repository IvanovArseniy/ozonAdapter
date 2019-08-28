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

$router->group(['prefix' => '1/products', 'middleware' => 'auth'], function() use ($router) {
    $router->get('/{productId}', 'ProductController@getProductInfo');
    $router->post('/', 'ProductController@createProduct');
    $router->post('{productId}', 'ProductController@createProductCombination');
    $router->put('/{productId}', 'ProductController@updateProduct');
    $router->delete('/{productId}', 'ProductController@deleteProduct');

    $router->post('/{productId}/image', 'ProductController@addMainImage');
    $router->post('/{productId}/gallery', 'ProductController@addGalleryImage');
    $router->delete('/{productId}/gallery/{imageId}', 'ProductController@deleteGalleryImage');
    $router->post('/{productId}/combinations/{combinationId}/image', 'ProductController@addGalleryImageForCombination');
});

$router->group(['prefix' => '1/tech', 'middleware' => 'auth'], function() use ($router) {
    $router->get('/product/sync', 'ProductController@syncProducts');
    $router->get('/product/create', 'ProductController@scheduleProductCreation');
    $router->get('/product/setIds', 'ProductController@setProductExternalId');
    $router->get('/product/notify', 'ProductController@notifyProducts');
    $router->get('/product/schedulejobs', 'ProductController@scheduleJobs');
    $router->get('/product/setstock', 'ProductController@setStock');
    $router->get('/order/setOrderNr', 'OrderController@setOrderNr');
    $router->get('/order/notify', 'OrderController@sendOrderNotifications');

    $router->get('/product/gearman', 'ProductController@gearmanTry');
    $router->get('/queue/addForRetry', 'QueueController@addForRetry');
    $router->get('/testMethod/{test}', 'ProductController@testMethod');
});

$router->group(['prefix' => '1/orders', 'middleware' => 'auth'], function() use ($router) {
    $router->get('/{orderNr}', 'OrderController@getOrderInfo');
    $router->put('/{orderNr}', 'OrderController@setOrderStatus');
    $router->get('/', 'OrderController@getOrderList');
});

$router->group(['prefix' => '1/categories', 'middleware' => 'auth'], function() use ($router) {
    $router->get('/{categoryId}', 'CategoryController@getCategoryName');
    $router->get('/', 'CategoryController@getCategoryList');
    $router->post('/', 'CategoryController@addCategory');
    $router->put('/{categoryId}', 'CategoryController@updateCategoryName');
    $router->post('/insert', 'CategoryController@uploadCategories');
});

$router->group(['prefix' => '1/chatsync', /*'middleware' => 'auth'*/], function() use ($router) {
    $router->get('/', 'ChatController@handle');
});

$router->group(['prefix' => '1/helpdesksync', /*'middleware' => 'auth'*/], function() use ($router) {
    $router->get('/', 'ChatController@handleEddyChats');
});