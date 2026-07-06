<?php
namespace OCA\CoBudget\Controller;

use OCP\IRequest;
use OCP\IURLGenerator;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Controller;

class PageController extends Controller {
	private IURLGenerator $urlGenerator;

	public function __construct($appName, IRequest $request, IURLGenerator $urlGenerator) {
		parent::__construct($appName, $request);
		$this->urlGenerator = $urlGenerator;
	}

	/**
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function index(): TemplateResponse {
		\OCP\Util::addTranslations('cobudget');
		\OCP\Util::addScript('cobudget', 'cobudget-main');
		$this->addAppIconHeaders();
		return new TemplateResponse('cobudget', 'index');
	}

	private function addAppIconHeaders(): void {
		$iconUrl = $this->urlGenerator->imagePath('cobudget', 'app.svg');
		\OCP\Util::addHeader('link', ['rel' => 'icon', 'type' => 'image/svg+xml', 'href' => $iconUrl]);
		\OCP\Util::addHeader('link', ['rel' => 'shortcut icon', 'type' => 'image/svg+xml', 'href' => $iconUrl]);
		\OCP\Util::addHeader('link', ['rel' => 'apple-touch-icon', 'href' => $iconUrl]);
	}
}
