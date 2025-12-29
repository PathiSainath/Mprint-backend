<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;

$carts = DB::table('carts')
    ->select('id', 'user_id', 'session_id', 'product_id', 'quantity', 'selected_attributes', 'unit_price', 'total_price')
    ->orderByDesc('id')
    ->limit(20)
    ->get();

echo json_encode($carts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
