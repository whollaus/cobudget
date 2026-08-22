<?php
namespace OCA\CoBudget\AppInfo;

use OCA\CoBudget\Notification\Notifier;
use OCA\CoBudget\Listener\BeforeUserDeletedListener;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\User\Events\BeforeUserDeletedEvent;

class Application extends App implements IBootstrap {
	public const APP_ID = 'cobudget';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		$context->registerEventListener(BeforeUserDeletedEvent::class, BeforeUserDeletedListener::class);
		if (method_exists($context, 'registerNotifierService')) {
			$context->registerNotifierService(Notifier::class);
		}
		// OCC commands are registered in appinfo/register_command.php for compatibility with Nextcloud 34+.
	}

	public function boot(IBootContext $context): void {
	}
}
