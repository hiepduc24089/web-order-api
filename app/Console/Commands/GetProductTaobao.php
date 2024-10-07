<?php

namespace App\Console\Commands;

use App\Models\CategoryTaobaoModel;
use App\Models\ProductImageTaobaoModel;
use App\Models\ProductTaobaoModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Stichoza\GoogleTranslate\GoogleTranslate;

class GetProductTaobao extends Command
{
    protected $signature = 'api:get-product-taobao';

    protected $description = 'Fetching Product Taobao with category ID';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    protected $translator;
    public function __construct()
    {
        parent::__construct();
        $this->translator = new GoogleTranslate('vi');
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $categoryIds = CategoryTaobaoModel::where('type', 0)->get();

        foreach ($categoryIds as $category) {
            $categoryApiId = $category->api_id;
            $url = env('OTAPI_HOST') . 'service-json/BatchSearchItemsFrame';
            $params = [
                'instanceKey' => env('OTAPI_KEY'),
                'language' => 'en',
                'signature' => '',
                'timestamp' => '',
                'sessionId' => '',
                'xmlParameters' => '<SearchItemsParameters><Provider>Taobao</Provider><CategoryId>' . $categoryApiId . '</CategoryId></SearchItemsParameters>',
                'framePosition' => 0,
                'frameSize' => 5,
                'blockList' => 'AvailableSearchMethods',
            ];
            $response = Http::get($url, $params);

            if ($response->successful()) {
                $data = $response->json();
                if (isset($data['Result']['Items']['Items']) && is_array($data['Result']['Items']['Items'])) {
                    foreach ($data['Result']['Items']['Items'] as $item) {
                        if (is_array($item)) {
                            foreach ($item as $contentItem) {
                                if (is_array($contentItem) || is_object($contentItem)) {
                                    $apiID = $contentItem['Id'] ?? null;
                                    $name = $contentItem['Title'] ?? null;
                                    $translatedName = $this->translateNameToVietnamese($name);
                                    $slug = Str::slug($translatedName);
                                    $quantity = $contentItem['MasterQuantity'];
                                    $price = $contentItem['Price']['OriginalPrice'];

                                    $product = ProductTaobaoModel::updateOrCreate(
                                        ['api_id' => $apiID],
                                        [
                                            'name' => $translatedName,
                                            'slug' => $slug,
                                            'category_id' => $category->id,
                                            'description' => null,
                                            'price' => $price,
                                            'quantity' => $quantity
                                        ]
                                    );

                                    $pictures = $contentItem['Pictures'] ?? null;

                                    if (!empty($pictures) && is_array($pictures)) {
                                        foreach ($pictures as $picture) {
                                            $mainUrl = $picture['Url'] ?? null;

                                            if ($mainUrl) {
                                                ProductImageTaobaoModel::updateOrCreate(
                                                    ['product_id' => $product->id, 'src' => $mainUrl],
                                                    ['src' => $mainUrl]
                                                );
                                            }
                                        }
                                    } else {
                                        $this->error("No pictures found for item: $apiID");
                                    }
                                } else {
                                    $this->error('Unexpected contentItem format.');
                                }
                            }
                        } else {
                            $this->error('Unexpected item format.');
                        }
                    }
                } else {
                    $this->error('No items found in result or invalid format.');
                }
            } else {
                $this->error('Error fetching product');
            }
        }

        return 0;
    }
    protected function translateNameToVietnamese($name)
    {
        try {
            return $this->translator->translate($name);
        } catch (\Exception $e) {
            $this->error('Translation failed: ' . $e->getMessage());
            return $name;
        }
    }
}
