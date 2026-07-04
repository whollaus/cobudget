<?php
namespace OCA\CoBudget\Controller;

use OCP\IRequest;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Controller;

class PageController extends Controller {

	public function __construct($appName, IRequest $request) {
		parent::__construct($appName, $request);
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index(): TemplateResponse {
		\OCP\Util::addTranslations('cobudget');
		\OCP\Util::addScript('cobudget', 'cobudget-main');
		return new TemplateResponse('cobudget', 'index');
	}
}
