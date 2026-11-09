<?php
declare(strict_types=1);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
require dirname(__DIR__).'/app/bootstrap.php';
try {
 $config=arbnt_config();
 $resource=(string)($_GET['resource']??'');
 if($_SERVER['REQUEST_METHOD']!=='GET')arbnt_json(405,['error'=>'Method not allowed']);
 if(!in_array($resource,['posts','communities'],true))arbnt_json(404,['error'=>'Unknown resource']);
 $items=arbnt_read($resource);
 $page=max(1,min(10000,(int)($_GET['page']??1)));
 $limit=max(1,min(30,(int)($_GET['limit']??15)));
 arbnt_json(200,['site'=>$config['site_name'],'page'=>$page,'total'=>count($items),'items'=>array_slice($items,($page-1)*$limit,$limit)]);
}catch(Throwable $e){error_log('ARBNT API: '.$e->getMessage());arbnt_json(503,['error'=>'Service unavailable']);}
