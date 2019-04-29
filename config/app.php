<?php

return [
//    'ozon_api_client_id' => '3639',
//    'ozon_api_key' => '4e1938c1-671d-40cd-ab4b-dada9fa16159',
//    'ozon_base_url' => 'https://api-seller.ozon.ru',


     'ozon_api_client_id' => '466',
     'ozon_api_key' => '9753260e-2324-fde7-97f1-7848ed7ed097',
     'ozon_base_url' => 'http://cb-api.ozonru.me',
    
    'ozon_productinfo_url' => '/v1/product/info',
    'ozon_importproduct_url' => '/v1/product/import',
    'ozon_createproduct_url' => '/v1/products/create',
    'ozon_update_stocks_url' => '/v1/products/stocks',
    'ozon_updateproduct_url' => '/v1/products/update',
    'deactivate_product_url' => '/v1/products/deactivate',
    'activate_product_url' => '/v1/products/activate',
    'update_productprices_url' => '/v1/products/prices',
    'ozon_productlist_url' => '/v1/product/list',
    'ozon_product_group_attribute' => 8292,

    'ozon_orderlist_url' => '/v1/order/list',
    'ozon_orderinfo_url' => '/v1/order/{orderId}?translit=true',
    'ozon_approveorder_url' => '/v1/order/items/approve/crossborder',
    'ozon_cancelorder_url' => '/v1/order/items/cancel/crossborder',
    'ozon_shiporder_url' => '/v1/order/ship/crossborder',

    'ozon_categorylist_url' => '/v1/categories/tree',
    'ozon_categoryattributelist_url' => '/v1/categories/{category_id}/attributes',

    'dropshipp_key' => 'ak_f4d1b81c00443db21a8fdb569150ba79',
    'dropshipp_owner_token' => 'token_b569150ba79f4d1b81c00443d',

    'dropshipp_base_url' => 'http://dev.dropshipp.io123',
    'dropshipp_token_url' => '?-apikey={api_key}&token={owner_token}',
    //'dropshipp_order_url' => '/api/v1/orders/{owner_id}/{store_num}'
    'dropshipp_order_url' => '/api/v1/orders/1/{store_num}',

    'dropshipp_updateproduct_url' => '/api/v1/products/1/{product_id}',

    'order_approve_status' => 'PROCESSING',
    'order_cancel_status' => 'WILL_NOT_DELIVER',
    'order_ship_status' => 'SHIPPED',
    'order_delivered_status'=> 'DELIVERED',
    'order_cancel_reason' => 358,
    'russianpost_shipping_provider' => 15406651969000,
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

    'ozon_order_status' => [
        'AWAITING_APPROVE' => 'AWAITING_APPROVE',
        'AWAITING_PACKAGING' => 'AWAITING_PACKAGING'
    ],

    'sync_portion' => 100,
    'shipping_rf_code' => 'eecb1edb-d647-479f-aa6e-a2e3c5f16be4',

    'eddy_base_url' => 'https://mallmycom.helpdeskeddy.com/api/v2',
    'eddy_get_tickets_url' => '/tickets/',
    'eddy_get_tickets_fields_url' => '/custom_fields/',
    'eddy_api_key' => 'a51d05f2-c706-486e-9304-a938249c2353',
    'eddy_login' => 'julia.novikova@corp.mail.ru',
    'eddy_add_ticket_url' => '/tickets/',
    'eddy_add_message_url' => '/tickets/{ticketId}/posts/',
];