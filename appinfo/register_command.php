<?php

declare(strict_types=1);

use OCA\CoBudget\AppInfo\Application as CoBudgetApplication;
use OCA\CoBudget\Command\CheckDataIntegrityCommand;
use OCA\CoBudget\Command\CreateBackupCommand;
use OCA\CoBudget\Command\CreateFullBackupCommand;
use OCA\CoBudget\Command\ResetAllCommand;
use OCA\CoBudget\Command\RestoreBackupCommand;
use OCA\CoBudget\Command\RestoreFullBackupCommand;

/** @var \Symfony\Component\Console\Application $application */

$cobudgetApp = new CoBudgetApplication();
$container = $cobudgetApp->getContainer();

foreach ([
	CreateBackupCommand::class,
	CreateFullBackupCommand::class,
	CheckDataIntegrityCommand::class,
	ResetAllCommand::class,
	RestoreBackupCommand::class,
	RestoreFullBackupCommand::class,
] as $commandClass) {
	$application->add($container->query($commandClass));
}
