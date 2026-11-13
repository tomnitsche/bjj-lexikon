if(!function_exists('gg_fixr')){function gg_fixr($v){ if(is_string($v)) return gg_fix($v); if(is_array($v)){foreach($v as $k=>$x)$v[$k]=gg_fixr($x);} return $v; }}
$s=gg_stg(); $out='';
$pm=get_option('gg_page_map',[]);
$order=[86,1186,4036,692,4692,4762,4763,4764,4695,852,377,1396,438,1110,1112,1113,4744];
$skipmeta=['_edit_lock','_edit_last','_wp_old_slug','_wp_old_date','_thumbnail_id','_aioseo_title','_aioseo_description'];
foreach($order as $sid){
  $sp=$s->get_row("SELECT * FROM wp_posts WHERE ID=$sid"); if(!$sp){$out.="$sid missing\n";continue;}
  $content=gg_fix($sp->post_content);
  $lid=$pm[$sid]??0;
  if(!$lid){ $ex=get_post($sid); if($ex && $ex->post_type==='page' && $ex->post_name===$sp->post_name) $lid=$sid; }
  if(!$lid){ $ex=get_page_by_path($sp->post_parent?get_post_field('post_name',$pm[$sp->post_parent]??0).'/'.$sp->post_name:$sp->post_name); if($ex) $lid=$ex->ID; }
  $parent=$sp->post_parent?($pm[$sp->post_parent]??0):0;
  $arr=['post_title'=>$sp->post_title,'post_content'=>wp_slash($content),'post_excerpt'=>wp_slash($sp->post_excerpt),'post_name'=>$sp->post_name,'post_status'=>'publish','post_parent'=>$parent,'menu_order'=>$sp->menu_order,'post_type'=>'page'];
  if($lid){ $arr['ID']=$lid; $r=wp_update_post($arr,true); } else { $r=wp_insert_post($arr,true); }
  if(is_wp_error($r)){ $out.="$sid ERR ".$r->get_error_message()."\n"; continue; }
  $lid=$r; $pm[$sid]=$lid; update_option('gg_page_map',$pm,false);
  foreach($s->get_results("SELECT meta_key,meta_value FROM wp_postmeta WHERE post_id=$sid") as $m){
    if(in_array($m->meta_key,$skipmeta,true)) continue;
    $v=maybe_unserialize($m->meta_value); $v=gg_fixr($v); update_post_meta($lid,$m->meta_key,wp_slash($v));
  }
  $th=$s->get_var("SELECT meta_value FROM wp_postmeta WHERE post_id=$sid AND meta_key='_thumbnail_id'");
  if($th){ $l=gg_media($th); if($l) set_post_thumbnail($lid,$l); }
  // AIOSEO
  $a=$s->get_row("SELECT * FROM wp_aioseo_posts WHERE post_id=$sid");
  if($a && class_exists('\AIOSEO\Plugin\Common\Models\Post')){
    $ap=\AIOSEO\Plugin\Common\Models\Post::getPost($lid); $ap->post_id=$lid;
    foreach(['title','description','og_title','og_description','twitter_title','twitter_description','robots_default','robots_noindex','robots_nofollow','keyphrases','schema'] as $c){ if(property_exists($a,$c)) $ap->$c=$a->$c===null?null:gg_fix($a->$c); }
    $ap->save();
  }
  $out.="$sid>$lid ".strlen($content)."\n";
}
echo $out;
