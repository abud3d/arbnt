<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
require dirname(__DIR__).'/app/core.php';
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
function sendLink(string $email,string $type,string $raw):bool{
 $host=(string)($_SERVER['HTTP_HOST']??'');
 if(!preg_match('/^[a-z0-9.-]+(?::[0-9]{1,5})?$/i',$host))return false;
 $path=($type==='verify')?'verify':'reset';
 $url='https://'.$host.'/community.html?'.$path.'='.rawurlencode($raw);
 $subject=$type==='verify'?'ARBNT email verification':'ARBNT password reset';
 $headers="Content-Type: text/plain; charset=UTF-8\r\n";
 return mail($email,$subject,"Open this link within 30 minutes:\n".$url."\nIf you did not request this, ignore it.",$headers);
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
  if($action==='admin-security'){admin();$file=arbnt_data_dir().'/security.log';$lines=is_file($file)?array_slice(file($file,FILE_IGNORE_NEW_LINES)?:[],-50):[];out(200,['items'=>array_map(fn($v)=>json_decode($v,true),array_reverse($lines))]);}
  if($action==='admin-stats'){admin();out(200,['users'=>count(arbnt_read('users')),'posts'=>count(arbnt_read('posts')),'comments'=>count(arbnt_read('comments'))]);}
  if($action==='admin-users'){admin();$rows=[];foreach(arbnt_read('users') as $u)$rows[]=['id'=>$u['id'],'username'=>$u['username'],'role'=>$u['role']??'member','banned'=>!empty($u['banned']),'created_at'=>$u['created_at']??''];out(200,['items'=>$rows]);}
  if($action==='posts'){
   $posts=arbnt_read('posts');$posts=array_reverse($posts);
   $page=max(1,min(100000,(int)($_GET['page']??1)));
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
  throttle('register:'.($_SERVER['REMOTE_ADDR']??'unknown'),30);
  $name=clean($b['username']??'',32);$password=(string)($b['password']??'');
  $email=strtolower(clean($b['email']??'',254));if(!filter_var($email,FILTER_VALIDATE_EMAIL))out(422,['error'=>'البريد غير صالح']);
  if(!preg_match('/^[a-zA-Z0-9_]{3,32}$/',$name)||strlen($password)<12||strlen($password)>256)out(422,['error'=>'اسم المستخدم 3-32 حرفاً إنجليزياً أو رقماً، وكلمة المرور 12 حرفاً على الأقل']);
  $raw=bin2hex(random_bytes(32));
  $new=['id'=>bin2hex(random_bytes(12)),'username'=>$name,'email'=>$email,'verified'=>false,'verify_hash'=>hash('sha256',$raw),'verify_exp'=>time()+1800,'password_hash'=>password_hash($password,PASSWORD_DEFAULT),'role'=>'member','created_at'=>gmdate('c')];
  $exists=false;
  arbnt_write('users',function($a)use($new,&$exists){foreach($a as $u){if(strcasecmp($u['username'],$new['username'])===0||strcasecmp((string)($u['email']??''),$new['email'])===0){$exists=true;return $a;}}$a[]=$new;return $a;});
  if($exists)out(409,['error'=>'اسم المستخدم أو البريد محجوز']);
  $sent=sendLink($email,'verify',$raw);audit('registered',$name);
  out(201,['ok'=>true,'mail_sent'=>$sent,'message'=>$sent?'راجع بريدك لتفعيل العضوية':'تعذر إرسال رسالة التفعيل؛ تواصل مع الإدارة']);
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

 if($action==='post'){
  $title=clean($b['title']??'',180);$text=clean($b['text']??'',8000);
  $post=['id'=>bin2hex(random_bytes(12)),'title'=>$title,'text'=>$text,'author'=>$u['username'],'author_id'=>$u['id'],'created_at'=>gmdate('c')];
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
