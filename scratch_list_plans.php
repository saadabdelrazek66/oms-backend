<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$plans = App\Models\ContentPlan::with('client')->get();
foreach ($plans as $p) {
    echo $p->id . ' | ' . ($p->client ? $p->client->name : 'NoClient') . ' | ' . $p->plan_type . ' | ' . $p->name . PHP_EOL;
}
