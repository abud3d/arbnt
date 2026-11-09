<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
require dirname(__DIR__).'/app/core.php';
ini_set('display_errors','0');
session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Lax','use_strict_mode'=>true]);
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
function user():?array{return $_SESSION['arbnt_user']??null;}
function requireUser():array{$u=user();if(!$u)out(401,['error'=>'سجل الدخول أولاً']);return $u;}
function token():void{
 $sent=(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'');
 if(!$sent||!hash_equals((string)($_SESSION['arbnt_csrf']??''),$sent))out(403,['error'=>'رمز الحماية غير صالح']);
 $origin=(string)($_SERVER['HTTP_ORIGIN']??'');
 if($origin){
  $o=parse_url($origin);
  if(!is_array($o)||strcasecmp((string)($o['host']??''),(string)($_SERVER['HTTP_HOST']??''))!==0)out(403,['error'=>'مصدر الطلب غير مسموح']);
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
  if($action==='posts'){
   $posts=arbnt_read('posts');$posts=array_reverse($posts);
   $page=max(1,min(100000,(int)($_GET['page']??1)));
   out(200,['items'=>array_slice($posts,($page-1)*20,20),'total'=>count($posts),'page'=>$page]);
  }
  if($action==='comments'){
   $id=(string)($_GET['post']??'');
   out(200,['items'=>array_values(array_filter(arbnt_read('comments'),fn($c)=>($c['post_id']??'')===$id))]);
  }
  out(404,['error'=>'غير موجود']);
 }
 if($method!=='POST')out(405,['error'=>'طريقة غير مدعومة']);
 token();$b=body();
 if($action==='login'){
  throttle('login:'.($_SERVER['REMOTE_ADDR']??'unknown'),2);
  $name=clean($b['username']??'',32);$password=(string)($b['password']??'');
  $match=null;
  foreach(arbnt_read('users') as $u){if(hash_equals($u['username'],$name)&&password_verify($password,$u['password_hash'])){$match=$u;break;}}
  if(!$match)out(401,['error'=>'بيانات الدخول غير صحيحة']);
  session_regenerate_id(true);
  $_SESSION['arbnt_user']=['id'=>$match['id'],'username'=>$match['username'],'role'=>$match['role']??'member'];
  $_SESSION['arbnt_csrf']=bin2hex(random_bytes(24));
  out(200,['user'=>user(),'csrf'=>$_SESSION['arbnt_csrf']]);
 }
 if($action==='logout'){$_SESSION=[];session_regenerate_id(true);out(200,['ok'=>true]);}
 $u=requireUser();
 throttle('write:'.$u['id'],2);
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
