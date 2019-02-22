<?php

return [
    'ozon_api_client_id' => '466',
    'ozon_api_key' => '9753260e-2324-fde7-97f1-7848ed7ed097',

    'ozon_base_url' => 'http://cb-api.test.ozon.ru',
    'ozon_productlist_url' => '/v1/product/info',
    'ozon_createrpoduct_url' => '/v1/product/import',
    'ozon_update_stocks_url' => '/v1/products/stocks',
    'ozon_product_group_attribute' => 8292,

    'ozon_orderlist_url' => '/v1/order/list',
    'ozon_orderinfo_url' => '/v1/order/{orderId}?translit=true',


    'dropshipp_key' => 'testKey',
    'dropshipp_owner_token' => 'testToken',

    'dropshipp_base_url' => 'http://dev.dropshipp.io',
    'dropshipp_token_url' => '?-apikey={api_key}&token={owner_token}',
    //'dropshipp_order_url' => '/api/v1/orders/{owner_id}/{store_num}'
    'dropshipp_order_url' => '/api/v1/orders/1/{store_num}',

    'active_category_id' => '1',
    'active_category_name' => 'test'
];