<?php

return [

    // Prefix nomor struk: {PREFIX}/{YYYY}/{MM}/{NNNN}
    'invoice_prefix' => env('POS_INVOICE_PREFIX', 'INV'),

    'owner' => [
        'name' => env('POS_OWNER_NAME', 'Owner Toko'),
        'email' => env('POS_OWNER_EMAIL', 'owner@pos.local'),
        'password' => env('POS_OWNER_PASSWORD', 'password'),
    ],

];
