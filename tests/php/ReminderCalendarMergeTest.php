<?php
declare(strict_types=1);
use CoBudget\Tests\Support\TestRunner;
use OCA\CoBudget\Service\ReminderCalendarMerge;
require_once dirname(__DIR__,2).'/lib/Service/ReminderCalendarMerge.php';
return [
 'Calendar three-way merge preserves independent edits and never guesses conflicting values' => function(TestRunner $t): void {
  $base=['title'=>'Original','description'=>'Notes','remind_at'=>2000000000];
  $remote=array_replace($base,['title'=>'Calendar','remind_at'=>2000003600]);
  $local=array_replace($base,['description'=>'Local notes']);
  $result=ReminderCalendarMerge::merge($base,$local,$remote);
  $t->assertSame([], $result['fields'], 'Merge policy preserves the expected field values');
  $t->assertSame(['title'=>'Calendar','description'=>'Local notes','remind_at'=>2000003600],$result['merged'], 'Merge policy preserves the expected field values');
  $local['title']='Local title';
  $result=ReminderCalendarMerge::merge($base,$local,$remote);
  $t->assertSame(['title'=>['local'=>'Local title','calendar'=>'Calendar']],$result['fields'], 'Merge policy preserves the expected field values');
  $t->assertSame('Local notes',$result['merged']['description'], 'Merge policy preserves the expected field values');
  $t->assertSame(2000003600,$result['merged']['remind_at'], 'Merge policy preserves the expected field values');
  $t->assertSame([],ReminderCalendarMerge::merge($base,$remote,$remote)['fields'], 'Merge policy preserves the expected field values');
  $t->assertSame($local,ReminderCalendarMerge::merge($base,$local,$base)['merged'], 'Merge policy preserves the expected field values');
 },
 'Missing calendar baselines require an explicit upgrade choice for each differing field' => function(TestRunner $t): void {
  $local=['title'=>'Local','description'=>'','remind_at'=>2000000000];
  $remote=['title'=>'Remote','description'=>'Notes','remind_at'=>2000003600];
  $result=ReminderCalendarMerge::merge(null,$local,$remote);
  $t->assertSame(['title','description','remind_at'],array_keys($result['fields']), 'Merge policy preserves the expected field values');
  $t->assertSame($local,$result['merged'], 'Merge policy preserves the expected field values');
  $t->assertSame([],ReminderCalendarMerge::merge(null,$local,$local)['fields'], 'Merge policy preserves the expected field values');
 },
];
