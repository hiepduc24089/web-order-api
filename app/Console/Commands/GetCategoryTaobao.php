<?php

namespace App\Console\Commands;

use App\Models\CategoryTaobaoModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Stichoza\GoogleTranslate\GoogleTranslate;

class GetCategoryTaobao extends Command
{
    protected $signature = 'api:get-category-taobao';
    protected $description = 'Fetching Category and Subcategory of Taobao data from API';

    protected $translator;

    public function __construct()
    {
        parent::__construct();
        $this->translator = new GoogleTranslate('vi');
    }

    public function handle()
    {
        $this->info("Fetching parent categories...");
        $this->fetchAndSaveCategories(env('OTAPI_HOST') . 'service/GetRootCategoryInfoList', 0, 1);

        $this->info("Fetching subcategories...");
        $parentCategories = CategoryTaobaoModel::where('type', 1)->get();
        foreach ($parentCategories as $parentCategory) {
            $this->fetchAndSaveCategories(env('OTAPI_HOST') . 'service/GetCategorySubcategoryInfoList', $parentCategory->id, 0, $parentCategory->api_id);
        }

        $this->info("Fetching sub-subcategories...");
        $childCategories = CategoryTaobaoModel::where('type', 0)->where('child_id', 0)->get();
        foreach ($childCategories as $childCategory) {
            $this->fetchAndSaveCategories(env('OTAPI_HOST') . 'service/GetCategorySubcategoryInfoList', 0, 0, $childCategory->api_id, $childCategory->id);
        }
        return 0;
    }

    protected function fetchAndSaveCategories($url, $parent_id = 0, $type = 0, $parentCategoryId = null, $child_id = 0)
    {
        $keywords = [
            'quần áo',
            'phụ kiện',
            'mẹ và bé',
            'linh kiện điện tử',
            'gia dụng',
            'văn phòng phẩm',
            'nội thất',
            'sức khoẻ',
            'mỹ phẩm',
            'thể thao'
        ];

        $params = [
            'instanceKey' => env('OTAPI_KEY'),
            'language' => 'en',
            'signature' => '',
            'timestamp' => '',
        ];

        if ($parentCategoryId) {
            $params['parentCategoryId'] = $parentCategoryId;
        }

        $response = Http::get($url, $params);

        if ($response->successful()) {
            $xml = simplexml_load_string($response->body());

            if ($xml && isset($xml->CategoryInfoList->Content->Item)) {
                $count = 0;
                foreach ($xml->CategoryInfoList->Content->Item as $item) {
                    $api_id = (string)$item->Id;
                    $name = (string)$item->Name;
                    $translatedName = $this->translateNameToVietnamese($name);

                    // Limit to 10 items for subcategories and sub-subcategories
                    if ($count >= 10) {
                        $this->info("Limit reached, skipping the rest of the categories.");
                        break;
                    }

                    // Apply keyword matching only for parent categories (when $parent_id is 0 and $type is parent)
                    if ($parent_id == 0 && $type == 1) {
                        if ($this->isKeywordMatch($translatedName, $keywords)) {
                            $slug = Str::slug($translatedName);

                            // Save only parent categories that match the keywords
                            CategoryTaobaoModel::updateOrCreate(
                                ['api_id' => $api_id],
                                [
                                    'name' => $translatedName,
                                    'slug' => $slug,
                                    'type' => $type,
                                    'parent_id' => $parent_id,
                                    'child_id' => $child_id,
                                ]
                            );

                            $this->info("Parent category saved: $translatedName");
                        } else {
                            $this->info("Parent category skipped: $translatedName (No keyword match)");
                        }
                    } else {
                        // For subcategories and sub-subcategories, save without keyword matching
                        $slug = Str::slug($translatedName);
                        CategoryTaobaoModel::updateOrCreate(
                            ['api_id' => $api_id],
                            [
                                'name' => $translatedName,
                                'slug' => $slug,
                                'type' => $type,
                                'parent_id' => $parent_id,
                                'child_id' => $child_id,
                            ]
                        );
                        $this->info("Subcategory saved: $translatedName");

                        $count++;
                    }
                }
            } else {
                $this->error('No categories found in the API response.');
            }
        } else {
            $this->error('Failed to fetch categories from the API.');
        }
    }

    protected function isKeywordMatch($translatedName, $keywords)
    {
        foreach ($keywords as $keyword) {
            if (stripos($translatedName, $keyword) !== false) {
                return true;
            }
        }
        return false;
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
