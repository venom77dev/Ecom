<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportProductsFromSource extends Command
{
    protected $signature = 'products:import-from-source';

    protected $description = 'Import products with all related data from source database (price 300-900) to target database';

    protected $sourceDb = [
        'host' => 'localhost',
        'port' => 3306,
        'database' => 'db_dazzlenook',
        'username' => 'root',
        'password' => '',
    ];

    protected $sourcePdo = null;

    protected $targetPdo = null;

    protected $skuMapping = [];

    protected $categoriesMapping = [];

    protected $products = [];

    protected $categories = [];

    protected $productCategories = [];

    protected $variations = [];

    protected $variationProducts = [];

    protected $slugs = [];

    public function handle()
    {
        $this->info('Starting product import process...');

        $this->connectSourceDb();
        $this->connectTargetDb();
        $this->step1GetAllProductData();
        $this->step2UpdatePricesOnTarget();
        $this->step3ImportProducts();
        $this->step4ImportCategories();
        $this->step5ImportProductCategories();
        $this->step6ImportVariations();
        $this->step7ImportSlugs();

        $this->info('Import completed successfully!');

        return 0;
    }

    protected function connectSourceDb()
    {
        try {
            $this->sourcePdo = new \PDO(
                "mysql:host={$this->sourceDb['host']};port={$this->sourceDb['port']};dbname={$this->sourceDb['database']}",
                $this->sourceDb['username'],
                $this->sourceDb['password']
            );
            $this->info('Connected to source database');
        } catch (\Exception $e) {
            $this->error('Source database connection failed: '.$e->getMessage());
            exit(1);
        }
    }

    protected function connectTargetDb()
    {
        $targetDb = [
            'host' => config('database.connections.mysql.host'),
            'port' => config('database.connections.mysql.port'),
            'database' => config('database.connections.mysql.database'),
            'username' => config('database.connections.mysql.username'),
            'password' => config('database.connections.mysql.password'),
        ];

        try {
            $this->targetPdo = new \PDO(
                "mysql:host={$targetDb['host']};port={$targetDb['port']};dbname={$targetDb['database']}",
                $targetDb['username'],
                $targetDb['password']
            );
            $this->info('Connected to target database');
        } catch (\Exception $e) {
            $this->error('Target database connection failed: '.$e->getMessage());
            exit(1);
        }
    }

    protected function step1GetAllProductData()
    {
        $this->info('Step 1: Getting all product data from source database...');

        $stmt = $this->sourcePdo->prepare('SELECT ec_products.* FROM ec_products WHERE ec_products.status = \'published\' AND ec_products.is_variation = 0 AND ec_products.price BETWEEN 300 AND 900 ORDER BY RAND()');
        $stmt->execute();
        $stmt->execute();
        $this->products = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->info('Found '.count($this->products).' products');

        if (empty($this->products)) {
            $this->warn('No products found');
            exit(0);
        }

        $productIds = array_column($this->products, 'id');

        $this->info('Getting related data...');

        $stmt = $this->sourcePdo->query('SELECT * FROM ec_product_categories');
        $this->categories = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $stmt = $this->sourcePdo->prepare("SELECT * FROM ec_product_category_product WHERE product_id IN ($placeholders)");
        $stmt->execute($productIds);
        $this->productCategories = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stmt = $this->sourcePdo->prepare("SELECT * FROM ec_product_variations WHERE product_id IN ($placeholders)");
        $stmt->execute($productIds);
        $this->variations = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $variationIds = array_column($this->variations, 'id');
        if (! empty($variationIds)) {
            $placeholders2 = implode(',', array_fill(0, count($variationIds), '?'));
            $stmt = $this->sourcePdo->prepare("SELECT * FROM ec_products WHERE id IN ($placeholders2)");
            $stmt->execute($variationIds);
            $this->variationProducts = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } else {
            $this->variationProducts = [];
        }

        $stmt = $this->sourcePdo->query('SELECT * FROM slugs');
        $this->slugs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->info('Got all related data');
    }

    protected function step2UpdatePricesOnTarget()
    {
        $this->info('Step 2: Running price update queries on target database BEFORE import...');

        try {
            $this->targetPdo->exec('
                UPDATE ec_products
                SET price = COALESCE(price, 0) + 1100
                WHERE price <= 900
            ');

            $this->targetPdo->exec('
                UPDATE ec_products
                SET sale_price = NULL
                WHERE sale_price IS NOT NULL
            ');

            $this->targetPdo->exec('
                UPDATE ec_products AS variation
                INNER JOIN ec_product_variations AS pv
                    ON variation.id = pv.product_id
                INNER JOIN ec_products AS parent
                    ON pv.configurable_product_id = parent.id
                SET
                    variation.price = parent.price,
                    variation.sale_price = parent.sale_price
                WHERE variation.is_variation = 1
            ');

            $this->info('Price updates completed');
        } catch (\Exception $e) {
            $this->error('Price update failed: '.$e->getMessage());
        }
    }

    protected function step3ImportProducts()
    {
        $this->info('Step 3: Importing products to target database...');

        $inserted = 0;
        $skipped = 0;

        foreach ($this->products as $product) {
            $stmt = $this->targetPdo->prepare('SELECT id FROM ec_products WHERE sku = ? LIMIT 1');
            $stmt->execute([$product['sku']]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            $now = now()->format('Y-m-d H:i:s');

            if ($existing) {
                $this->skuMapping[$product['sku']] = $existing['id'];
                $skipped++;

                continue;
            }

            $stmt = $this->targetPdo->prepare("
                INSERT INTO ec_products (
                    name, description, content, status, images, sku, `order`, quantity,
                    allow_checkout_when_out_of_stock, with_storehouse_management, is_featured,
                    brand_id, is_variation, sale_type, price, sale_price, start_date, end_date,
                    length, wide, height, weight, tax_id, views, created_at, updated_at,
                    stock_status, store_id, created_by_id, created_by_type, approved_by, image,
                    product_type, barcode, cost_per_item, generate_license_code,
                    minimum_order_quantity, maximum_order_quantity
                ) VALUES (
                    ?, ?, ?, 'published', ?, ?, 0, ?,
                    ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?,
                    ?, ?, ?, ?, ?, ?, ?, ?,
                    'in_stock', NULL, 1, 'Botble\\ACL\\Models\\User', 1, ?,
                    'physical', ?, ?, ?,
                    ?, ?
                )
            ");

            $rawImages = json_decode($product['images'] ?? '[]', true) ?: [];
            $newImages = json_encode(array_map(fn($img) => 'new_product/' . basename($img), $rawImages));
            $newImage = $product['image'] ? 'new_product/' . basename($product['image']) : null;

            $stmt->execute([
                $product['name'],
                $product['description'],
                $product['content'] ?? null,
                $newImages,
                $product['sku'],
                $product['quantity'] ?? 20,
                $product['allow_checkout_when_out_of_stock'] ?? 0,
                $product['with_storehouse_management'] ?? 1,
                $product['is_featured'] ?? 0,
                $product['brand_id'] ?? null,
                $product['is_variation'] ?? 0,
                $product['sale_type'] ?? 0,
                $product['price'],
                $product['sale_price'] ?? null,
                $product['start_date'] ?? null,
                $product['end_date'] ?? null,
                $product['length'] ?? null,
                $product['wide'] ?? null,
                $product['height'] ?? null,
                $product['weight'] ?? null,
                $product['tax_id'] ?? null,
                $product['views'] ?? 0,
                $now,
                $now,
                $newImage,
                $product['barcode'] ?? null,
                $product['cost_per_item'] ?? null,
                $product['generate_license_code'] ?? 0,
                $product['minimum_order_quantity'] ?? 1,
                $product['maximum_order_quantity'] ?? 0,
            ]);

            $this->skuMapping[$product['sku']] = $this->targetPdo->lastInsertId();
            $inserted++;
        }

        $this->info("Products: {$inserted} inserted, {$skipped} updated");
    }

    protected function step4ImportCategories()
    {
        $this->info('Step 4: Importing categories...');

        $inserted = 0;

        foreach ($this->categories as $category) {
            $stmt = $this->targetPdo->prepare('SELECT id FROM ec_product_categories WHERE name = ? LIMIT 1');
            $stmt->execute([$category['name']]);
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($existing) {
                $this->categoriesMapping[$category['id']] = $existing['id'];

                continue;
            }

            $now = now()->format('Y-m-d H:i:s');

            $stmt = $this->targetPdo->prepare('
                INSERT INTO ec_product_categories (
                    name, parent_id, description, status, `order`, image, is_featured,
                    created_at, updated_at, icon, icon_image
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');

            $stmt->execute([
                $category['name'],
                $category['parent_id'] ?? 0,
                $category['description'] ?? null,
                $category['status'] ?? 'published',
                $category['order'] ?? 0,
                $category['image'] ?? null,
                $category['is_featured'] ?? 0,
                $now,
                $now,
                $category['icon'] ?? null,
                $category['icon_image'] ?? null,
            ]);

            $this->categoriesMapping[$category['id']] = $this->targetPdo->lastInsertId();
            $inserted++;
        }

        $this->info("Categories: {$inserted} imported");
    }

    protected function step5ImportProductCategories()
    {
        $this->info('Step 5: Importing product-category relations...');

        $inserted = 0;

        foreach ($this->productCategories as $pc) {
            if (! isset($this->skuMapping[$pc['product_id']]) || ! isset($this->categoriesMapping[$pc['category_id']])) {
                continue;
            }

            $productId = $this->skuMapping[$pc['product_id']];
            $categoryId = $this->categoriesMapping[$pc['category_id']];

            $stmt = $this->targetPdo->prepare('
                SELECT 1 FROM ec_product_category_product
                WHERE product_id = ? AND category_id = ? LIMIT 1
            ');
            $stmt->execute([$productId, $categoryId]);
            if ($stmt->fetch()) {
                continue;
            }

            $stmt = $this->targetPdo->prepare('
                INSERT INTO ec_product_category_product (product_id, category_id) VALUES (?, ?)
            ');
            $stmt->execute([$productId, $categoryId]);
            $inserted++;
        }

        $this->info("Product-Category relations: {$inserted} imported");
    }

    protected function step6ImportVariations()
    {
        $this->info('Step 6: Importing variations...');

        $inserted = 0;

        foreach ($this->variations as $variation) {
            if (! isset($this->skuMapping[$variation['product_id']])) {
                continue;
            }

            $productId = $this->skuMapping[$variation['product_id']];

            $stmt = $this->targetPdo->prepare('
                INSERT INTO ec_product_variations (
                    product_id, configurable_product_id, is_default
                ) VALUES (?, ?, ?)
            ');

            $stmt->execute([
                $productId,
                $variation['configurable_product_id'] ?? $productId,
                $variation['is_default'] ?? 0,
            ]);

            $variationId = $this->targetPdo->lastInsertId();

            foreach ($this->variationProducts as $vp) {
                if ($vp['id'] == $variation['id']) {
                    $stmt = $this->targetPdo->prepare('
                        UPDATE ec_products SET is_variation = 1 WHERE id = ?
                    ');
                    $stmt->execute([$productId]);
                    break;
                }
            }

            $inserted++;
        }

        $this->info("Variations: {$inserted} imported");
    }

    protected function step7ImportSlugs()
    {
        $this->info('Step 7: Importing slugs...');

        $inserted = 0;

        foreach ($this->slugs as $slug) {
            $refId = null;

            if ($slug['reference_type'] == 'Botble\Ecommerce\Models\Product') {
                $stmt = $this->sourcePdo->prepare('SELECT sku FROM ec_products WHERE id = ?');
                $stmt->execute([$slug['reference_id']]);
                $product = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($product && isset($this->skuMapping[$product['sku']])) {
                    $refId = $this->skuMapping[$product['sku']];
                }
            }

            if (! $refId) {
                continue;
            }

            $stmt = $this->targetPdo->prepare('
                SELECT 1 FROM slugs WHERE `key` = ? AND `prefix` = ? LIMIT 1
            ');
            $stmt->execute([$slug['key'], $slug['prefix']]);
            if ($stmt->fetch()) {
                continue;
            }

            $now = now()->format('Y-m-d H:i:s');

            $stmt = $this->targetPdo->prepare('
                INSERT INTO slugs (`key`, reference_id, reference_type, prefix, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?)
            ');

            $stmt->execute([
                $slug['key'],
                $refId,
                $slug['reference_type'],
                $slug['prefix'],
                $now,
                $now,
            ]);

            $inserted++;
        }

        $this->info("Slugs: {$inserted} imported");
    }
}
