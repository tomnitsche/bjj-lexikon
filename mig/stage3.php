$s=gg_stg(); global $wpdb; $out='';
// MC4WP form
$fm=get_option('gg_mc4wp_form',0);
if(!$fm){ $f=$s->get_row("SELECT * FROM wp_posts WHERE ID=4742");
  $fm=wp_insert_post(['post_type'=>$f->post_type,'post_status'=>$f->post_status,'post_title'=>$f->post_title,'post_content'=>wp_slash($f->post_content)]);
  foreach($s->get_results("SELECT meta_key,meta_value FROM wp_postmeta WHERE post_id=4742") as $m){ if($m->meta_key[0]==='_'&&strpos($m->meta_key,'_mc4wp')!==0) continue; update_post_meta($fm,$m->meta_key,wp_slash(maybe_unserialize($m->meta_value))); }
  update_option('gg_mc4wp_form',$fm,false); }
$out.="form $fm\n";
$ids=[25,26,27,37,40,41,42,43,44,45,49,50,52,53,54];
$sm=get_option('gg_snip_map',[]);
$cols=$wpdb->get_col("SHOW COLUMNS FROM wp_snippets");
foreach($ids as $sid){
  $r=(array)$s->get_row("SELECT * FROM wp_snippets WHERE id=$sid",ARRAY_A);
  $code=gg_fix($r['code']);
  if($sid==37) $code=str_replace('$fid = 4742','$fid = '.$fm,$code);
  // collision check
  if(!isset($sm[$sid]) && preg_match_all('/^\s*function\s+(\w+)\s*\(/m',$code,$m)){ $cl=array_filter($m[1],'function_exists'); if($cl){ $out.="$sid COLLISION ".implode(',',$cl)."\n"; continue; } }
  $row=[]; foreach($r as $k=>$v){ if($k==='id'||!in_array($k,$cols,true)) continue; $row[$k]=$v; }
  $row['code']=$code; $row['modified']=current_time('mysql',1);
  $ex=$sm[$sid]??(int)$wpdb->get_var($wpdb->prepare("SELECT id FROM wp_snippets WHERE name=%s",$r['name']));
  if($ex){ $wpdb->update('wp_snippets',$row,['id'=>$ex]); $lid=$ex; } else { $wpdb->insert('wp_snippets',$row); $lid=$wpdb->insert_id; }
  $sm[$sid]=$lid; update_option('gg_snip_map',$sm,false);
  $out.="$sid>$lid a{$row['active']}\n";
}
if(function_exists('\Code_Snippets\clean_snippets_cache')) \Code_Snippets\clean_snippets_cache('wp_snippets');
wp_cache_flush();
echo $out;
