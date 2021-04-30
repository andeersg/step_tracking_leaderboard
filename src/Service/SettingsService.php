<?php

namespace App\Service;

use App\Entity\Settings;
use Doctrine\ORM\EntityManagerInterface;

class SettingsService
{
    public $em = NULL;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public function get($name, $default = ''): string
    {
        $setting = $this->em->getRepository(Settings::class)->findOneBy(['name' => $name]);
        if ($setting) {
            return $setting->getValue();
        }
        else {
            return $default;
        }
    }

    public function set($name, $value)
    {
        $setting = $this->em->getRepository(Settings::class)->findOneBy(['name' => $name]);
        if (!$setting) {
            $setting = new Settings();
            $setting->setName($name);
            $$this->em->persist($setting);
        }
        $setting->setValue($value);
        $this->em->flush();
    }
}