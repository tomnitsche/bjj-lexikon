if(!function_exists('gg_stg')){
function gg_stg(){
  static $s=null; if($s) return $s;
  $cfg=file_get_contents('/home/customer/www/staging2.greyground.de/public_html/wp-config.php');
  $g=function($k)use($cfg){preg_match("/define\(\s*'".$k."'\s*,\s*'([^']*)'/",$cfg,$m);return $m[1]??'';};
  $s=new wpdb($g('DB_USER'),$g('DB_PASSWORD'),$g('DB_NAME'),$g('DB_HOST'));
  return $s;
}
define('GG_STG_UP','/home/customer/www/staging2.greyground.de/public_html/wp-content/uploads/');
function gg_map(){ return get_option('gg_media_map',[]); }
// staging attachment -> live attachment
function gg_media($sid){
  $map=gg_map(); if(isset($map[$sid])) return $map[$sid];
  $s=gg_stg(); $a=$s->get_row($s->prepare("SELECT * FROM wp_posts WHERE ID=%d AND post_type='attachment'",$sid));
  if(!$a) return 0;
  $rel=$s->get_var($s->prepare("SELECT meta_value FROM wp_postmeta WHERE post_id=%d AND meta_key='_wp_attached_file'",$sid));
  if(!$rel) return 0;
  global $wpdb;
  $lid=(int)$wpdb->get_var($wpdb->prepare("SELECT post_id FROM wp_postmeta WHERE meta_key='_wp_attached_file' AND meta_value=%s LIMIT 1",$rel));
  $up=wp_upload_dir(); $dst=$up['basedir'].'/'.$rel;
  if(!$lid){
    if(!file_exists($dst)){ wp_mkdir_p(dirname($dst)); if(!@copy(GG_STG_UP.$rel,$dst)) return 0; }
    $lid=wp_insert_attachment(['post_title'=>$a->post_title,'post_mime_type'=>$a->post_mime_type,'post_status'=>'inherit','post_excerpt'=>$a->post_excerpt,'post_content'=>$a->post_content],$dst);
    require_once ABSPATH.'wp-admin/includes/image.php';
    wp_update_attachment_metadata($lid,wp_generate_attachment_metadata($lid,$dst));
    $alt=$s->get_var($s->prepare("SELECT meta_value FROM wp_postmeta WHERE post_id=%d AND meta_key='_wp_attachment_image_alt'",$sid));
    if($alt) update_post_meta($lid,'_wp_attachment_image_alt',$alt);
  }
  $map[$sid]=$lid; update_option('gg_media_map',$map,false); return $lid;
}
// copy any uploads file referenced by URL if missing
function gg_files($txt){
  $up=wp_upload_dir(); $n=0;
  if(preg_match_all('#https?://staging2\.greyground\.de/wp-content/uploads/([^\s"\'\)\\\\?]+)#',$txt,$m)){
    foreach(array_unique($m[1]) as $rel){ $dst=$up['basedir'].'/'.$rel; if(!file_exists($dst)&&file_exists(GG_STG_UP.$rel)){wp_mkdir_p(dirname($dst)); copy(GG_STG_UP.$rel,$dst); $n++;} }
  }
  return $n;
}
function gg_ids_in($txt){
  $ids=[];
  foreach(['/wp-image-(\d+)/','/"(?:id|mediaId|imageId)":(\d+)/'] as $re){ if(preg_match_all($re,$txt,$m)) $ids=array_merge($ids,$m[1]); }
  return array_unique(array_map('intval',$ids));
}
function gg_fix($txt,$pagemap=[]){
  $map=gg_map();
  $txt=preg_replace_callback('/wp-image-(\d+)/',function($m)use($map){return 'wp-image-'.($map[$m[1]]??$m[1]);},$txt);
  $txt=preg_replace_callback('/"(id|mediaId|imageId)":(\d+)/',function($m)use($map){return '"'.$m[1].'":'.($map[$m[2]]??$m[2]);},$txt);
  $txt=str_replace(['https://staging2.greyground.de','http://staging2.greyground.de','staging2.greyground.de'],['https://greyground.de','https://greyground.de','greyground.de'],$txt);
  return $txt;
}
}
