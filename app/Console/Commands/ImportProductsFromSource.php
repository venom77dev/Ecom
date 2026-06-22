<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class ImportProductsFromSource extends Command
{
    protected $signature = 'products:import-from-source';

    protected $description = 'Import products with all related data from source database (price 300-900) to target database';

    protected $sourceDb = [
        'host' => '116.202.152.43',
        'port' => 3306,
        'database' => 'dazzlenook',
        'username' => 'dazzlenook',
        'password' => '4qAnkFWg7qx$8?as',
    ];

    protected $sourcePdo = null;
    protected $targetPdo = null;

    // source product id => target product id
    protected $productIdMapping = [];

    // source variation id => target variation id
    protected $variationIdMapping = [];

    // source attribute_set id => target attribute_set id
    protected $attributeSetsMapping = [];

    // source attribute id => target attribute id
    protected $attributesMapping = [];

    // source category id => target category id
    protected $categoriesMapping = [];

    public function handle()
    {
        $this->info('Starting product import process...');

        $this->connectSourceDb();
        $this->connectTargetDb();

        $this->step1ImportProducts();
        $this->step2ImportCategories();
        $this->step3ImportProductCategories();
        $this->step4ImportAttributeSets();
        $this->step5ImportAttributes();
        $this->step6ImportProductAttributeSets();
        $this->step7ImportVariations();
        $this->step8ImportVariationItems();
        $this->step9ImportSlugs();

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
            $this->sourcePdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->info('Connected to source database');
        } catch (\Exception $e) {
            $this->error('Source DB connection failed: ' . $e->getMessage());
            exit(1);
        }
    }

    protected function connectTargetDb()
    {
        $targetDb = [
            'host'     => config('database.connections.mysql.host'),
            'port'     => config('database.connections.mysql.port'),
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
            $this->targetPdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            $this->info('Connected to target database');
        } catch (\Exception $e) {
            $this->error('Target DB connection failed: ' . $e->getMessage());
            exit(1);
        }
    }

    // -------------------------------------------------------
    // STEP 1: Import parent products (price 300-900)
    // -------------------------------------------------------
    protected function step1ImportProducts()
    {
        $this->info('Step 1: Importing parent products...');

        $stmt = $this->sourcePdo->query(
            "SELECT * FROM ec_products
             WHERE status = 'published' AND is_variation = 0 AND price BETWEEN 300 AND 900"
        );
        $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $this->info('Found ' . count($products) . ' source products');

        if (empty($products)) {
            $this->warn('No products found');
            exit(0);
        }

        $inserted = 0;
        $updated  = 0;

        foreach ($products as $p) {
            $rawImages = json_decode($p['images'] ?? '[]', true) ?: [];
            $newImages = json_encode(array_map(fn($img) => 'new_product/' . basename($img), $rawImages));
            $newImage  = $p['image'] ? 'new_product/' . basename($p['image']) : null;
            $now       = now()->format('Y-m-d H:i:s');

            // Check target by SKU
            $chk = $this->targetPdo->prepare('SELECT id FROM ec_products WHERE sku = ? LIMIT 1');
            $chk->execute([$p['sku']]);
            $existing = $chk->fetch(\PDO::FETCH_ASSOC);

            if ($existing) {
                // UPDATE
                $upd = $this->targetPdo->prepare("
                    UPDATE ec_products SET
                        name = ?, description = ?, content = ?, images = ?, image = ?,
                        quantity = ?, allow_checkout_when_out_of_stock = ?,
                        with_storehouse_management = ?, is_featured = ?,
                        brand_id = ?, sale_type = ?, price = ?, sale_price = ?,
                        start_date = ?, end_date = ?, length = ?, wide = ?,
                        height = ?, weight = ?, tax_id = ?,
                        barcode = ?, cost_per_item = ?,
                        minimum_order_quantity = ?, maximum_order_quantity = ?,
                        updated_at = ?
                    WHERE id = ?
                ");
                $upd->execute([
                    $p['name'], $p['description'], $p['content'] ?? null,
                    $newImages, $newImage,
                    $p['quantity'] ?? 20,
                    $p['allow_checkout_when_out_of_stock'] ?? 0,
                    $p['with_storehouse_management'] ?? 1,
                    $p['is_featured'] ?? 0,
                    $p['brand_id'] ?? null,
                    $p['sale_type'] ?? 0,
                    $p['price'], $p['sale_price'] ?? null,
                    $p['start_date'] ?? null, $p['end_date'] ?? null,
                    $p['length'] ?? null, $p['wide'] ?? null,
                    $p['height'] ?? null, $p['weight'] ?? null,
                    $p['tax_id'] ?? null,
                    $p['barcode'] ?? null, $p['cost_per_item'] ?? null,
                    $p['minimum_order_quantity'] ?? 1,
                    $p['maximum_order_quantity'] ?? 0,
                    $now,
                    $existing['id'],
                ]);
                $this->productIdMapping[$p['id']] = $existing['id'];
                $updated++;
            } else {
                // INSERT
                $ins = $this->targetPdo->prepare("
                    INSERT INTO ec_products (
                        name, description, content, status, images, sku, `order`, quantity,
                        allow_checkout_when_out_of_stock, with_storehouse_management, is_featured,
                        brand_id, is_variation, sale_type, price, sale_price, start_date, end_date,
                        length, wide,store_id, approved_by, height, weight, tax_id, views, created_at, updated_at,
                        stock_status, created_by_id, created_by_type, image,
                        product_type, barcode, cost_per_item, generate_license_code,
                        minimum_order_quantity, maximum_order_quantity
                    ) VALUES (
                        ?, ?, ?, 'published', ?, ?, 0, ?,
                        ?, ?, ?,
                        ?, 0, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?, ?,
                        'in_stock', 1, 'Botble\\ACL\\Models\\User', ?,
                        'physical', ?, ?, ?, ?, ?
                    )
                ");
                $ins->execute([
                    $p['name'], $p['description'], $p['content'] ?? null,
                    $newImages, $p['sku'],
                    $p['quantity'] ?? 20,
                    $p['allow_checkout_when_out_of_stock'] ?? 0,
                    $p['with_storehouse_management'] ?? 1,
                    $p['is_featured'] ?? 0,
                    $p['brand_id'] ?? null,
                    $p['sale_type'] ?? 0,
                    $p['price'], $p['sale_price'] ?? null,
                    $p['start_date'] ?? null, $p['end_date'] ?? null,
                    $p['length'] ?? null, $p['wide'] ?? null,
                    $p['height'] ?? null, $p['weight'] ?? null,
                    $p['tax_id'] ?? null,
                    $p['views'] ?? 0,
                    $now, $now,
                    $newImage,
                    $p['barcode'] ?? null, $p['cost_per_item'] ?? null,
                    $p['generate_license_code'] ?? 0,
                    $p['minimum_order_quantity'] ?? 1,
                    $p['maximum_order_quantity'] ?? 0,
                ]);
                $this->productIdMapping[$p['id']] = $this->targetPdo->lastInsertId();
                $inserted++;
            }
        }

        $this->info("Products: {$inserted} inserted, {$updated} updated");
    }

    // -------------------------------------------------------
    // STEP 2: Import categories
    // -------------------------------------------------------
    protected function step2ImportCategories()
    {
        $this->info('Step 2: Importing categories...');

        $stmt = $this->sourcePdo->query('SELECT * FROM ec_product_categories');
        $categories = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $inserted = 0;

        foreach ($categories as $c) {
            $chk = $this->targetPdo->prepare('SELECT id FROM ec_product_categories WHERE name = ? LIMIT 1');
            $chk->execute([$c['name']]);
            $existing = $chk->fetch(\PDO::FETCH_ASSOC);

            if ($existing) {
                $this->categoriesMapping[$c['id']] = $existing['id'];
                continue;
            }

            $now = now()->format('Y-m-d H:i:s');
            $ins = $this->targetPdo->prepare('
                INSERT INTO ec_product_categories
                    (name, parent_id, description, status, `order`, image, is_featured, created_at, updated_at, icon, icon_image)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $ins->execute([
                $c['name'], $c['parent_id'] ?? 0, $c['description'] ?? null,
                $c['status'] ?? 'published', $c['order'] ?? 0,
                $c['image'] ?? null, $c['is_featured'] ?? 0,
                $now, $now,
                $c['icon'] ?? null, $c['icon_image'] ?? null,
            ]);
            $this->categoriesMapping[$c['id']] = $this->targetPdo->lastInsertId();
            $inserted++;
        }

        $this->info("Categories: {$inserted} inserted");
    }

    // -------------------------------------------------------
    // STEP 3: Product - Category relations
    // -------------------------------------------------------
    protected function step3ImportProductCategories()
    {
        $this->info('Step 3: Importing product-category relations...');

        if (empty($this->productIdMapping)) {
            $this->warn('No product mapping, skipping');
            return;
        }

        $sourceProductIds = array_keys($this->productIdMapping);
        $placeholders     = implode(',', array_fill(0, count($sourceProductIds), '?'));

        $stmt = $this->sourcePdo->prepare("SELECT * FROM ec_product_category_product WHERE product_id IN ($placeholders)");
        $stmt->execute($sourceProductIds);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $inserted = 0;

        foreach ($rows as $row) {
            $targetProductId   = $this->productIdMapping[$row['product_id']] ?? null;
            $targetCategoryId  = $this->categoriesMapping[$row['category_id']] ?? null;

            if (! $targetProductId || ! $targetCategoryId) {
                continue;
            }

            $chk = $this->targetPdo->prepare('SELECT 1 FROM ec_product_category_product WHERE product_id = ? AND category_id = ? LIMIT 1');
            $chk->execute([$targetProductId, $targetCategoryId]);
            if ($chk->fetch()) {
                continue;
            }

            $ins = $this->targetPdo->prepare('INSERT INTO ec_product_category_product (product_id, category_id) VALUES (?, ?)');
            $ins->execute([$targetProductId, $targetCategoryId]);
            $inserted++;
        }

        $this->info("Product-Category relations: {$inserted} inserted");
    }

    // -------------------------------------------------------
    // STEP 4: Attribute Sets (Size, Color, etc.)
    // -------------------------------------------------------
    protected function step4ImportAttributeSets()
    {
        $this->info('Step 4: Importing attribute sets...');

        $stmt = $this->sourcePdo->query('SELECT * FROM ec_product_attribute_sets');
        $sets = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $inserted = 0;

        foreach ($sets as $s) {
            $chk = $this->targetPdo->prepare('SELECT id FROM ec_product_attribute_sets WHERE slug = ? LIMIT 1');
            $chk->execute([$s['slug']]);
            $existing = $chk->fetch(\PDO::FETCH_ASSOC);

            if ($existing) {
                $this->attributeSetsMapping[$s['id']] = $existing['id'];
                continue;
            }

            $now = now()->format('Y-m-d H:i:s');
            $ins = $this->targetPdo->prepare('
                INSERT INTO ec_product_attribute_sets
                    (title, slug, display_layout, is_searchable, is_comparable,
                     is_use_in_product_listing, status, `order`, created_at, updated_at,
                     use_image_from_product_variation)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $ins->execute([
                $s['title'], $s['slug'],
                $s['display_layout'] ?? 'text',
                $s['is_searchable'] ?? 1,
                $s['is_comparable'] ?? 1,
                $s['is_use_in_product_listing'] ?? 0,
                $s['status'] ?? 'published',
                $s['order'] ?? 0,
                $now, $now,
                $s['use_image_from_product_variation'] ?? 0,
            ]);
            $this->attributeSetsMapping[$s['id']] = $this->targetPdo->lastInsertId();
            $inserted++;
        }

        $this->info("Attribute Sets: {$inserted} inserted (mapping: " . count($this->attributeSetsMapping) . " total)");
    }

    // -------------------------------------------------------
    // STEP 5: Attributes (S, M, L, XL, XXL, Green, etc.)
    // -------------------------------------------------------
    protected function step5ImportAttributes()
    {
        $this->info('Step 5: Importing attributes...');

        $stmt = $this->sourcePdo->query('SELECT * FROM ec_product_attributes');
        $attrs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $inserted = 0;

        foreach ($attrs as $a) {
            $targetSetId = $this->attributeSetsMapping[$a['attribute_set_id']] ?? null;
            if (! $targetSetId) {
                continue;
            }

            $chk = $this->targetPdo->prepare('SELECT id FROM ec_product_attributes WHERE slug = ? AND attribute_set_id = ? LIMIT 1');
            $chk->execute([$a['slug'], $targetSetId]);
            $existing = $chk->fetch(\PDO::FETCH_ASSOC);

            if ($existing) {
                $this->attributesMapping[$a['id']] = $existing['id'];
                continue;
            }

            $now = now()->format('Y-m-d H:i:s');
            $ins = $this->targetPdo->prepare('
                INSERT INTO ec_product_attributes
                    (attribute_set_id, title, slug, color, image, is_default, `order`, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $ins->execute([
                $targetSetId,
                $a['title'], $a['slug'],
                $a['color'] ?? null, $a['image'] ?? null,
                $a['is_default'] ?? 0, $a['order'] ?? 0,
                $now, $now,
            ]);
            $this->attributesMapping[$a['id']] = $this->targetPdo->lastInsertId();
            $inserted++;
        }

        $this->info("Attributes: {$inserted} inserted (mapping: " . count($this->attributesMapping) . " total)");
    }

    // -------------------------------------------------------
    // STEP 6: Product <-> Attribute Set link
    // -------------------------------------------------------
    protected function step6ImportProductAttributeSets()
    {
        $this->info('Step 6: Importing product-attribute set relations...');

        if (empty($this->productIdMapping)) {
            $this->warn('No product mapping, skipping');
            return;
        }

        $sourceProductIds = array_keys($this->productIdMapping);
        $placeholders     = implode(',', array_fill(0, count($sourceProductIds), '?'));

        $stmt = $this->sourcePdo->prepare("SELECT * FROM ec_product_with_attribute_set WHERE product_id IN ($placeholders)");
        $stmt->execute($sourceProductIds);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $inserted = 0;

        foreach ($rows as $row) {
            $targetProductId = $this->productIdMapping[$row['product_id']] ?? null;
            $targetSetId     = $this->attributeSetsMapping[$row['attribute_set_id']] ?? null;

            if (! $targetProductId || ! $targetSetId) {
                continue;
            }

            $chk = $this->targetPdo->prepare('SELECT 1 FROM ec_product_with_attribute_set WHERE product_id = ? AND attribute_set_id = ? LIMIT 1');
            $chk->execute([$targetProductId, $targetSetId]);
            if ($chk->fetch()) {
                continue;
            }

            $ins = $this->targetPdo->prepare('INSERT INTO ec_product_with_attribute_set (attribute_set_id, product_id, `order`) VALUES (?, ?, ?)');
            $ins->execute([$targetSetId, $targetProductId, $row['order'] ?? 0]);
            $inserted++;
        }

        $this->info("Product-Attribute Set relations: {$inserted} inserted");
    }

    // -------------------------------------------------------
    // STEP 7: Variations + variation products
    // -------------------------------------------------------
    protected function step7ImportVariations()
    {
        $this->info('Step 7: Importing variations...');

        if (empty($this->productIdMapping)) {
            $this->warn('No product mapping, skipping');
            return;
        }

        $sourceProductIds = array_keys($this->productIdMapping);
        $placeholders     = implode(',', array_fill(0, count($sourceProductIds), '?'));

        // Get all variations where configurable_product_id is one of our parent products
        $stmt = $this->sourcePdo->prepare("SELECT * FROM ec_product_variations WHERE configurable_product_id IN ($placeholders)");
        $stmt->execute($sourceProductIds);
        $variations = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if (empty($variations)) {
            $this->info('Variations: 0 found');
            return;
        }

        // Get all variation product records from source
        $varProductIds  = array_column($variations, 'product_id');
        $placeholders2  = implode(',', array_fill(0, count($varProductIds), '?'));
        $stmt2 = $this->sourcePdo->prepare("SELECT * FROM ec_products WHERE id IN ($placeholders2)");
        $stmt2->execute($varProductIds);
        $varProducts = $stmt2->fetchAll(\PDO::FETCH_ASSOC);

        // source variation_product_id => product row
        $varProductMap = [];
        foreach ($varProducts as $vp) {
            $varProductMap[$vp['id']] = $vp;
        }

        $inserted = 0;

        foreach ($variations as $v) {
            $targetConfigurableId = $this->productIdMapping[$v['configurable_product_id']] ?? null;
            if (! $targetConfigurableId) {
                continue;
            }

            $vpData = $varProductMap[$v['product_id']] ?? null;
            if (! $vpData) {
                continue;
            }

            // Insert or get variation product in target
            $chk = $this->targetPdo->prepare('SELECT id FROM ec_products WHERE sku = ? LIMIT 1');
            $chk->execute([$vpData['sku']]);
            $existingVp = $chk->fetch(\PDO::FETCH_ASSOC);

            if ($existingVp) {
                $targetVpId = $existingVp['id'];
            } else {
                $now       = now()->format('Y-m-d H:i:s');
                $rawImages = json_decode($vpData['images'] ?? '[]', true) ?: [];
                $newImages = json_encode(array_map(fn($img) => 'new_product/' . basename($img), $rawImages));
                $newImage  = $vpData['image'] ? 'new_product/' . basename($vpData['image']) : null;

                $ins = $this->targetPdo->prepare("
                    INSERT INTO ec_products (
                        name, description, content, status, images, sku, `order`, quantity,
                        allow_checkout_when_out_of_stock, with_storehouse_management, is_featured,
                        brand_id, is_variation, sale_type, price, sale_price, start_date, end_date,
                        length, wide, height, weight, tax_id, views, created_at, updated_at,
                        stock_status, created_by_id, created_by_type, image,
                        product_type, barcode, cost_per_item, generate_license_code,
                        minimum_order_quantity, maximum_order_quantity
                    ) VALUES (
                        ?, ?, ?, 'published', ?, ?, 0, ?,
                        ?, ?, ?,
                        ?, 1, ?, ?, ?, ?, ?,
                        ?, ?, ?, ?, ?, ?, ?, ?,
                        'in_stock', 1, 'Botble\\ACL\\Models\\User', ?,
                        'physical', ?, ?, ?, ?, ?
                    )
                ");
                $ins->execute([
                    $vpData['name'], $vpData['description'], $vpData['content'] ?? null,
                    $newImages, $vpData['sku'],
                    $vpData['quantity'] ?? 0,
                    $vpData['allow_checkout_when_out_of_stock'] ?? 0,
                    $vpData['with_storehouse_management'] ?? 0,
                    $vpData['is_featured'] ?? 0,
                    $vpData['brand_id'] ?? null,
                    $vpData['sale_type'] ?? 0,
                    $vpData['price'], $vpData['sale_price'] ?? null,
                    $vpData['start_date'] ?? null, $vpData['end_date'] ?? null,
                    $vpData['length'] ?? null, $vpData['wide'] ?? null,
                    $vpData['height'] ?? null, $vpData['weight'] ?? null,
                    $vpData['tax_id'] ?? null,
                    $vpData['views'] ?? 0,
                    $now, $now,
                    $newImage,
                    $vpData['barcode'] ?? null, $vpData['cost_per_item'] ?? null,
                    $vpData['generate_license_code'] ?? 0,
                    $vpData['minimum_order_quantity'] ?? 0,
                    $vpData['maximum_order_quantity'] ?? 0,
                ]);
                $targetVpId = $this->targetPdo->lastInsertId();
            }

            // Insert or get variation record
            $chkVar = $this->targetPdo->prepare('SELECT id FROM ec_product_variations WHERE product_id = ? AND configurable_product_id = ? LIMIT 1');
            $chkVar->execute([$targetVpId, $targetConfigurableId]);
            $existingVar = $chkVar->fetch(\PDO::FETCH_ASSOC);

            if ($existingVar) {
                $this->variationIdMapping[$v['id']] = $existingVar['id'];
            } else {
                $ins = $this->targetPdo->prepare('INSERT INTO ec_product_variations (product_id, configurable_product_id, is_default) VALUES (?, ?, ?)');
                $ins->execute([$targetVpId, $targetConfigurableId, $v['is_default'] ?? 0]);
                $this->variationIdMapping[$v['id']] = $this->targetPdo->lastInsertId();
                $inserted++;
            }
        }

        $this->info("Variations: {$inserted} inserted (mapping: " . count($this->variationIdMapping) . " total)");
    }

    // -------------------------------------------------------
    // STEP 8: Variation Items (variation_id <-> attribute_id)
    // -------------------------------------------------------
    protected function step8ImportVariationItems()
    {
        $this->info('Step 8: Importing variation items...');

        if (empty($this->variationIdMapping)) {
            $this->warn('No variation mapping, skipping');
            return;
        }

        $sourceVariationIds = array_keys($this->variationIdMapping);
        $placeholders       = implode(',', array_fill(0, count($sourceVariationIds), '?'));

        $stmt = $this->sourcePdo->prepare("SELECT * FROM ec_product_variation_items WHERE variation_id IN ($placeholders)");
        $stmt->execute($sourceVariationIds);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $inserted = 0;

        foreach ($items as $item) {
            $targetVariationId  = $this->variationIdMapping[$item['variation_id']] ?? null;
            $targetAttributeId  = $this->attributesMapping[$item['attribute_id']] ?? null;

            if (! $targetVariationId || ! $targetAttributeId) {
                continue;
            }

            $chk = $this->targetPdo->prepare('SELECT 1 FROM ec_product_variation_items WHERE variation_id = ? AND attribute_id = ? LIMIT 1');
            $chk->execute([$targetVariationId, $targetAttributeId]);
            if ($chk->fetch()) {
                continue;
            }

            $ins = $this->targetPdo->prepare('INSERT INTO ec_product_variation_items (attribute_id, variation_id) VALUES (?, ?)');
            $ins->execute([$targetAttributeId, $targetVariationId]);
            $inserted++;
        }

        $this->info("Variation Items: {$inserted} inserted");
    }

    // -------------------------------------------------------
    // STEP 9: Slugs
    // -------------------------------------------------------
    protected function step9ImportSlugs()
    {
        $this->info('Step 9: Importing slugs...');

        // Build source product id => target product id map (all products including variations)
        // We need slugs for all imported products
        $allTargetIds = $this->productIdMapping; // source_id => target_id

        $stmt = $this->sourcePdo->query("SELECT * FROM slugs WHERE reference_type = 'Botble\\\\Ecommerce\\\\Models\\\\Product'");
        $slugs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $inserted = 0;

        foreach ($slugs as $slug) {
            $targetRefId = $allTargetIds[$slug['reference_id']] ?? null;
            if (! $targetRefId) {
                continue;
            }

            $chk = $this->targetPdo->prepare("SELECT 1 FROM slugs WHERE `key` = ? AND prefix = ? LIMIT 1");
            $chk->execute([$slug['key'], $slug['prefix']]);
            if ($chk->fetch()) {
                continue;
            }

            $now = now()->format('Y-m-d H:i:s');
            $ins = $this->targetPdo->prepare("INSERT INTO slugs (`key`, reference_id, reference_type, prefix, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)");
            $ins->execute([
                $slug['key'],
                $targetRefId,
                $slug['reference_type'],
                $slug['prefix'],
                $now, $now,
            ]);
            $inserted++;
        }

        $this->info("Slugs: {$inserted} inserted");
    }
}
