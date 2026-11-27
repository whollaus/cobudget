<?php

declare(strict_types=1);

namespace OCA\CoBudget\Service;

use OC\Files\Storage\Wrapper\Wrapper;
use OC\User\NoUserException;
use OCP\Files\File;
use OCP\Files\IRootFolder;
use OCP\Files\NotFoundException;
use OCP\Files\NotPermittedException;
use OCP\Files\Storage\IStorage;
use OCP\IConfig;
use OCP\Server;

/** Read and remove only the registered file at the receipt owner's recorded path. */
final class ReceiptFile extends Wrapper {
	private function __construct(IStorage $storage, private ?string $receiptPath, private readonly int $fileId) {
		parent::__construct(['storage' => $storage]);
	}

	private static function resolve(IRootFolder $root, array $attachment): ?File {
		$id = filter_var($attachment['file_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
		$owner = (string)($attachment['owner_user_id'] ?? '');
		$path = (string)($attachment['file_path'] ?? '');
		if ($id === false || $owner === '' || $path === '' || str_contains($path, '\\')
			|| preg_match('~(^|/)\.{0,2}(/|$)|[[:cntrl:]]~', $path)) {
			return null;
		}

		try {
			$folder = $root->getUserFolder($owner);
			$file = $folder->get($path);
			return $file instanceof File && $folder->isSubNode($file) && $file->getId() === $id ? $file : null;
		} catch (NotFoundException|NotPermittedException|NoUserException) {
			return null;
		}
	}

	/** @return array{file: File, stream: resource}|null */
	public static function open(IRootFolder $root, array $attachment): ?array {
		if (!Server::get(IConfig::class)->getSystemValueBool('filelocking.enabled', true)) {
			return null;
		}
		$file = self::resolve($root, $attachment);
		if ($file === null || !$file->isReadable()) {
			return null;
		}

		// Files keeps a shared lock until the open stream closes. Re-check the path
		// after opening it and pass the same handle through to the response or copy.
		try {
			$stream = $file->fopen('rb');
		} catch (NotFoundException|NotPermittedException) {
			return null;
		}
		if (!is_resource($stream)) {
			return null;
		}
		try {
			$current = self::resolve($root, $attachment);
			if ($current !== null && $current->isReadable() && $current->getMountPoint() === $file->getMountPoint()) {
				return ['file' => $current, 'stream' => $stream];
			}
		} catch (\Throwable $e) {
			fclose($stream);
			throw $e;
		}
		fclose($stream);
		return null;
	}

	public static function delete(IRootFolder $root, array $attachment): bool {
		$file = self::resolve($root, $attachment);
		return $file !== null && self::deleteFile($file);
	}

	/** Preserve Files permissions, hooks, trash and cache updates during deletion. */
	public static function deleteFile(File $file): bool {
		if (!Server::get(IConfig::class)->getSystemValueBool('filelocking.enabled', true)) {
			return false;
		}
		$id = $file->getId();
		$mount = $file->getMountPoint();
		$storage = $mount->getStorage();
		$path = $file->getInternalPath();
		if ($id <= 0 || !$file->isDeletable() || $storage === null || $path === '') {
			return false;
		}
		$guard = new self($storage, $path, $id);
		$mount->wrapStorage(static fn () => $guard);
		try {
			$file->delete();
			return true;
		} catch (NotFoundException|NotPermittedException) {
			return false;
		} finally {
			$guard->receiptPath = null;
			$mount->wrapStorage(static fn () => $storage);
		}
	}

	public function unlink(string $path): bool {
		if ($path === $this->receiptPath && (int)$this->getWrapperStorage()->getCache()->getId($path) !== $this->fileId) {
			throw new NotFoundException('Receipt file identity changed before deletion.');
		}
		return parent::unlink($path);
	}

	public function rmdir(string $path): bool {
		if ($path === $this->receiptPath) {
			throw new NotPermittedException('A receipt must be a file.');
		}
		return parent::rmdir($path);
	}
}
