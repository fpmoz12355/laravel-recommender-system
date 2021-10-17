<?php 

namespace App;

use Exception;

class ProductSimilarity
{
    protected $products       = [];
    protected $featureWeight  = 1;
    protected $priceWeight    = 1;
    protected $categoryWeight = 1;
    protected $priceHighRange = 100;            //koristi se za normalizaciju "da ne može bit neki outlier"

    public function __construct(array $products) 
    {
        $this->products       = $products;
        
    }

    public function calculateSimilarityMatrix(): array
    {
        $matrix = [];
        

        foreach ($this->products as $product) {

            $similarityScores = [];                   // tog produkta sa ostalim produktima

            foreach ($this->products as $_product) {
                if ($product->id === $_product->id) { //ne racuna sam sa sobom 
                    continue;
                }
                $similarityScores['product_id_' . $_product->id] = $this->calculateSimilarityScore($product, $_product);
            } //concatinet spoji product_id.id stvarnog produkta = spremi score
           
            $matrix['product_id_' . $product->id] = $similarityScores;  //array araya
        }
        
        return $matrix;
        
    }

    public function getProductsSortedBySimilarity(int $productId, array $matrix)
    {
        $similarities   = $matrix['product_id_' . $productId] ?? null;//ako je null da baci iznimku
        $sortedProducts = [];
        

        if (is_null($similarities)) {
            throw new Exception('Can\'t find product with that ID.');
        }
        arsort($similarities); //sort
      
        foreach ($similarities as $productIdKey => $similarity) {
            $id      = intval(str_replace('product_id_', '', $productIdKey)); //kupi samo broj
            
            $products = array_filter($this->products, function ($product) use ($id) { return $product->id === $id; });
            if (! count($products)) {
                continue; //izbacije sebe iz products
            
            }
            $product = $products[array_keys($products)[0]];
            $product->similarity = $similarity;
            $sortedProducts[] = $product; //pomoćni niz koji se sortira da se ne dira matrica
            
            
        }
        
        return $sortedProducts;
        
    }

    protected function calculateSimilarityScore($productA, $productB)                                       //uzima dva objekta tj proizvoda 
    {
        $productAFeatures = implode('', get_object_vars($productA->features));                               //uzima samo vrijednosti 1/0  od featrues
        $productBFeatures = implode('', get_object_vars($productB->features));
        
        

        return array_sum([                                                                                      //funkcija vraca niz 
            (Similarity::hamming($productAFeatures, $productBFeatures) * $this->featureWeight),                     //prodsljeđuje ""01010", računa hammingovu udaljenost i mnozi s weight
            (Similarity::euclidean(                                                                                                  // vraća vrijednost funkcije za taj product daje cijenu od a , nula je minimum, a zadani high
                Similarity::minMaxNorm([$productA->price], 0, $this->priceHighRange),                
                Similarity::minMaxNorm([$productB->price], 0, $this->priceHighRange)
            ) * $this->priceWeight),
            (Similarity::jaccard($productA->categories, $productB->categories) * $this->categoryWeight)
        ]) 
        / ($this->featureWeight + $this->priceWeight + $this->categoryWeight);                                                              //dijeli sa ukupnom težinom
    }
    
}
