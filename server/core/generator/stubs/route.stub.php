<?= '<?php' ?>

// 由代码生成器生成，`php start.php reload` 后生效。
<?php
$lines = [];
$lines[] = "use app\\adminapi\\controller\\{$module}\\{$model}Controller;";
$lines[] = 'use Webman\\Route;';
$lines[] = '';
$lines[] = "Route::group('/{$module}/{$modelKebab}', function () {";
$lines[] = "    Route::get('', [{$model}Controller::class, 'index']);";
$lines[] = "    Route::post('/batch-delete', [{$model}Controller::class, 'batchDelete']);";
if ($hasStatus) {
    $lines[] = "    Route::put('/{id:\\d+}/status', [{$model}Controller::class, 'status']);";
}
$lines[] = "    Route::get('/{id:\\d+}', [{$model}Controller::class, 'show']);";
$lines[] = "    Route::post('', [{$model}Controller::class, 'store']);";
$lines[] = "    Route::put('/{id:\\d+}', [{$model}Controller::class, 'update']);";
$lines[] = "    Route::delete('/{id:\\d+}', [{$model}Controller::class, 'delete']);";
$lines[] = "})->middleware(\$adminAuth);";

echo implode("\n", $lines) . "\n";
