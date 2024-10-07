<?php

namespace App\Console\Commands;

use App\Models\ProductAttributeTaobaoModel;
use App\Models\ProductTaobaoModel;
use App\Models\ProductValueTaobaoModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Stichoza\GoogleTranslate\GoogleTranslate;

class GetProductDetailTaobao extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:get-product-details-taobao';

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
        $products = ProductTaobaoModel::get();

        foreach ($products as $product) {
            $url = env('OTAPI_HOST') . 'service/BatchGetItemFullInfo';
            $params = [
                'instanceKey' => env('OTAPI_KEY'),
                'language' => 'en',
                'signature' => '',
                'timestamp' => '',
                'sessionId' => '',
                'itemParameters' => '',
                'itemId' => $product->api_id,
                'blockList' => 'Description',
            ];

            $response = Http::get($url, $params);

            if ($response->successful()) {
                $xml = simplexml_load_string($response->body());
                if (isset($xml->Result->Item->Description)) {
                    ProductTaobaoModel::updateOrCreate(
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
//                        $chineseName = isset($attribute->OriginalValue) ? (string)$attribute->OriginalValue : null;
                        $Pid = isset($attribute['Pid']) ? (string)$attribute['Pid'] : null;
                        $Vid = isset($attribute['Vid']) ? (string)$attribute['Vid'] : null;
                        $itemAttributeData = "Pid=\"$Pid\" Vid=\"$Vid\"";
                        $IsConfigurator = (string)$attribute->IsConfigurator;
                        if ($IsConfigurator === "true" && $imageUrl !== null) {
                            $productValue = ProductValueTaobaoModel::updateOrCreate(
                                [
                                    'product_id' => $product->id,
                                    'name' => $translatedValue,
                                    'PID' => $itemAttributeData
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

                        // Initialize array to hold ValuedConfigurator
                        $configurators = [];

                        // Collect all ValuedConfigurator objects
                        foreach ($attributeDetail->Configurators->ValuedConfigurator as $configurator) {
                            $configurators[] = $configurator; // Add each ValuedConfigurator to the array
                        }

                        // Check if there is a second ValuedConfigurator
                        if (isset($configurators[1])) {
                            $firstConfigurator = $configurators[0];
                            $firstVid = isset($firstConfigurator['Vid']) ? (string)$firstConfigurator['Vid'] : null;

                            // Get the second ValuedConfigurator
                            $secondConfigurator = $configurators[1];

                            // Extract Pid and Vid from the second ValuedConfigurator
                            $secondPid = isset($secondConfigurator['Pid']) ? (string)$secondConfigurator['Pid'] : null;
                            $secondVid = isset($secondConfigurator['Vid']) ? (string)$secondConfigurator['Vid'] : null;

                            // Form the comparison string in the desired format
                            $itemAttributeData = "Pid=\"$secondPid\" Vid=\"$secondVid\"";

                            // Perform the comparison or save operation
                            $productValue = ProductValueTaobaoModel::where('product_id', $product->id)
                                ->where('PID', $itemAttributeData) // Compare with the full string "Pid=... Vid=..."
                                ->first();

                            if ($productValue) {
                                // Determine size based on firstVid
                                $sizeMapping = [
                                    '28314' => 'S',
                                    '28315' => 'M',
                                    '28316' => 'L',
                                    '28317' => 'XL',
                                    '28318' => 'XXL',
                                ];

                                // Check if firstVid matches any of the predefined sizes
                                if (isset($sizeMapping[$firstVid])) {
                                    $size = $sizeMapping[$firstVid];
                                } else {
                                    // If firstVid does not match predefined sizes, look it up in ItemAttribute
                                    $size = $this->findAndTranslateSize($firstVid, $xml);
                                }

                                // Store attributes with the ProductValue ID for the second ValuedConfigurator
                                ProductAttributeTaobaoModel::updateOrCreate(
                                    [
                                        'product_value_id' => $productValue->id,
                                        'name' => $size, // Store the size or translated name
                                    ],
                                    [
                                        'quantity' => $quantity,
                                        'price' => $price,
                                    ]
                                );
                                $this->info("Product attribute saved for product: {$product->api_id}, Second Pid/Vid: {$itemAttributeData}, Size: {$size}, Quantity: {$quantity}, Price: {$price}");
                            } else {
                                // Log an error if the second ValuedConfigurator doesn't match any ProductValue
                                $this->error("No ProductValue found for Pid/Vid: {$itemAttributeData}, product: {$product->api_id}");
                            }
                        }
                    }
                }
                else {
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

    protected function findAndTranslateSize($firstVid, $xml)
    {
        // Iterate through each ItemAttribute to find a matching Vid
        if (isset($xml->Result->Item->Attributes->ItemAttribute)) {
            foreach ($xml->Result->Item->Attributes->ItemAttribute as $attribute) {
                $attributeVid = isset($attribute['Vid']) ? (string)$attribute['Vid'] : null;
                if ($attributeVid === $firstVid) {
                    // Get the OriginalValue if it exists
                    $originalValue = isset($attribute->OriginalValue) ? (string)$attribute->OriginalValue : null;
                    if ($originalValue) {
                        // Translate the OriginalValue
                        $translatedValue = $this->translateNameToVietnamese($originalValue);
                        return $translatedValue;
                    }
                }
            }
        }
        // If no match is found, return the original Vid
        return $firstVid;
    }
}
