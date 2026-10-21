<?php
declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OCA\Talk\Chat\ChatManager;
use OCA\Talk\Config;
use OCA\Talk\Events\BotInstallEvent;
use OCA\Talk\Manager;
use OCA\Talk\Model\Attendee;
use OCA\Talk\Model\Bot;
use OCA\Talk\Model\BotConversation;
use OCA\Talk\Model\BotConversationMapper;
use OCA\Talk\Model\BotServerMapper;
use OCA\Talk\Participant;
use OCA\Talk\Room;
use OCA\Talk\Service\ParticipantService as TalkParticipantService;
use OCP\App\IAppManager;
use OCP\AppFramework\Db\DoesNotExistException;
use OCP\EventDispatcher\IEventDispatcher;
use OCP\IDBConnection;
use OCP\IUserManager;
use OCP\Lock\ILockingProvider;
use OCP\Server;

/**
 * Optional adapter for Talk 23–25 (Nextcloud 33–35). All Talk-specific APIs
 * stay here: OCP\Talk\IBroker does not provide bot message delivery.
 * Never inject a Talk class: CoBudget must boot when Talk is absent.
 */
class TalkBackend {
	private const BOT_URL = 'nextcloudapp://cobudget/personal-notifications';
	private const OBJECT_TYPE = 'cobudget-notifications';

	public function __construct(
		private IAppManager $appManager,
		private IUserManager $userManager,
		private IEventDispatcher $dispatcher,
		private IDBConnection $db,
		private ILockingProvider $lockingProvider,
	) {
	}

	protected function service(string $class): object {
		return Server::get($class);
	}

	public function available(string $userId): bool {
		$user = $this->userManager->get($userId);
		if ($user === null || !$user->isEnabled() || !$this->appManager->isEnabledForUser('spreed', $user)
			|| !class_exists(BotInstallEvent::class)) {
			return false;
		}
		$config = $this->service(Config::class);
		return !$config->isDisabledForUser($user) && !$config->isNotAllowedToCreateConversations($user);
	}

	/** Called only during an explicit opt-in, under the setup lock. */
	public function createPersonalConversation(string $userId): string {
		$this->lockingProvider->acquireLock('cobudget:talk-bot-setup', ILockingProvider::LOCK_EXCLUSIVE);
		try {
			$bot = $this->ensureBot();
		} finally {
			$this->lockingProvider->releaseLock('cobudget:talk-bot-setup', ILockingProvider::LOCK_EXCLUSIVE);
		}
		return $this->createRoom($userId, $bot->getId());
	}

	private function ensureBot(): object {
		$mapper = $this->service(BotServerMapper::class);
		try {
			$bot = $mapper->findByUrl(self::BOT_URL);
		} catch (DoesNotExistException $e) {
			// Native app registration; no external webhook or user password required.
			$this->dispatcher->dispatchTyped(new BotInstallEvent(
				'CoBudget', bin2hex(random_bytes(32)), self::BOT_URL,
				'Personal CoBudget payment notifications', Bot::FEATURE_RESPONSE
			));
			$bot = $mapper->findByUrl(self::BOT_URL);
			$bot->setState(Bot::STATE_NO_SETUP);
			$mapper->update($bot);
		}
		$this->assertBotEnabled($bot);
		return $bot;
	}

	private function createRoom(string $userId, int $botId): string {
		$this->db->beginTransaction();
		try {
			$room = $this->service(Manager::class)->createRoom(
				Room::TYPE_GROUP, 'CoBudget', self::OBJECT_TYPE, $userId,
				readOnly: Room::READ_ONLY, listable: 0, sipEnabled: 0
			);
			// A regular participant cannot invite others or make the room public.
			$this->service(TalkParticipantService::class)->addUsers($room, [[
				'actorType' => Attendee::ACTOR_USERS,
				'actorId' => $userId,
				'participantType' => Participant::USER,
			]]);
			$binding = new BotConversation();
			$binding->setBotId($botId);
			$binding->setToken($room->getToken());
			$binding->setState(Bot::STATE_ENABLED);
			$this->service(BotConversationMapper::class)->insert($binding);
			$this->assertPrivate($room, $userId, $botId);
			$this->db->commit();
			return $room->getToken();
		} catch (\Throwable $e) {
			$this->db->rollBack();
			throw $e;
		}
	}

	public function ready(string $token, string $userId): bool {
		if ($token === '') {
			return false;
		}
		try {
			$this->personalRoom($token, $userId);
			return true;
		} catch (\Throwable $e) {
			return false;
		}
	}

	public function send(string $token, string $userId, string $message): void {
		// Revalidate on every send; never recreate a deleted/left chat implicitly.
		$room = $this->personalRoom($token, $userId);
		$this->service(ChatManager::class)->sendMessage(
			$room, null, Attendee::ACTOR_BOTS, Attendee::ACTOR_BOT_PREFIX . sha1(self::BOT_URL),
			$message, new \DateTime('now', new \DateTimeZone('UTC'))
		);
	}

	private function personalRoom(string $token, string $userId): object {
		$bot = $this->service(BotServerMapper::class)->findByUrl(self::BOT_URL);
		$this->assertBotEnabled($bot);
		$room = $this->service(Manager::class)->getRoomByToken($token);
		$this->assertPrivate($room, $userId, $bot->getId());
		return $room;
	}

	private function assertBotEnabled(object $bot): void {
		if (!in_array($bot->getState(), [Bot::STATE_ENABLED, Bot::STATE_NO_SETUP], true)
			|| $bot->getFeatures() !== Bot::FEATURE_RESPONSE) {
			throw new \RuntimeException('CoBudget Talk bot is unavailable');
		}
	}

	protected function attendees(int $roomId): array {
		// Include dormant guests and invitations too, not just active sessions.
		$qb = $this->db->getQueryBuilder();
		$qb->select('actor_type', 'actor_id', 'participant_type')->from('talk_attendees')
			->where($qb->expr()->eq('room_id', $qb->createNamedParameter($roomId, \PDO::PARAM_INT)));
		$result = $qb->executeQuery();
		$rows = $result->fetchAll();
		$result->closeCursor();
		return $rows;
	}

	private function assertPrivate(object $room, string $userId, int $botId): void {
		if ($room->getType() !== Room::TYPE_GROUP || $room->getListable() !== 0
			|| $room->getSIPEnabled() !== 0 || $room->isFederatedConversation()
			|| $room->getReadOnly() !== Room::READ_ONLY
			|| $room->getObjectType() !== self::OBJECT_TYPE || $room->getObjectId() !== $userId) {
			throw new \RuntimeException('CoBudget Talk conversation is not private');
		}
		$attendees = $this->attendees($room->getId());
		if (count($attendees) !== 1 || $attendees[0]['actor_type'] !== Attendee::ACTOR_USERS
			|| $attendees[0]['actor_id'] !== $userId || (int)$attendees[0]['participant_type'] !== Participant::USER) {
			throw new \RuntimeException('CoBudget Talk conversation participants changed');
		}
		$bots = $this->service(BotConversationMapper::class)->findForToken($room->getToken());
		if (count($bots) !== 1 || $bots[0]->getBotId() !== $botId || $bots[0]->getState() !== Bot::STATE_ENABLED) {
			throw new \RuntimeException('CoBudget Talk conversation bots changed');
		}
	}
}
