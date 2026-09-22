<?php

declare(strict_types=1);

use Bitrix\Main\Loader;
use Gorshkov\CatalogSentinel\Application\Export\ScanExporter;
use Gorshkov\CatalogSentinel\Bitrix\Admin\AdminDataProvider;
use Gorshkov\CatalogSentinel\Bitrix\Admin\AdminGuard;

require $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php';
Loader::includeModule(AdminGuard::MODULE_ID) || die();
AdminGuard::requireRead();
$id = max(0, (int) ($_GET['id'] ?? 0));
$format = strtolower((string) ($_GET['format'] ?? 'json'));
$scan = (new AdminDataProvider())->scan($id);
if ($scan === null || !in_array($format, ['json', 'csv'], true)) {
    http_response_code(404);
    exit;
}
$exporter = new ScanExporter();
$content = $format === 'csv' ? $exporter->csv($scan) : $exporter->json($scan);
header('Content-Type: ' . ($format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/json; charset=UTF-8'));
header('Content-Disposition: attachment; filename="catalog-sentinel-scan-' . $id . '.' . $format . '"');
header('X-Content-Type-Options: nosniff');
echo $content;
exit;
