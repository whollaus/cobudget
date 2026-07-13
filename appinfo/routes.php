<?php
return [
	'routes' => [
		// Page route
		['name' => 'page#index', 'url' => '/', 'verb' => 'GET'],
		
		// API routes for Entries
		['name' => 'entry#index', 'url' => '/api/entries', 'verb' => 'GET'],
		['name' => 'entry#exportCsv', 'url' => '/api/entries/export', 'verb' => 'GET'],
		['name' => 'entry#dashboard', 'url' => '/api/dashboard', 'verb' => 'GET'],
		['name' => 'entry#show', 'url' => '/api/entries/{id}', 'verb' => 'GET'],
		['name' => 'entry#create', 'url' => '/api/entries', 'verb' => 'POST'],
		['name' => 'entry#update', 'url' => '/api/entries/{id}', 'verb' => 'PUT'],
		['name' => 'entry#destroy', 'url' => '/api/entries/{id}', 'verb' => 'DELETE'],
		['name' => 'entry#stopRecurrence', 'url' => '/api/entries/{id}/stop-recurrence', 'verb' => 'POST'],
		['name' => 'entry#history', 'url' => '/api/entries/{id}/history', 'verb' => 'GET'],
		['name' => 'entry#attachments', 'url' => '/api/entries/{id}/attachments', 'verb' => 'GET'],
		['name' => 'entry#uploadAttachment', 'url' => '/api/entries/{id}/attachments', 'verb' => 'POST'],
		['name' => 'entry#downloadAttachment', 'url' => '/api/entries/{id}/attachments/{attachmentId}/download', 'verb' => 'GET'],
		['name' => 'entry#destroyAttachment', 'url' => '/api/entries/{id}/attachments/{attachmentId}', 'verb' => 'DELETE'],

		// API routes for Analytics
		['name' => 'analytics#summary', 'url' => '/api/analytics/summary', 'verb' => 'GET'],

		// API routes for Budgets
		['name' => 'budget#index', 'url' => '/api/budgets', 'verb' => 'GET'],
		['name' => 'budget#create', 'url' => '/api/budgets', 'verb' => 'POST'],
		['name' => 'budget#update', 'url' => '/api/budgets/{id}', 'verb' => 'PUT'],
		['name' => 'budget#destroy', 'url' => '/api/budgets/{id}', 'verb' => 'DELETE'],

		// API routes for Backups
		['name' => 'backup#index', 'url' => '/api/backups', 'verb' => 'GET'],
		['name' => 'backup#create', 'url' => '/api/backups', 'verb' => 'POST'],
		['name' => 'backup#inspect', 'url' => '/api/backups/{fileName}/inspect', 'verb' => 'GET'],
		['name' => 'backup#restore', 'url' => '/api/backups/{fileName}/restore', 'verb' => 'POST'],
		['name' => 'backup#destroy', 'url' => '/api/backups/{fileName}', 'verb' => 'DELETE'],
		['name' => 'backup#download', 'url' => '/api/backups/{fileName}/download', 'verb' => 'GET'],
		
		// Category & PaymentPartner Admin API
		['name' => 'category#adminIndex', 'url' => '/api/admin/categories', 'verb' => 'GET'],
		['name' => 'category#adminCreate', 'url' => '/api/admin/categories', 'verb' => 'POST'],
		['name' => 'category#adminUpdate', 'url' => '/api/admin/categories/{id}', 'verb' => 'PUT'],
		['name' => 'category#adminDestroy', 'url' => '/api/admin/categories/{id}', 'verb' => 'DELETE'],
		['name' => 'category#adminUpdateIcon', 'url' => '/api/admin/categories/{id}/icon', 'verb' => 'PUT'],
		['name' => 'category#adminHide', 'url' => '/api/admin/categories/{id}/hide', 'verb' => 'POST'],
		['name' => 'category#adminUnhide', 'url' => '/api/admin/categories/{id}/unhide', 'verb' => 'POST'],
		['name' => 'payment_partner#adminIndex', 'url' => '/api/admin/payment-partners', 'verb' => 'GET'],
		['name' => 'payment_partner#adminCreate', 'url' => '/api/admin/payment-partners', 'verb' => 'POST'],
		['name' => 'payment_partner#adminUpdate', 'url' => '/api/admin/payment-partners/{id}', 'verb' => 'PUT'],
		['name' => 'payment_partner#adminHide', 'url' => '/api/admin/payment-partners/{id}/hide', 'verb' => 'POST'],
		['name' => 'payment_partner#adminUnhide', 'url' => '/api/admin/payment-partners/{id}/unhide', 'verb' => 'POST'],
		['name' => 'payment_partner#adminDestroy', 'url' => '/api/admin/payment-partners/{id}', 'verb' => 'DELETE'],
		['name' => 'integrity#inspect', 'url' => '/api/admin/integrity', 'verb' => 'GET'],
		['name' => 'integrity#repair', 'url' => '/api/admin/integrity/repair', 'verb' => 'POST'],
		['name' => 'integrity#merge', 'url' => '/api/admin/integrity/merge', 'verb' => 'POST'],
		['name' => 'admin_backup#settings', 'url' => '/api/admin/full-backup/settings', 'verb' => 'GET'],
		['name' => 'admin_backup#saveSettings', 'url' => '/api/admin/full-backup/settings', 'verb' => 'POST'],
		['name' => 'admin_backup#create', 'url' => '/api/admin/full-backup', 'verb' => 'POST'],
		['name' => 'admin_backup#destroy', 'url' => '/api/admin/full-backup/{fileName}', 'verb' => 'DELETE'],
		['name' => 'admin_backup#download', 'url' => '/api/admin/full-backup/{fileName}/download', 'verb' => 'GET'],
		['name' => 'admin_backup#restore', 'url' => '/api/admin/full-backup/restore', 'verb' => 'POST'],

		// API routes for Projects
		['name' => 'project#index', 'url' => '/api/projects', 'verb' => 'GET'],
		['name' => 'project#create', 'url' => '/api/projects', 'verb' => 'POST'],
		['name' => 'project#update', 'url' => '/api/projects/{id}', 'verb' => 'PUT'],
		['name' => 'project#show', 'url' => '/api/projects/{id}', 'verb' => 'GET'],
		['name' => 'project#destroy', 'url' => '/api/projects/{id}', 'verb' => 'DELETE'],
		['name' => 'project#archive', 'url' => '/api/projects/{id}/archive', 'verb' => 'POST'],
		['name' => 'project#unarchive', 'url' => '/api/projects/{id}/unarchive', 'verb' => 'POST'],
		['name' => 'project#settle', 'url' => '/api/projects/{id}/settle', 'verb' => 'POST'],
		['name' => 'project#settlements', 'url' => '/api/projects/{id}/settlements', 'verb' => 'GET'],
		['name' => 'project#updateShares', 'url' => '/api/projects/{id}/shares', 'verb' => 'PUT'],
		['name' => 'project#transferOwnership', 'url' => '/api/projects/{id}/owner', 'verb' => 'PUT'],

		// API routes for Project Members
		['name' => 'project#addMember', 'url' => '/api/projects/{id}/members', 'verb' => 'POST'],
		['name' => 'project#removeMember', 'url' => '/api/projects/{id}/members/{userId}', 'verb' => 'DELETE'],
		
		// API routes for Categories
		['name' => 'category#index', 'url' => '/api/categories', 'verb' => 'GET'],
		['name' => 'category#settingsData', 'url' => '/api/categories/settings', 'verb' => 'GET'],
		['name' => 'category#create', 'url' => '/api/categories', 'verb' => 'POST'],
		['name' => 'category#update', 'url' => '/api/categories/{id}', 'verb' => 'PUT'],
		['name' => 'category#hide', 'url' => '/api/categories/{id}/hide', 'verb' => 'POST'],
		['name' => 'category#unhide', 'url' => '/api/categories/{id}/unhide', 'verb' => 'POST'],
		['name' => 'category#updateIcon', 'url' => '/api/categories/{id}/icon', 'verb' => 'PUT'],
		['name' => 'category#destroy', 'url' => '/api/categories/{id}', 'verb' => 'DELETE'],

		// API routes for PaymentPartners
		['name' => 'payment_partner#index', 'url' => '/api/payment-partners', 'verb' => 'GET'],
		['name' => 'payment_partner#settingsData', 'url' => '/api/payment-partners/settings', 'verb' => 'GET'],
		['name' => 'payment_partner#create', 'url' => '/api/payment-partners', 'verb' => 'POST'],
		['name' => 'payment_partner#update', 'url' => '/api/payment-partners/{id}', 'verb' => 'PUT'],
		['name' => 'payment_partner#hide', 'url' => '/api/payment-partners/{id}/hide', 'verb' => 'POST'],
		['name' => 'payment_partner#unhide', 'url' => '/api/payment-partners/{id}/unhide', 'verb' => 'POST'],
		['name' => 'payment_partner#destroy', 'url' => '/api/payment-partners/{id}', 'verb' => 'DELETE'],

		// API routes for Users and Settings
		['name' => 'user#search', 'url' => '/api/users/search', 'verb' => 'GET'],
		['name' => 'user#getSettings', 'url' => '/api/settings', 'verb' => 'GET'],
		['name' => 'user#saveSettings', 'url' => '/api/settings', 'verb' => 'POST'],
		['name' => 'user#resetPreview', 'url' => '/api/settings/reset-preview', 'verb' => 'GET'],
		['name' => 'user#resetAll', 'url' => '/api/settings/reset', 'verb' => 'POST'],

		// API routes for Templates
		['name' => 'template#index', 'url' => '/api/templates', 'verb' => 'GET'],
		['name' => 'template#create', 'url' => '/api/templates', 'verb' => 'POST'],
		['name' => 'template#markUsed', 'url' => '/api/templates/{id}/use', 'verb' => 'POST'],
		['name' => 'template#destroy', 'url' => '/api/templates/{id}', 'verb' => 'DELETE'],

		// API routes for Workspaces
		['name' => 'workspace#index', 'url' => '/api/workspaces', 'verb' => 'GET'],
		['name' => 'workspace#create', 'url' => '/api/workspaces', 'verb' => 'POST'],
		['name' => 'workspace#update', 'url' => '/api/workspaces/{id}', 'verb' => 'PUT'],
		['name' => 'workspace#hide', 'url' => '/api/workspaces/{id}/hide', 'verb' => 'POST'],
		['name' => 'workspace#unhide', 'url' => '/api/workspaces/{id}/unhide', 'verb' => 'POST'],
		['name' => 'workspace#destroy', 'url' => '/api/workspaces/{id}', 'verb' => 'DELETE'],
	]
];
