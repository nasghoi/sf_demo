<?php

namespace App\DataFixtures;

use App\Entity\Department;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class DepartmentFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $software = new Department();
        $software->setName("Software");
        $manager->persist($software);

        $department = new Department();
        $department->setName("Multimedia");
        $manager->persist($department);

        $department = new Department();
        $department->setName("Human Resource");
        $manager->persist($department);

        $manager->flush();
    }
}
