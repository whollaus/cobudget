<?php

declare(strict_types=1);

namespace OCP\L10N {
	if (!interface_exists(IFactory::class, false)) {
		interface IFactory {
			/** @return object */
			public function get(string $appId, string $languageCode);
		}
	}
}

namespace OCP {
	if (!interface_exists(IURLGenerator::class, false)) {
		interface IURLGenerator {
			/** @param array<string, mixed> $arguments */
			public function linkToRouteAbsolute(string $routeName, array $arguments = []): string;
		}
	}
}

namespace OCP\Notification {
	if (!interface_exists(INotification::class, false)) {
		interface INotification {
			public function getApp(): string;
			public function getSubject(): string;
			/** @return array<string, mixed> */
			public function getSubjectParameters(): array;
			public function setParsedSubject(string $subject): INotification;
			public function setParsedMessage(string $message): INotification;
			public function setLink(string $link): INotification;
		}
	}

	if (!interface_exists(INotifier::class, false)) {
		interface INotifier {
			public function getID(): string;
			public function getName(): string;
			public function prepare(INotification $notification, string $languageCode): INotification;
		}
	}

	if (!class_exists(UnknownNotificationException::class, false)) {
		class UnknownNotificationException extends \InvalidArgumentException {
		}
	}
}

namespace CoBudget\Tests {
	require_once dirname(__DIR__, 2) . '/lib/Notification/Notifier.php';

	use CoBudget\Tests\Support\TestRunner;
	use OCA\CoBudget\Notification\Notifier;
	use OCP\IURLGenerator;
	use OCP\L10N\IFactory;
	use OCP\Notification\INotification;
	use OCP\Notification\UnknownNotificationException;

	final class NotifierTestL10nFactory implements IFactory {
		public function get(string $appId, string $languageCode): object {
			return new class {
				/** @param list<string> $parameters */
				public function t(string $text, array $parameters = []): string {
					return $parameters === [] ? $text : vsprintf($text, $parameters);
				}
			};
		}
	}

	final class NotifierTestUrlGenerator implements IURLGenerator {
		public function linkToRouteAbsolute(string $routeName, array $arguments = []): string {
			return 'https://cloud.example.test/index.php/apps/cobudget/';
		}
	}

	final class NotifierTestNotification implements INotification {
		public ?string $parsedSubject = null;
		public ?string $parsedMessage = null;
		public ?string $link = null;

		/** @param array<string, mixed> $parameters */
		public function __construct(
			private string $app,
			private string $subject,
			private array $parameters = []
		) {
		}

		public function getApp(): string {
			return $this->app;
		}

		public function getSubject(): string {
			return $this->subject;
		}

		public function getSubjectParameters(): array {
			return $this->parameters;
		}

		public function setParsedSubject(string $subject): INotification {
			$this->parsedSubject = $subject;
			return $this;
		}

		public function setParsedMessage(string $message): INotification {
			if ($message === '') {
				throw new \InvalidArgumentException('Parsed messages must not be empty');
			}
			$this->parsedMessage = $message;
			return $this;
		}

		public function setLink(string $link): INotification {
			if (!str_starts_with($link, 'https://')) {
				throw new \InvalidArgumentException('Notification links must be absolute');
			}
			$this->link = $link;
			return $this;
		}
	}

	$createNotifier = static fn(): Notifier => new Notifier(
		new NotifierTestL10nFactory(),
		new NotifierTestUrlGenerator()
	);

	return [
		'Notifier accepts incomplete legacy shared-area notifications' => function(TestRunner $t) use ($createNotifier): void {
			$notification = new NotifierTestNotification('cobudget', 'project_entry_created', [
				'projectId' => ['invalid legacy value'],
				'projectName' => 'Household',
				'actorName' => 'Alex',
				'type' => 'expense',
				'amount' => '12.50',
			]);

			$createNotifier()->prepare($notification, 'en');

			$t->assertSame('Alex created an expense of 12.50 EUR in area Household.', $notification->parsedSubject, 'Legacy notification should receive a complete parsed subject');
			$t->assertNull($notification->parsedMessage, 'Empty legacy details should not be submitted as an invalid parsed message');
			$t->assertSame('https://cloud.example.test/index.php/apps/cobudget/', $notification->link, 'Invalid legacy area ids should fall back to the app link');
		},

		'Notifier renders obsolete CoBudget subjects safely' => function(TestRunner $t) use ($createNotifier): void {
			$notification = new NotifierTestNotification('cobudget', 'obsolete_subject');

			$createNotifier()->prepare($notification, 'en');

			$t->assertSame('CoBudget notification', $notification->parsedSubject, 'Obsolete notification should use a safe fallback subject');
			$t->assertSame('This notification is no longer available.', $notification->parsedMessage, 'Obsolete notification should explain the fallback');
		},

		'Notifier ignores notifications owned by other apps' => function(TestRunner $t) use ($createNotifier): void {
			$thrown = false;
			try {
				$createNotifier()->prepare(new NotifierTestNotification('files', 'shared_file'), 'en');
			} catch (UnknownNotificationException) {
				$thrown = true;
			}

			$t->assertTrue($thrown, 'Foreign notifications should use the Nextcloud unknown-notification exception');
		},
	];
}
