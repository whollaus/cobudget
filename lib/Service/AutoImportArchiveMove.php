<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OC\Files\Storage\Wrapper\Wrapper;
use OCP\Files\AlreadyExistsException;
use OCP\Files\File;
use OCP\Files\Folder;
use OCP\Files\Mount\IMountManager;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\Storage\IStorage;
use OCP\Server;

/** Apply the archive preconditions inside the Files rename's exclusive source and target locks. */
final class AutoImportArchiveMove extends Wrapper {
	private function __construct(
		IStorage $storage,
		private ?string $targetPath,
		private readonly ?string $expectedHash,
		private ?\Closure $beforeMove,
	) {
		parent::__construct(['storage' => $storage]);
	}

	public static function move(File $file, Folder $folder, string $name, ?string $expectedHash, ?\Closure $beforeMove = null): File {
		$path = $folder->getPath() . '/' . $name;
		$mount = $folder->getMountPoint();
		$storage = $mount->getStorage();
		// A mount root is moved by the mount manager instead of the storage's file operation.
		if ($storage === null || $file->getInternalPath() === '') throw new NotPermittedException('Cannot archive a mount root');
		// A file mounted at the chosen destination has a different storage and already owns the name.
		if (Server::get(IMountManager::class)->find($path) !== $mount) throw new AlreadyExistsException();
		$guard = new self($storage, $mount->getInternalPath($path), $expectedHash, $beforeMove);
		$mount->wrapStorage(static fn () => $guard);
		try {
			return $file->move($path);
		} finally {
			// FileInfo instances may retain the wrapper after the mount is restored.
			$guard->targetPath = null;
			$guard->beforeMove = null;
			$mount->wrapStorage(static fn () => $storage);
		}
	}

	private function checkMove(IStorage $sourceStorage, string $source, string $target): void {
		if ($target !== $this->targetPath) return;
		if ($this->getWrapperStorage()->file_exists($target)) throw new AlreadyExistsException();
		if ($this->beforeMove !== null || $this->expectedHash !== null) {
			$hash = AutoImportFile::fingerprintAndClose($sourceStorage->fopen($source, 'r'), $complete);
			if ($this->expectedHash !== null && $hash !== $this->expectedHash) throw new NotFoundException('The checked version was replaced');
			if ($this->beforeMove !== null) ($this->beforeMove)($complete ? $hash : null, $sourceStorage, $source);
		}
	}

	public function rename(string $source, string $target): bool {
		$this->checkMove($this->getWrapperStorage(), $source, $target);
		return parent::rename($source, $target);
	}

	public function moveFromStorage(IStorage $sourceStorage, string $sourceInternalPath, string $targetInternalPath): bool {
		if ($sourceStorage === $this) return $this->rename($sourceInternalPath, $targetInternalPath);
		$this->checkMove($sourceStorage, $sourceInternalPath, $targetInternalPath);
		return parent::moveFromStorage($sourceStorage, $sourceInternalPath, $targetInternalPath);
	}
}
