<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
$docroot=realpath((string)($_SERVER['DOCUMENT_ROOT']??''));$root=realpath(__DIR__);
$base=$docroot&&$root&&($root===$docroot||str_starts_with($root,$docroot.'/'))?$docroot:__DIR__;
$private=dirname($base).'/arbnt-private';
$appOutside=dirname($base).'/arbnt-app';
$appPointer=__DIR__.'/.arbnt-app.php';
$local=__DIR__.'/storage';
$pointer=__DIR__.'/.arbnt-storage.php';
if(is_file($pointer)){ $selected=require $pointer; if(in_array($selected,[$private,$local],true))$private=$selected; }
$installed=is_file($private.'/installed.lock')||is_file($local.'/installed.lock')||is_file(dirname($base).'/arbnt-private/installed.lock');
$mode='';
if(session_status()!==PHP_SESSION_ACTIVE)session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Strict','use_strict_mode'=>true]);
if(empty($_SESSION['install_token']))$_SESSION['install_token']=bin2hex(random_bytes(24));
function esc(string $v):string{return htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$errors=[];$done=false;
if($_SERVER['REQUEST_METHOD']==='POST'&&!$installed){
 if(!hash_equals($_SESSION['install_token'],(string)($_POST['csrf']??'')))$errors[]='Invalid installation token';
 if(!extension_loaded('json')||!function_exists('password_hash'))$errors[]='PHP JSON/password support required';
 if(PHP_VERSION_ID<80300)$errors[]='PHP 8.3 or later required';
 $site=trim((string)($_POST['site']??''));$user=trim((string)($_POST['username']??''));$pass=(string)($_POST['password']??'');
 if(strlen($site)<2||strlen($site)>100)$errors[]='Site name must be 2-100 bytes';
 if(!preg_match('/^[a-zA-Z0-9_]{3,32}$/',$user))$errors[]='Username must be 3-32 letters, digits or underscores';
 if(strlen($pass)<12||strlen($pass)>256)$errors[]='Password must be 12-256 characters';
 if(!$errors&&is_dir($private)){ $existing=array_diff(scandir($private)?:[],['.','..']);if($existing)$errors[]='يوجد محتوى قديم في مجلد البيانات الخارجي. لن يتم استبداله. تحقق من النسخة السابقة أو خذ نسخة احتياطية أولاً.'; }
 if(!$errors){
  if(!is_file(__DIR__.'/app/core.php'))$errors[]='ملفات المحرك غير موجودة';
  if(is_link($appOutside)||(!is_dir($appOutside)&&!@mkdir($appOutside,0700,true)))$errors[]='لا يمكن إنشاء مجلد المحرك خارج الموقع';
  if(!$errors&&is_file($appPointer))$errors[]='يوجد ربط محرك سابق في هذا المجلد؛ لا يمكن التثبيت فوقه';
 }
 if(!$errors){
  if(is_dir($private)||@mkdir($private,0700,true)){$mode='outside';}
  else {
   $private=$local;$mode='inside';
   if(!is_dir($local)&&!@mkdir($local,0700,true))$errors[]='تعذر إنشاء مجلد التخزين';
   if(!$errors){
    $rules="Require all denied\n";
    $ht="# ARBNT protected storage\n<IfModule mod_authz_core.c>\n".$rules."</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n";
    if(@file_put_contents($local.'/.htaccess',$ht,LOCK_EX)===false)$errors[]='تعذر إنشاء حماية التخزين';
    $probe=bin2hex(random_bytes(12));$probeFile=$local.'/probe-'.$probe.'.txt';
    if(!$errors&&@file_put_contents($probeFile,$probe)===false)$errors[]='تعذر فحص التخزين';
    if(!$errors){
     $host=(string)($_SERVER['HTTP_HOST']??'');
     $base=rtrim(str_replace('\\\\','/',dirname((string)($_SERVER['SCRIPT_NAME']??'/'))),'/');
     $url='https://'.$host.$base.'/storage/'.basename($probeFile);
     $status=0;
     if(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'&&preg_match('/^[a-z0-9.-]+(?::[0-9]{1,5})?$/i',$host)){
      if(function_exists('curl_init')){
       $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>6,CURLOPT_FOLLOWLOCATION=>false]);curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);curl_close($ch);
      }elseif(ini_get('allow_url_fopen')){
       $ctx=stream_context_create(['http'=>['ignore_errors'=>true,'timeout'=>6,'follow_location'=>0]]);
       @file_get_contents($url,false,$ctx);foreach(($http_response_header??[]) as $header){if(preg_match('/^HTTP\\/\\S+ (\\d{3})/',$header,$m)){$status=(int)$m[1];break;}}
      }
     }
     @unlink($probeFile);
     if($status!==403)$errors[]='لم يثبت منع الوصول إلى التخزين الداخلي (HTTP 403). لن يستمر التثبيت حفاظاً على بياناتك.';
    }
   }
  }
 }
 if(!$errors){
  try{
   if(is_file($private.'/users.json'))throw new RuntimeException('Already initialized');
   $config=['site_name'=>$site,'site_description'=>'مساحة عربية للمنشورات والأفكار والنقاشات.','created_at'=>gmdate('c'),'version'=>'0.6.3'];
   foreach(['config'=>$config,'users'=>[['id'=>bin2hex(random_bytes(12)),'username'=>$user,'password_hash'=>password_hash($pass,PASSWORD_DEFAULT),'role'=>'admin','created_at'=>gmdate('c')]],'posts'=>[],'communities'=>[],'comments'=>[],'favorites'=>[],'notifications'=>[],'reports'=>[]] as $key=>$data){
    $file=$private.'/'.$key.'.json';
    $fp=@fopen($file,'x');if(!$fp)throw new RuntimeException('Cannot create '.$key);
    try{fwrite($fp,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));}finally{fclose($fp);}
    chmod($file,0600);
   }
   $sourceCore=__DIR__.'/app/core.php';$targetCore=$appOutside.'/core.php';
   if(is_file($targetCore)){$oldBackup=$appOutside.'/core-previous-'.gmdate('YmdHis').'-'.bin2hex(random_bytes(4)).'.bak';if(!@copy($targetCore,$oldBackup))throw new RuntimeException('Cannot back up existing app engine');@chmod($oldBackup,0600);}
   if(!@copy($sourceCore,$targetCore)||hash_file('sha256',$sourceCore)!==hash_file('sha256',$targetCore))throw new RuntimeException('App core verification failed');
   @chmod($targetCore,0600);
   $appValue='<?php return '.var_export($appOutside,true).';';
   if(@file_put_contents($appPointer,$appValue,LOCK_EX)!==strlen($appValue))throw new RuntimeException('Cannot activate private app');
   @chmod($appPointer,0600);
   if(file_put_contents($private.'/installed.lock',gmdate('c'),LOCK_EX)===false)throw new RuntimeException('Lock failed');
   if(@file_put_contents($pointer,'<?php return '.var_export($private,true).';',LOCK_EX)===false)throw new RuntimeException('Cannot save storage location');
   @chmod($pointer,0600);
   chmod($private.'/installed.lock',0600);$done=true;@unlink(__DIR__.'/app/core.php');
  }catch(Throwable $e){$errors[]='Installation incomplete: '.esc($e->getMessage()).'. Remove incomplete private files before retrying.';}
 }
}
?><!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>تثبيت ARBNT</title><style>body{font:16px/1.8 system-ui;background:#f5f7fa;color:#202b36;margin:0;padding:5vh 16px}main{max-width:520px;background:white;border:1px solid #ddd;border-radius:12px;padding:28px;margin:auto}label{display:block;margin:14px 0}input{display:block;width:100%;padding:12px;border:1px solid #bbb;border-radius:6px;box-sizing:border-box}button{background:#075e9e;color:white;border:0;border-radius:6px;padding:12px 22px;cursor:pointer}.error{color:#9b2020}</style><main><h1>ARBNT · شبكة العرب</h1><p>معالج التثبيت الأولي — PHP وJSON دون قاعدة بيانات</p><p>يحفظ المثبت محرك PHP خارج المجلد العام، ويفضل تخزين البيانات خارج الموقع. إذا تعذر ذلك، يستخدم مجلد storage المحمي فقط بعد اختبار منع الوصول إليه فعلياً. إذا لم تثبت الحماية، يتوقف التثبيت.</p><?php if($done):?><p>موقع التخزين: <?=esc($mode==='outside'?'خارج الموقع — حماية أعلى':'داخل storage — حماية قياسية تم اختبارها')?></p><?php endif;?><?php if($installed||$done):?><p>تم التثبيت أو سبق تثبيت الموقع. أغلق صفحة التثبيت واحذف install.php بعد التحقق.</p><a href="index.html">فتح الموقع</a><?php if($installed&&!is_file($appPointer)):?><p>إذا حذفت مجلد الموقع وبقيت البيانات الخارجية، فلا تعِد التثبيت. استخدم <a href="recover.php">استعادة الربط بالتثبيت السابق</a>.</p><?php endif;?><?php else:foreach($errors as $e):?><p class="error"><?=esc($e)?></p><?php endforeach;?><form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['install_token'])?>"><label>اسم المجتمع<input name="site" required maxlength="100" value="<?=esc((string)($_POST['site']??'شبكة العرب'))?>"></label><label>اسم المدير<input name="username" required minlength="3" maxlength="32" autocomplete="username"></label><label>كلمة المرور (12 حرفاً على الأقل)<input type="password" name="password" required minlength="12" autocomplete="new-password"></label><button>تثبيت</button></form><?php endif;?></main></html>
