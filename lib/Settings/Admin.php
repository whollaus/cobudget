<?php
namespace OCA\CoBudget\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\Settings\ISettings;

class Admin implements ISettings {

    public function getForm(): TemplateResponse {
        \OCP\Util::addTranslations('cobudget');
        \OCP\Util::addScript('cobudget', 'cobudget-settings');
        return new TemplateResponse('cobudget', 'settings/admin');
    }

    public function getSection(): string {
        return 'cobudget';
    }

    public function getPriority(): int {
        return 50;
    }
}
