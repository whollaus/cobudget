<?php

declare(strict_types=1);

use OCA\CoBudget\Service\ReceiptFile;
use OCP\Files\IRootFolder;
use OCP\IUserManager;
use OCP\IUserSession;
use OCP\Server;

$options = getopt('', ['nextcloud:', 'user:', 'other-user:']);
if (PHP_SAPI !== 'cli' || !isset($options['nextcloud'], $options['user'], $options['other-user'])) {
	throw new RuntimeException('Pass --nextcloud=/path/to/local/server --user=admin --other-user=testuser');
}
$serverRoot = realpath($options['nextcloud']);
if (!$serverRoot || !is_file($serverRoot . '/lib/base.php')) {
	throw new RuntimeException('Local Nextcloud installation required.');
}
define('OC_CONSOLE', true);
$_SERVER['SCRIPT_FILENAME'] = __FILE__;
chdir($serverRoot);
require $serverRoot . '/lib/base.php';
OC_App::loadApps();
set_exception_handler(static function (Throwable $error): void {
	fwrite(STDERR, (string)$error . "\n");
	exit(1);
});

$accounts = Server::get(IUserManager::class);
$owner = $accounts->get($options['user']);
$other = $accounts->get($options['other-user']);
if ($owner === null || $other === null || $owner->getUID() === $other->getUID()) {
	throw new RuntimeException('Two distinct test accounts are required.');
}
$session = Server::get(IUserSession::class);
$original = $session->getUser();
$session->setUser($owner);
$root = Server::get(IRootFolder::class);
$folder = $root->getUserFolder($owner->getUID())->newFolder('cobudget-receipt-test-' . bin2hex(random_bytes(12)));
$check = static function (bool $condition, string $message): void {
	if (!$condition) {
		throw new RuntimeException($message);
	}
};
try {
	$file = $folder->newFile('receipt.txt', 'Example receipt');
	$row = ['owner_user_id' => $owner->getUID(), 'file_id' => $file->getId(), 'file_path' => $folder->getName() . '/receipt.txt'];
	if (!Server::get(\OCP\IConfig::class)->getSystemValueBool('filelocking.enabled', true)) {
		$check(ReceiptFile::open($root, $row) === null, 'CoBudget needs Files locking to bind a stream to a receipt.');
		$check(!ReceiptFile::delete($root, $row) && $file->getContent() === 'Example receipt', 'Without Files locking CoBudget preserves the file.');
		echo "PASS receipt preservation without Files locking\n";
		return;
	}
	$receipt = ReceiptFile::open($root, $row);
	$check($receipt !== null, 'An owner-scoped receipt should open.');
	try {
		$check(stream_get_contents($receipt['stream']) === 'Example receipt', 'The open stream should contain the receipt.');
	} finally {
		fclose($receipt['stream']);
	}
	foreach ([['file_id' => null], ['file_id' => 0], ['owner_user_id' => $other->getUID()]] as $change) {
		$check(ReceiptFile::open($root, array_replace($row, $change)) === null, 'Unbound metadata must not open a file.');
		$check(!ReceiptFile::delete($root, array_replace($row, $change)), 'Unbound metadata must not delete a file.');
	}

	$cachedNode = $folder->get('receipt.txt');
	$check($cachedNode->getId() === $row['file_id'], 'Fixture should retain the original Files identity.');
	$moved = $file->move($folder->getPath() . '/moved.txt');
	$replacement = $folder->newFile('receipt.txt', 'New file');
	$check($replacement->getId() !== $row['file_id'], 'Fixture must have a fresh Files ID.');
	$check(ReceiptFile::open($root, $row) === null && !ReceiptFile::delete($root, $row), 'Moved receipt row should stay unbound.');
	$check(!ReceiptFile::deleteFile($cachedNode), 'A stale file node must not remove its replacement during rollback.');
	$check($moved->getContent() === 'Example receipt' && $replacement->getContent() === 'New file', 'Both files should be preserved.');
	$row['file_path'] = $folder->getName() . '/moved.txt';
	$check(ReceiptFile::delete($root, $row), 'The verified receipt should be deleted.');
	$check(!$folder->nodeExists('moved.txt') && $folder->get('receipt.txt')->getContent() === 'New file', 'Only the verified receipt should be deleted.');
	echo "PASS receipt file identity, account scope, legacy metadata, move and physical cleanup\n";
} finally {
	try {
		$folder->delete();
	} finally {
		$session->setUser($original);
	}
}
