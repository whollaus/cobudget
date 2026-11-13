<?php
declare(strict_types=1);
// Real ZIP and Nextcloud database; virtual Files storage; all DB changes roll back.
$options=getopt('', ['nextcloud:', 'user:']);
if (PHP_SAPI !== 'cli' || !isset($options['nextcloud'],$options['user'])) throw new RuntimeException('Pass --nextcloud and --user (local administrator)');
$serverRoot=realpath($options['nextcloud']);
if (!$serverRoot || !is_file($serverRoot.'/lib/base.php')) throw new RuntimeException('Nextcloud installation missing');
define('OC_CONSOLE',true);$_SERVER['SCRIPT_FILENAME']=__FILE__;
chdir($serverRoot);require $serverRoot.'/lib/base.php';OC_App::loadApps();
set_exception_handler(static function(Throwable $e):void {fwrite(STDERR,(string)$e."\n");exit(1);});
use OCP\Server;
use OCA\CoBudget\Service\BackupService;
class AuditFolder extends OC\Files\Node\Folder {
 public array $children=[];public bool $failWrite=false;public bool $truncateWrite=false;
 public function __construct() {}
 public function nodeExists($path) { try {$this->get($path);return true;}catch(OCP\Files\NotFoundException){return false;} }
 public function get($path) { $parts=explode('/',trim($path,'/'));$n=$this;foreach($parts as $p){if(!isset($n->children[$p]))throw new OCP\Files\NotFoundException();$n=$n->children[$p];}return $n; }
 public function newFolder($path) { return $this->children[$path]=new self(); }
 public function newFile($path,$content=null) { $f=$this->children[$path]=new AuditFile($this,$path);if($content!==null)$f->putContent($content);return $f; }
 public function getDirectoryListing(?string $mimetypeFilter=null):array {return array_values($this->children);}
}
class AuditFile extends OC\Files\Node\File {
 public string $contents='';
 public function __construct(private AuditFolder $parentFolder, private string $name) {}
 public function getName(){return $this->name;}
 public function getMTime(){return time();}
 public function getSize($includeMounts=true):int|float{return strlen($this->contents);}
 public function fopen($mode){$s=fopen('php://temp','w+b');fwrite($s,$this->contents);rewind($s);return $s;}
 public function putContent($data){if($this->parentFolder->failWrite)throw new RuntimeException('Simulated storage failure');$this->contents=is_resource($data)?stream_get_contents($data):(string)$data;if($this->parentFolder->truncateWrite)$this->contents=substr($this->contents,0,5);if(is_resource($data))fclose($data);}
 public function delete(){unset($this->parentFolder->children[$this->name]);}
}
$root=new class extends OC\Files\Node\Root {
 public array $folders=[];public function __construct(){}
 public function getUserFolder($userId){return $this->folders[$userId]??=new AuditFolder();}
};
$calendar=new class extends OCA\CoBudget\Service\CalendarReminderService {public function __construct(){} public function resetConnections(?string $userId=null):void{}};
$ref=new ReflectionClass(BackupService::class);
$service=$ref->newInstanceArgs(array_map(fn($p)=>match($p->getType()->getName()){
 OCP\Files\IRootFolder::class=>$root,
 OCP\IL10N::class=>Server::get(OCP\L10N\IFactory::class)->get('cobudget','en'),
 OCA\CoBudget\Service\CalendarReminderService::class=>$calendar,
 default=>Server::get($p->getType()->getName())
},$ref->getConstructor()->getParameters()));
$call=fn($method,...$args)=>(new ReflectionMethod($service,$method))->invoke($service,...$args);
$db=Server::get(OCP\IDBConnection::class);$uid=$options['user'];$assertions=0;
$check=static function(bool $ok,string $why)use(&$assertions):void{if(!$ok)throw new RuntimeException($why);$assertions++;};
$reject=static function(Closure $fn,string $why,string $exception=InvalidArgumentException::class)use($check):void{try{$fn();}catch(Throwable $e){if(!$e instanceof $exception)throw $e;$check(true,$why);return;}$check(false,$why);};
$db->beginTransaction();
try {
 $q=$db->getQueryBuilder();$q->insert('cobudget_workspaces')->setValue('user_id',$q->createNamedParameter($uid))->setValue('name',$q->createNamedParameter('Backup audit ä €'))->executeStatement();$ws=(int)$q->getLastInsertId();
 $q=$db->getQueryBuilder();$q->insert('cobudget_entries');foreach(['workspace_id'=>$ws,'user_id'=>$uid,'created_by'=>$uid,'entry_kind'=>'personal','type'=>'expense','date'=>time(),'amount'=>'12.34','amount_cents'=>1234,'currency'=>'EUR','description'=>'Audit =SUM(1;2) ä €']as$k=>$v)$q->setValue($k,$q->createNamedParameter($v));$q->executeStatement();$entry=(int)$q->getLastInsertId();
 // Snapshots survive normal goal deletion. Cover both current and legacy ZIPs.
 $insert=static function(string $table,array $row)use($db):int {
  $q=$db->getQueryBuilder();$q->insert('cobudget_'.$table);
  foreach($row as $k=>$v)$q->setValue($k,$q->createNamedParameter($v));
  $q->executeStatement();return (int)$q->getLastInsertId();
 };
 $goal=$insert('budget_goals',['user_id'=>$uid,'workspace_id'=>$ws,'name'=>'Audit deleted goal','amount_cents'=>10000]);
 $liveGoal=$insert('budget_goals',['user_id'=>$uid,'workspace_id'=>$ws,'name'=>'Audit live goal','amount_cents'=>20000]);
 foreach([$goal,$liveGoal]as$goalId)$insert('budget_snapshots',['budget_goal_id'=>$goalId,'user_id'=>$uid,'workspace_id'=>$ws,'goal_name'=>$goalId===$goal?'Audit deleted goal':'Audit live goal','amount_cents'=>12345,'spent_cents'=>1234,'snapshot_reason'=>$goalId===$goal?'deleted':'period_closed']);
 $q=$db->getQueryBuilder();$q->delete('cobudget_budget_goals')->where($q->expr()->eq('id',$q->createNamedParameter($goal)))->executeStatement();
 $personal=$service->createBackup($uid,'Audit/Personal',100);
 $full=$service->createFullBackup($uid,'Audit/Full',100);
 $check($full['file_size']>0 && $personal['file_size']>0,'Both ZIP archives are nonempty');
 $pfile=$root->getUserFolder($uid)->get($personal['file_path']);
 $fFile=$root->getUserFolder($uid)->get($full['file_path']);
 $before=$call('collectFullBackupData');
 $q=$db->getQueryBuilder();$q->update('cobudget_entries')->set('amount_cents',$q->createNamedParameter(999))->where($q->expr()->eq('id',$q->createNamedParameter($entry)))->executeStatement();
 \OCP\Server::get(\OCP\IConfig::class)->setUserValue($uid,'cobudget','currency','USD');
 $preFailure=$call('collectFullBackupData');
 $failing=clone $service;
 (new ReflectionProperty($failing,'dataIntegrityService'))->setValue($failing,new class extends OCA\CoBudget\Service\DataIntegrityService {
  public function __construct(){}
  public function assertProjectionIntegrity():void{throw new RuntimeException('Simulated post-insert failure');}
 });
 try{$failing->restoreFullBackup($uid,$full['file_name'],'Audit/Full');throw new LogicException('Post-insert failure ignored');}
 catch(RuntimeException $e){$check($e->getMessage()==='Simulated post-insert failure','Failure occurs after replacement inserts');}
 $check($call('collectFullBackupData')===$preFailure,'Failed full restore rolls back all tables and portable settings');
 $service->restoreFullBackup($uid,$full['file_name'],'Audit/Full');
 $after=$call('collectFullBackupData');
 $check($before['tables']===$after['tables'],'Full restore preserves every table and exact cents');
 $check($before['settings']===$after['settings'],'Full restore preserves portable settings');
 $history=array_values(array_filter($after['tables']['cobudget_budget_snapshots'],fn($r)=>$r['goal_name']==='Audit deleted goal'));
 $check(count($history)===1 && (int)$history[0]['budget_goal_id']===0 && (int)$history[0]['spent_cents']===1234,'Deleted goal history survives full restore without linking to a future goal');
 // Older archives kept the deleted ID: the public restore must accept those too.
 $legacyTemp=tempnam(sys_get_temp_dir(),'cb-legacy-');
 try {
  file_put_contents($legacyTemp,$fFile->contents);$zip=new ZipArchive();$zip->open($legacyTemp);
  $snapshots=json_decode($zip->getFromName('data/cobudget_budget_snapshots.json'),true);
  foreach($snapshots as &$snapshot)if($snapshot['goal_name']==='Audit deleted goal')$snapshot['budget_goal_id']=$goal;
  unset($snapshot);$zip->addFromString('data/cobudget_budget_snapshots.json',json_encode($snapshots));$zip->close();
  $original=$fFile->contents;$fFile->contents=file_get_contents($legacyTemp);
  try{$service->restoreFullBackup($uid,$full['file_name'],'Audit/Full');}finally{$fFile->contents=$original;}
  $check($call('collectFullBackupData')['tables']===$after['tables'],'Legacy deleted-goal references normalize without losing financial history');
 }finally{unlink($legacyTemp);}
 $reject(fn()=>$service->restoreBackup($uid,$personal['file_name'],'Audit/Personal'),'Personal import rejects populated target',RuntimeException::class);
 $call('deleteAllBackupTables');
 $restored=$service->restoreBackup($uid,$personal['file_name'],'Audit/Personal');
 $check($restored['scope']==='user','Public personal restore succeeds into empty target');
 $data=$call('collectBackupData',$uid);
 $check(count(array_filter($data['tables']['cobudget_entries'],fn($e)=>$e['description']==='Audit =SUM(1;2) ä €' && (int)$e['amount_cents']===1234))===1,'Personal ZIP roundtrip preserves Unicode and cents');
 $history=array_values(array_filter($data['tables']['cobudget_budget_snapshots'],fn($r)=>$r['goal_name']==='Audit deleted goal'));
 $check(count($history)===1 && (int)$history[0]['budget_goal_id']===0 && (int)$history[0]['amount_cents']===12345,'Personal restore retains detached history and exact budget amount');
 $goals=array_column($data['tables']['cobudget_budget_goals'],null,'id');
 $liveHistory=array_values(array_filter($data['tables']['cobudget_budget_snapshots'],fn($r)=>$r['goal_name']==='Audit live goal'));
 $check(count($liveHistory)===1 && ($goals[$liveHistory[0]['budget_goal_id']]['name']??'')==='Audit live goal','Personal restore remaps live snapshot references');
 // Mutate an archive, keeping the original safe. Each corruption must be rejected before writes.
 $temp=tempnam(sys_get_temp_dir(),'cb-audit-');
 try {
  foreach(['missing-table','missing-settings','bad-json','unknown-table','empty-inventory','path-traversal']as$case){
   file_put_contents($temp,$fFile->contents);$zip=new ZipArchive();$zip->open($temp);$manifest=json_decode($zip->getFromName('manifest.json'),true);
   if($case==='missing-table')$zip->deleteName('data/cobudget_settlement_transfers.json');
   if($case==='missing-settings')$zip->deleteName('settings.json');
   if($case==='bad-json')$zip->addFromString('settings.json','{');
   if($case==='unknown-table'){$manifest['tables']['alien']=1;$zip->addFromString('manifest.json',json_encode($manifest));}
   if($case==='empty-inventory'){$manifest['tables']=[];$zip->addFromString('manifest.json',json_encode($manifest));}
   if($case==='path-traversal')$zip->addFromString('../escape','bad');
   $zip->close();$copy=new AuditFile(new AuditFolder(),'corrupt.zip');$copy->contents=file_get_contents($temp);
   $reject(function()use($call,$copy){$a=$call('readBackupArchive',$copy);$call('assertBackupArchive',$a,'system');},'Reject '.$case);
  }
 }finally{unlink($temp);}
 $failedZip=new class extends ZipArchive {
  public function addFromString(string $name,string $content,int $flags=ZipArchive::FL_OVERWRITE):bool{return false;}
 };
 try{$call('addJson',$failedZip,'data/test.json',[]);throw new LogicException('ZIP write failure ignored');}
 catch(RuntimeException $e){$check(true,'ZIP write failure propagates');}
 $folder=$root->getUserFolder($uid)->get('Audit/Personal');$count=count($folder->children);$folder->failWrite=true;
 try{$service->createBackup($uid,'Audit/Personal',1);throw new LogicException('Storage failure ignored');}catch(RuntimeException $e){$check($e->getMessage()==='Simulated storage failure','Propagate storage error');}
 $check(count($folder->children)===$count,'Failed write removes partial file and does not prune good backups');
 $folder->failWrite=false;$folder->truncateWrite=true;
 try{$service->createBackup($uid,'Audit/Personal',1);throw new LogicException('Truncated write ignored');}
 catch(RuntimeException $e){$check($e->getMessage()==='Backup-Datei wurde nicht vollständig gespeichert','Detect silent short storage write');}
 $check(count($folder->children)===$count,'Short write keeps existing backups and removes partial file');
 echo "Backup roundtrip: $assertions assertions passed; virtual Files, real ZIP/database, rollback-only.\n";
}finally{$db->rollBack();}
