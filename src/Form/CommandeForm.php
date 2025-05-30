<?php
namespace App\Form;

use App\Entity\Commande;
use App\Entity\Product;
use App\Repository\ProductRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Count;

class CommandeForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('products', EntityType::class, [
                'class' => Product::class,
                'choices' => $options['panier_products'],
                'choice_label' => function (Product $product) {
                    return sprintf('%s - %.2f TND', $product->getLabel(), $product->getPrix());
                },
                'query_builder' => function (ProductRepository $repository) {
                    return $repository->createQueryBuilder('p')
                        ->orderBy('p.label', 'ASC');
                },
                'multiple' => true,
                'expanded' => true,
                'label' => 'Produits disponibles',
                'help' => 'Sélectionnez les produits à inclure dans cette commande',
                'constraints' => [
                    new Count([
                        'min' => 1,
                        'minMessage' => 'Vous devez sélectionner au moins un produit'
                    ])
                ],
                'attr' => [
                    'class' => 'products-selection'
                ]
            ])
            ->add('adresse', TextareaType::class, [
                'label' => 'Adresse de livraison',
                'help' => 'Veuillez fournir une adresse complète avec le code postal et la ville',
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Exemple: 123 Avenue Habib Bourguiba, Tunis 1001, Tunisie'
                ],
                'constraints' => [
                    new NotBlank([
                        'message' => 'L\'adresse de livraison est obligatoire'
                    ])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Commande::class,
            'panier_products' => [],
        ]);
    }
}