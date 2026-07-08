<?php
namespace OCA\CoBudget\Settings;

use OCP\IL10N;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection {
    private IL10N $l;

    public function __construct(IL10N $l) {
        $this->l = $l;
    }

    public function getID() {
        return 'cobudget';
    }

    public function getName() {
        return $this->l->t('CoBudget');
    }

    public function getPriority() {
        return 50;
    }

    public function getIcon() {
        return \OC::$server->getURLGenerator()->imagePath('cobudget', 'admin.svg') . '?v=admin-dark-20260707';
    }
}
