<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class AddSizeVariants extends Command
{
    /**
     * php artisan ecom:add-size-variants
     *
     * Options:
     *   --sizes=S,M,L,XL        Comma separated sizes (default: S,M,L,XL)
     *   --attribute-set=size     Attribute set slug (default: size)
     *   --dry-run                Preview only, no DB changes
     *   --force                  Skip confirmation prompt
     */
    protected $signature = 'ecom:add-size-variants
                            {--sizes=S,M,L,XL : Comma separated size names to add}
                            {--attribute-set=size : Attribute set slug (e.g. size)}
                            {--dry-run : Preview changes without saving to database}
                            {--force : Skip confirmation prompt}';

    protected $description = 'Add S/M/L/XL (or custom) size variants to all published products that do not have them';

    public function handle(): int
    {
        $this->info('');
        $this->info('╔══════════════════════════════════════════╗');
        $this->info('║   DelightHive - Add Size Variants        ║');
        $this->info('╚══════════════════════════════════════════╝');
        $this->info('');

        $isDryRun       = $this->option('dry-run');
        $sizesInput     = $this->option('sizes');
        $attributeSlug  = $this->option('attribute-set');
        $sizeNames      = array_map('trim', explode(',', $sizesInput));

        // ── 1. Find Attribute Set ────────────────────────────────────────
        $attributeSet = DB::table('ec_product_attribute_sets')
            ->where('slug', $attributeSlug)
            ->first();

        if (! $attributeSet) {
            $this->error("Attribute set with slug '{$attributeSlug}' not found in ec_product_attribute_sets.");
            $this->line("Available sets:");
            DB::table('ec_product_attribute_sets')->get()->each(function ($set) {
                $this->line("  id={$set->id}  slug={$set->slug}  title={$set->title}");
            });
            return self::FAILURE;
        }

        $this->line("✔ Attribute Set  : <fg=cyan>{$attributeSet->title}</> (id={$attributeSet->id})");

        // ── 2. Find Size Attributes ──────────────────────────────────────
        $attributes = DB::table('ec_product_attributes')
            ->where('attribute_set_id', $attributeSet->id)
            ->whereIn('title', $sizeNames)
            ->orderBy('order')
            ->get()
            ->keyBy('title');

        $missing = array_diff($sizeNames, $attributes->keys()->toArray());
        if ($missing) {
            $this->warn("These sizes NOT found in DB: " . implode(', ', $missing));
            $this->line("Available attributes for set '{$attributeSet->title}':");
            DB::table('ec_product_attributes')
                ->where('attribute_set_id', $attributeSet->id)
                ->orderBy('order')
                ->get()
                ->each(fn($a) => $this->line("  id={$a->id}  title={$a->title}  slug={$a->slug}"));
            $this->warn("Only matching sizes will be processed.");
        }

        if ($attributes->isEmpty()) {
            $this->error('No matching size attributes found. Aborting.');
            return self::FAILURE;
        }

        $this->line("✔ Sizes to add   : <fg=cyan>" . $attributes->pluck('title')->implode(', ') . "</>");
        $this->info('');

        // ── 3. Get all parent products ───────────────────────────────────
        $products = DB::table('ec_products')
            ->where('is_variation', 0)
            ->where('status', 'published')
            ->select('id', 'name', 'sku', 'price', 'sale_price', 'image', 'images',
                     'weight', 'brand_id', 'tax_id', 'created_by_type')
            ->get();

        $this->line("✔ Total products : <fg=cyan>{$products->count()}</>");

        // ── 4. Count what needs to be done ───────────────────────────────
        $toProcess = [];

        foreach ($products as $product) {
            foreach ($attributes as $sizeName => $attr) {
                $exists = DB::table('ec_product_variations as pv')
                    ->join('ec_product_variation_items as pvi', 'pvi.variation_id', '=', 'pv.id')
                    ->where('pv.configurable_product_id', $product->id)
                    ->where('pvi.attribute_id', $attr->id)
                    ->exists();

                if (! $exists) {
                    $toProcess[] = [
                        'product'    => $product,
                        'size_name'  => $sizeName,
                        'attr'       => $attr,
                    ];
                }
            }
        }

        if (empty($toProcess)) {
            $this->info('✅ All products already have ' . implode('/', $sizeNames) . ' variants. Nothing to do!');
            return self::SUCCESS;
        }

        $this->line("✔ Variants to add: <fg=yellow>" . count($toProcess) . "</>");
        $this->info('');

        // ── 5. Dry-run preview ───────────────────────────────────────────
        if ($isDryRun) {
            $this->warn('[DRY-RUN] No changes will be made. Preview:');
            $this->info('');
            $rows = [];
            foreach ($toProcess as $item) {
                $rows[] = [
                    $item['product']->id,
                    mb_strimwidth($item['product']->name, 0, 50, '...'),
                    $item['size_name'],
                ];
            }
            $this->table(['Product ID', 'Product Name', 'Size'], $rows);
            $this->info('');
            $this->info("Run without --dry-run to apply " . count($toProcess) . " changes.");
            return self::SUCCESS;
        }

        // ── 6. Confirmation ──────────────────────────────────────────────
        if (! $this->option('force')) {
            if (! $this->confirm("Add " . count($toProcess) . " size variants to database?")) {
                $this->info('Aborted.');
                return self::SUCCESS;
            }
        }

        // ── 7. Process ───────────────────────────────────────────────────
        $now     = now();
        $added   = 0;
        $errors  = 0;

        $bar = $this->output->createProgressBar(count($toProcess));
        $bar->setFormat(' %current%/%max% [%bar%] %percent:3s%% — %message%');
        $bar->setMessage('Starting...');
        $bar->start();

        // Group by product so we correctly set is_default per product
        $grouped = collect($toProcess)->groupBy(fn($item) => $item['product']->id);

        foreach ($grouped as $productId => $items) {
            $product = $items->first()['product'];

            // Ensure Size attribute set is linked to this product
            $linkedSet = DB::table('ec_product_with_attribute_set')
                ->where('attribute_set_id', $attributeSet->id)
                ->where('product_id', $productId)
                ->exists();

            if (! $linkedSet) {
                DB::table('ec_product_with_attribute_set')->insert([
                    'attribute_set_id' => $attributeSet->id,
                    'product_id'       => $productId,
                    'order'            => 0,
                ]);
            }

            // Check if this product already has ANY variant (to handle is_default)
            $hasExistingVariants = DB::table('ec_product_variations')
                ->where('configurable_product_id', $productId)
                ->exists();

            $isFirstForThisRun = true;

            foreach ($items as $item) {
                $sizeName = $item['size_name'];
                $attr     = $item['attr'];

                try {
                    DB::beginTransaction();

                    // Insert variant product
                    // Trim name to fit varchar(191) — keep " - XL" suffix (max 5 chars)
                    $suffix      = ' - ' . $sizeName;
                    $maxNameLen  = 191 - strlen($suffix);
                    $variantName = mb_substr($product->name, 0, $maxNameLen) . $suffix;

                    $newProductId = DB::table('ec_products')->insertGetId([
                        'name'                           => $variantName,
                        'description'                    => null,
                        'content'                        => null,
                        'status'                         => 'published',
                        'images'                         => $product->images,
                        'sku'                            => ($product->sku ?? 'SKU-' . $productId) . '-' . strtoupper($sizeName),
                        'order'                          => 0,
                        'quantity'                       => null,
                        'allow_checkout_when_out_of_stock' => 0,
                        'with_storehouse_management'     => 0,
                        'is_featured'                    => 0,
                        'brand_id'                       => $product->brand_id,
                        'is_variation'                   => 1,
                        'sale_type'                      => 0,
                        'price'                          => $product->price ?? 0,
                        'sale_price'                     => $product->sale_price,
                        'weight'                         => $product->weight ?? 0,
                        'tax_id'                         => $product->tax_id,
                        'stock_status'                   => 'in_stock',
                        'image'                          => $product->image,
                        'product_type'                   => 'physical',
                        'created_by_id'                  => 1,
                        'created_by_type'                => $product->created_by_type ?? 'Botble\\ACL\\Models\\User',
                        'approved_by'                    => 0,
                        'minimum_order_quantity'         => 0,
                        'maximum_order_quantity'         => 0,
                        'created_at'                     => $now,
                        'updated_at'                     => $now,
                    ]);

                    // is_default: S size of first batch is default if no existing variants
                    $isDefault = (! $hasExistingVariants && $isFirstForThisRun) ? 1 : 0;
                    $isFirstForThisRun = false;

                    // Insert variation
                    $variationId = DB::table('ec_product_variations')->insertGetId([
                        'product_id'              => $newProductId,
                        'configurable_product_id' => $productId,
                        'is_default'              => $isDefault,
                    ]);

                    // Insert variation item (attribute link)
                    DB::table('ec_product_variation_items')->insert([
                        'attribute_id' => $attr->id,
                        'variation_id' => $variationId,
                    ]);

                    DB::commit();
                    $added++;

                } catch (\Throwable $e) {
                    DB::rollBack();
                    $errors++;
                    $this->newLine();
                    $this->error("Error on product {$productId} size {$sizeName}: " . $e->getMessage());
                }

                $bar->setMessage("Product #{$productId} — {$sizeName}");
                $bar->advance();
            }
        }

        $bar->finish();
        $this->info('');
        $this->info('');
        $this->info("✅ Done! Added   : <fg=green>{$added}</> variants");

        if ($errors > 0) {
            $this->warn("⚠  Errors       : {$errors} (check logs)");
        }

        // ── 8. Summary ───────────────────────────────────────────────────
        $this->info('');
        $totalVariations = DB::table('ec_product_variations')->count();
        $totalVariants   = DB::table('ec_products')->where('is_variation', 1)->count();
        $this->line("DB Stats after run:");
        $this->line("  ec_products (variants)    : <fg=cyan>{$totalVariants}</>");
        $this->line("  ec_product_variations     : <fg=cyan>{$totalVariations}</>");
        $this->info('');

        return self::SUCCESS;
    }
}
