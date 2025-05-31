<?php

namespace App\DataFixtures;

use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class AppFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $products = [
            ['Rasoir Fusion 2up', '2up est une technique de rasage qui consiste à passer une première fois dans le sens contraire 
            de la pousse des poils(up), puis une deuxième fois dans le sens de la pousse (down).',61, 'rasoir.jpg'],
            ['Gaufrette', '16.5GR Fruits des bois.', 0.3, 'gaucho.jpg'],
            ['FLORIDA chewing-gum', 'FLORIDA – Chewing-gum Chloropylle 17g', .7 , 'florida.webp'],
            ['D-Clic', '44GR Chocolat.', 1.1, 'dclic.jpg'],
            ['Tabac', 'Fumer tue.', 1.1, 'malboro.webp'],
            ['Briquet', 'Briquet Standard BIC Rose', 5.9, 'brikeya.jpg'],
            ['l\'immeuble Yakoubian', 'Revivez chaque scène culte de ce chef-d\'œuvre du cinéma.', 5, 'yacoubian.jpg'],
            ['Roti', 'Une œuvre cinématographique à savourer encore et encore.',5, 'cd2.jpg'],
            ['Coolie', 'Le film à ajouter absolument à votre collection.', 5, 'cd1.jpg'],
            ['Flashlight', 'Puissante et compacte, idéale pour toutes vos aventures.',63, 'flashlight.webp'],
            ['Rouge à lèvre', 'L\'huile de coco dans son contenu; Tout en aidant à hydrater et nourrir les lèvres, sa texture douce offre un confort de libération facile. ', 20, 'rouge-a-levre.jpg'],
            ['Parfum', 'LANCÔME-LA VIE EST BELLE-Eau de parfum', 20 , 'la-vie-est-belle.jpg'],
            ['Fard à joues', 'Description Le fard à joues de LELLA illumine en un clin d’œil le maquillage du teint.',20.8, 'fardajoue.jpg'],
            ['AZZA', 'Azza, l’emblématique employée de chez Janet, débarque en figurine pleine de charme et d’attitude.',20, 'azza.jpg'],
            ['BEJI MATRIX', 'Le légendaire Béji Matrix , cette figurine rend hommage à son style unique', 20, 'beji.jpg'],
            ['DALANDA', 'Toujours coiffée, cette figurine capte toute sa personnalité explosive.', 20, 'dadou.jpg'],
            ['FADHILA', 'Fadhila, cette figurine la montre dans toute sa majesté .', 20, 'fadhila.jpg'],
            ['SBOUII', 'Impossible d’imaginer Choufli Hal sans Sbouï ! cette figurine est l’élément central de toute collection.', 20, 'sboui.jpg'],
            ['JANET', 'Madame Janet, débarque en figurine ultra-classy. ', 20, 'janet.jpg'],
            ['TAYEB', 'Taïeb, Cette figurine est parfaite pour surveiller ta collection.', 20, 'tayeb.jpg'],
            ['DR. SLIMAN LABYEDH', 'DR sliman débarque en figurine pleine de charme et d’attitude.', 20, 'soulimen.jpg'],
            ['FOUCHIKA', 'Fouchika, c’est plus qu’un Concierge : c’est une légende. Cette figurine est parfaite pour surveiller ta collection.', 20, 'fouchika.jpg'],






        ];

        foreach ($products as [$label, $description, $prix, $imageUrl]) {
            $product = new Product();
            $product->setLabel($label);
            $product->setDescription($description);
            $product->setPrix($prix);
            $product->setImageUrl($imageUrl);
            $product->setQuantity(100);
            $manager->persist($product);
        }

        $manager->flush();
    }
}
