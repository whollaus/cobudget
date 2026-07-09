<?php
namespace OCA\CoBudget\Settings;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

class AdminSection implements IIconSection {
    private IL10N $l;
    private IURLGenerator $urlGenerator;

    public function __construct(IL10N $l, IURLGenerator $urlGenerator) {
        $this->l = $l;
        $this->urlGenerator = $urlGenerator;
    }

    public function getID(): string {
        return 'cobudget';
    }

    public function getName(): string {
        return $this->l->t('CoBudget');
    }

    public function getPriority(): int {
        return 50;
    }

    public function getIcon(): string {
        return $this->urlGenerator->imagePath('cobudget', 'admin.svg') . '?v=admin-dark-20260707';
    }
}
