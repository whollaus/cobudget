<?php
namespace OCA\CoBudget\Settings;

use OCP\Settings\ISettings;
use OCP\AppFramework\Http\TemplateResponse;

class Admin implements ISettings {

    public function getForm() {
        \OCP\Util::addTranslations('cobudget');
        \OCP\Util::addScript('cobudget', 'cobudget-settings');
        return new TemplateResponse('cobudget', 'settings/admin', [], '');
    }

    public function getSection() {
        return 'cobudget'; // We need to register a section as well, or just use 'additional'
    }

    public function getPriority() {
        return 50;
    }
}
