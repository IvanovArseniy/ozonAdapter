<?php

return [
    'ozon_api_client_id' => '3639',
    'ozon_api_key' => '4e1938c1-671d-40cd-ab4b-dada9fa16159',

    'ozon_base_url' => 'https://api-seller.ozon.ru',
    'ozon_productinfo_url' => '/v1/product/info',
    'ozon_importproduct_url' => '/v1/product/import',
    'ozon_createproduct_url' => '/v1/products/create',
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
    'ozon_categoryattributelist_url' => '/v1/categories/{category_id}/attributes',

    'dropshipp_key' => 'ak_f4d1b81c00443db21a8fdb569150ba79',
    'dropshipp_owner_token' => 'token_b569150ba79f4d1b81c00443d',

    'dropshipp_base_url' => 'http://dev.dropshipp.io',
    'dropshipp_token_url' => '?-apikey={api_key}&token={owner_token}',
    //'dropshipp_order_url' => '/api/v1/orders/{owner_id}/{store_num}'
    'dropshipp_order_url' => '/api/v1/orders/1/{store_num}',

    'dropshipp_updateproduct_url' => '/api/v1/products/1/{product_id}',

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
    ],

    'sync_portion' => 100
];