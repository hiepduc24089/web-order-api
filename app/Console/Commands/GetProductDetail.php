<?php

namespace App\Console\Commands;

use App\Models\Product;
use App\Models\ProductAttribute;
use App\Models\ProductValue;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Stichoza\GoogleTranslate\GoogleTranslate;

class GetProductDetail extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:get-product-details';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetching Product Details with product ID';

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
        $products = Product::get();

        foreach ($products as $product) {
            $url = env('OTAPI_HOST') . 'service/BatchGetItemFullInfo';
            $params = [
                'instanceKey' => env('OTAPI_KEY'),
                'language' => 'en',
                'signature' => '',
                'timestamp' => '',
                'sessionId' => '',
                'itemParameters' => '',
                'itemId' => 'abb-706004846792',
                'blockList' => 'Description',
            ];

            $response = Http::get($url, $params);

            if ($response->successful()) {
                $xml = simplexml_load_string($response->body());

                if (isset($xml->Result->Item->Description)) {
                    Product::updateOrCreate(
                        ['api_id' => $product->api_id],
                        ['description' => (string)$xml->Result->Item->Description]
                    );
                } else {
                    $this->error("Description missing for product: {$product->api_id}");
                }

                // Color Attribute -> Save to Value table
                if (isset($xml->Result->Item->Attributes->ItemAttribute)) {
                    foreach ($xml->Result->Item->Attributes->ItemAttribute as $attribute) {
                        $value = (string)$attribute->Value ?? null;
                        $translatedValue = $this->translateNameToVietnamese($value);
                        $imageUrl = isset($attribute->MiniImageUrl) ? (string)$attribute->MiniImageUrl : null;
                        $chineseName = isset($attribute->OriginalValue) ? (string)$attribute->OriginalValue : null;
                        $IsConfigurator = (string)$attribute->IsConfigurator;
                        if ($IsConfigurator === "true") {
                            $productValue = ProductValue::updateOrCreate(
                                [
                                    'product_id' => $product->id,
                                    'name' => $translatedValue,
                                    'PID' => $chineseName
                                ],
                                [
                                    'src' => $imageUrl,
                                ]
                            );
                            $this->info("Product value saved for product: {$product->api_id}, attribute: {$value}");
                        }
                    }
                }

                // Color Price and Name Attribute -> Save to Attribute table
                if (isset($xml->Result->Item->ConfiguredItems->OtapiConfiguredItem)) {
                    foreach ($xml->Result->Item->ConfiguredItems->OtapiConfiguredItem as $attributeDetail) {
                        $quantity = isset($attributeDetail->Quantity) ? (int)$attributeDetail->Quantity : null;
                        $price = isset($attributeDetail->Price->OriginalPrice) ? (string)$attributeDetail->Price->OriginalPrice : null;

                        $names = [];
                        foreach ($attributeDetail->Configurators->ValuedConfigurator as $configurator) {
                            $vid = (string)$configurator['Vid'] ?? null;
                            if ($vid) {
                                $names[] = $vid;
                            }
                            $concatenatedName = implode(' & ', $names);
                            // Check if a matching ProductValue exists
                            $productValue = ProductValue::where('product_id', $product->id)
                                ->where('PID', $vid)
                                ->first();

                            if ($productValue) {
                                ProductAttribute::updateOrCreate(
                                    [
                                        'product_value_id' => $productValue->id,
                                        'name' => $concatenatedName,
                                    ],
                                    [
                                        'quantity' => $quantity,
                                        'price' => $price,
                                    ]
                                );
                                $this->info("Product attribute saved for product: {$product->api_id}, VID: {$vid}");
                            } else {
                                $this->error("No ProductValue found for PID: {$vid}, product: {$product->api_id}");
                            }
                        }
                    }
                } else {
                    $this->error("No configured items found for product: {$product->api_id}");
                }

            } else {
                $this->error('Error fetching product: ' . $product->api_id);
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
