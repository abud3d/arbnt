<?php
declare(strict_types=1);
function arbnt_data_dir(): string {
 $root=dirname(__DIR__);
 $pointer=$root.'/.arbnt-storage.php';
 $path=dirname($root).'/arbnt-private';
 if(is_file($pointer)){
  $chosen=require $pointer;
  if(!is_string($chosen)||!in_array($chosen,[$path,$root.'/storage'],true))throw new RuntimeException('Invalid storage pointer');
  $path=$chosen;
 }
 if (!is_dir($path)) throw new RuntimeException('Storage is not initialized');
 return $path;
}
function arbnt_config(): array {
 $f=arbnt_data_dir().'/config.json';
 $v=json_decode((string)file_get_contents($f),true);
 if(!is_array($v))throw new RuntimeException('Invalid configuration');
 return $v;
}
function arbnt_read(string $name): array {
 if(!preg_match('/^[a-z]+$/',$name))throw new InvalidArgumentException('Invalid collection');
 $f=arbnt_data_dir().'/'.$name.'.json';
 if(!is_file($f))return [];
 $data=json_decode((string)file_get_contents($f),true);
 return is_array($data)?$data:[];
}
function arbnt_write(string $name, callable $change): array {
 if(!preg_match('/^[a-z]+$/',$name))throw new InvalidArgumentException('Invalid collection');
 $path=arbnt_data_dir();$lock=fopen($path.'/'.$name.'.lock','c');
 if(!$lock||!flock($lock,LOCK_EX))throw new RuntimeException('Storage busy');
 try {
  $old=arbnt_read($name);$new=$change($old);
  if(!is_array($new))throw new RuntimeException('Invalid data');
  $tmp=tempnam($path,'.arbnt-');
  if($tmp===false)throw new RuntimeException('Storage unavailable');
  try {
   if(file_put_contents($tmp,json_encode($new,JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT|JSON_THROW_ON_ERROR))===false)throw new RuntimeException('Write failed');
   chmod($tmp,0600);
   if(!rename($tmp,$path.'/'.$name.'.json'))throw new RuntimeException('Commit failed');
  } finally {if(is_file($tmp))unlink($tmp);}
  return $new;
 }finally{flock($lock,LOCK_UN);fclose($lock);}
}
function arbnt_json(int $code,array $payload): never {
 http_response_code($code);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');
 echo json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR);exit;
}
