<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$publicRoot=dirname(__DIR__);
$appPointer=$publicRoot.'/.arbnt-app.php';
if(is_file($appPointer)){
 $appPath=require $appPointer;
 if(!is_string($appPath)||!is_file($appPath.'/core.php')||is_link($appPath)){http_response_code(503);exit;}
 define('ARBNT_PUBLIC_ROOT',$publicRoot);
 require $appPath.'/core.php';
}else{require $publicRoot.'/app/core.php';}
ini_set('display_errors','0');
if(empty($_SERVER['HTTPS'])||$_SERVER['HTTPS']==='off')arbnt_json(403,['error'=>'HTTPS required']);
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none'");
header('X-Frame-Options: DENY');
session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Lax','use_strict_mode'=>true]);
function audit(string $event,string $subject=''):void{
 $path=arbnt_data_dir().'/security.log';
 $line=json_encode(['at'=>gmdate('c'),'event'=>$event,'subject'=>substr($subject,0,64),'ip_hash'=>hash('sha256',(string)($_SERVER['REMOTE_ADDR']??''))],JSON_UNESCAPED_UNICODE)."\n";
 file_put_contents($path,$line,FILE_APPEND|LOCK_EX);@chmod($path,0600);
}
function smtpRead($socket):string{
 $text='';
 while(!feof($socket)){
  $line=fgets($socket,2048);if($line===false)break;$text.=$line;
  if(strlen($line)>=4&&$line[3]===' ')break;
 }
 return $text;
}
function smtpExpect($socket,array $codes):string{
 $reply=smtpRead($socket);$code=(int)substr($reply,0,3);
 if(!in_array($code,$codes,true))throw new RuntimeException('SMTP '.$code);
 return $reply;
}
function smtpCommand($socket,string $command,array $codes):string{
 if(fwrite($socket,$command."\r\n")===false)throw new RuntimeException('SMTP write failed');
 return smtpExpect($socket,$codes);
}
function smtpSend(array $cfg,string $to,string $subject,string $body):bool{
 $host=trim((string)($cfg['host']??''));$port=(int)($cfg['port']??587);$enc=(string)($cfg['encryption']??'tls');
 $user=(string)($cfg['username']??'');$pass=(string)($cfg['password']??'');$from=(string)($cfg['from_email']??$user);$fromName=trim((string)($cfg['from_name']??'ARBNT'));
 if(!$host||$port<1||$port>65535||!filter_var($from,FILTER_VALIDATE_EMAIL)||!filter_var($to,FILTER_VALIDATE_EMAIL))return false;
 $target=($enc==='ssl'?'ssl://':'').$host.':'.$port;$errno=0;$errstr='';
 $socket=@stream_socket_client($target,$errno,$errstr,10,STREAM_CLIENT_CONNECT);
 if(!$socket)return false;
 stream_set_timeout($socket,12);
 try{
  smtpExpect($socket,[220]);$hello=preg_replace('/[^a-z0-9.-]/i','',(string)($_SERVER['HTTP_HOST']??'localhost'))?:'localhost';
  smtpCommand($socket,'EHLO '.$hello,[250]);
  if($enc==='tls'){
   smtpCommand($socket,'STARTTLS',[220]);
   if(!stream_socket_enable_crypto($socket,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('SMTP TLS failed');
   smtpCommand($socket,'EHLO '.$hello,[250]);
  }
  if($user!==''){
   smtpCommand($socket,'AUTH LOGIN',[334]);smtpCommand($socket,base64_encode($user),[334]);smtpCommand($socket,base64_encode($pass),[235]);
  }
  smtpCommand($socket,'MAIL FROM:<'.$from.'>',[250]);smtpCommand($socket,'RCPT TO:<'.$to.'>',[250,251]);smtpCommand($socket,'DATA',[354]);
  $safeSubject=function_exists('mb_encode_mimeheader')?mb_encode_mimeheader($subject,'UTF-8','B',"\r\n"):'=?UTF-8?B?'.base64_encode($subject).'?=';
  $safeName=function_exists('mb_encode_mimeheader')?mb_encode_mimeheader($fromName,'UTF-8','B',"\r\n"):'=?UTF-8?B?'.base64_encode($fromName).'?=';
  $headers=['Date: '.date(DATE_RFC2822),'From: '.$safeName.' <'.$from.'>','To: <'.$to.'>','Subject: '.$safeSubject,'MIME-Version: 1.0','Content-Type: text/plain; charset=UTF-8','Content-Transfer-Encoding: 8bit'];
  $payload=implode("\r\n",$headers)."\r\n\r\n".str_replace("\n.","\n..",str_replace(["\r\n","\r"],"\n",$body));
  if(fwrite($socket,str_replace("\n","\r\n",$payload)."\r\n.\r\n")===false)throw new RuntimeException('SMTP data failed');
  smtpExpect($socket,[250]);@smtpCommand($socket,'QUIT',[221,250]);fclose($socket);return true;
 }catch(Throwable $e){error_log('ARBNT SMTP: '.$e->getMessage());fclose($socket);return false;}
}
function sendMailMessage(string $to,string $subject,string $body):bool{
 $cfg=arbnt_config();$mail=is_array($cfg['mail']??null)?$cfg['mail']:[];
 if(($mail['driver']??'mail')==='smtp'&&!empty($mail['host']))return smtpSend($mail,$to,$subject,$body);
 $host=(string)($_SERVER['HTTP_HOST']??'');$domain=strtolower((string)parse_url('https://'.$host,PHP_URL_HOST));
 if(!$domain||!preg_match('/^[a-z0-9.-]+$/',$domain))return false;
 $from=(string)($mail['from_email']??('no-reply@'.$domain));if(!filter_var($from,FILTER_VALIDATE_EMAIL))$from='no-reply@'.$domain;
 $headers="MIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nFrom: ARBNT <".$from.">\r\nReply-To: ".$from."\r\n";
 $sent=false;try{$sent=@mail($to,$subject,$body,$headers,'-f'.$from);}catch(Throwable $e){}
 if(!$sent){try{$sent=@mail($to,$subject,$body,$headers);}catch(Throwable $e){}}
 return $sent;
}
function sendLink(string $email,string $type,string $raw):bool{
 $host=(string)($_SERVER['HTTP_HOST']??'');if(!preg_match('/^[a-z0-9.-]+(?::[0-9]{1,5})?$/i',$host))return false;
 $page=$type==='verify'?'login.html':'forgot.html';$param=$type==='verify'?'verify':'reset';
 $script=(string)($_SERVER['SCRIPT_NAME']??'/api/community.php');$base=rtrim(dirname(dirname($script)),'/');
 $url='https://'.$host.$base.'/'.$page.'?'.$param.'='.rawurlencode($raw);
 $subject=$type==='verify'?'تفعيل عضويتك في ARBNT':'استعادة كلمة المرور في ARBNT';
 $body=$type==='verify'
  ?"مرحباً بك في ARBNT\n\nلتفعيل عضويتك افتح الرابط التالي خلال 30 دقيقة:\n".$url."\n\nإذا لم تطلب التسجيل فتجاهل الرسالة."
  :"لاستعادة كلمة المرور افتح الرابط التالي خلال 30 دقيقة:\n".$url."\n\nإذا لم تطلب الاستعادة فتجاهل الرسالة.";
 $sent=sendMailMessage($email,$subject,$body);if(!$sent)audit('mail_failed',$type.':'.substr(hash('sha256',$email),0,12));return $sent;
}
function out(int $code,array $data):never{arbnt_json($code,$data);}
function body():array{
 if((int)($_SERVER['CONTENT_LENGTH']??0)>16000)out(413,['error'=>'طلب كبير']);
 if(!str_contains((string)($_SERVER['CONTENT_TYPE']??''),'application/json'))out(415,['error'=>'JSON مطلوب']);
 $v=json_decode(file_get_contents('php://input'),true);
 if(!is_array($v))out(400,['error'=>'بيانات غير صالحة']);
 return $v;
}
function clean(mixed $s,int $max):string{
 if(!is_string($s))out(422,['error'=>'نص غير صالح']);
 $s=trim($s);
 if($s===''||strlen($s)>$max||preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F]/',$s))out(422,['error'=>'طول النص غير صالح']);
 return $s;
}
function user():?array{
 $s=$_SESSION['arbnt_user']??null;if(!is_array($s))return null;
 foreach(arbnt_read('users') as $u){if(($u['id']??'')===($s['id']??'')&&empty($u['banned'])&&(!isset($u['verified'])||$u['verified']===true)&&($u['session_version']??0)===($s['session_version']??0))return ['id'=>$u['id'],'username'=>$u['username'],'role'=>$u['role']??'member'];}
 unset($_SESSION['arbnt_user']);return null;
}
function admin():array{$u=requireUser();if(($u['role']??'')!=='admin')out(403,['error'=>'للمدير فقط']);return $u;}
function requireUser():array{$u=user();if(!$u)out(401,['error'=>'سجل الدخول أولاً']);return $u;}
function token():void{
 $sent=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
 if(!$sent||!hash_equals((string)($_SESSION['arbnt_csrf']??''),$sent))out(403,['error'=>'رمز الحماية غير صالح']);
 $origin=(string)($_SERVER['HTTP_ORIGIN']??'');
 if($origin){
  $o=parse_url($origin);
  if(!is_array($o)||strcasecmp((string)($o['host']??''),(string)parse_url('http://'.($_SERVER['HTTP_HOST']??''),PHP_URL_HOST))!==0)out(403,['error'=>'مصدر الطلب غير مسموح']);
 }
}
function throttle(string $key,int $seconds):void{
 $now=time();$dir=arbnt_data_dir();$f=$dir.'/rate-'.hash('sha256',$key).'.lock';
 $h=fopen($f,'c+');if(!$h||!flock($h,LOCK_EX))out(503,['error'=>'تعذر معالجة الطلب']);
 $last=(int)stream_get_contents($h);
 if($now-$last<$seconds){flock($h,LOCK_UN);fclose($h);out(429,['error'=>'انتظر قليلاً قبل المحاولة']);}
 rewind($h);ftruncate($h,0);fwrite($h,(string)$now);fflush($h);flock($h,LOCK_UN);fclose($h);
}
try{
 arbnt_config();
 if(empty($_SESSION['arbnt_csrf']))$_SESSION['arbnt_csrf']=bin2hex(random_bytes(24));
 $action=(string)($_GET['action']??'');
 $method=$_SERVER['REQUEST_METHOD']??'GET';
 if($method==='GET'){
  if($action==='session')out(200,['user'=>user(),'csrf'=>$_SESSION['arbnt_csrf']]);
  if($action==='admin-storage'){admin();out(200,arbnt_storage_status());}
  if($action==='admin-security'){admin();$file=arbnt_data_dir().'/security.log';$lines=is_file($file)?array_slice(file($file,FILE_IGNORE_NEW_LINES)?:[],-50):[];out(200,['items'=>array_map(fn($v)=>json_decode($v,true),array_reverse($lines))]);}
  if($action==='admin-stats'){admin();out(200,['users'=>count(arbnt_read('users')),'posts'=>count(arbnt_read('posts')),'comments'=>count(arbnt_read('comments')),'communities'=>count(arbnt_read('communities'))]);}
  if($action==='admin-overview'){
   admin();$users=arbnt_read('users');$posts=arbnt_read('posts');$comments=arbnt_read('comments');$communities=arbnt_read('communities');
   $days=[];for($i=13;$i>=0;$i--){$d=gmdate('Y-m-d',time()-$i*86400);$days[$d]=['day'=>$d,'posts'=>0,'members'=>0,'comments'=>0];}
   foreach([['rows'=>$posts,'key'=>'posts'],['rows'=>$users,'key'=>'members'],['rows'=>$comments,'key'=>'comments']] as $series){foreach($series['rows'] as $row){$date=substr((string)($row['created_at']??''),0,10);if(isset($days[$date]))$days[$date][$series['key']]++;}}
   $banned=count(array_filter($users,static fn($u)=>!empty($u['banned'])));
   $unverified=count(array_filter($users,static fn($u)=>isset($u['verified'])&&$u['verified']===false));
   $logsFile=arbnt_data_dir().'/security.log';$logLines=is_file($logsFile)?array_slice(file($logsFile,FILE_IGNORE_NEW_LINES)?:[],-100):[];
   $recentFailures=0;foreach($logLines as $line){$entry=json_decode($line,true);if(is_array($entry)&&preg_match('/fail|denied|blocked|invalid|رفض|فشل/i',(string)($entry['event']??'')))$recentFailures++;}
   $warnings=[];if($unverified)$warnings[]=['level'=>'warning','title'=>'حسابات غير مفعلة','detail'=>$unverified.' حساب يحتاج تفعيل البريد'];
   if($banned)$warnings[]=['level'=>'info','title'=>'حسابات محظورة','detail'=>$banned.' حساب محظور'];
   if($recentFailures>=5)$warnings[]=['level'=>'warning','title'=>'محاولات أمنية تستحق المراجعة','detail'=>$recentFailures.' حدثاً في آخر 100 سجل'];
   $storage=arbnt_storage_status();if($storage['mode']!=='outside')$warnings[]=['level'=>'warning','title'=>'موقع التخزين','detail'=>'البيانات داخل مجلد الموقع؛ راجع الحماية'];
   if(!$warnings)$warnings[]=['level'=>'ok','title'=>'لا توجد تنبيهات حالية','detail'=>'لم ترصد مؤشرات التحذير المحددة في لوحة الإدارة'];
   $counts=[];foreach($posts as $post){$id=(string)($post['community_id']??'');$counts[$id]=($counts[$id]??0)+1;}$top=[];foreach($communities as $cm){$id=(string)($cm['id']??'');$top[]=['name'=>(string)($cm['name']??'مجتمع'),'count'=>$counts[$id]??0];}usort($top,static fn($a,$b)=>$b['count']<=>$a['count']);
   out(200,['stats'=>['members'=>count($users),'posts'=>count($posts),'comments'=>count($comments),'communities'=>count($communities),'banned'=>$banned,'unverified'=>$unverified],'trend'=>array_values($days),'warnings'=>$warnings,'top_communities'=>array_slice($top,0,6),'storage'=>$storage]);
  }
  if($action==='admin-users'){admin();$rows=[];foreach(arbnt_read('users') as $u)$rows[]=['id'=>$u['id'],'username'=>$u['username'],'role'=>$u['role']??'member','banned'=>!empty($u['banned']),'verified'=>!isset($u['verified'])||$u['verified']===true,'email'=>(string)($u['email']??''),'created_at'=>$u['created_at']??''];out(200,['items'=>$rows]);}

  if($action==='admin-settings'){
   admin();$cfg=arbnt_config();
   out(200,['site_name'=>(string)($cfg['site_name']??'شبكة العرب'),'site_description'=>(string)($cfg['site_description']??''),'version'=>trim((string)@file_get_contents($publicRoot.'/VERSION')),'storage'=>arbnt_storage_status()]);
  }
  if($action==='site-info'){
   $cfg=arbnt_config();out(200,['site_name'=>(string)($cfg['site_name']??'شبكة العرب'),'site_description'=>(string)($cfg['site_description']??'')]);
  }
  if($action==='admin-content-posts'||$action==='admin-content-comments'){
   admin();$kind=$action==='admin-content-posts'?'posts':'comments';$rows=array_reverse(arbnt_read($kind));
   $q=trim((string)($_GET['q']??''));if(strlen($q)>100)out(422,['error'=>'بحث طويل']);
   if($q!=='')$rows=array_values(array_filter($rows,static fn($r)=>str_contains(strtolower((string)($r['title']??'').' '.(string)($r['text']??'').' '.(string)($r['author']??'')),strtolower($q))));
   $page=max(1,min(10000,(int)($_GET['page']??1)));$per=15;
   out(200,['items'=>array_slice($rows,($page-1)*$per,$per),'total'=>count($rows),'page'=>$page,'per_page'=>$per]);
  }
  if($action==='admin-communities'){
   admin();$rows=arbnt_read('communities');$counts=[];foreach(arbnt_read('posts') as $post){$id=(string)($post['community_id']??'');$counts[$id]=($counts[$id]??0)+1;}
   foreach($rows as &$cm)$cm['post_count']=$counts[(string)($cm['id']??'')]??0;unset($cm);
   out(200,['items'=>$rows]);
  }
  if($action==='communities')out(200,['items'=>arbnt_read('communities')]);
  if($action==='profile'){
   $name=(string)($_GET['username']??'');$found=null;
   foreach(arbnt_read('users') as $member)if(strcasecmp((string)($member['username']??''),$name)===0){$found=$member;break;}
   if(!$found)out(404,['error'=>'العضو غير موجود']);
   $items=array_values(array_filter(arbnt_read('posts'),fn($p)=>($p['author_id']??'')===($found['id']??'')));
   $me=user();$own=$me&&($me['id']??'')===($found['id']??'');
   $profile=['username'=>$found['username'],'bio'=>$found['bio']??'','created_at'=>$found['created_at']??'','posts'=>count($items),'own'=>$own];
   if($own){$profile['email']=$found['email']??'';$profile['verified']=!isset($found['verified'])||$found['verified']===true;}
   out(200,['profile'=>$profile,'items'=>array_reverse($items)]);
  }
  if($action==='favorites'){ $me=requireUser();$ids=arbnt_read('favorites');$mine=array_column(array_values(array_filter($ids,fn($v)=>($v['user_id']??'')===$me['id'])),'post_id');out(200,['ids'=>$mine,'items'=>array_values(array_filter(arbnt_read('posts'),fn($v)=>in_array($v['id'],$mine,true)))]);}
  if($action==='posts'){
   $posts=arbnt_read('posts');$community=(string)($_GET['community']??'');if($community!=='')$posts=array_values(array_filter($posts,fn($p)=>($p['community_id']??'')===$community));
   $replyCounts=[];foreach(arbnt_read('comments') as $comment){$pid=(string)($comment['post_id']??'');$replyCounts[$pid]=($replyCounts[$pid]??0)+1;}
   foreach($posts as &$post)$post['reply_count']=$replyCounts[(string)($post['id']??'')]??0;unset($post);
   $posts=array_reverse($posts);$page=max(1,min(100000,(int)($_GET['page']??1)));
   out(200,['items'=>array_slice($posts,($page-1)*20,20),'total'=>count($posts),'page'=>$page]);
  }
  if($action==='comments'){
   throttle('read:'.($_SERVER['REMOTE_ADDR']??'unknown'),1);
   $id=(string)($_GET['post']??'');
   out(200,['items'=>array_values(array_filter(arbnt_read('comments'),fn($c)=>($c['post_id']??'')===$id))]);
  }
  out(404,['error'=>'غير موجود']);
 }
 if($method!=='POST')out(405,['error'=>'طريقة غير مدعومة']);
 token();$b=body();
 if($action==='verify'){
  $raw=(string)($b['token']??'');if(!preg_match('/^[a-f0-9]{64}$/',$raw))out(422,['error'=>'رابط غير صالح']);
  $found=false;$hash=hash('sha256',$raw);
  arbnt_write('users',function($a)use($hash,&$found){foreach($a as &$v){if(!empty($v['verify_hash'])&&hash_equals($v['verify_hash'],$hash)&&($v['verify_exp']??0)>=time()){$v['verified']=true;unset($v['verify_hash'],$v['verify_exp']);$found=true;}}unset($v);return $a;});
  if(!$found)out(400,['error'=>'رابط منتهي أو مستخدم']);audit('email_verified');out(200,['ok'=>true]);
 }
 if($action==='forgot'){
  throttle('forgot:'.($_SERVER['REMOTE_ADDR']??''),30);
  $email=strtolower(clean($b['email']??'',254));if(!filter_var($email,FILTER_VALIDATE_EMAIL))out(422,['error'=>'بريد غير صالح']);
  $raw=bin2hex(random_bytes(32));$found=false;
  arbnt_write('users',function($a)use($email,$raw,&$found){foreach($a as &$v){if(strcasecmp((string)($v['email']??''),$email)===0){$v['reset_hash']=hash('sha256',$raw);$v['reset_exp']=time()+1800;$found=true;}}unset($v);return $a;});
  if($found)sendLink($email,'reset',$raw);
  audit('password_reset_requested');out(200,['ok'=>true,'message'=>'إذا كان البريد مسجلاً فستصلك رسالة']);
 }
 if($action==='reset'){
  throttle('reset:'.($_SERVER['REMOTE_ADDR']??''),5);
  $raw=(string)($b['token']??'');$password=(string)($b['password']??'');
  if(!preg_match('/^[a-f0-9]{64}$/',$raw)||strlen($password)<12||strlen($password)>256)out(422,['error'=>'الرابط أو كلمة المرور غير صالحة']);
  $found=false;$hash=hash('sha256',$raw);
  arbnt_write('users',function($a)use($hash,$password,&$found){foreach($a as &$v){if(!empty($v['reset_hash'])&&hash_equals($v['reset_hash'],$hash)&&($v['reset_exp']??0)>=time()){$v['password_hash']=password_hash($password,PASSWORD_DEFAULT);$v['session_version']=($v['session_version']??0)+1;unset($v['reset_hash'],$v['reset_exp']);$found=true;}}unset($v);return $a;});
  if(!$found)out(400,['error'=>'رابط منتهي أو مستخدم']);audit('password_reset');out(200,['ok'=>true]);
 }
 if($action==='register'){
  $name=clean($b['username']??'',32);$password=(string)($b['password']??'');
  $email=strtolower(clean($b['email']??'',254));
  if(!filter_var($email,FILTER_VALIDATE_EMAIL))out(422,['error'=>'البريد غير صالح']);
  if(!preg_match('/^[a-zA-Z0-9_]{3,32}$/',$name)||strlen($password)<12||strlen($password)>256)out(422,['error'=>'اسم المستخدم 3-32 حرفاً إنجليزياً أو رقماً، وكلمة المرور 12 حرفاً على الأقل']);
  foreach(arbnt_read('users') as $existing){
   if(strcasecmp((string)($existing['username']??''),$name)===0)out(409,['error'=>'اسم المستخدم مستخدم مسبقاً']);
   if(strcasecmp((string)($existing['email']??''),$email)===0)out(409,['error'=>'البريد الإلكتروني مستخدم مسبقاً']);
  }
  throttle('register:'.($_SERVER['REMOTE_ADDR']??'unknown'),15);
  $raw=bin2hex(random_bytes(32));
  $new=['id'=>bin2hex(random_bytes(12)),'username'=>$name,'email'=>$email,'verified'=>false,'verify_hash'=>hash('sha256',$raw),'verify_exp'=>time()+1800,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>'member','created_at'=>gmdate('c')];
  $conflict='';
  arbnt_write('users',function($rows)use($new,&$conflict){
   foreach($rows as $row){
    if(strcasecmp((string)($row['username']??''),$new['username'])===0){$conflict='username';return $rows;}
    if(strcasecmp((string)($row['email']??''),$new['email'])===0){$conflict='email';return $rows;}
   }
   $rows[]=$new;return $rows;
  });
  if($conflict==='username')out(409,['error'=>'اسم المستخدم مستخدم مسبقاً']);
  if($conflict==='email')out(409,['error'=>'البريد الإلكتروني مستخدم مسبقاً']);
  $sent=sendLink($email,'verify',$raw);audit($sent?'registered_mail_sent':'registered_mail_failed',$name);
  out(201,['ok'=>true,'mail_sent'=>$sent,'message'=>$sent?'تم التسجيل. أرسلنا رابط التفعيل إلى بريدك؛ افتحه لإكمال العضوية.':'تم التسجيل، لكن خادم الاستضافة لم يرسل بريد التفعيل. أعد الإرسال من نفس الصفحة أو راجع إعداد البريد في الاستضافة.']);
 }
 if($action==='resend-verification'){
  throttle('resend:'.($_SERVER['REMOTE_ADDR']??'unknown'),30);
  $email=strtolower(clean($b['email']??'',254));if(!filter_var($email,FILTER_VALIDATE_EMAIL))out(422,['error'=>'البريد غير صالح']);
  $raw=bin2hex(random_bytes(32));$found=false;
  arbnt_write('users',function($rows)use($email,$raw,&$found){foreach($rows as &$row){if(strcasecmp((string)($row['email']??''),$email)===0&&isset($row['verified'])&&$row['verified']===false){$row['verify_hash']=hash('sha256',$raw);$row['verify_exp']=time()+1800;$found=true;break;}}unset($row);return $rows;});
  $sent=$found?sendLink($email,'verify',$raw):true;
  audit('verification_resent',$found?'matched':'hidden');
  out(200,['ok'=>true,'mail_sent'=>$sent,'message'=>$sent?'إذا كان الحساب يحتاج تفعيل فقد أرسلنا رابطاً جديداً.':'تعذر على الخادم إرسال البريد. راجع إعداد البريد في الاستضافة.']);
 }
 if($action==='login'){
  throttle('login:'.($_SERVER['REMOTE_ADDR']??'unknown'),2);
  $name=clean($b['username']??'',32);
  throttle('account:'.strtolower($name),3);$password=(string)($b['password']??'');
  $match=null;
  foreach(arbnt_read('users') as $u){if(hash_equals($u['username'],$name)&&password_verify($password,$u['password_hash'])){$match=$u;break;}}
  if(!$match||!empty($match['banned'])){audit('login_failed',$name);out(401,['error'=>'بيانات الدخول غير صحيحة']);}
  if(isset($match['verified'])&&!$match['verified'])out(403,['error'=>'يجب تفعيل البريد قبل الدخول']);
  session_regenerate_id(true);
  $_SESSION['arbnt_user']=['id'=>$match['id'],'username'=>$match['username'],'role'=>$match['role']??'member','session_version'=>$match['session_version']??0];
  audit('login_success',$name);
  $_SESSION['arbnt_csrf']=bin2hex(random_bytes(24));
  out(200,['user'=>user(),'csrf'=>$_SESSION['arbnt_csrf']]);
 }
 if($action==='logout'){audit('logout');$_SESSION=[];session_regenerate_id(true);out(200,['ok'=>true]);}
 $u=requireUser();
 throttle('write:'.$u['id'],2);
 if($action==='admin-storage-migrate'){
  admin();$password=(string)($b['password']??'');$valid=false;
  foreach(arbnt_read('users') as $member){if(($member['id']??'')===$u['id']&&password_verify($password,(string)($member['password_hash']??'')))$valid=true;}
  if(!$valid)out(403,['error'=>'كلمة مرور المدير غير صحيحة']);
  $result=arbnt_storage_migrate();audit('storage_migrated',$u['id']);out(200,['ok'=>true,'storage'=>$result,'notice'=>'تم النقل. احتفظ بالمجلد القديم محمياً حتى تتحقق من عمل الموقع؛ لا تحذفه من لوحة الملفات الآن.']);
 }
 if($action==='admin-ban'){
  admin();$id=clean($b['id']??'',64);$ban=($b['banned']??null)===true;
  if($id===$u['id'])out(422,['error'=>'لا يمكن حظر نفسك']);
  $found=false;
  arbnt_write('users',function($a)use($id,$ban,&$found){foreach($a as &$v){if($v['id']===$id&&($v['role']??'')!=='admin'){$v['banned']=$ban;$found=true;}}unset($v);return $a;});
  if(!$found)out(404,['error'=>'العضو غير موجود أو مدير']);audit('admin_ban',$id);out(200,['ok'=>true]);
 }
 if($action==='admin-delete'){
  admin();$kind=(string)($b['kind']??'');$id=clean($b['id']??'',64);
  if(!in_array($kind,['posts','comments'],true))out(422,['error'=>'نوع غير صالح']);
  $found=false;arbnt_write($kind,function($a)use($id,&$found){$new=[];foreach($a as $item){if(($item['id']??'')===$id){$found=true;continue;}$new[]=$item;}return $new;});
  if(!$found)out(404,['error'=>'العنصر غير موجود']);audit('admin_delete',$kind);
  if($kind==='posts')arbnt_write('comments',fn($a)=>array_values(array_filter($a,fn($v)=>($v['post_id']??'')!==$id)));
  out(200,['ok'=>true]);
 }


 if($action==='admin-verify'){
  admin();$id=clean($b['id']??'',64);$found=false;
  arbnt_write('users',static function($rows)use($id,&$found){foreach($rows as &$row){if(($row['id']??'')===$id&&($row['role']??'')!=='admin'&&isset($row['verified'])&&$row['verified']===false){$row['verified']=true;unset($row['verify_hash'],$row['verify_exp']);$found=true;break;}}unset($row);return $rows;});
  if(!$found)out(404,['error'=>'العضوية غير موجودة أو مفعلة مسبقاً']);audit('admin_verified_user',$id);out(200,['ok'=>true]);
 }
 if($action==='admin-settings-update'){
  admin();$name=clean($b['site_name']??'',100);$description=trim((string)($b['site_description']??''));
  if(strlen($description)>320||preg_match('/[[:cntrl:]]/u',$description))out(422,['error'=>'الوصف غير صالح']);
  arbnt_write('config',static function($cfg)use($name,$description){$cfg['site_name']=$name;$cfg['site_description']=$description;$cfg['updated_at']=gmdate('c');return $cfg;});
  audit('admin_settings_update',$u['id']);out(200,['ok'=>true]);
 }
 if($action==='admin-post-update'){
  admin();$id=clean($b['id']??'',64);$title=clean($b['title']??'',180);$text=clean($b['text']??'',8000);
  $community=(string)($b['community_id']??'');$valid=$community==='';foreach(arbnt_read('communities') as $cm)if(($cm['id']??'')===$community)$valid=true;
  if(!$valid)out(422,['error'=>'المجتمع غير موجود']);
  $image=trim((string)($b['image']??''));
  if($image!==''&&(strlen($image)>1000||!filter_var($image,FILTER_VALIDATE_URL)||strtolower((string)parse_url($image,PHP_URL_SCHEME))!=='https'))out(422,['error'=>'رابط الصورة يجب أن يكون HTTPS']);
  $found=false;arbnt_write('posts',static function($rows)use($id,$title,$text,$community,$image,&$found){foreach($rows as &$row)if(($row['id']??'')===$id){$row['title']=$title;$row['text']=$text;$row['community_id']=$community;$row['image']=$image;$row['updated_at']=gmdate('c');$found=true;break;}unset($row);return $rows;});
  if(!$found)out(404,['error'=>'المنشور غير موجود']);audit('admin_post_update',$id);out(200,['ok'=>true]);
 }
 if($action==='admin-community-update'){
  admin();$id=clean($b['id']??'',64);$name=clean($b['name']??'',80);$description=trim((string)($b['description']??''));
  if(strlen($description)>500||preg_match('/[[:cntrl:]]/u',$description))out(422,['error'=>'الوصف غير صالح']);
  $found=false;arbnt_write('communities',static function($rows)use($id,$name,$description,&$found){foreach($rows as &$row)if(($row['id']??'')===$id){$row['name']=$name;$row['description']=$description;$found=true;break;}unset($row);return $rows;});
  if(!$found)out(404,['error'=>'المجتمع غير موجود']);audit('admin_community_update',$id);out(200,['ok'=>true]);
 }
 if($action==='admin-community-delete'){
  admin();$id=clean($b['id']??'',64);
  foreach(arbnt_read('posts') as $post)if(($post['community_id']??'')===$id)out(409,['error'=>'المجتمع يحتوي منشورات؛ انقلها إلى عام أولاً']);
  $found=false;arbnt_write('communities',static function($rows)use($id,&$found){$new=[];foreach($rows as $row){if(($row['id']??'')===$id){$found=true;continue;}$new[]=$row;}return $new;});
  if(!$found)out(404,['error'=>'المجتمع غير موجود']);audit('admin_community_delete',$id);out(200,['ok'=>true]);
 }
 if($action==='favorite'){
  $id=clean($b['id']??'',64);$found=false;foreach(arbnt_read('posts') as $p)if(($p['id']??'')===$id)$found=true;
  if(!$found)out(404,['error'=>'المنشور غير موجود']);
  $saved=false;arbnt_write('favorites',function($items)use($id,$u,&$saved){foreach($items as $i=>$item)if(($item['user_id']??'')===$u['id']&&($item['post_id']??'')===$id){unset($items[$i]);return array_values($items);} $items[]=['user_id'=>$u['id'],'post_id'=>$id];$saved=true;return $items;});
  out(200,['saved'=>$saved]);
 }
 if($action==='profile-update'){
  $bio=trim((string)($b['bio']??''));if(strlen($bio)>500||preg_match('/[\\x00-\\x08\\x0B\\x0C\\x0E-\\x1F]/',$bio))out(422,['error'=>'نبذة غير صالحة']);
  arbnt_write('users',function($items)use($u,$bio){foreach($items as &$item)if($item['id']===$u['id'])$item['bio']=$bio;unset($item);return $items;});out(200,['ok'=>true]);
 }
 if($action==='community-create'){
  $name=clean($b['name']??'',80);$description=trim((string)($b['description']??''));
  if(strlen($description)>500)out(422,['error'=>'وصف طويل']);
  $slug=strtolower(trim((string)($b['slug']??'')));if(!preg_match('/^[a-z0-9-]{3,40}$/',$slug))out(422,['error'=>'المعرف 3-40 حرفاً إنجليزياً أو رقماً أو شرطة']);
  $created=null;arbnt_write('communities',function($items)use($name,$description,$slug,$u,&$created){foreach($items as $item)if(($item['slug']??'')===$slug)return $items;$created=['id'=>bin2hex(random_bytes(12)),'slug'=>$slug,'name'=>$name,'description'=>$description,'owner_id'=>$u['id'],'created_at'=>gmdate('c')];$items[]=$created;return $items;});
  if(!$created)out(409,['error'=>'معرف المجتمع مستخدم']);out(201,['item'=>$created]);
 }
 if($action==='post'){
  $title=clean($b['title']??'',180);$text=clean($b['text']??'',8000);
  $communityId=(string)($b['community_id']??'');$valid=$communityId==='';
  foreach(arbnt_read('communities') as $community)if($community['id']===$communityId)$valid=true;
  if(!$valid)out(422,['error'=>'المجتمع غير موجود']);
  $image=trim((string)($b['image']??''));if($image!==''){
   if(strlen($image)>1000||!filter_var($image,FILTER_VALIDATE_URL)||strtolower((string)parse_url($image,PHP_URL_SCHEME))!=='https')out(422,['error'=>'رابط الصورة يجب أن يكون HTTPS']);
  }
  $post=['id'=>bin2hex(random_bytes(12)),'title'=>$title,'text'=>$text,'image'=>$image,'community_id'=>$communityId,'author'=>$u['username'],'author_id'=>$u['id'],'created_at'=>gmdate('c')];
  arbnt_write('posts',function($a)use($post){$a[]=$post;return $a;});
  out(201,['item'=>$post]);
 }
 if($action==='comment'){
  $postId=clean($b['post_id']??'',64);$text=clean($b['text']??'',3000);
  $exists=false;foreach(arbnt_read('posts') as $p){if(($p['id']??'')===$postId){$exists=true;break;}}
  if(!$exists)out(404,['error'=>'المنشور غير موجود']);
  $comment=['id'=>bin2hex(random_bytes(12)),'post_id'=>$postId,'text'=>$text,'author'=>$u['username'],'author_id'=>$u['id'],'created_at'=>gmdate('c')];
  arbnt_write('comments',function($a)use($comment){$a[]=$comment;return $a;});
  out(201,['item'=>$comment]);
 }
 out(404,['error'=>'غير موجود']);
}catch(Throwable $e){error_log('ARBNT: '.$e->getMessage());out(503,['error'=>'تعذر إتمام الطلب']);}
