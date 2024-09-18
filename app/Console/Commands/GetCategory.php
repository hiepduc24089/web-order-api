<?php

namespace App\Console\Commands;

use App\Models\Category;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Stichoza\GoogleTranslate\GoogleTranslate;

class GetCategory extends Command
{
    protected $signature = 'api:get-category';
    protected $description = 'Fetching Category and Subcategory data from API';

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
        $parentCategories = Category::where('type', 1)->get();
        foreach ($parentCategories as $parentCategory) {
            $this->fetchAndSaveCategories(env('OTAPI_HOST') . 'service/GetCategorySubcategoryInfoList', $parentCategory->id, 0, $parentCategory->api_id);
        }

        $this->info("Fetching sub-subcategories...");
        $childCategories = Category::where('type', 0)->where('child_id', 0)->get();
        foreach ($childCategories as $childCategory) {
            $this->fetchAndSaveCategories(env('OTAPI_HOST') . 'service/GetCategorySubcategoryInfoList', 0, 0, $childCategory->api_id, $childCategory->id);
        }
        return 0;
    }

    protected function fetchAndSaveCategories($url, $parent_id = 0, $type = 0, $parentCategoryId = null, $child_id = 0)
    {
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
                foreach ($xml->CategoryInfoList->Content->Item as $item) {
                    $api_id = (string)$item->Id;
                    $name = (string)$item->Name;
                    $translatedName = $this->translateNameToVietnamese($name);
                    dd($translatedName);
                    $slug = Str::slug($translatedName);

                    Category::updateOrCreate(
                        ['api_id' => $api_id],
                        [
                            'name' => $translatedName,
                            'slug' => $slug,
                            'type' => $type,
                            'parent_id' => $parent_id,
                            'child_id' => $child_id,
                        ]
                    );

                    $this->info("Category saved: $translatedName (Parent ID: $parent_id, Child ID: $child_id)");
                }
            } else {
                $this->error('No categories found in the API response.');
            }
        } else {
            $this->error('Failed to fetch categories from the API.');
        }
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
