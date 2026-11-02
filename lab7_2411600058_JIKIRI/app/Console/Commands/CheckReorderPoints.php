<?php

namespace App\Console\Commands;

use App\Services\ReorderService;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;

class CheckReorderPoints extends Command
{
    protected $signature = 'reorder:check {--dry-run : Show what would be ordered without creating anything}';

    protected $description = 'Find products at or below their reorder point and create draft purchase orders per supplier';

    public function handle(ReorderService $service): int
    {
        $this->info('Checking reorder points...');

        if ($this->option('dry-run')) {
            $rows = $service->getProductsNeedingReorder();
            $this->renderTable($rows);
            $this->comment('Dry run: nothing was created.');

            return self::SUCCESS;
        }

        $result = $service->generatePurchaseOrders();
        $this->renderTable($result['rows']);

        foreach ($result['skipped'] as $row) {
            $this->warn("Skipped {$row['product']->name}: no active supplier is linked to it.");
        }

        if ($result['orders']->isEmpty()) {
            $waiting = $result['drafted']->count();

            $this->info($waiting > 0
                ? "Nothing new to order: {$waiting} product(s) already have a draft purchase order waiting to be submitted."
                : 'Nothing to order: every product is covered.');

            return self::SUCCESS;
        }

        foreach ($result['orders'] as $order) {
            $this->line("Created {$order->reference_number} for {$order->supplier->name}: $"
                . number_format($order->total, 2) . " ({$order->items()->count()} item(s))");
        }

        $this->info($result['orders']->count() . ' draft purchase order(s) created. Admins and managers were notified.');

        return self::SUCCESS;
    }

    private function renderTable(Collection $rows): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $this->table(
            ['Product', 'Stock', 'On order', 'In draft', 'Reorder point', 'Order qty', 'Supplier'],
            $rows->map(fn ($row) => [
                $row['product']->name,
                $row['product']->quantity,
                $row['on_order'],
                $row['in_draft'],
                $row['reorder_point'],
                $row['quantity'],
                $row['supplier']?->name ?? '(none)',
            ])->all()
        );
    }
}