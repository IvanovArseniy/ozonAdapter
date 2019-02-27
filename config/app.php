<?php

return [
    'ozon_api_client_id' => '466',
    'ozon_api_key' => '9753260e-2324-fde7-97f1-7848ed7ed097',

    'ozon_base_url' => 'http://cb-api.test.ozon.ru',
    'ozon_productinfo_url' => '/v1/product/info',
    'ozon_createrproduct_url' => '/v1/product/import',
    'ozon_update_stocks_url' => '/v1/products/stocks',
    'ozon_updateproduct_url' => '/v1/products/update',
    'deactivate_product_url' => '/v1/products/activate',
    'activate_product_url' => '/v1/products/deactivate',
    'update_productprices_url' => '/v1/products/prices',
    'ozon_product_group_attribute' => 8292,

    'ozon_orderlist_url' => '/v1/order/list',
    'ozon_orderinfo_url' => '/v1/order/{orderId}?translit=true',
    'ozon_approveorder_url' => '/v1/order/items/approve/crossborder',
    'ozon_cancelorder_url' => '/v1/order/items/cancel/crossborder',

    'ozon_categorylist_url' => '/v1/categories/tree',

    'dropshipp_key' => 'testKey',
    'dropshipp_owner_token' => 'testToken',

    'dropshipp_base_url' => 'http://dev.dropshipp.io',
    'dropshipp_token_url' => '?-apikey={api_key}&token={owner_token}',
    //'dropshipp_order_url' => '/api/v1/orders/{owner_id}/{store_num}'
    'dropshipp_order_url' => '/api/v1/orders/1/{store_num}',

    'active_category_id' => '1',
    'active_category_name' => 'test',

    'order_approve_status' => 'PROCESSING',
    'order_cancel_status' => 'RETURNED',
    'order_cancel_reason' => 358,
    'order_status' => [
        'AWAITING_APPROVE' => 'AWAITING_PROCESSING',
        'AWAITING_PACKAGING' => 'PROCESSING', 
        'AWAITING_DELIVER' => 'PROCESSING',
        'DELIVERING' => 'SHIPPED',
        'DELIVERED' => 'DELIVERED',
        //'CANCELLED' => 'WILL_NOT_DELIVER',
        'CANCELLED' => 'RETURNED',
        'DELIVERED' => 'READY_FOR_PICKUP'
    ]
];