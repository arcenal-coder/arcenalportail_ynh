<?php
// Runner/test fixture only. Never distributed in the application or web root.
declare(strict_types=1);
if (PHP_SAPI!=='cli' || getenv('S105_FIXTURE')!=='1') exit(1);
require $argv[1].'/src/load.php';
use Vigilance\{Config,Database,Access,Identity,AdminService,PersonService,AffairService,MissionService};
$install=$argv[1]; $action=$argv[2];
$config=Config::load(getenv('ARCENAL_PORTAL_CONFIG')?:$install.'/config.php');
$db=new Database($config['database_path'],$config); $company=(int)$config['company_id'];
$data=dirname($config['database_path']);
$fixture=json_decode(file_get_contents($argv[3]),true,512,JSON_THROW_ON_ERROR);
function expect(bool $condition,string $message):void { if(!$condition)throw new RuntimeException($message); }
function denied(callable $call):void { try{$call();}catch(DomainException $e){return;}throw new RuntimeException('Expected access denial'); }
$access=fn($uid,$role)=>new Access($db,$company,$uid,$role);
if($action==='context') {
    $result=['database_path'=>$config['database_path'],'company_id'=>$company];
} elseif($action==='seed4') {
    $db->checkSchema();
    expect(count($db->rows('SELECT * FROM schema_version'))===4,'Expected schema4');
    $admin=new AdminService($db,$access('package_checker','admin'));
    foreach($fixture['accounts'] as $account) {
        Identity::observe($db,$company,$account['uid']);
        $revision=(int)$db->rows('SELECT revision FROM account WHERE company_id=? AND uid=?',[$company,$account['uid']])[0]['revision'];
        $admin->updateAccount($account['uid'],$revision,$account['roles'],$account['uid']==='s105_manager'?['S105-A']:[],true,false,'');
    }
    $rh=new PersonService($db,$access('s105_rh','rh')); $ids=[];
    foreach(['manager','alice','bob'] as $who) $ids[$who]=$rh->create(['first_name'=>ucfirst($who),'last_name'=>'Fictif S1-05','team'=>'S105-A','account_uid'=>'s105_'.$who,'status'=>'active']);
    foreach(['s105_alice','s105_bob'] as $uid)$db->execute('INSERT INTO welcome_progress(company_id,uid,step,completed_at) VALUES (?,?,10,?)',[$company,$uid,'2026-10-28T00:00:00Z']);
    foreach($fixture['baseline4']['mail_states_to_seed'] as $state){
        $db->execute('INSERT INTO journey_notification(company_id,uid,title,body,created_at) VALUES (?,?,?,?,?)',[$company,'s105_alice','S105 '.$state,'S105 FICTIF','2026-10-28T00:00:00Z']);
        $id=(int)$db->pdo->lastInsertId();
        $db->execute('INSERT INTO journey_mail(notification_id,event,state,available_at,sent_at) VALUES (?,?,?,?,?)',[$id,'candidate.opened',$state,2147483647,$state==='sent'?'2026-10-28T00:00:00Z':null]);
    }
    if(!is_dir($data.'/welcome'))mkdir($data.'/welcome',0700,true);
    file_put_contents($data.'/welcome/'.$fixture['baseline4']['document']['filename'],$fixture['baseline4']['document']['content_utf8']);
    $db->audit($company,'s105_rh','s105.marker',$fixture['baseline4']['business_marker'],1);
    $result=['people'=>$ids];
} elseif($action==='group-config') {
    $path=$install.'/application-settings.php'; $settings=require $path;
    $settings['authorization_mode']='groups';$settings['app_instance']='arcenalportail';
    $settings['group_bindings_path']='/etc/arcenal-vigilance/arcenalportail/bindings.json';
    $settings['group_projection_path']='/etc/arcenal-vigilance/arcenalportail/projection.json';
    chmod($path,0600);file_put_contents($path,'<?php return '.var_export($settings,true).';');chmod($path,0400);
    $result=['mode'=>'groups'];
} elseif($action==='target6') {
    $db->checkSchema();expect(count($db->rows('SELECT * FROM schema_version'))===6,'Expected schema6');
    $ids=[];foreach(['manager','alice','bob'] as $who)$ids[$who]=$db->rows('SELECT id FROM person WHERE company_id=? AND account_uid=?',[$company,'s105_'.$who])[0]['id'];
    $a=$fixture['target6']['affair'];$a+=['client_label'=>'Client fictif','manager_person_id'=>$ids['manager'],'status'=>'active','external_system'=>'none','external_ref'=>'','nextcloud_folder_url'=>''];
    $affair=(new AffairService($db,$access('s105_direction','direction')))->create($a);
    $m=$fixture['target6']['mission'];$day=$m['day'];$service=new MissionService($db,$access('s105_manager','manager'));
    $mission=$service->create(['affair_id'=>$affair,'title'=>$m['title'],'site_label'=>$a['site_label'],'starts_on'=>$day,'ends_on'=>$day,'instructions'=>'Consigne fictive','risks'=>'Coactivité fictive','epi'=>'Casque','internal_note'=>'S105_PRIVATE',
        'assignments'=>[['person_id'=>$ids['alice'],'role_label'=>'Équipière','days'=>[['day'=>$day,'starts_at'=>$m['starts_at'],'ends_at'=>$m['ends_at'],'break_minutes'=>$m['break_minutes']]]]],'requirements'=>[],'resources'=>[]]);
    $temporary=$data.'/s105-upload.txt';file_put_contents($temporary,$fixture['target6']['resource']['content_utf8']);
    $resource=$service->addInternalResource($mission,1,'Consigne S105',true,$temporary,'consigne.txt');
    $current=$service->getManagement($mission);$service->publish($mission,(int)$current['revision']);
    $employee=new MissionService($db,$access('s105_alice','employee'));$view=$employee->getEmployee($mission);
    $employee->markResourceRead($view['assignment']['id'],$resource);
    $order=$employee->accept($view['assignment']['id'],true,$view['version']['id'],$view['assignment']['content_hash']);
    $result=['mission'=>$mission,'resource'=>$resource,'order'=>$order];
} elseif($action==='verify6') {
    $db->checkSchema();$mission=$db->rows('SELECT m.id FROM mission m JOIN affair a ON a.id=m.affair_id WHERE a.reference=?',[$fixture['target6']['affair']['reference']])[0]['id'];
    foreach(['direction','rh','manager'] as $role){$s=new MissionService($db,$access('s105_'.$role,$role));$s->getManagement($mission);if($role==='rh')expect(!$s->canManage(),'RH cannot publish');}
    $employee=new MissionService($db,$access('s105_alice','employee'));$view=$employee->getEmployee($mission);
    expect($view['assignment']['state']==='accepted','Accepted state');expect(!isset($view['mission']['internal_note']),'No private note');
    $order=$employee->orderDocument((int)$view['order']['id']);expect(is_file($order['path']) && str_starts_with(file_get_contents($order['path']),'%PDF-'),'Real PDF');
    denied(fn()=>(new MissionService($db,$access('s105_bob','employee')))->getEmployee($mission));
    denied(fn()=>(new MissionService($db,$access('package_checker','admin')))->orderDocument((int)$view['order']['id']));
    $resource=(int)$db->rows('SELECT id FROM mission_resource WHERE mission_id=? AND kind=?',[$mission,'internal_file'])[0]['id'];
    $document=$employee->resourceForDownload($resource);expect(is_file($document['path']),'Resource survives');
    $result=['pdf_sha256'=>hash_file('sha256',$order['path']),'resource_sha256'=>hash_file('sha256',$document['path'])];
} elseif($action==='finish-mail') {
    // After conservation/restore assertions, leave no pending synthetic event for
    // the EXISTING native mail test. No SMTP worker or client connector is called.
    $db->execute("UPDATE journey_mail SET state='cancelled' WHERE notification_id IN (SELECT id FROM journey_notification WHERE uid LIKE 's105_%') AND state IN ('pending','failed')");
    $result=['pending'=>(int)$db->rows("SELECT COUNT(*) AS n FROM journey_mail WHERE state='pending'")[0]['n']];
    expect($result['pending']===0,'Unexpected non-fixture pending mail');
} elseif($action==='state') {
    // Independent of checkSchema(): also usable immediately after failed migrate.
    $tables=[];
    foreach($db->rows("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name") as $row){
        $name=$row['name'];expect((bool)preg_match('/^[a-z][a-z0-9_]*$/D',$name),'Table name');
        if($name==='schema_version')continue;
        $rows=$db->rows('SELECT * FROM "'.$name.'"');$encoded=array_map(fn($r)=>json_encode($r,JSON_THROW_ON_ERROR),$rows);sort($encoded,SORT_STRING);
        $tables[$name]=['count'=>count($rows),'sha256'=>hash('sha256',implode("\n",$encoded))];
    }
    $files=[];
    foreach(['welcome','logos','missions'] as $tree){if(!is_dir($data.'/'.$tree))continue;
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($data.'/'.$tree,FilesystemIterator::SKIP_DOTS));
        foreach($iterator as $file){expect(!$file->isLink(),'No linked fixture data');if($file->isFile())$files[substr($file->getPathname(),strlen($data)+1)]=hash_file('sha256',$file->getPathname());}
    }ksort($files);
    $code=[];
    foreach(['src','bin','views','public','migrations'] as $tree){
        $iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($install.'/'.$tree,FilesystemIterator::SKIP_DOTS));
        foreach($iterator as $file)if($file->isFile())$code[substr($file->getPathname(),strlen($install)+1)]=hash_file('sha256',$file->getPathname());
    }ksort($code);
    $permissions=[];foreach([$install,$data,$install.'/config.php',$install.'/application-settings.php'] as $path){$s=stat($path);$permissions[$path]=['mode'=>$s['mode']&0777,'uid'=>$s['uid'],'gid'=>$s['gid']];}
    $result=['schema'=>$db->rows('SELECT * FROM schema_version ORDER BY version'),'tables'=>$tables,'documents'=>$files,'code'=>$code,'permissions'=>$permissions,
             'config'=>['config.php'=>hash_file('sha256',$install.'/config.php'),'application-settings.php'=>hash_file('sha256',$install.'/application-settings.php')]];
} else throw new InvalidArgumentException('Unknown fixture operation');
echo json_encode($result,JSON_THROW_ON_ERROR|JSON_UNESCAPED_UNICODE)."\n";
