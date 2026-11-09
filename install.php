<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
$private=dirname(__DIR__).'/arbnt-private';
$installed=is_file($private.'/installed.lock');
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
 if(!is_dir($private)&&!@mkdir($private,0700,true))$errors[]='Cannot create private storage above web root';
 if(!$errors){
  $real=realpath($private);$root=realpath(__DIR__);
  if(!$real||!$root||str_starts_with($real,$root.'/')||$real===$root)$errors[]='Private storage must be outside public directory';
 }
 if(!$errors){
  try{
   if(is_file($private.'/users.json'))throw new RuntimeException('Already initialized');
   $config=['site_name'=>$site,'created_at'=>gmdate('c'),'version'=>'0.3.0'];
   foreach(['config'=>$config,'users'=>[['id'=>bin2hex(random_bytes(12)),'username'=>$user,'password_hash'=>password_hash($pass,PASSWORD_DEFAULT),'role'=>'admin','created_at'=>gmdate('c')]],'posts'=>[],'communities'=>[],'comments'=>[]] as $key=>$data){
    $file=$private.'/'.$key.'.json';
    $fp=@fopen($file,'x');if(!$fp)throw new RuntimeException('Cannot create '.$key);
    try{fwrite($fp,json_encode($data,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR));}finally{fclose($fp);}
    chmod($file,0600);
   }
   if(file_put_contents($private.'/installed.lock',gmdate('c'),LOCK_EX)===false)throw new RuntimeException('Lock failed');
   chmod($private.'/installed.lock',0600);$done=true;
  }catch(Throwable $e){$errors[]='Installation incomplete: '.esc($e->getMessage()).'. Remove incomplete private files before retrying.';}
 }
}
?><!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>تثبيت ARBNT</title><style>body{font:16px/1.8 system-ui;background:#f5f7fa;color:#202b36;margin:0;padding:5vh 16px}main{max-width:520px;background:white;border:1px solid #ddd;border-radius:12px;padding:28px;margin:auto}label{display:block;margin:14px 0}input{display:block;width:100%;padding:12px;border:1px solid #bbb;border-radius:6px;box-sizing:border-box}button{background:#075e9e;color:white;border:0;border-radius:6px;padding:12px 22px;cursor:pointer}.error{color:#9b2020}</style><main><h1>ARBNT · شبكة العرب</h1><p>معالج التثبيت الأولي — PHP وJSON دون قاعدة بيانات</p><?php if($installed||$done):?><p>تم التثبيت أو سبق تثبيت الموقع. أغلق صفحة التثبيت واحذف install.php بعد التحقق.</p><a href="index.html">فتح الموقع</a><?php else:foreach($errors as $e):?><p class="error"><?=esc($e)?></p><?php endforeach;?><form method="post"><input type="hidden" name="csrf" value="<?=esc($_SESSION['install_token'])?>"><label>اسم المجتمع<input name="site" required maxlength="100" value="<?=esc((string)($_POST['site']??'شبكة العرب'))?>"></label><label>اسم المدير<input name="username" required minlength="3" maxlength="32" autocomplete="username"></label><label>كلمة المرور (12 حرفاً على الأقل)<input type="password" name="password" required minlength="12" autocomplete="new-password"></label><button>تثبيت</button></form><?php endif;?></main></html>
