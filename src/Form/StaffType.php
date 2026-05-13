<?php

namespace App\Form;

use App\Entity\Department;
use App\Entity\Staff;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StaffType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $formOptions = [
            // 'row_attr' => [
            //     'class' => 'mb-3',
            // ],
            // 'label_attr' => [
            //     'class' => 'form-label',
            // ],
            // 'attr' => [
            //     'class' => 'form-control',
            //     'placeholder' => 'Enter your name',
            // ]
        ];

        $builder
            ->add('name', TextType::class, $formOptions)
            ->add('email', TextType::class, $formOptions)
            ->add('status', ChoiceType::class, $formOptions)
            ->add('department', EntityType::class, [
                'class' => Department::class,
                'choice_label' => 'name',
                ...$formOptions

            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Submit',
                'row_attr' => [
                    'class' => 'd-flex justify-content-end',
                ],
                'attr' => [
                    'class' => 'btn btn-success',
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Staff::class,
        ]);
    }
}
