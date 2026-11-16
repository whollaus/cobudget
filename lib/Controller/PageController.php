<?php
namespace OCA\CoBudget\Controller;

use OCP\IRequest;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\AppFramework\Services\IInitialState;
use OCP\IURLGenerator;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\Attribute\NoAdminRequired;
use OCP\AppFramework\Http\Attribute\NoCSRFRequired;

class PageController extends Controller {
	private IURLGenerator $urlGenerator;

	public function __construct($appName, IRequest $request, IURLGenerator $urlGenerator,
		private IConfig $config, private IUserSession $userSession, private IInitialState $initialState) {
		parent::__construct($appName, $request);
		$this->urlGenerator = $urlGenerator;
	}

	#[NoAdminRequired]
	#[NoCSRFRequired]
	public function index(): TemplateResponse {
		\OCP\Util::addTranslations('cobudget');
		$userId = $this->userSession->getUser()?->getUID();
		if ($userId !== null) {
			$appearance = [
				'showDesktop' => $this->config->getUserValue($userId, 'cobudget', 'show_nextcloud_header_desktop', 'yes') === 'yes',
				'showMobile' => $this->config->getUserValue($userId, 'cobudget', 'show_nextcloud_header_mobile', 'yes') === 'yes',
				'themeMode' => $this->config->getUserValue($userId, 'cobudget', 'theme_mode', 'auto'),
			];
			$this->initialState->provideInitialState('appearance', $appearance);
			foreach (['Desktop', 'Mobile'] as $viewport) {
				if (!$appearance['show' . $viewport]) {
					\OCP\Util::addHeader('meta', ['name' => 'cobudget-header-' . strtolower($viewport) . '-hidden', 'content' => 'true']);
				}
			}
		}
		\OCP\Util::addHeader('link', [
			'rel' => 'stylesheet',
			'href' => $this->urlGenerator->linkTo('cobudget', 'js/cobudget-main.css')
				. '?v=' . filemtime(__DIR__ . '/../../js/cobudget-main.css'),
		]);
		\OCP\Util::addInitScript('cobudget', 'cobudget-bootstrap');
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
