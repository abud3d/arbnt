<?php
declare(strict_types=1);
header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');
session_start(['cookie_httponly'=>true,'cookie_samesite'=>'Strict','use_strict_mode'=>true]);
$root=__DIR__;$doc=realpath((string)($_SERVER['DOCUMENT_ROOT']??''));$real=realpath($root);$base=$doc&&$real&&($real===$doc||str_starts_with($real,$doc.'/'))?$doc:$root;
$parent=dirname($base);$data=$parent.'/arbnt-private';$app=$parent.'/arbnt-app';$errors=[];$success=false;
if(empty($_SESSION['arbnt_recovery_csrf']))$_SESSION['arbnt_recovery_csrf']=bin2hex(random_bytes(24));
function e(string $v):string{return htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!hash_equals($_SESSION['arbnt_recovery_csrf'],(string)($_POST['csrf']??'')))$errors[]='انتهت جلسة النموذج';
 if(!is_dir($data)||!is_file($data.'/installed.lock')||!is_file($data.'/users.json'))$errors[]='لم يتم العثور على تثبيت سابق في مجلد البيانات الخارجي';
 if(is_link($data)||is_link($app))$errors[]='مسار غير آمن';
 if(is_file($root.'/.arbnt-app.php')||is_file($root.'/.arbnt-storage.php'))$errors[]='ملفات الربط موجودة بالفعل؛ لا حاجة للاستعادة';
 if(!$errors){
  $users=json_decode((string)@file_get_contents($data.'/users.json'),true);$name=(string)($_POST['username']??'');$pass=(string)($_POST['password']??'');$valid=false;
  if(is_array($users))foreach($users as $u){if(($u['role']??'')==='admin'&&hash_equals((string)($u['username']??''),$name)&&password_verify($pass,(string)($u['password_hash']??''))){$valid=true;break;}}
  if(!$valid)$errors[]='بيانات المدير غير صحيحة';
 }
 if(!$errors){
  $core=$app.'/core.php';$local=$root.'/app/core.php';
  if(!is_file($core)&&!is_file($local))$errors[]='ملف محرك PHP غير موجود';
  if(!$errors&&!is_file($core)){if(!is_dir($app)&&!@mkdir($app,0700,true))$errors[]='تعذر إنشاء مجلد المحرك';elseif(!@copy($local,$core))$errors[]='تعذر نسخ المحرك';else @chmod($core,0600);}
  if(!$errors){
   $p1='<?php return '.var_export($app,true).';';$p2='<?php return '.var_export($data,true).';';
   $tmp1=$root.'/.arbnt-app-'.bin2hex(random_bytes(8)).'.tmp';$tmp2=$root.'/.arbnt-storage-'.bin2hex(random_bytes(8)).'.tmp';
   if(@file_put_contents($tmp1,$p1,LOCK_EX)!==strlen($p1)||@file_put_contents($tmp2,$p2,LOCK_EX)!==strlen($p2))$errors[]='تعذر كتابة ملفات الربط';
   elseif(!@rename($tmp1,$root.'/.arbnt-app.php'))$errors[]='تعذر تفعيل ربط المحرك';
   elseif(!@rename($tmp2,$root.'/.arbnt-storage.php'))$errors[]='تعذر تفعيل ربط البيانات';
   else{$success=true;@chmod($root.'/.arbnt-app.php',0600);@chmod($root.'/.arbnt-storage.php',0600);}
   @unlink($tmp1);@unlink($tmp2);
  }
 }
}
?><!doctype html><html lang="ar" dir="rtl"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>استعادة ربط ARBNT</title><style>body{font:15px/1.9 system-ui;background:#f5f7fa;color:#1c2938;margin:0;padding:6vh 16px}main{max-width:540px;margin:auto;background:white;border:1px solid #d9e1e8;border-radius:12px;padding:26px}input{width:100%;padding:11px;margin:6px 0 15px;box-sizing:border-box;border:1px solid #c8d2dd;border-radius:7px}button{background:#1166a1;color:white;border:0;border-radius:7px;padding:11px 18px}p.error{color:#a22}</style><main><h1>ARBNT · استعادة التثبيت</h1><p>تُستخدم هذه الصفحة فقط عند حذف مجلد الموقع مع بقاء بيانات <code>arbnt-private</code> خارج <code>public_html</code>. لا تنشئ أعضاء أو تمسح منشورات.</p><?php foreach($errors as $error):?><p class="error"><?=e($error)?></p><?php endforeach;?><?php if($success):?><p>تمت استعادة ملفات الربط. افتح المجتمع وتحقق من بياناتك، ثم احذف <code>recover.php</code> من الموقع.</p><a href="community.html">فتح المجتمع</a><?php else:?><form method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['arbnt_recovery_csrf'])?>"><label>اسم حساب المدير<input name="username" required autocomplete="username"></label><label>كلمة مرور المدير السابقة<input type="password" name="password" required autocomplete="current-password"></label><button>استعادة الربط دون حذف البيانات</button></form><?php endif;?></main></html>